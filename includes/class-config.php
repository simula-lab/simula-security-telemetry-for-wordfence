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
    public const VERSION        = '2.3.3';
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
                'description' => __('Cumulative counter of newly observed blocked Wordfence hits.', 'simula-security-telemetry-for-wordfence'),
            ],
            'blocked_events_window' => [
                'label'       => __('Blocked events by window', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Blocked Wordfence hits over 5m, 1h, 24h, and 7d windows.', 'simula-security-telemetry-for-wordfence'),
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
        ];
    }

    /** Returns the default enabled state for every exportable metric family. */
    public static function default_enabled_metrics() {
        $defaults = array_fill_keys(array_keys(self::metric_definitions()), 1);
        $defaults['plugin_inventory_info'] = 0;

        return $defaults;
    }
}
