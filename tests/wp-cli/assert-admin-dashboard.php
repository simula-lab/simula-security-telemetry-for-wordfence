<?php
/**
 * Verifies the real wp-admin dashboard render without Wordfence-derived output.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

$state = Simula_Security_Telemetry_Settings::get_state();
$state['last_error'] = 'DO_NOT_DISPLAY_WORDFENCE_ERROR';
$state['slow_metric_cache'] = is_array($state['slow_metric_cache'] ?? null) ? $state['slow_metric_cache'] : [];
$state['slow_metric_cache']['wordfence_posture'] = ['marker' => 'DO_NOT_DISPLAY_WORDFENCE_DATA'];
update_option(Simula_Security_Telemetry_Config::STATE, $state, false);

wp_set_current_user(1);
ob_start();
Simula_Security_Telemetry_Admin::dashboard_page();
$html = ob_get_clean();

foreach ([
    'Security Telemetry',
    'WordPress posture',
    'Change signals',
    'https://grafana.example.test/d/sstfw-security',
] as $expected) {
    if (strpos($html, $expected) === false) {
        WP_CLI::error('Dashboard output is missing: ' . $expected);
    }
}

foreach (['Platform information', 'PHP runtime posture', 'DO_NOT_DISPLAY_WORDFENCE_ERROR', 'DO_NOT_DISPLAY_WORDFENCE_DATA'] as $forbidden) {
    if (strpos($html, $forbidden) !== false) {
        WP_CLI::error('Dashboard rendered excluded content: ' . $forbidden);
    }
}

WP_CLI::success('Admin dashboard smoke test passed.');
