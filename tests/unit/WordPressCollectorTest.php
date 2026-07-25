<?php
/**
 * WordPress collector unit tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

return [
    'collect_sprint6_metrics returns bounded role settings and drift values' => function () {
        $now = 200000;
        $windows = [
            '1h' => $now - 3600,
            '24h' => $now - 86400,
            '7d' => $now - (7 * 86400),
        ];

        $GLOBALS['sstfw_test_users'] = [
            (object) ['ID' => 1, 'roles' => ['administrator'], 'user_registered' => gmdate('Y-m-d H:i:s', $now - 100)],
            (object) ['ID' => 2, 'roles' => ['subscriber'], 'user_registered' => gmdate('Y-m-d H:i:s', $now - 90000)],
            (object) ['ID' => 3, 'roles' => ['custom_role'], 'user_registered' => gmdate('Y-m-d H:i:s', $now - 100)],
        ];
        $GLOBALS['sstfw_test_roles'] = [
            'administrator' => ['capabilities' => ['manage_options' => true, 'edit_posts' => true]],
            'editor' => ['capabilities' => ['edit_posts' => true, 'manage_options' => true]],
        ];
        $GLOBALS['sstfw_test_options']['users_can_register'] = 1;
        $GLOBALS['sstfw_test_options']['default_role'] = 'administrator';
        $GLOBALS['sstfw_test_plugins'] = [
            'old/old.php' => ['Name' => 'Old', 'Version' => '1.0.0'],
            'new/new.php' => ['Name' => 'New', 'Version' => '1.0.0'],
        ];
        $GLOBALS['sstfw_test_active_plugins'] = ['new/new.php'];

        $state = [
            'wp_asset_snapshot' => [
                'plugins' => [
                    'old/old.php' => 'active',
                ],
            ],
            'wp_drift_events' => [],
        ];

        $result = Simula_Security_Telemetry_WordPress_Collector::collect_sprint6_metrics($state, $now, $windows);
        $metrics = $result['metrics'];

        sstfw_assert_same(1, $metrics['user_roles']['users_total']['administrator']);
        sstfw_assert_same(1, $metrics['user_roles']['users_total']['subscriber']);
        sstfw_assert_same(1, $metrics['user_roles']['users_total']['other']);
        sstfw_assert_same(1, $metrics['user_roles']['admin_users_created']['1h']);
        sstfw_assert_same(1, $metrics['settings']['users_can_register_enabled']);
        sstfw_assert_same('administrator', $metrics['settings']['default_role']);
        sstfw_assert_same(1, $metrics['user_roles']['unexpected_admin_capabilities_total']);
        sstfw_assert_same(1, $metrics['asset_drift_windows']['plugins_added']['1h']);
        sstfw_assert_same(1, $metrics['asset_drift_windows']['plugins_deactivated']['1h']);
    },
    'collect_account_metrics counts event windows application passwords and sessions' => function () {
        $now = 300000;
        $windows = [
            '1h' => $now - 3600,
            '24h' => $now - 86400,
            '7d' => $now - (7 * 86400),
        ];

        $GLOBALS['sstfw_test_users'] = [
            (object) ['ID' => 10, 'roles' => ['administrator'], 'user_registered' => gmdate('Y-m-d H:i:s', $now - 1000)],
            (object) ['ID' => 11, 'roles' => ['subscriber'], 'user_registered' => gmdate('Y-m-d H:i:s', $now - 1000)],
        ];
        $GLOBALS['sstfw_test_user_meta'] = [
            10 => [
                '_application_passwords' => ['one' => [], 'two' => []],
                'session_tokens' => ['a' => [], 'b' => []],
            ],
            11 => [
                '_application_passwords' => ['one' => []],
                'session_tokens' => ['a' => []],
            ],
        ];

        $result = Simula_Security_Telemetry_WordPress_Collector::collect_account_metrics(
            [
                'account_events' => [
                    'successful_login' => [['ts' => $now - 60, 'role' => 'administrator']],
                    'password_reset' => [['ts' => $now - 90000, 'role' => 'subscriber']],
                    'email_change' => [],
                    'admin_modified' => [['ts' => $now - 60, 'role' => 'administrator']],
                ],
            ],
            $now,
            $windows
        );

        $metrics = $result['metrics'];

        sstfw_assert_same(1, $metrics['event_windows']['successful_login']['administrator']['1h']);
        sstfw_assert_same(0, $metrics['event_windows']['password_reset']['subscriber']['24h']);
        sstfw_assert_same(1, $metrics['event_windows']['password_reset']['subscriber']['7d']);
        sstfw_assert_same(3, $metrics['application_passwords_total']);
        sstfw_assert_same(2, $metrics['admin_application_passwords_total']);
        sstfw_assert_same(2, $metrics['sessions_by_role']['administrator']);
        sstfw_assert_same(1, $metrics['sessions_by_role']['subscriber']);
    },
];
