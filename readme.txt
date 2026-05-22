=== Simula Wordfence Node Exporter Integration ===
Contributors: simulalab
Tags: wordfence, prometheus, monitoring, node-exporter, metrics, security
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://simulalab.org

Export Prometheus metrics from Wordfence into a node_exporter textfile collector .prom file.

== Description ==

Simula Wordfence Node Exporter Integration exports Wordfence activity as Prometheus metrics for the node_exporter textfile collector.

This plugin is intended for WordPress sites that already use Wordfence and Prometheus-based infrastructure. Instead of exposing a public metrics endpoint from WordPress, the plugin writes a local .prom file that node_exporter can read and publish to Prometheus.

By default, the plugin runs every 5 minutes using WP-Cron and writes metrics for:

* Export health
* Plugin version info
* Last export timestamp
* Whether scheduled exporting is enabled
* Total observed blocked events
* Blocked event counts over recent windows
* Blocked event counts by HTTP status code over the last 24 hours
* Failed login attempt counts over recent windows
* Locked out IP or user counts
* Two-factor authentication status and protected user counts
* Scan issue counts by severity
* Malware or file change detection counts from recent scans
* Rate-limited or throttled request counts
* Top attack sources by country or IP range
* Brute-force attack counts against usernames or XML-RPC
* Outdated core, plugin, or theme vulnerability findings reported by Wordfence scans

Blocked events are currently identified from the Wordfence hits table where:

* action matches blocked:*
* or the HTTP status code is 403 or 503

The plugin includes an admin settings screen under Settings > Wordfence Metrics, where you can:

* Enable or disable scheduled exports
* Set the .prom output path
* Set a custom metric prefix
* Set a custom site label
* Trigger a manual export

== Installation ==

1. Upload the plugin folder to the /wp-content/plugins/ directory, or install it using your preferred deployment process.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings > Wordfence Metrics.
4. Set the Prometheus output file path. The default is /var/lib/node_exporter/textfile_collector/wordfence.prom.
5. Ensure the target directory already exists and is writable by the PHP process.
6. Ensure node_exporter is configured with the textfile collector and can read the generated .prom file.

== Frequently Asked Questions ==

= Does this plugin expose a public metrics endpoint? =

No. It writes metrics to a local file for node_exporter to collect.

= Does this plugin require Wordfence? =

Yes. The plugin reads Wordfence data from the WordPress database. If the Wordfence hits table does not exist, the exporter writes failure-state metrics instead of Wordfence event metrics.

= How often are metrics exported? =

The plugin schedules exports every 5 minutes using WP-Cron by default. On low-traffic sites, WP-Cron may not run exactly on schedule unless you trigger WordPress cron processing through a system cron job.

= What metrics does the plugin export? =

With the default metric prefix of wordpress_wordfence, the plugin exports:

* wordpress_wordfence_export_success
* wordpress_wordfence_plugin_info
* wordpress_wordfence_last_export_timestamp_seconds
* wordpress_wordfence_enabled
* wordpress_wordfence_blocked_events_total
* wordpress_wordfence_blocked_events_window
* wordpress_wordfence_blocked_events_by_status_24h

On export failure, it also exports:

* wordpress_wordfence_error_info

= What permissions are required? =

The directory that will contain the .prom file must already exist and be writable by the PHP process running WordPress. node_exporter must also be able to read the resulting file.

== Screenshots ==

1. Settings screen showing the exporter configuration and current exporter state.

== Changelog ==

= 1.0.0 =

* Initial stable release.
* Added scheduled export of Wordfence metrics to node_exporter textfile collector format.
* Added admin settings page for file path, metric prefix, site label, and manual export.
* Added exporter health, blocked event counters, recent windows, and status-code metrics.

== Upgrade Notice ==

= 1.0.0 =

Initial stable release of the plugin.
