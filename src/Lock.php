<?php

namespace Sayamaapps\Monban;

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;

/**
 * One exclusive lock for the state Monban keeps in its cache. The replay
 * protection and the throttle read a value, decide and write it back; without
 * a lock, parallel requests all read the old value and all pass. A Kirby
 * cache has no atomic "add unless present", so the read and the write run
 * inside `run()` instead.
 *
 * The lock is `flock()` on a file in the site's cache folder: it is released
 * when the callback ends, however it ends, and by the system when the process
 * dies, so a crashed request never blocks the others. It covers the requests
 * of one server, which is where a file cache lives anyway.
 *
 * Whoever cannot get the lock within `$wait` seconds (a flood, a stuck
 * process) is turned away: `run()` returns the fallback the caller names and
 * does not run the callback. Running it unlocked would bring the race back
 * exactly when it is most likely. Only when no lock is possible at all (no
 * cache, the lock file cannot be opened) does the callback run without one,
 * because the cache could not keep anything there anyway.
 *
 * @package   Kirby Monban
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-monban
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Lock
{
	/** Name of the lock file in the cache folder. */
	public const FILE = 'sayamaapps-monban.lock';

	/** Seconds a request waits for the lock before it is turned away. Tests lower it. */
	public static float $wait = 2.0;

	/**
	 * The open lock file with the lock held, null when no lock is possible
	 * (the callback then runs without one) or false when the lock stayed taken.
	 *
	 * @return resource|null|false
	 */
	private static function acquire(string $folder): mixed
	{
		$file = $folder . '/' . self::FILE;
		if (Dir::make($folder) === false || is_dir($file) || !is_writable(file_exists($file) ? $file : $folder)) {
			return null;
		}
		$handle = fopen($file, 'c');
		if ($handle === false) {
			return null;
		}
		$deadline = microtime(true) + self::$wait;
		while (!flock($handle, LOCK_EX | LOCK_NB)) {
			if (microtime(true) > $deadline) {
				fclose($handle);
				return false;
			}
			usleep(1000);
		}
		return $handle;
	}

	/**
	 * Runs `$callback` while holding the lock and returns its result. When the
	 * lock is not free within `$wait` seconds, the callback does not run and
	 * `$busy` is returned. Without a cache to protect, or when the lock file
	 * cannot be opened, the callback runs without the lock.
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @param T $busy the result when the lock stays taken
	 * @return T
	 */
	public static function run(App $kirby, callable $callback, mixed $busy): mixed
	{
		$folder = $kirby->root('cache');
		$handle = $folder !== null && $kirby->cache(Options::CACHE)->enabled() ? self::acquire($folder) : null;
		if ($handle === false) {
			return $busy;
		}
		try {
			return $callback();
		} finally {
			if ($handle !== null) {
				flock($handle, LOCK_UN);
				fclose($handle);
			}
		}
	}
}
