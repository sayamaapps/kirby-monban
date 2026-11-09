<?php

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Monban\Guard;
use Sayamaapps\Monban\Puzzle;

/**
 * The gatekeeper: the time stamp, the order of the checks, the markup the
 * snippet prints (once per page for the tags) and the throttle.
 */
#[CoversClass(Guard::class)]
final class GuardTest extends MonbanTestCase
{
	private const FAST = ['sayamaapps.monban.cost' => 100, 'sayamaapps.monban.rounds' => 100];

	/** A complete, honest submission: empty decoy, a time stamp old enough, a solved puzzle. */
	private function honest(\Kirby\Cms\App $kirby, int $now): array
	{
		$challenge = Challenge::fromArray(Puzzle::challenge($kirby));
		$solution = Puzzle::altcha($kirby)->solveChallenge(new SolveChallengeOptions(algorithm: new Pbkdf2(), challenge: $challenge));
		return [
			'website' => '',
			Guard::TIME_FIELD => Guard::timeToken($kirby, $now - 10),
			Puzzle::FIELD => (new Payload($challenge, $solution))->toBase64(),
		];
	}

	public function testCheckInOrder(): void
	{
		$kirby = $this->app(self::FAST);
		$now = time();
		$honest = $this->honest($kirby, $now);

		expect(Guard::HONEYPOT, Guard::check($kirby, ['website' => 'https://spam.example'] + $honest, $now), 'the decoy field first');
		expect(Guard::MISSING, Guard::check($kirby, [], $now), 'nothing sent');
		expect(Guard::TOO_FAST, Guard::check($kirby, [Guard::TIME_FIELD => Guard::timeToken($kirby, $now)] + $honest, $now), 'the time stamp second');
		expect(Guard::MISSING, Guard::check($kirby, [Puzzle::FIELD => ''] + $honest, $now), 'the puzzle third');
		expect(Guard::INVALID, Guard::check($kirby, [Puzzle::FIELD => 'nonsense'] + $honest, $now), 'a wrong answer');
		expect(Guard::OK, Guard::check($kirby, $honest, $now), 'an honest submission');
		expect(Guard::REPLAY, Guard::check($kirby, $honest, $now), 'the same submission again');
		expect(Guard::MISSING, Guard::check($kirby, ['website' => ['array'], Puzzle::FIELD => ['array']] + [Guard::TIME_FIELD => $honest[Guard::TIME_FIELD]], $now), 'arrays instead of strings are no answer');
	}

	public function testCustomDecoyNameAndInactive(): void
	{
		$kirby = $this->app(['sayamaapps.monban.honeypot' => 'homepage']);
		expect(Guard::HONEYPOT, Guard::check($kirby, ['homepage' => 'x'], time()), 'the configured decoy field');
		expect(Guard::MISSING, Guard::check($kirby, ['website' => 'x'], time()), 'the default name is just another field now');

		$off = $this->app(['sayamaapps.monban.active' => false]);
		expect(Guard::OK, Guard::check($off, ['website' => 'spam'], time()), 'switched off, everything passes');
		expect('', Guard::fields($off), 'switched off, nothing is printed');
	}

	public function testFields(): void
	{
		$kirby = $this->app(['sayamaapps.monban.language' => 'de', 'sayamaapps.monban.honeypot' => 'homepage']);
		$html = Guard::fields($kirby);

		holds(str_contains($html, '<link rel="stylesheet" href="https://site.example/media/plugins/sayamaapps/monban/'), 'stylesheet from the plugin');
		holds(substr_count($html, '<link rel="stylesheet"') === 2, 'ALTCHA\'s stylesheet and Monban\'s');
		holds(preg_match('#<script data-monban-altcha="(https://site\.example/media/plugins/sayamaapps/monban/[^"]+)/altcha\.js" data-monban-i18n="\1/i18n/de\.js" data-monban-pbkdf2="\1/workers/pbkdf2\.js" data-monban-sha="\1/workers/sha\.js" src="\1/monban\.js" type="module"></script>#', $html) === 1 || preg_match('#<script data-monban-altcha="https://site\.example/media/plugins/sayamaapps/monban/[^"]+/altcha\.js" data-monban-i18n="[^"]+/i18n/de\.js" data-monban-pbkdf2="[^"]+/workers/pbkdf2\.js" data-monban-sha="[^"]+/workers/sha\.js" src="[^"]+/monban\.js" type="module"></script>#', $html) === 1, 'the module script with the URLs of widget, workers and labels');
		holds(preg_match('#<div class="monban-decoy" aria-hidden="true"><label for="(monban-[0-9a-f]{8})">Website</label><input id="\1" type="text" name="homepage" tabindex="-1" autocomplete="off"></div>#', $html) === 1, 'the decoy field');
		holds(preg_match('#<input type="hidden" name="monban_time" value="\d+\.[0-9a-f]{32}">#', $html) === 1, 'the time stamp');
		holds(str_contains($html, '<altcha-widget auto="onsubmit" challenge="https://site.example/monban/challenge" class="monban-widget" configuration="'), 'the widget');
		holds(str_contains($html, '" display="invisible" language="de" name="monban"></altcha-widget>'), 'invisible, German, the field name');
		holds(str_contains($html, '&quot;validationMessage&quot;:&quot;Bitte warte kurz, die Pr&uuml;fung l&auml;uft noch.&quot;'), 'the German label in the configuration');
		holds(str_contains($html, '&quot;humanInteractionSignature&quot;:false'), 'no interaction signature');
		holds(!str_contains($html, 'style='), 'no inline style');

		$again = Guard::fields($kirby);
		holds(!str_contains($again, '<link') && !str_contains($again, '<script'), 'the tags only once per page');
		holds(str_contains($again, '<altcha-widget'), 'the fields again');
	}

	public function testMinimumZeroSwitchesTheWaitOff(): void
	{
		$kirby = $this->app(['sayamaapps.monban.minimum' => 0, 'sayamaapps.monban.maximum' => 60]);
		$token = Guard::timeToken($kirby, 1000);

		expect(Guard::OK, Guard::timeCheck($kirby, $token, 1000), 'at once');
		expect(Guard::TOO_OLD, Guard::timeCheck($kirby, $token, 1061), 'after the maximum');
	}

	public function testTagsCanBeLeftOut(): void
	{
		$html = Guard::fields($this->app(['sayamaapps.monban.style' => false, 'sayamaapps.monban.script' => false]));

		holds(!str_contains($html, '<link') && !str_contains($html, '<script'), 'a site that bundles the files prints no tags');
		holds(!str_contains($html, 'language='), 'no language attribute for English');
		holds(!str_contains(Guard::fields($this->app()), 'data-monban-i18n'), 'no label file for English');
		holds(str_contains($html, '&quot;validationMessage&quot;:&quot;Please wait a moment'), 'the English label');
	}

	public function testThrottle(): void
	{
		$kirby = $this->app();

		holds(Guard::throttle($kirby, 'anna@example.com', 2, 60), 'first attempt');
		holds(Guard::throttle($kirby, 'anna@example.com', 2, 60), 'second attempt');
		holds(!Guard::throttle($kirby, 'anna@example.com', 2, 60), 'third attempt within the window');
		holds(Guard::throttle($kirby, 'ben@example.com', 2, 60), 'another key counts for itself');
		holds(!Guard::throttle($this->app(), 'anna@example.com', 2, 60), 'the count survives the request');
		foreach (glob($this->site . '/cache/*/*') ?: [] as $file) {
			holds(!str_contains($file, 'anna'), 'the key is hashed in the cache: ' . basename($file));
		}
	}

	public function testTimeStamp(): void
	{
		$kirby = $this->app();
		$now = 1_800_000_000;
		$token = Guard::timeToken($kirby, $now);

		holds(preg_match('/^1800000000\.[0-9a-f]{32}$/', $token) === 1, 'time and signature');
		expect(Guard::TOO_FAST, Guard::timeCheck($kirby, $token, $now), 'sent at once');
		expect(Guard::TOO_FAST, Guard::timeCheck($kirby, $token, $now + 2), 'sent after two seconds');
		expect(Guard::OK, Guard::timeCheck($kirby, $token, $now + 3), 'sent after three seconds');
		expect(Guard::OK, Guard::timeCheck($kirby, $token, $now + 86400), 'sent a day later');
		expect(Guard::TOO_OLD, Guard::timeCheck($kirby, $token, $now + 86401), 'sent after more than a day');
		expect(Guard::MISSING, Guard::timeCheck($kirby, '', $now + 10), 'no stamp');
		expect(Guard::MISSING, Guard::timeCheck($kirby, '1800000000', $now + 10), 'no signature');
		expect(Guard::MISSING, Guard::timeCheck($kirby, ($now - 100) . '.' . substr($token, 11), $now + 10), 'a forged time');
		expect(Guard::MISSING, Guard::timeCheck($kirby, 'x' . $token, $now + 10), 'a broken stamp');
		expect(Guard::MISSING, Guard::timeCheck($this->app(['sayamaapps.monban.secret' => 'another-secret-with-thirty-two-characters-or-more']), $token, $now + 10), 'another site');
	}
}
