<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
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

    /**
     * Resets the incident cursor to 0 for controlled backfill.
     *
     * @subcommand reset-cursor
     */
    public function reset_cursor() {
        Simula_Security_Telemetry_Incidents::reset_cursor();
        WP_CLI::success(__('Incident cursor reset to 0.', 'simula-security-telemetry-for-wordfence'));
    }

    /** Displays current exporter status. */
    public function status() {
        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();
        $now     = time();
        $last_export = isset($state['last_export']) ? (int) $state['last_export'] : 0;
        $last_slow_refresh = isset($state['slow_metric_cache_at']) ? (int) $state['slow_metric_cache_at'] : 0;
        $fast_interval_seconds = Simula_Security_Telemetry_Settings::schedule_interval_seconds($options['cron_interval'] ?? '');
        $slow_interval_seconds = Simula_Security_Telemetry_Settings::schedule_interval_seconds($options['slow_cron_interval'] ?? '');
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
            ['field' => 'plugin_inventory_info', 'value' => empty($options['enabled_metrics']['plugin_inventory_info']) ? 'disabled' : 'enabled'],
            ['field' => 'admin_user_info', 'value' => empty($options['enabled_metrics']['admin_user_info']) ? 'disabled' : 'enabled'],
            ['field' => 'admin_identity_mode', 'value' => (string) ($options['admin_identity_mode'] ?? 'hashed')],
            ['field' => 'last_export', 'value' => Simula_Security_Telemetry_Settings::format_state_time($last_export)],
            ['field' => 'last_export_age', 'value' => $last_export > 0 ? Simula_Security_Telemetry_Settings::format_state_duration(max(0, $now - $last_export)) : 'never'],
            ['field' => 'fast_export_status', 'value' => Simula_Security_Telemetry_Settings::freshness_summary($last_export, $fast_interval_seconds, !empty($options['enabled']), __('Fast export', 'simula-security-telemetry-for-wordfence'), $now)],
            ['field' => 'next_fast_export', 'value' => Simula_Security_Telemetry_Settings::format_state_time(Simula_Security_Telemetry_Settings::next_scheduled_timestamp(Simula_Security_Telemetry_Config::CRON_HOOK))],
            ['field' => 'last_result_ok', 'value' => empty($state['last_result_ok']) ? 'no' : 'yes'],
            ['field' => 'last_result', 'value' => (string) ($state['last_result'] ?? '')],
            ['field' => 'last_error', 'value' => (string) ($state['last_error'] ?? '')],
            ['field' => 'last_incident_id', 'value' => (string) ($state['last_incident_id'] ?? 0)],
            ['field' => 'last_slow_refresh', 'value' => Simula_Security_Telemetry_Settings::format_state_time($last_slow_refresh)],
            ['field' => 'last_slow_refresh_age', 'value' => $last_slow_refresh > 0 ? Simula_Security_Telemetry_Settings::format_state_duration(max(0, $now - $last_slow_refresh)) : 'never'],
            ['field' => 'slow_collector_status', 'value' => Simula_Security_Telemetry_Settings::freshness_summary($last_slow_refresh, $slow_interval_seconds, !empty($options['enabled']), __('Slow collector', 'simula-security-telemetry-for-wordfence'), $now)],
            ['field' => 'next_slow_export', 'value' => Simula_Security_Telemetry_Settings::format_state_time(Simula_Security_Telemetry_Settings::next_scheduled_timestamp(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK))],
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
