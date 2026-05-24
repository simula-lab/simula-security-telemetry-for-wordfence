# Simula Wordfence Grafana Integration

`Simula Wordfence Grafana Integration` is a WordPress plugin that exports Wordfence security telemetry in two operator-friendly forms:

- Prometheus metrics for the `node_exporter` textfile collector
- A local incident log containing blocked Wordfence requests

It is designed for WordPress sites that already run:

- Wordfence
- `node_exporter` with the textfile collector enabled
- `alloy` with the a log

The plugin writes local files on a schedule, so Prometheus and log-based tooling can ingest Wordfence activity without exposing a public metrics endpoint from WordPress.

## What It Does

The plugin reads data from available Wordfence tables and can write:

- A Prometheus `.prom` file such as `/var/lib/node_exporter/textfile_collector/wordfence.prom`
- An append-only incident log such as `/var/log/wordpress-wordfence-incidents.log`

By default, it runs a fast collector every 15 minutes and a slow collector hourly using WP-Cron. It supports:

- Exporter health and plugin metadata metrics
- Configurable cron interval
- Separate fast and slow collector intervals
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
- Incident privacy controls for IPs, URLs, referers, user agents, and internal traffic
- Manual export from the admin screen
- Incident cursor reset for controlled backfill
- Current exporter and incident state visibility in the admin UI
- Optional JSON Lines incident output
- WP-CLI exports for system cron
- Source freshness and WordPress/Wordfence posture metrics
- A ready-to-import Grafana dashboard and sample Prometheus alert rules

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
- `Slow collector interval`
- `Prometheus file path`
- `Metric prefix`
- `Site label`
- `Exported metrics`
  Each metric family can be enabled or disabled independently.

### Incident log settings

- `Enable incident log export`
- `Incident log path`
- `Incident log format`
- `Max incidents per run`
- `Incident IP privacy`
- `Incident privacy filters`
- `Retention note`

### Manual actions and state

The settings page also provides:

- `Export now`
- `Reset incident cursor for backfill`
- A current state table showing the latest metrics and incident export results
- A sample incident log line

Default values:

- `Cron interval`: `Every fifteen minutes`
- `Slow collector interval`: `Hourly`
- `Prometheus file path`: `/var/lib/node_exporter/textfile_collector/wordfence.prom`
- `Metric prefix`: `wordpress_wordfence`
- `Site label`: current site host name
- `Incident log path`: `/var/log/wordpress-wordfence-incidents.log`
- `Incident log format`: `text`
- `Max incidents per run`: `1000`
- `Incident IP privacy`: `Log full IP address`
- `Incident privacy filters`: disabled
- `Retention note`: empty

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
- `wordpress_wordfence_plugin_info{version="2.2"}`
  Static plugin metadata metric.
- `wordpress_wordfence_last_export_timestamp_seconds`
  Unix timestamp of the last export attempt or successful export.
- `wordpress_wordfence_enabled`
  `1` when the exporter master switch is enabled, `0` otherwise.
- `wordpress_wordfence_error_info{type="write_failed|schema_unsupported|wordfence_missing|incident_failed|unknown"}`
  Failure-state marker with a bounded error type. The detailed error remains in the admin UI and WP-CLI status.

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
- `wordpress_wordfence_latest_hit_timestamp_seconds`
  Latest observed Wordfence hit timestamp.
- `wordpress_wordfence_latest_blocked_hit_timestamp_seconds`
  Latest observed blocked Wordfence hit timestamp.
- `wordpress_wordfence_latest_scan_timestamp_seconds`
  Latest observed scan issue update timestamp when available.
- `wordpress_wordfence_scan_age_seconds`
  Age of the latest observed scan issue update.

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

### Posture metrics

- `wordpress_wordfence_installed`
  Whether Wordfence appears installed or present in the database.
- `wordpress_wordfence_version_info{version="..."}`
  Wordfence version metadata when available.
- `wordpress_wordfence_firewall_enabled`
  Whether the Wordfence firewall appears enabled.
- `wordpress_wordfence_firewall_optimized`
  Whether the Wordfence firewall appears optimized.
- `wordpress_wordfence_live_traffic_enabled`
  Whether Wordfence live traffic appears enabled.
- `wordpress_wordfence_scan_enabled`
  Whether Wordfence scanning appears enabled.
- `wordpress_wordfence_license_type{type="free|premium|unknown"}`
  Wordfence license type metadata.
- `wordpress_wordfence_core_update_available`
  Whether a WordPress core update is available.
- `wordpress_wordfence_plugin_update_available_total`
  Number of plugin updates available.
- `wordpress_wordfence_theme_update_available_total`
  Number of theme updates available.
- `wordpress_wordfence_admin_users_total`
  Number of administrator users.
- `wordpress_wordfence_admin_users_without_2fa_total`
  Number of administrator users without Wordfence two-factor secrets.

## Incident Log Export

When incident export is enabled, each fast or full export appends newly observed blocked Wordfence hits to the configured log file. The default `text` format preserves the v1 log line format. The `jsonl` format emits one JSON object per blocked event for Loki, ELK, OpenSearch, and similar pipelines.

Incident privacy controls can:

- Keep full IPs, truncate IPv4 to `/24` and IPv6 to `/64`, hash IPs with the site salt, or drop IP fields
- Drop query strings from logged URL and referer fields
- Drop referer fields
- Drop user-agent fields
- Skip incidents whose source IP is private, loopback, link-local, or otherwise reserved
- Append an optional retention note to each text or JSON Lines incident event

Operational behavior:

- The exporter tracks the last processed Wordfence hit ID
- The emitted incident timestamp is taken from the Wordfence hit row, falling back across known timestamp columns before using export time
- Activation initializes the cursor at the current maximum hit ID to avoid an unexpected full historical backfill
- `Reset incident cursor for backfill` sets the cursor to `0`, so the next run can replay retained incidents up to the configured row limit
- `Max incidents per run` limits how much history can be appended in a single pass
- Incident events include a bounded log level: `INFO`, `WARN`, or `CRITICAL`

For Loki, configure your log collector to parse the text prefix or the JSON Lines `timestamp` field if you want Grafana to display the original Wordfence event time instead of the collector ingestion time.

Example log line:

```text
[23-May-2026 12:34:56 UTC] CRITICAL Wordfence blocked request: site="example.com" hostname="web-01" blog_id=1 hit_id=123 ip="203.0.113.10" status=403 action="blocked:waf" reason="SQL injection attempt" method="POST" url="/wp-admin/admin-ajax.php" referer="https://example.com/" user_agent="curl/8.0" country="NO"
```

Example JSON Lines event:

```json
{
  "timestamp": "2026-05-23T12:34:56+00:00",
  "site": "example.com",
  "hostname": "web-01",
  "blog_id": 1,
  "hit_id": 123,
  "level": "CRITICAL",
  "ip": "203.0.113.10",
  "status": 403,
  "action": "blocked:waf",
  "reason": "SQL injection attempt",
  "method": "POST",
  "url": "/wp-admin/admin-ajax.php",
  "referer": "https://example.com/",
  "user_agent": "curl/8.0",
  "country": "NO",
  "wf_table": "wp_wfHits"
}
```

## WP-CLI

If WP-CLI is available, the plugin registers:

```bash
wp simula-wordfence-metrics export
wp simula-wordfence-metrics export --metrics-only
wp simula-wordfence-metrics export --metrics-only --scope=fast
wp simula-wordfence-metrics export --metrics-only --scope=slow
wp simula-wordfence-metrics export --incidents-only
wp simula-wordfence-metrics reset-cursor
wp simula-wordfence-metrics status
```

For production scheduling, prefer system cron invoking WP-CLI over relying only on traffic-triggered WP-Cron:

```cron
*/15 * * * * cd /path/to/wordpress && wp simula-wordfence-metrics export --quiet
0 * * * * cd /path/to/wordpress && wp simula-wordfence-metrics export --metrics-only --scope=slow --quiet
```

## Grafana and Prometheus Assets

- Import `examples/grafana/grafana-dashboard-wordfence-security-overview.json` into Grafana and select your Prometheus datasource.
- Load `examples/prometheus/wordfence-alerts.yml` into Prometheus or your rule management workflow.
- Adjust alert thresholds to match site traffic. The defaults are intentionally conservative starting points for blocked request spikes, failed login bursts, stale exports, malware findings, vulnerabilities, and administrator 2FA coverage.

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
- Configure incident privacy controls when IP addresses, URLs, referers, or user agents are sensitive in your environment

## Development

Repository structure is intentionally minimal:

- [simula-wordfence-grafana-integration.php](/Users/ouss/Documents/workspace/simula/wordpress/plugins_repos/simula-wordfence-grafana-integration/simula-wordfence-grafana-integration.php:1)

The plugin is currently implemented as a single-file WordPress plugin for simple deployment and review.

## License

GPL-2.0-or-later. See the plugin header for licensing details.
