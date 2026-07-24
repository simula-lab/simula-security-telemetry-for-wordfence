#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-sstfw-zip-test}"
PLUGIN_SLUG="simula-security-telemetry-for-wordfence"

mkdir -p tests/runtime/output tests/runtime/zip
mkdir -p tests/runtime/empty-plugin-dir

./build-zip.sh "$PLUGIN_SLUG"
cp "$PLUGIN_SLUG.zip" "tests/runtime/zip/$PLUGIN_SLUG.zip"

compose() {
  if docker compose version >/dev/null 2>&1; then
    docker compose -f docker-compose.yml -f tests/docker-compose.zip.yml "$@"
    return
  fi

  docker-compose -f docker-compose.yml -f tests/docker-compose.zip.yml "$@"
}

cleanup() {
  if [ "${SSTFW_KEEP_TEST_STACK:-0}" = "1" ]; then
    return
  fi

  compose down -v --remove-orphans >/dev/null 2>&1 || true
}

trap cleanup EXIT

wp() {
  compose run --rm wpcli wp "$@" --allow-root
}

wait_for_wordpress_files() {
  local attempt

  for attempt in $(seq 1 60); do
    if compose run --rm --entrypoint sh wpcli -c 'test -f wp-load.php' >/dev/null 2>&1; then
      return 0
    fi

    sleep 2
  done

  echo "WordPress files were not available in the shared volume." >&2
  return 1
}

install_wordpress_if_needed() {
  if wp core is-installed >/dev/null 2>&1; then
    return 0
  fi

  wp core install \
    --url="${SSTFW_TEST_URL:-http://localhost:8080}" \
    --title="SSTFW Zip Test" \
    --admin_user=admin \
    --admin_password=password \
    --admin_email=admin@example.test \
    --skip-email
}

compose up -d db wordpress
wait_for_wordpress_files
install_wordpress_if_needed

wp plugin deactivate "$PLUGIN_SLUG" || true
wp plugin delete "$PLUGIN_SLUG" || true
wp plugin install "/tests/zip/$PLUGIN_SLUG.zip" --force --activate
wp eval-file /tests/wp-cli/configure-test-options.php
wp eval-file /tests/wp-cli/seed-wordfence-fixtures.php
wp simula-security-telemetry export

php tests/bin/assert-prom.php \
  tests/runtime/output/wordfence.prom \
  tests/golden/local-fixture.required-metrics.txt

echo "Release zip install smoke test passed."
