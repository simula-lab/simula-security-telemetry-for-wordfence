=== Simula Security Telemetry for Wordfence ===
Contributors: simulalab
Tags: wordfence, monitoring, security, grafana, metrics
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 4.1.1
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://simulalab.org

Export Wordfence-generated telemetry into a node_exporter textfile collector .prom file and append incidents detected by Wordfence to a local log file.

== Description ==
Simula Security Telemetry for Wordfence exports Wordfence security telemetry in two forms:

* Prometheus metrics for the node_exporter textfile collector that can be scraped by Prometheus
* A local incident log containing blocked Wordfence requests that can be shipped with Grafana Alloy

This plugin is intended for WordPress sites that already use Wordfence and Prometheus-based infrastructure. Instead of exposing a public metrics endpoint from WordPress, the plugin writes local files that node_exporter and log-based tooling can consume.

By default, the plugin runs a fast collector every 15 minutes and a slow collector hourly using WP-Cron. It supports:

* Exporter health and plugin metadata metrics
* Configurable cron interval
* Separate fast and slow collector intervals
* Per-metric-family enable or disable controls
* Aggregate firewall block counts derived from locally stored Wordfence block-log data
* Blocked hit-row counters and recent activity windows
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

The plugin exposes two distinct Wordfence blocking measurements. blocked_hit_rows_* counts retained hit/live-traffic records matching a blocked-request predicate. firewall_blocks_* derives aggregate block counts from locally stored Wordfence block-log data and groups them into bounded categories and reporting windows. The values are not expected to be equal because the two metric families use different sources, units, retention behavior, and categorization.

The legacy blocked_events_* names are deprecated aliases for the hit/live-traffic row model. They are still emitted for compatibility, but they represent retained blocked hit rows rather than the aggregate block-log data exposed through firewall_blocks_*.

Blocked hit rows are currently identified from the Wordfence hits table where:

* action matches blocked:*
* or the HTTP status code is 403 or 503

The plugin includes an admin settings screen under Settings > Security Telemetry, where you can:

* Enable or disable the exporter master switch
* Choose the export cron interval
* Choose the slow collector interval
* Set the .prom output path
* Set a custom metric prefix
* Set a custom site label
* Set an optional HTTPS Grafana dashboard URL for the separate Security Telemetry admin dashboard
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

Simula Security Telemetry for Wordfence is an independent open-source project and is not affiliated with, endorsed by, sponsored by, or otherwise associated with Defiant, Inc. or Wordfence.

Wordfence and related names and marks are the property of their respective owners.

== Installation ==

1. Upload the plugin folder to the /wp-content/plugins/ directory, or install it using your preferred deployment process.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Settings > Security Telemetry.
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
* wordpress_wordfence_next_export_timestamp_seconds
* wordpress_wordfence_next_slow_export_timestamp_seconds
* wordpress_wordfence_enabled
* wordpress_wordfence_error_info
* wordpress_wordfence_blocked_events_total
* wordpress_wordfence_blocked_events_window
* wordpress_wordfence_blocked_hit_rows_total
* wordpress_wordfence_blocked_hit_rows_window
* wordpress_wordfence_firewall_blocks_window
* wordpress_wordfence_firewall_blocks_available
* wordpress_wordfence_firewall_blocks_collection_success
* wordpress_wordfence_firewall_blocks_source_info
* wordpress_wordfence_firewall_blocks_latest_timestamp_seconds
* wordpress_wordfence_blocked_events_by_status_24h
* wordpress_wordfence_failed_login_attempts_window
* wordpress_wordfence_authentication_failures_window
* wordpress_wordfence_rate_limited_events_window
* wordpress_wordfence_brute_force_events_window
* wordpress_wordfence_top_attack_sources_24h
* wordpress_wordfence_locked_out_total
* wordpress_wordfence_two_factor_enabled
* wordpress_wordfence_two_factor_protected_users_total
* wordpress_wordfence_passkey_enabled
* wordpress_wordfence_passkey_protected_users_total
* wordpress_wordfence_login_protected_users_total
* wordpress_wordfence_scan_issues_by_severity
* wordpress_wordfence_scan_findings_total
* wordpress_wordfence_vulnerability_findings_total
* wordpress_wordfence_latest_hit_timestamp_seconds
* wordpress_wordfence_latest_blocked_hit_timestamp_seconds
* wordpress_wordfence_latest_scan_timestamp_seconds
* wordpress_wordfence_scan_age_seconds
* wordpress_wordfence_scan_issue_update_age_seconds
* wordpress_wordfence_installed
* wordpress_wordfence_version_info
* wordpress_wordfence_firewall_enabled
* wordpress_wordfence_firewall_optimized
* wordpress_wordfence_live_traffic_enabled
* wordpress_wordfence_scan_enabled
* wordpress_wordfence_license_type
* wordpress_wordfence_wordpress_version_info
* wordpress_wordfence_php_runtime_posture
* wordpress_wordfence_php_runtime_info
* wordpress_wordfence_php_version_supported
* wordpress_wordfence_php_extension_info
* wordpress_wordfence_php_display_errors_enabled
* wordpress_wordfence_php_expose_php_enabled
* wordpress_wordfence_php_allow_url_fopen_enabled
* wordpress_wordfence_php_session_cookie_secure_enabled
* wordpress_wordfence_php_session_cookie_httponly_enabled
* wordpress_wordfence_php_session_cookie_samesite_info
* wordpress_wordfence_php_memory_limit_bytes
* wordpress_wordfence_php_max_execution_time_seconds
* wordpress_wordfence_php_upload_max_filesize_bytes
* wordpress_wordfence_php_post_max_size_bytes
* wordpress_wordfence_php_opcache_enabled
* wordpress_wordfence_php_opcache_jit_enabled
* wordpress_wordfence_php_opcache_memory_usage_ratio
* wordpress_wordfence_core_update_available
* wordpress_wordfence_plugin_update_available_total
* wordpress_wordfence_plugins_installed_total
* wordpress_wordfence_plugins_active_total
* wordpress_wordfence_plugins_inactive_total
* wordpress_wordfence_plugins_network_active_total
* wordpress_wordfence_plugin_inventory_info
* wordpress_wordfence_theme_update_available_total
* wordpress_wordfence_admin_users_total
* wordpress_wordfence_admin_users_without_2fa_total
* wordpress_wordfence_admin_users_without_passkey_total
* wordpress_wordfence_admin_users_without_login_protection_total
* wordpress_wordfence_admin_user_info
* wordpress_wordfence_users_total
* wordpress_wordfence_users_created_window
* wordpress_wordfence_admin_users_created_window
* wordpress_wordfence_admin_users_modified_window
* wordpress_wordfence_roles_total
* wordpress_wordfence_role_capabilities_total
* wordpress_wordfence_unexpected_admin_capabilities_total
* wordpress_wordfence_users_can_register_enabled
* wordpress_wordfence_default_role_info
* wordpress_wordfence_file_edit_allowed
* wordpress_wordfence_file_mods_allowed
* wordpress_wordfence_debug_enabled
* wordpress_wordfence_debug_display_enabled
* wordpress_wordfence_xmlrpc_enabled
* wordpress_wordfence_rest_api_enabled
* wordpress_wordfence_search_engine_visibility_enabled
* wordpress_wordfence_home_url_info
* wordpress_wordfence_site_url_info
* wordpress_wordfence_plugins_added_window
* wordpress_wordfence_plugins_removed_window
* wordpress_wordfence_plugins_activated_window
* wordpress_wordfence_plugins_deactivated_window
* wordpress_wordfence_mu_plugins_total
* wordpress_wordfence_dropins_total
* wordpress_wordfence_active_theme_info
* wordpress_wordfence_themes_installed_total
* wordpress_wordfence_themes_update_available_total
* wordpress_wordfence_successful_logins_window
* wordpress_wordfence_password_resets_window
* wordpress_wordfence_user_email_changes_window
* wordpress_wordfence_application_passwords_total
* wordpress_wordfence_admin_application_passwords_total
* wordpress_wordfence_sessions_total
* wordpress_wordfence_cron_events_total
* wordpress_wordfence_cron_hooks_total
* wordpress_wordfence_cron_new_hooks_window
* wordpress_wordfence_cron_scheduled_events_total
* wordpress_wordfence_cron_suspicious_hooks_total
* wordpress_wordfence_options_total
* wordpress_wordfence_autoload_options_total
* wordpress_wordfence_autoload_options_bytes
* wordpress_wordfence_options_changed_window
* wordpress_wordfence_new_autoload_options_window
* wordpress_wordfence_sensitive_options_changed_window
* wordpress_wordfence_posts_modified_window
* wordpress_wordfence_pages_modified_window
* wordpress_wordfence_posts_with_script_tags_total
* wordpress_wordfence_posts_with_iframe_tags_total
* wordpress_wordfence_posts_with_suspicious_redirects_total
* wordpress_wordfence_recent_admin_post_edits_window
* wordpress_wordfence_upload_php_files_total
* wordpress_wordfence_upload_executable_files_total
* wordpress_wordfence_recent_upload_php_files_window
* wordpress_wordfence_plugin_files_modified_window
* wordpress_wordfence_theme_files_modified_window
* wordpress_wordfence_wp_content_recently_modified_files_total

Each metric family can be enabled or disabled independently from the settings screen. Per-plugin inventory and per-admin inventory are disabled by default because plugin names, versions, active state, and administrator identities can expose sensitive operational details. Admin inventory uses hashed identity labels by default when enabled.

The wordpress_wordfence_php_runtime_posture control is disabled by default. When enabled, it exports a bounded PHP runtime posture allowlist: PHP version, SAPI, architecture, maintained-version status, selected loaded extensions, safe effective settings, and limited OPcache status. It never exports raw phpinfo output, PHP environment variables, paths, arbitrary INI values, extension configuration, or cached-script inventories. Size metrics are omitted when their source is unlimited or unreadable.

blocked_events_total and blocked_events_window are deprecated ambiguous aliases for hit/live-traffic row counts. blocked_hit_rows_total and blocked_hit_rows_window are the explicit names for that same low-level data model. 

firewall_blocks_window derives aggregate firewall block counts from the locally stored wfBlockedIPLog/wfblockediplog table when the required fields are available. It uses locally available day-bucket, block-type, and block-count data, groups recognized block types into the bounded categories complex, brute_force, and blocklist, and groups all other values under other.

This metric has different source and retention semantics from blocked_hit_rows_* and should not be expected to match retained hit-row counts. If the aggregate source is unavailable, firewall_blocks_available is 0 and category/window series are omitted rather than fabricated.

The two_factor_* and admin_users_without_2fa_total metrics remain strict Wordfence TOTP/2FA metrics. Wordfence 9.0.0 passkeys are exported through passkey_enabled, passkey_protected_users_total, admin_users_without_passkey_total, admin_users_without_login_protection_total, and login_protected_users_total. For Wordfence 9.0.0+ login-protection alerts, prefer admin_users_without_login_protection_total overloading the 2FA-only admin metric.

= What does the Security Telemetry dashboard show? =

The top-level Security Telemetry wp-admin page is a read-only, current-state overview of exporter health and version, WordPress posture, and change signals. Platform information and PHP runtime details are available under Tools > Site Health > Info. The telemetry page and WordPress dashboard widget omit those sections, and their overall status reflects only the displayed telemetry cards. The page does not display Wordfence firewall activity, scan data, incidents, login activity, configuration, or other Wordfence-derived data. It does not run collectors, query Wordfence tables, store time-series data, embed charts, or query Prometheus or Grafana. Configure an optional HTTPS Grafana dashboard URL in Settings to show a handoff link for historical telemetry.

failed_login_attempts_window remains the aggregate failed-login metric and counts Wordfence wfLogins rows where fail is greater than 0, including passkey-related failures in Wordfence 9.0.0. authentication_failures_window adds bounded method labels for password, passkey, passkey_required, two_factor, and other failures. brute_force_events_window username values use password-classified login failures when wfLogins is available, so passkey-required policy blocks are not counted as password brute force.

= What does the incident log export do? =

It appends newly observed blocked Wordfence hits to a local .log or .jsonl path. The default text format preserves the original plain-text log line. The JSON Lines format emits one structured JSON object per blocked event for Loki, ELK, OpenSearch, and similar tooling. The exported incident timestamp is taken from the Wordfence hit row, falling back across known timestamp columns before using export time. The exporter tracks the last processed hit ID, and you can reset the incident cursor from the admin UI or WP-CLI to backfill retained history up to the configured per-run limit.

For Loki, configure your log collector to parse the text prefix or the JSON Lines timestamp field if you want Grafana to display the original Wordfence event time instead of the collector ingestion time.

Incident privacy controls can keep full IPs, truncate IPv4 to /24 and IPv6 to /64, hash IPs with the site salt, drop IP fields, drop query strings from URL and referer fields, drop referers, drop user agents, skip private/internal source IP ranges, and append an optional retention note to emitted events.

= What WP-CLI commands are available? =

If WP-CLI is available, the plugin registers:

* wp simula-security-telemetry export
* wp simula-security-telemetry export --metrics-only
* wp simula-security-telemetry export --metrics-only --scope=fast
* wp simula-security-telemetry export --metrics-only --scope=slow
* wp simula-security-telemetry export --incidents-only
* wp simula-security-telemetry reset-cursor
* wp simula-security-telemetry status

= Does the project provide Grafana and Prometheus examples? =

Yes. The source repository provides repository-only examples under examples/grafana/ and examples/prometheus/. They are intentionally not included in the WordPress.org plugin zip. The dashboard includes exporter health, activity, scan posture, WordPress version, plugin posture, opt-in plugin inventory, opt-in admin inventory, administrator login-protection coverage, and incident logs. Inventory-based alert examples require the matching opt-in inventory metric to be enabled.

= What permissions are required? =

The directory that will contain the .prom file must already exist and be writable by the PHP process running WordPress. If incident export is enabled, the incident log directory must also already exist and be writable by PHP. node_exporter must be able to read the resulting .prom file.

== Screenshots ==

1. Settings screen showing Prometheus metric controls, incident log settings, manual actions, and current exporter state.

= Platform version metadata =

wordpress_wordfence_platform_info controls best-effort platform version collection and is enabled by default. Disable Platform version info in settings to stop collecting and exporting this metadata.

* wordpress_wordfence_web_server_info: web server name and version from the Apache API or PHP server software banner.
* wordpress_wordfence_database_info: MySQL/MariaDB server name and version through WordPress's existing database connection.
* wordpress_wordfence_os_info: distribution ID and version from readable /etc/os-release or /usr/lib/os-release files.
* wordpress_wordfence_kernel_info: kernel name and release visible to PHP.

These info metrics have value 1 and use unknown labels for unavailable values. They refresh on the slow collector schedule (hourly by default) and are reused by fast exports. No shell commands or new network connections are used, and no hostnames or full banners are exported. Container OS metadata describes the container, which may differ from the host; CLI exports may lack web server information. Redis is not collected.

== Changelog ==

= 4.1.1 =

* Added opt-in PHP runtime posture metrics as a PHP2Shell incident post-mortem action.
* Added bounded PHP version, SAPI, architecture, extension, effective-setting, and OPcache metrics without exporting raw phpinfo data, paths, environment variables, arbitrary INI values, or cached-script inventories.
* Added optional PHP posture alert examples for unsupported PHP versions, display_errors, session cookie flags, and missing OPcache posture telemetry.

= 3.2.3 =

* Added Wordfence 9.0.0 passkey metrics while keeping existing two-factor metrics strict to TOTP/2FA secrets.
* Added combined login-protection metrics for administrators and users protected by 2FA, passkeys, either method, or both methods.
* Added authentication_failures_window with bounded password, passkey, passkey_required, two_factor, and other labels.
* Kept the aggregate failed-login metric compatible while preventing passkey-required failures from being counted as password brute force.
* Added Wordfence-version pinning support to Docker smoke scripts for current-version and 8.x compatibility checks.

= 3.1.3 =

* Changed wordpress_wordfence_scan_age_seconds to report the age in seconds since Wordfence's latest successful completed scan.
* Added wordpress_wordfence_scan_issue_update_age_seconds for the previous scan issue update age behavior.
* Updated scan freshness documentation and tests to distinguish successful scan completion age from scan issue update age.

= 3.1.2 =

* Added aggregate firewall block metrics derived from locally stored Wordfence block-log data by category and 24h, 7d, and 30d reporting window.
* Added explicit blocked_hit_rows aliases for the existing hit/live-traffic row metrics while keeping blocked_events metrics for compatibility.
* Added aggregate firewall block-source availability, collection-success, source-info, and latest-bucket diagnostics.
* Updated admin and WP-CLI status output to distinguish hit-row metrics from aggregate Firewall summary metrics.
* Updated Prometheus rules, Grafana dashboard examples, Docker fixtures, and documentation for source-model comparison and migration.
* Marked ambiguous blocked_events metric names as deprecated aliases in documentation.
* Count failed-login windows from Wordfence's wfLogins table when available, with the previous hit/live-traffic text heuristic retained as a fallback.
* Count brute_force_events_window{vector="username"} from failed wfLogins rows when available. The xmlrpc vector remains hit/live-traffic based.
* Document rate-limited windows as retained hit/live-traffic heuristics.

= 3.0.0 =

* Split the plugin implementation into explicit include classes for configuration, settings, admin UI, WP-CLI, metrics rendering, Wordfence schema detection, collection, output, incidents, and utilities.
* Added disposable Docker test harnesses for local fixture, publisher integration, and release zip install validation.
* Added dependency-free PHP unit tests, bootstrap smoke checks, Prometheus output validators, and coverage tooling for environments with Xdebug or PCOV.
* Added WordPress core version metadata through wordpress_wordfence_wordpress_version_info.
* Added installed plugin aggregate metrics for installed, active, inactive, and network-active plugin totals.
* Added opt-in per-plugin inventory metrics with plugin file, name, version, active state, and update availability labels.
* Added opt-in administrator inventory metrics with hashed identity labels by default and per-admin Wordfence two-factor status.
* Added WordPress settings, role/user, plugin/theme drift, account event, cron/option persistence, content injection, and uploads/file IoC metric families.
* Added administrator identity label modes for hashed, ID-only, and counts-only operation.
* Updated WP-CLI and admin status output to show plugin inventory, admin inventory, and admin identity mode settings.
* Updated Grafana dashboard and Prometheus alert examples for WordPress version, plugin posture, plugin inventory, admin inventory, core updates, plugin updates, inactive Wordfence inventory, and administrator 2FA coverage.
* Added release asset validation for README/readme metric coverage, Grafana JSON, and Prometheus alert examples.
* Updated the Docker smoke test to install and activate the current official Wordfence plugin before activating this plugin.
* Updated documentation for the new WordPress version, plugin inventory, and administrator inventory metric families.

= 2.3.3 =

* Added clearer stale-export diagnostics through next scheduled fast and slow export timestamps plus richer admin and WP-CLI freshness status output.
* Fixed scan finding classification so malware counts prefer structured Wordfence issue types instead of broad message text matches.
* Reduced false malware positives for non-malware scan issues such as skipped scan paths and unknown files.

= 2.2.2 =

* Fixed incident log timestamps to prefer the original Wordfence hit timestamp over the export run time.
* Added bounded INFO, WARN, and CRITICAL levels to Wordfence incident log events.
* Added dashboard filtering by instance_name across metrics and incident logs.
* Renamed the plugin, admin page slug, option keys, cron hooks, and WP-CLI command to the Simula Security Telemetry naming.

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

= 4.1.1 =

This major release adds an opt-in PHP runtime posture metric group. Existing installations keep it disabled; enable it only after confirming that readers of your Prometheus metrics may view the documented PHP operational metadata.

= 3.2.3 =

Wordfence 9.0.0 passkeys are reported separately from strict 2FA metrics; use wordpress_wordfence_admin_users_without_login_protection_total for combined admin login-protection alerts.

= 3.1.3 =

wordpress_wordfence_scan_age_seconds now means age since the latest successful completed Wordfence scan. Use wordpress_wordfence_scan_issue_update_age_seconds for the previous scan issue update age behavior.

= 3.1.2 =

Uses Wordfence's wfLogins table for failed-login and username brute-force windows when available, preserving older hit/live-traffic fallback behavior.

= 3.1.0 =

Adds Wordfence Firewall summary aggregate block metrics and explicit blocked_hit_rows aliases. Existing blocked_events metrics remain available but are now documented as deprecated hit/live-traffic row aliases, not Wordfence Firewall summary totals.

= 2.3.3 =

Improves scan finding classification so malware counts follow Wordfence issue types more closely, avoids false positives from descriptive text, and makes stale exports easier to diagnose.

= 2.2.2 =

Fixes incident log event timestamps, adds incident log levels and instance_name dashboard filtering, and renames the plugin storage prefix from `wfne` to `sstfw`. The WP-CLI command is now `wp simula-security-telemetry`.

= 2.1.0 =

Adds incident privacy controls for sensitive IP, URL, referer, user-agent, private/internal IP, and retention-note handling.

= 2.0.0 =

Adds ops-ready dashboard, alert, WP-CLI, JSON Lines incident, freshness, and posture capabilities while preserving the node_exporter textfile collection model.

= 1.0.0 =

Adds configurable metric export coverage and optional blocked-incident log export for Wordfence operators.
