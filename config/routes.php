<?php

use Kirby\Cms\App;
use Sayamaapps\Monban\Puzzle;

// The browser fetches a fresh challenge from here when the visitor submits the
// form. Never cached: every challenge is used once.
return [
	[
		'pattern' => Puzzle::ROUTE,
		'method'  => 'GET',
		'action'  => fn () => Puzzle::response(App::instance()),
	],
];
