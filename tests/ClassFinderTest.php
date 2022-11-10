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

namespace MediaWiki\Tool\PatchCoverage\Test;

use MediaWiki\Tool\PatchCoverage\ClassFinder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Tool\PatchCoverage\ClassFinder
 * @covers \MediaWiki\Tool\PatchCoverage\Parser\ClassTrackerVisitor
 */
class ClassFinderTest extends TestCase {

	public function testFind() {
		$finder = new ClassFinder();
		$found = $finder->find( [
			__DIR__ . '/data/Dummy1.php',
			__DIR__ . '/data/Dummy2.php',
		] );

		$this->assertSame( [
			'A', 'C', 'D', 'E\F',
		], $found );
	}
}
