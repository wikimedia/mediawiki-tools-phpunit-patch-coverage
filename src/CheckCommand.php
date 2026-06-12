<?php
declare( strict_types = 1 );

/**
 * Copyright (C) 2018 Kunal Mehta <legoktm@debian.org>
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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wikimedia\CloverDiff\CloverXml;
use Wikimedia\CloverDiff\Differ;
use Wikimedia\CloverDiff\DiffPrinter;
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
	private array $scopedCallbacks = [];

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

	private function absolutify( array $paths ): array {
		$newPaths = [];
		foreach ( $paths as $path ) {
			$newPaths[] = getcwd() . DIRECTORY_SEPARATOR . $path;
		}

		return $newPaths;
	}

	/**
	 * @return string|false regex or false if no files to test
	 */
	private function getFilterRegex( array $tests ): string|false {
		// PHPUnit requires filename to be the same as the classname,
		// so we can use that as a shortcut.
		$filter = [];

		foreach ( $tests as $test ) {
			$pathInfo = pathinfo( $test );
			if ( ( $pathInfo['extension'] ?? '' ) !== 'php' ) {
				// Not a PHP file
				continue;
			}
			$testClass = $pathInfo['filename'];
			// Strip TestBase suffix to make abstract classes work if they have
			// the same base names (T193107).
			$testClass = preg_replace( '/TestBase$/', '', $testClass );
			$filter[] = preg_quote( $testClass, '/' );
		}

		if ( !$filter ) {
			return false;
		}

		return escapeshellarg( '/' . implode( '|', $filter ) . '/' );
	}

	private function runTests( OutputInterface $output, string $command, string $regex ): string|false {
		// TODO: Run this in parallel?
		$clover = tempnam( sys_get_temp_dir(), 'clover' );
		$process = CommandProcess::fromShellCommandline(
			"$command --coverage-clover $clover --filter $regex"
		);
		// Disable timeout
		$process->setTimeout( null );
		// Run and buffer output for progress
		$process->runWithOutput( $output );

		$this->scopedCallbacks[] = new ScopedCallback(
			static function () use ( $clover ) {
				unlink( $clover );
			}
		);

		return $clover;
	}

	protected function saveFiles( CloverXml $cloverXml, string $cloverPath ): array {
		$lineMap = $cloverXml->getFiles( $cloverXml::LINES );
		// CloverXml::getFiles() returns paths relative to the directory common
		// to every covered file, which can be deeper than the working
		// directory (e.g. all files happen to live under includes/). Recover
		// the prefix it stripped so the relative paths can be read back as real
		// files instead of failing to open (T425807).
		$prefix = $this->strippedPathPrefix( $cloverPath, array_key_first( $lineMap ) );
		$files = [];
		foreach ( $lineMap as $fname => $lines ) {
			// It has at least one covered line
			if ( !array_sum( $lines ) ) {
				continue;
			}
			$path = $prefix . $fname;
			if ( !is_readable( $path ) ) {
				continue;
			}
			$contents = file_get_contents( $path );
			if ( $contents === false ) {
				continue;
			}
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
			// Key by the relative path so it matches Differ/HtmlReport, which
			// also work off CloverXml::getFiles().
			$files[$fname] = $parts;
		}

		return $files;
	}

	/**
	 * Recover the directory prefix that CloverXml::getFiles() strips off the
	 * absolute file paths. The same prefix is removed from every path, so it
	 * can be derived from a single absolute/relative pair and prepended to the
	 * relative paths to turn them back into readable files.
	 *
	 * @param string $cloverPath Path to the clover XML the report was read from
	 * @param string|null $firstRelative First path returned by getFiles(), or
	 *   null when there are no files
	 * @return string Prefix to prepend to the relative paths (may be empty)
	 */
	private function strippedPathPrefix( string $cloverPath, ?string $firstRelative ): string {
		if ( $firstRelative === null ) {
			return '';
		}
		$previous = libxml_use_internal_errors( true );
		$xml = simplexml_load_file( $cloverPath );
		libxml_use_internal_errors( $previous );
		if ( $xml === false ) {
			return '';
		}
		// getFiles() walks the file nodes in document order, so the first one
		// corresponds to $firstRelative.
		$fileNodes = $xml->xpath( '//file[@name]' );
		if ( !$fileNodes ) {
			return '';
		}
		$firstAbsolute = (string)$fileNodes[0]['name'];
		if ( $firstRelative === '' ) {
			// The whole path was stripped (a single covered file)
			return $firstAbsolute;
		}
		if ( str_ends_with( $firstAbsolute, $firstRelative ) ) {
			return substr( $firstAbsolute, 0, -strlen( $firstRelative ) );
		}
		// Couldn't line them up; leave the paths untouched
		return '';
	}

	protected function filterPaths( array $files, string $testDir ): array {
		$changedFiles = [];
		$changedTests = [];
		foreach ( $files as $file ) {
			if ( str_starts_with( $file, $testDir ) ) {
				$changedTests[] = $file;
			} else {
				$changedFiles[] = $file;
			}
		}

		return [ $changedFiles, $changedTests ];
	}

	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$git = new Git( getcwd() );
		$sha1 = $input->getOption( 'sha1' );
		$current = $git->getSha1( 'HEAD' );
		$notMerge = $git->findNonMergeCommit( $sha1 );
		$output->writeln( "Finding coverage difference in $notMerge" );
		// To reset back to once we're done, use a scoped callback so this
		// still happens regardless of exceptions
		$this->scopedCallbacks[] = new ScopedCallback(
			static function () use ( $git, $current ) {
				$git->checkout( $current );
			}
		);
		$git->checkout( $notMerge );
		$testDir = $input->getOption( 'test-dir' );
		$changed = $git->getChangedFiles( $notMerge );
		[ $changedFiles, $changedTests ] = $this->filterPaths(
			$changed->getNewFiles(), $testDir
		);

		$classFinder = new ClassFinder();
		$modifiedClasses = $classFinder->find( $changedFiles );

		// And find the corresponding tests...
		$testFinder = new TestFinder( $testDir );
		$foundTests = $testFinder->find( $modifiedClasses );
		$testsToRun = array_unique( array_merge(
			$foundTests,
			$this->absolutify( $changedTests )
		) );
		$filterRegex = $this->getFilterRegex( $testsToRun );

		// TODO: We need to trim the coverage filter, because that takes forever

		$command = $input->getOption( 'command' );
		if ( $filterRegex ) {
			// Run it!
			$newCloverPath = $this->runTests( $output, $command, $filterRegex );
			$newClover = new CloverXml( $newCloverPath );
			$newFiles = $this->saveFiles( $newClover, $newCloverPath );
		} else {
			$newClover = null;
			$newFiles = [];
		}

		// Now we want to run tests for the old stuff.
		$git->checkout( 'HEAD~1' );
		[ $changedOldFiles, $changedOldTests ] = $this->filterPaths(
			$changed->getPreviousFiles(), $testDir
		);

		$modifiedOldClasses = $classFinder->find( $changedOldFiles );
		$foundOldTests = $testFinder->find( $modifiedOldClasses );
		$testsOldToRun = array_unique( array_merge(
			$foundOldTests,
			$this->absolutify( $changedOldTests )
		) );
		$filterOldRegex = $this->getFilterRegex( $testsOldToRun );
		if ( $filterOldRegex ) {
			$oldCloverPath = $this->runTests( $output, $command, $filterOldRegex );
			$oldClover = new CloverXml( $oldCloverPath );
			$oldFiles = $this->saveFiles( $oldClover, $oldCloverPath );
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

		// (T419359) Add a newline after the PHPUnit output for clarity
		$output->writeln( '' );

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
