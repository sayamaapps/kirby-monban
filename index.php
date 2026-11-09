<?php

use Kirby\Filesystem\F;

/**
 * Plugin registration for Monban (門番, the gatekeeper): spam protection for
 * forms, served from the site's own server.
 *
 * Kirby loads this file. It registers the class map and the definitions in
 * config/. The logic lives in src/: Guard checks a submitted form, Puzzle
 * creates and verifies the proof-of-work challenge, Options reads the
 * configuration.
 *
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-monban
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
F::loadClasses(require __DIR__ . '/autoload.php', __DIR__);

Kirby::plugin(
	name: 'sayamaapps/monban',
	extends: [
		'options'  => require __DIR__ . '/config/options.php',
		'routes'   => require __DIR__ . '/config/routes.php',
		'snippets' => require __DIR__ . '/config/snippets.php',
	],
	root: __DIR__
);
