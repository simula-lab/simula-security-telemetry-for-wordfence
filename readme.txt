=== Simula Security Metrics Exporter for Wordfence ===
Contributors: simulalab
Tags: wordfence, monitoring, security, grafana, metrics
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.2.2
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://simulalab.org

Export Prometheus metrics from Wordfence into a node_exporter textfile collector .prom file and append incidents detected by wordfence to a local log file.

== Description ==
Simula Security Metrics Exporter for Wordfence exports Wordfence security telemetry in two forms:

* Prometheus metrics for the node_exporter textfile collector that can be scrapped by Prometheus
* A local incident log containing blocked Wordfence requests that can be shipped with Grafana-Alloy

This plugin is intended for WordPress sites that already use Wordfence and Prometheus-based infrastructure. Instead of exposing a public metrics endpoint from WordPress, the plugin writes local files that node_exporter and log-based tooling can consume.

By default, the plugin runs a fast collector every 15 minutes and a slow collector hourly using WP-Cron. It supports:

* Exporter health and plugin metadata metrics
* Configurable cron interval
* Separate fast and slow collector intervals
* Per-metric-family enable or disable controls
* Blocked event counters and recent activity windows
* Blocked event counts by HTTP status code over the last 24 hours
* Failed login, rate-limited, and brute-force activity windows
* Current lockout counts for IPs and users
* Wordfence two-factor status and protected user counts
* Scan issue counts by severity
* Malware, file change, and vulnerable component findings
* Top blocked attack sources by country and normalized IP range
* Incident log export for newly observed blocked requests
* Incident privacy controls for IPs, URLs, referers, user agents, and internal traffic
* Manual export and incident cursor reset from the admin UI
* Current exporter and incident state visibility in the admin UI
* Optional JSON Lines incident output
* WP-CLI exports for system cron
* Source freshness and WordPress/Wordfence posture metrics
* A ready-to-import Grafana dashboard and sample Prometheus alert rules

Blocked events are currently identified from the Wordfence hits table where:

* action matches blocked:*
* or the HTTP status code is 403 or 503

The plugin includes an admin settings screen under Settings > Wordfence Metrics, where you can:

* Enable or disable the exporter master switch
* Choose the export cron interval
* Choose the slow collector interval
* Set the .prom output path
* Set a custom metric prefix
* Set a custom site label
* Enable or disable individual metric families
* Enable or disable incident log export
* Set the incident log path
* Choose text or JSON Lines incident output
* Limit the number of incidents appended per run
* Configure incident IP privacy and field-dropping filters
* Add an optional retention note to emitted incident events
* Trigger a manual export
* Reset the incident cursor for backfill
* Review current exporter and incident state

== Installation ==

1. Upload the plugin folder to the /wp-content/plugins/ directory, or install it using your preferred deployment process.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings > Wordfence Metrics.
4. Set the Prometheus output file path. The default is /var/lib/node_exporter/textfile_collector/wordfence.prom.
5. Ensure the target directory already exists and is writable by the PHP process.
6. If incident export is enabled, set the incident log path. The default is /var/log/wordpress-wordfence-incidents.log.
7. Ensure the incident log directory already exists and is writable by the PHP process.
8. Ensure node_exporter is configured with the textfile collector and can read the generated .prom file.

== Frequently Asked Questions ==

= Does this plugin expose a public metrics endpoint? =

No. It writes metrics to a local file for node_exporter to collect, and it can append blocked incidents to a local log file.

= Does this plugin require Wordfence? =

Yes. The plugin reads Wordfence data from the WordPress database. If required Wordfence tables or columns are unavailable, the exporter writes failure-state metrics instead of silently doing nothing.

= How often are metrics exported? =

The plugin schedules fast exports with WP-Cron. The default fast interval is every 15 minutes, and the admin UI also supports every 5 minutes, every 30 minutes, and hourly. Slow posture and scan metrics refresh hourly by default and can be set to hourly, twice daily, or daily. On low-traffic sites, WP-Cron may not run exactly on schedule unless you trigger WordPress cron processing through a system cron job or WP-CLI.

= What metrics does the plugin export? =

With the default metric prefix of wordpress_wordfence, the plugin can export:

* wordpress_wordfence_export_success
* wordpress_wordfence_plugin_info
* wordpress_wordfence_last_export_timestamp_seconds
* wordpress_wordfence_enabled
* wordpress_wordfence_error_info
* wordpress_wordfence_blocked_events_total
* wordpress_wordfence_blocked_events_window
* wordpress_wordfence_blocked_events_by_status_24h
* wordpress_wordfence_failed_login_attempts_window
* wordpress_wordfence_rate_limited_events_window
* wordpress_wordfence_brute_force_events_window
* wordpress_wordfence_top_attack_sources_24h
* wordpress_wordfence_locked_out_total
* wordpress_wordfence_two_factor_enabled
* wordpress_wordfence_two_factor_protected_users_total
* wordpress_wordfence_scan_issues_by_severity
* wordpress_wordfence_scan_findings_total
* wordpress_wordfence_vulnerability_findings_total
* wordpress_wordfence_latest_hit_timestamp_seconds
* wordpress_wordfence_latest_blocked_hit_timestamp_seconds
* wordpress_wordfence_latest_scan_timestamp_seconds
* wordpress_wordfence_scan_age_seconds
* wordpress_wordfence_installed
* wordpress_wordfence_version_info
* wordpress_wordfence_firewall_enabled
* wordpress_wordfence_firewall_optimized
* wordpress_wordfence_live_traffic_enabled
* wordpress_wordfence_scan_enabled
* wordpress_wordfence_license_type
* wordpress_wordfence_core_update_available
* wordpress_wordfence_plugin_update_available_total
* wordpress_wordfence_theme_update_available_total
* wordpress_wordfence_admin_users_total
* wordpress_wordfence_admin_users_without_2fa_total

Each metric family can be enabled or disabled independently from the settings screen.

= What does the incident log export do? =

It appends newly observed blocked Wordfence hits to a local .log or .jsonl path. The default text format preserves the original plain-text log line. The JSON Lines format emits one structured JSON object per blocked event for Loki, ELK, OpenSearch, and similar tooling. The exported incident timestamp is taken from the Wordfence hit row, falling back across known timestamp columns before using export time. The exporter tracks the last processed hit ID, and you can reset the incident cursor from the admin UI or WP-CLI to backfill retained history up to the configured per-run limit.

For Loki, configure your log collector to parse the text prefix or the JSON Lines timestamp field if you want Grafana to display the original Wordfence event time instead of the collector ingestion time.

Incident privacy controls can keep full IPs, truncate IPv4 to /24 and IPv6 to /64, hash IPs with the site salt, drop IP fields, drop query strings from URL and referer fields, drop referers, drop user agents, skip private/internal source IP ranges, and append an optional retention note to emitted events.

= What WP-CLI commands are available? =

If WP-CLI is available, the plugin registers:

* wp simula-wordfence-metrics export
* wp simula-wordfence-metrics export --metrics-only
* wp simula-wordfence-metrics export --metrics-only --scope=fast
* wp simula-wordfence-metrics export --metrics-only --scope=slow
* wp simula-wordfence-metrics export --incidents-only
* wp simula-wordfence-metrics reset-cursor
* wp simula-wordfence-metrics status

= Does the plugin include Grafana and Prometheus assets? =

Yes. Import examples/grafana/grafana-dashboard-wordfence-security-overview.json into Grafana and load examples/prometheus/wordfence-alerts.yml into Prometheus or your rule management workflow.

= What permissions are required? =

The directory that will contain the .prom file must already exist and be writable by the PHP process running WordPress. If incident export is enabled, the incident log directory must also already exist and be writable by PHP. node_exporter must be able to read the resulting .prom file.

== Screenshots ==

1. Settings screen showing Prometheus metric controls, incident log settings, manual actions, and current exporter state.

== Changelog ==

= 2.2.2 =

* Fixed incident log timestamps to prefer the original Wordfence hit timestamp over the export run time.
* Added bounded INFO, WARN, and CRITICAL levels to Wordfence incident log events.
* Added dashboard filtering by instance_name across metrics and incident logs.

= 2.1.0 =

* Added incident privacy controls for IPs, URL and referer query strings, referers, user agents, private/internal IP ranges, and retention notes.

= 2.0.0 =

* Changed the default fast export interval to 15 minutes.
* Added a slow collector for scan, two-factor, WordPress posture, and Wordfence posture metrics.
* Added WP-CLI export, status, and incident cursor commands.
* Added optional JSON Lines incident output.
* Added source freshness, Wordfence posture, and WordPress posture metrics.
* Replaced unbounded error message labels with bounded error type labels.
* Added a Grafana dashboard and sample Prometheus alert rules.

= 1.0.0 =

* Added configurable WP-Cron export intervals.
* Added per-metric-family enable and disable controls.
* Added incident log export for blocked Wordfence requests.
* Added incident cursor tracking and manual cursor reset for backfill.
* Added current exporter and incident state visibility in the admin UI.
* Added expanded Wordfence telemetry including failed logins, rate limiting, brute force activity, lockouts, two-factor coverage, scan findings, and top attack sources.

== Upgrade Notice ==

= 2.2.2 =

Fixes incident log event timestamps and adds incident log levels plus instance_name dashboard filtering.
Changed the prefix of this plugin DB entries from `wfne` to `swfgi`

= 2.1.0 =

Adds incident privacy controls for sensitive IP, URL, referer, user-agent, private/internal IP, and retention-note handling.

= 2.0.0 =

Adds ops-ready dashboard, alert, WP-CLI, JSON Lines incident, freshness, and posture capabilities while preserving the node_exporter textfile collection model.

= 1.0.0 =

Adds configurable metric export coverage and optional blocked-incident log export for Wordfence operators.
