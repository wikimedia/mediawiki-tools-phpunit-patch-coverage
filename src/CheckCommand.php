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

use Legoktm\CloverDiff\Differ;
use Legoktm\CloverDiff\DiffPrinter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Wikimedia\ScopedCallback;

/**
 * Assumes cwd is the git repository
 */
class CheckCommand extends Command {
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

	private function getFilterRegex( $tests ) {
		// PHPUnit requires filename to be the same as the classname,
		// so we can use that as a shortcut.
		$filter = [];
		foreach ( $tests as $test ) {
			$base = basename( $test );
			// Strip extension
			$filter[] = substr( $base, 0, strlen( $base ) - 4 );
		}

		return '\'/' . implode( '|', $filter ) . '/\'';
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {
		$git = new Git( getcwd() );
		$sha1 = $input->getOption( 'sha1' );
		$current = $git->getSha1( 'HEAD' );
		$notMerge = $git->findNonMergeCommit( $sha1 );
		$output->writeln( "Finding coverage difference in $notMerge" );
		// To reset back to once we're done, use a scoped callback so this
		// still happens regardless of exceptions
		$lock = new ScopedCallback( function () use ( $git, $current ) {
			$git->checkout( $current );
		} );
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

		// TODO: We need to trim suite.xml coverage filter, because that takes forever

		if ( $testsToRun ) {
			// Run it!
			// TODO: Run this in parallel?
			$newClover = tempnam( sys_get_temp_dir(), 'old-clover' );
			$cmd = $input->getOption( 'command' ) .
				" --coverage-clover $newClover --filter " .
				$this->getFilterRegex( $testsToRun );
			$process = new CommandProcess( $cmd );
			// Disable timeout
			$process->setTimeout( null );
			// Run and buffer output for progress
			$process->runWithOutput( $output );
		} else {
			$newClover = null;
		}

		// Now we want to run tests for the old stuff.
		$process = new Process( 'git checkout HEAD~1' );
		$process->mustRun();
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
		if ( $testsOldToRun ) {
			$oldClover = tempnam( sys_get_temp_dir(), 'new-clover' );
			$cmd = $input->getOption( 'command' ) .
				" --coverage-clover $oldClover --filter " .
				$this->getFilterRegex( $testsOldToRun );
			$process = new CommandProcess( $cmd );
			// Disable timeout
			$process->setTimeout( null );
			// Run and buffer output for progress
			$process->runWithOutput( $output );
		} else {
			$oldClover = null;
		}

		$diff = ( new Differ() )->diff( $oldClover, $newClover );
		$printer = new DiffPrinter( $output );
		$printer->show( $diff );
		// TODO: clean up tmp clover files
	}

}
