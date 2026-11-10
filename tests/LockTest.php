<?php

use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Monban\Lock;

/**
 * The lock around the state in the cache: it is held while the callback runs,
 * released when the callback throws, and never keeps a visitor out.
 */
#[CoversClass(Lock::class)]
final class LockTest extends MonbanTestCase
{
	/** Whether another handle on the lock file gets the lock right now. */
	private function free(): bool
	{
		$handle = fopen($this->site . '/cache/' . Lock::FILE, 'c');
		holds($handle !== false, 'the lock file can be opened');
		$free = flock($handle, LOCK_EX | LOCK_NB);
		fclose($handle);
		return $free;
	}

	public function testHeldWhileTheCallbackRunsAndReleasedAfterwards(): void
	{
		$kirby = $this->app();
		$held = null;

		expect('result', Lock::run($kirby, function () use (&$held): string {
			$held = !$this->free();
			return 'result';
		}), 'the result of the callback');
		expect(true, $held, 'held inside');
		holds($this->free(), 'free afterwards');
	}

	public function testReleasedWhenTheCallbackThrows(): void
	{
		$kirby = $this->app();

		try {
			Lock::run($kirby, static function (): never {
				throw new RuntimeException('boom');
			});
			$this->fail('the exception passes through');
		} catch (RuntimeException $exception) {
			expect('boom', $exception->getMessage(), 'the exception of the callback');
		}
		holds($this->free(), 'free after the exception');
	}

	public function testRunsWithoutTheLockWhenTheFileCannotBeOpened(): void
	{
		$kirby = $this->app();
		mkdir($this->site . '/cache/' . Lock::FILE); // a folder where the file should be

		expect('ran', Lock::run($kirby, static fn (): string => 'ran'), 'the callback runs anyway');
	}

	public function testRunsWithoutCache(): void
	{
		$kirby = $this->app(['sayamaapps.monban.cache' => false]);

		expect('ran', Lock::run($kirby, static fn (): string => 'ran'), 'the callback runs');
		holds(!file_exists($this->site . '/cache/' . Lock::FILE), 'no lock file without a cache');
	}
}
