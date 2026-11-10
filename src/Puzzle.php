<?php

namespace Sayamaapps\Monban;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Kirby\Cms\App;
use Kirby\Http\Response;
use Throwable;

/**
 * The proof of work (ALTCHA, PBKDF2/SHA-256): the server hands out a signed
 * challenge, the browser searches the counter whose derived key starts with
 * the given prefix, the server checks the answer with one HMAC.
 *
 * The challenge expires (option `expires`), and each one counts only once:
 * the signature of a verified challenge is kept in the plugin's cache until it
 * would have expired anyway. Looking up and keeping the signature happen under
 * a lock (Lock), so parallel requests cannot all use the same challenge. An
 * answer that cannot get the lock in time is rejected as invalid; the challenge
 * stays unused.
 *
 * @package   Kirby Monban
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-monban
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Puzzle
{
	/** Name of the hidden field the widget fills with its answer. */
	public const FIELD = 'monban';

	/** Route the widget fetches its challenge from, relative to the site. */
	public const ROUTE = 'monban/challenge';

	/** The ALTCHA verifier with both secrets derived from the site's secret. */
	public static function altcha(App $kirby): Altcha
	{
		$secret = Options::secret($kirby);
		return new Altcha(
			hmacSignatureSecret: $secret,
			hmacKeySignatureSecret: hash_hmac('sha256', 'key', $secret),
		);
	}

	/**
	 * A fresh challenge as the widget expects it: `parameters` and `signature`.
	 * The counter is drawn between a third of `rounds` and `rounds`, so the
	 * browser needs that many attempts of `cost` iterations each.
	 */
	public static function challenge(App $kirby): array
	{
		$rounds = Options::rounds($kirby);
		$challenge = self::altcha($kirby)->createChallenge(new CreateChallengeOptions(
			algorithm: new Pbkdf2(),
			cost: Options::cost($kirby),
			counter: random_int(intdiv($rounds, 3), $rounds),
			expiresAt: time() + Options::expires($kirby),
		));
		return $challenge->toArray();
	}

	/** The challenge as a JSON response that no cache keeps. */
	public static function response(App $kirby): Response
	{
		return Response::json(self::challenge($kirby), 200, false, [
			'Cache-Control' => 'no-store',
		]);
	}

	/**
	 * Checks the widget's answer. Returns Guard::OK, or Guard::MISSING (no
	 * answer), Guard::EXPIRED, Guard::REPLAY (seen before) or Guard::INVALID (also when the
	 * lock around the used challenges stayed taken, see Lock).
	 */
	public static function verify(App $kirby, string $payload): string
	{
		if (trim($payload) === '') {
			return Guard::MISSING;
		}
		try {
			$result = self::altcha($kirby)->verifySolution(new VerifySolutionOptions(
				payload: trim($payload),
				algorithm: new Pbkdf2(),
			));
		} catch (Throwable) {
			return Guard::INVALID;
		}
		if ($result->expired) {
			return Guard::EXPIRED;
		}
		if ($result->verified !== true) {
			return Guard::INVALID;
		}

		$signature = (string)(json_decode((string)base64_decode(trim($payload), true), true)['challenge']['signature'] ?? '');
		$cache = $kirby->cache(Options::CACHE);
		$key = 'seen-' . hash('sha256', $signature);
		// Look and mark in one step, or parallel requests all see "not used yet".
		return Lock::run($kirby, static function () use ($kirby, $cache, $key): string {
			if ($cache->get($key) !== null) {
				return Guard::REPLAY;
			}
			$cache->set($key, time(), (int)ceil(Options::expires($kirby) / 60) + 1);
			return Guard::OK;
		}, Guard::INVALID);
	}
}
