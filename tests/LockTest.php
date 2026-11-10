<?php

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Monban\Guard;
use Sayamaapps\Monban\Lock;
use Sayamaapps\Monban\Puzzle;

/**
 * The lock around the state in the cache: it is held while the callback runs,
 * released when the callback throws, turns away whoever waits too long, and
 * steps aside only where no lock is possible at all.
 */
#[CoversClass(Lock::class)]
#[CoversClass(Guard::class)]
#[CoversClass(Puzzle::class)]
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

	/** Another request that holds the lock: its own handle, as a second process would have. */
	private function hold(): mixed
	{
		$handle = fopen($this->site . '/cache/' . Lock::FILE, 'c');
		holds($handle !== false && flock($handle, LOCK_EX | LOCK_NB), 'the lock is taken');
		return $handle;
	}

	protected function tearDown(): void
	{
		Lock::$wait = 2.0;

		parent::tearDown();
	}

	public function testHeldWhileTheCallbackRunsAndReleasedAfterwards(): void
	{
		$kirby = $this->app();
		$held = null;

		expect('result', Lock::run($kirby, function () use (&$held): string {
			$held = !$this->free();
			return 'result';
		}, 'busy'), 'the result of the callback');
		expect(true, $held, 'held inside');
		holds($this->free(), 'free afterwards');
	}

	public function testReleasedWhenTheCallbackThrows(): void
	{
		$kirby = $this->app();

		try {
			Lock::run($kirby, static function (): never {
				throw new RuntimeException('boom');
			}, 'busy');
			$this->fail('the exception passes through');
		} catch (RuntimeException $exception) {
			expect('boom', $exception->getMessage(), 'the exception of the callback');
		}
		holds($this->free(), 'free after the exception');
	}

	public function testRunsWithoutCache(): void
	{
		$kirby = $this->app(['sayamaapps.monban.cache' => false]);

		expect('ran', Lock::run($kirby, static fn (): string => 'ran', 'busy'), 'the callback runs');
		holds(!file_exists($this->site . '/cache/' . Lock::FILE), 'no lock file without a cache');
	}

	public function testRunsWithoutTheLockWhenTheFileCannotBeOpened(): void
	{
		$kirby = $this->app();
		mkdir($this->site . '/cache/' . Lock::FILE); // a folder where the file should be

		expect('ran', Lock::run($kirby, static fn (): string => 'ran', 'busy'), 'the callback runs anyway');
	}

	public function testThrottleAndVerifyRefuseWhileTheLockStaysTaken(): void
	{
		$kirby = $this->app(['sayamaapps.monban.cost' => 100, 'sayamaapps.monban.rounds' => 100]);
		$challenge = Challenge::fromArray(Puzzle::challenge($kirby));
		$solution = Puzzle::altcha($kirby)->solveChallenge(new SolveChallengeOptions(algorithm: new Pbkdf2(), challenge: $challenge));
		holds($solution !== null, 'the challenge can be solved');
		$payload = (new Payload($challenge, $solution))->toBase64();

		Lock::$wait = 0.1;
		holds(Guard::throttle($kirby, 'anna@example.com', 5, 60), 'creates the lock file, one attempt counted');
		$other = $this->hold();

		holds(!Guard::throttle($kirby, 'anna@example.com', 5, 60), 'no attempt while the lock stays taken');
		expect(Guard::INVALID, Puzzle::verify($kirby, $payload), 'no answer counts while the lock stays taken');

		flock($other, LOCK_UN);
		fclose($other);
		holds(Guard::throttle($kirby, 'anna@example.com', 5, 60), 'an attempt again once the lock is free');
		expect(Guard::OK, Puzzle::verify($kirby, $payload), 'the answer was not used up by the refusal');
		expect(Guard::REPLAY, Puzzle::verify($kirby, $payload), 'and counts once');
	}

	public function testTurnedAwayWhenTheLockStaysTaken(): void
	{
		$kirby = $this->app();
		Lock::$wait = 0.1;
		Lock::run($kirby, static fn (): bool => true, false); // creates the lock file
		$other = $this->hold();

		$ran = false;
		$started = microtime(true);
		expect('busy', Lock::run($kirby, static function () use (&$ran): string {
			$ran = true;
			return 'ran';
		}, 'busy'), 'the fallback is returned');
		holds(!$ran, 'the callback does not run');
		holds(microtime(true) - $started >= 0.1, 'it waited for the lock first');

		flock($other, LOCK_UN);
		fclose($other);
		expect('ran', Lock::run($kirby, static fn (): string => 'ran', 'busy'), 'with the lock free again it runs');
	}
}
