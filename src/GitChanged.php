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

/**
 * Typed object to hold changed files in a
 * git commit
 */
class GitChanged {
	public function __construct(
		/** @var string[] */
		public array $added = [],
		/** @var string[] */
		public array $modified = [],
		/** @var string[] */
		public array $deleted = [],
		/** @var string[] */
		public array $renamed = [],
	) {
	}

	/**
	 * @return array|int[]|string[]
	 */
	public function getPreviousFiles(): array {
		return array_merge(
			$this->deleted,
			$this->modified,
			array_keys( $this->renamed )
		);
	}

	/**
	 * @return array|string[]
	 */
	public function getNewFiles(): array {
		return array_merge(
			$this->added,
			$this->modified,
			array_values( $this->renamed )
		);
	}

}
