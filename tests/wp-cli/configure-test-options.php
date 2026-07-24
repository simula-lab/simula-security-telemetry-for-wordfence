<?php
/**
 * Configures plugin options for disposable test environments.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

$output_dir = WP_CONTENT_DIR . '/uploads/sstfw-test-output';
if (!is_dir($output_dir)) {
    wp_mkdir_p($output_dir);
}

$options = Simula_Security_Telemetry_Config::defaults();
$options['enabled'] = 1;
$options['cron_interval'] = 'sstfw_fifteen_minutes';
$options['slow_cron_interval'] = 'hourly';
$options['prom_file'] = $output_dir . '/wordfence.prom';
$options['incident_log_enabled'] = 1;
$options['incident_log_file'] = $output_dir . '/incidents.log';
$options['incident_log_format'] = 'text';
$options['incident_max_rows'] = 100;
$options['site_label'] = 'sstfw-test.local';

update_option(Simula_Security_Telemetry_Config::OPTION, $options, false);
delete_option(Simula_Security_Telemetry_Config::STATE);
Simula_Security_Telemetry_Settings::sync_schedule($options);

WP_CLI::success('Configured Simula Security Telemetry test options.');
