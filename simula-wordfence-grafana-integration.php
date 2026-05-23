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
            'enabled'         => 1,
            'cron_interval'   => 'wfne_five_minutes',
            'prom_file'       => '/var/lib/node_exporter/textfile_collector/wordfence.prom',
            'metric_prefix'   => 'wordpress_wordfence',
            'site_label'      => (string) parse_url(home_url('/'), PHP_URL_HOST),
            'enabled_metrics' => self::default_enabled_metrics(),
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
                'description' => __('Reports whether scheduled exporting is currently enabled.', self::TEXT_DOMAIN),
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

        $output['enabled']         = empty($input['enabled']) ? 0 : 1;
        $output['cron_interval']   = self::sanitize_cron_interval($input['cron_interval'] ?? $defaults['cron_interval']);
        $output['prom_file']       = self::sanitize_prom_file($input['prom_file'] ?? $defaults['prom_file']);
        $output['metric_prefix']   = self::sanitize_metric_prefix($input['metric_prefix'] ?? $defaults['metric_prefix']);
        $output['site_label']      = sanitize_text_field(wp_unslash((string) ($input['site_label'] ?? $defaults['site_label'])));
        $output['enabled_metrics'] = self::sanitize_enabled_metrics($input['enabled_metrics'] ?? []);

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
        $value = trim(wp_unslash((string) $value));
        if ($value === '') {
            $value = Simula_Wordfence_Grafana_Config::defaults()['prom_file'];
        }

        $value = wp_normalize_path($value);

        if (!self::is_absolute_path($value)) {
            add_settings_error(
                'wfne_metrics',
                'wfne-prom-file',
                __('The Prometheus file path must be absolute. The default path has been restored.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                'error'
            );

            return Simula_Wordfence_Grafana_Config::defaults()['prom_file'];
        }

        if (!preg_match('/\.prom$/', $value)) {
            add_settings_error(
                'wfne_metrics',
                'wfne-prom-file-extension',
                __('The Prometheus file path must end with .prom. The default path has been restored.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                'error'
            );

            return Simula_Wordfence_Grafana_Config::defaults()['prom_file'];
        }

        return $value;
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

    /** Checks whether a filesystem path is absolute on Unix or Windows. */
    private static function is_absolute_path($path) {
        return (bool) preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path);
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
    public static function write_disabled_metrics($options, $state = []) {
        $state = is_array($state) ? $state : [];
        $site  = self::escape_label($options['site_label']);
        $now   = time();
        $body  = [];

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'export_success')) {
            $body[] = '# HELP ' . $options['metric_prefix'] . '_export_success Whether the last Wordfence metrics export succeeded.';
            $body[] = '# TYPE ' . $options['metric_prefix'] . '_export_success gauge';
            $body[] = $options['metric_prefix'] . '_export_success{site="' . $site . '"} 0';
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'plugin_info')) {
            $body[] = '# HELP ' . $options['metric_prefix'] . '_plugin_info Plugin metadata for the exporter.';
            $body[] = '# TYPE ' . $options['metric_prefix'] . '_plugin_info gauge';
            $body[] = $options['metric_prefix'] . '_plugin_info{site="' . $site . '",version="' . self::escape_label(Simula_Wordfence_Grafana_Config::VERSION) . '"} 1';
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'enabled')) {
            $body[] = '# HELP ' . $options['metric_prefix'] . '_enabled Whether scheduled exporting is enabled.';
            $body[] = '# TYPE ' . $options['metric_prefix'] . '_enabled gauge';
            $body[] = $options['metric_prefix'] . '_enabled{site="' . $site . '"} 0';
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'last_export_timestamp_seconds')) {
            $body[] = '# HELP ' . $options['metric_prefix'] . '_last_export_timestamp_seconds Unix timestamp of the last export attempt.';
            $body[] = '# TYPE ' . $options['metric_prefix'] . '_last_export_timestamp_seconds gauge';
            $body[] = $options['metric_prefix'] . '_last_export_timestamp_seconds{site="' . $site . '"} ' . $now;
        }

        $state['last_export'] = $now;

        return self::write_metrics(
            $options['prom_file'],
            empty($body) ? '' : implode("\n", $body) . "\n",
            __('Export disabled.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
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
            $metrics[] = '# HELP ' . $prefix . '_export_success Whether the last Wordfence metrics export succeeded.';
            $metrics[] = '# TYPE ' . $prefix . '_export_success gauge';
            $metrics[] = $prefix . '_export_success{site="' . $site . '"} 0';
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'plugin_info')) {
            $metrics[] = '# HELP ' . $prefix . '_plugin_info Plugin metadata for the exporter.';
            $metrics[] = '# TYPE ' . $prefix . '_plugin_info gauge';
            $metrics[] = $prefix . '_plugin_info{site="' . $site . '",version="' . self::escape_label(Simula_Wordfence_Grafana_Config::VERSION) . '"} 1';
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'enabled')) {
            $metrics[] = '# HELP ' . $prefix . '_enabled Whether scheduled exporting is enabled.';
            $metrics[] = '# TYPE ' . $prefix . '_enabled gauge';
            $metrics[] = $prefix . '_enabled{site="' . $site . '"} ' . (int) $enabled;
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'last_export_timestamp_seconds')) {
            $metrics[] = '# HELP ' . $prefix . '_last_export_timestamp_seconds Unix timestamp of the last export attempt.';
            $metrics[] = '# TYPE ' . $prefix . '_last_export_timestamp_seconds gauge';
            $metrics[] = $prefix . '_last_export_timestamp_seconds{site="' . $site . '"} ' . $timestamp;
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'error_info')) {
            $metrics[] = '# HELP ' . $prefix . '_error_info Static error indicator for the latest export.';
            $metrics[] = '# TYPE ' . $prefix . '_error_info gauge';
            $metrics[] = $prefix . '_error_info{site="' . $site . '",message="' . self::escape_label((string) $message) . '"} 1';
        }

        return empty($metrics) ? '' : implode("\n", $metrics) . "\n";
    }

    /** Atomically writes the metrics file and persists the outcome to plugin state. */
    public static function write_metrics($file, $content, $error_message, $state) {
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
        update_option(Simula_Wordfence_Grafana_Config::STATE, $state, false);

        return [
            'ok'      => $result_ok,
            'message' => $message,
        ];
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
}

final class Simula_Wordfence_Grafana_Wordfence {
    /** Returns the resolved Wordfence hits table name. */
    public static function wordfence_hits_table() {
        return self::wordfence_table('wfHits');
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
        $columns = self::table_columns($table);

        foreach ((array) $candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /** Builds the SQL condition used to identify blocked requests in a hits table. */
    public static function blocked_where_sql($table) {
        $clauses = [];

        $action_column = self::first_available_column($table, ['action']);
        if ($action_column !== null) {
            $action_identifier = self::quote_identifier($action_column);
            $clauses[]         = '(' . $action_identifier . " IS NOT NULL AND $action_identifier LIKE 'blocked:%')";
        }

        $status_column = self::first_available_column($table, ['statusCode', 'status']);
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
        $country_column = self::first_available_column($table, ['ctry', 'countryCode', 'country']);

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

        $ip_column = self::first_available_column($table, ['IP', 'ip']);
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
        $blocked_ip_table = self::wordfence_table('wfBlockedIPLog');

        if (self::table_exists($blocked_ip_table)) {
            $ip_column = self::first_available_column($blocked_ip_table, ['IP', 'ip']);
            if ($ip_column !== null) {
                $ip_identifier = self::quote_identifier($ip_column);
                $lockout_where = self::lockout_active_where_sql($blocked_ip_table, $now);
                $query         = 'SELECT COUNT(DISTINCT ' . $ip_identifier . ") AS total FROM `$blocked_ip_table`";

                if ($lockout_where !== '') {
                    $query .= ' WHERE ' . $lockout_where;
                }

                $counts['ip'] = (int) $wpdb->get_var($query);
            }

            $user_column = self::first_available_column($blocked_ip_table, ['username', 'userName', 'user_id', 'userID', 'userId']);
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

        $login_table = self::wordfence_table('wfLogins');
        if ($counts['user'] === 0 && self::table_exists($login_table)) {
            $user_column   = self::first_available_column($login_table, ['username', 'userName', 'user_id', 'userID', 'userId']);
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
        $secrets_table  = self::wordfence_table('wfls_2fa_secrets');
        $settings_table = self::wordfence_table('wfls_settings');

        if (self::table_exists($secrets_table)) {
            $user_column = self::first_available_column($secrets_table, ['user_id', 'userID', 'userId', 'user']);
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
        } elseif (self::table_exists($settings_table)) {
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
        $table   = self::scan_issue_table();

        if ($table === null) {
            return $metrics;
        }

        $severity_column = self::first_available_column($table, ['severity', 'level', 'status']);
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
    private static function wordfence_table($suffix) {
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

        $candidates    = self::wordfence_table_candidates($suffix);
        $cache[$suffix] = isset($candidates[0]) ? $candidates[0] : (string) $suffix;

        return $cache[$suffix];
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

    /** Returns the column metadata for a table, cached by table name. */
    private static function table_columns($table) {
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

    /** Filters a list of candidate column names down to those present in a table. */
    private static function available_columns($table, $candidates) {
        $columns   = self::table_columns($table);
        $available = [];

        foreach ((array) $candidates as $candidate) {
            if (isset($columns[$candidate])) {
                $available[] = $candidate;
            }
        }

        return $available;
    }

    /** Escapes a database identifier for use in dynamic SQL fragments. */
    private static function quote_identifier($identifier) {
        return '`' . str_replace('`', '``', (string) $identifier) . '`';
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
            if (self::first_available_column($table, [$column]) !== null) {
                $clauses[] = self::quote_identifier($column) . ' > ' . (int) $now;
            }
        }

        foreach (['blocked', 'lockedOut', 'isLocked'] as $column) {
            if (self::first_available_column($table, [$column]) !== null) {
                $clauses[] = self::quote_identifier($column) . ' = 1';
            }
        }

        $status_column = self::first_available_column($table, ['status']);
        if ($status_column !== null) {
            $status_identifier = self::quote_identifier($status_column);
            $clauses[]         = "LOWER(COALESCE(CAST($status_identifier AS CHAR), '')) LIKE '%lock%'";
        }

        if ($clauses === []) {
            return '';
        }

        return self::combine_where_any($clauses);
    }

    /** Returns the Wordfence scan issue table currently available in the database. */
    private static function scan_issue_table() {
        foreach (['wfIssues', 'wfPendingIssues'] as $suffix) {
            $table = self::wordfence_table($suffix);
            if (self::table_exists($table)) {
                return $table;
            }
        }

        return null;
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

final class Simula_Wordfence_Grafana_Service {
    /** Collects Wordfence data, builds metrics, and writes the Prometheus output file. */
    public static function export($force = false) {
        global $wpdb;

        $options = Simula_Wordfence_Grafana_Settings::get_options();
        $state   = Simula_Wordfence_Grafana_Settings::get_state();
        $now     = time();

        if (!$force && empty($options['enabled'])) {
            return Simula_Wordfence_Grafana_Output::write_disabled_metrics($options, $state);
        }

        $table = Simula_Wordfence_Grafana_Wordfence::wordfence_hits_table();
        if (!Simula_Wordfence_Grafana_Wordfence::table_exists($table)) {
            $message = sprintf(
                __('Wordfence table not found. Tried: %s', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN),
                implode(', ', Simula_Wordfence_Grafana_Wordfence::wordfence_table_candidates('wfHits'))
            );

            return Simula_Wordfence_Grafana_Output::write_metrics(
                $options['prom_file'],
                Simula_Wordfence_Grafana_Output::build_failure_metrics($options, $now, $message),
                $message,
                $state
            );
        }

        $id_column   = Simula_Wordfence_Grafana_Wordfence::first_available_column($table, ['id']);
        $time_column = Simula_Wordfence_Grafana_Wordfence::first_available_column($table, ['attackLogTime', 'ctime', 'time']);
        $where_sql   = Simula_Wordfence_Grafana_Wordfence::blocked_where_sql($table);

        if ($id_column === null || $time_column === null || $where_sql === '0=1') {
            $message = __('Unsupported Wordfence hits schema.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN);

            return Simula_Wordfence_Grafana_Output::write_metrics(
                $options['prom_file'],
                Simula_Wordfence_Grafana_Output::build_failure_metrics($options, $now, $message),
                $message,
                $state
            );
        }

        $time_identifier     = '`' . str_replace('`', '``', (string) $time_column) . '`';
        $last_id             = isset($state['last_id']) ? (int) $state['last_id'] : 0;
        $total               = isset($state['blocked_total']) ? (float) $state['blocked_total'] : 0.0;
        $windows             = self::window_timestamps($now);
        $site                = Simula_Wordfence_Grafana_Output::escape_label($options['site_label']);
        $prefix              = $options['metric_prefix'];
        $needs_blocked_total = Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'blocked_events_total');
        $needs_window_counts =
            Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'blocked_events_window') ||
            Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'failed_login_attempts_window') ||
            Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'rate_limited_events_window') ||
            Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'brute_force_events_window');
        $needs_scan_metrics  =
            Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'scan_issues_by_severity') ||
            Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'scan_findings_total') ||
            Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'vulnerability_findings_total');

        $incremental = [];
        if ($needs_blocked_total) {
            $incremental = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT COALESCE(MAX(id), 0) AS max_id, COUNT(*) AS new_count
                    FROM `$table`
                    WHERE id > %d AND $where_sql",
                    $last_id
                ),
                ARRAY_A
            );
        }

        $window_counts = [];
        if ($needs_window_counts) {
            $window_selects = [];

            if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'blocked_events_window')) {
                $window_selects[] = Simula_Wordfence_Grafana_Wordfence::build_window_count_select_sql('blocked', $where_sql, $time_identifier, $windows);
            }

            if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'failed_login_attempts_window')) {
                $window_selects[] = Simula_Wordfence_Grafana_Wordfence::build_window_count_select_sql(
                    'failed_login',
                    Simula_Wordfence_Grafana_Wordfence::failed_login_where_sql($table),
                    $time_identifier,
                    $windows
                );
            }

            if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'rate_limited_events_window')) {
                $window_selects[] = Simula_Wordfence_Grafana_Wordfence::build_window_count_select_sql(
                    'rate_limited',
                    Simula_Wordfence_Grafana_Wordfence::rate_limited_where_sql($table),
                    $time_identifier,
                    $windows
                );
            }

            if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'brute_force_events_window')) {
                $window_selects[] = Simula_Wordfence_Grafana_Wordfence::build_window_count_select_sql(
                    'brute_username',
                    Simula_Wordfence_Grafana_Wordfence::brute_force_username_where_sql($table),
                    $time_identifier,
                    $windows
                );
                $window_selects[] = Simula_Wordfence_Grafana_Wordfence::build_window_count_select_sql(
                    'brute_xmlrpc',
                    Simula_Wordfence_Grafana_Wordfence::brute_force_xmlrpc_where_sql($table),
                    $time_identifier,
                    $windows
                );
            }

            $window_counts = $wpdb->get_row(
                "SELECT
                    " . implode(",\n                    ", $window_selects) . "
                FROM `$table`
                WHERE $time_identifier >= " . (int) $windows['7d'],
                ARRAY_A
            );
        }

        $status_counts = [];
        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'blocked_events_by_status_24h')) {
            $status_column = Simula_Wordfence_Grafana_Wordfence::first_available_column($table, ['statusCode', 'status']);
            if ($status_column !== null) {
                $status_identifier = '`' . str_replace('`', '``', (string) $status_column) . '`';
                $status_counts     = $wpdb->get_results(
                    "SELECT $status_identifier AS status_code, COUNT(*) AS count_total
                    FROM `$table`
                    WHERE $time_identifier >= " . (int) $windows['24h'] . " AND $where_sql
                    GROUP BY $status_identifier
                    ORDER BY count_total DESC",
                    ARRAY_A
                );
            }
        }

        $top_attack_sources = [];
        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'top_attack_sources_24h')) {
            $top_attack_sources = Simula_Wordfence_Grafana_Wordfence::collect_top_attack_sources($table, $time_identifier, $where_sql, $windows['24h']);
        }

        $lockout_counts = [];
        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'locked_out_total')) {
            $lockout_counts = Simula_Wordfence_Grafana_Wordfence::collect_lockout_counts($now);
        }

        $two_factor_metrics = [];
        if (
            Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'two_factor_enabled') ||
            Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'two_factor_protected_users_total')
        ) {
            $two_factor_metrics = Simula_Wordfence_Grafana_Wordfence::collect_two_factor_metrics();
        }

        $scan_issue_metrics = [];
        if ($needs_scan_metrics) {
            $scan_issue_metrics = Simula_Wordfence_Grafana_Wordfence::collect_scan_issue_metrics();
        }

        if ($wpdb->last_error !== '') {
            return Simula_Wordfence_Grafana_Output::write_metrics(
                $options['prom_file'],
                Simula_Wordfence_Grafana_Output::build_failure_metrics($options, $now, $wpdb->last_error),
                $wpdb->last_error,
                $state
            );
        }

        if ($needs_blocked_total) {
            $max_id = isset($incremental['max_id']) ? (int) $incremental['max_id'] : $last_id;
            if ($max_id > $last_id) {
                $total += isset($incremental['new_count']) ? (int) $incremental['new_count'] : 0;
                $last_id = $max_id;
            }
        }

        $metrics = [];

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'export_success')) {
            $metrics[] = '# HELP ' . $prefix . '_export_success Whether the last Wordfence metrics export succeeded.';
            $metrics[] = '# TYPE ' . $prefix . '_export_success gauge';
            $metrics[] = $prefix . '_export_success{site="' . $site . '"} 1';
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'plugin_info')) {
            $metrics[] = '# HELP ' . $prefix . '_plugin_info Plugin metadata for the exporter.';
            $metrics[] = '# TYPE ' . $prefix . '_plugin_info gauge';
            $metrics[] = $prefix . '_plugin_info{site="' . $site . '",version="' . Simula_Wordfence_Grafana_Output::escape_label(Simula_Wordfence_Grafana_Config::VERSION) . '"} 1';
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'last_export_timestamp_seconds')) {
            $metrics[] = '# HELP ' . $prefix . '_last_export_timestamp_seconds Unix timestamp of the last successful export.';
            $metrics[] = '# TYPE ' . $prefix . '_last_export_timestamp_seconds gauge';
            $metrics[] = $prefix . '_last_export_timestamp_seconds{site="' . $site . '"} ' . $now;
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'enabled')) {
            $metrics[] = '# HELP ' . $prefix . '_enabled Whether scheduled exporting is enabled.';
            $metrics[] = '# TYPE ' . $prefix . '_enabled gauge';
            $metrics[] = $prefix . '_enabled{site="' . $site . '"} ' . (int) !empty($options['enabled']);
        }

        if ($needs_blocked_total) {
            $metrics[] = '# HELP ' . $prefix . '_blocked_events_total Cumulative count of newly observed blocked Wordfence hits.';
            $metrics[] = '# TYPE ' . $prefix . '_blocked_events_total counter';
            $metrics[] = $prefix . '_blocked_events_total{site="' . $site . '"} ' . Simula_Wordfence_Grafana_Output::format_number($total);
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'blocked_events_window')) {
            $metrics[] = '# HELP ' . $prefix . '_blocked_events_window Blocked Wordfence hits seen within recent windows.';
            $metrics[] = '# TYPE ' . $prefix . '_blocked_events_window gauge';
            $metrics[] = $prefix . '_blocked_events_window{site="' . $site . '",window="5m"} ' . self::window_metric_value($window_counts, 'blocked', '5m');
            $metrics[] = $prefix . '_blocked_events_window{site="' . $site . '",window="1h"} ' . self::window_metric_value($window_counts, 'blocked', '1h');
            $metrics[] = $prefix . '_blocked_events_window{site="' . $site . '",window="24h"} ' . self::window_metric_value($window_counts, 'blocked', '24h');
            $metrics[] = $prefix . '_blocked_events_window{site="' . $site . '",window="7d"} ' . self::window_metric_value($window_counts, 'blocked', '7d');
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'blocked_events_by_status_24h')) {
            $metrics[] = '# HELP ' . $prefix . '_blocked_events_by_status_24h Blocked Wordfence hits in the last 24 hours grouped by HTTP status code.';
            $metrics[] = '# TYPE ' . $prefix . '_blocked_events_by_status_24h gauge';

            foreach ((array) $status_counts as $row) {
                $status = isset($row['status_code']) && $row['status_code'] !== '' ? (string) $row['status_code'] : 'unknown';
                $count  = isset($row['count_total']) ? (int) $row['count_total'] : 0;
                $metrics[] = $prefix . '_blocked_events_by_status_24h{site="' . $site . '",status="' . Simula_Wordfence_Grafana_Output::escape_label($status) . '"} ' . $count;
            }
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'failed_login_attempts_window')) {
            $metrics[] = '# HELP ' . $prefix . '_failed_login_attempts_window Failed login attempts observed within recent windows.';
            $metrics[] = '# TYPE ' . $prefix . '_failed_login_attempts_window gauge';
            $metrics[] = $prefix . '_failed_login_attempts_window{site="' . $site . '",window="5m"} ' . self::window_metric_value($window_counts, 'failed_login', '5m');
            $metrics[] = $prefix . '_failed_login_attempts_window{site="' . $site . '",window="1h"} ' . self::window_metric_value($window_counts, 'failed_login', '1h');
            $metrics[] = $prefix . '_failed_login_attempts_window{site="' . $site . '",window="24h"} ' . self::window_metric_value($window_counts, 'failed_login', '24h');
            $metrics[] = $prefix . '_failed_login_attempts_window{site="' . $site . '",window="7d"} ' . self::window_metric_value($window_counts, 'failed_login', '7d');
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'locked_out_total')) {
            $metrics[] = '# HELP ' . $prefix . '_locked_out_total Current Wordfence lockout totals by target type when available.';
            $metrics[] = '# TYPE ' . $prefix . '_locked_out_total gauge';
            $metrics[] = $prefix . '_locked_out_total{site="' . $site . '",target="ip"} ' . (int) ($lockout_counts['ip'] ?? 0);
            $metrics[] = $prefix . '_locked_out_total{site="' . $site . '",target="user"} ' . (int) ($lockout_counts['user'] ?? 0);
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'two_factor_enabled')) {
            $metrics[] = '# HELP ' . $prefix . '_two_factor_enabled Whether Wordfence two-factor authentication appears to be configured.';
            $metrics[] = '# TYPE ' . $prefix . '_two_factor_enabled gauge';
            $metrics[] = $prefix . '_two_factor_enabled{site="' . $site . '"} ' . (int) ($two_factor_metrics['enabled'] ?? 0);
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'two_factor_protected_users_total')) {
            $metrics[] = '# HELP ' . $prefix . '_two_factor_protected_users_total Count of users with Wordfence two-factor secrets configured.';
            $metrics[] = '# TYPE ' . $prefix . '_two_factor_protected_users_total gauge';
            $metrics[] = $prefix . '_two_factor_protected_users_total{site="' . $site . '"} ' . (int) ($two_factor_metrics['protected_users'] ?? 0);
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'scan_issues_by_severity')) {
            $metrics[] = '# HELP ' . $prefix . '_scan_issues_by_severity Current Wordfence scan issues grouped by severity.';
            $metrics[] = '# TYPE ' . $prefix . '_scan_issues_by_severity gauge';
            foreach ((array) ($scan_issue_metrics['severity'] ?? []) as $row) {
                $severity = isset($row['severity']) && $row['severity'] !== '' ? strtolower((string) $row['severity']) : 'unknown';
                $count    = isset($row['count_total']) ? (int) $row['count_total'] : 0;
                $metrics[] = $prefix . '_scan_issues_by_severity{site="' . $site . '",severity="' . Simula_Wordfence_Grafana_Output::escape_label($severity) . '"} ' . $count;
            }
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'scan_findings_total')) {
            $metrics[] = '# HELP ' . $prefix . '_scan_findings_total Current Wordfence scan findings for selected categories.';
            $metrics[] = '# TYPE ' . $prefix . '_scan_findings_total gauge';
            $metrics[] = $prefix . '_scan_findings_total{site="' . $site . '",category="malware"} ' . (int) ($scan_issue_metrics['malware'] ?? 0);
            $metrics[] = $prefix . '_scan_findings_total{site="' . $site . '",category="file_change"} ' . (int) ($scan_issue_metrics['file_change'] ?? 0);
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'rate_limited_events_window')) {
            $metrics[] = '# HELP ' . $prefix . '_rate_limited_events_window Rate-limited or throttled requests observed within recent windows.';
            $metrics[] = '# TYPE ' . $prefix . '_rate_limited_events_window gauge';
            $metrics[] = $prefix . '_rate_limited_events_window{site="' . $site . '",window="5m"} ' . self::window_metric_value($window_counts, 'rate_limited', '5m');
            $metrics[] = $prefix . '_rate_limited_events_window{site="' . $site . '",window="1h"} ' . self::window_metric_value($window_counts, 'rate_limited', '1h');
            $metrics[] = $prefix . '_rate_limited_events_window{site="' . $site . '",window="24h"} ' . self::window_metric_value($window_counts, 'rate_limited', '24h');
            $metrics[] = $prefix . '_rate_limited_events_window{site="' . $site . '",window="7d"} ' . self::window_metric_value($window_counts, 'rate_limited', '7d');
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'top_attack_sources_24h')) {
            $metrics[] = '# HELP ' . $prefix . '_top_attack_sources_24h Top blocked attack sources over the last 24 hours.';
            $metrics[] = '# TYPE ' . $prefix . '_top_attack_sources_24h gauge';
            foreach ((array) $top_attack_sources as $row) {
                $source_type = isset($row['source_type']) ? (string) $row['source_type'] : 'unknown';
                $source_name = isset($row['source']) ? (string) $row['source'] : 'unknown';
                $count       = isset($row['count_total']) ? (int) $row['count_total'] : 0;
                $metrics[] = $prefix . '_top_attack_sources_24h{site="' . $site . '",source_type="' . Simula_Wordfence_Grafana_Output::escape_label($source_type) . '",source="' . Simula_Wordfence_Grafana_Output::escape_label($source_name) . '"} ' . $count;
            }
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'brute_force_events_window')) {
            $metrics[] = '# HELP ' . $prefix . '_brute_force_events_window Brute-force activity observed within recent windows.';
            $metrics[] = '# TYPE ' . $prefix . '_brute_force_events_window gauge';
            foreach (Simula_Wordfence_Grafana_Config::WINDOWS as $window) {
                $metrics[] = $prefix . '_brute_force_events_window{site="' . $site . '",vector="username",window="' . $window . '"} ' . self::window_metric_value($window_counts, 'brute_username', $window);
                $metrics[] = $prefix . '_brute_force_events_window{site="' . $site . '",vector="xmlrpc",window="' . $window . '"} ' . self::window_metric_value($window_counts, 'brute_xmlrpc', $window);
            }
        }

        if (Simula_Wordfence_Grafana_Settings::is_metric_enabled($options, 'vulnerability_findings_total')) {
            $metrics[] = '# HELP ' . $prefix . '_vulnerability_findings_total Current Wordfence scan findings indicating outdated or vulnerable components.';
            $metrics[] = '# TYPE ' . $prefix . '_vulnerability_findings_total gauge';
            $metrics[] = $prefix . '_vulnerability_findings_total{site="' . $site . '",component="core"} ' . (int) ($scan_issue_metrics['vulnerabilities']['core'] ?? 0);
            $metrics[] = $prefix . '_vulnerability_findings_total{site="' . $site . '",component="plugin"} ' . (int) ($scan_issue_metrics['vulnerabilities']['plugin'] ?? 0);
            $metrics[] = $prefix . '_vulnerability_findings_total{site="' . $site . '",component="theme"} ' . (int) ($scan_issue_metrics['vulnerabilities']['theme'] ?? 0);
        }

        $state['blocked_total'] = $total;
        $state['last_export']   = $now;
        $state['last_id']       = $last_id;

        return Simula_Wordfence_Grafana_Output::write_metrics(
            $options['prom_file'],
            empty($metrics) ? '' : implode("\n", $metrics) . "\n",
            '',
            $state
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

        $options = Simula_Wordfence_Grafana_Settings::get_options();
        $state   = Simula_Wordfence_Grafana_Settings::get_state();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Simula Wordfence Grafana Metrics', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></h1>
            <p><?php echo esc_html__('Exports Wordfence block telemetry into a Prometheus .prom file for node_exporter textfile collection.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>

            <?php settings_errors('wfne_metrics'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('wfne_metrics'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable scheduled exports', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Simula_Wordfence_Grafana_Config::OPTION); ?>[enabled]" value="1" <?php checked($options['enabled'], 1); ?> />
                                <?php echo esc_html__('Run the exporter on a recurring WP-Cron schedule.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?>
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
                            <p class="description"><?php echo esc_html__('Controls how often scheduled exports run when enabled.', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN); ?></p>
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
                <?php submit_button(); ?>
            </form>

            <hr />

            <form method="post">
                <?php wp_nonce_field('wfne_export_now'); ?>
                <?php submit_button(__('Export now', Simula_Wordfence_Grafana_Config::TEXT_DOMAIN), 'secondary', 'wfne_export_now'); ?>
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
                </tbody>
            </table>
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

    /** Removes plugin data and deletes the generated metrics file on uninstall. */
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
