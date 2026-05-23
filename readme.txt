=== Simula Wordfence Grafana Integration ===
Contributors: simulalab
Tags: wordfence, prometheus, monitoring, node-exporter, metrics, security, grafana
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://simulalab.org

Export Prometheus metrics from Wordfence into a node_exporter textfile collector .prom file and append blocked incidents to a local log file.

== Description ==

Simula Wordfence Grafana Integration exports Wordfence security telemetry in two forms:

* Prometheus metrics for the node_exporter textfile collector
* A local incident log containing blocked Wordfence requests

This plugin is intended for WordPress sites that already use Wordfence and Prometheus-based infrastructure. Instead of exposing a public metrics endpoint from WordPress, the plugin writes local files that node_exporter and log-based tooling can consume.

By default, the plugin runs every 5 minutes using WP-Cron and supports:

* Exporter health and plugin metadata metrics
* Configurable cron interval
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
* Manual export and incident cursor reset from the admin UI
* Current exporter and incident state visibility in the admin UI

Blocked events are currently identified from the Wordfence hits table where:

* action matches blocked:*
* or the HTTP status code is 403 or 503

The plugin includes an admin settings screen under Settings > Wordfence Metrics, where you can:

* Enable or disable the exporter master switch
* Choose the export cron interval
* Set the .prom output path
* Set a custom metric prefix
* Set a custom site label
* Enable or disable individual metric families
* Enable or disable incident log export
* Set the incident log path
* Limit the number of incidents appended per run
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

The plugin schedules exports with WP-Cron. The default interval is every 5 minutes, and the admin UI also supports every 15 minutes, every 30 minutes, and hourly. On low-traffic sites, WP-Cron may not run exactly on schedule unless you trigger WordPress cron processing through a system cron job.

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

Each metric family can be enabled or disabled independently from the settings screen.

= What does the incident log export do? =

It appends newly observed blocked Wordfence hits as plain-text log lines to a local .log path. Existing .jsonl paths are also accepted for compatibility, but the output is still plain text rather than JSON. The exporter tracks the last processed hit ID, and you can reset the incident cursor from the admin UI to backfill retained history up to the configured per-run limit.

= What permissions are required? =

The directory that will contain the .prom file must already exist and be writable by the PHP process running WordPress. If incident export is enabled, the incident log directory must also already exist and be writable by PHP. node_exporter must be able to read the resulting .prom file.

== Screenshots ==

1. Settings screen showing Prometheus metric controls, incident log settings, manual actions, and current exporter state.

== Changelog ==

= 1.0.0 =

* Added configurable WP-Cron export intervals.
* Added per-metric-family enable and disable controls.
* Added incident log export for blocked Wordfence requests.
* Added incident cursor tracking and manual cursor reset for backfill.
* Added current exporter and incident state visibility in the admin UI.
* Added expanded Wordfence telemetry including failed logins, rate limiting, brute force activity, lockouts, two-factor coverage, scan findings, and top attack sources.

== Upgrade Notice ==

= 1.0.0 =

Adds configurable metric export coverage and optional blocked-incident log export for Wordfence operators.
