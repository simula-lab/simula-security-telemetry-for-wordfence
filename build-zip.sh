#!/usr/bin/env bash
set -euo pipefail

ZIP_EXCLUDES=(-x "*/.*" ".*")

# Usage: ./build-zip.sh [--no-assets] [plugin-slug]
# The default slug is stable even if the checkout directory is renamed.
# Pass --no-assets to skip including the assets/ folder.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

# 0) Parse flags
NO_ASSETS=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-assets)
      NO_ASSETS=1
      shift
      ;;
    -*)
      echo "Unknown option: $1"
      exit 1
      ;;
    *)
      break
      ;;
  esac
done

# 1) Determine plugin slug & target ZIP name
PLUGIN_SLUG="${1:-simula-security-telemetry-for-wordfence}"
if [[ ! "$PLUGIN_SLUG" =~ ^[a-z0-9][a-z0-9-]*$ ]] || [ "$#" -gt 1 ]; then
  echo "Usage: $0 [--no-assets] [plugin-slug] (lowercase letters, digits, and hyphens)" >&2
  exit 1
fi
ZIP_FILE="$ROOT_DIR/${PLUGIN_SLUG}.zip"

# 2) Compile .po → .mo
if [ -d "languages" ]; then
  echo "Compiling translation files…"
  for PO in languages/*.po; do
    [ -f "$PO" ] || continue
    MO="${PO%.po}.mo"
    msgfmt "$PO" -o "$MO"
    echo "  → $PO → $MO"
  done
else
  echo "Warning: no 'languages/' directory found; skipping .mo generation."
fi

# 3) Stage a single, stable top-level plugin directory. Without this wrapper,
# WordPress uses the temporary upload filename as the installed directory name.
BUILD_DIR="$(mktemp -d "${TMPDIR:-/tmp}/sstfw-build.XXXXXX")"
trap 'rm -rf "$BUILD_DIR"' EXIT
PLUGIN_DIR="$BUILD_DIR/$PLUGIN_SLUG"
mkdir "$PLUGIN_DIR"

# 4) Build the ZIP
echo "Creating $ZIP_FILE …"
# 4a) add root PHP files
cp ./*.php "$PLUGIN_DIR/"

# 4b) add include files
[ ! -d "includes" ] || cp -R includes "$PLUGIN_DIR/"

# 4c) add readme
[ ! -f "readme.txt" ] || cp readme.txt "$PLUGIN_DIR/"

# 4d) add languages (pot, po, mo)
[ ! -d "languages" ] || cp -R languages "$PLUGIN_DIR/"

# 4e) keep repository-only Grafana and Prometheus examples out of the WordPress.org plugin zip

# 4f) add assets (unless excluded)
if [ "$NO_ASSETS" -eq 1 ]; then
  echo "Skipping assets directory as requested."
else
  [ ! -d "assets" ] || cp -R assets "$PLUGIN_DIR/"
fi

(
  cd "$BUILD_DIR"
  zip -qr package.zip "$PLUGIN_SLUG" "${ZIP_EXCLUDES[@]}"
)
# Replace the previous artifact only after the new archive has been built.
mv "$BUILD_DIR/package.zip" "$ZIP_FILE"

echo "✅ $ZIP_FILE created successfully."
