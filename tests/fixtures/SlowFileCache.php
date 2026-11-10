<?php

use Kirby\Cache\FileCache;
use Kirby\Cache\Value;

/**
 * A file cache that is slow after every read. A request that reads, decides
 * and then writes leaves a wide gap between the read and the write, so
 * parallel requests are all inside that gap together, on every run.
 */
final class SlowFileCache extends FileCache
{
	public function retrieve(string $key): Value|null
	{
		$value = parent::retrieve($key);
		usleep(50_000);
		return $value;
	}
}
