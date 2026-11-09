<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The class map in autoload.php against src/: every file is listed, every entry
 * points to an existing file, and each key is the lowercase class name of that file.
 */
#[CoversNothing]
final class AutoloadTest extends TestCase
{
	public function testClassMapListsEverySourceFileAndNothingElse(): void
	{
		$root = dirname(__DIR__);
		$map = require $root . '/autoload.php';

		$listed = array_values($map);
		$onDisk = array_map(
			static fn (string $file): string => 'src/' . basename($file),
			glob($root . '/src/*.php') ?: []
		);
		sort($listed);
		sort($onDisk);

		$this->assertSame($onDisk, $listed, 'the class map lists exactly the files of src/');
	}

	public function testEachKeyIsTheLowercaseNameOfItsClass(): void
	{
		$root = dirname(__DIR__);
		$map = require $root . '/autoload.php';

		foreach ($map as $key => $file) {
			$class = 'Sayamaapps\\Monban\\' . basename($file, '.php');
			$this->assertSame(strtolower($class), $key, $file . ' is listed under its class name');
			$this->assertFileExists($root . '/' . $file);
			$this->assertTrue(class_exists($class), $class . ' is loadable through the class map');
		}
	}
}
