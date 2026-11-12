#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLUGIN_SLUG="simula-security-telemetry-for-wordfence"
ZIP_FILE="${1:-$ROOT_DIR/$PLUGIN_SLUG.zip}"

unzip -tq "$ZIP_FILE"
ENTRIES="$(unzip -Z1 "$ZIP_FILE")"
printf '%s\n' "$ENTRIES" | awk -v prefix="$PLUGIN_SLUG/" '
  index($0, prefix) != 1 { print "Unexpected ZIP entry: " $0; failed = 1 }
  $0 == prefix "simula-security-telemetry-for-wordfence.php" { main = 1 }
  $0 == prefix "includes/class-config.php" { includes = 1 }
  $0 == prefix "readme.txt" { readme = 1 }
  END { exit (failed || !main || !includes || !readme) }
'

echo "Release ZIP has one canonical plugin directory."
