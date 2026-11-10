<?php

namespace Sayamaapps\Monban;

use Kirby\Cms\App;
use LogicException;

/**
 * Reads Monban's options (`sayamaapps.monban.<key>`, defaults in
 * config/options.php) and keeps the secret that signs challenges and time
 * stamps.
 *
 * The secret comes from the option `secret` (at least 32 characters). Without
 * one, Monban generates a random secret on the first request and keeps it in
 * its cache, so a site needs no configuration at all. The cache has to be on
 * for that (it is, unless the site sets `sayamaapps.monban.cache` to false).
 *
 * @package   Kirby Monban
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-monban
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Options
{
	/** Name of the plugin's cache (`$kirby->cache(...)`). */
	public const CACHE = 'sayamaapps.monban';

	/** Languages with labels of their own; everything else gets English. */
	public const LANGUAGES = ['de'];

	/** Option prefix. */
	public const PREFIX = 'sayamaapps.monban.';

	/** Shortest secret Monban accepts, in characters. */
	public const SECRET_MIN = 32;

	/** Master switch. Everything but `false` means on. */
	public static function active(App $kirby): bool
	{
		return self::get($kirby, 'active') !== false;
	}

	/** PBKDF2 iterations per attempt in the browser; 100 or more. */
	public static function cost(App $kirby): int
	{
		return max(100, self::int($kirby, 'cost', 1000));
	}

	/** Seconds a challenge stays valid; 60 or more. */
	public static function expires(App $kirby): int
	{
		return max(60, self::int($kirby, 'expires', 600));
	}

	/** Reads an option under the sayamaapps.monban.* spelling. Returns $default when it is not set. */
	public static function get(App $kirby, string $key, mixed $default = null): mixed
	{
		return $kirby->option(self::PREFIX . $key) ?? $default;
	}

	/** Name of the decoy field; a bot fills it, a person never sees it. */
	public static function honeypot(App $kirby): string
	{
		$name = self::get($kirby, 'honeypot', 'website');
		return is_string($name) && preg_match('/^[a-z][a-z0-9_-]{0,40}$/i', $name) === 1 ? $name : 'website';
	}

	/** An integer option, or $default when it is not a number. */
	private static function int(App $kirby, string $key, int $default): int
	{
		$value = self::get($kirby, $key, $default);
		return is_int($value) || (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) ? (int)$value : $default;
	}

	/**
	 * Language of the labels: `sayamaapps.monban.language`, otherwise the
	 * language of the request, the default language, Kirby's `locale`.
	 * Returns a language from LANGUAGES or null for English.
	 */
	public static function language(App $kirby): string|null
	{
		$candidates = [self::get($kirby, 'language')];
		try {
			$candidates[] = $kirby->language()?->code();
			$candidates[] = $kirby->defaultLanguage()?->code();
		} catch (\Throwable) {
			// No languages configured.
		}
		$locale = $kirby->option('locale');
		if (is_array($locale)) {
			$locale = $locale[LC_ALL] ?? reset($locale);
		}
		$candidates[] = is_string($locale) ? $locale : null;

		foreach ($candidates as $candidate) {
			if (is_string($candidate) && in_array(strtolower(substr($candidate, 0, 2)), self::LANGUAGES, true)) {
				return strtolower(substr($candidate, 0, 2));
			}
		}
		return null;
	}

	/** Seconds a form may stay open before its time stamp is too old; at least the minimum plus one. */
	public static function maximum(App $kirby): int
	{
		return max(self::minimum($kirby) + 1, self::int($kirby, 'maximum', 86400));
	}

	/** Seconds a form has to be open before it may be sent; 0 switches the time check off. */
	public static function minimum(App $kirby): int
	{
		return max(0, self::int($kirby, 'minimum', 3));
	}

	/** Highest counter the browser may have to try; 100 or more. */
	public static function rounds(App $kirby): int
	{
		return max(100, self::int($kirby, 'rounds', 3000));
	}

	/**
	 * The secret that signs challenges and time stamps: the option `secret`, or a
	 * generated one kept in the plugin's cache. The first request creates it
	 * under the lock of `Lock`; requests that arrive together read that one. A
	 * request that cannot get the lock in time signs with a secret of its own
	 * that is not kept, so what it signed does not verify later: it is turned
	 * away like the other steps, and no second secret ever replaces the kept one.
	 * On such a timeout the visitor's form renders but the submit is rejected,
	 * and a retry works.
	 *
	 * @throws LogicException when neither a secret is configured nor the cache can keep one
	 */
	public static function secret(App $kirby): string
	{
		$secret = self::get($kirby, 'secret');
		if (is_string($secret) && strlen($secret) >= self::SECRET_MIN) {
			return $secret;
		}

		$cache = $kirby->cache(self::CACHE);
		$kept = $cache->get('secret');
		if (is_string($kept) && strlen($kept) >= self::SECRET_MIN) {
			return $kept;
		}

		// The first request creates the secret; requests that arrive together wait for the lock and read it.
		return Lock::run($kirby, static function () use ($cache): string {
			$kept = $cache->get('secret');
			if (is_string($kept) && strlen($kept) >= self::SECRET_MIN) {
				return $kept;
			}

			$generated = bin2hex(random_bytes(32));
			$cache->set('secret', $generated, 0);
			if ($cache->get('secret') !== $generated) {
				throw new LogicException('Monban needs a secret: set sayamaapps.monban.secret (32 characters or more) or keep sayamaapps.monban.cache on.');
			}
			return $generated;
		}, bin2hex(random_bytes(32)));
	}
}
