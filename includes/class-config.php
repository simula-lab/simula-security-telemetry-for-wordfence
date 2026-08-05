<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Config {
    public const OPTION         = 'sstfw_metrics_options';
    public const STATE          = 'sstfw_metrics_state';
    public const CRON_HOOK      = 'sstfw_metrics_export_event';
    public const SLOW_CRON_HOOK = 'sstfw_metrics_slow_export_event';
    public const SLUG           = 'simula-security-telemetry-for-wordfence';
    public const CAPABILITY     = 'manage_options';
    public const VERSION        = '3.1.0';
    public const TEXT_DOMAIN    = 'simula-security-telemetry-for-wordfence';
    public const CLI_COMMAND    = 'simula-security-telemetry';
    // public const LEGACY_OPTION         = 'wfne_metrics_options';
    // public const LEGACY_STATE          = 'wfne_metrics_state';
    // public const LEGACY_CRON_HOOK      = 'wfne_metrics_export_event';
    // public const LEGACY_SLOW_CRON_HOOK = 'wfne_metrics_slow_export_event';
    public const WINDOWS        = ['5m', '1h', '24h', '7d'];

    /** Returns the default plugin option values. */
    public static function defaults() {
        return [
            'enabled'              => 1,
            'cron_interval'        => 'sstfw_fifteen_minutes',
            'slow_cron_interval'   => 'hourly',
            'prom_file'            => '/var/lib/node_exporter/textfile_collector/wordfence.prom',
            'metric_prefix'        => 'wordpress_wordfence',
            'site_label'           => (string) wp_parse_url(home_url('/'), PHP_URL_HOST),
            'incident_log_enabled' => 1,
            'incident_log_file'    => '/var/log/wordpress-wordfence-incidents.log',
            'incident_log_format'  => 'text',
            'incident_max_rows'    => 1000,
            'privacy_ip_mode'      => 'full',
            'privacy_drop_url_query' => 0,
            'privacy_drop_referer' => 0,
            'privacy_drop_user_agent' => 0,
            'privacy_exclude_private_ips' => 0,
            'privacy_retention_note' => '',
            'admin_identity_mode' => 'hashed',
            'enabled_metrics'      => self::default_enabled_metrics(),
        ];
    }

    /** Returns the metric families that can be individually exported. */
    public static function metric_definitions() {
        return [
            'export_success' => [
                'label'       => __('Export success', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Reports whether the latest export completed successfully.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugin_info' => [
                'label'       => __('Plugin info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Exports static plugin metadata including the installed version.', 'simula-security-telemetry-for-wordfence'),
            ],
            'enabled' => [
                'label'       => __('Exporter enabled state', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Reports whether the exporter master switch is enabled. When off, both metrics and incident exports are disabled.', 'simula-security-telemetry-for-wordfence'),
            ],
            'last_export_timestamp_seconds' => [
                'label'       => __('Last export timestamp', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Exports the Unix timestamp of the most recent export attempt.', 'simula-security-telemetry-for-wordfence'),
            ],
            'next_export_timestamp_seconds' => [
                'label'       => __('Next fast export timestamp', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Exports the Unix timestamp of the next scheduled fast exporter run when WP-Cron has one queued.', 'simula-security-telemetry-for-wordfence'),
            ],
            'next_slow_export_timestamp_seconds' => [
                'label'       => __('Next slow export timestamp', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Exports the Unix timestamp of the next scheduled slow collector run when WP-Cron has one queued.', 'simula-security-telemetry-for-wordfence'),
            ],
            'error_info' => [
                'label'       => __('Error info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Exports a bounded error type for the latest export failure.', 'simula-security-telemetry-for-wordfence'),
            ],
            'blocked_events_total' => [
                'label'       => __('Blocked events total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Deprecated ambiguous alias: cumulative counter of newly observed blocked Wordfence hit/live-traffic rows, not the Wordfence Firewall Summary.', 'simula-security-telemetry-for-wordfence'),
            ],
            'blocked_events_window' => [
                'label'       => __('Blocked events by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Deprecated ambiguous alias: blocked Wordfence hit/live-traffic rows over 5m, 1h, 24h, and 7d windows.', 'simula-security-telemetry-for-wordfence'),
            ],
            'blocked_hit_rows_total' => [
                'label'       => __('Blocked hit rows total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Cumulative counter of newly observed Wordfence hit/live-traffic rows matching the blocked-hit predicate.', 'simula-security-telemetry-for-wordfence'),
            ],
            'blocked_hit_rows_window' => [
                'label'       => __('Blocked hit rows by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Retained Wordfence hit/live-traffic rows matching the blocked-hit predicate over 5m, 1h, 24h, and 7d windows.', 'simula-security-telemetry-for-wordfence'),
            ],
            'firewall_blocks_window' => [
                'label'       => __('Wordfence Firewall Summary metrics', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Wordfence aggregate Firewall Summary block counts by bounded category over 24h, 7d, and 30d windows.', 'simula-security-telemetry-for-wordfence'),
            ],
            'firewall_blocks_available' => [
                'label'       => __('Firewall Summary source available', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Reports whether the supported Wordfence aggregate block source table and columns are available.', 'simula-security-telemetry-for-wordfence'),
            ],
            'firewall_blocks_collection_success' => [
                'label'       => __('Firewall Summary collection success', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Reports whether the latest Firewall Summary aggregate collection completed successfully.', 'simula-security-telemetry-for-wordfence'),
            ],
            'firewall_blocks_source_info' => [
                'label'       => __('Firewall Summary source info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Bounded metadata for the detected Firewall Summary aggregate source schema.', 'simula-security-telemetry-for-wordfence'),
            ],
            'firewall_blocks_latest_timestamp_seconds' => [
                'label'       => __('Firewall Summary latest timestamp', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Unix timestamp for the latest Wordfence aggregate block day bucket when available.', 'simula-security-telemetry-for-wordfence'),
            ],
            'blocked_events_by_status_24h' => [
                'label'       => __('Blocked events by status (24h)', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Blocked Wordfence hits in the last 24 hours grouped by HTTP status code.', 'simula-security-telemetry-for-wordfence'),
            ],
            'failed_login_attempts_window' => [
                'label'       => __('Failed login attempts by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Failed login activity over 5m, 1h, 24h, and 7d windows.', 'simula-security-telemetry-for-wordfence'),
            ],
            'locked_out_total' => [
                'label'       => __('Current lockouts', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Current Wordfence lockout totals grouped by IP and user.', 'simula-security-telemetry-for-wordfence'),
            ],
            'two_factor_enabled' => [
                'label'       => __('Two-factor enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether Wordfence two-factor authentication appears configured.', 'simula-security-telemetry-for-wordfence'),
            ],
            'two_factor_protected_users_total' => [
                'label'       => __('Two-factor protected users', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Count of users with Wordfence two-factor secrets configured.', 'simula-security-telemetry-for-wordfence'),
            ],
            'scan_issues_by_severity' => [
                'label'       => __('Scan issues by severity', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Current Wordfence scan issues grouped by severity.', 'simula-security-telemetry-for-wordfence'),
            ],
            'scan_findings_total' => [
                'label'       => __('Scan findings total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Current Wordfence scan findings for malware issue types and file changes.', 'simula-security-telemetry-for-wordfence'),
            ],
            'rate_limited_events_window' => [
                'label'       => __('Rate-limited events by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Rate-limited or throttled requests over 5m, 1h, 24h, and 7d windows.', 'simula-security-telemetry-for-wordfence'),
            ],
            'top_attack_sources_24h' => [
                'label'       => __('Top attack sources (24h)', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Top blocked attack sources observed during the last 24 hours.', 'simula-security-telemetry-for-wordfence'),
            ],
            'brute_force_events_window' => [
                'label'       => __('Brute-force events by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Brute-force activity over 5m, 1h, 24h, and 7d windows.', 'simula-security-telemetry-for-wordfence'),
            ],
            'vulnerability_findings_total' => [
                'label'       => __('Vulnerability findings total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Current vulnerable or outdated core, plugin, and theme findings.', 'simula-security-telemetry-for-wordfence'),
            ],
            'latest_hit_timestamp_seconds' => [
                'label'       => __('Latest hit timestamp', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Unix timestamp of the latest observed Wordfence hit.', 'simula-security-telemetry-for-wordfence'),
            ],
            'latest_blocked_hit_timestamp_seconds' => [
                'label'       => __('Latest blocked hit timestamp', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Unix timestamp of the latest observed blocked Wordfence hit.', 'simula-security-telemetry-for-wordfence'),
            ],
            'latest_scan_timestamp_seconds' => [
                'label'       => __('Latest scan timestamp', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Unix timestamp of the latest observed Wordfence scan issue update when available.', 'simula-security-telemetry-for-wordfence'),
            ],
            'scan_age_seconds' => [
                'label'       => __('Scan age', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Age in seconds of the latest observed Wordfence scan issue update.', 'simula-security-telemetry-for-wordfence'),
            ],
            'installed' => [
                'label'       => __('Wordfence installed', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether Wordfence appears to be installed or present in the database.', 'simula-security-telemetry-for-wordfence'),
            ],
            'version_info' => [
                'label'       => __('Wordfence version info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Static Wordfence version metadata when available.', 'simula-security-telemetry-for-wordfence'),
            ],
            'firewall_enabled' => [
                'label'       => __('Wordfence firewall enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether the Wordfence firewall appears enabled.', 'simula-security-telemetry-for-wordfence'),
            ],
            'firewall_optimized' => [
                'label'       => __('Wordfence firewall optimized', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether the Wordfence firewall appears optimized.', 'simula-security-telemetry-for-wordfence'),
            ],
            'live_traffic_enabled' => [
                'label'       => __('Wordfence live traffic enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether Wordfence live traffic appears enabled.', 'simula-security-telemetry-for-wordfence'),
            ],
            'scan_enabled' => [
                'label'       => __('Wordfence scan enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether Wordfence scanning appears enabled.', 'simula-security-telemetry-for-wordfence'),
            ],
            'license_type' => [
                'label'       => __('Wordfence license type', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Wordfence license type metadata as free, premium, or unknown.', 'simula-security-telemetry-for-wordfence'),
            ],
            'wordpress_version_info' => [
                'label'       => __('WordPress version info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Static WordPress core version metadata.', 'simula-security-telemetry-for-wordfence'),
            ],
            'core_update_available' => [
                'label'       => __('WordPress core update available', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether a WordPress core update is available.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugin_update_available_total' => [
                'label'       => __('Plugin updates available', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of plugin updates available.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugins_installed_total' => [
                'label'       => __('Plugins installed total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of installed WordPress plugins.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugins_active_total' => [
                'label'       => __('Plugins active total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of site-active WordPress plugins.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugins_inactive_total' => [
                'label'       => __('Plugins inactive total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of inactive WordPress plugins.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugins_network_active_total' => [
                'label'       => __('Plugins network-active total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of network-active WordPress plugins.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugin_inventory_info' => [
                'label'       => __('Plugin inventory info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Opt-in installed plugin inventory with plugin file, name, version, active state, and update availability labels. This can expose sensitive operational details.', 'simula-security-telemetry-for-wordfence'),
            ],
            'theme_update_available_total' => [
                'label'       => __('Theme updates available', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of theme updates available.', 'simula-security-telemetry-for-wordfence'),
            ],
            'admin_users_total' => [
                'label'       => __('Admin users total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of WordPress administrator users.', 'simula-security-telemetry-for-wordfence'),
            ],
            'admin_users_without_2fa_total' => [
                'label'       => __('Admin users without 2FA', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of administrator users without a Wordfence two-factor secret.', 'simula-security-telemetry-for-wordfence'),
            ],
            'admin_user_info' => [
                'label'       => __('Admin user inventory info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Opt-in administrator inventory with privacy-preserving identity labels and per-admin Wordfence two-factor status. This can expose sensitive operational details.', 'simula-security-telemetry-for-wordfence'),
            ],
            'users_total' => [
                'label'       => __('Users total by role', 'simula-security-telemetry-for-wordfence'),
                'description' => __('WordPress users grouped by a bounded role label.', 'simula-security-telemetry-for-wordfence'),
            ],
            'users_created_window' => [
                'label'       => __('Users created by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Recently created WordPress users grouped by bounded role and window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'admin_users_created_window' => [
                'label'       => __('Admin users created by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Recently created administrator users grouped by window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'admin_users_modified_window' => [
                'label'       => __('Admin users modified by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Administrator profile modifications observed by plugin hooks grouped by window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'roles_total' => [
                'label'       => __('Roles total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of registered WordPress roles.', 'simula-security-telemetry-for-wordfence'),
            ],
            'role_capabilities_total' => [
                'label'       => __('Role capabilities total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Role capability counts grouped by bounded role label.', 'simula-security-telemetry-for-wordfence'),
            ],
            'unexpected_admin_capabilities_total' => [
                'label'       => __('Unexpected admin capabilities total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Count of administrator-level capabilities assigned to non-administrator roles.', 'simula-security-telemetry-for-wordfence'),
            ],
            'users_can_register_enabled' => [
                'label'       => __('Users can register enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether public user registration is enabled.', 'simula-security-telemetry-for-wordfence'),
            ],
            'default_role_info' => [
                'label'       => __('Default user role info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Default new-user role as bounded metadata.', 'simula-security-telemetry-for-wordfence'),
            ],
            'file_edit_allowed' => [
                'label'       => __('File edit allowed', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether WordPress file editing appears allowed.', 'simula-security-telemetry-for-wordfence'),
            ],
            'file_mods_allowed' => [
                'label'       => __('File modifications allowed', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether WordPress file modifications appear allowed.', 'simula-security-telemetry-for-wordfence'),
            ],
            'debug_enabled' => [
                'label'       => __('Debug enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether WP_DEBUG is enabled.', 'simula-security-telemetry-for-wordfence'),
            ],
            'debug_display_enabled' => [
                'label'       => __('Debug display enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether WP_DEBUG_DISPLAY is enabled.', 'simula-security-telemetry-for-wordfence'),
            ],
            'xmlrpc_enabled' => [
                'label'       => __('XML-RPC enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether XML-RPC appears enabled.', 'simula-security-telemetry-for-wordfence'),
            ],
            'rest_api_enabled' => [
                'label'       => __('REST API enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether the WordPress REST API appears enabled.', 'simula-security-telemetry-for-wordfence'),
            ],
            'search_engine_visibility_enabled' => [
                'label'       => __('Search engine discouragement enabled', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether search engine visibility is discouraged.', 'simula-security-telemetry-for-wordfence'),
            ],
            'home_url_info' => [
                'label'       => __('Home URL info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Hashed WordPress home URL metadata.', 'simula-security-telemetry-for-wordfence'),
            ],
            'site_url_info' => [
                'label'       => __('Site URL info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Hashed WordPress site URL metadata.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugins_added_window' => [
                'label'       => __('Plugins added by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Plugin additions detected by slow-snapshot comparison.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugins_removed_window' => [
                'label'       => __('Plugins removed by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Plugin removals detected by slow-snapshot comparison.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugins_activated_window' => [
                'label'       => __('Plugins activated by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Plugin activations detected by slow-snapshot comparison.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugins_deactivated_window' => [
                'label'       => __('Plugins deactivated by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Plugin deactivations detected by slow-snapshot comparison.', 'simula-security-telemetry-for-wordfence'),
            ],
            'mu_plugins_total' => [
                'label'       => __('MU plugins total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of must-use plugins.', 'simula-security-telemetry-for-wordfence'),
            ],
            'dropins_total' => [
                'label'       => __('Drop-ins total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of WordPress drop-in files.', 'simula-security-telemetry-for-wordfence'),
            ],
            'active_theme_info' => [
                'label'       => __('Active theme info', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Active theme name and version metadata.', 'simula-security-telemetry-for-wordfence'),
            ],
            'themes_installed_total' => [
                'label'       => __('Themes installed total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of installed WordPress themes.', 'simula-security-telemetry-for-wordfence'),
            ],
            'themes_update_available_total' => [
                'label'       => __('Theme updates available total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of available WordPress theme updates.', 'simula-security-telemetry-for-wordfence'),
            ],
            'successful_logins_window' => [
                'label'       => __('Successful logins by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Successful logins observed by plugin hooks grouped by bounded role and window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'password_resets_window' => [
                'label'       => __('Password resets by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Password resets observed by plugin hooks grouped by bounded role and window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'user_email_changes_window' => [
                'label'       => __('User email changes by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Email changes observed by plugin hooks grouped by bounded role and window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'application_passwords_total' => [
                'label'       => __('Application passwords total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Total stored WordPress application passwords.', 'simula-security-telemetry-for-wordfence'),
            ],
            'admin_application_passwords_total' => [
                'label'       => __('Admin application passwords total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Stored WordPress application passwords owned by administrator users.', 'simula-security-telemetry-for-wordfence'),
            ],
            'sessions_total' => [
                'label'       => __('Sessions total by role', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Stored WordPress session-token counts grouped by bounded role.', 'simula-security-telemetry-for-wordfence'),
            ],
            'cron_events_total' => [
                'label'       => __('Cron events total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Total scheduled WordPress cron events.', 'simula-security-telemetry-for-wordfence'),
            ],
            'cron_hooks_total' => [
                'label'       => __('Cron hooks total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of distinct scheduled WordPress cron hooks.', 'simula-security-telemetry-for-wordfence'),
            ],
            'cron_new_hooks_window' => [
                'label'       => __('New cron hooks by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('New cron hook names detected by slow-snapshot comparison.', 'simula-security-telemetry-for-wordfence'),
            ],
            'cron_scheduled_events_total' => [
                'label'       => __('Cron scheduled events by recurrence', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Scheduled cron events grouped by bounded recurrence label.', 'simula-security-telemetry-for-wordfence'),
            ],
            'cron_suspicious_hooks_total' => [
                'label'       => __('Suspicious cron hooks total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Count of cron hooks matching suspicious persistence-oriented names.', 'simula-security-telemetry-for-wordfence'),
            ],
            'options_total' => [
                'label'       => __('Options total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Total rows in the WordPress options table.', 'simula-security-telemetry-for-wordfence'),
            ],
            'autoload_options_total' => [
                'label'       => __('Autoload options total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Total autoloaded WordPress options.', 'simula-security-telemetry-for-wordfence'),
            ],
            'autoload_options_bytes' => [
                'label'       => __('Autoload options bytes', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Approximate byte size of autoloaded WordPress option values.', 'simula-security-telemetry-for-wordfence'),
            ],
            'options_changed_window' => [
                'label'       => __('Options changed by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Sensitive option changes detected by slow-snapshot comparison.', 'simula-security-telemetry-for-wordfence'),
            ],
            'new_autoload_options_window' => [
                'label'       => __('New autoload options by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('New autoloaded option rows detected by slow-snapshot comparison.', 'simula-security-telemetry-for-wordfence'),
            ],
            'sensitive_options_changed_window' => [
                'label'       => __('Sensitive options changed by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Sensitive option changes grouped by bounded option group and window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'posts_modified_window' => [
                'label'       => __('Posts modified by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Recently modified content grouped by bounded post type and window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'pages_modified_window' => [
                'label'       => __('Pages modified by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Recently modified WordPress pages grouped by window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'posts_with_script_tags_total' => [
                'label'       => __('Posts with script tags total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Published content containing script tags grouped by bounded post type.', 'simula-security-telemetry-for-wordfence'),
            ],
            'posts_with_iframe_tags_total' => [
                'label'       => __('Posts with iframe tags total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Published content containing iframe tags grouped by bounded post type.', 'simula-security-telemetry-for-wordfence'),
            ],
            'posts_with_suspicious_redirects_total' => [
                'label'       => __('Posts with suspicious redirects total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Published content containing simple suspicious redirect indicators grouped by bounded post type.', 'simula-security-telemetry-for-wordfence'),
            ],
            'recent_admin_post_edits_window' => [
                'label'       => __('Recent admin post edits by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Recent content modifications by administrator users grouped by window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'upload_php_files_total' => [
                'label'       => __('Upload PHP files total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('PHP-like files found under uploads.', 'simula-security-telemetry-for-wordfence'),
            ],
            'upload_executable_files_total' => [
                'label'       => __('Upload executable files total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Executable-like files found under uploads.', 'simula-security-telemetry-for-wordfence'),
            ],
            'recent_upload_php_files_window' => [
                'label'       => __('Recent upload PHP files by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Recently modified PHP-like files under uploads grouped by window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugin_files_modified_window' => [
                'label'       => __('Plugin files modified by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Recently modified files under plugins grouped by window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'theme_files_modified_window' => [
                'label'       => __('Theme files modified by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Recently modified files under themes grouped by window.', 'simula-security-telemetry-for-wordfence'),
            ],
            'wp_content_recently_modified_files_total' => [
                'label'       => __('WP content recently modified files total', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Recently modified files under bounded wp-content areas.', 'simula-security-telemetry-for-wordfence'),
            ],
        ];
    }

    /** Returns the default enabled state for every exportable metric family. */
    public static function default_enabled_metrics() {
        $defaults = array_fill_keys(array_keys(self::metric_definitions()), 1);
        $defaults['plugin_inventory_info'] = 0;
        $defaults['admin_user_info']       = 0;

        return $defaults;
    }
}
