<?php

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use Kirby\Template\Snippet;

/**
 * A test case with a throwaway site: each test gets an empty directory for
 * content, cache and media, and `app()` builds a Kirby instance over it. The
 * secret is configured unless a test asks for the generated one.
 */
abstract class MonbanTestCase extends PluginTestCase
{
	/** A secret that passes Options::SECRET_MIN. */
	public const SECRET = 'test-secret-with-at-least-thirty-two-characters';

	/** The throwaway site of the current test. */
	protected string $site;

	/** A new Kirby instance over the throwaway site. Becomes the current instance. */
	protected function app(array $options = []): App
	{
		Snippet::$cache = []; // Kirby remembers snippet paths per process

		return new App([
			'roots' => [
				'index'   => $this->site . '/public',
				'content' => $this->site . '/content',
				'site'    => $this->site . '/site',
				'cache'   => $this->site . '/cache',
				'media'   => $this->site . '/public/media',
				'storage' => $this->site . '/storage',
			],
			'options' => $options + ['url' => 'https://site.example', 'sayamaapps.monban.secret' => self::SECRET],
		]);
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->site = sys_get_temp_dir() . '/monban-' . bin2hex(random_bytes(6));
		Dir::make($this->site . '/content');
		Dir::make($this->site . '/public/media');
		Dir::make($this->site . '/site');
		Dir::make($this->site . '/cache');
	}

	protected function tearDown(): void
	{
		Dir::remove($this->site);

		parent::tearDown();
	}
}
