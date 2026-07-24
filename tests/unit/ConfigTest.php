<?php
/**
 * Config unit tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

return [
    'defaults include all enabled metric keys' => function () {
        $defaults = Simula_Security_Telemetry_Config::defaults();
        $definitions = Simula_Security_Telemetry_Config::metric_definitions();

        sstfw_assert_same('sstfw_metrics_options', Simula_Security_Telemetry_Config::OPTION);
        sstfw_assert_same('example.test', $defaults['site_label']);
        sstfw_assert_same(array_keys($definitions), array_keys($defaults['enabled_metrics']));
        sstfw_assert_true(!in_array(0, $defaults['enabled_metrics'], true), 'Default metrics should all be enabled.');
    },
];
