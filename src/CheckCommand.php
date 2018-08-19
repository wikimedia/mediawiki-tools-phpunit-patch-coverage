<?php
/**
 * Copyright (C) 2018 Kunal Mehta <legoktm@member.fsf.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace MediaWiki\Tool\PatchCoverage;

use Legoktm\CloverDiff\CloverXml;
use Legoktm\CloverDiff\Differ;
use Legoktm\CloverDiff\DiffPrinter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wikimedia\ScopedCallback;

/**
 * Assumes cwd is the git repository
 */
class CheckCommand extends Command {

	/**
	 * Once this class is destructed, all of these
	 * will get run
	 *
	 * @var ScopedCallback[]
	 */
	private $scopedCallbacks = [];

	protected function configure() {
		$this->setName( 'check' )
			->addOption(
				'sha1',
				null, InputOption::VALUE_OPTIONAL,
				'Reference of commit to test against',
				'HEAD'
			)->addOption(
				'test-dir',
				null, InputOption::VALUE_REQUIRED,
				'Directory tests are in (relative to git root)',
				'tests/phpunit'
			)->addOption(
				'html',
				null, InputOption::VALUE_OPTIONAL,
				'Location to save an HTML report'
			)->addOption(
				'command',
				null, InputOption::VALUE_REQUIRED,
				'Command to run to execute PHPUnit tests',
				'php vendor/bin/phpunit'
			);
	}

	private function absolutify( array $paths ) {
		$newPaths = [];
		foreach ( $paths as $path ) {
			$newPaths[] = getcwd() . DIRECTORY_SEPARATOR . $path;
		}

		return $newPaths;
	}

	/**
	 * @param array $tests
	 *
	 * @return string|false regex or false if no files to test
	 */
	private function getFilterRegex( array $tests ) {
		// PHPUnit requires filename to be the same as the classname,
		// so we can use that as a shortcut.
		$filter = [];
		foreach ( $tests as $test ) {
			$pathInfo = pathinfo( $test );
			if ( $pathInfo['extension'] !== 'php' ) {
				// Not a PHP file
				continue;
			}
			$testClass = $pathInfo['filename'];
			// Strip TestBase suffix to make abstract classes work if they have
			// the same base names (T193107).
			$testClass = preg_replace( '/TestBase$/', '', $testClass );
			$filter[] = preg_quote( $testClass );
		}

		if ( !$filter ) {
			return false;
		}

		return escapeshellarg( '/' . implode( '|', $filter ) . '/' );
	}

	private function runTests( $output, $command, $regex ) {
		// TODO: Run this in parallel?
		$clover = tempnam( sys_get_temp_dir(), 'clover' );
		$cmd = "$command --coverage-clover $clover --filter $regex";
		$process = new CommandProcess( $cmd );
		// Disable timeout
		$process->setTimeout( null );
		// Run and buffer output for progress
		$process->runWithOutput( $output );

		$this->scopedCallbacks[] = new ScopedCallback(
			function () use ( $clover ) {
				unlink( $clover );
			}
		);

		return $clover;
	}

	protected function saveFiles( CloverXml $cloverXml ) {
		$files = [];
		foreach ( $cloverXml->getFiles( $cloverXml::LINES ) as $fname => $lines ) {
			// It has at least one covered line
			if ( !array_sum( $lines ) ) {
				continue;
			}
			$contents = file_get_contents( $fname );
			$parts = explode( "\n", $contents );
			foreach ( $parts as $i => &$line ) {
				if ( isset( $lines[$i + 1] ) && $lines[$i + 1] ) {
					$line = "✓ $line";
				} elseif ( isset( $lines[$i + 1] ) ) {
					// Supposed to be covered, but it isn't
					$line = "✘ $line";
				} else {
					// Just stick some spaces in front so it lines up
					$line = "  $line";
				}
			}
			unset( $line );
			$files[$fname] = $parts;
		}

		return $files;
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$git = new Git( getcwd() );
		$sha1 = $input->getOption( 'sha1' );
		$current = $git->getSha1( 'HEAD' );
		$notMerge = $git->findNonMergeCommit( $sha1 );
		$output->writeln( "Finding coverage difference in $notMerge" );
		// To reset back to once we're done, use a scoped callback so this
		// still happens regardless of exceptions
		$this->scopedCallbacks[] = new ScopedCallback(
			function () use ( $git, $current ) {
				$git->checkout( $current );
			}
		);
		$git->checkout( $notMerge );
		$changed = $git->getChangedFiles( $notMerge );
		$changedFiles = new GitChanged();
		$changedTests = new GitChanged();
		$testDir = $input->getOption( 'test-dir' );
		foreach ( (array)$changed as $type => $files ) {
			foreach ( $files as $file ) {
				if ( strpos( $file, $testDir ) === 0 ) {
					$changedTests->{$type}[] = $file;
				} else {
					$changedFiles->{$type}[] = $file;
				}
			}
		}

		$classFinder = new ClassFinder();
		$modifiedClasses = $classFinder->find( array_merge(
			$changedFiles->added,
			$changedFiles->modified
		) );

		// And find the corresponding tests...
		$testFinder = new TestFinder( $testDir );
		$foundTests = $testFinder->find( $modifiedClasses );
		$testsToRun = array_unique( array_merge(
			$foundTests,
			$this->absolutify( $changedTests->added ),
			$this->absolutify( $changedTests->modified )
		) );
		$filterRegex = $this->getFilterRegex( $testsToRun );

		// TODO: We need to trim suite.xml coverage filter, because that takes forever

		$command = $input->getOption( 'command' );
		if ( $filterRegex ) {
			// Run it!
			$newClover = new CloverXml( $this->runTests( $output, $command, $filterRegex ) );
			$newFiles = $this->saveFiles( $newClover );
		} else {
			$newClover = null;
			$newFiles = [];
		}

		// Now we want to run tests for the old stuff.
		$git->checkout( 'HEAD~1' );
		$modifiedOldClasses = $classFinder->find( array_merge(
			$changedFiles->modified,
			$changedFiles->deleted
		) );
		$foundOldTests = $testFinder->find( $modifiedOldClasses );
		$testsOldToRun = array_unique( array_merge(
			$foundOldTests,
			$this->absolutify( $changedTests->modified ),
			$this->absolutify( $changedTests->deleted )
		) );
		$filterOldRegex = $this->getFilterRegex( $testsOldToRun );
		if ( $filterOldRegex ) {
			$oldClover = new CloverXml( $this->runTests( $output, $command, $filterOldRegex ) );
			$oldFiles = $this->saveFiles( $oldClover );
		} else {
			$oldClover = null;
			$oldFiles = [];
		}

		if ( !$filterRegex && !$filterOldRegex ) {
			$output->writeln(
				'<error>Could not find any tests to run.</error>'
			);
			return 0;
		}

		$diff = ( new Differ() )->diff( $oldClover, $newClover );
		$printer = new DiffPrinter( $output );
		$lowered = $printer->show( $diff );
		$reportPath = $input->getOption( 'html' );
		if ( $reportPath ) {
			$html = ( new HtmlReport() )->report( $diff, $oldFiles, $newFiles );
			file_put_contents( $reportPath, $html );
		}

		return $lowered ? 1 : 0;
	}

}
