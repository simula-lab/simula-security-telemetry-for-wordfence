<?php
/**
 * Enables opt-in PHP runtime posture telemetry for Docker smoke tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

$options = Simula_Security_Telemetry_Settings::get_options();
$options['enabled_metrics']['php_runtime_posture'] = 1;
$options['grafana_dashboard_url'] = 'https://grafana.example.test/d/sstfw-security';

update_option(Simula_Security_Telemetry_Config::OPTION, $options, false);

WP_CLI::success('Enabled opt-in PHP runtime posture metric and Grafana handoff.');
