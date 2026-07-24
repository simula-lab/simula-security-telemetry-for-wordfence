# Test Harness

Sprint 1 adds a disposable WordPress validation path for the split plugin and later v3.0 collectors.

## Fast Local Checks

Run syntax and bootstrap checks without Docker:

```bash
tests/bin/check-local.sh
```

This runs PHP syntax checks, shell syntax checks, and `tests/bootstrap-smoke.php`.
It also runs dependency-free unit tests with `tests/bin/run-unit.php`.

## Coverage

Run line coverage for unit tests with:

```bash
php tests/bin/coverage.php
```

Coverage requires Xdebug or PCOV. The command writes `tests/runtime/coverage/coverage-summary.json`. To enforce the v3.0 target of 80% line coverage for `includes/`, run:

```bash
php tests/bin/coverage.php --min=80
```

## Fixture-Based WordPress Smoke Test

Run the local Docker WordPress stack with synthetic Wordfence-like fixtures:

```bash
tests/bin/run-local.sh
```

The script:

- Starts MariaDB, WordPress, and WP-CLI with `docker compose`.
- Installs WordPress if needed.
- Activates this plugin from the mounted workspace.
- Configures metrics and incident output under `tests/runtime/output`.
- Seeds synthetic Wordfence-like tables and administrator 2FA state.
- Runs WP-CLI smoke checks for `status`, full export, metrics-only export, incidents-only export, and cursor reset.
- Validates the generated `.prom` file against `tests/golden/local-fixture.required-metrics.txt`.

The generated files are ignored under `tests/runtime/`.

Set `SSTFW_KEEP_TEST_STACK=1` to keep containers and volumes after a run for debugging. By default, the scripts remove Compose volumes on exit.

## Publisher Integration

Run the same harness with official Wordfence installed from the WordPress plugin repository:

```bash
tests/bin/run-publisher.sh
```

This path requires outbound network access from Docker for WordPress.org plugin installation. It keeps the environment disposable and local-only, using the same isolated Compose network and test output directory.

Set image overrides when validating a pinned release candidate:

```bash
SSTFW_WORDPRESS_IMAGE=wordpress:6.6.2-php8.2-apache \
SSTFW_WPCLI_IMAGE=wordpress:cli-php8.2 \
tests/bin/run-publisher.sh
```

## Release Zip Install Smoke Test

Build and install the generated zip into the disposable WordPress stack:

```bash
tests/bin/run-zip-install.sh
```

This script depends on `build-zip.sh`, installs the generated archive with WP-CLI, activates the plugin, runs an export, and validates the generated `.prom` file.
