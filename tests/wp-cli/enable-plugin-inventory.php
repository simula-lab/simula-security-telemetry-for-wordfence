<?php
/**
 * Enables opt-in per-plugin inventory metrics for smoke tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

$options = Simula_Security_Telemetry_Settings::get_options();
$options['enabled_metrics']['plugin_inventory_info'] = 1;

update_option(Simula_Security_Telemetry_Config::OPTION, $options, false);

WP_CLI::success('Enabled opt-in plugin inventory metric.');
