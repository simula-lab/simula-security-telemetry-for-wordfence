# Simula Wordfence Grafana Integration

`Simula Wordfence Grafana Integration` is a WordPress plugin that exports Wordfence activity as Prometheus metrics for the `node_exporter` textfile collector.

It is designed for WordPress sites that already run:

- Wordfence
- Prometheus
- `node_exporter` with the textfile collector enabled

The plugin writes a `.prom` file on a schedule, allowing Prometheus to scrape Wordfence-related security telemetry without exposing a public metrics endpoint from WordPress.

## What It Does

The plugin reads data from Wordfence tables and writes metrics to a local file such as:

```text
/var/lib/node_exporter/textfile_collector/wordfence.prom
```

By default, it runs every 5 minutes using WP-Cron and exports:

- Export health
- Plugin version info
- Last export timestamp
- Whether scheduled exporting is enabled
- Total observed blocked events
- Blocked event counts over recent windows
- Blocked event counts by HTTP status code for the last 24 hours
- Failed login attempt counts over recent windows
- Locked out IP or user counts
- Two-factor authentication status and protected user counts
- Scan issue counts by severity
- Malware or file change detection counts from recent scans
- Rate-limited or throttled request counts
- Top attack sources by country or IP range
- Brute-force attack counts against usernames or XML-RPC
- Outdated core, plugin, or theme vulnerability findings reported by Wordfence scans

Blocked events are currently identified as Wordfence hits where:

- `action LIKE 'blocked:%'`
- or `statusCode IN (403, 503)`

## Requirements

- WordPress `6.0+`
- PHP `7.4+`
- Wordfence installed and writing to the WordPress database
- `node_exporter` textfile collector enabled
- A writable directory for the `.prom` output file

## Installation

1. Copy this repository into your WordPress plugins directory.
2. Ensure the main plugin file is present:

```text
simula-wordfence-node-exporter-integration.php
```

3. Activate the plugin from the WordPress admin panel.
4. Confirm that the PHP process can write to your textfile collector directory.

## Configuration

After activation, go to:

```text
Settings > Wordfence Metrics
```

Available settings:

- `Enable scheduled exports`
- `Prometheus file path`
  The directory for this path must already exist and be writable by the web server or PHP process running WordPress so the exporter can write the metrics file.
- `Metric prefix`
- `Site label`

Default values:

- `Prometheus file path`: `/var/lib/node_exporter/textfile_collector/wordfence.prom`
- `Metric prefix`: `wordpress_wordfence`
- `Site label`: current site host name

You can also trigger a manual export from the settings page using `Export now`.

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

- The plugin is configured to write to a file inside the collector directory, for example `/var/lib/node_exporter/textfile_collector/wordfence.prom`
- The WordPress/PHP user can write that file
- The `node_exporter` user can read that file
- Prometheus is already scraping the `node_exporter` instance

## Exported Metrics

The metric prefix is configurable. With the default prefix `wordpress_wordfence`, the plugin exports the following metrics.

Several advanced security metrics are best-effort and depend on which Wordfence tables and columns are available on the site. When the underlying data source is unavailable, fixed-value gauges fall back to `0` and dynamic labeled series may be omitted.

### Core exporter metrics

- `wordpress_wordfence_export_success`
  Indicates whether the last export succeeded.

- `wordpress_wordfence_plugin_info{version="1.0.0"}`
  Static plugin metadata metric.

- `wordpress_wordfence_last_export_timestamp_seconds`
  Unix timestamp of the last export attempt or success.

- `wordpress_wordfence_enabled`
  `1` when scheduled exporting is enabled, `0` otherwise.

### Wordfence event metrics

- `wordpress_wordfence_blocked_events_total`
  Cumulative count of newly observed blocked Wordfence hits.

- `wordpress_wordfence_blocked_events_window{window="5m|1h|24h|7d"}`
  Counts of blocked hits seen in recent time windows.

- `wordpress_wordfence_blocked_events_by_status_24h{status="..."}`
  Counts of blocked hits during the last 24 hours grouped by HTTP status code.

- `wordpress_wordfence_failed_login_attempts_window{window="5m|1h|24h|7d"}`
  Failed login attempts observed in recent windows.

- `wordpress_wordfence_locked_out_total{target="ip|user"}`
  Lockout totals by target type when Wordfence exposes the relevant data.

- `wordpress_wordfence_two_factor_enabled`
  `1` when Wordfence two-factor authentication appears to be configured, `0` otherwise.

- `wordpress_wordfence_two_factor_protected_users_total`
  Count of users with Wordfence two-factor secrets configured.

- `wordpress_wordfence_scan_issues_by_severity{severity="..."}`
  Current Wordfence scan issues grouped by severity.

- `wordpress_wordfence_scan_findings_total{category="malware|file_change"}`
  Current scan findings for malware-like and file-change categories.

- `wordpress_wordfence_rate_limited_events_window{window="5m|1h|24h|7d"}`
  Rate-limited or throttled requests observed in recent windows.

- `wordpress_wordfence_top_attack_sources_24h{source_type="country|ip_range",source="..."}`
  Top blocked attack sources during the last 24 hours.

- `wordpress_wordfence_brute_force_events_window{vector="username|xmlrpc",window="5m|1h|24h|7d"}`
  Brute-force activity observed in recent windows.

- `wordpress_wordfence_vulnerability_findings_total{component="core|plugin|theme"}`
  Current scan findings indicating outdated or vulnerable core, plugin, or theme components.

### Failure metric

On export failure, the plugin also emits:

- `wordpress_wordfence_error_info{message="..."}`
  Static error marker carrying the latest failure message as a label.

All metrics include a `site` label.

## Example Prometheus Scrape Flow

1. The plugin writes a `.prom` file locally on the WordPress host.
2. `node_exporter` reads the file through its textfile collector.
3. Prometheus scrapes `node_exporter`.
4. Grafana or alerting rules consume the resulting metrics.

## Operational Notes

- The plugin writes metrics atomically by writing a temporary file and renaming it into place.
- If exporting is disabled, it still writes exporter state metrics indicating that exports are disabled.
- If the Wordfence table is missing or the export fails, the output includes failure-state metrics instead of silently doing nothing.
- WP-Cron is traffic-driven. Low-traffic sites may export less precisely than every 5 minutes unless a real cron job triggers WordPress cron processing.

## Security and Permissions

This plugin does not expose a public metrics endpoint. Metrics are written to disk for collection by `node_exporter`.

You should:

- Use a file path outside the public web root
- Restrict filesystem permissions appropriately
- Ensure the web server user can write the target directory
- Ensure your `node_exporter` process can read the target file

## Development

Repository structure is intentionally minimal:

- [simula-wordfence-node-exporter-integration.php](/Users/ouss/Documents/workspace/simula/simula-wordfence-node-exporter-integration/simula-wordfence-node-exporter-integration.php:1)

The plugin is currently implemented as a single-file WordPress plugin for simple deployment and review.

## License

GPL-2.0-or-later. See the plugin header for licensing details.
