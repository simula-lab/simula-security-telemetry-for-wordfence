<?php
/**
 * Enables opt-in administrator inventory metrics for smoke tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

$options = Simula_Security_Telemetry_Settings::get_options();
$options['admin_identity_mode'] = 'hashed';
$options['enabled_metrics']['admin_user_info'] = 1;

update_option(Simula_Security_Telemetry_Config::OPTION, $options, false);

WP_CLI::success('Enabled opt-in administrator inventory metric.');
