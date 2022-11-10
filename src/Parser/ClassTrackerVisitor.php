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

namespace MediaWiki\Tool\PatchCoverage\Parser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Keeps track of all the classes and traits it sees
 */
class ClassTrackerVisitor extends NodeVisitorAbstract {

	/**
	 * @var array
	 */
	public $classes = [];

	/**
	 * @param Node $node
	 *
	 * @return int|void|null
	 */
	public function enterNode( Node $node ) {
		if ( $node instanceof Node\Stmt\Class_
			|| $node instanceof Node\Stmt\Trait_
		) {
			$this->classes[] = (string)$node->namespacedName;
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}
	}
}
