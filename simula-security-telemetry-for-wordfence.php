<?php
/**
 * Plugin Name: Simula Security Metrics Exporter for Wordfence
 * Plugin URI:  https://github.com/simula-lab/simula-security-telemetry-for-wordfence
 * 
 * Description: Export metrics and incidents from WordPress and Wordfence into a node_exporter textfile collector .prom file, and .log file
 * Version:     2.2.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      Simula
 * Author URI:  https://simulalab.org
 * License:     GPL v2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simula-security-telemetry-for-wordfence
 * Domain Path: /languages
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Config {
    public const OPTION      = 'sstfw_metrics_options';
    public const STATE       = 'sstfw_metrics_state';
    public const CRON_HOOK   = 'sstfw_metrics_export_event';
    public const SLOW_CRON_HOOK = 'sstfw_metrics_slow_export_event';
    public const SLUG        = 'sstfw-metrics';
    public const CAPABILITY  = 'manage_options';
    public const VERSION     = '2.2.2';
    public const TEXT_DOMAIN = 'simula-security-telemetry-for-wordfence';
    public const WINDOWS     = ['5m', '1h', '24h', '7d'];

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
                'description' => __('Current Wordfence scan findings for malware and file changes.', 'simula-security-telemetry-for-wordfence'),
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
            'core_update_available' => [
                'label'       => __('WordPress core update available', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Whether a WordPress core update is available.', 'simula-security-telemetry-for-wordfence'),
            ],
            'plugin_update_available_total' => [
                'label'       => __('Plugin updates available', 'simula-security-telemetry-for-wordfence'),
                'description' => __('Number of plugin updates available.', 'simula-security-telemetry-for-wordfence'),
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
        return array_fill_keys(array_keys(self::metric_definitions()), 1);
    }
}

final class Simula_Security_Telemetry_Util {
    /** Returns an initialized WordPress filesystem handler, or null when unavailable. */
    public static function filesystem() {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!WP_Filesystem()) {
            return null;
        }

        return $wp_filesystem;
    }

    /** Escapes a single database identifier for use in dynamic SQL fragments. */
    public static function quote_identifier($identifier) {
        $identifier = (string) $identifier;

        if (!preg_match('/\A[A-Za-z0-9_]+\z/', $identifier)) {
            return '``';
        }

        return '`' . esc_sql($identifier) . '`';
    }

    /** Executes internally assembled SQL after identifiers/fragments have been validated. */
    public static function db_get_var($query) {
        global $wpdb;

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_var($query);
    }

    /** Executes internally assembled SQL after identifiers/fragments have been validated. */
    public static function db_get_row($query, $output = OBJECT) {
        global $wpdb;

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row($query, $output);
    }

    /** Executes internally assembled SQL after identifiers/fragments have been validated. */
    public static function db_get_results($query, $output = OBJECT) {
        global $wpdb;

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results($query, $output);
    }

    /** Executes internally assembled SQL after identifiers/fragments have been validated. */
    public static function db_get_col($query) {
        global $wpdb;

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_col($query);
    }

    /** Returns the first matching candidate from a resolved column metadata map. */
    public static function resolve_first_candidate($columns, $candidates) {
        foreach ((array) $candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /** Filters candidate names down to the entries available in a resolved column metadata map. */
    public static function resolve_available_candidates($columns, $candidates) {
        $available = [];

        foreach ((array) $candidates as $candidate) {
            if (isset($columns[$candidate])) {
                $available[] = $candidate;
            }
        }

        return $available;
    }

    /** Validates and normalizes an absolute file path against an allowed extension pattern. */
    public static function sanitize_file_setting_path($value, $default, $absolute_error_code, $absolute_error_message, $extension_error_code, $extension_pattern, $extension_error_message) {
        $value = trim(wp_unslash((string) $value));
        if ($value === '') {
            $value = (string) $default;
        }

        $value = wp_normalize_path($value);

        if (!self::is_absolute_path($value)) {
            add_settings_error(
                'sstfw_metrics',
                $absolute_error_code,
                $absolute_error_message,
                'error'
            );

            return (string) $default;
        }

        if (!preg_match($extension_pattern, $value)) {
            add_settings_error(
                'sstfw_metrics',
                $extension_error_code,
                $extension_error_message,
                'error'
            );

            return (string) $default;
        }

        return $value;
    }

    /** Checks whether a filesystem path is absolute on Unix or Windows. */
    private static function is_absolute_path($path) {
        return (bool) preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path);
    }
}

final class Simula_Security_Telemetry_Settings {
    /** Registers the plugin settings and sanitization callback. */
    public static function register_settings() {
        register_setting(
            'sstfw_metrics',
            Simula_Security_Telemetry_Config::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_options'],
                'default'           => Simula_Security_Telemetry_Config::defaults(),
            ]
        );
    }

    /** Loads plugin options merged with defaults. */
    public static function get_options() {
        $options = get_option(Simula_Security_Telemetry_Config::OPTION, []);
        $options = wp_parse_args(is_array($options) ? $options : [], Simula_Security_Telemetry_Config::defaults());
        $options['enabled_metrics'] = self::normalize_enabled_metrics($options['enabled_metrics'] ?? []);

        return $options;
    }

    /** Loads the exporter runtime state from the database. */
    public static function get_state() {
        $state = get_option(Simula_Security_Telemetry_Config::STATE, []);

        return is_array($state) ? $state : [];
    }

    /** Sanitizes submitted settings, updates scheduling, and writes disabled metrics when needed. */
    public static function sanitize_options($input) {
        $defaults = Simula_Security_Telemetry_Config::defaults();
        $input    = is_array($input) ? $input : [];
        $output   = [];

        $output['enabled']              = empty($input['enabled']) ? 0 : 1;
        $output['cron_interval']        = self::sanitize_cron_interval($input['cron_interval'] ?? $defaults['cron_interval']);
        $output['slow_cron_interval']   = self::sanitize_slow_cron_interval($input['slow_cron_interval'] ?? $defaults['slow_cron_interval']);
        $output['prom_file']            = self::sanitize_prom_file($input['prom_file'] ?? $defaults['prom_file']);
        $output['metric_prefix']        = self::sanitize_metric_prefix($input['metric_prefix'] ?? $defaults['metric_prefix']);
        $output['site_label']           = sanitize_text_field(wp_unslash((string) ($input['site_label'] ?? $defaults['site_label'])));
        $output['incident_log_enabled'] = empty($input['incident_log_enabled']) ? 0 : 1;
        $output['incident_log_file']    = self::sanitize_incident_log_file($input['incident_log_file'] ?? $defaults['incident_log_file']);
        $output['incident_log_format']  = self::sanitize_incident_log_format($input['incident_log_format'] ?? $defaults['incident_log_format']);
        $output['incident_max_rows']    = self::sanitize_incident_max_rows($input['incident_max_rows'] ?? $defaults['incident_max_rows']);
        $output['privacy_ip_mode']      = self::sanitize_privacy_ip_mode($input['privacy_ip_mode'] ?? $defaults['privacy_ip_mode']);
        $output['privacy_drop_url_query'] = empty($input['privacy_drop_url_query']) ? 0 : 1;
        $output['privacy_drop_referer'] = empty($input['privacy_drop_referer']) ? 0 : 1;
        $output['privacy_drop_user_agent'] = empty($input['privacy_drop_user_agent']) ? 0 : 1;
        $output['privacy_exclude_private_ips'] = empty($input['privacy_exclude_private_ips']) ? 0 : 1;
        $output['privacy_retention_note'] = self::sanitize_retention_note($input['privacy_retention_note'] ?? $defaults['privacy_retention_note']);
        $output['enabled_metrics']      = self::sanitize_enabled_metrics($input['enabled_metrics'] ?? []);

        if ($output['site_label'] === '') {
            $output['site_label'] = $defaults['site_label'];
        }

        self::sync_schedule($output);

        if (!$output['enabled']) {
            Simula_Security_Telemetry_Output::write_disabled_metrics($output);
        }

        return $output;
    }

    /** Checks whether a metric family is enabled for export. */
    public static function is_metric_enabled($options, $metric_key) {
        $enabled_metrics = self::normalize_enabled_metrics($options['enabled_metrics'] ?? []);

        return !empty($enabled_metrics[$metric_key]);
    }

    /** Ensures the cron event is scheduled only while exporting is enabled. */
    public static function sync_schedule($options) {
        self::sync_single_schedule(
            Simula_Security_Telemetry_Config::CRON_HOOK,
            self::sanitize_cron_interval($options['cron_interval'] ?? Simula_Security_Telemetry_Config::defaults()['cron_interval']),
            !empty($options['enabled'])
        );
        self::sync_single_schedule(
            Simula_Security_Telemetry_Config::SLOW_CRON_HOOK,
            self::sanitize_slow_cron_interval($options['slow_cron_interval'] ?? Simula_Security_Telemetry_Config::defaults()['slow_cron_interval']),
            !empty($options['enabled'])
        );
    }

    /** Synchronizes one named cron hook with the requested interval and enabled state. */
    private static function sync_single_schedule($hook, $interval, $enabled) {
        $scheduled = wp_next_scheduled($hook);

        if ($enabled) {
            $event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event($hook) : false;

            if ($event && $event->schedule !== $interval) {
                wp_clear_scheduled_hook($hook);
                $scheduled = false;
            }

            if (!$scheduled) {
                wp_schedule_event(time() + 60, $interval, $hook);
            }

            return;
        }

        if ($scheduled) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /** Formats a stored export timestamp for display in the admin UI. */
    public static function format_state_time($timestamp) {
        $timestamp = (int) $timestamp;

        if ($timestamp <= 0) {
            return __('Never', 'simula-security-telemetry-for-wordfence');
        }

        return gmdate('Y-m-d H:i:s', $timestamp) . ' UTC';
    }

    /** Validates and normalizes the configured Prometheus output file path. */
    private static function sanitize_prom_file($value) {
        $default = Simula_Security_Telemetry_Config::defaults()['prom_file'];

        return Simula_Security_Telemetry_Util::sanitize_file_setting_path(
            $value,
            $default,
            'sstfw-prom-file',
            __('The Prometheus file path must be absolute. The default path has been restored.', 'simula-security-telemetry-for-wordfence'),
            'sstfw-prom-file-extension',
            '/\.prom$/',
            __('The Prometheus file path must end with .prom. The default path has been restored.', 'simula-security-telemetry-for-wordfence')
        );
    }

    /** Validates and normalizes the configured incident log output path. */
    private static function sanitize_incident_log_file($value) {
        $default = Simula_Security_Telemetry_Config::defaults()['incident_log_file'];

        return Simula_Security_Telemetry_Util::sanitize_file_setting_path(
            $value,
            $default,
            'sstfw-incident-log-file',
            __('The incident log file path must be absolute. The default path has been restored.', 'simula-security-telemetry-for-wordfence'),
            'sstfw-incident-log-file-extension',
            '/\.(?:log|jsonl)$/',
            __('The incident log file path must end with .log or .jsonl. The default path has been restored.', 'simula-security-telemetry-for-wordfence')
        );
    }

    /** Converts the configured metric prefix into a Prometheus-safe identifier. */
    private static function sanitize_metric_prefix($value) {
        $value = wp_unslash((string) $value);
        $value = preg_replace('/[^a-zA-Z0-9_:]/', '_', $value);

        if ($value === '' || !preg_match('/^[a-zA-Z_:]/', $value)) {
            $value = 'wordpress_wordfence';
        }

        return $value;
    }

    /** Validates the configured WP-Cron interval. */
    private static function sanitize_cron_interval($value) {
        $value     = sanitize_key(wp_unslash((string) $value));
        $intervals = Simula_Security_Telemetry_Metrics::cron_interval_labels();

        return isset($intervals[$value]) ? $value : Simula_Security_Telemetry_Config::defaults()['cron_interval'];
    }

    /** Validates the configured slow collector WP-Cron interval. */
    private static function sanitize_slow_cron_interval($value) {
        $value     = sanitize_key(wp_unslash((string) $value));
        $intervals = Simula_Security_Telemetry_Metrics::slow_cron_interval_labels();

        return isset($intervals[$value]) ? $value : Simula_Security_Telemetry_Config::defaults()['slow_cron_interval'];
    }

    /** Validates the configured incident log format. */
    private static function sanitize_incident_log_format($value) {
        $value = sanitize_key(wp_unslash((string) $value));

        return in_array($value, ['text', 'jsonl'], true) ? $value : Simula_Security_Telemetry_Config::defaults()['incident_log_format'];
    }

    /** Validates the configured IP privacy mode for incident logs. */
    private static function sanitize_privacy_ip_mode($value) {
        $value = sanitize_key(wp_unslash((string) $value));

        return in_array($value, ['full', 'truncate', 'hash', 'drop'], true) ? $value : Simula_Security_Telemetry_Config::defaults()['privacy_ip_mode'];
    }

    /** Validates the maximum number of incident rows exported per run. */
    private static function sanitize_incident_max_rows($value) {
        $value = absint($value);

        if ($value < 1) {
            $value = Simula_Security_Telemetry_Config::defaults()['incident_max_rows'];
        }

        return min($value, 10000);
    }

    /** Sanitizes the operator-facing incident retention note. */
    private static function sanitize_retention_note($value) {
        $value = wp_strip_all_tags(wp_unslash((string) $value));
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $value);
        $value = trim(is_string($value) ? $value : '');

        if (strlen($value) > 200) {
            $value = substr($value, 0, 200);
        }

        return $value;
    }

    /** Normalizes stored metric settings to include every known metric family. */
    private static function normalize_enabled_metrics($value) {
        $defaults = Simula_Security_Telemetry_Config::default_enabled_metrics();
        $value    = is_array($value) ? $value : [];

        foreach ($defaults as $metric_key => $default_value) {
            if (array_key_exists($metric_key, $value)) {
                $defaults[$metric_key] = empty($value[$metric_key]) ? 0 : 1;
            }
        }

        return $defaults;
    }

    /** Sanitizes submitted metric settings from the admin form. */
    private static function sanitize_enabled_metrics($value) {
        $sanitized = Simula_Security_Telemetry_Config::default_enabled_metrics();
        $value     = is_array($value) ? $value : [];

        foreach ($sanitized as $metric_key => $default_value) {
            $sanitized[$metric_key] = empty($value[$metric_key]) ? 0 : 1;
        }

        return $sanitized;
    }
}

final class Simula_Security_Telemetry_Output {
    /** Writes a disabled-export metrics file and updates exporter state. */
    public static function write_disabled_metrics($options, $state = [], $disabled_message = null) {
        $state = is_array($state) ? $state : [];
        $site  = self::escape_label($options['site_label']);
        $now   = time();
        $body  = [];
        $disabled_message = is_string($disabled_message) && $disabled_message !== ''
            ? $disabled_message
            : __('Export disabled.', 'simula-security-telemetry-for-wordfence');

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'export_success')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_export_success',
                'gauge',
                'Whether the last Wordfence metrics export succeeded.',
                [
                    ['labels' => ['site' => $site], 'value' => 0],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'plugin_info')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_plugin_info',
                'gauge',
                'Plugin metadata for the exporter.',
                [
                    ['labels' => ['site' => $site, 'version' => self::escape_label(Simula_Security_Telemetry_Config::VERSION)], 'value' => 1],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'enabled')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_enabled',
                'gauge',
                'Whether the exporter master switch is enabled.',
                [
                    ['labels' => ['site' => $site], 'value' => 0],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'last_export_timestamp_seconds')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_last_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the last export attempt.',
                [
                    ['labels' => ['site' => $site], 'value' => $now],
                ]
            );
        }

        $state['last_export'] = $now;

        return self::write_metrics(
            $options['prom_file'],
            empty($body) ? '' : implode("\n", $body) . "\n",
            $disabled_message,
            $state
        );
    }

    /** Builds the fallback metric payload used when an export fails. */
    public static function build_failure_metrics($options, $timestamp, $message) {
        $prefix  = $options['metric_prefix'];
        $site    = self::escape_label($options['site_label']);
        $enabled = !empty($options['enabled']);
        $metrics   = [];

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'export_success')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_export_success',
                'gauge',
                'Whether the last Wordfence metrics export succeeded.',
                [
                    ['labels' => ['site' => $site], 'value' => 0],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'plugin_info')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_plugin_info',
                'gauge',
                'Plugin metadata for the exporter.',
                [
                    ['labels' => ['site' => $site, 'version' => self::escape_label(Simula_Security_Telemetry_Config::VERSION)], 'value' => 1],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'enabled')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_enabled',
                'gauge',
                'Whether the exporter master switch is enabled.',
                [
                    ['labels' => ['site' => $site], 'value' => (int) $enabled],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'last_export_timestamp_seconds')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_last_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the last export attempt.',
                [
                    ['labels' => ['site' => $site], 'value' => $timestamp],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'error_info')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_error_info',
                'gauge',
                'Static bounded error indicator for the latest export.',
                [
                    ['labels' => ['site' => $site, 'type' => self::escape_label(self::classify_error_type((string) $message))], 'value' => 1],
                ]
            );
        }

        return empty($metrics) ? '' : implode("\n", $metrics) . "\n";
    }

    /** Atomically writes the metrics file and optionally persists the outcome to plugin state. */
    public static function write_metrics($file, $content, $error_message, $state, $persist_state = true) {
        $state      = is_array($state) ? $state : [];
        $directory  = dirname($file);
        $filesystem = Simula_Security_Telemetry_Util::filesystem();
        $ok         = false;
        $message    = '';
        $result_ok  = false;

        if ($filesystem === null) {
            $message = __('Could not initialize the WordPress filesystem API.', 'simula-security-telemetry-for-wordfence');
        } elseif (!preg_match('/\.prom$/', $file)) {
            $message = __('Output file must end with .prom.', 'simula-security-telemetry-for-wordfence');
        } elseif (!$filesystem->is_dir($directory)) {
            $message = sprintf(
                /* translators: %s: Metrics output directory path. */
                __('Output directory does not exist: %s', 'simula-security-telemetry-for-wordfence'),
                $directory
            );
        } elseif (!$filesystem->is_writable($directory)) {
            $message = sprintf(
                /* translators: %s: Metrics output directory path. */
                __('Output directory is not writable by PHP: %s', 'simula-security-telemetry-for-wordfence'),
                $directory
            );
        } else {
            $tmp_name = sprintf(
                '%s/.%s.%s.tmp',
                $directory,
                basename($file),
                wp_generate_password(12, false, false)
            );

            $written = $filesystem->put_contents($tmp_name, $content, FS_CHMOD_FILE);
            if (!$written) {
                $message = __('Failed writing the temporary metrics file.', 'simula-security-telemetry-for-wordfence');
            } elseif (!$filesystem->move($tmp_name, $file, true)) {
                $filesystem->delete($tmp_name);
                $message = __('Failed moving the temporary metrics file into place.', 'simula-security-telemetry-for-wordfence');
            } else {
                $ok        = true;
                $message   = $error_message !== '' ? $error_message : sprintf(
                    /* translators: %s: Metrics output file path. */
                    __('Metrics exported to %s', 'simula-security-telemetry-for-wordfence'),
                    $file
                );
                $result_ok = $error_message === '';
            }
        }

        if (!$ok) {
            $result_ok = false;
        }

        $state['last_result']    = $message;
        $state['last_result_ok'] = $result_ok ? 1 : 0;
        $state['last_error']     = $result_ok ? '' : $message;
        if ($persist_state) {
            update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
        }

        return [
            'ok'      => $result_ok,
            'message' => $message,
            'state'   => $state,
        ];
    }

    /** Maps detailed error text to a bounded metric label value. */
    public static function classify_error_type($message) {
        $message = strtolower((string) $message);

        if (strpos($message, 'wordfence table not found') !== false || strpos($message, 'wordfence missing') !== false) {
            return 'wordfence_missing';
        }

        if (strpos($message, 'unsupported wordfence') !== false || strpos($message, 'schema') !== false) {
            return 'schema_unsupported';
        }

        if (strpos($message, 'incident') !== false || strpos($message, 'log') !== false) {
            return 'incident_failed';
        }

        if (strpos($message, 'write') !== false || strpos($message, 'writable') !== false || strpos($message, 'directory') !== false || strpos($message, 'file') !== false) {
            return 'write_failed';
        }

        return 'unknown';
    }

    /** Appends a HELP/TYPE block and one or more metric samples using pre-escaped label values. */
    public static function append_metric_family(&$lines, $metric_name, $type, $help, $samples) {
        $lines[] = '# HELP ' . $metric_name . ' ' . $help;
        $lines[] = '# TYPE ' . $metric_name . ' ' . $type;

        foreach ((array) $samples as $sample) {
            $labels = isset($sample['labels']) && is_array($sample['labels']) ? $sample['labels'] : [];
            $value  = $sample['value'] ?? 0;
            $lines[] = self::build_metric_sample_line($metric_name, $labels, $value);
        }
    }

    /** Builds a single metric sample line using pre-escaped label values. */
    public static function build_metric_sample_line($metric_name, $labels, $value) {
        $label_sql = self::format_metric_labels($labels);

        return $metric_name . $label_sql . ' ' . self::format_metric_value($value);
    }

    /** Escapes a string for safe use in Prometheus label values. */
    public static function escape_label($value) {
        return str_replace(
            ["\\", "\n", '"'],
            ["\\\\", "\\n", '\\"'],
            (string) $value
        );
    }

    /** Formats numeric values for Prometheus output without unnecessary decimals. */
    public static function format_number($value) {
        if (is_int($value) || floor((float) $value) === (float) $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.6F', (float) $value), '0'), '.');
    }

    /** Formats a metric label set from pre-escaped label values. */
    private static function format_metric_labels($labels) {
        $parts = [];

        foreach ((array) $labels as $key => $value) {
            $parts[] = $key . '="' . (string) $value . '"';
        }

        return $parts === [] ? '' : '{' . implode(',', $parts) . '}';
    }

    /** Formats a metric sample value. */
    private static function format_metric_value($value) {
        if (is_int($value) || is_float($value) || is_numeric($value)) {
            return self::format_number($value);
        }

        return (string) $value;
    }
}

final class Simula_Security_Telemetry_Wordfence_Schema {
    /** Returns the resolved Wordfence hits table name. */
    public static function wordfence_hits_table() {
        return self::wordfence_table_aliases(['wfHits', 'wfhits']);
    }

    /** Checks whether a database table exists, using a local cache. */
    public static function table_exists($table) {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $cache[$table] = self::find_existing_table_name([$table]) !== null;

        return $cache[$table];
    }

    /** Returns the first column name that exists from a list of candidates. */
    public static function first_available_column($table, $candidates) {
        return Simula_Security_Telemetry_Util::resolve_first_candidate(self::table_columns($table), $candidates);
    }

    /** Builds the likely table names for a Wordfence table suffix. */
    public static function wordfence_table_candidates($suffix) {
        global $wpdb;

        $candidates = [];
        $prefixes   = [
            (string) $wpdb->prefix,
            isset($wpdb->base_prefix) ? (string) $wpdb->base_prefix : (string) $wpdb->prefix,
        ];

        foreach ($prefixes as $prefix) {
            if ($prefix === '') {
                continue;
            }

            $table = $prefix . $suffix;
            if (!in_array($table, $candidates, true)) {
                $candidates[] = $table;
            }
        }

        return $candidates;
    }

    /** Resolves a Wordfence table suffix to the best matching database table name. */
    public static function wordfence_table($suffix) {
        static $cache = [];

        if (isset($cache[$suffix])) {
            return $cache[$suffix];
        }

        $table = self::find_existing_table_name(self::wordfence_table_candidates($suffix));
        if ($table !== null) {
            $cache[$suffix] = $table;
            return $cache[$suffix];
        }

        $matches = self::discover_wordfence_tables($suffix);
        if (count($matches) === 1) {
            $cache[$suffix] = $matches[0];
            return $cache[$suffix];
        }

        $candidates     = self::wordfence_table_candidates($suffix);
        $cache[$suffix] = isset($candidates[0]) ? $candidates[0] : (string) $suffix;

        return $cache[$suffix];
    }

    /** Returns the column metadata for a table, cached by table name. */
    public static function table_columns($table) {
        static $cache = [];
        global $wpdb;

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        if (!self::table_exists($table)) {
            $cache[$table] = [];
            return $cache[$table];
        }

        $table_identifier = Simula_Security_Telemetry_Util::quote_identifier($table);
        $rows             = Simula_Security_Telemetry_Util::db_get_results("SHOW COLUMNS FROM $table_identifier", ARRAY_A);
        $columns          = [];

        foreach ((array) $rows as $row) {
            if (!isset($row['Field'])) {
                continue;
            }

            $columns[(string) $row['Field']] = $row;
        }

        $cache[$table] = $columns;

        return $cache[$table];
    }

    /** Returns the Wordfence scan issue table currently available in the database. */
    public static function scan_issue_table() {
        foreach (['wfIssues', 'wfPendingIssues'] as $suffix) {
            $table = self::wordfence_table($suffix);
            if (self::table_exists($table)) {
                return $table;
            }
        }

        return null;
    }

    /** Resolves a Wordfence table from multiple known suffix aliases. */
    private static function wordfence_table_aliases($suffixes) {
        static $cache = [];

        $suffixes  = array_values(array_unique(array_filter(array_map('strval', (array) $suffixes))));
        $cache_key = implode('|', $suffixes);

        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        foreach ($suffixes as $suffix) {
            $resolved = self::wordfence_table($suffix);
            if (self::table_exists($resolved)) {
                $cache[$cache_key] = $resolved;
                return $cache[$cache_key];
            }
        }

        $fallback          = isset($suffixes[0]) ? self::wordfence_table($suffixes[0]) : '';
        $cache[$cache_key] = $fallback;

        return $cache[$cache_key];
    }

    /** Returns the first existing table name that matches the provided candidates. */
    private static function find_existing_table_name($candidates) {
        $tables = self::database_tables();

        foreach ((array) $candidates as $candidate) {
            foreach ($tables as $table) {
                if (strcasecmp($table, (string) $candidate) === 0) {
                    return $table;
                }
            }
        }

        return null;
    }

    /** Returns the list of database tables, cached for repeated lookups. */
    private static function database_tables() {
        static $cache = null;
        global $wpdb;

        if ($cache !== null) {
            return $cache;
        }

        $rows  = Simula_Security_Telemetry_Util::db_get_col('SHOW TABLES');
        $cache = [];

        foreach ((array) $rows as $table) {
            $table = (string) $table;
            if ($table !== '') {
                $cache[] = $table;
            }
        }

        return $cache;
    }

    /** Finds database tables whose names end with the requested Wordfence suffix. */
    private static function discover_wordfence_tables($suffix) {
        static $cache = [];

        $suffix = (string) $suffix;

        if (isset($cache[$suffix])) {
            return $cache[$suffix];
        }

        $rows    = self::database_tables();
        $matches = [];

        foreach ((array) $rows as $table) {
            $table = (string) $table;
            if ($table === '') {
                continue;
            }

            if (strlen($table) < strlen($suffix) || strcasecmp(substr($table, -strlen($suffix)), $suffix) !== 0) {
                continue;
            }

            if (!in_array($table, $matches, true)) {
                $matches[] = $table;
            }
        }

        $cache[$suffix] = $matches;

        return $cache[$suffix];
    }
}

final class Simula_Security_Telemetry_Wordfence_Collector {
    /** Builds the SQL condition used to identify blocked requests in a hits table. */
    public static function blocked_where_sql($table) {
        $clauses = [];

        $action_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['action']);
        if ($action_column !== null) {
            $action_identifier = self::quote_identifier($action_column);
            $clauses[]         = '(' . $action_identifier . " IS NOT NULL AND $action_identifier LIKE 'blocked:%')";
        }

        $status_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['statusCode', 'status']);
        if ($status_column !== null) {
            $clauses[] = self::quote_identifier($status_column) . ' IN (403, 503)';
        }

        return self::combine_where_any($clauses);
    }

    /** Builds the SQL condition used to detect failed login activity. */
    public static function failed_login_where_sql($table) {
        return self::text_search_where_sql(
            $table,
            ['action', 'URL', 'url', 'requestUri', 'path'],
            ['loginfail', 'login failure', 'failed login', 'invalid username', 'incorrect password']
        );
    }

    /** Builds the SQL condition used to detect throttled or rate-limited requests. */
    public static function rate_limited_where_sql($table) {
        return self::text_search_where_sql(
            $table,
            ['action', 'URL', 'url', 'requestUri', 'path'],
            ['throttle', 'throttled', 'rate limit', 'rate-limit', 'rate_limited']
        );
    }

    /** Builds the SQL condition used to detect username/password brute-force activity. */
    public static function brute_force_username_where_sql($table) {
        $xmlrpc_where = self::brute_force_xmlrpc_where_sql($table);
        $username_sql = self::combine_where_any([
            self::failed_login_where_sql($table),
            self::text_search_where_sql(
                $table,
                ['action', 'URL', 'url', 'requestUri', 'path'],
                ['brute force', 'brute-force', 'wp-login.php', 'login attempt']
            ),
        ]);

        if ($username_sql === '0=1') {
            return $username_sql;
        }

        if ($xmlrpc_where !== '0=1') {
            return '(' . $username_sql . ' AND NOT ' . $xmlrpc_where . ')';
        }

        return $username_sql;
    }

    /** Builds the SQL condition used to detect XML-RPC brute-force activity. */
    public static function brute_force_xmlrpc_where_sql($table) {
        return self::text_search_where_sql(
            $table,
            ['action', 'URL', 'url', 'requestUri', 'path'],
            ['xmlrpc', 'xml-rpc']
        );
    }

    /** Builds SQL SELECT expressions that count matching rows across configured time windows. */
    public static function build_window_count_select_sql($prefix, $condition_sql, $time_identifier, $windows) {
        $selects = [];

        foreach (Simula_Security_Telemetry_Config::WINDOWS as $window) {
            $selects[] = sprintf(
                'SUM(CASE WHEN %1$s >= %2$d AND %3$s THEN 1 ELSE 0 END) AS %4$s_count_%5$s',
                $time_identifier,
                (int) $windows[$window],
                $condition_sql,
                $prefix,
                $window
            );
        }

        return implode(",\n                ", $selects);
    }

    /** Collects the top blocked attack sources by country and normalized IP range. */
    public static function collect_top_attack_sources($table, $time_identifier, $blocked_where, $since_timestamp) {
        global $wpdb;

        $sources        = [];
        $table_identifier = self::quote_identifier($table);
        $country_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['ctry', 'countryCode', 'country']);

        if ($country_column !== null) {
            $country_identifier = self::quote_identifier($country_column);
            $country_rows       = Simula_Security_Telemetry_Util::db_get_results(
                "SELECT $country_identifier AS source_name, COUNT(*) AS count_total
                FROM $table_identifier
                WHERE $time_identifier >= " . (int) $since_timestamp . " AND $blocked_where AND $country_identifier IS NOT NULL AND $country_identifier <> ''
                GROUP BY $country_identifier
                ORDER BY count_total DESC
                LIMIT 10",
                ARRAY_A
            );

            foreach ((array) $country_rows as $row) {
                if (empty($row['source_name'])) {
                    continue;
                }

                $sources[] = [
                    'source_type' => 'country',
                    'source'      => (string) $row['source_name'],
                    'count_total' => (int) ($row['count_total'] ?? 0),
                ];
            }
        }

        $ip_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['IP', 'ip']);
        if ($ip_column !== null) {
            $ip_identifier = self::quote_identifier($ip_column);
            $ip_rows       = Simula_Security_Telemetry_Util::db_get_results(
                "SELECT $ip_identifier AS source_ip, COUNT(*) AS count_total
                FROM $table_identifier
                WHERE $time_identifier >= " . (int) $since_timestamp . " AND $blocked_where AND $ip_identifier IS NOT NULL
                GROUP BY $ip_identifier
                ORDER BY count_total DESC
                LIMIT 100",
                ARRAY_A
            );
            $ip_ranges = [];

            foreach ((array) $ip_rows as $row) {
                $range = self::normalize_ip_range($row['source_ip'] ?? '');
                if ($range === '') {
                    continue;
                }

                if (!isset($ip_ranges[$range])) {
                    $ip_ranges[$range] = 0;
                }

                $ip_ranges[$range] += (int) ($row['count_total'] ?? 0);
            }

            arsort($ip_ranges);

            foreach (array_slice($ip_ranges, 0, 10, true) as $range => $count) {
                $sources[] = [
                    'source_type' => 'ip_range',
                    'source'      => (string) $range,
                    'count_total' => (int) $count,
                ];
            }
        }

        return $sources;
    }

    /** Collects current IP and user lockout totals from available Wordfence tables. */
    public static function collect_lockout_counts($now) {
        global $wpdb;

        $counts           = ['ip' => 0, 'user' => 0];
        $blocked_ip_table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfBlockedIPLog');

        if (Simula_Security_Telemetry_Wordfence_Schema::table_exists($blocked_ip_table)) {
            $blocked_ip_table_identifier = self::quote_identifier($blocked_ip_table);
            $ip_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($blocked_ip_table, ['IP', 'ip']);
            if ($ip_column !== null) {
                $ip_identifier = self::quote_identifier($ip_column);
                $lockout_where = self::lockout_active_where_sql($blocked_ip_table, $now);
                $query         = 'SELECT COUNT(DISTINCT ' . $ip_identifier . ") AS total FROM $blocked_ip_table_identifier";

                if ($lockout_where !== '') {
                    $query .= ' WHERE ' . $lockout_where;
                }

                $counts['ip'] = (int) Simula_Security_Telemetry_Util::db_get_var($query);
            }

            $user_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($blocked_ip_table, ['username', 'userName', 'user_id', 'userID', 'userId']);
            if ($user_column !== null) {
                $user_identifier = self::quote_identifier($user_column);
                $lockout_where   = self::lockout_active_where_sql($blocked_ip_table, $now);
                $query           = 'SELECT COUNT(DISTINCT ' . $user_identifier . ") AS total FROM $blocked_ip_table_identifier WHERE $user_identifier IS NOT NULL AND $user_identifier <> ''";

                if ($lockout_where !== '') {
                    $query .= ' AND ' . $lockout_where;
                }

                $counts['user'] = (int) Simula_Security_Telemetry_Util::db_get_var($query);
            }
        }

        $login_table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfLogins');
        if ($counts['user'] === 0 && Simula_Security_Telemetry_Wordfence_Schema::table_exists($login_table)) {
            $user_column   = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($login_table, ['username', 'userName', 'user_id', 'userID', 'userId']);
            $lockout_where = self::lockout_active_where_sql($login_table, $now);

            if ($user_column !== null && $lockout_where !== '') {
                $user_identifier = self::quote_identifier($user_column);
                $login_table_identifier = self::quote_identifier($login_table);
                $counts['user']  = (int) Simula_Security_Telemetry_Util::db_get_var(
                    'SELECT COUNT(DISTINCT ' . $user_identifier . ") AS total FROM $login_table_identifier WHERE $user_identifier IS NOT NULL AND $user_identifier <> '' AND " . $lockout_where
                );
            }
        }

        return $counts;
    }

    /** Collects Wordfence two-factor status and protected-user counts. */
    public static function collect_two_factor_metrics() {
        global $wpdb;

        $metrics        = ['enabled' => 0, 'protected_users' => 0];
        $secrets_table  = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfls_2fa_secrets');
        $settings_table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfls_settings');

        if (Simula_Security_Telemetry_Wordfence_Schema::table_exists($secrets_table)) {
            $secrets_table_identifier = self::quote_identifier($secrets_table);
            $user_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($secrets_table, ['user_id', 'userID', 'userId', 'user']);
            if ($user_column !== null) {
                $metrics['protected_users'] = (int) Simula_Security_Telemetry_Util::db_get_var(
                    'SELECT COUNT(DISTINCT ' . self::quote_identifier($user_column) . ") FROM $secrets_table_identifier"
                );
            } else {
                $metrics['protected_users'] = (int) Simula_Security_Telemetry_Util::db_get_var("SELECT COUNT(*) FROM $secrets_table_identifier");
            }
        }

        if ($metrics['protected_users'] > 0) {
            $metrics['enabled'] = 1;
        } elseif (Simula_Security_Telemetry_Wordfence_Schema::table_exists($settings_table)) {
            $settings_table_identifier = self::quote_identifier($settings_table);
            $metrics['enabled'] = (int) (Simula_Security_Telemetry_Util::db_get_var("SELECT COUNT(*) FROM $settings_table_identifier") > 0);
        }

        return $metrics;
    }

    /** Collects scan issue totals grouped by severity and finding category. */
    public static function collect_scan_issue_metrics() {
        global $wpdb;

        $metrics = [
            'severity'        => [],
            'malware'         => 0,
            'file_change'     => 0,
            'vulnerabilities' => [
                'core'   => 0,
                'plugin' => 0,
                'theme'  => 0,
            ],
        ];
        $table   = Simula_Security_Telemetry_Wordfence_Schema::scan_issue_table();

        if ($table === null) {
            return $metrics;
        }

        $table_identifier = self::quote_identifier($table);
        $severity_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['severity', 'level', 'status']);
        if ($severity_column !== null) {
            $severity_identifier = self::quote_identifier($severity_column);
            $metrics['severity'] = Simula_Security_Telemetry_Util::db_get_results(
                "SELECT $severity_identifier AS severity, COUNT(*) AS count_total
                FROM $table_identifier
                GROUP BY $severity_identifier
                ORDER BY count_total DESC",
                ARRAY_A
            );
        }

        $text_columns = self::available_columns($table, ['type', 'shortMsg', 'longMsg', 'description', 'solution', 'data', 'ignoreInfo']);
        if ($text_columns === []) {
            return $metrics;
        }

        $malware_where = self::text_search_where_sql_from_columns($text_columns, ['malware', 'malicious', 'backdoor', 'trojan', 'phishing']);
        $file_where    = self::text_search_where_sql_from_columns($text_columns, ['file changed', 'changed file', 'modified file', 'unknown file', 'file contents changed']);
        $vuln_where    = self::text_search_where_sql_from_columns($text_columns, ['vulnerab', 'outdated', 'security update', 'update available']);
        $core_where    = self::combine_where_all([
            self::text_search_where_sql_from_columns($text_columns, ['core']),
            $vuln_where,
        ]);
        $plugin_where  = self::combine_where_all([
            self::text_search_where_sql_from_columns($text_columns, ['plugin']),
            $vuln_where,
        ]);
        $theme_where   = self::combine_where_all([
            self::text_search_where_sql_from_columns($text_columns, ['theme']),
            $vuln_where,
        ]);
        $row           = Simula_Security_Telemetry_Util::db_get_row(
            "SELECT
                SUM(CASE WHEN $malware_where THEN 1 ELSE 0 END) AS malware_total,
                SUM(CASE WHEN $file_where THEN 1 ELSE 0 END) AS file_change_total,
                SUM(CASE WHEN $core_where THEN 1 ELSE 0 END) AS core_total,
                SUM(CASE WHEN $plugin_where THEN 1 ELSE 0 END) AS plugin_total,
                SUM(CASE WHEN $theme_where THEN 1 ELSE 0 END) AS theme_total
            FROM $table_identifier",
            ARRAY_A
        );

        $metrics['malware']                   = isset($row['malware_total']) ? (int) $row['malware_total'] : 0;
        $metrics['file_change']               = isset($row['file_change_total']) ? (int) $row['file_change_total'] : 0;
        $metrics['vulnerabilities']['core']   = isset($row['core_total']) ? (int) $row['core_total'] : 0;
        $metrics['vulnerabilities']['plugin'] = isset($row['plugin_total']) ? (int) $row['plugin_total'] : 0;
        $metrics['vulnerabilities']['theme']  = isset($row['theme_total']) ? (int) $row['theme_total'] : 0;

        return $metrics;
    }

    /** Collects latest source timestamps from Wordfence hit and scan tables. */
    public static function collect_source_freshness($hits_table, $time_identifier, $blocked_where, $now) {
        global $wpdb;

        $freshness = [
            'latest_hit'         => 0,
            'latest_blocked_hit' => 0,
            'latest_scan'        => 0,
            'scan_age'           => 0,
        ];

        if (Simula_Security_Telemetry_Wordfence_Schema::table_exists($hits_table)) {
            $hits_table_identifier = self::quote_identifier($hits_table);
            $freshness['latest_hit'] = (int) Simula_Security_Telemetry_Util::db_get_var("SELECT COALESCE(MAX($time_identifier), 0) FROM $hits_table_identifier");
            if ($blocked_where !== '0=1') {
                $freshness['latest_blocked_hit'] = (int) Simula_Security_Telemetry_Util::db_get_var("SELECT COALESCE(MAX($time_identifier), 0) FROM $hits_table_identifier WHERE $blocked_where");
            }
        }

        $freshness['latest_scan'] = self::collect_latest_scan_timestamp();
        $freshness['scan_age']    = $freshness['latest_scan'] > 0 ? max(0, (int) $now - (int) $freshness['latest_scan']) : 0;

        return $freshness;
    }

    /** Collects Wordfence installation, version, firewall, live traffic, scan, and license posture. */
    public static function collect_wordfence_posture() {
        $version      = self::wordfence_version();
        $installed    = $version !== '' || Simula_Security_Telemetry_Wordfence_Schema::table_exists(Simula_Security_Telemetry_Wordfence_Schema::wordfence_hits_table());
        $license_type = self::wordfence_license_type();

        return [
            'installed'            => $installed ? 1 : 0,
            'version'              => $version !== '' ? $version : 'unknown',
            'firewall_enabled'     => self::wordfence_config_enabled(['firewallEnabled', 'wafEnabled'], $installed ? 1 : 0),
            'firewall_optimized'   => self::wordfence_firewall_optimized(),
            'live_traffic_enabled' => self::wordfence_config_enabled(['liveTrafficEnabled', 'liveTraffic'], 0),
            'scan_enabled'         => self::wordfence_config_enabled(['scansEnabled', 'scheduledScansEnabled'], $installed ? 1 : 0),
            'license_type'         => $license_type,
        ];
    }

    /** Collects WordPress update and administrator 2FA posture. */
    public static function collect_wordpress_posture() {
        $admin_ids      = self::administrator_user_ids();
        $protected_ids  = self::two_factor_protected_user_ids();
        $without_2fa    = 0;
        $protected_flip = array_fill_keys(array_map('intval', $protected_ids), true);

        foreach ($admin_ids as $admin_id) {
            if (empty($protected_flip[(int) $admin_id])) {
                $without_2fa++;
            }
        }

        return [
            'core_update_available'        => self::core_update_available(),
            'plugin_update_available_total' => self::plugin_update_count(),
            'theme_update_available_total'  => self::theme_update_count(),
            'admin_users_total'             => count($admin_ids),
            'admin_users_without_2fa_total' => $without_2fa,
        ];
    }

    /** Filters a list of candidate column names down to those present in a table. */
    private static function available_columns($table, $candidates) {
        return Simula_Security_Telemetry_Util::resolve_available_candidates(
            Simula_Security_Telemetry_Wordfence_Schema::table_columns($table),
            $candidates
        );
    }

    /** Finds the latest timestamp-like value from the current Wordfence scan issue table. */
    private static function collect_latest_scan_timestamp() {
        global $wpdb;

        $table = Simula_Security_Telemetry_Wordfence_Schema::scan_issue_table();
        if ($table === null) {
            return 0;
        }

        $column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column(
            $table,
            ['lastUpdated', 'last_update', 'updated_at', 'modified', 'ctime', 'time', 'created_at']
        );

        if ($column === null) {
            return 0;
        }

        $table_identifier = self::quote_identifier($table);
        $value = Simula_Security_Telemetry_Util::db_get_var('SELECT COALESCE(MAX(' . self::quote_identifier($column) . "), 0) FROM $table_identifier");

        return self::normalize_timestamp_value($value);
    }

    /** Normalizes numeric or parseable date values into Unix timestamps. */
    private static function normalize_timestamp_value($value) {
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value) && $value !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? 0 : max(0, (int) $timestamp);
        }

        return 0;
    }

    /** Returns the detected Wordfence version. */
    private static function wordfence_version() {
        if (defined('WORDFENCE_VERSION')) {
            return (string) WORDFENCE_VERSION;
        }

        if (!function_exists('get_plugins')) {
            $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_readable($plugin_file)) {
                require_once $plugin_file;
            }
        }

        if (function_exists('get_plugins')) {
            $plugins = get_plugins();
            if (isset($plugins['wordfence/wordfence.php']['Version'])) {
                return (string) $plugins['wordfence/wordfence.php']['Version'];
            }
        }

        return '';
    }

    /** Reads a boolean-ish Wordfence config value when wfConfig is available. */
    private static function wordfence_config_enabled($keys, $default) {
        if (!class_exists('wfConfig') || !method_exists('wfConfig', 'get')) {
            return (int) $default;
        }

        foreach ((array) $keys as $key) {
            $value = wfConfig::get($key, null);
            if ($value !== null && $value !== '') {
                return empty($value) ? 0 : 1;
            }
        }

        return (int) $default;
    }

    /** Detects whether the Wordfence firewall appears optimized. */
    private static function wordfence_firewall_optimized() {
        if (defined('WFWAF_AUTO_PREPEND') && WFWAF_AUTO_PREPEND) {
            return 1;
        }

        if (class_exists('wfConfig') && method_exists('wfConfig', 'get')) {
            $status = wfConfig::get('wafStatus', '');
            if (is_string($status) && stripos($status, 'enabled') !== false) {
                return 1;
            }
        }

        return 0;
    }

    /** Returns free, premium, or unknown for the Wordfence license type. */
    private static function wordfence_license_type() {
        if (class_exists('wfConfig') && method_exists('wfConfig', 'get')) {
            $is_paid = wfConfig::get('isPaid', null);
            if ($is_paid !== null && $is_paid !== '') {
                return empty($is_paid) ? 'free' : 'premium';
            }
        }

        return 'unknown';
    }

    /** Returns administrator user IDs. */
    private static function administrator_user_ids() {
        if (!function_exists('get_users')) {
            return [];
        }

        $users = get_users([
            'role'   => 'administrator',
            'fields' => 'ID',
        ]);

        return array_map('intval', is_array($users) ? $users : []);
    }

    /** Returns user IDs with Wordfence two-factor secrets when available. */
    private static function two_factor_protected_user_ids() {
        global $wpdb;

        $table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfls_2fa_secrets');
        if (!Simula_Security_Telemetry_Wordfence_Schema::table_exists($table)) {
            return [];
        }

        $user_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['user_id', 'userID', 'userId', 'user']);
        if ($user_column === null) {
            return [];
        }

        $table_identifier = self::quote_identifier($table);

        return array_map('intval', (array) Simula_Security_Telemetry_Util::db_get_col('SELECT DISTINCT ' . self::quote_identifier($user_column) . " FROM $table_identifier"));
    }

    /** Returns whether a WordPress core update is available. */
    private static function core_update_available() {
        $updates = function_exists('get_site_transient') ? get_site_transient('update_core') : null;
        if (!is_object($updates) || empty($updates->updates) || !is_array($updates->updates)) {
            return 0;
        }

        foreach ($updates->updates as $update) {
            if (is_object($update) && isset($update->response) && $update->response === 'upgrade') {
                return 1;
            }
        }

        return 0;
    }

    /** Returns the number of plugin updates available. */
    private static function plugin_update_count() {
        $updates = function_exists('get_site_transient') ? get_site_transient('update_plugins') : null;

        return is_object($updates) && isset($updates->response) && is_array($updates->response) ? count($updates->response) : 0;
    }

    /** Returns the number of theme updates available. */
    private static function theme_update_count() {
        $updates = function_exists('get_site_transient') ? get_site_transient('update_themes') : null;

        return is_object($updates) && isset($updates->response) && is_array($updates->response) ? count($updates->response) : 0;
    }

    /** Builds a text-search SQL condition across matching columns in a table. */
    private static function text_search_where_sql($table, $candidate_columns, $terms) {
        return self::text_search_where_sql_from_columns(self::available_columns($table, $candidate_columns), $terms);
    }

    /** Builds a text-search SQL condition from a specific list of columns and terms. */
    private static function text_search_where_sql_from_columns($columns, $terms) {
        global $wpdb;

        $clauses = [];

        foreach ((array) $columns as $column) {
            $identifier = self::quote_identifier($column);

            foreach ((array) $terms as $term) {
                $like      = '%' . strtolower($wpdb->esc_like((string) $term)) . '%';
                $clauses[] = "LOWER(COALESCE(CAST($identifier AS CHAR), '')) LIKE '" . esc_sql($like) . "'";
            }
        }

        return self::combine_where_any($clauses);
    }

    /** Escapes a database identifier for use in dynamic SQL fragments. */
    private static function quote_identifier($identifier) {
        return Simula_Security_Telemetry_Util::quote_identifier($identifier);
    }

    /** Combines SQL clauses with OR and returns an always-false condition when empty. */
    private static function combine_where_any($clauses) {
        $filtered = [];

        foreach ((array) $clauses as $clause) {
            $clause = trim((string) $clause);
            if ($clause === '' || $clause === '0=1') {
                continue;
            }

            $filtered[] = $clause;
        }

        if ($filtered === []) {
            return '0=1';
        }

        return '(' . implode(' OR ', $filtered) . ')';
    }

    /** Combines SQL clauses with AND and returns an always-false condition when empty. */
    private static function combine_where_all($clauses) {
        $filtered = [];

        foreach ((array) $clauses as $clause) {
            $clause = trim((string) $clause);
            if ($clause === '') {
                continue;
            }

            if ($clause === '0=1') {
                return '0=1';
            }

            $filtered[] = $clause;
        }

        if ($filtered === []) {
            return '0=1';
        }

        return '(' . implode(' AND ', $filtered) . ')';
    }

    /** Builds the SQL condition used to identify active lockouts in a table. */
    private static function lockout_active_where_sql($table, $now) {
        $clauses = [];

        foreach (['expiration', 'blockedUntil', 'expiresAt', 'lockedOutUntil', 'lockoutTime'] as $column) {
            if (Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, [$column]) !== null) {
                $clauses[] = self::quote_identifier($column) . ' > ' . (int) $now;
            }
        }

        foreach (['blocked', 'lockedOut', 'isLocked'] as $column) {
            if (Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, [$column]) !== null) {
                $clauses[] = self::quote_identifier($column) . ' = 1';
            }
        }

        $status_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['status']);
        if ($status_column !== null) {
            $status_identifier = self::quote_identifier($status_column);
            $clauses[]         = "LOWER(COALESCE(CAST($status_identifier AS CHAR), '')) LIKE '%lock%'";
        }

        if ($clauses === []) {
            return '';
        }

        return self::combine_where_any($clauses);
    }

    /** Normalizes an IP value into a /24 IPv4 or /64 IPv6 range label. */
    private static function normalize_ip_range($value) {
        if (!is_scalar($value)) {
            return '';
        }

        $ip = trim((string) $value);
        if ($ip === '' || preg_match('/[[:cntrl:]]/', $ip)) {
            return '';
        }

        if (ctype_digit($ip)) {
            $numeric_ip = (float) $ip;
            if ($numeric_ip >= 0 && $numeric_ip <= 4294967295) {
                $ip = (string) long2ip((int) $numeric_ip);
            }
        }

        if (strpos($ip, '.') !== false) {
            $parts = explode('.', $ip);
            if (count($parts) >= 4) {
                return implode('.', array_slice($parts, 0, 3)) . '.0/24';
            }
        }

        if (strpos($ip, ':') !== false) {
            $packed = @inet_pton($ip);
            if ($packed === false) {
                return '';
            }

            $hex = bin2hex($packed);

            return sprintf(
                '%s:%s:%s:%s::/64',
                substr($hex, 0, 4),
                substr($hex, 4, 4),
                substr($hex, 8, 4),
                substr($hex, 12, 4)
            );
        }

        return '';
    }
}

final class Simula_Security_Telemetry_Wordfence {
    /** Returns the resolved Wordfence hits table name. */
    public static function wordfence_hits_table() {
        return Simula_Security_Telemetry_Wordfence_Schema::wordfence_hits_table();
    }

    /** Checks whether a database table exists, using a local cache. */
    public static function table_exists($table) {
        return Simula_Security_Telemetry_Wordfence_Schema::table_exists($table);
    }

    /** Returns the first column name that exists from a list of candidates. */
    public static function first_available_column($table, $candidates) {
        return Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, $candidates);
    }

    /** Builds the SQL condition used to identify blocked requests in a hits table. */
    public static function blocked_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::blocked_where_sql($table);
    }

    /** Builds the SQL condition used to detect failed login activity. */
    public static function failed_login_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::failed_login_where_sql($table);
    }

    /** Builds the SQL condition used to detect throttled or rate-limited requests. */
    public static function rate_limited_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::rate_limited_where_sql($table);
    }

    /** Builds the SQL condition used to detect username/password brute-force activity. */
    public static function brute_force_username_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::brute_force_username_where_sql($table);
    }

    /** Builds the SQL condition used to detect XML-RPC brute-force activity. */
    public static function brute_force_xmlrpc_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::brute_force_xmlrpc_where_sql($table);
    }

    /** Builds SQL SELECT expressions that count matching rows across configured time windows. */
    public static function build_window_count_select_sql($prefix, $condition_sql, $time_identifier, $windows) {
        return Simula_Security_Telemetry_Wordfence_Collector::build_window_count_select_sql($prefix, $condition_sql, $time_identifier, $windows);
    }

    /** Collects the top blocked attack sources by country and normalized IP range. */
    public static function collect_top_attack_sources($table, $time_identifier, $blocked_where, $since_timestamp) {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_top_attack_sources($table, $time_identifier, $blocked_where, $since_timestamp);
    }

    /** Collects current IP and user lockout totals from available Wordfence tables. */
    public static function collect_lockout_counts($now) {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_lockout_counts($now);
    }

    /** Collects Wordfence two-factor status and protected-user counts. */
    public static function collect_two_factor_metrics() {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_two_factor_metrics();
    }

    /** Collects scan issue totals grouped by severity and finding category. */
    public static function collect_scan_issue_metrics() {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_scan_issue_metrics();
    }

    /** Collects latest source timestamps from Wordfence hit and scan tables. */
    public static function collect_source_freshness($hits_table, $time_identifier, $blocked_where, $now) {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_source_freshness($hits_table, $time_identifier, $blocked_where, $now);
    }

    /** Collects Wordfence installation and runtime posture. */
    public static function collect_wordfence_posture() {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_wordfence_posture();
    }

    /** Collects WordPress update and administrator 2FA posture. */
    public static function collect_wordpress_posture() {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_wordpress_posture();
    }

    /** Builds the likely table names for a Wordfence table suffix. */
    public static function wordfence_table_candidates($suffix) {
        return Simula_Security_Telemetry_Wordfence_Schema::wordfence_table_candidates($suffix);
    }

    /** Returns the column metadata for a table, cached by table name. */
    public static function table_columns($table) {
        return Simula_Security_Telemetry_Wordfence_Schema::table_columns($table);
    }
}

final class Simula_Security_Telemetry_Incidents {
    /** Initializes the incident cursor from the current maximum Wordfence hit ID and returns the resulting state. */
    public static function initialize_cursor_if_needed($state = null, $persist_state = true) {
        global $wpdb;

        $state = is_array($state) ? $state : Simula_Security_Telemetry_Settings::get_state();
        if (!empty($state['incident_cursor_initialized'])) {
            return $state;
        }

        $table      = Simula_Security_Telemetry_Wordfence_Schema::wordfence_hits_table();
        $last_id    = 0;
        $id_column  = null;

        if (Simula_Security_Telemetry_Wordfence_Schema::table_exists($table)) {
            $id_column = Simula_Security_Telemetry_Util::resolve_first_candidate(
                Simula_Security_Telemetry_Wordfence_Schema::table_columns($table),
                ['id']
            );
        }

        if ($id_column !== null) {
            $table_identifier = self::quote_identifier($table);
            $last_id = (int) Simula_Security_Telemetry_Util::db_get_var(
                'SELECT COALESCE(MAX(' . self::quote_identifier($id_column) . "), 0) FROM $table_identifier"
            );
        }

        $state['incident_cursor_initialized'] = 1;
        $state['last_incident_id']            = max(0, $last_id);
        if ($persist_state) {
            update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
        }

        return $state;
    }

    /** Resets the incident cursor so the next run can backfill from the start of the hits table. */
    public static function reset_cursor() {
        $state = Simula_Security_Telemetry_Settings::get_state();

        $state['incident_cursor_initialized'] = 1;
        $state['last_incident_id']            = 0;
        $state['last_incident_error']         = '';
        update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
    }

    /** Exports new blocked Wordfence incidents as text or JSON Lines log lines. */
    public static function export($options = null, $state = null, $persist_state = true) {
        global $wpdb;

        $options = is_array($options) ? $options : Simula_Security_Telemetry_Settings::get_options();
        $state   = is_array($state) ? $state : Simula_Security_Telemetry_Settings::get_state();
        if (empty($options['enabled'])) {
            return [
                'ok'      => false,
                'message' => __('Exporter is disabled. Enable the exporter to run both metrics and incident log exports.', 'simula-security-telemetry-for-wordfence'),
                'state'   => $state,
            ];
        }

        if (empty($options['incident_log_enabled'])) {
            return [
                'ok'      => true,
                'message' => __('Incident log export disabled.', 'simula-security-telemetry-for-wordfence'),
                'state'   => $state,
            ];
        }

        $table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_hits_table();

        if (!Simula_Security_Telemetry_Wordfence_Schema::table_exists($table)) {
            $message = sprintf(
                /* translators: %s: Comma-separated list of Wordfence table names that were checked. */
                __('Wordfence table not found. Tried: %s', 'simula-security-telemetry-for-wordfence'),
                implode(', ', Simula_Security_Telemetry_Wordfence_Schema::wordfence_table_candidates('wfHits'))
            );

            return self::update_failure_state($state, $options, $message, $persist_state);
        }

        $state  = self::initialize_cursor_if_needed($state, $persist_state);
        $schema = self::resolve_schema($table);

        if ($schema['id'] === null) {
            return self::update_failure_state(
                $state,
                $options,
                __('Unsupported Wordfence hits schema: missing an incident ID column.', 'simula-security-telemetry-for-wordfence'),
                $persist_state
            );
        }

        if ($schema['time'] === null && empty($schema['time_columns'])) {
            return self::update_failure_state(
                $state,
                $options,
                __('Unsupported Wordfence hits schema: missing an incident timestamp column.', 'simula-security-telemetry-for-wordfence'),
                $persist_state
            );
        }

        $where_sql = Simula_Security_Telemetry_Wordfence_Collector::blocked_where_sql($table);
        if ($where_sql === '0=1') {
            return self::update_failure_state(
                $state,
                $options,
                __('Unsupported Wordfence hits schema: blocked incident filtering is unavailable.', 'simula-security-telemetry-for-wordfence'),
                $persist_state
            );
        }

        $last_id       = isset($state['last_incident_id']) ? (int) $state['last_incident_id'] : 0;
        $max_rows      = isset($options['incident_max_rows']) ? (int) $options['incident_max_rows'] : Simula_Security_Telemetry_Config::defaults()['incident_max_rows'];
        $limit         = min(max($max_rows, 1), 10000);
        $id_identifier = self::quote_identifier($schema['id']);
        $table_identifier = self::quote_identifier($table);
        $rows          = Simula_Security_Telemetry_Util::db_get_results(
            "SELECT * FROM $table_identifier
                WHERE $id_identifier > " . (int) $last_id . " AND $where_sql
                ORDER BY $id_identifier ASC
                LIMIT " . (int) $limit,
            ARRAY_A
        );

        if ($wpdb->last_error !== '') {
            return self::update_failure_state($state, $options, $wpdb->last_error, $persist_state);
        }

        if (empty($rows)) {
            $state['last_incident_export']        = time();
            $state['last_incident_exported_rows'] = 0;
            $state['last_incident_log_file']      = $options['incident_log_file'];
            $state['last_incident_error']         = '';
            if ($persist_state) {
                update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
            }

            return [
                'ok'      => true,
                'message' => __('No new Wordfence incidents to append.', 'simula-security-telemetry-for-wordfence'),
                'state'   => $state,
            ];
        }

        $lines       = [];
        $max_seen_id = $last_id;

        foreach ((array) $rows as $row) {
            $row_id = isset($row[$schema['id']]) ? (int) $row[$schema['id']] : 0;
            if ($row_id > $max_seen_id) {
                $max_seen_id = $row_id;
            }

            $line = self::row_to_log_line($row, $table, $options, $schema);
            if ($line === null) {
                continue;
            }

            if (!is_string($line) || $line === '') {
                return self::update_failure_state(
                    $state,
                    $options,
                    __('Failed formatting a Wordfence incident log line.', 'simula-security-telemetry-for-wordfence'),
                    $persist_state
                );
            }

            $lines[] = $line . "\n";
        }

        if (empty($lines)) {
            $state['last_incident_id']            = $max_seen_id;
            $state['last_incident_export']        = time();
            $state['last_incident_exported_rows'] = 0;
            $state['last_incident_log_file']      = $options['incident_log_file'];
            $state['last_incident_error']         = '';
            if ($persist_state) {
                update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
            }

            return [
                'ok'      => true,
                'message' => __('No Wordfence incidents were appended after privacy filters.', 'simula-security-telemetry-for-wordfence'),
                'state'   => $state,
            ];
        }

        $write = self::append_log($options['incident_log_file'], implode('', $lines));
        if (!$write['ok']) {
            return self::update_failure_state($state, $options, $write['message'], $persist_state);
        }

        $exported_count = count($lines);
        $state['last_incident_id']            = $max_seen_id;
        $state['last_incident_export']        = time();
        $state['last_incident_exported_rows'] = $exported_count;
        $state['last_incident_log_file']      = $options['incident_log_file'];
        $state['last_incident_error']         = '';
        if ($persist_state) {
            update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
        }

        return [
            'ok'      => true,
            'message' => sprintf(
                /* translators: 1: Number of incident rows appended, 2: Incident log file path. */
                __('Appended %1$d Wordfence incidents to %2$s.', 'simula-security-telemetry-for-wordfence'),
                $exported_count,
                $options['incident_log_file']
            ),
            'state'   => $state,
        ];
    }

    /** Returns a sample incident log line for operator-facing admin UI help text. */
    public static function sample_log_line($options = null) {
        $options = is_array($options) ? $options : Simula_Security_Telemetry_Settings::get_options();

        $event_ts = strtotime('2026-05-23T12:34:56+00:00');
        $context  = [
            'site'        => (string) ($options['site_label'] ?? wp_parse_url(home_url('/'), PHP_URL_HOST)),
            'hostname'    => self::clean_string(function_exists('gethostname') ? gethostname() : ''),
            'blog_id'     => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1,
            'hit_id'      => 123,
            'level'       => 'CRITICAL',
            'ip'          => '203.0.113.10',
            'status'      => 403,
            'action'      => 'blocked:waf',
            'reason'      => 'SQL injection attempt',
            'method'      => 'POST',
            'url'         => '/wp-admin/admin-ajax.php',
            'referer'     => empty($options['privacy_drop_referer']) ? 'https://example.com/' : null,
            'user_agent'  => empty($options['privacy_drop_user_agent']) ? 'curl/8.0' : null,
            'country'     => 'NO',
            'wf_table'    => 'wp_wfHits',
        ];
        $context['ip'] = self::apply_ip_privacy($context['ip'], $options);
        $context['url'] = self::apply_url_privacy($context['url'], $options);
        $context['referer'] = self::apply_url_privacy($context['referer'], $options);
        $retention_note = self::privacy_retention_note($options);
        if ($retention_note !== null) {
            $context['retention_note'] = $retention_note;
        }

        return self::incident_format($options) === 'jsonl'
            ? self::format_json_line($event_ts, $context)
            : self::format_log_line($event_ts, $context);
    }

    /** Resolves the Wordfence hits schema columns used by the incident exporter. */
    private static function resolve_schema($table) {
        $columns = Simula_Security_Telemetry_Wordfence_Schema::table_columns($table);

        return [
            'columns'      => $columns,
            'id'           => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['id']),
            'time'         => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['attackLogTime', 'ctime', 'time']),
            'time_columns' => Simula_Security_Telemetry_Util::resolve_available_candidates($columns, ['attackLogTime', 'ctime', 'time']),
            'status'       => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['statusCode', 'status']),
            'action'       => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['action']),
            'reason'       => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['actionDescription', 'description', 'msg', 'message', 'reason']),
            'method'       => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['method', 'httpMethod', 'requestMethod']),
            'url'          => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['URL', 'url', 'uri', 'requestUri', 'request_uri', 'path']),
            'referer'      => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['referer', 'Referer', 'referrer']),
            'user_agent'   => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['UA', 'user_agent', 'userAgent']),
            'country'      => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['ctry', 'countryCode', 'country']),
            'ip'           => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['IP', 'ip', 'ipaddress', 'ipAddress']),
        ];
    }

    /** Maps a Wordfence hit row into a text or JSON Lines incident log line. */
    private static function row_to_log_line($row, $table, $options, $schema) {
        $event_ts   = self::row_event_timestamp($row, $schema);
        $status     = self::column_value($row, $schema['status']);
        $raw_ip     = self::normalize_ip(self::column_value($row, $schema['ip']));

        if (!empty($options['privacy_exclude_private_ips']) && self::is_private_or_reserved_ip($raw_ip)) {
            return null;
        }

        $url            = self::apply_url_privacy(self::clean_string(self::column_value($row, $schema['url'])), $options);
        $referer        = empty($options['privacy_drop_referer']) ? self::apply_url_privacy(self::clean_string(self::column_value($row, $schema['referer'])), $options) : null;
        $user_agent     = empty($options['privacy_drop_user_agent']) ? self::clean_string(self::column_value($row, $schema['user_agent'])) : null;
        $retention_note = self::privacy_retention_note($options);
        $action       = self::clean_string(self::column_value($row, $schema['action']));
        $reason       = self::clean_string(self::column_value($row, $schema['reason']));
        $context    = [
            'site'       => (string) ($options['site_label'] ?? wp_parse_url(home_url('/'), PHP_URL_HOST)),
            'hostname'   => self::clean_string(function_exists('gethostname') ? gethostname() : ''),
            'blog_id'    => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1,
            'hit_id'     => isset($schema['id'], $row[$schema['id']]) ? (int) $row[$schema['id']] : null,
            'level'      => self::incident_log_level($status, $action, $reason, $url),
            'ip'         => self::apply_ip_privacy($raw_ip, $options),
            'status'     => is_numeric($status) ? (int) $status : self::clean_string($status),
            'action'     => $action,
            'reason'     => $reason,
            'method'     => self::clean_string(self::column_value($row, $schema['method'])),
            'url'        => $url,
            'referer'    => $referer,
            'user_agent' => $user_agent,
            'country'    => self::clean_string(self::column_value($row, $schema['country'])),
            'wf_table'   => self::clean_string($table),
        ];

        if ($retention_note !== null) {
            $context['retention_note'] = $retention_note;
        }

        return self::incident_format($options) === 'jsonl'
            ? self::format_json_line($event_ts, $context)
            : self::format_log_line($event_ts, $context);
    }

    /** Formats one incident as a PHP-style log line with a UTC timestamp prefix. */
    private static function format_log_line($event_ts, $context) {
        $parts = [];
        $level = self::normalize_log_level($context['level'] ?? null);
        unset($context['level']);

        foreach ((array) $context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = self::format_log_field($key, $value);
        }

        $message = 'Wordfence blocked request';
        if (!empty($parts)) {
            $message .= ': ' . implode(' ', $parts);
        }

        return sprintf('[%s UTC] %s %s', gmdate('d-M-Y H:i:s', (int) $event_ts), $level, $message);
    }

    /** Formats one incident as a JSON Lines event. */
    private static function format_json_line($event_ts, $context) {
        $event = ['timestamp' => gmdate('c', (int) $event_ts)];

        foreach ((array) $context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $event[$key] = $value;
        }

        $json = wp_json_encode($event, JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }

    /** Returns the configured incident output format. */
    private static function incident_format($options) {
        $format = isset($options['incident_log_format']) ? (string) $options['incident_log_format'] : 'text';

        return $format === 'jsonl' ? 'jsonl' : 'text';
    }

    /** Returns the best available source timestamp for a Wordfence hit row. */
    private static function row_event_timestamp($row, $schema) {
        $columns = !empty($schema['time_columns']) && is_array($schema['time_columns'])
            ? $schema['time_columns']
            : [$schema['time'] ?? null];

        foreach ($columns as $column) {
            $timestamp = self::normalize_event_timestamp(self::column_value($row, $column));
            if ($timestamp > 0) {
                return $timestamp;
            }
        }

        return time();
    }

    /** Normalizes Wordfence timestamp column values into Unix seconds. */
    private static function normalize_event_timestamp($value) {
        if (!is_scalar($value)) {
            return 0;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            $timestamp = (float) $value;
            if ($timestamp <= 0) {
                return 0;
            }

            while ($timestamp > 9999999999) {
                $timestamp /= 1000;
            }

            return (int) $timestamp;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? 0 : max(0, (int) $timestamp);
    }

    /** Assigns a bounded log level to a Wordfence incident. */
    private static function incident_log_level($status, $action, $reason, $url) {
        $haystack = strtolower(implode(' ', array_filter([
            is_scalar($status) ? (string) $status : '',
            is_scalar($action) ? (string) $action : '',
            is_scalar($reason) ? (string) $reason : '',
            is_scalar($url) ? (string) $url : '',
        ], 'strlen')));

        if (preg_match('/\b(sql|xss|rce|xxe|lfi|rfi)\b|injection|cross[- ]site|remote code|command execution|path traversal|directory traversal|file upload|web shell|shell upload|backdoor|malware|exploit/i', $haystack)) {
            return 'CRITICAL';
        }

        if (preg_match('/loginfail|login failure|failed login|invalid username|incorrect password|throttle|throttled|rate[-_ ]?limit|xmlrpc|xml-rpc|brute force|brute-force|wp-login\.php/i', $haystack)) {
            return 'INFO';
        }

        return 'WARN';
    }

    /** Returns one of the supported incident log levels. */
    private static function normalize_log_level($level) {
        $level = strtoupper((string) $level);

        return in_array($level, ['INFO', 'WARN', 'CRITICAL'], true) ? $level : 'WARN';
    }

    /** Formats one context field in key=value form while quoting free-text values. */
    private static function format_log_field($key, $value) {
        if (is_int($value) || is_float($value)) {
            return sprintf('%s=%s', $key, $value);
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);

        return sprintf('%s="%s"', $key, $escaped);
    }

    /** Returns a row value only when the resolved column is present. */
    private static function column_value($row, $column) {
        if ($column === null || !array_key_exists($column, $row)) {
            return null;
        }

        return $row[$column];
    }

    /** Appends content to the incident log using the WordPress filesystem API. */
    private static function append_log($file, $content) {
        $directory  = dirname($file);
        $filesystem = Simula_Security_Telemetry_Util::filesystem();

        if ($filesystem === null) {
            return [
                'ok'      => false,
                'message' => __('Could not initialize the WordPress filesystem API.', 'simula-security-telemetry-for-wordfence'),
            ];
        }

        if (!$filesystem->is_dir($directory)) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: Incident log directory path. */
                    __('Incident log directory does not exist: %s', 'simula-security-telemetry-for-wordfence'),
                    $directory
                ),
            ];
        }

        if (!$filesystem->is_writable($directory) && !($filesystem->exists($file) && $filesystem->is_writable($file))) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: Incident log file path. */
                    __('Incident log path is not writable by PHP: %s', 'simula-security-telemetry-for-wordfence'),
                    $file
                ),
            ];
        }

        $existing = $filesystem->exists($file) ? $filesystem->get_contents($file) : '';
        if ($existing === false) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: Incident log file path. */
                    __('Could not read the incident log for append: %s', 'simula-security-telemetry-for-wordfence'),
                    $file
                ),
            ];
        }

        if (!$filesystem->put_contents($file, $existing . $content, FS_CHMOD_FILE)) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: Incident log file path. */
                    __('Failed appending the incident log: %s', 'simula-security-telemetry-for-wordfence'),
                    $file
                ),
            ];
        }

        return [
            'ok'      => true,
            'message' => __('Incident log appended.', 'simula-security-telemetry-for-wordfence'),
        ];
    }

    /** Updates incident-specific failure state and returns a normalized error result. */
    private static function update_failure_state($state, $options, $message, $persist_state = true) {
        $state                           = is_array($state) ? $state : [];
        $state['last_incident_export']   = time();
        $state['last_incident_exported_rows'] = 0;
        $state['last_incident_log_file'] = $options['incident_log_file'] ?? '';
        $state['last_incident_error']    = (string) $message;
        if ($persist_state) {
            update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
        }

        return [
            'ok'      => false,
            'message' => (string) $message,
            'state'   => $state,
        ];
    }

    /** Applies the configured IP privacy mode to an incident IP field. */
    private static function apply_ip_privacy($ip, $options) {
        $ip = self::clean_string($ip);
        if ($ip === null) {
            return null;
        }

        $mode = isset($options['privacy_ip_mode']) ? (string) $options['privacy_ip_mode'] : 'full';

        if ($mode === 'drop') {
            return null;
        }

        if ($mode === 'hash') {
            $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_SALT') ? AUTH_SALT : __FILE__);

            return 'sha256:' . hash_hmac('sha256', $ip, $salt);
        }

        if ($mode === 'truncate') {
            return self::truncate_ip($ip);
        }

        return $ip;
    }

    /** Removes query strings from incident URLs when configured. */
    private static function apply_url_privacy($url, $options) {
        $url = self::clean_string($url);
        if ($url === null || empty($options['privacy_drop_url_query'])) {
            return $url;
        }

        $query_pos = strpos($url, '?');
        if ($query_pos === false) {
            return $url;
        }

        $url = substr($url, 0, $query_pos);

        return $url === '' ? null : $url;
    }

    /** Returns the configured retention note when present. */
    private static function privacy_retention_note($options) {
        $note = self::clean_string($options['privacy_retention_note'] ?? '');

        return $note === '' ? null : $note;
    }

    /** Checks whether an IP is private, loopback, link-local, or otherwise reserved. */
    private static function is_private_or_reserved_ip($ip) {
        $ip = self::clean_string($ip);
        if ($ip === null || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** Truncates IPv4 to /24 and IPv6 to /64 for privacy-preserving incident logs. */
    private static function truncate_ip($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return implode('.', array_slice($parts, 0, 3)) . '.0/24';
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if ($packed !== false && strlen($packed) === 16) {
                $hex = bin2hex(substr($packed, 0, 8));

                return sprintf(
                    '%s:%s:%s:%s::/64',
                    substr($hex, 0, 4),
                    substr($hex, 4, 4),
                    substr($hex, 8, 4),
                    substr($hex, 12, 4)
                );
            }
        }

        return $ip;
    }

    /** Normalizes a scalar value into a safe plain-text log field. */
    private static function clean_string($value) {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = self::normalize_binary_string($value);
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value);
        $value = trim(is_string($value) ? $value : '');

        return $value === '' ? null : $value;
    }

    /** Normalizes stored IP values from plain text, packed binary, or numeric IPv4 forms. */
    private static function normalize_ip($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $plain = self::normalize_binary_string($value);

            if (ctype_digit($plain)) {
                $numeric_ip = (float) $plain;
                if ($numeric_ip >= 0 && $numeric_ip <= 4294967295) {
                    return (string) long2ip((int) $numeric_ip);
                }
            }

            if (filter_var($plain, FILTER_VALIDATE_IP)) {
                return $plain;
            }

            $length = strlen($value);
            if ($length === 4 || $length === 16) {
                $decoded = @inet_ntop($value);
                if ($decoded !== false) {
                    return $decoded;
                }
            }

            return self::clean_string($plain);
        }

        if (is_scalar($value)) {
            return self::clean_string((string) $value);
        }

        return null;
    }

    /** Converts invalid UTF-8 strings into a stable hex representation. */
    private static function normalize_binary_string($value) {
        if (!is_string($value)) {
            return $value;
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            $hex = bin2hex($value);
            return $hex === '' ? '' : 'hex:' . $hex;
        }

        return $value;
    }

    /** Escapes a database identifier for use in dynamic incident queries. */
    private static function quote_identifier($identifier) {
        return Simula_Security_Telemetry_Util::quote_identifier($identifier);
    }
}

final class Simula_Security_Telemetry_Service {
    /** Orchestrates a full metrics and incident export. */
    public static function export($force = false) {
        return self::export_all($force);
    }

    /** Orchestrates a full metrics and incident export. */
    public static function export_all($force = false) {
        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();

        if (empty($options['enabled'])) {
            $message = $force
                ? __('Exporter is disabled. Enable the exporter to run both metrics and incident log exports.', 'simula-security-telemetry-for-wordfence')
                : __('Export disabled.', 'simula-security-telemetry-for-wordfence');

            return Simula_Security_Telemetry_Output::write_disabled_metrics($options, $state, $message);
        }

        $metric_result   = self::export_metrics($options, $state, 'all', false);
        $incident_result = Simula_Security_Telemetry_Incidents::export(
            $options,
            self::result_state($metric_result, $state),
            false
        );

        return self::merge_results($metric_result, $incident_result, $state);
    }

    /** Runs the fast collector and incident export using cached slow data. */
    public static function export_fast($force = false) {
        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();

        if (empty($options['enabled'])) {
            $message = $force
                ? __('Exporter is disabled. Enable the exporter to run both metrics and incident log exports.', 'simula-security-telemetry-for-wordfence')
                : __('Export disabled.', 'simula-security-telemetry-for-wordfence');

            return Simula_Security_Telemetry_Output::write_disabled_metrics($options, $state, $message);
        }

        $metric_result   = self::export_metrics($options, $state, 'fast', false);
        $incident_result = Simula_Security_Telemetry_Incidents::export(
            $options,
            self::result_state($metric_result, $state),
            false
        );

        return self::merge_results($metric_result, $incident_result, $state);
    }

    /** Runs the slow collector and writes a complete metrics file without appending incidents. */
    public static function export_slow($force = false) {
        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();

        if (empty($options['enabled'])) {
            $message = $force
                ? __('Exporter is disabled. Enable the exporter before running slow metrics export.', 'simula-security-telemetry-for-wordfence')
                : __('Export disabled.', 'simula-security-telemetry-for-wordfence');

            return Simula_Security_Telemetry_Output::write_disabled_metrics($options, $state, $message);
        }

        return self::export_metrics($options, $state, 'slow', true);
    }

    /** Runs only the metrics exporter. */
    public static function export_metrics_only($scope = 'all') {
        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();

        if (empty($options['enabled'])) {
            return Simula_Security_Telemetry_Output::write_disabled_metrics($options, $state, __('Export disabled.', 'simula-security-telemetry-for-wordfence'));
        }

        return self::export_metrics($options, $state, in_array($scope, ['all', 'fast', 'slow'], true) ? $scope : 'all', true);
    }

    /** Runs only the incident exporter. */
    public static function export_incidents_only() {
        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();

        return Simula_Security_Telemetry_Incidents::export($options, $state);
    }

    /** Collects Wordfence data, builds metrics, and writes the Prometheus output file. */
    private static function export_metrics($options, $state, $scope = 'all', $persist_state = true) {
        $now = time();
        $data = self::collect_metric_export_data($options, $state, $now, $scope);
        if (empty($data['ok'])) {
            return self::write_metric_failure($options, $state, $now, $data['message'] ?? '', $persist_state);
        }

        $metrics = self::build_metric_output_lines($options, $now, $data);

        return self::persist_metric_export($options, $state, $now, $data, $metrics, $persist_state);
    }

    /** Collects all source data required to render a metrics export run. */
    private static function collect_metric_export_data($options, $state, $now, $scope = 'all') {
        global $wpdb;

        $table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_hits_table();
        if (!Simula_Security_Telemetry_Wordfence_Schema::table_exists($table)) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: Comma-separated list of Wordfence table names that were checked. */
                    __('Wordfence table not found. Tried: %s', 'simula-security-telemetry-for-wordfence'),
                    implode(', ', Simula_Security_Telemetry_Wordfence_Schema::wordfence_table_candidates('wfHits'))
                ),
            ];
        }

        $id_column   = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['id']);
        $time_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['attackLogTime', 'ctime', 'time']);
        $where_sql   = Simula_Security_Telemetry_Wordfence_Collector::blocked_where_sql($table);

        if ($id_column === null || $time_column === null || $where_sql === '0=1') {
            return [
                'ok'      => false,
                'message' => __('Unsupported Wordfence hits schema.', 'simula-security-telemetry-for-wordfence'),
            ];
        }

        $flags = self::metric_export_flags($options);
        $data  = [
            'ok'                 => true,
            'table'              => $table,
            'time_identifier'    => Simula_Security_Telemetry_Util::quote_identifier($time_column),
            'where_sql'          => $where_sql,
            'last_id'            => isset($state['last_id']) ? (int) $state['last_id'] : 0,
            'blocked_total'      => isset($state['blocked_total']) ? (float) $state['blocked_total'] : 0.0,
            'windows'            => self::window_timestamps($now),
            'site'               => Simula_Security_Telemetry_Output::escape_label($options['site_label']),
            'prefix'             => $options['metric_prefix'],
            'scope'              => $scope,
            'flags'              => $flags,
            'window_counts'      => [],
            'status_counts'      => [],
            'top_attack_sources' => [],
            'lockout_counts'     => [],
            'two_factor_metrics' => [],
            'scan_issue_metrics' => [],
            'source_freshness'   => [],
            'wordfence_posture'  => [],
            'wordpress_posture'  => [],
        ];

        if ($flags['blocked_events_total']) {
            $table_identifier = Simula_Security_Telemetry_Util::quote_identifier($table);
            $id_identifier = Simula_Security_Telemetry_Util::quote_identifier($id_column);
            $incremental = Simula_Security_Telemetry_Util::db_get_row(
                "SELECT COALESCE(MAX($id_identifier), 0) AS max_id, COUNT(*) AS new_count
                    FROM $table_identifier
                    WHERE $id_identifier > " . (int) $data['last_id'] . " AND $where_sql",
                ARRAY_A
            );

            $max_id = isset($incremental['max_id']) ? (int) $incremental['max_id'] : $data['last_id'];
            if ($max_id > $data['last_id']) {
                $data['blocked_total'] += isset($incremental['new_count']) ? (int) $incremental['new_count'] : 0;
                $data['last_id'] = $max_id;
            }
        }

        if ($flags['needs_window_counts']) {
            $data['window_counts'] = self::collect_window_counts($table, $data['time_identifier'], $where_sql, $data['windows'], $flags);
        }

        if ($flags['blocked_events_by_status_24h']) {
            $data['status_counts'] = self::collect_status_counts($table, $data['time_identifier'], $where_sql, $data['windows']);
        }

        if ($flags['top_attack_sources_24h']) {
            $data['top_attack_sources'] = Simula_Security_Telemetry_Wordfence_Collector::collect_top_attack_sources(
                $table,
                $data['time_identifier'],
                $where_sql,
                $data['windows']['24h']
            );
        }

        if ($flags['locked_out_total']) {
            $data['lockout_counts'] = Simula_Security_Telemetry_Wordfence_Collector::collect_lockout_counts($now);
        }

        if ($flags['needs_source_freshness']) {
            $data['source_freshness'] = Simula_Security_Telemetry_Wordfence_Collector::collect_source_freshness(
                $table,
                $data['time_identifier'],
                $where_sql,
                $now
            );
        }

        if ($scope === 'fast') {
            $data = self::apply_cached_slow_metrics($data, $state);
        } else {
            if ($flags['needs_two_factor_metrics']) {
                $data['two_factor_metrics'] = Simula_Security_Telemetry_Wordfence_Collector::collect_two_factor_metrics();
            }

            if ($flags['needs_scan_metrics']) {
                $data['scan_issue_metrics'] = Simula_Security_Telemetry_Wordfence_Collector::collect_scan_issue_metrics();
            }

            if ($flags['needs_wordfence_posture']) {
                $data['wordfence_posture'] = Simula_Security_Telemetry_Wordfence_Collector::collect_wordfence_posture();
            }

            if ($flags['needs_wordpress_posture']) {
                $data['wordpress_posture'] = Simula_Security_Telemetry_Wordfence_Collector::collect_wordpress_posture();
            }
        }

        if ($wpdb->last_error !== '') {
            return [
                'ok'      => false,
                'message' => $wpdb->last_error,
            ];
        }

        return $data;
    }

    /** Returns which metric families are enabled and which grouped collectors are needed. */
    private static function metric_export_flags($options) {
        $flags = [];

        foreach (array_keys(Simula_Security_Telemetry_Config::metric_definitions()) as $metric_key) {
            $flags[$metric_key] = Simula_Security_Telemetry_Settings::is_metric_enabled($options, $metric_key);
        }

        $flags['needs_window_counts'] =
            $flags['blocked_events_window'] ||
            $flags['failed_login_attempts_window'] ||
            $flags['rate_limited_events_window'] ||
            $flags['brute_force_events_window'];
        $flags['needs_scan_metrics'] =
            $flags['scan_issues_by_severity'] ||
            $flags['scan_findings_total'] ||
            $flags['vulnerability_findings_total'];
        $flags['needs_two_factor_metrics'] =
            $flags['two_factor_enabled'] ||
            $flags['two_factor_protected_users_total'];
        $flags['needs_source_freshness'] =
            $flags['latest_hit_timestamp_seconds'] ||
            $flags['latest_blocked_hit_timestamp_seconds'] ||
            $flags['latest_scan_timestamp_seconds'] ||
            $flags['scan_age_seconds'];
        $flags['needs_wordfence_posture'] =
            $flags['installed'] ||
            $flags['version_info'] ||
            $flags['firewall_enabled'] ||
            $flags['firewall_optimized'] ||
            $flags['live_traffic_enabled'] ||
            $flags['scan_enabled'] ||
            $flags['license_type'];
        $flags['needs_wordpress_posture'] =
            $flags['core_update_available'] ||
            $flags['plugin_update_available_total'] ||
            $flags['theme_update_available_total'] ||
            $flags['admin_users_total'] ||
            $flags['admin_users_without_2fa_total'];

        return $flags;
    }

    /** Collects windowed counts for the enabled recent-activity metric families. */
    private static function collect_window_counts($table, $time_identifier, $where_sql, $windows, $flags) {
        global $wpdb;

        $window_selects = [];
        $table_identifier = Simula_Security_Telemetry_Util::quote_identifier($table);

        if (!empty($flags['blocked_events_window'])) {
            $window_selects[] = Simula_Security_Telemetry_Wordfence_Collector::build_window_count_select_sql('blocked', $where_sql, $time_identifier, $windows);
        }

        if (!empty($flags['failed_login_attempts_window'])) {
            $window_selects[] = Simula_Security_Telemetry_Wordfence_Collector::build_window_count_select_sql(
                'failed_login',
                Simula_Security_Telemetry_Wordfence_Collector::failed_login_where_sql($table),
                $time_identifier,
                $windows
            );
        }

        if (!empty($flags['rate_limited_events_window'])) {
            $window_selects[] = Simula_Security_Telemetry_Wordfence_Collector::build_window_count_select_sql(
                'rate_limited',
                Simula_Security_Telemetry_Wordfence_Collector::rate_limited_where_sql($table),
                $time_identifier,
                $windows
            );
        }

        if (!empty($flags['brute_force_events_window'])) {
            $window_selects[] = Simula_Security_Telemetry_Wordfence_Collector::build_window_count_select_sql(
                'brute_username',
                Simula_Security_Telemetry_Wordfence_Collector::brute_force_username_where_sql($table),
                $time_identifier,
                $windows
            );
            $window_selects[] = Simula_Security_Telemetry_Wordfence_Collector::build_window_count_select_sql(
                'brute_xmlrpc',
                Simula_Security_Telemetry_Wordfence_Collector::brute_force_xmlrpc_where_sql($table),
                $time_identifier,
                $windows
            );
        }

        return Simula_Security_Telemetry_Util::db_get_row(
            "SELECT
                " . implode(",\n                    ", $window_selects) . "
            FROM $table_identifier
            WHERE $time_identifier >= " . (int) $windows['7d'],
            ARRAY_A
        );
    }

    /** Collects blocked 24h status-code counts when the necessary schema columns are present. */
    private static function collect_status_counts($table, $time_identifier, $where_sql, $windows) {
        global $wpdb;

        $status_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['statusCode', 'status']);
        if ($status_column === null) {
            return [];
        }

        $status_identifier = Simula_Security_Telemetry_Util::quote_identifier($status_column);
        $table_identifier  = Simula_Security_Telemetry_Util::quote_identifier($table);

        return Simula_Security_Telemetry_Util::db_get_results(
            "SELECT $status_identifier AS status_code, COUNT(*) AS count_total
            FROM $table_identifier
            WHERE $time_identifier >= " . (int) $windows['24h'] . " AND $where_sql
            GROUP BY $status_identifier
            ORDER BY count_total DESC",
            ARRAY_A
        );
    }

    /** Builds the Prometheus metric lines for a successful export run. */
    private static function build_metric_output_lines($options, $now, $data) {
        $metrics = [];

        $metrics = array_merge($metrics, self::render_core_export_metrics($options, $now, $data));
        $metrics = array_merge($metrics, self::render_blocked_event_metrics($data));
        $metrics = array_merge($metrics, self::render_activity_window_metrics($data));
        $metrics = array_merge($metrics, self::render_access_control_metrics($data));
        $metrics = array_merge($metrics, self::render_scan_metrics($data));
        $metrics = array_merge($metrics, self::render_source_freshness_metrics($data));
        $metrics = array_merge($metrics, self::render_posture_metrics($data));

        return $metrics;
    }

    /** Renders exporter status and metadata metrics. */
    private static function render_core_export_metrics($options, $now, $data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];

        if (!empty($flags['export_success'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_export_success',
                'gauge',
                'Whether the last Wordfence metrics export succeeded.',
                [
                    ['labels' => ['site' => $site], 'value' => 1],
                ]
            );
        }

        if (!empty($flags['plugin_info'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_plugin_info',
                'gauge',
                'Plugin metadata for the exporter.',
                [
                    ['labels' => ['site' => $site, 'version' => Simula_Security_Telemetry_Output::escape_label(Simula_Security_Telemetry_Config::VERSION)], 'value' => 1],
                ]
            );
        }

        if (!empty($flags['last_export_timestamp_seconds'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_last_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the last successful export.',
                [
                    ['labels' => ['site' => $site], 'value' => $now],
                ]
            );
        }

        if (!empty($flags['enabled'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_enabled',
                'gauge',
                'Whether the exporter master switch is enabled.',
                [
                    ['labels' => ['site' => $site], 'value' => (int) !empty($options['enabled'])],
                ]
            );
        }

        return $metrics;
    }

    /** Renders blocked-request metric families and their related aggregations. */
    private static function render_blocked_event_metrics($data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];

        if (!empty($flags['blocked_events_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_blocked_events_total',
                'counter',
                'Cumulative count of newly observed blocked Wordfence hits.',
                [
                    ['labels' => ['site' => $site], 'value' => $data['blocked_total']],
                ]
            );
        }

        if (!empty($flags['blocked_events_window'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_blocked_events_window',
                'gauge',
                'Blocked Wordfence hits seen within recent windows.',
                self::build_window_metric_samples($site, $data['window_counts'], 'blocked')
            );
        }

        if (!empty($flags['blocked_events_by_status_24h'])) {
            $samples = [];
            foreach ((array) $data['status_counts'] as $row) {
                $status = isset($row['status_code']) && $row['status_code'] !== '' ? (string) $row['status_code'] : 'unknown';
                $count  = isset($row['count_total']) ? (int) $row['count_total'] : 0;
                $samples[] = [
                    'labels' => ['site' => $site, 'status' => Simula_Security_Telemetry_Output::escape_label($status)],
                    'value'  => $count,
                ];
            }

            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_blocked_events_by_status_24h',
                'gauge',
                'Blocked Wordfence hits in the last 24 hours grouped by HTTP status code.',
                $samples
            );
        }

        if (!empty($flags['top_attack_sources_24h'])) {
            $samples = [];
            foreach ((array) $data['top_attack_sources'] as $row) {
                $source_type = isset($row['source_type']) ? (string) $row['source_type'] : 'unknown';
                $source_name = isset($row['source']) ? (string) $row['source'] : 'unknown';
                $count       = isset($row['count_total']) ? (int) $row['count_total'] : 0;
                $samples[] = [
                    'labels' => [
                        'site'        => $site,
                        'source_type' => Simula_Security_Telemetry_Output::escape_label($source_type),
                        'source'      => Simula_Security_Telemetry_Output::escape_label($source_name),
                    ],
                    'value'  => $count,
                ];
            }

            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_top_attack_sources_24h',
                'gauge',
                'Top blocked attack sources over the last 24 hours.',
                $samples
            );
        }

        return $metrics;
    }

    /** Renders recent-activity metric families derived from windowed counts. */
    private static function render_activity_window_metrics($data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];

        if (!empty($flags['failed_login_attempts_window'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_failed_login_attempts_window',
                'gauge',
                'Failed login attempts observed within recent windows.',
                self::build_window_metric_samples($site, $data['window_counts'], 'failed_login')
            );
        }

        if (!empty($flags['rate_limited_events_window'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_rate_limited_events_window',
                'gauge',
                'Rate-limited or throttled requests observed within recent windows.',
                self::build_window_metric_samples($site, $data['window_counts'], 'rate_limited')
            );
        }

        if (!empty($flags['brute_force_events_window'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_brute_force_events_window',
                'gauge',
                'Brute-force activity observed within recent windows.',
                array_merge(
                    self::build_window_metric_samples($site, $data['window_counts'], 'brute_username', ['vector' => 'username']),
                    self::build_window_metric_samples($site, $data['window_counts'], 'brute_xmlrpc', ['vector' => 'xmlrpc'])
                )
            );
        }

        return $metrics;
    }

    /** Renders lockout and two-factor metric families. */
    private static function render_access_control_metrics($data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];

        if (!empty($flags['locked_out_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_locked_out_total',
                'gauge',
                'Current Wordfence lockout totals by target type when available.',
                [
                    ['labels' => ['site' => $site, 'target' => 'ip'], 'value' => (int) ($data['lockout_counts']['ip'] ?? 0)],
                    ['labels' => ['site' => $site, 'target' => 'user'], 'value' => (int) ($data['lockout_counts']['user'] ?? 0)],
                ]
            );
        }

        if (!empty($flags['two_factor_enabled'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_two_factor_enabled',
                'gauge',
                'Whether Wordfence two-factor authentication appears to be configured.',
                [
                    ['labels' => ['site' => $site], 'value' => (int) ($data['two_factor_metrics']['enabled'] ?? 0)],
                ]
            );
        }

        if (!empty($flags['two_factor_protected_users_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_two_factor_protected_users_total',
                'gauge',
                'Count of users with Wordfence two-factor secrets configured.',
                [
                    ['labels' => ['site' => $site], 'value' => (int) ($data['two_factor_metrics']['protected_users'] ?? 0)],
                ]
            );
        }

        return $metrics;
    }

    /** Renders scan-related metric families. */
    private static function render_scan_metrics($data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];

        if (!empty($flags['scan_issues_by_severity'])) {
            $samples = [];
            foreach ((array) ($data['scan_issue_metrics']['severity'] ?? []) as $row) {
                $severity = isset($row['severity']) && $row['severity'] !== '' ? strtolower((string) $row['severity']) : 'unknown';
                $count    = isset($row['count_total']) ? (int) $row['count_total'] : 0;
                $samples[] = [
                    'labels' => ['site' => $site, 'severity' => Simula_Security_Telemetry_Output::escape_label($severity)],
                    'value'  => $count,
                ];
            }

            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_scan_issues_by_severity',
                'gauge',
                'Current Wordfence scan issues grouped by severity.',
                $samples
            );
        }

        if (!empty($flags['scan_findings_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_scan_findings_total',
                'gauge',
                'Current Wordfence scan findings for selected categories.',
                [
                    ['labels' => ['site' => $site, 'category' => 'malware'], 'value' => (int) ($data['scan_issue_metrics']['malware'] ?? 0)],
                    ['labels' => ['site' => $site, 'category' => 'file_change'], 'value' => (int) ($data['scan_issue_metrics']['file_change'] ?? 0)],
                ]
            );
        }

        if (!empty($flags['vulnerability_findings_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_vulnerability_findings_total',
                'gauge',
                'Current Wordfence scan findings indicating outdated or vulnerable components.',
                [
                    ['labels' => ['site' => $site, 'component' => 'core'], 'value' => (int) ($data['scan_issue_metrics']['vulnerabilities']['core'] ?? 0)],
                    ['labels' => ['site' => $site, 'component' => 'plugin'], 'value' => (int) ($data['scan_issue_metrics']['vulnerabilities']['plugin'] ?? 0)],
                    ['labels' => ['site' => $site, 'component' => 'theme'], 'value' => (int) ($data['scan_issue_metrics']['vulnerabilities']['theme'] ?? 0)],
                ]
            );
        }

        return $metrics;
    }

    /** Renders source freshness metric families. */
    private static function render_source_freshness_metrics($data) {
        $metrics   = [];
        $flags     = $data['flags'];
        $prefix    = $data['prefix'];
        $site      = $data['site'];
        $freshness = is_array($data['source_freshness'] ?? null) ? $data['source_freshness'] : [];

        if (!empty($flags['latest_hit_timestamp_seconds'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_latest_hit_timestamp_seconds',
                'gauge',
                'Unix timestamp of the latest observed Wordfence hit.',
                [
                    ['labels' => ['site' => $site], 'value' => (int) ($freshness['latest_hit'] ?? 0)],
                ]
            );
        }

        if (!empty($flags['latest_blocked_hit_timestamp_seconds'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_latest_blocked_hit_timestamp_seconds',
                'gauge',
                'Unix timestamp of the latest observed blocked Wordfence hit.',
                [
                    ['labels' => ['site' => $site], 'value' => (int) ($freshness['latest_blocked_hit'] ?? 0)],
                ]
            );
        }

        if (!empty($flags['latest_scan_timestamp_seconds'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_latest_scan_timestamp_seconds',
                'gauge',
                'Unix timestamp of the latest observed Wordfence scan issue update.',
                [
                    ['labels' => ['site' => $site], 'value' => (int) ($freshness['latest_scan'] ?? 0)],
                ]
            );
        }

        if (!empty($flags['scan_age_seconds'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_scan_age_seconds',
                'gauge',
                'Age in seconds of the latest observed Wordfence scan issue update.',
                [
                    ['labels' => ['site' => $site], 'value' => (int) ($freshness['scan_age'] ?? 0)],
                ]
            );
        }

        return $metrics;
    }

    /** Renders WordPress and Wordfence posture metric families. */
    private static function render_posture_metrics($data) {
        $metrics             = [];
        $flags               = $data['flags'];
        $prefix              = $data['prefix'];
        $site                = $data['site'];
        $wordfence_posture   = is_array($data['wordfence_posture'] ?? null) ? $data['wordfence_posture'] : [];
        $wordpress_posture   = is_array($data['wordpress_posture'] ?? null) ? $data['wordpress_posture'] : [];
        $wordfence_version   = Simula_Security_Telemetry_Output::escape_label((string) ($wordfence_posture['version'] ?? 'unknown'));
        $wordfence_license   = Simula_Security_Telemetry_Output::escape_label((string) ($wordfence_posture['license_type'] ?? 'unknown'));

        if (!empty($flags['installed'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_installed', 'gauge', 'Whether Wordfence appears installed.', [['labels' => ['site' => $site], 'value' => (int) ($wordfence_posture['installed'] ?? 0)]]);
        }

        if (!empty($flags['version_info'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_version_info', 'gauge', 'Wordfence version metadata.', [['labels' => ['site' => $site, 'version' => $wordfence_version], 'value' => 1]]);
        }

        if (!empty($flags['firewall_enabled'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_firewall_enabled', 'gauge', 'Whether the Wordfence firewall appears enabled.', [['labels' => ['site' => $site], 'value' => (int) ($wordfence_posture['firewall_enabled'] ?? 0)]]);
        }

        if (!empty($flags['firewall_optimized'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_firewall_optimized', 'gauge', 'Whether the Wordfence firewall appears optimized.', [['labels' => ['site' => $site], 'value' => (int) ($wordfence_posture['firewall_optimized'] ?? 0)]]);
        }

        if (!empty($flags['live_traffic_enabled'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_live_traffic_enabled', 'gauge', 'Whether Wordfence live traffic appears enabled.', [['labels' => ['site' => $site], 'value' => (int) ($wordfence_posture['live_traffic_enabled'] ?? 0)]]);
        }

        if (!empty($flags['scan_enabled'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_scan_enabled', 'gauge', 'Whether Wordfence scanning appears enabled.', [['labels' => ['site' => $site], 'value' => (int) ($wordfence_posture['scan_enabled'] ?? 0)]]);
        }

        if (!empty($flags['license_type'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_license_type', 'gauge', 'Wordfence license type metadata.', [['labels' => ['site' => $site, 'type' => $wordfence_license], 'value' => 1]]);
        }

        if (!empty($flags['core_update_available'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_core_update_available', 'gauge', 'Whether a WordPress core update is available.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['core_update_available'] ?? 0)]]);
        }

        if (!empty($flags['plugin_update_available_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_plugin_update_available_total', 'gauge', 'Number of plugin updates available.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['plugin_update_available_total'] ?? 0)]]);
        }

        if (!empty($flags['theme_update_available_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_theme_update_available_total', 'gauge', 'Number of theme updates available.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['theme_update_available_total'] ?? 0)]]);
        }

        if (!empty($flags['admin_users_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_admin_users_total', 'gauge', 'Number of WordPress administrator users.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['admin_users_total'] ?? 0)]]);
        }

        if (!empty($flags['admin_users_without_2fa_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_admin_users_without_2fa_total', 'gauge', 'Number of administrator users without Wordfence two-factor secrets.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['admin_users_without_2fa_total'] ?? 0)]]);
        }

        return $metrics;
    }

    /** Builds metric samples for all configured time windows with stable label ordering. */
    private static function build_window_metric_samples($site, $window_counts, $count_prefix, $extra_labels = []) {
        $samples = [];

        foreach (Simula_Security_Telemetry_Config::WINDOWS as $window) {
            $samples[] = [
                'labels' => array_merge(['site' => $site], $extra_labels, ['window' => $window]),
                'value'  => self::window_metric_value($window_counts, $count_prefix, $window),
            ];
        }

        return $samples;
    }

    /** Writes failure metrics for an unsuccessful metrics export attempt. */
    private static function write_metric_failure($options, $state, $now, $message, $persist_state = true) {
        $message = (string) $message;

        return Simula_Security_Telemetry_Output::write_metrics(
            $options['prom_file'],
            Simula_Security_Telemetry_Output::build_failure_metrics($options, $now, $message),
            $message,
            $state,
            $persist_state
        );
    }

    /** Applies final state updates and writes the rendered metrics file. */
    private static function persist_metric_export($options, $state, $now, $data, $metrics, $persist_state = true) {
        $state['blocked_total'] = $data['blocked_total'];
        $state['last_export']   = $now;
        $state['last_id']       = $data['last_id'];

        if (($data['scope'] ?? 'all') !== 'fast') {
            $state['slow_metric_cache'] = self::slow_metric_cache_from_data($data);
            $state['slow_metric_cache_at'] = $now;
        }

        return Simula_Security_Telemetry_Output::write_metrics(
            $options['prom_file'],
            empty($metrics) ? '' : implode("\n", $metrics) . "\n",
            '',
            $state,
            $persist_state
        );
    }

    /** Copies cached slow collector data into a fast export payload. */
    private static function apply_cached_slow_metrics($data, $state) {
        $cache = isset($state['slow_metric_cache']) && is_array($state['slow_metric_cache']) ? $state['slow_metric_cache'] : [];

        foreach (['two_factor_metrics', 'scan_issue_metrics', 'wordfence_posture', 'wordpress_posture'] as $key) {
            if (isset($cache[$key]) && is_array($cache[$key])) {
                $data[$key] = $cache[$key];
            }
        }

        if (isset($cache['source_freshness']) && is_array($cache['source_freshness'])) {
            $cached_freshness = $cache['source_freshness'];
            foreach (['latest_scan', 'scan_age'] as $key) {
                if (array_key_exists($key, $cached_freshness)) {
                    $data['source_freshness'][$key] = $cached_freshness[$key];
                }
            }
        }

        return $data;
    }

    /** Extracts slow collector data suitable for caching in plugin state. */
    private static function slow_metric_cache_from_data($data) {
        return [
            'two_factor_metrics' => is_array($data['two_factor_metrics'] ?? null) ? $data['two_factor_metrics'] : [],
            'scan_issue_metrics' => is_array($data['scan_issue_metrics'] ?? null) ? $data['scan_issue_metrics'] : [],
            'source_freshness'   => is_array($data['source_freshness'] ?? null) ? [
                'latest_scan' => (int) ($data['source_freshness']['latest_scan'] ?? 0),
                'scan_age'    => (int) ($data['source_freshness']['scan_age'] ?? 0),
            ] : [],
            'wordfence_posture' => is_array($data['wordfence_posture'] ?? null) ? $data['wordfence_posture'] : [],
            'wordpress_posture' => is_array($data['wordpress_posture'] ?? null) ? $data['wordpress_posture'] : [],
        ];
    }

    /** Persists and returns the combined result from the metrics and incident exporters. */
    private static function merge_results($metric_result, $incident_result, $state = []) {
        $metric_result   = is_array($metric_result) ? $metric_result : [];
        $incident_result = is_array($incident_result) ? $incident_result : [];
        $ok              = !empty($metric_result['ok']) && !empty($incident_result['ok']);
        $messages        = array_filter([
            isset($metric_result['message']) ? (string) $metric_result['message'] : '',
            isset($incident_result['message']) ? (string) $incident_result['message'] : '',
        ]);
        $message         = $messages === []
            ? __('Export completed.', 'simula-security-telemetry-for-wordfence')
            : implode(' ', $messages);
        $errors          = array_filter([
            empty($metric_result['ok']) && !empty($metric_result['message']) ? (string) $metric_result['message'] : '',
            empty($incident_result['ok']) && !empty($incident_result['message']) ? (string) $incident_result['message'] : '',
        ]);
        $state           = self::merge_state(
            self::result_state($metric_result, $state),
            self::result_state($incident_result)
        );

        $state['last_result']    = $message;
        $state['last_result_ok'] = $ok ? 1 : 0;
        $state['last_error']     = $ok ? '' : implode(' ', $errors);
        update_option(Simula_Security_Telemetry_Config::STATE, $state, false);

        return [
            'ok'      => $ok,
            'message' => $message,
        ];
    }

    /** Returns the state payload emitted by an export result, or a provided fallback state. */
    private static function result_state($result, $fallback_state = []) {
        if (isset($result['state']) && is_array($result['state'])) {
            return $result['state'];
        }

        return is_array($fallback_state) ? $fallback_state : [];
    }

    /** Merges one state array onto another using later values as the source of truth. */
    private static function merge_state($base_state, $updated_state) {
        return array_merge(
            is_array($base_state) ? $base_state : [],
            is_array($updated_state) ? $updated_state : []
        );
    }

    /** Returns the cutoff timestamps for each configured reporting window. */
    private static function window_timestamps($now) {
        return [
            '5m'  => $now - (5 * MINUTE_IN_SECONDS),
            '1h'  => $now - HOUR_IN_SECONDS,
            '24h' => $now - DAY_IN_SECONDS,
            '7d'  => $now - (7 * DAY_IN_SECONDS),
        ];
    }

    /** Reads a windowed metric count from a query result row. */
    private static function window_metric_value($data, $prefix, $window) {
        $key = $prefix . '_count_' . $window;

        return isset($data[$key]) ? (int) $data[$key] : 0;
    }
}

final class Simula_Security_Telemetry_Admin {
    /** Registers the plugin settings page under the WordPress Settings menu. */
    public static function admin_menu() {
        add_options_page(
            __('Simula Wordfence Grafana Metrics', 'simula-security-telemetry-for-wordfence'),
            __('Wordfence Metrics', 'simula-security-telemetry-for-wordfence'),
            Simula_Security_Telemetry_Config::CAPABILITY,
            Simula_Security_Telemetry_Config::SLUG,
            [__CLASS__, 'settings_page']
        );
    }

    /** Adds a Settings shortcut link on the plugins screen. */
    public static function plugin_action_links($links) {
        $url = admin_url('options-general.php?page=' . Simula_Security_Telemetry_Config::SLUG);

        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url($url),
                esc_html__('Settings', 'simula-security-telemetry-for-wordfence')
            )
        );

        return $links;
    }

    /** Renders the settings page and handles manual export requests. */
    public static function settings_page() {
        if (!current_user_can(Simula_Security_Telemetry_Config::CAPABILITY)) {
            return;
        }

        self::handle_settings_page_actions();

        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Simula Wordfence Grafana Metrics', 'simula-security-telemetry-for-wordfence'); ?></h1>
            <p><?php echo esc_html__('Exports Wordfence block telemetry into a Prometheus .prom file for node_exporter textfile collection and blocked-request events into a plain-text incident log.', 'simula-security-telemetry-for-wordfence'); ?></p>

            <?php settings_errors('sstfw_metrics'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('sstfw_metrics'); ?>
                <?php self::render_metrics_settings_section($options); ?>
                <?php self::render_incident_settings_section($options); ?>
                <?php submit_button(); ?>
            </form>

            <hr />

            <?php self::render_manual_actions_section(); ?>
            <?php self::render_current_state_section($options, $state); ?>
            <?php self::render_sample_incident_section($options); ?>
        </div>
        <?php
    }

    /** Handles manual export and cursor reset actions from the settings page. */
    private static function handle_settings_page_actions() {
        if (isset($_POST['sstfw_export_now'])) {
            check_admin_referer('sstfw_export_now');
            $result = Simula_Security_Telemetry_Service::export(true);
            add_settings_error(
                'sstfw_metrics',
                'sstfw-export-now',
                $result['message'],
                $result['ok'] ? 'updated' : 'error'
            );
        }

        if (isset($_POST['sstfw_reset_incident_cursor'])) {
            check_admin_referer('sstfw_reset_incident_cursor');
            Simula_Security_Telemetry_Incidents::reset_cursor();
            add_settings_error(
                'sstfw_metrics',
                'sstfw-reset-incident-cursor',
                __('Incident cursor reset to 0. The next export can backfill retained Wordfence incidents up to the configured row limit.', 'simula-security-telemetry-for-wordfence'),
                'updated'
            );
        }
    }

    /** Renders the Prometheus exporter settings section. */
    private static function render_metrics_settings_section($options) {
        ?>
        <h2><?php echo esc_html__('Prometheus metrics', 'simula-security-telemetry-for-wordfence'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Enable exporter', 'simula-security-telemetry-for-wordfence'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[enabled]" value="1" <?php checked($options['enabled'], 1); ?> />
                        <?php echo esc_html__('Master switch for the exporter. When disabled, both Prometheus metrics and incident log exports are off.', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-cron-interval"><?php echo esc_html__('Cron interval', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <select id="sstfw-cron-interval" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[cron_interval]">
                        <?php foreach (Simula_Security_Telemetry_Metrics::cron_interval_labels() as $interval_key => $interval_label) : ?>
                            <option value="<?php echo esc_attr($interval_key); ?>" <?php selected($options['cron_interval'], $interval_key); ?>>
                                <?php echo esc_html($interval_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Controls how often WP-Cron runs exports while the exporter is enabled.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-slow-cron-interval"><?php echo esc_html__('Slow collector interval', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <select id="sstfw-slow-cron-interval" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[slow_cron_interval]">
                        <?php foreach (Simula_Security_Telemetry_Metrics::slow_cron_interval_labels() as $interval_key => $interval_label) : ?>
                            <option value="<?php echo esc_attr($interval_key); ?>" <?php selected($options['slow_cron_interval'], $interval_key); ?>>
                                <?php echo esc_html($interval_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Refreshes slow-changing scan, two-factor, WordPress posture, and Wordfence posture metrics.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-prom-file"><?php echo esc_html__('Prometheus file path', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-prom-file" class="regular-text code" type="text" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[prom_file]" value="<?php echo esc_attr($options['prom_file']); ?>" />
                    <p class="description"><?php echo esc_html__('Example: /var/lib/node_exporter/textfile_collector/wordfence.prom. The directory must already exist and be writable by PHP.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-metric-prefix"><?php echo esc_html__('Metric prefix', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-metric-prefix" class="regular-text code" type="text" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[metric_prefix]" value="<?php echo esc_attr($options['metric_prefix']); ?>" />
                    <p class="description"><?php echo esc_html__('Prometheus metric prefix. Invalid characters are replaced automatically.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-site-label"><?php echo esc_html__('Site label', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-site-label" class="regular-text" type="text" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[site_label]" value="<?php echo esc_attr($options['site_label']); ?>" />
                    <p class="description"><?php echo esc_html__('Added to every exported metric as the site label value.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Exported metrics', 'simula-security-telemetry-for-wordfence'); ?></th>
                <td>
                    <fieldset>
                        <?php foreach (Simula_Security_Telemetry_Config::metric_definitions() as $metric_key => $metric_definition) : ?>
                            <label style="display:block; margin-bottom:12px;">
                                <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[enabled_metrics][<?php echo esc_attr($metric_key); ?>]" value="1" <?php checked(!empty($options['enabled_metrics'][$metric_key])); ?> />
                                <strong><code><?php echo esc_html($options['metric_prefix'] . '_' . $metric_key); ?></code></strong>
                                <?php echo esc_html($metric_definition['label']); ?>
                                <br />
                                <span class="description"><?php echo esc_html($metric_definition['description']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </td>
            </tr>
        </table>
        <?php
    }

    /** Renders the incident log settings section. */
    private static function render_incident_settings_section($options) {
        ?>
        <h2><?php echo esc_html__('Incident log', 'simula-security-telemetry-for-wordfence'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Enable incident log export', 'simula-security-telemetry-for-wordfence'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[incident_log_enabled]" value="1" <?php checked($options['incident_log_enabled'], 1); ?> />
                        <?php echo esc_html__('Append blocked Wordfence hits to the incident log on each export run. This runs only while the exporter is enabled.', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-incident-log-file"><?php echo esc_html__('Incident log path', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-incident-log-file" class="regular-text code" type="text" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[incident_log_file]" value="<?php echo esc_attr($options['incident_log_file']); ?>" />
                    <p class="description"><?php echo esc_html__('Use an absolute log file path. A .log suffix is recommended; existing .jsonl paths are still accepted. The directory must already exist and be writable by PHP.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-incident-log-format"><?php echo esc_html__('Incident log format', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <select id="sstfw-incident-log-format" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[incident_log_format]">
                        <option value="text" <?php selected($options['incident_log_format'], 'text'); ?>><?php echo esc_html__('Text', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="jsonl" <?php selected($options['incident_log_format'], 'jsonl'); ?>><?php echo esc_html__('JSON Lines', 'simula-security-telemetry-for-wordfence'); ?></option>
                    </select>
                    <p class="description"><?php echo esc_html__('Text preserves the v1 log format. JSON Lines emits one structured JSON object per blocked event for Loki, ELK, and OpenSearch pipelines.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-incident-max-rows"><?php echo esc_html__('Max incidents per run', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-incident-max-rows" class="small-text" type="number" min="1" max="10000" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[incident_max_rows]" value="<?php echo esc_attr((string) $options['incident_max_rows']); ?>" />
                    <p class="description"><?php echo esc_html__('Caps each export pass so large retained Wordfence hit tables do not create long-running admin or cron requests.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-privacy-ip-mode"><?php echo esc_html__('Incident IP privacy', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <select id="sstfw-privacy-ip-mode" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_ip_mode]">
                        <option value="full" <?php selected($options['privacy_ip_mode'], 'full'); ?>><?php echo esc_html__('Log full IP address', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="truncate" <?php selected($options['privacy_ip_mode'], 'truncate'); ?>><?php echo esc_html__('Truncate to IPv4 /24 or IPv6 /64', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="hash" <?php selected($options['privacy_ip_mode'], 'hash'); ?>><?php echo esc_html__('Hash with site salt', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="drop" <?php selected($options['privacy_ip_mode'], 'drop'); ?>><?php echo esc_html__('Drop IP field', 'simula-security-telemetry-for-wordfence'); ?></option>
                    </select>
                    <p class="description"><?php echo esc_html__('Controls how IP addresses are written to text and JSON Lines incident logs. Prometheus top-source metrics already use normalized IP ranges.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Incident privacy filters', 'simula-security-telemetry-for-wordfence'); ?></th>
                <td>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_drop_url_query]" value="1" <?php checked($options['privacy_drop_url_query'], 1); ?> />
                        <?php echo esc_html__('Drop query strings from logged URLs', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_drop_referer]" value="1" <?php checked($options['privacy_drop_referer'], 1); ?> />
                        <?php echo esc_html__('Drop referer fields from incident logs', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_drop_user_agent]" value="1" <?php checked($options['privacy_drop_user_agent'], 1); ?> />
                        <?php echo esc_html__('Drop user-agent fields from incident logs', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                    <label style="display:block;">
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_exclude_private_ips]" value="1" <?php checked($options['privacy_exclude_private_ips'], 1); ?> />
                        <?php echo esc_html__('Do not append incidents from private, loopback, link-local, or reserved IP ranges', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-privacy-retention-note"><?php echo esc_html__('Retention note', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <textarea id="sstfw-privacy-retention-note" class="large-text" rows="2" maxlength="200" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_retention_note]"><?php echo esc_textarea($options['privacy_retention_note']); ?></textarea>
                    <p class="description"><?php echo esc_html__('Optional note appended to each incident event so downstream log users can see the local retention expectation. Keep operational retention enforcement in your log pipeline.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /** Renders manual exporter actions below the settings form. */
    private static function render_manual_actions_section() {
        ?>
        <form method="post" style="display:inline-block; margin-right: 12px;">
            <?php wp_nonce_field('sstfw_export_now'); ?>
            <?php submit_button(__('Export now', 'simula-security-telemetry-for-wordfence'), 'secondary', 'sstfw_export_now'); ?>
        </form>
        <p class="description"><?php echo esc_html__('Manual export uses the same master exporter toggle. If the exporter is disabled, the button writes disabled metrics and reports that exports are off.', 'simula-security-telemetry-for-wordfence'); ?></p>

        <form method="post" style="display:inline-block;">
            <?php wp_nonce_field('sstfw_reset_incident_cursor'); ?>
            <?php submit_button(__('Reset incident cursor for backfill', 'simula-security-telemetry-for-wordfence'), 'delete', 'sstfw_reset_incident_cursor'); ?>
        </form>
        <?php
    }

    /** Renders the current exporter state table. */
    private static function render_current_state_section($options, $state) {
        ?>
        <h2><?php echo esc_html__('Current state', 'simula-security-telemetry-for-wordfence'); ?></h2>
        <table class="widefat striped" style="max-width:900px">
            <tbody>
                <?php self::render_state_row(__('Last export timestamp', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::format_state_time($state['last_export'] ?? null)); ?>
                <?php self::render_state_row(__('Observed blocked events', 'simula-security-telemetry-for-wordfence'), (string) ($state['blocked_total'] ?? 0)); ?>
                <?php self::render_state_row(__('Last processed hit ID', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_id'] ?? 0)); ?>
                <?php self::render_state_row(__('Last result', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_result'] ?? __('No exports yet.', 'simula-security-telemetry-for-wordfence'))); ?>
                <?php self::render_state_row(__('Last error', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_error'] ?? '')); ?>
                <?php self::render_state_row(__('Incident cursor initialized', 'simula-security-telemetry-for-wordfence'), !empty($state['incident_cursor_initialized']) ? __('Yes', 'simula-security-telemetry-for-wordfence') : __('No', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Last incident export', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::format_state_time($state['last_incident_export'] ?? null)); ?>
                <?php self::render_state_row(__('Last incident hit ID', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_incident_id'] ?? 0)); ?>
                <?php self::render_state_row(__('Last incident row count', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_incident_exported_rows'] ?? 0)); ?>
                <?php self::render_state_row(__('Last incident log file', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_incident_log_file'] ?? $options['incident_log_file'])); ?>
                <?php self::render_state_row(__('Last incident error', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_incident_error'] ?? '')); ?>
                <?php self::render_state_row(__('Last slow collector refresh', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::format_state_time($state['slow_metric_cache_at'] ?? null)); ?>
            </tbody>
        </table>
        <?php
    }

    /** Renders the sample incident log block. */
    private static function render_sample_incident_section($options) {
        ?>
        <h2><?php echo esc_html__('Sample incident log line', 'simula-security-telemetry-for-wordfence'); ?></h2>
        <pre style="max-width:900px; overflow:auto;"><?php echo esc_html(Simula_Security_Telemetry_Incidents::sample_log_line($options)); ?></pre>
        <?php
    }

    /** Renders one row in the exporter state table. */
    private static function render_state_row($label, $value) {
        ?>
        <tr>
            <td><strong><?php echo esc_html($label); ?></strong></td>
            <td><?php echo esc_html((string) $value); ?></td>
        </tr>
        <?php
    }
}

final class Simula_Security_Telemetry_CLI {
    /** Runs metrics and incident exports, or one side when requested. */
    public function export($args, $assoc_args) {
        $scope = isset($assoc_args['scope']) ? (string) $assoc_args['scope'] : 'all';

        if (!empty($assoc_args['metrics-only'])) {
            $result = Simula_Security_Telemetry_Service::export_metrics_only($scope);
        } elseif (!empty($assoc_args['incidents-only'])) {
            $result = Simula_Security_Telemetry_Service::export_incidents_only();
        } else {
            $result = Simula_Security_Telemetry_Service::export_all(true);
        }

        $this->finish($result);
    }

    /** Resets the incident cursor to 0 for controlled backfill. */
    public function reset_cursor() {
        Simula_Security_Telemetry_Incidents::reset_cursor();
        WP_CLI::success(__('Incident cursor reset to 0.', 'simula-security-telemetry-for-wordfence'));
    }

    /** Displays current exporter status. */
    public function status() {
        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();
        $rows    = [
            ['field' => 'enabled', 'value' => empty($options['enabled']) ? 'no' : 'yes'],
            ['field' => 'fast_interval', 'value' => (string) ($options['cron_interval'] ?? '')],
            ['field' => 'slow_interval', 'value' => (string) ($options['slow_cron_interval'] ?? '')],
            ['field' => 'prom_file', 'value' => (string) ($options['prom_file'] ?? '')],
            ['field' => 'incident_log_file', 'value' => (string) ($options['incident_log_file'] ?? '')],
            ['field' => 'incident_log_format', 'value' => (string) ($options['incident_log_format'] ?? 'text')],
            ['field' => 'privacy_ip_mode', 'value' => (string) ($options['privacy_ip_mode'] ?? 'full')],
            ['field' => 'privacy_drop_url_query', 'value' => empty($options['privacy_drop_url_query']) ? 'no' : 'yes'],
            ['field' => 'privacy_drop_referer', 'value' => empty($options['privacy_drop_referer']) ? 'no' : 'yes'],
            ['field' => 'privacy_drop_user_agent', 'value' => empty($options['privacy_drop_user_agent']) ? 'no' : 'yes'],
            ['field' => 'privacy_exclude_private_ips', 'value' => empty($options['privacy_exclude_private_ips']) ? 'no' : 'yes'],
            ['field' => 'last_export', 'value' => Simula_Security_Telemetry_Settings::format_state_time($state['last_export'] ?? null)],
            ['field' => 'last_result_ok', 'value' => empty($state['last_result_ok']) ? 'no' : 'yes'],
            ['field' => 'last_result', 'value' => (string) ($state['last_result'] ?? '')],
            ['field' => 'last_error', 'value' => (string) ($state['last_error'] ?? '')],
            ['field' => 'last_incident_id', 'value' => (string) ($state['last_incident_id'] ?? 0)],
            ['field' => 'last_slow_refresh', 'value' => Simula_Security_Telemetry_Settings::format_state_time($state['slow_metric_cache_at'] ?? null)],
        ];

        WP_CLI\Utils\format_items('table', $rows, ['field', 'value']);
    }

    /** Emits WP-CLI success or error from a service result. */
    private function finish($result) {
        $result  = is_array($result) ? $result : [];
        $message = isset($result['message']) ? (string) $result['message'] : __('Export completed.', 'simula-security-telemetry-for-wordfence');

        if (!empty($result['ok'])) {
            WP_CLI::success($message);
            return;
        }

        WP_CLI::error($message);
    }
}

final class Simula_Security_Telemetry_Metrics {
    /** Hooks the plugin into WordPress actions, filters, and lifecycle events. */
    public static function init() {
        add_action('admin_menu', ['Simula_Security_Telemetry_Admin', 'admin_menu']);
        add_action('admin_init', ['Simula_Security_Telemetry_Settings', 'register_settings']);
        add_action(Simula_Security_Telemetry_Config::CRON_HOOK, ['Simula_Security_Telemetry_Service', 'export_fast']);
        add_action(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK, ['Simula_Security_Telemetry_Service', 'export_slow']);
        add_filter('cron_schedules', [__CLASS__, 'cron_schedules']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), ['Simula_Security_Telemetry_Admin', 'plugin_action_links']);

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('simula-security-telemtry', 'Simula_Security_Telemetry_CLI');
        }

        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
    }

    /** Returns the selectable schedule labels for cron interval settings. */
    public static function cron_interval_labels() {
        return [
            'sstfw_five_minutes'    => __('Every five minutes', 'simula-security-telemetry-for-wordfence'),
            'sstfw_fifteen_minutes' => __('Every fifteen minutes', 'simula-security-telemetry-for-wordfence'),
            'sstfw_thirty_minutes'  => __('Every thirty minutes', 'simula-security-telemetry-for-wordfence'),
            'hourly'               => __('Hourly', 'simula-security-telemetry-for-wordfence'),
        ];
    }

    /** Returns the selectable schedule labels for slow collector settings. */
    public static function slow_cron_interval_labels() {
        return [
            'hourly'     => __('Hourly', 'simula-security-telemetry-for-wordfence'),
            'twicedaily' => __('Twice daily', 'simula-security-telemetry-for-wordfence'),
            'daily'      => __('Daily', 'simula-security-telemetry-for-wordfence'),
        ];
    }

    /** Registers the custom cron schedules used by the exporter. */
    public static function cron_schedules($schedules) {
        $schedules['sstfw_five_minutes'] = [
            'interval' => 300,
            'display'  => __('Every five minutes', 'simula-security-telemetry-for-wordfence'),
        ];
        $schedules['sstfw_fifteen_minutes'] = [
            'interval' => 900,
            'display'  => __('Every fifteen minutes', 'simula-security-telemetry-for-wordfence'),
        ];
        $schedules['sstfw_thirty_minutes'] = [
            'interval' => 1800,
            'display'  => __('Every thirty minutes', 'simula-security-telemetry-for-wordfence'),
        ];

        return $schedules;
    }

    /** Initializes options, schedules exports, and writes the first metrics file on activation. */
    public static function activate() {
        if (!get_option(Simula_Security_Telemetry_Config::OPTION)) {
            add_option(Simula_Security_Telemetry_Config::OPTION, Simula_Security_Telemetry_Config::defaults(), '', false);
        }

        $options = Simula_Security_Telemetry_Settings::get_options();
        Simula_Security_Telemetry_Incidents::initialize_cursor_if_needed();
        Simula_Security_Telemetry_Settings::sync_schedule($options);

        if ($options['enabled']) {
            Simula_Security_Telemetry_Service::export();
            return;
        }

        Simula_Security_Telemetry_Output::write_disabled_metrics($options);
    }

    /** Unschedules the exporter cron job when the plugin is deactivated. */
    public static function deactivate() {
        wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::CRON_HOOK);
        wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK);
    }

    /** Removes plugin data and deletes only the generated metrics file on uninstall. */
    public static function uninstall() {
        $options = Simula_Security_Telemetry_Settings::get_options();

        wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::CRON_HOOK);
        wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK);
        delete_option(Simula_Security_Telemetry_Config::OPTION);
        delete_option(Simula_Security_Telemetry_Config::STATE);

        if (!empty($options['prom_file']) && is_string($options['prom_file']) && preg_match('/\.prom$/', $options['prom_file'])) {
            @wp_delete_file($options['prom_file']);
        }
    }
}

Simula_Security_Telemetry_Metrics::init();
