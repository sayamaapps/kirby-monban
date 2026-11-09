<?php

// Options Monban reads, relative to the plugin: `sayamaapps.monban.<key>`.
// The values here are the defaults. `cache` switches on the plugin's own cache
// (site/cache), which holds the generated secret, the list of used challenges
// and the counters of Guard::throttle().
return [
	'active'   => true,
	'cache'    => true,
	'cost'     => 1000,
	'expires'  => 600,
	'honeypot' => 'website',
	'language' => null,
	'maximum'  => 86400,
	'minimum'  => 3,
	'rounds'   => 3000,
	'script'   => true,
	'secret'   => null,
	'style'    => true,
];
