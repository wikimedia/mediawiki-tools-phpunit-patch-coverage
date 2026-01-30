<?php

namespace MediaWiki\Tool\PatchCoverage\Test;

use MediaWiki\Tool\PatchCoverage\CheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

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
}
