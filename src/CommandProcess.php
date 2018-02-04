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

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Wrapper around Process that prints output
 * to the console live
 */
class CommandProcess extends Process {

	public function mustRunWithOutput( OutputInterface $output ) {
		$output->writeln( '$ ' . $this->getCommandLine() );
		$this->mustRun( $this->makeCallback( $output ) );
	}

	public function runWithOutput( OutputInterface $output ) {
		$output->writeln( '$ ' . $this->getCommandLine() );
		$this->run( $this->makeCallback( $output ) );
	}

	private function makeCallback( OutputInterface $output ) {
		return function ( $type, $buffer ) use ( $output ) {
			if ( $type === Process::ERR ) {
				$buffer = "<error>$buffer</error>";
			}
			$output->write( $buffer );
		};
	}
}
