<?php

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Monban\Guard;
use Sayamaapps\Monban\Puzzle;

/**
 * Parallel requests: the replay protection and the throttle keep their state
 * in the cache, so a check followed by a write must not let two requests
 * through that both saw the old state. The requests are real PHP processes
 * over one site, released together by a start file; the cache of the site is
 * slow after each read (tests/fixtures/SlowFileCache.php), so every process
 * has read before any process writes unless the check and the write are one
 * step.
 */
#[CoversClass(Guard::class)]
#[CoversClass(Puzzle::class)]
final class ConcurrencyTest extends MonbanTestCase
{
	private const PROCESSES = 6;

	/** The base64 payload the widget would post for a fresh challenge of the site. */
	private function answer(): string
	{
		$kirby = $this->app(['sayamaapps.monban.cost' => 100, 'sayamaapps.monban.rounds' => 100]);
		$challenge = Challenge::fromArray(Puzzle::challenge($kirby));
		$solution = Puzzle::altcha($kirby)->solveChallenge(new SolveChallengeOptions(algorithm: new Pbkdf2(), challenge: $challenge));
		holds($solution !== null, 'the challenge can be solved');
		return (new Payload($challenge, $solution))->toBase64();
	}

	/**
	 * Starts the processes, releases them at once and returns what each printed.
	 *
	 * @return list<string>
	 */
	private function race(string $mode, string $payload = ''): array
	{
		$start = $this->site . '/start';
		file_put_contents($this->site . '/payload', $payload);
		// One answer per process for the warm-up call, each used once.
		for ($i = 0; $i < self::PROCESSES && $mode === 'replay'; $i++) {
			file_put_contents($this->site . '/warm-up-' . $i, $this->answer());
		}

		mkdir($this->site . '/ready');
		$processes = [];
		for ($i = 0; $i < self::PROCESSES; $i++) {
			$command = [PHP_BINARY, __DIR__ . '/fixtures/race.php', $this->site, $mode, $start, (string)$i, $this->site . '/payload', $this->site . '/warm-up-' . $i];
			$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			holds(is_resource($process), 'a process can be started');
			$processes[] = [$process, $pipes];
		}
		// Release them only when every process has booted and waits for the start file.
		$deadline = microtime(true) + 30;
		while (count(glob($this->site . '/ready/*') ?: []) < self::PROCESSES) {
			holds(microtime(true) < $deadline, 'every process gets ready');
			usleep(10_000);
		}
		touch($start);

		$results = [];
		foreach ($processes as [$process, $pipes]) {
			$output = stream_get_contents($pipes[1]);
			$errors = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			expect(0, proc_close($process), 'the process ends cleanly: ' . $errors);
			$results[] = (string)$output;
		}
		sort($results);
		return $results;
	}

	public function testParallelRequestsStayWithinTheLimit(): void
	{
		$results = $this->race('throttle');

		expect(['allowed', 'allowed', 'allowed', ...array_fill(0, self::PROCESSES - 3, 'blocked')], $results, 'no more attempts than the limit allows');
	}

	public function testParallelRequestsUseAChallengeOnlyOnce(): void
	{
		$results = $this->race('replay', $this->answer());

		expect([Guard::OK, ...array_fill(0, self::PROCESSES - 1, Guard::REPLAY)], $results, 'exactly one of the parallel answers counts');
	}
}
