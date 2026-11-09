<?php

use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Monban\Options;

/**
 * The options: defaults, lower bounds, the decoy field's name, the language
 * and the secret (configured, generated and kept, or refused without a cache).
 */
#[CoversClass(Options::class)]
final class OptionsTest extends MonbanTestCase
{
	public function testConfiguredSecretWins(): void
	{
		expect(self::SECRET, Options::secret($this->app()), 'the configured secret');
	}

	public function testDefaults(): void
	{
		$kirby = $this->app();

		holds(Options::active($kirby), 'on by default');
		expect(1000, Options::cost($kirby), 'cost');
		expect(600, Options::expires($kirby), 'expires');
		expect('website', Options::honeypot($kirby), 'decoy field');
		expect(3, Options::minimum($kirby), 'minimum');
		expect(86400, Options::maximum($kirby), 'maximum');
		expect(3000, Options::rounds($kirby), 'rounds');
		expect(null, Options::language($kirby), 'no language configured means English');
	}

	public function testGeneratedSecretIsKeptAcrossRequests(): void
	{
		$first = Options::secret($this->app(['sayamaapps.monban.secret' => 'too short']));
		$second = Options::secret($this->app(['sayamaapps.monban.secret' => null]));

		holds(preg_match('/^[0-9a-f]{64}$/', $first) === 1, 'a generated secret is 64 hex characters');
		expect($first, $second, 'the next request reads the same secret from the cache');
	}

	public function testLanguage(): void
	{
		expect('de', Options::language($this->app(['sayamaapps.monban.language' => 'de'])), 'configured');
		expect('de', Options::language($this->app(['locale' => 'de_DE.UTF-8'])), 'from the locale');
		expect('de', Options::language($this->app(['locale' => [LC_ALL => 'de_AT']])), 'from a locale array');
		expect(null, Options::language($this->app(['locale' => 'fr_FR'])), 'a language without labels means English');
	}

	public function testLowerBoundsAndInvalidValues(): void
	{
		$kirby = $this->app([
			'sayamaapps.monban.active'   => false,
			'sayamaapps.monban.cost'     => 10,
			'sayamaapps.monban.expires'  => 5,
			'sayamaapps.monban.honeypot' => 'no spaces allowed',
			'sayamaapps.monban.maximum'  => 2,
			'sayamaapps.monban.minimum'  => 10,
			'sayamaapps.monban.rounds'   => 'many',
		]);

		holds(!Options::active($kirby), 'false switches off');
		expect(100, Options::cost($kirby), 'cost never below 100');
		expect(60, Options::expires($kirby), 'expires never below 60 seconds');
		expect('website', Options::honeypot($kirby), 'an invalid field name falls back to the default');
		expect(11, Options::maximum($kirby), 'maximum at least minimum plus one');
		expect(0, Options::minimum($this->app(['sayamaapps.monban.minimum' => -4])), 'minimum never negative');
		expect(3000, Options::rounds($kirby), 'a value that is no number keeps the default');
	}

	public function testNumbersMayComeAsStrings(): void
	{
		$kirby = $this->app(['sayamaapps.monban.cost' => '2500', 'sayamaapps.monban.honeypot' => 'kontakt_url']);

		expect(2500, Options::cost($kirby), 'digits in a string count');
		expect('kontakt_url', Options::honeypot($kirby), 'letters, digits, underscore and hyphen are allowed');
	}

	public function testWithoutCacheAndSecretMonbanRefusesToWork(): void
	{
		$kirby = $this->app(['sayamaapps.monban.secret' => null, 'sayamaapps.monban.cache' => false]);

		$this->expectException(LogicException::class);
		Options::secret($kirby);
	}
}
