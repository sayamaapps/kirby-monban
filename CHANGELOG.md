# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change to the form contract** (the names of the fields Monban adds, the
  result constants of `Guard::check()`, the route of the challenge) is at least
  a **minor** version and described under "Contract", so a site can re-check
  its form handlers.
- **A change to the bundled ALTCHA widget** is listed under "Widget" with its
  version.

## [1.0.1] — 2026-11-09

### Fixed

- The widget never loaded: Kirby serves every plugin asset under a hashed folder of its own, so `monban.js` could not import `altcha.js` and the workers by relative path. The script tag now carries their URLs as `data-monban-*` attributes.

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
