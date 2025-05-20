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

use MediaWiki\Tool\PatchCoverage\Parser\ClassTrackerVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Parse PHP files to find classes. We use a proper PHP parser
 * for it's awesome namespace support and to avoid all false
 * positives.
 */
class ClassFinder {

	/**
	 * Get all the classes that are found in these files
	 *
	 * @param array $files
	 * @return string[] Fully qualified class names
	 */
	public function find( array $files ) {
		if ( !$files ) {
			return [];
		}
		$parser = ( new ParserFactory() )
			->createForHostVersion();
		$tracker = new ClassTrackerVisitor();
		foreach ( $files as $file ) {
			$contents = file_get_contents( $file );
			$tree = $parser->parse( $contents );
			if ( $tree ) {
			// TODO: Do we need a new traverser each time?
				$traverser = new NodeTraverser();
				$traverser->addVisitor( new NameResolver() );
				$traverser->addVisitor( $tracker );
				$traverser->traverse( $tree );
			}
		}

		sort( $tracker->classes );
		return $tracker->classes;
	}
}
