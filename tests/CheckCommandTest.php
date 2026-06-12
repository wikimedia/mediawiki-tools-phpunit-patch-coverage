<?php
declare( strict_types = 1 );

namespace MediaWiki\Tool\PatchCoverage\Test;

use MediaWiki\Tool\PatchCoverage\CheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Wikimedia\CloverDiff\CloverXml;

/**
 * @covers \MediaWiki\Tool\PatchCoverage\CheckCommand
 * @covers \MediaWiki\Tool\PatchCoverage\CommandProcess
 * @covers \MediaWiki\Tool\PatchCoverage\Git
 */
class CheckCommandTest extends TestCase {

	private string $tmp;

	public function setUp(): void {
		$tmp = sys_get_temp_dir() . '/' . uniqid( 'patchcoverage' );
		mkdir( $tmp );
		mkdir( "$tmp/tests/phpunit", 0777, true );
		$p = new Process( [ 'git', 'init', '.' ], $tmp );
		$p->mustRun();

		file_put_contents( "$tmp/A.php", '<?php class A {}' );
		file_put_contents( "$tmp/B.php", '<?php class B {}' );
		file_put_contents( "$tmp/C.php", '<?php class C {}' );
		file_put_contents( "$tmp/tests/phpunit/ATest.php", '@covers A' );
		file_put_contents( "$tmp/tests/phpunit/BTest.php", '@covers B' );
		file_put_contents( "$tmp/tests/phpunit/CTest.php", "@covers A\n@covers C" );
		$p = new Process( [ 'git', 'add', '.' ], $tmp );
		$p->mustRun();
		$conf = [ '-c', 'user.email="nobody@fake.test"', '-c', 'user.name="Nobody"' ];
		$p = new Process( [ 'git', ...$conf, 'commit', '-m', 'patch one' ], $tmp );
		$p->mustRun();

		file_put_contents( "$tmp/A.php", "<?php\n// added line\nclass A {}\n" );
		$p = new Process( [ 'git', 'add', '.' ], $tmp );
		$p->mustRun();
		$p = new Process( [ 'git', ...$conf, 'commit', '-m', 'patch two' ], $tmp );
		$p->mustRun();

		$this->tmp = $tmp;
	}

	public function tearDown(): void {
		$p = new Process( [ 'rm', '-rf', $this->tmp ] );
		$p->mustRun();
	}

	public function testExecute() {
		$data = __DIR__ . '/data';

		chdir( $this->tmp );
		$in = new ArrayInput( [ '--command' => "$data/mock-phpunit.sh" ] );
		$out = new BufferedOutput();
		$command = new CheckCommand();
		$exit = $command->run( $in, $out );

		// Git::getChangedFiles finds A.php
		// ClassFinder finds A
		// TestFinder should identify ATest and CTest, not BTest.
		$buffer = $out->fetch();
		$this->assertMatchesRegularExpression( "/--filter ['\"]\/ATest\|CTest\/['\"]/", $buffer );
		$this->assertStringContainsString( 'No coverage changes found.', $buffer );
		$this->assertSame( 0, $exit );
	}

	public function testBin() {
		$bin = dirname( __DIR__ ) . '/bin';
		$data = __DIR__ . '/data';
		$p = new Process(
			[
				"$bin/phpunit-patch-coverage",
				'check',
				'--command', "$data/mock-phpunit.sh"
			],
			$this->tmp
		);
		$p->mustRun();

		$this->assertMatchesRegularExpression( "/--filter ['\"]\/ATest\|CTest\/['\"]/", $p->getOutput() );
		$this->assertStringContainsString( 'No coverage changes found.', $p->getOutput() );
	}

	public function testSaveFilesRecoversStrippedPrefix() {
		// PHPUnit clover reports use absolute paths, and CloverXml::getFiles()
		// strips the directory common to every covered file. When they all live
		// under a deep directory (here includes/libs/) the stripped keys
		// ("Foo.php", "Bar.php") are not readable from the working directory, so
		// saveFiles() must recover the prefix to read them back (T425807).
		$dir = "$this->tmp/includes/libs";
		mkdir( $dir, 0777, true );
		file_put_contents( "$dir/Foo.php", "<?php\nclass Foo {}\n" );
		file_put_contents( "$dir/Bar.php", "<?php\nclass Bar {}\n" );

		$clover = "$this->tmp/clover.xml";
		$this->writeClover( $clover, [
			// Line 2 covered, line 3 expected-but-missed
			"$dir/Foo.php" => [ 2 => 1, 3 => 0 ],
			"$dir/Bar.php" => [ 2 => 5 ],
		] );

		$files = $this->invokeSaveFiles( $clover );

		// Keyed by the stripped relative path (matching Differ/HtmlReport), with
		// the real file read back: covered lines get ✓, missed lines get ✘.
		$this->assertArrayHasKey( 'Foo.php', $files );
		$this->assertArrayHasKey( 'Bar.php', $files );
		$this->assertStringContainsString( '✓ class Foo {}', implode( "\n", $files['Foo.php'] ) );
		$this->assertStringContainsString( '✘', implode( "\n", $files['Foo.php'] ) );
		$this->assertStringContainsString( '✓ class Bar {}', implode( "\n", $files['Bar.php'] ) );
	}

	public function testSaveFilesResolvesSingleFile() {
		// A single covered file has its entire path stripped to '', so the
		// prefix must be recovered as the whole absolute path (T425807).
		$dir = "$this->tmp/lonely";
		mkdir( $dir );
		file_put_contents( "$dir/Solo.php", "<?php\nclass Solo {}\n" );

		$clover = "$this->tmp/clover-solo.xml";
		$this->writeClover( $clover, [ "$dir/Solo.php" => [ 2 => 1 ] ] );

		$files = $this->invokeSaveFiles( $clover );

		$this->assertArrayHasKey( '', $files );
		$this->assertStringContainsString( '✓ class Solo {}', implode( "\n", $files[''] ) );
	}

	/**
	 * Write a minimal clover XML report referencing files by absolute path.
	 *
	 * @param string $path Where to write the clover XML
	 * @param array<string,array<int,int>> $fileLines Map of file path to a map
	 *   of line number => coverage count
	 */
	private function writeClover( string $path, array $fileLines ): void {
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<coverage><project>\n";
		foreach ( $fileLines as $name => $lines ) {
			$xml .= "<file name=\"$name\">\n";
			foreach ( $lines as $num => $count ) {
				$xml .= "<line num=\"$num\" type=\"stmt\" count=\"$count\"/>\n";
			}
			$xml .= "</file>\n";
		}
		$xml .= "</project></coverage>\n";
		file_put_contents( $path, $xml );
	}

	/**
	 * Call CheckCommand::saveFiles(), which is protected, via a subclass.
	 *
	 * @param string $cloverPath
	 * @return array
	 */
	private function invokeSaveFiles( string $cloverPath ): array {
		$command = new class() extends CheckCommand {
			public function saveFilesPublic( CloverXml $cloverXml, string $cloverPath ): array {
				return $this->saveFiles( $cloverXml, $cloverPath );
			}
		};
		return $command->saveFilesPublic( new CloverXml( $cloverPath ), $cloverPath );
	}
}
