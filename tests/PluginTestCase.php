<?php

use Kirby\Cms\App;
use PHPUnit\Framework\TestCase;

/**
 * A test case with the plugin registered the way Kirby registers it when it loads
 * site/plugins. App::destroy() forgets every registered plugin, so each test
 * registers the plugin again, as a fresh site would.
 */
abstract class PluginTestCase extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		App::destroy();
		require dirname(__DIR__) . '/index.php';
	}

	protected function tearDown(): void
	{
		App::destroy();

		parent::tearDown();
	}
}
