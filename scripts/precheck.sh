#!/usr/bin/env bash
# Runs the static check the sites run (genba-precheck: no embedded scripts, no
# inline styles or handlers, no foreign hosts) over a scratch site that holds
# this plugin under site/plugins/monban, the way a site installs it.
set -euo pipefail
cd "$(dirname "$0")/.."
SCRATCH="$(mktemp -d)"
trap 'rm -rf "$SCRATCH"' EXIT
mkdir -p "$SCRATCH/site/plugins/monban" "$SCRATCH/public"
for entry in assets config snippets src autoload.php index.php composer.json README.md CHANGELOG.md LICENSE.md; do
	cp -R "$entry" "$SCRATCH/site/plugins/monban/"
done
vendor/bin/genba-precheck --site="$SCRATCH/site" --public="$SCRATCH/public" --require=none
