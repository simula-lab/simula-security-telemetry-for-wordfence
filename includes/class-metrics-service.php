<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
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
            'admin_identity_mode' => (string) ($options['admin_identity_mode'] ?? 'hashed'),
            'window_counts'      => [],
            'status_counts'      => [],
            'top_attack_sources' => [],
            'lockout_counts'     => [],
            'two_factor_metrics' => [],
            'scan_issue_metrics' => [],
            'source_freshness'   => [],
            'wordfence_posture'  => [],
            'wordpress_posture'  => [],
            'wordpress_drift'    => [],
            'account_metrics'    => [],
            'cron_option_metrics' => [],
            'ioc_metrics'        => [],
            'state_updates'      => [],
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
                $data['wordpress_posture'] = Simula_Security_Telemetry_Wordfence_Collector::collect_wordpress_posture(
                    !empty($flags['needs_plugin_inventory']),
                    !empty($flags['needs_admin_inventory']),
                    (string) ($options['admin_identity_mode'] ?? 'hashed')
                );
            }

            if ($flags['needs_wordpress_drift']) {
                $result = Simula_Security_Telemetry_WordPress_Collector::collect_sprint6_metrics($state, $now, $data['windows']);
                $data['wordpress_drift'] = is_array($result['metrics'] ?? null) ? $result['metrics'] : [];
                $data['state_updates'] = array_merge($data['state_updates'], is_array($result['state'] ?? null) ? $result['state'] : []);
            }

            if ($flags['needs_account_metrics']) {
                $result = Simula_Security_Telemetry_WordPress_Collector::collect_account_metrics($state, $now, $data['windows']);
                $data['account_metrics'] = is_array($result['metrics'] ?? null) ? $result['metrics'] : [];
                $data['state_updates'] = array_merge($data['state_updates'], is_array($result['state'] ?? null) ? $result['state'] : []);
            }

            if ($flags['needs_cron_option_metrics']) {
                $result = Simula_Security_Telemetry_WordPress_Collector::collect_cron_option_metrics($state, $now, $data['windows']);
                $data['cron_option_metrics'] = is_array($result['metrics'] ?? null) ? $result['metrics'] : [];
                $data['state_updates'] = array_merge($data['state_updates'], is_array($result['state'] ?? null) ? $result['state'] : []);
            }

            if ($flags['needs_ioc_metrics']) {
                $data['ioc_metrics'] = Simula_Security_Telemetry_WordPress_Collector::collect_ioc_metrics($now, $data['windows']);
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
            $flags['wordpress_version_info'] ||
            $flags['core_update_available'] ||
            $flags['plugin_update_available_total'] ||
            $flags['plugins_installed_total'] ||
            $flags['plugins_active_total'] ||
            $flags['plugins_inactive_total'] ||
            $flags['plugins_network_active_total'] ||
            $flags['plugin_inventory_info'] ||
            $flags['theme_update_available_total'] ||
            $flags['admin_users_total'] ||
            $flags['admin_users_without_2fa_total'] ||
            $flags['admin_user_info'];
        $flags['needs_plugin_inventory'] =
            $flags['plugins_installed_total'] ||
            $flags['plugins_active_total'] ||
            $flags['plugins_inactive_total'] ||
            $flags['plugins_network_active_total'] ||
            $flags['plugin_inventory_info'];
        $flags['needs_admin_inventory'] = $flags['admin_user_info'];
        $flags['needs_wordpress_drift'] =
            $flags['users_total'] ||
            $flags['users_created_window'] ||
            $flags['admin_users_created_window'] ||
            $flags['admin_users_modified_window'] ||
            $flags['roles_total'] ||
            $flags['role_capabilities_total'] ||
            $flags['unexpected_admin_capabilities_total'] ||
            $flags['users_can_register_enabled'] ||
            $flags['default_role_info'] ||
            $flags['file_edit_allowed'] ||
            $flags['file_mods_allowed'] ||
            $flags['debug_enabled'] ||
            $flags['debug_display_enabled'] ||
            $flags['xmlrpc_enabled'] ||
            $flags['rest_api_enabled'] ||
            $flags['search_engine_visibility_enabled'] ||
            $flags['home_url_info'] ||
            $flags['site_url_info'] ||
            $flags['plugins_added_window'] ||
            $flags['plugins_removed_window'] ||
            $flags['plugins_activated_window'] ||
            $flags['plugins_deactivated_window'] ||
            $flags['mu_plugins_total'] ||
            $flags['dropins_total'] ||
            $flags['active_theme_info'] ||
            $flags['themes_installed_total'] ||
            $flags['themes_update_available_total'];
        $flags['needs_account_metrics'] =
            $flags['successful_logins_window'] ||
            $flags['password_resets_window'] ||
            $flags['user_email_changes_window'] ||
            $flags['admin_users_modified_window'] ||
            $flags['application_passwords_total'] ||
            $flags['admin_application_passwords_total'] ||
            $flags['sessions_total'];
        $flags['needs_cron_option_metrics'] =
            $flags['cron_events_total'] ||
            $flags['cron_hooks_total'] ||
            $flags['cron_new_hooks_window'] ||
            $flags['cron_scheduled_events_total'] ||
            $flags['cron_suspicious_hooks_total'] ||
            $flags['options_total'] ||
            $flags['autoload_options_total'] ||
            $flags['autoload_options_bytes'] ||
            $flags['options_changed_window'] ||
            $flags['new_autoload_options_window'] ||
            $flags['sensitive_options_changed_window'];
        $flags['needs_ioc_metrics'] =
            $flags['posts_modified_window'] ||
            $flags['pages_modified_window'] ||
            $flags['posts_with_script_tags_total'] ||
            $flags['posts_with_iframe_tags_total'] ||
            $flags['posts_with_suspicious_redirects_total'] ||
            $flags['recent_admin_post_edits_window'] ||
            $flags['upload_php_files_total'] ||
            $flags['upload_executable_files_total'] ||
            $flags['recent_upload_php_files_window'] ||
            $flags['plugin_files_modified_window'] ||
            $flags['theme_files_modified_window'] ||
            $flags['wp_content_recently_modified_files_total'];

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
        $metrics = array_merge($metrics, self::render_wordpress_drift_metrics($data));
        $metrics = array_merge($metrics, self::render_account_metrics($data));
        $metrics = array_merge($metrics, self::render_cron_option_metrics($data));
        $metrics = array_merge($metrics, self::render_ioc_metrics($data));

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

        if (!empty($flags['next_export_timestamp_seconds'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_next_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the next scheduled fast exporter run.',
                [
                    ['labels' => ['site' => $site], 'value' => Simula_Security_Telemetry_Settings::next_scheduled_timestamp(Simula_Security_Telemetry_Config::CRON_HOOK)],
                ]
            );
        }

        if (!empty($flags['next_slow_export_timestamp_seconds'])) {
            Simula_Security_Telemetry_Output::append_metric_family(
                $metrics,
                $prefix . '_next_slow_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the next scheduled slow collector run.',
                [
                    ['labels' => ['site' => $site], 'value' => Simula_Security_Telemetry_Settings::next_scheduled_timestamp(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK)],
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
        $wordpress_version   = Simula_Security_Telemetry_Output::escape_label((string) ($wordpress_posture['wordpress_version'] ?? 'unknown'));
        $admin_identity_mode = (string) ($data['admin_identity_mode'] ?? 'hashed');

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

        if (!empty($flags['wordpress_version_info'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_wordpress_version_info', 'gauge', 'WordPress core version metadata.', [['labels' => ['site' => $site, 'version' => $wordpress_version], 'value' => 1]]);
        }

        if (!empty($flags['core_update_available'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_core_update_available', 'gauge', 'Whether a WordPress core update is available.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['core_update_available'] ?? 0)]]);
        }

        if (!empty($flags['plugin_update_available_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_plugin_update_available_total', 'gauge', 'Number of plugin updates available.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['plugin_update_available_total'] ?? 0)]]);
        }

        if (!empty($flags['plugins_installed_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_plugins_installed_total', 'gauge', 'Number of installed WordPress plugins.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['plugins_installed_total'] ?? 0)]]);
        }

        if (!empty($flags['plugins_active_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_plugins_active_total', 'gauge', 'Number of site-active WordPress plugins.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['plugins_active_total'] ?? 0)]]);
        }

        if (!empty($flags['plugins_inactive_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_plugins_inactive_total', 'gauge', 'Number of inactive WordPress plugins.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['plugins_inactive_total'] ?? 0)]]);
        }

        if (!empty($flags['plugins_network_active_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_plugins_network_active_total', 'gauge', 'Number of network-active WordPress plugins.', [['labels' => ['site' => $site], 'value' => (int) ($wordpress_posture['plugins_network_active_total'] ?? 0)]]);
        }

        if (!empty($flags['plugin_inventory_info'])) {
            $samples = [];
            foreach ((array) ($wordpress_posture['plugin_inventory'] ?? []) as $plugin) {
                $samples[] = [
                    'labels' => [
                        'site'             => $site,
                        'plugin_file'      => Simula_Security_Telemetry_Output::escape_label((string) ($plugin['plugin_file'] ?? 'unknown')),
                        'name'             => Simula_Security_Telemetry_Output::escape_label((string) ($plugin['name'] ?? 'unknown')),
                        'version'          => Simula_Security_Telemetry_Output::escape_label((string) ($plugin['version'] ?? 'unknown')),
                        'state'            => Simula_Security_Telemetry_Output::escape_label((string) ($plugin['state'] ?? 'inactive')),
                        'update_available' => empty($plugin['update_available']) ? '0' : '1',
                    ],
                    'value' => 1,
                ];
            }

            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_plugin_inventory_info', 'gauge', 'Installed WordPress plugin inventory metadata.', $samples);
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

        if (!empty($flags['admin_user_info']) && $admin_identity_mode !== 'disabled') {
            $samples = [];
            foreach ((array) ($wordpress_posture['admin_user_inventory'] ?? []) as $admin_user) {
                if ($admin_identity_mode === 'id_only') {
                    $labels = [
                        'site'               => $site,
                        'user_id'            => Simula_Security_Telemetry_Output::escape_label((string) ($admin_user['user_id'] ?? '0')),
                        'two_factor_enabled' => empty($admin_user['two_factor_enabled']) ? '0' : '1',
                    ];
                } else {
                    $labels = [
                        'site'               => $site,
                        'user_id_hash'       => Simula_Security_Telemetry_Output::escape_label((string) ($admin_user['user_id_hash'] ?? 'unknown')),
                        'login_hash'         => Simula_Security_Telemetry_Output::escape_label((string) ($admin_user['login_hash'] ?? 'unknown')),
                        'display_name_hash'  => Simula_Security_Telemetry_Output::escape_label((string) ($admin_user['display_name_hash'] ?? 'unknown')),
                        'two_factor_enabled' => empty($admin_user['two_factor_enabled']) ? '0' : '1',
                    ];
                }

                $samples[] = [
                    'labels' => $labels,
                    'value'  => 1,
                ];
            }

            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_admin_user_info', 'gauge', 'Administrator user inventory metadata.', $samples);
        }

        return $metrics;
    }

    /** Renders WordPress settings, role, user, plugin, and theme drift metric families. */
    private static function render_wordpress_drift_metrics($data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];
        $drift   = is_array($data['wordpress_drift'] ?? null) ? $data['wordpress_drift'] : [];
        $roles   = ['administrator', 'editor', 'author', 'contributor', 'subscriber', 'other'];
        $user_roles = is_array($drift['user_roles'] ?? null) ? $drift['user_roles'] : [];
        $settings = is_array($drift['settings'] ?? null) ? $drift['settings'] : [];
        $asset_posture = is_array($drift['asset_posture'] ?? null) ? $drift['asset_posture'] : [];
        $asset_windows = is_array($drift['asset_drift_windows'] ?? null) ? $drift['asset_drift_windows'] : [];

        if (!empty($flags['users_total'])) {
            $samples = [];
            foreach ($roles as $role) {
                $samples[] = ['labels' => ['site' => $site, 'role' => $role], 'value' => (int) ($user_roles['users_total'][$role] ?? 0)];
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_users_total', 'gauge', 'WordPress users grouped by bounded role.', $samples);
        }

        if (!empty($flags['users_created_window'])) {
            $samples = [];
            foreach ($roles as $role) {
                foreach (self::slow_windows() as $window) {
                    $samples[] = ['labels' => ['site' => $site, 'role' => $role, 'window' => $window], 'value' => (int) ($user_roles['users_created'][$role][$window] ?? 0)];
                }
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_users_created_window', 'gauge', 'Recently created WordPress users grouped by bounded role and window.', $samples);
        }

        if (!empty($flags['admin_users_created_window'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_admin_users_created_window', 'gauge', 'Recently created administrator users grouped by window.', self::simple_window_samples($site, $user_roles['admin_users_created'] ?? []));
        }

        if (!empty($flags['admin_users_modified_window'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_admin_users_modified_window', 'gauge', 'Administrator profile modifications observed by plugin hooks grouped by window.', self::simple_window_samples($site, $data['account_metrics']['event_windows']['admin_modified']['administrator'] ?? []));
        }

        if (!empty($flags['roles_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_roles_total', 'gauge', 'Number of registered WordPress roles.', [['labels' => ['site' => $site], 'value' => (int) ($user_roles['roles_total'] ?? 0)]]);
        }

        if (!empty($flags['role_capabilities_total'])) {
            $samples = [];
            foreach ($roles as $role) {
                $samples[] = ['labels' => ['site' => $site, 'role' => $role], 'value' => (int) ($user_roles['role_capabilities_total'][$role] ?? 0)];
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_role_capabilities_total', 'gauge', 'Role capability counts grouped by bounded role.', $samples);
        }

        if (!empty($flags['unexpected_admin_capabilities_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_unexpected_admin_capabilities_total', 'gauge', 'Administrator-level capabilities assigned to non-administrator roles.', [['labels' => ['site' => $site], 'value' => (int) ($user_roles['unexpected_admin_capabilities_total'] ?? 0)]]);
        }

        foreach ([
            'users_can_register_enabled' => 'Whether public user registration is enabled.',
            'file_edit_allowed' => 'Whether WordPress file editing appears allowed.',
            'file_mods_allowed' => 'Whether WordPress file modifications appear allowed.',
            'debug_enabled' => 'Whether WP_DEBUG is enabled.',
            'debug_display_enabled' => 'Whether WP_DEBUG_DISPLAY is enabled.',
            'xmlrpc_enabled' => 'Whether XML-RPC appears enabled.',
            'rest_api_enabled' => 'Whether the WordPress REST API appears enabled.',
            'search_engine_visibility_enabled' => 'Whether search engine visibility is discouraged.',
        ] as $metric_key => $help) {
            if (!empty($flags[$metric_key])) {
                Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_' . $metric_key, 'gauge', $help, [['labels' => ['site' => $site], 'value' => (int) ($settings[$metric_key] ?? 0)]]);
            }
        }

        if (!empty($flags['default_role_info'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_default_role_info', 'gauge', 'Default new-user role metadata.', [['labels' => ['site' => $site, 'role' => Simula_Security_Telemetry_Output::escape_label((string) ($settings['default_role'] ?? 'other'))], 'value' => 1]]);
        }

        if (!empty($flags['home_url_info'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_home_url_info', 'gauge', 'Hashed WordPress home URL metadata.', [['labels' => ['site' => $site, 'hash' => Simula_Security_Telemetry_Output::escape_label((string) ($settings['home_url_hash'] ?? 'unknown'))], 'value' => 1]]);
        }

        if (!empty($flags['site_url_info'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_site_url_info', 'gauge', 'Hashed WordPress site URL metadata.', [['labels' => ['site' => $site, 'hash' => Simula_Security_Telemetry_Output::escape_label((string) ($settings['site_url_hash'] ?? 'unknown'))], 'value' => 1]]);
        }

        foreach ([
            'plugins_added_window' => 'plugins_added',
            'plugins_removed_window' => 'plugins_removed',
            'plugins_activated_window' => 'plugins_activated',
            'plugins_deactivated_window' => 'plugins_deactivated',
        ] as $metric_key => $event_key) {
            if (!empty($flags[$metric_key])) {
                Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_' . $metric_key, 'gauge', 'Plugin state changes detected by slow-snapshot comparison.', self::simple_window_samples($site, $asset_windows[$event_key] ?? []));
            }
        }

        foreach ([
            'mu_plugins_total' => 'Number of must-use plugins.',
            'dropins_total' => 'Number of WordPress drop-ins.',
            'themes_installed_total' => 'Number of installed WordPress themes.',
            'themes_update_available_total' => 'Number of available WordPress theme updates.',
        ] as $metric_key => $help) {
            if (!empty($flags[$metric_key])) {
                Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_' . $metric_key, 'gauge', $help, [['labels' => ['site' => $site], 'value' => (int) ($asset_posture[$metric_key] ?? 0)]]);
            }
        }

        if (!empty($flags['active_theme_info'])) {
            $theme = is_array($asset_posture['active_theme'] ?? null) ? $asset_posture['active_theme'] : [];
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_active_theme_info', 'gauge', 'Active theme metadata.', [[
                'labels' => [
                    'site' => $site,
                    'theme' => Simula_Security_Telemetry_Output::escape_label((string) ($theme['theme'] ?? 'unknown')),
                    'version' => Simula_Security_Telemetry_Output::escape_label((string) ($theme['version'] ?? 'unknown')),
                ],
                'value' => 1,
            ]]);
        }

        return $metrics;
    }

    /** Renders account takeover and session metric families. */
    private static function render_account_metrics($data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];
        $account = is_array($data['account_metrics'] ?? null) ? $data['account_metrics'] : [];
        $roles   = ['administrator', 'editor', 'author', 'contributor', 'subscriber', 'other'];

        foreach ([
            'successful_logins_window' => 'successful_login',
            'password_resets_window' => 'password_reset',
            'user_email_changes_window' => 'email_change',
        ] as $metric_key => $event_key) {
            if (empty($flags[$metric_key])) {
                continue;
            }
            $samples = [];
            foreach ($roles as $role) {
                foreach (self::slow_windows() as $window) {
                    $samples[] = [
                        'labels' => ['site' => $site, 'role' => $role, 'window' => $window],
                        'value' => (int) ($account['event_windows'][$event_key][$role][$window] ?? 0),
                    ];
                }
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_' . $metric_key, 'gauge', 'Account events observed by plugin hooks grouped by bounded role and window.', $samples);
        }

        if (!empty($flags['application_passwords_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_application_passwords_total', 'gauge', 'Total stored WordPress application passwords.', [['labels' => ['site' => $site], 'value' => (int) ($account['application_passwords_total'] ?? 0)]]);
        }

        if (!empty($flags['admin_application_passwords_total'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_admin_application_passwords_total', 'gauge', 'Stored WordPress application passwords owned by administrator users.', [['labels' => ['site' => $site], 'value' => (int) ($account['admin_application_passwords_total'] ?? 0)]]);
        }

        if (!empty($flags['sessions_total'])) {
            $samples = [];
            foreach ($roles as $role) {
                $samples[] = ['labels' => ['site' => $site, 'role' => $role], 'value' => (int) ($account['sessions_by_role'][$role] ?? 0)];
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_sessions_total', 'gauge', 'Stored WordPress session-token counts grouped by bounded role.', $samples);
        }

        return $metrics;
    }

    /** Renders cron and option persistence metric families. */
    private static function render_cron_option_metrics($data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];
        $signals = is_array($data['cron_option_metrics'] ?? null) ? $data['cron_option_metrics'] : [];
        $cron    = is_array($signals['cron'] ?? null) ? $signals['cron'] : [];
        $options = is_array($signals['options'] ?? null) ? $signals['options'] : [];
        $windows = is_array($signals['event_windows'] ?? null) ? $signals['event_windows'] : [];

        foreach ([
            'cron_events_total' => 'Total scheduled WordPress cron events.',
            'cron_hooks_total' => 'Number of distinct scheduled WordPress cron hooks.',
            'cron_suspicious_hooks_total' => 'Count of cron hooks matching suspicious persistence-oriented names.',
            'options_total' => 'Total rows in the WordPress options table.',
            'autoload_options_total' => 'Total autoloaded WordPress options.',
            'autoload_options_bytes' => 'Approximate byte size of autoloaded WordPress option values.',
        ] as $metric_key => $help) {
            if (empty($flags[$metric_key])) {
                continue;
            }
            $source = strpos($metric_key, 'cron_') === 0 ? $cron : $options;
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_' . $metric_key, 'gauge', $help, [['labels' => ['site' => $site], 'value' => (int) ($source[$metric_key] ?? 0)]]);
        }

        if (!empty($flags['cron_new_hooks_window'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_cron_new_hooks_window', 'gauge', 'New cron hooks detected by slow-snapshot comparison.', self::simple_window_samples($site, $windows['cron_new_hooks'] ?? []));
        }

        if (!empty($flags['cron_scheduled_events_total'])) {
            $samples = [];
            foreach (['single', 'hourly', 'twicedaily', 'daily', 'custom'] as $recurrence) {
                $samples[] = ['labels' => ['site' => $site, 'recurrence' => $recurrence], 'value' => (int) ($cron['recurrences'][$recurrence] ?? 0)];
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_cron_scheduled_events_total', 'gauge', 'Scheduled cron events grouped by bounded recurrence label.', $samples);
        }

        if (!empty($flags['options_changed_window'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_options_changed_window', 'gauge', 'Sensitive option changes detected by slow-snapshot comparison.', self::simple_window_samples($site, $windows['options_changed'] ?? []));
        }

        if (!empty($flags['new_autoload_options_window'])) {
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_new_autoload_options_window', 'gauge', 'New autoloaded options detected by slow-snapshot comparison.', self::simple_window_samples($site, $windows['new_autoload_options'] ?? []));
        }

        if (!empty($flags['sensitive_options_changed_window'])) {
            $samples = [];
            foreach (['site_url', 'users', 'mail', 'auth', 'cron', 'plugins', 'other'] as $group) {
                foreach (self::slow_windows() as $window) {
                    $samples[] = [
                        'labels' => ['site' => $site, 'option_group' => $group, 'window' => $window],
                        'value' => (int) ($signals['sensitive_option_windows'][$group][$window] ?? 0),
                    ];
                }
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_sensitive_options_changed_window', 'gauge', 'Sensitive option changes grouped by bounded option group and window.', $samples);
        }

        return $metrics;
    }

    /** Renders content and filesystem IoC metric families. */
    private static function render_ioc_metrics($data) {
        $metrics = [];
        $flags   = $data['flags'];
        $prefix  = $data['prefix'];
        $site    = $data['site'];
        $ioc     = is_array($data['ioc_metrics'] ?? null) ? $data['ioc_metrics'] : [];
        $content = is_array($ioc['content'] ?? null) ? $ioc['content'] : [];
        $files   = is_array($ioc['files'] ?? null) ? $ioc['files'] : [];

        if (!empty($flags['posts_modified_window'])) {
            $samples = [];
            foreach (['post', 'page', 'attachment', 'other'] as $post_type) {
                foreach (self::slow_windows() as $window) {
                    $samples[] = ['labels' => ['site' => $site, 'post_type' => $post_type, 'window' => $window], 'value' => (int) ($content['posts_modified'][$post_type][$window] ?? 0)];
                }
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_posts_modified_window', 'gauge', 'Recently modified content grouped by bounded post type and window.', $samples);
        }

        foreach ([
            'pages_modified_window' => ['source' => $content['pages_modified'] ?? [], 'help' => 'Recently modified WordPress pages grouped by window.'],
            'recent_admin_post_edits_window' => ['source' => $content['recent_admin_post_edits'] ?? [], 'help' => 'Recent content modifications by administrator users grouped by window.'],
            'recent_upload_php_files_window' => ['source' => $files['recent_upload_php_files'] ?? [], 'help' => 'Recently modified PHP-like files under uploads grouped by window.'],
            'plugin_files_modified_window' => ['source' => $files['plugin_files_modified'] ?? [], 'help' => 'Recently modified files under plugins grouped by window.'],
            'theme_files_modified_window' => ['source' => $files['theme_files_modified'] ?? [], 'help' => 'Recently modified files under themes grouped by window.'],
        ] as $metric_key => $config) {
            if (!empty($flags[$metric_key])) {
                Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_' . $metric_key, 'gauge', $config['help'], self::simple_window_samples($site, $config['source']));
            }
        }

        foreach ([
            'posts_with_script_tags_total' => ['source' => $content['script_tags'] ?? [], 'help' => 'Published content containing script tags grouped by bounded post type.'],
            'posts_with_iframe_tags_total' => ['source' => $content['iframe_tags'] ?? [], 'help' => 'Published content containing iframe tags grouped by bounded post type.'],
            'posts_with_suspicious_redirects_total' => ['source' => $content['suspicious_redirects'] ?? [], 'help' => 'Published content containing simple suspicious redirect indicators grouped by bounded post type.'],
        ] as $metric_key => $config) {
            if (empty($flags[$metric_key])) {
                continue;
            }
            $samples = [];
            foreach (['post', 'page', 'other'] as $post_type) {
                $samples[] = ['labels' => ['site' => $site, 'post_type' => $post_type], 'value' => (int) ($config['source'][$post_type] ?? 0)];
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_' . $metric_key, 'gauge', $config['help'], $samples);
        }

        foreach ([
            'upload_php_files_total' => 'PHP-like files found under uploads.',
            'upload_executable_files_total' => 'Executable-like files found under uploads.',
        ] as $metric_key => $help) {
            if (!empty($flags[$metric_key])) {
                Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_' . $metric_key, 'gauge', $help, [['labels' => ['site' => $site], 'value' => (int) ($files[$metric_key] ?? 0)]]);
            }
        }

        if (!empty($flags['wp_content_recently_modified_files_total'])) {
            $samples = [];
            foreach (['plugins', 'themes', 'uploads', 'mu_plugins'] as $area) {
                $samples[] = ['labels' => ['site' => $site, 'area' => $area], 'value' => (int) ($files['wp_content_recently_modified_files'][$area] ?? 0)];
            }
            Simula_Security_Telemetry_Output::append_metric_family($metrics, $prefix . '_wp_content_recently_modified_files_total', 'gauge', 'Recently modified files under bounded wp-content areas.', $samples);
        }

        return $metrics;
    }

    /** Builds samples for the 1h/24h/7d slow windows. */
    private static function simple_window_samples($site, $values) {
        $samples = [];
        foreach (self::slow_windows() as $window) {
            $samples[] = [
                'labels' => ['site' => $site, 'window' => $window],
                'value' => (int) ($values[$window] ?? 0),
            ];
        }

        return $samples;
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
        if (!empty($data['state_updates']) && is_array($data['state_updates'])) {
            $state = array_merge($state, $data['state_updates']);
        }

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

        foreach (['two_factor_metrics', 'scan_issue_metrics', 'wordfence_posture', 'wordpress_posture', 'wordpress_drift', 'account_metrics', 'cron_option_metrics', 'ioc_metrics'] as $key) {
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
            'wordpress_drift' => is_array($data['wordpress_drift'] ?? null) ? $data['wordpress_drift'] : [],
            'account_metrics' => is_array($data['account_metrics'] ?? null) ? $data['account_metrics'] : [],
            'cron_option_metrics' => is_array($data['cron_option_metrics'] ?? null) ? $data['cron_option_metrics'] : [],
            'ioc_metrics' => is_array($data['ioc_metrics'] ?? null) ? $data['ioc_metrics'] : [],
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

    /** Returns the v3 slow-drift reporting windows. */
    private static function slow_windows() {
        return ['1h', '24h', '7d'];
    }
}
