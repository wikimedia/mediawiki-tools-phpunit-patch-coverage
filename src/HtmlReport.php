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

use Legoktm\CloverDiff\Diff;
use Legoktm\CloverDiff\DiffPrinter;
use Phalcon\Diff as PhalconDiff;
use Phalcon\Diff\Renderer\Html\SideBySide;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Build an HTML report that's helpfully nice for humans to use
 */
class HtmlReport {

	/**
	 * @param Diff $diff
	 * @param array $oldFiles
	 * @param array $newFiles
	 *
	 * @return string
	 */
	public function report( Diff $diff, array $oldFiles, array $newFiles ) {
		$html = <<<HTML
<!DOCTYPE html>
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
	<h2>Summary</h2>
HTML;
		$output = new BufferedOutput();
		( new DiffPrinter( $output ) )->show( $diff );
		$html .= "<pre>{$output->fetch()}</pre>";
		foreach ( array_keys( $diff->getChanged() ) as $fname ) {
			if ( isset( $newFiles[$fname] ) && isset( $oldFiles[$fname] ) ) {
				$pdiff = new PhalconDiff( $oldFiles[$fname], $newFiles[$fname] );
				$html .= "<h2>$fname</h2>\n";
				$html .= $pdiff->render( new SideBySide() );
			}
		}

		return $html . '</body></html>';
	}
}
