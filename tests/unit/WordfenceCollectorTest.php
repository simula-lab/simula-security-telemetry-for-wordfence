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
    'firewall block category mapping is bounded and complete' => function () {
        foreach ([
            ['fakegoogle', 'complex'],
            ['badpost', 'complex'],
            ['country', 'complex'],
            ['advanced', 'complex'],
            ['waf', 'complex'],
            ['throttle', 'brute_force'],
            ['brute', 'brute_force'],
            ['blacklist', 'blocklist'],
            ['manual', 'blocklist'],
            ['new-wordfence-type', 'other'],
            ['', 'other'],
            [null, 'other'],
        ] as $case) {
            sstfw_assert_same($case[1], Simula_Security_Telemetry_Wordfence_Collector::firewall_block_category($case[0]));
        }
    },
    'firewall block windows are the supported bounded labels' => function () {
        sstfw_assert_same(['24h' => 1, '7d' => 7, '30d' => 30], Simula_Security_Telemetry_Wordfence_Collector::firewall_block_windows());
    },
    'normalize_ip_range bounds ipv4 ipv6 and integer addresses' => function () {
        sstfw_assert_same('203.0.113.0/24', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'normalize_ip_range', ['203.0.113.44']));
        sstfw_assert_same('203.0.113.0/24', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'normalize_ip_range', ['3405803820']));
        sstfw_assert_same('2001:0db8:abcd:0012::/64', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'normalize_ip_range', ['2001:db8:abcd:12::1']));
        sstfw_assert_same('', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'normalize_ip_range', ["bad\nip"]));
    },
    'wordpress_version falls back to global version and unknown' => function () {
        global $wp_version;

        $wp_version = '6.8.1';
        sstfw_assert_same('6.8.1', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'wordpress_version'));

        $wp_version = '';
        sstfw_assert_same('unknown', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'wordpress_version'));
    },
    'collect_plugin_inventory returns exclusive states and update availability' => function () {
        $updates = new stdClass();
        $updates->response = [
            'inactive/inactive.php' => (object) ['new_version' => '2.0.0'],
        ];

        $GLOBALS['sstfw_test_plugins'] = [
            'active/active.php' => ['Name' => 'Active Plugin', 'Version' => '1.0.0'],
            'network/network.php' => ['Name' => 'Network Plugin', 'Version' => '1.1.0'],
            'inactive/inactive.php' => ['Name' => 'Inactive Plugin', 'Version' => '1.2.0'],
        ];
        $GLOBALS['sstfw_test_active_plugins'] = ['active/active.php'];
        $GLOBALS['sstfw_test_network_active_plugins'] = ['network/network.php'];
        $GLOBALS['sstfw_test_site_transients']['update_plugins'] = $updates;

        $inventory = sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'collect_plugin_inventory');

        sstfw_assert_same(3, $inventory['installed_total']);
        sstfw_assert_same(1, $inventory['active_total']);
        sstfw_assert_same(1, $inventory['network_active_total']);
        sstfw_assert_same(1, $inventory['inactive_total']);
        sstfw_assert_same('active', $inventory['plugins'][0]['state']);
        sstfw_assert_same('network_active', $inventory['plugins'][1]['state']);
        sstfw_assert_same('inactive', $inventory['plugins'][2]['state']);
        sstfw_assert_same(1, $inventory['plugins'][2]['update_available']);
    },
    'bounded_plugin_label strips tags controls and caps length' => function () {
        sstfw_assert_same('unknown', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'bounded_plugin_label', ["\n\t", 10]));
        sstfw_assert_same('Plugin Name', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'bounded_plugin_label', ['<b>Plugin</b> Name', 20]));
        sstfw_assert_same('abcdef', sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'bounded_plugin_label', ['abcdefghi', 6]));
    },
    'collect_admin_user_inventory hashes identities and keeps 2fa state per admin' => function () {
        $admins = [
            (object) ['ID' => 7, 'user_login' => 'admin@example.test', 'display_name' => 'Site Admin'],
            (object) ['ID' => 8, 'user_login' => 'ops', 'display_name' => 'Ops User'],
        ];

        $inventory = sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'collect_admin_user_inventory', [$admins, [7], 'hashed']);

        sstfw_assert_same(2, count($inventory));
        sstfw_assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $inventory[0]['user_id_hash']));
        sstfw_assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $inventory[0]['login_hash']));
        sstfw_assert_true((bool) preg_match('/^[a-f0-9]{64}$/', $inventory[0]['display_name_hash']));
        sstfw_assert_true($inventory[0]['login_hash'] !== 'admin@example.test');
        sstfw_assert_true($inventory[0]['display_name_hash'] !== 'Site Admin');
        sstfw_assert_same(1, $inventory[0]['two_factor_enabled']);
        sstfw_assert_same(0, $inventory[1]['two_factor_enabled']);
    },
    'collect_admin_user_inventory supports id only and disabled modes' => function () {
        $admins = [
            ['ID' => 9, 'user_login' => 'root', 'display_name' => 'Root Admin'],
        ];

        $id_only = sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'collect_admin_user_inventory', [$admins, [], 'id_only']);
        sstfw_assert_same('9', $id_only[0]['user_id']);
        sstfw_assert_same(0, $id_only[0]['two_factor_enabled']);
        sstfw_assert_true(!isset($id_only[0]['login_hash']));

        $disabled = sstfw_invoke_private_static('Simula_Security_Telemetry_Wordfence_Collector', 'collect_admin_user_inventory', [$admins, [], 'disabled']);
        sstfw_assert_same([], $disabled);
    },
];
