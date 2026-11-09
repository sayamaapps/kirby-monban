# Monban

Spam protection for forms in [Kirby CMS](https://getkirby.com) 5: a proof of work the browser solves on its own, a decoy field, a time check and a throttle, all served from your own server.

**Versions:** Kirby 5 · PHP 8.2 to 8.5 · MIT · [Changes](CHANGELOG.md)

Monban (門番, the gatekeeper) stands in front of a form. A visitor notices nothing: no puzzle, no checkbox, no pictures of buses. The browser does a short calculation when the form is sent ([ALTCHA](https://altcha.org), proof of work), and the server checks the answer in a fraction of a millisecond. A script that posts straight to your endpoint, fills every field it finds or sends within a second is refused before your code runs. Nothing is loaded from a third party, nothing is stored in the visitor's browser.

## Features

- Works with any form: one snippet inside the `<form>`, one call in the handler.
- Proof of work (ALTCHA 3, PBKDF2/SHA-256) in a Web Worker, invisible to the visitor; the answer is signed, expires and counts once.
- Decoy field off screen (not `display: none`, which bots recognise), signed time stamp against instant submits.
- `Guard::throttle()`: attempts per key and window, for example one mail per address and day. Keys are hashed.
- Zero configuration: the secret is generated and kept in the plugin's cache. Pin one with `sayamaapps.monban.secret`.
- Own server only: widget, workers and labels are plugin assets; no inline code, no `blob:` workers, no foreign host, no cookies. `default-src 'self'` is enough.
- English and German labels.

## Requirements

- Kirby 5 (`getkirby/cms` 5.0 or newer)
- PHP 8.2, 8.3, 8.4 or 8.5
- Composer: the plugin depends on `altcha-org/altcha` (PHP). A ZIP install has no verifier.
- A browser with Web Workers and `crypto.subtle` (every current browser). Without JavaScript, the form is refused with `Guard::MISSING`; say so in your form (see Usage).

## Installation

```sh
composer require sayamaapps/kirby-monban
```

The plugin lands in `site/plugins/monban/`. Add that folder to `.gitignore` and deploy it like `vendor/`.

## Usage

### In the form

Print the protection inside the `<form>`. It prints the decoy field, the time stamp, the widget and, once per page, the stylesheet and script tags:

```php
<form method="post" action="<?= url('kontakt') ?>">
    <?php snippet('monban') ?>
    <label for="email">E-Mail</label>
    <input id="email" type="email" name="email" required>
    <button type="submit">Send</button>
</form>
```

The widget is invisible. When the visitor presses the button, it fetches a challenge from `/monban/challenge`, solves it (about a second on a phone with the defaults) and sends the form with the answer in the field `monban`.

### In the handler

Check before anything else happens:

```php
use Sayamaapps\Monban\Guard;

$result = Guard::check(kirby(), kirby()->request()->data());
if ($result !== Guard::OK) {
    // Refuse. Guard::HONEYPOT is a bot: answer as if everything worked and do nothing.
    // Everything else: ask the visitor to reload the page and try again.
}
```

`check()` returns one of:

| Result | Meaning |
|---|---|
| `Guard::OK` | Every check passed. |
| `Guard::HONEYPOT` | The decoy field was filled: a bot. Pretend success and do nothing. |
| `Guard::TOO_FAST` | Sent within `minimum` seconds after the form was printed. |
| `Guard::TOO_OLD` | The form was open longer than `maximum` seconds. |
| `Guard::MISSING` | No answer, no time stamp or a forged one: a script without a browser, or JavaScript off. |
| `Guard::EXPIRED` | The challenge was older than `expires` seconds. |
| `Guard::REPLAY` | This answer was used before. |
| `Guard::INVALID` | A wrong answer or a tampered challenge. |

With `sayamaapps.monban.active` set to `false`, the snippet prints nothing and `check()` returns `OK`: for a staging site or a test.

### Throttle

Count attempts per key, in the plugin's cache:

```php
if (!Guard::throttle(kirby(), 'mail:' . $email, 1, 1440)) {
    // This address already got a mail today. Pretend success and send nothing.
}
```

`throttle($kirby, $key, $max, $minutes)` returns `true` and counts the attempt while fewer than `$max` attempts fell into the last `$minutes` minutes; the window starts again with every attempt. The key is hashed before it is stored.

### Without JavaScript

The proof needs JavaScript. A form sent without it fails with `Guard::MISSING`. Tell the visitor in your error message, or offer another way to reach you next to the form.

## Options

Every option is `sayamaapps.monban.<name>`, written flat in `site/config/config.php`.

| Option | Default | Meaning |
|---|---|---|
| `active` | `true` | `false` switches Monban off: no fields, every check passes. |
| `secret` | `null` | Secret that signs challenges and time stamps, 32 characters or more. `null`: generated once and kept in the cache. |
| `cache` | `true` | The plugin's cache (`site/cache`): secret, used challenges, throttle counters. `false` needs a `secret` and switches replay protection and throttle off. |
| `cost` | `1000` | PBKDF2 iterations per attempt in the browser. |
| `rounds` | `3000` | Highest counter the browser may have to try; the server draws between a third and this. Work ≈ `rounds` × `cost`. |
| `expires` | `600` | Seconds a challenge stays valid. |
| `minimum` | `3` | Seconds a form has to be open before it may be sent. `0` switches the wait off. |
| `maximum` | `86400` | Seconds a form may stay open. |
| `honeypot` | `'website'` | Name of the decoy field. Change it when your form has a real field of that name. |
| `language` | request, default language, `locale`, then English | Labels, `en` or `de`. |
| `style` | `true` | `false` omits the `<link>` tags, for sites that bundle the stylesheets. |
| `script` | `true` | `false` omits the `<script>`, for sites that bundle the script. |

Example:

```php
return [
    'sayamaapps.monban.secret'   => 'a-long-random-string-of-at-least-32-characters',
    'sayamaapps.monban.language' => 'de',
    'sayamaapps.monban.honeypot' => 'homepage',
];
```

### Difficulty

With the defaults a phone needs about a second, a laptop a fraction of that, and the server about 0.1 ms per check. Raise `rounds` when bots still get through; lower it when visitors on old phones wait too long. A bot that runs a real browser can solve the puzzle; it pays with CPU time per submission, and `throttle()` caps what it gets for it.

## Content Security Policy

Every file comes from your server under `/media/plugins/sayamaapps/monban/`. The widget runs as `<script type="module">`, the workers are files (no `blob:`), the styles are stylesheets (no inline `<style>`), the challenge is fetched from your own origin. `default-src 'self'` covers everything; with separate directives you need `script-src 'self'`, `style-src 'self'`, `worker-src 'self'` and `connect-src 'self'`.

## Privacy

Monban processes no visitor data beyond the form itself: no cookies, no browser storage, no fingerprinting, no request to a third party. The ALTCHA widget's "human interaction signature" is switched off. The time stamp and the puzzle answer are checked and forgotten; the signature of a used challenge stays in the cache until the challenge would have expired. `throttle()` stores a hash of the key, never the key.

## Snippets and functions

| Name | Use |
|---|---|
| `snippet('monban')` | The protection of one form, inside the `<form>`. |
| `Guard::check($kirby, $input)` | Checks a submitted form; `OK` or the first failed check. |
| `Guard::throttle($kirby, $key, $max, $minutes)` | Counts attempts per key. |
| `Guard::fields($kirby)` | What the snippet prints, for your own snippet. |
| `Guard::tags($kirby)` | The `<link>` and `<script>` tags, once per page, for a site that prints them in `<head>`. |
| `Puzzle::challenge($kirby)` | A fresh challenge as an array, for your own route. |

A site that needs its own markup creates `site/snippets/monban.php`; Kirby uses that file instead of Monban's.

## Maintenance

Tests: `composer test`. Checks: see [CONTRIBUTING](.github/CONTRIBUTING.md). Issues and pull requests are welcome, without a support promise. Report security problems privately through GitHub's vulnerability reporting ([SECURITY](.github/SECURITY.md)).

## License

MIT, see [LICENSE.md](LICENSE.md). The bundled ALTCHA widget is MIT as well, © Daniel Regeci, see [assets/ALTCHA-LICENSE.txt](assets/ALTCHA-LICENSE.txt).
