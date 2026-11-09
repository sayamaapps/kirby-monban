<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The plugin's own registration, as Kirby runs it on load. PluginTestCase registers it again for every test.
require_once dirname(__DIR__) . '/index.php';

// Kirby's Whoops error handler stays registered after every App is created, so
// the risky-test check would flag each Kirby test. PHPUnit reports errors itself.
Kirby\Cms\App::$enableWhoops = false;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/PluginTestCase.php';
require_once __DIR__ . '/MonbanTestCase.php';
