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

use MediaWiki\Tool\PatchCoverage\HtmlReport;
use PHPUnit\Framework\TestCase;
use Wikimedia\CloverDiff\Diff;

/**
 * @covers \MediaWiki\Tool\PatchCoverage\HtmlReport
 */
class HtmlReportTest extends TestCase {

	public function testFind() {
		$diff = new Diff( [], [] );
		$report = new HtmlReport();
		$html = $report->report( $diff, [], [] );

		// Do a "\r\n" -> "\n" and "\r" -> "\n" transformation for windows machine
		$html = str_replace( [ "\r\n", "\r" ], "\n", $html );

		$this->assertSame( '<!DOCTYPE html>
<html>
	<head>
<title>Coverage difference report</title>
<meta charset="utf-8"/>
<style>
.Differences {
  font-family: monospace;
}
</style>
	</head>
	<body>
	<h2>Summary</h2><pre>No coverage changes found.
</pre></body></html>',
			$html
		);
	}
}
