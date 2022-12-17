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

namespace MediaWiki\Tool\PatchCoverage;

use Symfony\Component\Finder\Finder;

/**
 * Finds tests that cover provided classes
 */
class TestFinder {

	/**
	 * @var string
	 */
	private $testDir;

	/**
	 * @param string $testDir
	 */
	public function __construct( $testDir ) {
		$this->testDir = $testDir;
	}

	/**
	 * @param string[] $classes
	 *
	 * @return string[] Absolute filenames to tests
	 */
	public function find( array $classes ) {
		if ( !$classes ) {
			return [];
		}
		$regex = implode( '|', array_map( static function ( $class ) {
			return preg_quote( $class );
		}, $classes ) );
		// Look for @covers, @coversDefaultClass
		// There might be a leading \ or not
		// After the class there can be ::method or end of line
		$regex = "/@covers(DefaultClass)? (\\\\)?($regex)(::|$)/m";
		$finder = new Finder();
		$finder->files()->in( $this->testDir )->name( '*Test.php' );
		$found = [];
		foreach ( $finder as $fileInfo ) {
			$contents = $fileInfo->getContents();
			if ( preg_match( $regex, $contents ) === 1 ) {
				$found[] = $fileInfo->getRealPath();
			}
		}

		sort( $found );
		return $found;
	}
}
