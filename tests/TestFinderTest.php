<?php
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

namespace MediaWiki\Tool\PatchCoverage\Test;

use MediaWiki\Tool\PatchCoverage\TestFinder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Tool\PatchCoverage\TestFinder
 */
class TestFinderTest extends TestCase {

	public function testFind() {
		$data = self::fixSlashes( __DIR__ . '/data' );
		$finder = new TestFinder( $data );
		$found = $finder->find( [
			'A', 'B', 'C', 'D\E\F', 'G', 'H'
		] );
		$found = array_map( [ self::class, 'fixSlashes' ], $found );
		$this->assertSame( [
			"$data/Dummy1Test.php",
			"$data/Dummy2Test.php",
			"$data/Dummy3Test.php",
			// No Dummy 3b
			"$data/Dummy4Test.php",
			"$data/Dummy5Test.php",
			// No Dummy 6
		], $found );
	}

	private static function fixSlashes( string $str ): string {
		// Replace dir separator for windows machine
		return str_replace( '\\', '/', $str );
	}
}
