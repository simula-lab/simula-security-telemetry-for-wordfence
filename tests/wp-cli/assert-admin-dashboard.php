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
    'aria-current="page">Dashboard</a>',
    admin_url('admin.php?page=' . Simula_Security_Telemetry_Config::SLUG),
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

ob_start();
Simula_Security_Telemetry_Admin::dashboard_widget();
$widget = ob_get_clean();
foreach (['Exporter version', Simula_Security_Telemetry_Config::VERSION, 'Last export (UTC)', 'Most recent exporter result', 'Last slow refresh (UTC)', 'Next export (UTC)'] as $expected) {
    if (strpos($widget, $expected) === false) {
        WP_CLI::error('Dashboard widget is missing: ' . $expected);
    }
}
foreach (['DO_NOT_DISPLAY_WORDFENCE_ERROR', 'DO_NOT_DISPLAY_WORDFENCE_DATA'] as $forbidden) {
    if (strpos($widget, $forbidden) !== false) {
        WP_CLI::error('Dashboard widget rendered excluded content: ' . $forbidden);
    }
}

require_once ABSPATH . 'wp-admin/includes/template.php';
ob_start();
Simula_Security_Telemetry_Admin::settings_page();
$settings = ob_get_clean();
foreach (['aria-current="page">Settings</a>', admin_url('admin.php?page=' . Simula_Security_Telemetry_Config::DASHBOARD_SLUG), 'action="options.php"', 'sstfw_export_now', 'sstfw_reset_incident_cursor'] as $expected) {
    if (strpos($settings, $expected) === false) {
        WP_CLI::error('Settings view is missing: ' . $expected);
    }
}

WP_CLI::success('Admin dashboard, widget, and settings smoke tests passed.');
