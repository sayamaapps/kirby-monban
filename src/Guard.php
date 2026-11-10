<?php

namespace Sayamaapps\Monban;

use Kirby\Cms\App;
use Kirby\Toolkit\Html;
use WeakMap;

/**
 * The gatekeeper: prints the protection into a form and checks a submitted
 * form.
 *
 * Three checks, in this order: the decoy field has to stay empty, the signed
 * time stamp has to be old enough and young enough, and the proof of work has
 * to be solved (Puzzle). `check()` returns one of the result constants; only
 * OK means the form may be processed. `throttle()` counts attempts per key,
 * for example per e-mail address, in the plugin's cache.
 *
 * @package   Kirby Monban
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-monban
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Guard
{
	public const EXPIRED = 'expired';
	public const HONEYPOT = 'honeypot';
	public const INVALID = 'invalid';

	/** The message the widget shows while the proof is not solved yet (HTML5 validation). */
	public const LABELS = [
		'en' => 'Please wait a moment, the check is still running.',
		'de' => 'Bitte warte kurz, die Prüfung läuft noch.',
	];
	public const MISSING = 'missing';
	public const OK = 'ok';
	public const REPLAY = 'replay';

	/** Every result `check()` can return. */
	public const RESULTS = [self::OK, self::HONEYPOT, self::TOO_FAST, self::TOO_OLD, self::MISSING, self::EXPIRED, self::REPLAY, self::INVALID];

	/** Name of the hidden field with the signed time stamp. */
	public const TIME_FIELD = 'monban_time';
	public const TOO_FAST = 'too-fast';
	public const TOO_OLD = 'too-old';

	/** URL of a file in the plugin's assets, or null when the plugin is not registered. */
	public static function assetUrl(App $kirby, string $file): string|null
	{
		return $kirby->plugin('sayamaapps/monban')?->asset($file)?->url();
	}

	/**
	 * Checks a submitted form: `$input` is the posted data (`$kirby->request()->data()`).
	 * Returns OK, or the first check that failed. With Monban off, always OK.
	 */
	public static function check(App $kirby, array $input, int|null $now = null): string
	{
		if (!Options::active($kirby)) {
			return self::OK;
		}
		$decoy = $input[Options::honeypot($kirby)] ?? '';
		if (is_string($decoy) && trim($decoy) !== '') {
			return self::HONEYPOT;
		}
		$time = $input[self::TIME_FIELD] ?? '';
		$result = self::timeCheck($kirby, is_string($time) ? $time : '', $now ?? time());
		if ($result !== self::OK) {
			return $result;
		}
		$payload = $input[Puzzle::FIELD] ?? '';
		return Puzzle::verify($kirby, is_string($payload) ? $payload : '');
	}

	/**
	 * The protection of one form: the tags (once per page), the decoy field, the
	 * time stamp and the widget. Empty while Monban is off.
	 */
	public static function fields(App $kirby): string
	{
		if (!Options::active($kirby)) {
			return '';
		}
		$language = Options::language($kirby);
		$decoyId = 'monban-' . bin2hex(random_bytes(4));
		$configuration = json_encode([
			'hideFooter' => true,
			'hideLogo' => true,
			'humanInteractionSignature' => false,
			'validationMessage' => self::LABELS[$language ?? 'en'],
		], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

		$html = self::tags($kirby);
		$html .= '<div class="monban-decoy" aria-hidden="true">';
		$html .= '<label for="' . $decoyId . '">Website</label>';
		$html .= '<input id="' . $decoyId . '" type="text" name="' . Html::encode(Options::honeypot($kirby)) . '" tabindex="-1" autocomplete="off">';
		$html .= '</div>';
		$html .= '<input type="hidden" name="' . self::TIME_FIELD . '" value="' . Html::encode(self::timeToken($kirby, time())) . '">';
		$html .= '<altcha-widget ' . Html::attr([
			'class' => 'monban-widget',
			'challenge' => $kirby->url() . '/' . Puzzle::ROUTE,
			'auto' => 'onsubmit',
			'display' => 'invisible',
			'name' => Puzzle::FIELD,
			'language' => $language,
			'configuration' => $configuration,
		]) . '></altcha-widget>';
		return $html;
	}

	/**
	 * The attributes of the script tag: `src` (monban.js), `type` and the URLs
	 * of the widget, the workers and the labels as `data-monban-*`. Kirby serves
	 * every asset under a folder of its own, so the script cannot import its
	 * neighbours by relative path: the URLs travel with the tag. A site that
	 * prints the tag itself (`script` set to false) prints these attributes.
	 *
	 * @return array<string, string|null> attribute => value; `data-monban-i18n` is null for English
	 */
	public static function scriptAttributes(App $kirby): array
	{
		$language = Options::language($kirby);
		return [
			'src' => self::assetUrl($kirby, 'monban.js'),
			'type' => 'module',
			'data-monban-altcha' => self::assetUrl($kirby, 'altcha.js'),
			'data-monban-pbkdf2' => self::assetUrl($kirby, 'workers/pbkdf2.js'),
			'data-monban-sha' => self::assetUrl($kirby, 'workers/sha.js'),
			'data-monban-i18n' => $language === null ? null : self::assetUrl($kirby, 'i18n/' . $language . '.js'),
		];
	}

	/** HMAC of a time stamp with the site's secret, 32 hex characters. */
	private static function sign(App $kirby, string $time): string
	{
		return substr(hash_hmac('sha256', 'time:' . $time, Options::secret($kirby)), 0, 32);
	}

	/**
	 * The <link> tags and the script tag for the widget, once per page: the first
	 * call prints them, later ones print nothing. External files only, so a
	 * Content Security Policy needs no 'unsafe-inline'. `style` set to false
	 * leaves the <link> tags out, `script` set to false the script tag; the site
	 * then prints a tag with scriptAttributes() itself.
	 */
	public static function tags(App $kirby): string
	{
		static $printed = null;
		$printed ??= new WeakMap();
		if (isset($printed[$kirby]) || !Options::active($kirby)) {
			return '';
		}
		$printed[$kirby] = true;

		$html = '';
		if (Options::get($kirby, 'style', true) !== false) {
			foreach (['altcha.css', 'monban.css'] as $file) {
				$url = self::assetUrl($kirby, $file);
				if ($url !== null) {
					$html .= '<link rel="stylesheet" href="' . Html::encode($url) . '">';
				}
			}
		}
		$attributes = self::scriptAttributes($kirby);
		if (Options::get($kirby, 'script', true) !== false && $attributes['src'] !== null) {
			// `src` written out, so a static check for embedded scripts sees it.
			$html .= '<script src="' . Html::encode($attributes['src']) . '" type="module" ' . Html::attr(array_diff_key($attributes, ['src' => 1, 'type' => 1])) . '></script>';
		}
		return $html;
	}

	/**
	 * Counts an attempt for `$key` and tells whether it is still allowed: true
	 * while fewer than `$max` attempts fell into the window of `$minutes`. The
	 * window starts again with every attempt. The key is hashed before it is
	 * stored, so an e-mail address or an IP never lands in the cache as text.
	 */
	public static function throttle(App $kirby, string $key, int $max, int $minutes): bool
	{
		$cache = $kirby->cache(Options::CACHE);
		$name = 'throttle-' . hash('sha256', $key);
		$count = (int)$cache->get($name, 0);
		if ($count >= $max) {
			return false;
		}
		$cache->set($name, $count + 1, max(1, $minutes));
		return true;
	}

	/**
	 * Checks the signed time stamp of the form against `$now`: OK, MISSING (no
	 * stamp or a forged one), TOO_FAST (sent before `minimum` seconds passed)
	 * or TOO_OLD (older than `maximum` seconds). With `minimum` 0, only forged
	 * stamps fail.
	 */
	public static function timeCheck(App $kirby, string $token, int $now): string
	{
		$parts = explode('.', $token, 2);
		if (count($parts) !== 2 || preg_match('/^[0-9]{1,12}$/', $parts[0]) !== 1 || !hash_equals(self::sign($kirby, $parts[0]), $parts[1])) {
			return self::MISSING;
		}
		$age = $now - (int)$parts[0];
		if ($age < Options::minimum($kirby)) {
			return self::TOO_FAST;
		}
		if ($age > Options::maximum($kirby)) {
			return self::TOO_OLD;
		}
		return self::OK;
	}

	/** The value of the hidden time field: the Unix time and its signature. */
	public static function timeToken(App $kirby, int $now): string
	{
		return $now . '.' . self::sign($kirby, (string)$now);
	}
}
