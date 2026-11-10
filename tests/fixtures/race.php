<?php

/**
 * One parallel request of ConcurrencyTest: boots a Kirby instance over the
 * shared throwaway site, reports that it is ready (a file of its own in
 * `<site>/ready`, which the test creates), waits until the start file exists,
 * runs one call and prints its result. Before it reports, it makes the same
 * call once with a payload or key of its own, so the classes are loaded and
 * the call takes the same time in every process. In the mode `secret` the site
 * has no configured secret and the call is `Options::secret()` on a fresh
 * cache; the warm-up only loads the classes, as it must not create the secret.
 *
 * Usage: php race.php <site> <replay|throttle|secret> <start file> <number> [<payload file> <payload file for the warm-up>]
 */

use Kirby\Cms\App;
use Sayamaapps\Monban\Guard;
use Sayamaapps\Monban\Lock;
use Sayamaapps\Monban\Options;
use Sayamaapps\Monban\Puzzle;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/index.php';
require_once __DIR__ . '/SlowFileCache.php';

App::$enableWhoops = false;

[, $site, $mode, $start, $number] = $argv;
$kirby = new App([
	'cacheTypes' => ['slow-file' => SlowFileCache::class],
	'roots' => [
		'index' => $site . '/public',
		'content' => $site . '/content',
		'site' => $site . '/site',
		'cache' => $site . '/cache',
		'media' => $site . '/public/media',
		'storage' => $site . '/storage',
	],
	'options' => [
		'url' => 'https://site.example',
		'sayamaapps.monban.secret' => $mode === 'secret' ? null : 'test-secret-with-at-least-thirty-two-characters',
		'sayamaapps.monban.cache' => ['type' => 'slow-file'],
	],
]);

if ($mode === 'replay') {
	Puzzle::verify($kirby, (string)file_get_contents($argv[6]));
} elseif ($mode === 'secret') {
	// Six processes queue for the lock behind a slow cache; give them time.
	Lock::$wait = 20.0;
	Lock::run($kirby, static fn (): bool => Options::cost($kirby) > 0, false);
} else {
	Guard::throttle($kirby, 'warm-up-' . $number, 3, 1440);
}
touch($site . '/ready/' . $number);

$deadline = microtime(true) + 30;
while (!file_exists($start)) {
	if (microtime(true) > $deadline) {
		fwrite(STDERR, "no start signal\n");
		exit(2);
	}
	usleep(200);
}

if ($mode === 'replay') {
	echo Puzzle::verify($kirby, (string)file_get_contents($argv[5]));
} elseif ($mode === 'secret') {
	// A request that fails is reported by its exception, so the test compares results instead of exit codes.
	try {
		echo Options::secret($kirby);
	} catch (LogicException $exception) {
		echo 'LogicException: ' . $exception->getMessage();
	}
} else {
	echo Guard::throttle($kirby, 'anna@example.com', 3, 1440) ? 'allowed' : 'blocked';
}
