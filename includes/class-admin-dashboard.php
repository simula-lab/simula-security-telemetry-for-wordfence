<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Builds the WordPress-only, read-only admin dashboard view model. */
final class Simula_Security_Telemetry_Admin_Dashboard {
    /** Builds a display-safe current-state model without collecting or querying data. */
    public static function view_model($options, $state, $now = null) {
        $options = is_array($options) ? $options : [];
        $state   = is_array($state) ? $state : [];
        $now     = $now === null ? time() : (int) $now;
        $cache   = is_array($state['slow_metric_cache'] ?? null) ? $state['slow_metric_cache'] : [];
        $exporter = self::exporter_card($options, $state, $now);
        $wordpress = self::wordpress_card($cache);
        $changes = self::change_signals_card($cache);

        return [
            'overall_status' => self::overall_status([$exporter, $wordpress, $changes]),
            'exporter' => $exporter,
            'wordpress' => $wordpress,
            'changes' => $changes,
            'grafana_url' => self::grafana_url($options['grafana_dashboard_url'] ?? ''),
        ];
    }

    /** Returns a safely validated Grafana handoff URL or an empty string. */
    public static function grafana_url($value) {
        $value = trim((string) $value);
        $parts = function_exists('wp_parse_url') ? wp_parse_url($value) : parse_url($value);

        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            return '';
        }

        return $value;
    }

    /** Produces exporter status solely from plugin state and scheduled timestamps. */
    private static function exporter_card($options, $state, $now) {
        $last_export = (int) ($state['last_export'] ?? 0);
        $interval    = Simula_Security_Telemetry_Settings::schedule_interval_seconds($options['cron_interval'] ?? '');
        $status      = !empty($options['enabled'])
            ? ($last_export > 0 && ($interval === 0 || $now - $last_export <= ($interval * 2)) && empty($state['last_error']) ? 'healthy' : 'review')
            : 'unavailable';

        if ($last_export === 0) {
            $status = 'unavailable';
        } elseif (!empty($state['last_error'])) {
            $status = 'action_required';
        }

        return [
            'status' => $status,
            'last_export' => $last_export,
            'last_result' => $last_export === 0 ? 'not_run' : (empty($state['last_error']) ? 'success' : 'failed'),
            'next_export' => Simula_Security_Telemetry_Settings::next_scheduled_timestamp(Simula_Security_Telemetry_Config::CRON_HOOK),
            'slow_refresh' => (int) ($state['slow_metric_cache_at'] ?? 0),
        ];
    }

    /** Produces WordPress posture from explicit non-Wordfence cache keys only. */
    private static function wordpress_card($cache) {
        $posture = is_array($cache['wordpress_posture'] ?? null) ? $cache['wordpress_posture'] : [];
        $drift   = is_array($cache['wordpress_drift'] ?? null) ? $cache['wordpress_drift'] : [];
        $settings = is_array($drift['settings'] ?? null) ? $drift['settings'] : [];
        if ($posture === [] && $drift === []) {
            return ['status' => 'unavailable', 'data' => []];
        }

        $data = [
            'wordpress_version' => (string) ($posture['wordpress_version'] ?? 'unknown'),
            'core_update_available' => !empty($posture['core_update_available']),
            'plugin_update_available_total' => (int) ($posture['plugin_update_available_total'] ?? 0),
            'theme_update_available_total' => (int) ($posture['theme_update_available_total'] ?? 0),
            'debug_enabled' => !empty($settings['debug_enabled']),
            'file_edit_allowed' => !empty($settings['file_edit_allowed']),
            'file_mods_allowed' => !empty($settings['file_mods_allowed']),
        ];

        $review = $data['core_update_available'] || $data['plugin_update_available_total'] > 0 || $data['theme_update_available_total'] > 0 || $data['debug_enabled'] || $data['file_edit_allowed'];
        return ['status' => $review ? 'review' : 'healthy', 'data' => $data];
    }

    /** Produces change signals from explicit non-Wordfence drift and IoC keys only. */
    private static function change_signals_card($cache) {
        $drift = is_array($cache['wordpress_drift'] ?? null) ? $cache['wordpress_drift'] : [];
        $ioc   = is_array($cache['ioc_metrics'] ?? null) ? $cache['ioc_metrics'] : [];
        $windows = is_array($drift['asset_drift_windows'] ?? null) ? $drift['asset_drift_windows'] : [];
        $content = is_array($ioc['content'] ?? null) ? $ioc['content'] : [];
        $files   = is_array($ioc['files'] ?? null) ? $ioc['files'] : [];
        if ($drift === [] && $ioc === []) {
            return ['status' => 'unavailable', 'data' => []];
        }

        $data = [
            'plugins_added' => self::window_total($windows['plugins_added'] ?? []),
            'plugins_removed' => self::window_total($windows['plugins_removed'] ?? []),
            'theme_files_modified' => self::window_total($files['theme_files_modified'] ?? []),
            'upload_php_files' => (int) ($files['upload_php_files_total'] ?? 0),
            'upload_executable_files' => (int) ($files['upload_executable_files_total'] ?? 0),
            'suspicious_content' => self::post_type_total($content['script_tags'] ?? []) + self::post_type_total($content['iframe_tags'] ?? []) + self::post_type_total($content['suspicious_redirects'] ?? []),
        ];

        return ['status' => array_sum($data) > 0 ? 'review' : 'healthy', 'data' => $data];
    }

    /** Derives an overall status without exposing source-specific details. */
    private static function overall_status($cards) {
        $statuses = array_map(static function ($card) {
            return is_array($card) ? ($card['status'] ?? 'unavailable') : 'unavailable';
        }, is_array($cards) ? $cards : []);

        if (in_array('action_required', $statuses, true)) {
            return 'action_required';
        }
        if (in_array('review', $statuses, true)) {
            return 'review';
        }
        if (in_array('healthy', $statuses, true)) {
            return 'healthy';
        }

        return 'unavailable';
    }

    /** Sums known numeric window values without retaining their labels. */
    private static function window_total($windows) {
        return array_sum(array_map('intval', is_array($windows) ? $windows : []));
    }

    /** Sums known numeric post-type values without retaining their labels. */
    private static function post_type_total($values) {
        return array_sum(array_map('intval', is_array($values) ? $values : []));
    }
}
