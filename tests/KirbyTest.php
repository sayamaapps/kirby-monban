<?php

use Kirby\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use Sayamaapps\Monban\Guard;
use Sayamaapps\Monban\Puzzle;

/**
 * The plugin inside Kirby: the challenge route, the snippet and the assets.
 */
#[CoversClass(Guard::class)]
#[CoversClass(Puzzle::class)]
final class KirbyTest extends MonbanTestCase
{
	public function testAssetsExist(): void
	{
		$kirby = $this->app();
		foreach (['altcha.js', 'altcha.css', 'monban.js', 'monban.css', 'workers/pbkdf2.js', 'workers/sha.js', 'i18n/de.js'] as $file) {
			$url = Guard::assetUrl($kirby, $file);
			holds(is_string($url) && str_ends_with($url, '/' . $file), 'asset served: ' . $file);
			holds(is_file(dirname(__DIR__) . '/assets/' . $file), 'asset on disk: ' . $file);
		}
	}

	public function testChallengeRoute(): void
	{
		$kirby = $this->app();
		$response = $kirby->call(Puzzle::ROUTE, 'GET');

		holds($response instanceof Response, 'the route answers with a response');
		expect('application/json', $response->type(), 'JSON');
		expect('no-store', $response->headers()['Cache-Control'] ?? null, 'never cached');
		holds(isset(json_decode($response->body(), true)['parameters']['keyPrefix']), 'a challenge');
	}

	public function testSnippetPrintsTheFields(): void
	{
		$kirby = $this->app();
		$html = snippet('monban', [], true);

		holds(str_contains($html, '<altcha-widget'), 'the widget');
		holds(str_contains($html, 'name="monban_time"'), 'the time stamp');
		holds(str_contains($html, 'name="website"'), 'the decoy field');
		expect(Guard::MISSING, Guard::check($kirby, [], time()), 'an empty submission is refused');
	}
}
