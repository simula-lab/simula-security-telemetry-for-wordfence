# Simula Wordfence Grafana Integration

`Simula Wordfence Grafana Integration` is a WordPress plugin that exports Wordfence security telemetry in two operator-friendly forms:

- Prometheus metrics for the `node_exporter` textfile collector
- A local incident log containing blocked Wordfence requests

It is designed for WordPress sites that already run:

- Wordfence
- Prometheus
- `node_exporter` with the textfile collector enabled

The plugin writes local files on a schedule, so Prometheus and log-based tooling can ingest Wordfence activity without exposing a public metrics endpoint from WordPress.

## What It Does

The plugin reads data from available Wordfence tables and can write:

- A Prometheus `.prom` file such as `/var/lib/node_exporter/textfile_collector/wordfence.prom`
- An append-only incident log such as `/var/log/wordpress-wordfence-incidents.log`

By default, it runs every 5 minutes using WP-Cron and supports:

- Exporter health and plugin metadata metrics
- Configurable cron interval
- Per-metric-family enable or disable controls
- Blocked event counters and recent activity windows
- Blocked event counts by HTTP status code
- Failed login, rate-limited, and brute-force activity windows
- Current lockout counts for IPs and users
- Wordfence two-factor status and protected user counts
- Scan issue counts by severity
- Malware, file change, and vulnerable component findings
- Top blocked attack sources by country and normalized IP range
- Incident log export for newly observed blocked requests
- Manual export from the admin screen
- Incident cursor reset for controlled backfill
- Current exporter and incident state visibility in the admin UI

Blocked events are currently identified as Wordfence hits where:

- `action LIKE 'blocked:%'`
- or `statusCode/status IN (403, 503)`

## Requirements

- WordPress `6.0+`
- PHP `7.4+`
- Wordfence installed and writing to the WordPress database
- `node_exporter` textfile collector enabled
- A writable directory for the `.prom` output file
- A writable directory for the incident log file if incident export is enabled

## Installation

1. Copy this repository into your WordPress plugins directory.
2. Ensure the main plugin file is present:

```text
simula-wordfence-grafana-integration.php
```

3. Activate the plugin from the WordPress admin panel.
4. Confirm that the PHP process can write to your textfile collector directory.
5. If you want incident log export, confirm that the configured log directory already exists and is writable by PHP.

## Configuration

After activation, go to:

```text
Settings > Wordfence Metrics
```

### Prometheus metrics settings

- `Enable exporter`
- `Cron interval`
- `Prometheus file path`
- `Metric prefix`
- `Site label`
- `Exported metrics`
  Each metric family can be enabled or disabled independently.

### Incident log settings

- `Enable incident log export`
- `Incident log path`
- `Max incidents per run`

### Manual actions and state

The settings page also provides:

- `Export now`
- `Reset incident cursor for backfill`
- A current state table showing the latest metrics and incident export results
- A sample incident log line

Default values:

- `Cron interval`: `Every five minutes`
- `Prometheus file path`: `/var/lib/node_exporter/textfile_collector/wordfence.prom`
- `Metric prefix`: `wordpress_wordfence`
- `Site label`: current site host name
- `Incident log path`: `/var/log/wordpress-wordfence-incidents.log`
- `Max incidents per run`: `1000`

Path validation rules:

- The Prometheus path must be absolute and end with `.prom`
- The incident log path must be absolute and end with `.log` or `.jsonl`
- Target directories must already exist and be writable by PHP

## Configure `node_exporter`

`node_exporter` must run with the textfile collector enabled and pointed at the same directory configured in the plugin.

Example `systemd` override:

```ini
[Service]
ExecStart=
ExecStart=/usr/local/bin/node_exporter \
  --collector.textfile \
  --collector.textfile.directory=/var/lib/node_exporter/textfile_collector
```

If you install `node_exporter` from a package, the binary path may be different, but the important part is:

```text
--collector.textfile
--collector.textfile.directory=/var/lib/node_exporter/textfile_collector
```

After updating the service, reload and restart it:

```bash
sudo systemctl daemon-reload
sudo systemctl restart node_exporter
```

Then verify:

- The plugin is configured to write a `.prom` file inside the collector directory
- The WordPress/PHP user can write that file
- The `node_exporter` user can read that file
- Prometheus is already scraping the `node_exporter` instance

## Exported Metrics

The metric prefix is configurable. With the default prefix `wordpress_wordfence`, the plugin can export the following metric families.

Several advanced security metrics are best-effort and depend on which Wordfence tables and columns are available on the site. When the underlying data source is unavailable, fixed-value gauges fall back to `0` and dynamic labeled series may be omitted.

All metrics include a `site` label.

### Core exporter metrics

- `wordpress_wordfence_export_success`
  Indicates whether the last export succeeded.
- `wordpress_wordfence_plugin_info{version="1.0.0"}`
  Static plugin metadata metric.
- `wordpress_wordfence_last_export_timestamp_seconds`
  Unix timestamp of the last export attempt or successful export.
- `wordpress_wordfence_enabled`
  `1` when the exporter master switch is enabled, `0` otherwise.
- `wordpress_wordfence_error_info{message="..."}`
  Failure-state marker containing the latest export error.

### Wordfence activity metrics

- `wordpress_wordfence_blocked_events_total`
  Cumulative counter of newly observed blocked hits.
- `wordpress_wordfence_blocked_events_window{window="5m|1h|24h|7d"}`
  Blocked hits in recent time windows.
- `wordpress_wordfence_blocked_events_by_status_24h{status="..."}`
  Blocked hits over the last 24 hours grouped by HTTP status.
- `wordpress_wordfence_failed_login_attempts_window{window="5m|1h|24h|7d"}`
  Failed login activity in recent windows.
- `wordpress_wordfence_rate_limited_events_window{window="5m|1h|24h|7d"}`
  Rate-limited or throttled requests in recent windows.
- `wordpress_wordfence_brute_force_events_window{vector="username|xmlrpc",window="5m|1h|24h|7d"}`
  Brute-force activity in recent windows.
- `wordpress_wordfence_top_attack_sources_24h{source_type="country|ip_range",source="..."}`
  Top blocked attack sources over the last 24 hours.

### Access-control and scan metrics

- `wordpress_wordfence_locked_out_total{target="ip|user"}`
  Current lockout totals grouped by target type.
- `wordpress_wordfence_two_factor_enabled`
  Whether Wordfence two-factor authentication appears configured.
- `wordpress_wordfence_two_factor_protected_users_total`
  Count of users with Wordfence two-factor secrets configured.
- `wordpress_wordfence_scan_issues_by_severity{severity="..."}`
  Current Wordfence scan issues grouped by severity.
- `wordpress_wordfence_scan_findings_total{category="malware|file_change"}`
  Current malware-like and file-change findings.
- `wordpress_wordfence_vulnerability_findings_total{component="core|plugin|theme"}`
  Current vulnerable or outdated core, plugin, and theme findings.

## Incident Log Export

When incident export is enabled, each run appends newly observed blocked Wordfence hits to the configured log file as plain-text log lines. A `.jsonl` suffix is accepted for compatibility, but the output format is still plain text rather than JSON.

Operational behavior:

- The exporter tracks the last processed Wordfence hit ID
- Activation initializes the cursor at the current maximum hit ID to avoid an unexpected full historical backfill
- `Reset incident cursor for backfill` sets the cursor to `0`, so the next run can replay retained incidents up to the configured row limit
- `Max incidents per run` limits how much history can be appended in a single pass
- Incident log writes use an exclusive file lock

Example log line:

```text
[23-May-2026 12:34:56 UTC] Wordfence blocked request: site="example.com" hostname="web-01" blog_id=1 hit_id=123 ip="203.0.113.10" status=403 action="blocked:waf" reason="SQL injection attempt" method="POST" url="/wp-admin/admin-ajax.php" referer="https://example.com/" user_agent="curl/8.0" country="NO"
```

## Example Prometheus Scrape Flow

1. The plugin writes a `.prom` file locally on the WordPress host.
2. `node_exporter` reads the file through its textfile collector.
3. Prometheus scrapes `node_exporter`.
4. Grafana or alerting rules consume the resulting metrics.

## Operational Notes

- Metrics are written atomically by writing a temporary file and renaming it into place.
- If the exporter is disabled, both metrics and incident log exports are disabled.
- A manual export while disabled still rewrites exporter-state metrics so Prometheus can see that exporting is off.
- If the Wordfence schema is missing required tables or columns, the exporter writes failure-state metrics instead of silently doing nothing.
- WP-Cron is traffic-driven. Low-traffic sites may export less precisely unless WordPress cron is triggered by a real system cron job.
- The incident log file is not deleted on uninstall; only the generated `.prom` file is removed automatically.

## Security and Permissions

This plugin does not expose a public metrics endpoint. Metrics and incidents are written to local files for collection by `node_exporter` and log tooling.

You should:

- Use file paths outside the public web root
- Restrict filesystem permissions appropriately
- Ensure the web server or PHP user can write the target files
- Ensure `node_exporter` can read the `.prom` file

## Development

Repository structure is intentionally minimal:

- [simula-wordfence-grafana-integration.php](/Users/ouss/Documents/workspace/simula/wordpress/plugins_repos/simula-wordfence-grafana-integration/simula-wordfence-grafana-integration.php:1)

The plugin is currently implemented as a single-file WordPress plugin for simple deployment and review.

## License

GPL-2.0-or-later. See the plugin header for licensing details.
