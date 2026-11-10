# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change to the form contract** (the names of the fields Monban adds, the
  result constants of `Guard::check()`, the route of the challenge) is at least
  a **minor** version and described under "Contract", so a site can re-check
  its form handlers.
- **A change to the bundled ALTCHA widget** is listed under "Widget" with its
  version.

## Unreleased

### Fixed

- Two first requests at once no longer end up with different secrets. Without a configured `sayamaapps.monban.secret`, both found no secret in the cache, each generated one and the last write won, so a challenge signed with the other secret was rejected once. The secret is now created under the same exclusive lock as the replay protection (`Lock`): the first request creates it, the others read it. A request that cannot get the lock in time uses a secret of its own that is not kept, so it never replaces the kept one. A configured secret is read as before, without the lock.
- Parallel requests could undermine the replay protection and the throttle: both read a value from the cache, decided and then wrote it back, so requests that arrived together all saw the old state. One challenge could be accepted several times, and `Guard::throttle()` could let through more attempts than its limit. Reading and writing now happen under an exclusive file lock in the cache folder (`Lock`), released when the step ends and by the system if the process dies. The lock covers the requests of one server. A request that cannot get the lock within two seconds (a flood, a stuck process) is turned away instead of running unlocked: `Guard::throttle()` returns `false`, `Puzzle::verify()` returns `Guard::INVALID` and leaves the challenge unused. Where no lock is possible (cache off, lock file cannot be opened, cache folder not writable) the step runs without one, as before.

## [1.0.3] — 2026-11-10

### Added

- `Guard::scriptAttributes()`: the attributes of the script tag, for a site that prints the tag itself (`script` set to `false`). Since 1.0.1 such a site got no widget, because the script reads its URLs from its own tag; the README says so now.

### Fixed

- A failed load of the widget or the labels is reported in the console instead of ending as an unhandled promise rejection; a missing labels file leaves the widget working in English.
- The release checks run the same static check the sites run (`genba-precheck` over a scratch site with the plugin) instead of a line-based grep that was stricter in one place and blind in another.

### Docs

- 1.0.1 replaced the attribute `data-monban-language` with `data-monban-i18n`; 1.0.2 changed the order of the attributes (`src` first). The DOM is the same; only a test that compares the markup as text notices.

## [1.0.2] — 2026-11-09

### Fixed

- The script tag writes `src` out in the PHP source, so a static check for embedded scripts (one that reads a site's `site/` folder, plugins included) recognises it as an external script. The attributes are the same, `src` now comes first.

## [1.0.1] — 2026-11-09

### Fixed

- The widget never loaded: Kirby serves every plugin asset under a hashed folder of its own, so `monban.js` could not import `altcha.js` and the workers by relative path. The script tag now carries their URLs as `data-monban-*` attributes (`data-monban-altcha`, `-pbkdf2`, `-sha`, `-i18n`); `data-monban-language` is gone.

## [1.0.0] — 2026-11-09

### Added

- **Proof of work.** `snippet('monban')` prints an invisible ALTCHA widget into a form. When the visitor submits, the browser fetches a signed challenge from `/monban/challenge`, solves it in a Web Worker and sends the answer with the form. `Guard::check()` verifies it on the server with one HMAC; every challenge counts once and expires.
- **Decoy field.** A text field placed off screen that a person never sees. A filled decoy is a bot.
- **Time stamp.** A signed stamp of the moment the form was printed. A form sent within three seconds (or after a day) is refused.
- **Throttle.** `Guard::throttle($kirby, $key, $max, $minutes)` counts attempts per key, for example one confirmation mail per address and day. Keys are hashed before they reach the cache.
- **Zero configuration.** Without an option, Monban generates a secret and keeps it in its cache. `sayamaapps.monban.secret` pins one.
- **Own server only.** Widget, workers and labels come from the plugin's assets. No inline code, no blob: workers, no foreign host, no cookies. A Content Security Policy with `default-src 'self'` needs no change.
- English and German labels.

### Widget

- ALTCHA 3.5.0 (MIT), the `external` build: styles and workers as separate files.
