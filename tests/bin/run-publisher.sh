#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-sstfw-publisher-test}"

mkdir -p tests/runtime/output tests/runtime/zip

compose() {
  if docker compose version >/dev/null 2>&1; then
    docker compose "$@"
    return
  fi

  docker-compose "$@"
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

prepare_wordpress_install_dirs() {
  compose run --rm --user root --entrypoint sh wpcli -c 'mkdir -p wp-content/plugins wp-content/upgrade wp-content/uploads && chown www-data:www-data wp-content wp-content/plugins wp-content/upgrade wp-content/uploads'
}

install_wordpress_if_needed() {
  if wp core is-installed >/dev/null 2>&1; then
    return 0
  fi

  wp core install \
    --url="${SSTFW_TEST_URL:-http://localhost:8080}" \
    --title="SSTFW Publisher Test" \
    --admin_user=admin \
    --admin_password=password \
    --admin_email=admin@example.test \
    --skip-email
}

compose up -d db wordpress
wait_for_wordpress_files
prepare_wordpress_install_dirs
install_wordpress_if_needed

wp plugin install wordfence --activate --force
wp plugin activate simula-security-telemetry-for-wordfence
wp eval-file /tests/wp-cli/configure-test-options.php

wp simula-security-telemetry status
wp simula-security-telemetry export --metrics-only --scope=slow
wp simula-security-telemetry export

php tests/bin/assert-prom.php \
  tests/runtime/output/wordfence.prom \
  tests/golden/publisher.required-metrics.txt

echo "Publisher WordPress plus official Wordfence smoke test passed."
