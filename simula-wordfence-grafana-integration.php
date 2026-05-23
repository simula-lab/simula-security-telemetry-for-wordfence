<?php
/**
 * Plugin Name: Simula Wordfence Grafana Integration
 * Plugin URI:  https://simula.no/
 * Description: Export Prometheus metrics from WordPress and Wordfence into a node_exporter textfile collector .prom file.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      Simula
 * Author URI:  https://simula.no/
 * License:     GPL v2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simula-wordfence-node-exporter-integration
 * Domain Path: /languages
 *
 * @package Simula_Wordfence_Grafana_Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Wordfence_Grafana_Config {
    public const OPTION      = 'wfne_metrics_options';
    public const STATE       = 'wfne_metrics_state';
    public const CRON_HOOK   = 'wfne_metrics_export_event';
    public const SLUG        = 'wfne-metrics';
    public const CAPABILITY  = 'manage_options';
    public const VERSION     = '1.0.0';
    public const TEXT_DOMAIN = 'simula-wordfence-node-exporter-integration';
    public const WINDOWS     = ['5m', '1h', '24h', '7d'];

    /** Returns the default plugin option values. */
    public static function defaults() {
        return [
            'enabled'              => 1,
            'cron_interval'        => 'wfne_five_minutes',
            'prom_file'            => '/var/lib/node_exporter/textfile_collector/wordfence.prom',
            'metric_prefix'        => 'wordpress_wordfence',
            'site_label'           => (string) parse_url(home_url('/'), PHP_URL_HOST),
            'incident_log_enabled' => 1,
            'incident_log_file'    => '/var/log/wordpress-wordfence-incidents.log',
            'incident_max_rows'    => 1000,
            'enabled_metrics'      => self::default_enabled_metrics(),
        ];
    }

    /** Returns the metric families that can be individually exported. */
    public static function metric_definitions() {
        return [
            'export_success' => [
                'label'       => __('Export success', self::TEXT_DOMAIN),
                'description' => __('Reports whether the latest export completed successfully.', self::TEXT_DOMAIN),
            ],
            'plugin_info' => [
                'label'       => __('Plugin info', self::TEXT_DOMAIN),
                'description' => __('Exports static plugin metadata including the installed version.', self::TEXT_DOMAIN),
            ],
            'enabled' => [
                'label'       => __('Exporter enabled state', self::TEXT_DOMAIN),
                'description' => __('Reports whether the exporter master switch is enabled. When off, both metrics and incident exports are disabled.', self::TEXT_DOMAIN),
            ],
            'last_export_timestamp_seconds' => [
                'label'       => __('Last export timestamp', self::TEXT_DOMAIN),
                'description' => __('Exports the Unix timestamp of the most recent export attempt.', self::TEXT_DOMAIN),
            ],
            'error_info' => [
                'label'       => __('Error info', self::TEXT_DOMAIN),
                'description' => __('Exports the last export failure message when an export fails.', self::TEXT_DOMAIN),
            ],
            'blocked_events_total' => [
                'label'       => __('Blocked events total', self::TEXT_DOMAIN),
                'description' => __('Cumulative counter of newly observed blocked Wordfence hits.', self::TEXT_DOMAIN),
            ],
            'blocked_events_window' => [
                'label'       => __('Blocked events by window', self::TEXT_DOMAIN),
                'description' => __('Blocked Wordfence hits over 5m, 1h, 24h, and 7d windows.', self::TEXT_DOMAIN),
            ],
            'blocked_events_by_status_24h' => [
                'label'       => __('Blocked events by status (24h)', self::TEXT_DOMAIN),
                'description' => __('Blocked Wordfence hits in the last 24 hours grouped by HTTP status code.', self::TEXT_DOMAIN),
            ],
            'failed_login_attempts_window' => [
                'label'       => __('Failed login attempts by window', self::TEXT_DOMAIN),
                'description' => __('Failed login activity over 5m, 1h, 24h, and 7d windows.', self::TEXT_DOMAIN),
            ],
            'locked_out_total' => [
                'label'       => __('Current lockouts', self::TEXT_DOMAIN),
                'description' => __('Current Wordfence lockout totals grouped by IP and user.', self::TEXT_DOMAIN),
            ],
            'two_factor_enabled' => [
                'label'       => __('Two-factor enabled', self::TEXT_DOMAIN),
                'description' => __('Whether Wordfence two-factor authentication appears configured.', self::TEXT_DOMAIN),
            ],
            'two_factor_protected_users_total' => [
                'label'       => __('Two-factor protected users', self::TEXT_DOMAIN),
                'description' => __('Count of users with Wordfence two-factor secrets configured.', self::TEXT_DOMAIN),
            ],
            'scan_issues_by_severity' => [
                'label'       => __('Scan issues by severity', self::TEXT_DOMAIN),
                'description' => __('Current Wordfence scan issues grouped by severity.', self::TEXT_DOMAIN),
            ],
            'scan_findings_total' => [
                'label'       => __('Scan findings total', self::TEXT_DOMAIN),
                'description' => __('Current Wordfence scan findings for malware and file changes.', self::TEXT_DOMAIN),
            ],
            'rate_limited_events_window' => [
                'label'       => __('Rate-limited events by window', self::TEXT_DOMAIN),
                'description' => __('Rate-limited or throttled requests over 5m, 1h, 24h, and 7d windows.', self::TEXT_DOMAIN),
            ],
            'top_attack_sources_24h' => [
                'label'       => __('Top attack sources (24h)', self::TEXT_DOMAIN),
                'description' => __('Top blocked attack sources observed during the last 24 hours.', self::TEXT_DOMAIN),
            ],
            'brute_force_events_window' => [
                'label'       => __('Brute-force events by window', self::TEXT_DOMAIN),
                'description' => __('Brute-force activity over 5m, 1h, 24h, and 7d windows.', self::TEXT_DOMAIN),
            ],
            'vulnerability_findings_total' => [
                'label'       => __('Vulnerability findings total', self::TEXT_DOMAIN),
                'description' => __('Current vulnerable or outdated core, plugin, and theme findings.', self::TEXT_DOMAIN),
            ],
        ];
    }

    /** Returns the default enabled state for every exportable metric family. */
    public static function default_enabled_metrics() {
        return array_fill_keys(array_keys(self::metric_definitions()), 1);
    }
}

final class Simula_Wordfence_Grafana_Util {
    /** Escapes a database identifier for use in dynamic SQL fragments. */
    public static function quote_identifier($identifier) {
        return '`' . str_replace('`', '``', (string) $identifier) . '`';
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
                'wfne_metrics',
                $absolute_error_code,
                $absolute_error_message,
                'error'
            );

            return (string) $default;
        }

        if (!preg_match($extension_pattern, $value)) {
            add_settings_error(
                'wfne_metrics',
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

final class Simula_Wordfence_Grafana_Settings {
    /** Registers the plugin settings and sanitization callback. */
    public static function register_settings() {
        register_setting(
            'wfne_metrics',
            Simula_Wordfence_Grafana_Config::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_options'],
                'default'           => Simula_Wordfence_Grafana_Config::defaults(),
            ]
        );
    }

    /** Loads plugin options merged with defaults. */
    public static function get_options() {
        $options = get_option(Simula_Wordfence_Grafana_Config::OPTION, []);
        $options = wp_parse_args(is_array($options) ? $options : [], Simula_Wordfence_Grafana_Config::defaults());
        $options['enabled_metrics'] = self::normalize_enabled_metrics($options['enabled_metrics'] ?? []);

        return $options;
    }

    /** Loads the exporter runtime state from the database. */
    public static function get_state() {
        $state = get_option(Simula_Wordfence_Grafana_Config::STATE, []);

        return is_array($state) ? $state : [];
    }

    /** Sanitizes submitted settings, updates scheduling, and writes disabled metrics when needed. */
    public static function sanitize_options($input) {
        $defaults = Simula_Wordfence_Grafana_Config::defaults();
        $input    = is_array($input) ? $input : [];
        $output   = [];

        $output['enabled']              = empty($input['enabled']) ? 0 : 1;
        $output['cron_interval']        = self::sanitize_cron_interval($input['cron_interval'] ?? $defaults['cron_interval']);
        $output['prom_file']            = self::sanitize_prom_file($input['prom_file'] ?? $defaults['prom_file']);
        $output['metric_prefix']        = self::sanitize_metric_prefix($input['metric_prefix'] ?? $defaults['metric_prefix']);
        $output['site_label']           = sanitize_text_field(wp_unslash((string) ($input['site_label'] ?? $defaults['site_label'])));
        $output['incident_log_enabled'] = empty($input['incident_log_enabled']) ? 0 : 1;
        $output['incident_log_file']    = self::sanitize_incident_log_file($input['incident_log_file'] ?? $defaults['incident_log_file']);
        $output['incident_max_rows']    = self::sanitize_incident_max_rows($input['incident_max_rows'] ?? $defaults['incident_max_rows']);
        $output['enabled_metrics']      = self::sanitize_enabled_metrics($input['enabled_metrics'] ?? []);

        if ($output['site_label'] === '') {
            $output['site_label'] = $defaults['site_label'];
        }

        self::sync_schedule($output);

        if (!$output['enabled']) {
            Simula_Wordfence_Grafana_Output::write_disabled_metrics($output);
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
        $scheduled = wp_next_scheduled(Simula_Wordfence_Grafana_Config::CRON_HOOK);
        $interval  = self::sanitize_cron_interval($options['cron_interval'] ?? Simula_Wordfence_Grafana_Config::defaults()['cron_interval']);

        if (!empty($options['enabled'])) {
            $event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event(Simula_Wordfence_Grafana_Config::CRON_HOOK) : false;

            if ($event && $event->schedule !== $interval) {
                wp_clear_scheduled_hook(Simula_Wordfence_Grafana_Config::CRON_HOOK);
                $scheduled = false;
            }

            if (!$scheduled) {
                wp_schedule_event(time() + 60, $interval, Simula_Wordfence_Grafana_Config::CRON_HOOK);
            }

            return;
        }

        if ($scheduled) {
            wp_clear_scheduled_hook(Simula_Wordfence_Grafana_Config::CRON_HOOK);
        }
    }

    /** Formats a stored export timestamp for display in the admin UI. */
    public static function format_state_time($timestamp) {
        $timestamp = (int) $timestamp;

        if ($timestamp <= 0) {
            return __('Never', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN);
        }

        return gmdate('Y-m-d H:i:s', $timestamp) . ' UTC';
    }

    /** Validates and normalizes the configured Prometheus output file path. */
    private static function sanitize_prom_file($value) {
        $default = Simula_Wordfence_Grafana_Config::defaults()['prom_file'];

        return Simula_Wordfence_Grafana_Util::sanitize_file_setting_path(
            $value,
            $default,
            'wfne-prom-file',
            __('The Prometheus file path must be absolute. The default path has been restored.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            'wfne-prom-file-extension',
            '/\.prom$/',
            __('The Prometheus file path must end with .prom. The default path has been restored.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)
        );
    }

    /** Validates and normalizes the configured incident log output path. */
    private static function sanitize_incident_log_file($value) {
        $default = Simula_Wordfence_Grafana_Config::defaults()['incident_log_file'];

        return Simula_Wordfence_Grafana_Util::sanitize_file_setting_path(
            $value,
            $default,
            'wfne-incident-log-file',
            __('The incident log file path must be absolute. The default path has been restored.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            'wfne-incident-log-file-extension',
            '/\.(?:log|jsonl)$/',
            __('The incident log file path must end with .log or .jsonl. The default path has been restored.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)
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
        $intervals = Simula_Wordfence_Grafana_Metrics::cron_interval_labels();

        return isset($intervals[$value]) ? $value : Simula_Wordfence_Grafana_Config::defaults()['cron_interval'];
    }

    /** Validates the maximum number of incident rows exported per run. */
    private static function sanitize_incident_max_rows($value) {
        $value = absint($value);

        if ($value < 1) {
            $value = Simula_Wordfence_Grafana_Config::defaults()['incident_max_rows'];
        }

        return min($value, 10000);
    }

    /** Normalizes stored metric settings to include every known metric family. */
    private static function normalize_enabled_metrics($value) {
        $defaults = Simula_Wordfence_Grafana_Config::default_enabled_metrics();
        $value    = is_array($value) ? $value : [];

        foreach ($defaults as $metric_key => $default_value) {
            $defaults[$metric_key] = empty($value[$metric_key]) ? 0 : 1;
        }

        return $defaults;
    }

    /** Sanitizes submitted metric settings from the admin form. */
    private static function sanitize_enabled_metrics($value) {
        $sanitized = Simula_Wordfence_Grafana_Config::default_enabled_metrics();
        $value     = is_array($value) ? $value : [];

        foreach ($sanitized as $metric_key => $default_value) {
            $sanitized[$metric_key] = empty($value[$metric_key]) ? 0 : 1;
        }

        return $sanitized;
    }
}

final class Simula_Wordfence_Grafana_Output {
    /** Writes a disabled-export metrics file and updates exporter state. */
    public static function write_disabled_metrics($options, $state = [], $disabled_message = null) {
        $state = is_array($state) ? $state : [];
        $site  = self::escape_label($options['site_label']);
        $now   = time();
        $body  = [];
        $disabled_message = is_string($disabled_message) && $disabled_message !== ''
            ? $disabled_message
            : __('Export disabled.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN);

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'export_success')) {
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

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'plugin_info')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_plugin_info',
                'gauge',
                'Plugin metadata for the exporter.',
                [
                    ['labels' => ['site' => $site, 'version' => self::escape_label(Simula_Wordfence_Grafana_Config::VERSION)], 'value' => 1],
                ]
            );
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'enabled')) {
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

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'last_export_timestamp_seconds')) {
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

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'export_success')) {
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

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'plugin_info')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_plugin_info',
                'gauge',
                'Plugin metadata for the exporter.',
                [
                    ['labels' => ['site' => $site, 'version' => self::escape_label(Simula_Wordfence_Grafana_Config::VERSION)], 'value' => 1],
                ]
            );
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'enabled')) {
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

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'last_export_timestamp_seconds')) {
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

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'error_info')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_error_info',
                'gauge',
                'Static error indicator for the latest export.',
                [
                    ['labels' => ['site' => $site, 'message' => self::escape_label((string) $message)], 'value' => 1],
                ]
            );
        }

        return empty($metrics) ? '' : implode("\n", $metrics) . "\n";
    }

    /** Atomically writes the metrics file and optionally persists the outcome to plugin state. */
    public static function write_metrics($file, $content, $error_message, $state, $persist_state = true) {
        $state      = is_array($state) ? $state : [];
        $directory  = dirname($file);
        $ok         = false;
        $message    = '';
        $result_ok  = false;

        if (!preg_match('/\.prom$/', $file)) {
            $message = __('Output file must end with .prom.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN);
        } elseif (!is_dir($directory)) {
            $message = sprintf(
                __('Output directory does not exist: %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                $directory
            );
        } elseif (!is_writable($directory)) {
            $message = sprintf(
                __('Output directory is not writable by PHP: %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                $directory
            );
        } else {
            $tmp_name = sprintf(
                '%s/.%s.%s.tmp',
                $directory,
                basename($file),
                wp_generate_password(12, false, false)
            );

            $written = file_put_contents($tmp_name, $content, LOCK_EX);
            if ($written === false) {
                $message = __('Failed writing the temporary metrics file.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN);
            } elseif (!rename($tmp_name, $file)) {
                @unlink($tmp_name);
                $message = __('Failed moving the temporary metrics file into place.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN);
            } else {
                $ok        = true;
                $message   = $error_message !== '' ? $error_message : sprintf(__('Metrics exported to %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN), $file);
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
            update_option(Simula_Wordfence_Grafana_Config::STATE, $state, false);
        }

        return [
            'ok'      => $result_ok,
            'message' => $message,
            'state'   => $state,
        ];
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

final class Simula_Wordfence_Grafana_Wordfence_Schema {
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
        return Simula_Wordfence_Grafana_Util::resolve_first_candidate(self::table_columns($table), $candidates);
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

        $rows    = $wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A);
        $columns = [];

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

        $rows  = $wpdb->get_col('SHOW TABLES');
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

final class Simula_Wordfence_Grafana_Wordfence_Collector {
    /** Builds the SQL condition used to identify blocked requests in a hits table. */
    public static function blocked_where_sql($table) {
        $clauses = [];

        $action_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, ['action']);
        if ($action_column !== null) {
            $action_identifier = self::quote_identifier($action_column);
            $clauses[]         = '(' . $action_identifier . " IS NOT NULL AND $action_identifier LIKE 'blocked:%')";
        }

        $status_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, ['statusCode', 'status']);
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

        foreach (Simula_Wordfence_Grafana_Config::WINDOWS as $window) {
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
        $country_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, ['ctry', 'countryCode', 'country']);

        if ($country_column !== null) {
            $country_identifier = self::quote_identifier($country_column);
            $country_rows       = $wpdb->get_results(
                "SELECT $country_identifier AS source_name, COUNT(*) AS count_total
                FROM `$table`
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

        $ip_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, ['IP', 'ip']);
        if ($ip_column !== null) {
            $ip_identifier = self::quote_identifier($ip_column);
            $ip_rows       = $wpdb->get_results(
                "SELECT $ip_identifier AS source_ip, COUNT(*) AS count_total
                FROM `$table`
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
        $blocked_ip_table = Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_table('wfBlockedIPLog');

        if (Simula_Wordfence_Grafana_Wordfence_Schema::table_exists($blocked_ip_table)) {
            $ip_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($blocked_ip_table, ['IP', 'ip']);
            if ($ip_column !== null) {
                $ip_identifier = self::quote_identifier($ip_column);
                $lockout_where = self::lockout_active_where_sql($blocked_ip_table, $now);
                $query         = 'SELECT COUNT(DISTINCT ' . $ip_identifier . ") AS total FROM `$blocked_ip_table`";

                if ($lockout_where !== '') {
                    $query .= ' WHERE ' . $lockout_where;
                }

                $counts['ip'] = (int) $wpdb->get_var($query);
            }

            $user_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($blocked_ip_table, ['username', 'userName', 'user_id', 'userID', 'userId']);
            if ($user_column !== null) {
                $user_identifier = self::quote_identifier($user_column);
                $lockout_where   = self::lockout_active_where_sql($blocked_ip_table, $now);
                $query           = 'SELECT COUNT(DISTINCT ' . $user_identifier . ") AS total FROM `$blocked_ip_table` WHERE $user_identifier IS NOT NULL AND $user_identifier <> ''";

                if ($lockout_where !== '') {
                    $query .= ' AND ' . $lockout_where;
                }

                $counts['user'] = (int) $wpdb->get_var($query);
            }
        }

        $login_table = Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_table('wfLogins');
        if ($counts['user'] === 0 && Simula_Wordfence_Grafana_Wordfence_Schema::table_exists($login_table)) {
            $user_column   = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($login_table, ['username', 'userName', 'user_id', 'userID', 'userId']);
            $lockout_where = self::lockout_active_where_sql($login_table, $now);

            if ($user_column !== null && $lockout_where !== '') {
                $user_identifier = self::quote_identifier($user_column);
                $counts['user']  = (int) $wpdb->get_var(
                    'SELECT COUNT(DISTINCT ' . $user_identifier . ") AS total FROM `$login_table` WHERE $user_identifier IS NOT NULL AND $user_identifier <> '' AND " . $lockout_where
                );
            }
        }

        return $counts;
    }

    /** Collects Wordfence two-factor status and protected-user counts. */
    public static function collect_two_factor_metrics() {
        global $wpdb;

        $metrics        = ['enabled' => 0, 'protected_users' => 0];
        $secrets_table  = Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_table('wfls_2fa_secrets');
        $settings_table = Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_table('wfls_settings');

        if (Simula_Wordfence_Grafana_Wordfence_Schema::table_exists($secrets_table)) {
            $user_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($secrets_table, ['user_id', 'userID', 'userId', 'user']);
            if ($user_column !== null) {
                $metrics['protected_users'] = (int) $wpdb->get_var(
                    'SELECT COUNT(DISTINCT ' . self::quote_identifier($user_column) . ") FROM `$secrets_table`"
                );
            } else {
                $metrics['protected_users'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$secrets_table`");
            }
        }

        if ($metrics['protected_users'] > 0) {
            $metrics['enabled'] = 1;
        } elseif (Simula_Wordfence_Grafana_Wordfence_Schema::table_exists($settings_table)) {
            $metrics['enabled'] = (int) ($wpdb->get_var("SELECT COUNT(*) FROM `$settings_table`") > 0);
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
        $table   = Simula_Wordfence_Grafana_Wordfence_Schema::scan_issue_table();

        if ($table === null) {
            return $metrics;
        }

        $severity_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, ['severity', 'level', 'status']);
        if ($severity_column !== null) {
            $severity_identifier = self::quote_identifier($severity_column);
            $metrics['severity'] = $wpdb->get_results(
                "SELECT $severity_identifier AS severity, COUNT(*) AS count_total
                FROM `$table`
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
        $row           = $wpdb->get_row(
            "SELECT
                SUM(CASE WHEN $malware_where THEN 1 ELSE 0 END) AS malware_total,
                SUM(CASE WHEN $file_where THEN 1 ELSE 0 END) AS file_change_total,
                SUM(CASE WHEN $core_where THEN 1 ELSE 0 END) AS core_total,
                SUM(CASE WHEN $plugin_where THEN 1 ELSE 0 END) AS plugin_total,
                SUM(CASE WHEN $theme_where THEN 1 ELSE 0 END) AS theme_total
            FROM `$table`",
            ARRAY_A
        );

        $metrics['malware']                   = isset($row['malware_total']) ? (int) $row['malware_total'] : 0;
        $metrics['file_change']               = isset($row['file_change_total']) ? (int) $row['file_change_total'] : 0;
        $metrics['vulnerabilities']['core']   = isset($row['core_total']) ? (int) $row['core_total'] : 0;
        $metrics['vulnerabilities']['plugin'] = isset($row['plugin_total']) ? (int) $row['plugin_total'] : 0;
        $metrics['vulnerabilities']['theme']  = isset($row['theme_total']) ? (int) $row['theme_total'] : 0;

        return $metrics;
    }

    /** Filters a list of candidate column names down to those present in a table. */
    private static function available_columns($table, $candidates) {
        return Simula_Wordfence_Grafana_Util::resolve_available_candidates(
            Simula_Wordfence_Grafana_Wordfence_Schema::table_columns($table),
            $candidates
        );
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
        return Simula_Wordfence_Grafana_Util::quote_identifier($identifier);
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
            if (Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, [$column]) !== null) {
                $clauses[] = self::quote_identifier($column) . ' > ' . (int) $now;
            }
        }

        foreach (['blocked', 'lockedOut', 'isLocked'] as $column) {
            if (Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, [$column]) !== null) {
                $clauses[] = self::quote_identifier($column) . ' = 1';
            }
        }

        $status_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, ['status']);
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

final class Simula_Wordfence_Grafana_Wordfence {
    /** Returns the resolved Wordfence hits table name. */
    public static function wordfence_hits_table() {
        return Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_hits_table();
    }

    /** Checks whether a database table exists, using a local cache. */
    public static function table_exists($table) {
        return Simula_Wordfence_Grafana_Wordfence_Schema::table_exists($table);
    }

    /** Returns the first column name that exists from a list of candidates. */
    public static function first_available_column($table, $candidates) {
        return Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, $candidates);
    }

    /** Builds the SQL condition used to identify blocked requests in a hits table. */
    public static function blocked_where_sql($table) {
        return Simula_Wordfence_Grafana_Wordfence_Collector::blocked_where_sql($table);
    }

    /** Builds the SQL condition used to detect failed login activity. */
    public static function failed_login_where_sql($table) {
        return Simula_Wordfence_Grafana_Wordfence_Collector::failed_login_where_sql($table);
    }

    /** Builds the SQL condition used to detect throttled or rate-limited requests. */
    public static function rate_limited_where_sql($table) {
        return Simula_Wordfence_Grafana_Wordfence_Collector::rate_limited_where_sql($table);
    }

    /** Builds the SQL condition used to detect username/password brute-force activity. */
    public static function brute_force_username_where_sql($table) {
        return Simula_Wordfence_Grafana_Wordfence_Collector::brute_force_username_where_sql($table);
    }

    /** Builds the SQL condition used to detect XML-RPC brute-force activity. */
    public static function brute_force_xmlrpc_where_sql($table) {
        return Simula_Wordfence_Grafana_Wordfence_Collector::brute_force_xmlrpc_where_sql($table);
    }

    /** Builds SQL SELECT expressions that count matching rows across configured time windows. */
    public static function build_window_count_select_sql($prefix, $condition_sql, $time_identifier, $windows) {
        return Simula_Wordfence_Grafana_Wordfence_Collector::build_window_count_select_sql($prefix, $condition_sql, $time_identifier, $windows);
    }

    /** Collects the top blocked attack sources by country and normalized IP range. */
    public static function collect_top_attack_sources($table, $time_identifier, $blocked_where, $since_timestamp) {
        return Simula_Wordfence_Grafana_Wordfence_Collector::collect_top_attack_sources($table, $time_identifier, $blocked_where, $since_timestamp);
    }

    /** Collects current IP and user lockout totals from available Wordfence tables. */
    public static function collect_lockout_counts($now) {
        return Simula_Wordfence_Grafana_Wordfence_Collector::collect_lockout_counts($now);
    }

    /** Collects Wordfence two-factor status and protected-user counts. */
    public static function collect_two_factor_metrics() {
        return Simula_Wordfence_Grafana_Wordfence_Collector::collect_two_factor_metrics();
    }

    /** Collects scan issue totals grouped by severity and finding category. */
    public static function collect_scan_issue_metrics() {
        return Simula_Wordfence_Grafana_Wordfence_Collector::collect_scan_issue_metrics();
    }

    /** Builds the likely table names for a Wordfence table suffix. */
    public static function wordfence_table_candidates($suffix) {
        return Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_table_candidates($suffix);
    }

    /** Returns the column metadata for a table, cached by table name. */
    public static function table_columns($table) {
        return Simula_Wordfence_Grafana_Wordfence_Schema::table_columns($table);
    }
}

final class Simula_Wordfence_Grafana_Incidents {
    /** Initializes the incident cursor from the current maximum Wordfence hit ID. */
    public static function initialize_cursor_if_needed() {
        global $wpdb;

        $state = Simula_Wordfence_Grafana_Settings::get_state();
        if (!empty($state['incident_cursor_initialized'])) {
            return;
        }

        $table      = Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_hits_table();
        $last_id    = 0;
        $id_column  = null;

        if (Simula_Wordfence_Grafana_Wordfence_Schema::table_exists($table)) {
            $id_column = Simula_Wordfence_Grafana_Util::resolve_first_candidate(
                Simula_Wordfence_Grafana_Wordfence_Schema::table_columns($table),
                ['id']
            );
        }

        if ($id_column !== null) {
            $last_id = (int) $wpdb->get_var(
                'SELECT COALESCE(MAX(' . self::quote_identifier($id_column) . "), 0) FROM `$table`"
            );
        }

        $state['incident_cursor_initialized'] = 1;
        $state['last_incident_id']            = max(0, $last_id);
        update_option(Simula_Wordfence_Grafana_Config::STATE, $state, false);
    }

    /** Resets the incident cursor so the next run can backfill from the start of the hits table. */
    public static function reset_cursor() {
        $state = Simula_Wordfence_Grafana_Settings::get_state();

        $state['incident_cursor_initialized'] = 1;
        $state['last_incident_id']            = 0;
        $state['last_incident_error']         = '';
        update_option(Simula_Wordfence_Grafana_Config::STATE, $state, false);
    }

    /** Exports new blocked Wordfence incidents as JSON Lines for Loki or Alloy ingestion. */
    public static function export($options = null) {
        global $wpdb;

        $options = is_array($options) ? $options : Simula_Wordfence_Grafana_Settings::get_options();
        if (empty($options['enabled'])) {
            return [
                'ok'      => false,
                'message' => __('Exporter is disabled. Enable the exporter to run both metrics and incident log exports.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            ];
        }

        if (empty($options['incident_log_enabled'])) {
            return [
                'ok'      => true,
                'message' => __('Incident log export disabled.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            ];
        }

        $table = Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_hits_table();
        $state = Simula_Wordfence_Grafana_Settings::get_state();

        if (!Simula_Wordfence_Grafana_Wordfence_Schema::table_exists($table)) {
            $message = sprintf(
                __('Wordfence table not found. Tried: %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                implode(', ', Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_table_candidates('wfHits'))
            );

            return self::update_failure_state($state, $options, $message);
        }

        self::initialize_cursor_if_needed();
        $state  = Simula_Wordfence_Grafana_Settings::get_state();
        $schema = self::resolve_schema($table);

        if ($schema['id'] === null) {
            return self::update_failure_state(
                $state,
                $options,
                __('Unsupported Wordfence hits schema: missing an incident ID column.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)
            );
        }

        if ($schema['time'] === null) {
            return self::update_failure_state(
                $state,
                $options,
                __('Unsupported Wordfence hits schema: missing an incident timestamp column.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)
            );
        }

        $where_sql = Simula_Wordfence_Grafana_Wordfence_Collector::blocked_where_sql($table);
        if ($where_sql === '0=1') {
            return self::update_failure_state(
                $state,
                $options,
                __('Unsupported Wordfence hits schema: blocked incident filtering is unavailable.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)
            );
        }

        $last_id       = isset($state['last_incident_id']) ? (int) $state['last_incident_id'] : 0;
        $max_rows      = isset($options['incident_max_rows']) ? (int) $options['incident_max_rows'] : Simula_Wordfence_Grafana_Config::defaults()['incident_max_rows'];
        $limit         = min(max($max_rows, 1), 10000);
        $id_identifier = self::quote_identifier($schema['id']);
        $rows          = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `$table`
                WHERE $id_identifier > %d AND $where_sql
                ORDER BY $id_identifier ASC
                LIMIT %d",
                $last_id,
                $limit
            ),
            ARRAY_A
        );

        if ($wpdb->last_error !== '') {
            return self::update_failure_state($state, $options, $wpdb->last_error);
        }

        if (empty($rows)) {
            $state['last_incident_export']        = time();
            $state['last_incident_exported_rows'] = 0;
            $state['last_incident_log_file']      = $options['incident_log_file'];
            $state['last_incident_error']         = '';
            update_option(Simula_Wordfence_Grafana_Config::STATE, $state, false);

            return [
                'ok'      => true,
                'message' => __('No new Wordfence incidents to append.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            ];
        }

        $lines       = [];
        $max_seen_id = $last_id;

        foreach ((array) $rows as $row) {
            $row_id = isset($row[$schema['id']]) ? (int) $row[$schema['id']] : 0;
            if ($row_id > $max_seen_id) {
                $max_seen_id = $row_id;
            }

            $json = wp_json_encode(self::row_to_event($row, $table, $options, $schema), JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || $json === '') {
                return self::update_failure_state(
                    $state,
                    $options,
                    __('Failed encoding a Wordfence incident row as JSON.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)
                );
            }

            $lines[] = $json . "\n";
        }

        $write = self::append_log($options['incident_log_file'], implode('', $lines));
        if (!$write['ok']) {
            return self::update_failure_state($state, $options, $write['message']);
        }

        $state['last_incident_id']            = $max_seen_id;
        $state['last_incident_export']        = time();
        $state['last_incident_exported_rows'] = count($rows);
        $state['last_incident_log_file']      = $options['incident_log_file'];
        $state['last_incident_error']         = '';
        update_option(Simula_Wordfence_Grafana_Config::STATE, $state, false);

        return [
            'ok'      => true,
            'message' => sprintf(
                __('Appended %1$d Wordfence incidents to %2$s.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                count($rows),
                $options['incident_log_file']
            ),
        ];
    }

    /** Returns a sample incident event for operator-facing admin UI help text. */
    public static function sample_json_line($options = null) {
        $options = is_array($options) ? $options : Simula_Wordfence_Grafana_Settings::get_options();

        $sample = [
            'ts'             => '2026-05-23T12:34:56+00:00',
            'level'          => 'warning',
            'event'          => 'wordfence_blocked_request',
            'source'         => 'wordfence',
            'site'           => (string) ($options['site_label'] ?? parse_url(home_url('/'), PHP_URL_HOST)),
            'wordpress_home' => home_url('/'),
            'hostname'       => self::clean_string(function_exists('gethostname') ? gethostname() : ''),
            'blog_id'        => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1,
            'wp_table'       => Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_hits_table(),
            'wf_hit_id'      => 123,
            'ip'             => '203.0.113.10',
            'http_status'    => 403,
            'action'         => 'blocked:waf',
            'reason'         => 'SQL injection attempt',
            'method'         => 'POST',
            'url'            => '/wp-admin/admin-ajax.php',
            'referer'        => 'https://example.com/',
            'user_agent'     => 'curl/8.0',
            'country'        => 'NO',
        ];

        $sample = array_filter(
            $sample,
            static function ($value) {
                return $value !== null && $value !== '';
            }
        );

        $json = wp_json_encode($sample, JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }

    /** Resolves the Wordfence hits schema columns used by the incident exporter. */
    private static function resolve_schema($table) {
        $columns = Simula_Wordfence_Grafana_Wordfence_Schema::table_columns($table);

        return [
            'columns'    => $columns,
            'id'         => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['id']),
            'time'       => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['attackLogTime', 'ctime', 'time']),
            'status'     => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['statusCode', 'status']),
            'action'     => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['action']),
            'reason'     => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['actionDescription', 'description', 'msg', 'message', 'reason']),
            'method'     => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['method', 'httpMethod', 'requestMethod']),
            'url'        => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['URL', 'url', 'uri', 'requestUri', 'request_uri', 'path']),
            'referer'    => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['referer', 'Referer', 'referrer']),
            'user_agent' => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['UA', 'user_agent', 'userAgent']),
            'country'    => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['ctry', 'countryCode', 'country']),
            'ip'         => Simula_Wordfence_Grafana_Util::resolve_first_candidate($columns, ['IP', 'ip', 'ipaddress', 'ipAddress']),
        ];
    }

    /** Maps a Wordfence hit row into a stable JSON event envelope. */
    private static function row_to_event($row, $table, $options, $schema) {
        $event_time = self::column_value($row, $schema['time']);
        $event_ts   = is_numeric($event_time) && (int) $event_time > 0 ? (int) $event_time : time();
        $status     = self::column_value($row, $schema['status']);
        $ip         = self::normalize_ip(self::column_value($row, $schema['ip']));
        $event      = [
            'ts'             => gmdate('c', $event_ts),
            'level'          => 'warning',
            'event'          => 'wordfence_blocked_request',
            'source'         => 'wordfence',
            'site'           => (string) ($options['site_label'] ?? parse_url(home_url('/'), PHP_URL_HOST)),
            'wordpress_home' => home_url('/'),
            'hostname'       => self::clean_string(function_exists('gethostname') ? gethostname() : ''),
            'blog_id'        => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1,
            'wp_table'       => $table,
            'wf_hit_id'      => isset($schema['id'], $row[$schema['id']]) ? (int) $row[$schema['id']] : null,
            'ip'             => $ip,
            'http_status'    => is_numeric($status) ? (int) $status : self::clean_string($status),
            'action'         => self::clean_string(self::column_value($row, $schema['action'])),
            'reason'         => self::clean_string(self::column_value($row, $schema['reason'])),
            'method'         => self::clean_string(self::column_value($row, $schema['method'])),
            'url'            => self::clean_string(self::column_value($row, $schema['url'])),
            'referer'        => self::clean_string(self::column_value($row, $schema['referer'])),
            'user_agent'     => self::clean_string(self::column_value($row, $schema['user_agent'])),
            'country'        => self::clean_string(self::column_value($row, $schema['country'])),
        ];

        foreach ($event as $key => $value) {
            if ($value === null || $value === '') {
                unset($event[$key]);
            }
        }

        return $event;
    }

    /** Returns a row value only when the resolved column is present. */
    private static function column_value($row, $column) {
        if ($column === null || !array_key_exists($column, $row)) {
            return null;
        }

        return $row[$column];
    }

    /** Appends content to the incident log using an exclusive file lock. */
    private static function append_log($file, $content) {
        $directory = dirname($file);

        if (!is_dir($directory)) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    __('Incident log directory does not exist: %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                    $directory
                ),
            ];
        }

        if (!is_writable($directory) && !(file_exists($file) && is_writable($file))) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    __('Incident log path is not writable by PHP: %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                    $file
                ),
            ];
        }

        $handle = @fopen($file, 'ab');
        if (!is_resource($handle)) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    __('Could not open the incident log for append: %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                    $file
                ),
            ];
        }

        $ok = false;

        if (flock($handle, LOCK_EX)) {
            $written = fwrite($handle, $content);
            fflush($handle);
            flock($handle, LOCK_UN);
            $ok = $written === strlen($content);
        }

        fclose($handle);

        if (!$ok) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    __('Failed appending the incident log: %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                    $file
                ),
            ];
        }

        return [
            'ok'      => true,
            'message' => __('Incident log appended.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
        ];
    }

    /** Updates incident-specific failure state and returns a normalized error result. */
    private static function update_failure_state($state, $options, $message) {
        $state                           = is_array($state) ? $state : [];
        $state['last_incident_export']   = time();
        $state['last_incident_exported_rows'] = 0;
        $state['last_incident_log_file'] = $options['incident_log_file'] ?? '';
        $state['last_incident_error']    = (string) $message;
        update_option(Simula_Wordfence_Grafana_Config::STATE, $state, false);

        return [
            'ok'      => false,
            'message' => (string) $message,
        ];
    }

    /** Normalizes a scalar value into a safe plain-text JSON string field. */
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
        return Simula_Wordfence_Grafana_Util::quote_identifier($identifier);
    }
}

final class Simula_Wordfence_Grafana_Service {
    /** Orchestrates metrics export and incident log export under the shared cron hook. */
    public static function export($force = false) {
        $options = Simula_Wordfence_Grafana_Settings::get_options();
        $state   = Simula_Wordfence_Grafana_Settings::get_state();

        if (empty($options['enabled'])) {
            $message = $force
                ? __('Exporter is disabled. Enable the exporter to run both metrics and incident log exports.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)
                : __('Export disabled.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN);

            return Simula_Wordfence_Grafana_Output::write_disabled_metrics($options, $state, $message);
        }

        $metric_result   = self::export_metrics($options, $state);
        $incident_result = Simula_Wordfence_Grafana_Incidents::export($options);

        return self::merge_results($metric_result, $incident_result);
    }

    /** Collects Wordfence data, builds metrics, and writes the Prometheus output file. */
    private static function export_metrics($options, $state) {
        $now = time();
        $data = self::collect_metric_export_data($options, $state, $now);
        if (empty($data['ok'])) {
            return self::write_metric_failure($options, $state, $now, $data['message'] ?? '');
        }

        $metrics = self::build_metric_output_lines($options, $now, $data);

        return self::persist_metric_export($options, $state, $now, $data, $metrics);
    }

    /** Collects all source data required to render a metrics export run. */
    private static function collect_metric_export_data($options, $state, $now) {
        global $wpdb;

        $table = Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_hits_table();
        if (!Simula_Wordfence_Grafana_Wordfence_Schema::table_exists($table)) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    __('Wordfence table not found. Tried: %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                    implode(', ', Simula_Wordfence_Grafana_Wordfence_Schema::wordfence_table_candidates('wfHits'))
                ),
            ];
        }

        $id_column   = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, ['id']);
        $time_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, ['attackLogTime', 'ctime', 'time']);
        $where_sql   = Simula_Wordfence_Grafana_Wordfence_Collector::blocked_where_sql($table);

        if ($id_column === null || $time_column === null || $where_sql === '0=1') {
            return [
                'ok'      => false,
                'message' => __('Unsupported Wordfence hits schema.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            ];
        }

        $flags = self::metric_export_flags($options);
        $data  = [
            'ok'                 => true,
            'table'              => $table,
            'time_identifier'    => Simula_Wordfence_Grafana_Util::quote_identifier($time_column),
            'where_sql'          => $where_sql,
            'last_id'            => isset($state['last_id']) ? (int) $state['last_id'] : 0,
            'blocked_total'      => isset($state['blocked_total']) ? (float) $state['blocked_total'] : 0.0,
            'windows'            => self::window_timestamps($now),
            'site'               => Simula_Wordfence_Grafana_Output::escape_label($options['site_label']),
            'prefix'             => $options['metric_prefix'],
            'flags'              => $flags,
            'window_counts'      => [],
            'status_counts'      => [],
            'top_attack_sources' => [],
            'lockout_counts'     => [],
            'two_factor_metrics' => [],
            'scan_issue_metrics' => [],
        ];

        if ($flags['blocked_events_total']) {
            $incremental = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT COALESCE(MAX(id), 0) AS max_id, COUNT(*) AS new_count
                    FROM `$table`
                    WHERE id > %d AND $where_sql",
                    $data['last_id']
                ),
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
            $data['top_attack_sources'] = Simula_Wordfence_Grafana_Wordfence_Collector::collect_top_attack_sources(
                $table,
                $data['time_identifier'],
                $where_sql,
                $data['windows']['24h']
            );
        }

        if ($flags['locked_out_total']) {
            $data['lockout_counts'] = Simula_Wordfence_Grafana_Wordfence_Collector::collect_lockout_counts($now);
        }

        if ($flags['needs_two_factor_metrics']) {
            $data['two_factor_metrics'] = Simula_Wordfence_Grafana_Wordfence_Collector::collect_two_factor_metrics();
        }

        if ($flags['needs_scan_metrics']) {
            $data['scan_issue_metrics'] = Simula_Wordfence_Grafana_Wordfence_Collector::collect_scan_issue_metrics();
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

        foreach (array_keys(Simula_Wordfence_Grafana_Config::metric_definitions()) as $metric_key) {
            $flags[$metric_key] = Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, $metric_key);
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

        return $flags;
    }

    /** Collects windowed counts for the enabled recent-activity metric families. */
    private static function collect_window_counts($table, $time_identifier, $where_sql, $windows, $flags) {
        global $wpdb;

        $window_selects = [];

        if (!empty($flags['blocked_events_window'])) {
            $window_selects[] = Simula_Wordfence_Grafana_Wordfence_Collector::build_window_count_select_sql('blocked', $where_sql, $time_identifier, $windows);
        }

        if (!empty($flags['failed_login_attempts_window'])) {
            $window_selects[] = Simula_Wordfence_Grafana_Wordfence_Collector::build_window_count_select_sql(
                'failed_login',
                Simula_Wordfence_Grafana_Wordfence_Collector::failed_login_where_sql($table),
                $time_identifier,
                $windows
            );
        }

        if (!empty($flags['rate_limited_events_window'])) {
            $window_selects[] = Simula_Wordfence_Grafana_Wordfence_Collector::build_window_count_select_sql(
                'rate_limited',
                Simula_Wordfence_Grafana_Wordfence_Collector::rate_limited_where_sql($table),
                $time_identifier,
                $windows
            );
        }

        if (!empty($flags['brute_force_events_window'])) {
            $window_selects[] = Simula_Wordfence_Grafana_Wordfence_Collector::build_window_count_select_sql(
                'brute_username',
                Simula_Wordfence_Grafana_Wordfence_Collector::brute_force_username_where_sql($table),
                $time_identifier,
                $windows
            );
            $window_selects[] = Simula_Wordfence_Grafana_Wordfence_Collector::build_window_count_select_sql(
                'brute_xmlrpc',
                Simula_Wordfence_Grafana_Wordfence_Collector::brute_force_xmlrpc_where_sql($table),
                $time_identifier,
                $windows
            );
        }

        return $wpdb->get_row(
            "SELECT
                " . implode(",\n                    ", $window_selects) . "
            FROM `$table`
            WHERE $time_identifier >= " . (int) $windows['7d'],
            ARRAY_A
        );
    }

    /** Collects blocked 24h status-code counts when the necessary schema columns are present. */
    private static function collect_status_counts($table, $time_identifier, $where_sql, $windows) {
        global $wpdb;

        $status_column = Simula_Wordfence_Grafana_Wordfence_Schema::first_available_column($table, ['statusCode', 'status']);
        if ($status_column === null) {
            return [];
        }

        $status_identifier = Simula_Wordfence_Grafana_Util::quote_identifier($status_column);

        return $wpdb->get_results(
            "SELECT $status_identifier AS status_code, COUNT(*) AS count_total
            FROM `$table`
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

        return $metrics;
    }

    /** Renders exporter status and metadata metrics. */
    private static function render_core_export_metrics($options, $now, $data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];

        if (!empty($flags['export_success'])) {
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
            Simula_Wordfence_Grafana_Output::append_metric_family(
                $metrics,
                $prefix . '_plugin_info',
                'gauge',
                'Plugin metadata for the exporter.',
                [
                    ['labels' => ['site' => $site, 'version' => Simula_Wordfence_Grafana_Output::escape_label(Simula_Wordfence_Grafana_Config::VERSION)], 'value' => 1],
                ]
            );
        }

        if (!empty($flags['last_export_timestamp_seconds'])) {
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
                    'labels' => ['site' => $site, 'status' => Simula_Wordfence_Grafana_Output::escape_label($status)],
                    'value'  => $count,
                ];
            }

            Simula_Wordfence_Grafana_Output::append_metric_family(
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
                        'source_type' => Simula_Wordfence_Grafana_Output::escape_label($source_type),
                        'source'      => Simula_Wordfence_Grafana_Output::escape_label($source_name),
                    ],
                    'value'  => $count,
                ];
            }

            Simula_Wordfence_Grafana_Output::append_metric_family(
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
            Simula_Wordfence_Grafana_Output::append_metric_family(
                $metrics,
                $prefix . '_failed_login_attempts_window',
                'gauge',
                'Failed login attempts observed within recent windows.',
                self::build_window_metric_samples($site, $data['window_counts'], 'failed_login')
            );
        }

        if (!empty($flags['rate_limited_events_window'])) {
            Simula_Wordfence_Grafana_Output::append_metric_family(
                $metrics,
                $prefix . '_rate_limited_events_window',
                'gauge',
                'Rate-limited or throttled requests observed within recent windows.',
                self::build_window_metric_samples($site, $data['window_counts'], 'rate_limited')
            );
        }

        if (!empty($flags['brute_force_events_window'])) {
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
                    'labels' => ['site' => $site, 'severity' => Simula_Wordfence_Grafana_Output::escape_label($severity)],
                    'value'  => $count,
                ];
            }

            Simula_Wordfence_Grafana_Output::append_metric_family(
                $metrics,
                $prefix . '_scan_issues_by_severity',
                'gauge',
                'Current Wordfence scan issues grouped by severity.',
                $samples
            );
        }

        if (!empty($flags['scan_findings_total'])) {
            Simula_Wordfence_Grafana_Output::append_metric_family(
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
            Simula_Wordfence_Grafana_Output::append_metric_family(
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

    /** Builds metric samples for all configured time windows with stable label ordering. */
    private static function build_window_metric_samples($site, $window_counts, $count_prefix, $extra_labels = []) {
        $samples = [];

        foreach (Simula_Wordfence_Grafana_Config::WINDOWS as $window) {
            $samples[] = [
                'labels' => array_merge(['site' => $site], $extra_labels, ['window' => $window]),
                'value'  => self::window_metric_value($window_counts, $count_prefix, $window),
            ];
        }

        return $samples;
    }

    /** Writes failure metrics for an unsuccessful metrics export attempt. */
    private static function write_metric_failure($options, $state, $now, $message) {
        $message = (string) $message;

        return Simula_Wordfence_Grafana_Output::write_metrics(
            $options['prom_file'],
            Simula_Wordfence_Grafana_Output::build_failure_metrics($options, $now, $message),
            $message,
            $state
        );
    }

    /** Applies final state updates and writes the rendered metrics file. */
    private static function persist_metric_export($options, $state, $now, $data, $metrics) {
        $state['blocked_total'] = $data['blocked_total'];
        $state['last_export']   = $now;
        $state['last_id']       = $data['last_id'];

        return Simula_Wordfence_Grafana_Output::write_metrics(
            $options['prom_file'],
            empty($metrics) ? '' : implode("\n", $metrics) . "\n",
            '',
            $state
        );
    }

    /** Persists and returns the combined result from the metrics and incident exporters. */
    private static function merge_results($metric_result, $incident_result) {
        $metric_result   = is_array($metric_result) ? $metric_result : [];
        $incident_result = is_array($incident_result) ? $incident_result : [];
        $ok              = !empty($metric_result['ok']) && !empty($incident_result['ok']);
        $messages        = array_filter([
            isset($metric_result['message']) ? (string) $metric_result['message'] : '',
            isset($incident_result['message']) ? (string) $incident_result['message'] : '',
        ]);
        $message         = $messages === []
            ? __('Export completed.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)
            : implode(' ', $messages);
        $errors          = array_filter([
            empty($metric_result['ok']) && !empty($metric_result['message']) ? (string) $metric_result['message'] : '',
            empty($incident_result['ok']) && !empty($incident_result['message']) ? (string) $incident_result['message'] : '',
        ]);
        $state           = Simula_Wordfence_Grafana_Settings::get_state();

        $state['last_result']    = $message;
        $state['last_result_ok'] = $ok ? 1 : 0;
        $state['last_error']     = $ok ? '' : implode(' ', $errors);
        update_option(Simula_Wordfence_Grafana_Config::STATE, $state, false);

        return [
            'ok'      => $ok,
            'message' => $message,
        ];
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

final class Simula_Wordfence_Grafana_Admin {
    /** Registers the plugin settings page under the WordPress Settings menu. */
    public static function admin_menu() {
        add_options_page(
            __('Simula Wordfence Grafana Metrics', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            __('Wordfence Metrics', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            Simula_Wordfence_Grafana_Config::CAPABILITY,
            Simula_Wordfence_Grafana_Config::SLUG,
            [__CLASS__, 'settings_page']
        );
    }

    /** Adds a Settings shortcut link on the plugins screen. */
    public static function plugin_action_links($links) {
        $url = admin_url('options-general.php?page=' . Simula_Wordfence_Grafana_Config::SLUG);

        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url($url),
                esc_html__('Settings', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)
            )
        );

        return $links;
    }

    /** Renders the settings page and handles manual export requests. */
    public static function settings_page() {
        if (!current_user_can(Simula_Wordfence_Grafana_Config::CAPABILITY)) {
            return;
        }

        if (isset($_POST['wfne_export_now'])) {
            check_admin_referer('wfne_export_now');
            $result = Simula_Wordfence_Grafana_Service::export(true);
            add_settings_error(
                'wfne_metrics',
                'wfne-export-now',
                $result['message'],
                $result['ok'] ? 'updated' : 'error'
            );
        }

        if (isset($_POST['wfne_reset_incident_cursor'])) {
            check_admin_referer('wfne_reset_incident_cursor');
            Simula_Wordfence_Grafana_Incidents::reset_cursor();
            add_settings_error(
                'wfne_metrics',
                'wfne-reset-incident-cursor',
                __('Incident cursor reset to 0. The next export can backfill retained Wordfence incidents up to the configured row limit.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                'updated'
            );
        }

        $options = Simula_Wordfence_Grafana_Settings::get_options();
        $state   = Simula_Wordfence_Grafana_Settings::get_state();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Simula Wordfence Grafana Metrics', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></h1>
            <p><?php echo esc_html__('Exports Wordfence block telemetry into a Prometheus .prom file for node_exporter textfile collection and blocked-request events into a Loki-friendly JSON Lines log.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>

            <?php settings_errors('wfne_metrics'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('wfne_metrics'); ?>
                <h2><?php echo esc_html__('Prometheus metrics', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable exporter', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[enabled]" value="1" <?php checked($options['enabled'], 1); ?> />
                                <?php echo esc_html__('Master switch for the exporter. When disabled, both Prometheus metrics and incident log exports are off.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wfne-cron-interval"><?php echo esc_html__('Cron interval', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <select id="wfne-cron-interval" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[cron_interval]">
                                <?php foreach (Simula_Wordfence_Grafana_Metrics::cron_interval_labels() as $interval_key => $interval_label) : ?>
                                    <option value="<?php echo esc_attr($interval_key); ?>" <?php selected($options['cron_interval'], $interval_key); ?>>
                                        <?php echo esc_html($interval_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__('Controls how often WP-Cron runs exports while the exporter is enabled.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wfne-prom-file"><?php echo esc_html__('Prometheus file path', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wfne-prom-file" class="regular-text code" type="text" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[prom_file]" value="<?php echo esc_attr($options['prom_file']); ?>" />
                            <p class="description"><?php echo esc_html__('Example: /var/lib/node_exporter/textfile_collector/wordfence.prom. The directory must already exist and be writable by PHP.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wfne-metric-prefix"><?php echo esc_html__('Metric prefix', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wfne-metric-prefix" class="regular-text code" type="text" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[metric_prefix]" value="<?php echo esc_attr($options['metric_prefix']); ?>" />
                            <p class="description"><?php echo esc_html__('Prometheus metric prefix. Invalid characters are replaced automatically.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wfne-site-label"><?php echo esc_html__('Site label', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wfne-site-label" class="regular-text" type="text" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[site_label]" value="<?php echo esc_attr($options['site_label']); ?>" />
                            <p class="description"><?php echo esc_html__('Added to every exported metric as the site label value.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Exported metrics', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach (Simula_Wordfence_Grafana_Config::metric_definitions() as $metric_key => $metric_definition) : ?>
                                    <label style="display:block; margin-bottom:12px;">
                                        <input type="checkbox" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[enabled_metrics][<?php echo esc_attr($metric_key); ?>]" value="1" <?php checked(!empty($options['enabled_metrics'][$metric_key])); ?> />
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

                <h2><?php echo esc_html__('Loki / incident log', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable incident log export', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[incident_log_enabled]" value="1" <?php checked($options['incident_log_enabled'], 1); ?> />
                                <?php echo esc_html__('Append blocked Wordfence hits to the incident log on each export run. This runs only while the exporter is enabled.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wfne-incident-log-file"><?php echo esc_html__('Incident log path', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wfne-incident-log-file" class="regular-text code" type="text" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[incident_log_file]" value="<?php echo esc_attr($options['incident_log_file']); ?>" />
                            <p class="description"><?php echo esc_html__('Use an absolute .log or .jsonl path. The directory must already exist and be writable by PHP.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wfne-incident-max-rows"><?php echo esc_html__('Max incidents per run', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input id="wfne-incident-max-rows" class="small-text" type="number" min="1" max="10000" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[incident_max_rows]" value="<?php echo esc_attr((string) $options['incident_max_rows']); ?>" />
                            <p class="description"><?php echo esc_html__('Caps each export pass so large retained Wordfence hit tables do not create long-running admin or cron requests.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr />

            <form method="post" style="display:inline-block; margin-right: 12px;">
                <?php wp_nonce_field('wfne_export_now'); ?>
                <?php submit_button(__('Export now', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN), 'secondary', 'wfne_export_now'); ?>
            </form>
            <p class="description"><?php echo esc_html__('Manual export uses the same master exporter toggle. If the exporter is disabled, the button writes disabled metrics and reports that exports are off.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>

            <form method="post" style="display:inline-block;">
                <?php wp_nonce_field('wfne_reset_incident_cursor'); ?>
                <?php submit_button(__('Reset incident cursor for backfill', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN), 'delete', 'wfne_reset_incident_cursor'); ?>
            </form>

            <h2><?php echo esc_html__('Current state', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></h2>
            <table class="widefat striped" style="max-width:900px">
                <tbody>
                    <tr>
                        <td><strong><?php echo esc_html__('Last export timestamp', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html(Simula_Wordfence_Grafana_Settings::format_state_time($state['last_export'] ?? null)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Observed blocked events', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html((string) ($state['blocked_total'] ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Last processed hit ID', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html((string) ($state['last_id'] ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Last result', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html((string) ($state['last_result'] ?? __('No exports yet.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN))); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Last error', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html((string) ($state['last_error'] ?? '')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Incident cursor initialized', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html(!empty($state['incident_cursor_initialized']) ? __('Yes', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN) : __('No', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Last incident export', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html(Simula_Wordfence_Grafana_Settings::format_state_time($state['last_incident_export'] ?? null)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Last incident hit ID', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html((string) ($state['last_incident_id'] ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Last incident row count', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html((string) ($state['last_incident_exported_rows'] ?? 0)); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Last incident log file', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html((string) ($state['last_incident_log_file'] ?? $options['incident_log_file'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Last incident error', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></strong></td>
                        <td><?php echo esc_html((string) ($state['last_incident_error'] ?? '')); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Sample incident JSON line', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></h2>
            <pre style="max-width:900px; overflow:auto;"><?php echo esc_html(Simula_Wordfence_Grafana_Incidents::sample_json_line($options)); ?></pre>
        </div>
        <?php
    }
}

final class Simula_Wordfence_Grafana_Metrics {
    /** Hooks the plugin into WordPress actions, filters, and lifecycle events. */
    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'load_textdomain']);
        add_action('admin_menu', ['Simula_Wordfence_Grafana_Admin', 'admin_menu']);
        add_action('admin_init', ['Simula_Wordfence_Grafana_Settings', 'register_settings']);
        add_action(Simula_Wordfence_Grafana_Config::CRON_HOOK, ['Simula_Wordfence_Grafana_Service', 'export']);
        add_filter('cron_schedules', [__CLASS__, 'cron_schedules']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), ['Simula_Wordfence_Grafana_Admin', 'plugin_action_links']);

        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
    }

    /** Loads the plugin translation files. */
    public static function load_textdomain() {
        load_plugin_textdomain(Simula_Wordfence_Grafana_Config::TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /** Returns the selectable schedule labels for cron interval settings. */
    public static function cron_interval_labels() {
        return [
            'wfne_five_minutes'    => __('Every five minutes', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            'wfne_fifteen_minutes' => __('Every fifteen minutes', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            'wfne_thirty_minutes'  => __('Every thirty minutes', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
            'hourly'               => __('Hourly', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
        ];
    }

    /** Registers the custom cron schedules used by the exporter. */
    public static function cron_schedules($schedules) {
        $schedules['wfne_five_minutes'] = [
            'interval' => 300,
            'display'  => __('Every five minutes', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
        ];
        $schedules['wfne_fifteen_minutes'] = [
            'interval' => 900,
            'display'  => __('Every fifteen minutes', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
        ];
        $schedules['wfne_thirty_minutes'] = [
            'interval' => 1800,
            'display'  => __('Every thirty minutes', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
        ];

        return $schedules;
    }

    /** Initializes options, schedules exports, and writes the first metrics file on activation. */
    public static function activate() {
        if (!get_option(Simula_Wordfence_Grafana_Config::OPTION)) {
            add_option(Simula_Wordfence_Grafana_Config::OPTION, Simula_Wordfence_Grafana_Config::defaults(), '', false);
        }

        $options = Simula_Wordfence_Grafana_Settings::get_options();
        Simula_Wordfence_Grafana_Incidents::initialize_cursor_if_needed();
        Simula_Wordfence_Grafana_Settings::sync_schedule($options);

        if ($options['enabled']) {
            Simula_Wordfence_Grafana_Service::export();
            return;
        }

        Simula_Wordfence_Grafana_Output::write_disabled_metrics($options);
    }

    /** Unschedules the exporter cron job when the plugin is deactivated. */
    public static function deactivate() {
        wp_clear_scheduled_hook(Simula_Wordfence_Grafana_Config::CRON_HOOK);
    }

    /** Removes plugin data and deletes only the generated metrics file on uninstall. */
    public static function uninstall() {
        $options = Simula_Wordfence_Grafana_Settings::get_options();

        wp_clear_scheduled_hook(Simula_Wordfence_Grafana_Config::CRON_HOOK);
        delete_option(Simula_Wordfence_Grafana_Config::OPTION);
        delete_option(Simula_Wordfence_Grafana_Config::STATE);

        if (!empty($options['prom_file']) && is_string($options['prom_file']) && preg_match('/\.prom$/', $options['prom_file'])) {
            @unlink($options['prom_file']);
        }
    }
}

Simula_Wordfence_Grafana_Metrics::init();
