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

	/** Seconds a request waits for the lock before it goes on without it. */
	public const WAIT = 5;

	/**
	 * Runs `$callback` while holding the lock and returns its result. Without a
	 * cache to protect, or when the lock file cannot be opened or the lock is
	 * not free after WAIT seconds (a stuck process), the callback runs without
	 * the lock, so a visitor is never blocked for good.
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public static function run(App $kirby, callable $callback): mixed
	{
		$folder = $kirby->root('cache');
		$handle = $folder !== null && $kirby->cache(Options::CACHE)->enabled() ? self::acquire($folder) : null;
		try {
			return $callback();
		} finally {
			if ($handle !== null) {
				flock($handle, LOCK_UN);
				fclose($handle);
			}
		}
	}

	/** The open lock file with the lock held, or null. */
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
		$deadline = microtime(true) + (float)self::WAIT;
		while (!flock($handle, LOCK_EX | LOCK_NB)) {
			if (microtime(true) > $deadline) {
				fclose($handle);
				return null;
			}
			usleep(1000);
		}
		return $handle;
	}
}
