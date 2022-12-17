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

use MediaWiki\Tool\PatchCoverage\Git;
use MediaWiki\Tool\PatchCoverage\GitChanged;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Wikimedia\ScopedCallback;

/**
 * @covers \MediaWiki\Tool\PatchCoverage\Git
 */
class GitTest extends TestCase {

	public function testGetChangedFiles() {
		$tmp = sys_get_temp_dir() . '/' . uniqid( 'patchcoverage' );
		mkdir( $tmp );
		$teardown = new ScopedCallback( static function () use ( $tmp ) {
			$p = new Process( [ 'rm', '-rf', $tmp ] );
			$p->mustRun();
		} );
		$p = new Process( [ 'git', 'init', '.' ], $tmp );
		$p->mustRun();
		file_put_contents( "$tmp/A.txt", 'foobar' );
		file_put_contents( "$tmp/C.txt", 'goodbye' );
		file_put_contents( "$tmp/D.txt", 'goodbye' );
		$p = new Process( [ 'git', 'add', '.' ], $tmp );
		$p->mustRun();
		$conf = [ '-c', 'user.email="nobody@fake.foo"', '-c', 'user.name="Nobody"' ];
		$p = new Process( array_merge( [ 'git' ], $conf, [ 'commit', '-m', 'commit' ] ), $tmp );
		$p->mustRun();
		file_put_contents( "$tmp/B.txt", 'barbaz' );
		file_put_contents( "$tmp/A.txt", 'different' );
		unlink( "$tmp/C.txt" );
		$p = new Process( [ 'git', 'mv', 'D.txt', 'E.txt' ], $tmp );
		$p->mustRun();
		$p = new Process( [ 'git', 'add', '.' ], $tmp );
		$p->mustRun();
		$p = new Process( array_merge( [ 'git' ], $conf, [ 'commit', '-m', 'commit' ] ), $tmp );
		$p->mustRun();

		$patch = new Git( $tmp );
		$this->assertEquals(
			new GitChanged(
				[ 'B.txt' ],
				[ 'A.txt' ],
				[ 'D.txt' ],
				[ 'C.txt' => 'E.txt' ]
			),
			$patch->getChangedFiles( 'HEAD' )
		);
	}
}
