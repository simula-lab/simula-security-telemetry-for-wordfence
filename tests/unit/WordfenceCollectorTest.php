<?php
/**
 * Wordfence collector unit tests for pure helpers.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

return [
    'combine_where_any removes empty and false clauses' => function () {
        sstfw_assert_same('(a = 1 OR b = 2)', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'combine_where_any', [['', '0=1', 'a = 1', 'b = 2']]));
        sstfw_assert_same('0=1', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'combine_where_any', [['', '0=1']]));
    },
    'combine_where_all propagates false clauses' => function () {
        sstfw_assert_same('(a = 1 AND b = 2)', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'combine_where_all', [['a = 1', '', 'b = 2']]));
        sstfw_assert_same('0=1', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'combine_where_all', [['a = 1', '0=1']]));
    },
    'normalize_ip_range bounds ipv4 ipv6 and integer addresses' => function () {
        sstfw_assert_same('203.0.113.0/24', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'normalize_ip_range', ['203.0.113.44']));
        sstfw_assert_same('203.0.113.0/24', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'normalize_ip_range', ['3405803820']));
        sstfw_assert_same('2001:0db8:abcd:0012::/64', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'normalize_ip_range', ['2001:db8:abcd:12::1']));
        sstfw_assert_same('', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'normalize_ip_range', ["bad\nip"]));
    },
];
