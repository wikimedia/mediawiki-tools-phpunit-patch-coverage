<?php
/**
 * Copyright (C) 2019 Kunal Mehta <legoktm@debian.org>
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

namespace MediaWiki\Tool\PatchCoverage\Test;

use MediaWiki\Tool\PatchCoverage\GitChanged;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Tool\PatchCoverage\GitChanged
 */
class GitChangedTest extends TestCase {

	/**
	 * @var GitChanged
	 */
	private GitChanged $changed;

	public function setUp(): void {
		parent::setUp();
		$this->changed = new GitChanged(
			[ 'B.txt' ],
			[ 'A.txt' ],
			[ 'D.txt' ],
			[ 'C.txt' => 'E.txt' ]
		);
	}

	public function testGetPreviousFiles() {
		$this->assertEquals(
			[ 'D.txt', 'A.txt', 'C.txt' ],
			$this->changed->getPreviousFiles()
		);
	}

	public function testGetNewFiles() {
		$this->assertEquals(
			[ 'B.txt', 'A.txt', 'E.txt' ],
			$this->changed->getNewFiles()
		);
	}
}
