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

use Symfony\Component\Process\Process;

/**
 * Wrapper around git
 */
class Git {

	/**
	 * @var string[]
	 */
	private static $status = [
		'A' => 'added',
		'D' => 'deleted',
		'M' => 'modified',
		'R' => 'renamed',
	];

	/**
	 * @var string
	 */
	private $path;

	/**
	 * @param string $path
	 */
	public function __construct( $path ) {
		$this->path = $path;
	}

	/**
	 * @param string $ref
	 *
	 * @return GitChanged
	 */
	public function getChangedFiles( $ref ) {
		$process = new Process(
			[ 'git', 'diff', '--name-status', $ref . '^', $ref ]
		);
		$process->setWorkingDirectory( $this->path );
		$process->mustRun();
		$changed = new GitChanged();
		$lines = explode( "\n", trim( $process->getOutput() ) );
		foreach ( $lines as $line ) {
			$status = self::$status[$line[0]];
			if ( $status === 'renamed' ) {
				$matches = [];
				preg_match( '/R\d{3}\s*(\S*)\s*(\S*)/', $line, $matches );
				$changed->renamed[$matches[1]] = $matches[2];
			} else {
				$file = trim( substr( $line, 1 ) );
				$changed->{$status}[] = $file;
			}
		}

		return $changed;
	}

	/**
	 * git checkout
	 *
	 * @param string $ref
	 */
	public function checkout( $ref ) {
		$process = new Process(
			[ 'git', 'checkout', $ref ]
		);
		$process->mustRun();
	}

	/**
	 * Get a SHA1 for any ref
	 *
	 * @param string $ref
	 *
	 * @return string
	 */
	public function getSha1( $ref ) {
		$process = new Process(
			[ 'git', 'rev-parse', $ref ]
		);
		$process->mustRun();
		return trim( $process->getOutput() );
	}

	/**
	 * If $ref is a merge commit, get its parent,
	 * otherwise return $ref
	 *
	 * @param string $ref
	 *
	 * @return string
	 */
	public function findNonMergeCommit( $ref ) {
		$process = new Process(
			[ 'git', 'log', '--format=%P', $ref, '-n1' ]
		);
		$process->mustRun();
		$exploded = explode( ' ', trim( $process->getOutput() ) );
		if ( count( $exploded ) > 1 ) {
			return end( $exploded );
		} else {
			return $this->getSha1( $ref );
		}
	}
}
