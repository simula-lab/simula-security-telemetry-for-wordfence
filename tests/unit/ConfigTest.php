<?php
/**
 * Config unit tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

return [
    'defaults include all enabled metric keys with sensitive inventory opt-in' => function () {
        $defaults = Simula_Security_Telemetry_Config::defaults();
        $definitions = Simula_Security_Telemetry_Config::metric_definitions();

        sstfw_assert_same('sstfw_metrics_options', Simula_Security_Telemetry_Config::OPTION);
        sstfw_assert_same('example.test', $defaults['site_label']);
        sstfw_assert_same(array_keys($definitions), array_keys($defaults['enabled_metrics']));
        sstfw_assert_same(0, $defaults['enabled_metrics']['plugin_inventory_info']);
        sstfw_assert_same(1, $defaults['enabled_metrics']['plugins_installed_total']);
        sstfw_assert_same(1, $defaults['enabled_metrics']['plugins_active_total']);
        sstfw_assert_same(1, $defaults['enabled_metrics']['plugins_inactive_total']);
        sstfw_assert_same(1, $defaults['enabled_metrics']['plugins_network_active_total']);
    },
];
