<?php

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Monban\Guard;
use Sayamaapps\Monban\Options;
use Sayamaapps\Monban\Puzzle;

/**
 * The proof of work: the shape of a challenge, the answer the browser would
 * give (solved here with the same library), and every way an answer fails.
 * Cost and rounds are set low so a test solves in milliseconds.
 */
#[CoversClass(Puzzle::class)]
final class PuzzleTest extends MonbanTestCase
{
	private const FAST = ['sayamaapps.monban.cost' => 100, 'sayamaapps.monban.rounds' => 100];

	/** The base64 payload the widget would post for a challenge of this site. */
	private function solve(\Kirby\Cms\App $kirby, array|null $challenge = null): string
	{
		$challenge = Challenge::fromArray($challenge ?? Puzzle::challenge($kirby));
		$solution = Puzzle::altcha($kirby)->solveChallenge(new SolveChallengeOptions(
			algorithm: new Pbkdf2(),
			challenge: $challenge,
		));
		holds($solution !== null, 'the challenge can be solved');
		return (new Payload($challenge, $solution))->toBase64();
	}

	public function testAnswerForAnotherSiteFails(): void
	{
		$payload = $this->solve($this->app(self::FAST));
		$other = $this->app(self::FAST + ['sayamaapps.monban.secret' => 'another-secret-with-thirty-two-characters-or-more']);

		expect(Guard::INVALID, Puzzle::verify($other, $payload), 'signed with another secret');
	}

	public function testBadAnswers(): void
	{
		$kirby = $this->app(self::FAST);

		expect(Guard::MISSING, Puzzle::verify($kirby, ''), 'no answer');
		expect(Guard::MISSING, Puzzle::verify($kirby, "  \n"), 'blank answer');
		expect(Guard::INVALID, Puzzle::verify($kirby, 'not base64 at all!'), 'garbage');
		expect(Guard::INVALID, Puzzle::verify($kirby, base64_encode('{"challenge":1}')), 'wrong structure');

		$data = json_decode(base64_decode($this->solve($kirby)), true);
		$data['solution']['derivedKey'] = strrev($data['solution']['derivedKey']);
		expect(Guard::INVALID, Puzzle::verify($kirby, base64_encode(json_encode($data))), 'a wrong key');

		$data = json_decode(base64_decode($this->solve($kirby)), true);
		$data['challenge']['parameters']['cost'] = 1;
		expect(Guard::INVALID, Puzzle::verify($kirby, base64_encode(json_encode($data))), 'tampered parameters break the signature');
	}

	public function testChallengeShape(): void
	{
		$kirby = $this->app(['sayamaapps.monban.cost' => 1500, 'sayamaapps.monban.expires' => 300]);
		$before = time();
		$challenge = Puzzle::challenge($kirby);
		$parameters = $challenge['parameters'];

		expect('PBKDF2/SHA-256', $parameters['algorithm'], 'algorithm');
		expect(1500, $parameters['cost'], 'cost from the option');
		holds($parameters['expiresAt'] >= $before + 300 && $parameters['expiresAt'] <= time() + 300, 'expires after the configured seconds');
		holds(preg_match('/^[0-9a-f]{32}$/', $parameters['keyPrefix']) === 1, 'the prefix the browser searches for, 16 bytes');
		holds(preg_match('/^[0-9a-f]{64}$/', $parameters['keySignature']) === 1, 'the key signature for the fast check');
		holds(preg_match('/^[0-9a-f]{64}$/', $challenge['signature']) === 1, 'the challenge is signed');
		holds($challenge !== Puzzle::challenge($kirby), 'every challenge is different');
	}

	public function testExpiredChallengeFails(): void
	{
		$kirby = $this->app(self::FAST);
		$expired = Puzzle::altcha($kirby)->createChallenge(new CreateChallengeOptions(
			algorithm: new Pbkdf2(),
			cost: 100,
			counter: 50,
			expiresAt: time() - 1,
		))->toArray();

		expect(Guard::EXPIRED, Puzzle::verify($kirby, $this->solve($kirby, $expired)), 'solved too late');
	}

	public function testResponseIsJsonThatNoCacheKeeps(): void
	{
		$response = Puzzle::response($this->app());
		$body = json_decode($response->body(), true);

		expect(200, $response->code(), 'status');
		expect('application/json', $response->type(), 'content type');
		expect('no-store', $response->headers()['Cache-Control'] ?? null, 'Cache-Control');
		holds(isset($body['parameters']['salt'], $body['signature']), 'a challenge in the body');
		expect('monban/challenge', Puzzle::ROUTE, 'the route the widget fetches from');
		expect(Options::CACHE, 'sayamaapps.monban', 'the plugin cache');
	}

	public function testSolvedChallengeVerifiesOnce(): void
	{
		$kirby = $this->app(self::FAST);
		$payload = $this->solve($kirby);

		expect(Guard::OK, Puzzle::verify($kirby, $payload), 'the right answer');
		expect(Guard::REPLAY, Puzzle::verify($kirby, $payload), 'the same answer a second time');
		expect(Guard::REPLAY, Puzzle::verify($this->app(self::FAST), ' ' . $payload . "\n"), 'also on the next request, whitespace ignored');
	}
}
