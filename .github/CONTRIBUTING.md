# Contributing

Bug reports, feature requests and pull requests are welcome. Support is provided on a best-effort basis.

## Before you start

Open an issue first for anything beyond a small fix, so the change can be discussed before work starts.

## Setup

```sh
git clone https://github.com/sayamaapps/kirby-monban.git
cd kirby-monban
composer install
```

To try the plugin on a site, link or copy the folder to `site/plugins/monban/`.

## Checks

`composer ci` runs the checks that need no global tool, in the order of the CI workflow: Composer validation and normalization, dependency checks, platform requirements, Psalm, PHP Mess Detector and the tests. Run it before you open a pull request. CI runs these checks on PHP 8.2 to 8.5, plus the two global tools below.

```sh
composer ci
```

The two checks that need global tools are not part of `composer ci`. php-cs needs PHP 8.3 or newer, and phpcpd conflicts with PHPUnit 11 in this project:

```sh
composer global require getkirby/php-cs:0.2.1
composer global require sebastian/phpcpd:6.0.3
kirby-cs fix --dry-run --diff               # code style, Kirby rule set
phpcpd --fuzzy --exclude tests --exclude vendor .   # duplicated code
```

The global bin directory must be in `PATH`.

## Conventions

- Tabs for indentation, LF line endings. `.editorconfig` lists the rest.
- Classes live in `src/`, one class per file. The class map in `autoload.php` lists them, and `tests/AutoloadTest.php` checks it.
- Keep public names stable: the field names, the result constants of `Guard::check()` and the route are the contract of a site's form handler (CHANGELOG, project rules).
- The ALTCHA files in `assets/` are copied from the npm package `altcha` (`dist/external/altcha.min.js`, `dist/external/altcha.css`, `dist/workers/`, `dist/i18n/`). Update them as a whole and note the version in the CHANGELOG.
- Commit messages follow Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `style:`, `chore:`).

## Pull requests

Describe what changes and why. Add or update tests for a bug fix or a feature. Use the pull request template.
