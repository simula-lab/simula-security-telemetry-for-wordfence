#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l

for script in build-zip.sh tests/bin/*.sh; do
  bash -n "$script"
done

php tests/bootstrap-smoke.php
