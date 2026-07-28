# Plugin File Layout

Sprint 0 splits the plugin into a small WordPress entrypoint plus focused include files. The split is intended to preserve behavior: class names, option keys, cron hooks, metric names, WP-CLI commands, and incident formats remain stable.

## Entrypoint

- `simula-security-telemetry-for-wordfence.php`
  - WordPress plugin header.
  - Direct-access guard.
  - `SSTFW_PLUGIN_FILE` and `SSTFW_PLUGIN_DIR` constants.
  - Explicit `require_once` loading for every include file.
  - Calls `Simula_Security_Telemetry_Metrics::init()`.

## Includes

Load order is explicit and dependency-oriented:

- `includes/class-config.php` defines option keys, cron hook names, metric defaults, and version metadata.
- `includes/class-util.php` contains shared filesystem, database, label, and path helpers.
- `includes/class-settings.php` handles option/state loading, validation, schedules, and state formatting.
- `includes/class-output.php` renders and writes Prometheus textfile output.
- `includes/class-wordfence-schema.php` detects Wordfence tables and columns.
- `includes/class-wordfence-collector.php` collects Wordfence and current WordPress posture values.
- `includes/class-wordpress-collector.php` collects WordPress settings, drift, account, cron, option, content, and filesystem IoC signals.
- `includes/class-wordfence.php` preserves the existing compatibility facade for Wordfence helper calls.
- `includes/class-incident-exporter.php` exports blocked-hit incident logs.
- `includes/class-metrics-service.php` coordinates metrics and incident export flows.
- `includes/class-admin.php` renders and handles the settings screen.
- `includes/class-cli.php` registers WP-CLI command behavior.
- `includes/class-metrics.php` wires WordPress hooks, cron schedules, lifecycle hooks, and WP-CLI registration.

## Smoke Check

Run the split-file bootstrap smoke check with:

```bash
php tests/bootstrap-smoke.php
```

The smoke check stubs the WordPress hook APIs used during plugin load, then verifies class loading, admin hooks, cron hooks, lifecycle hooks, WP-CLI registration, and export entrypoint callability.
