<?php
/**
 * Admin dashboard unit tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

return [
    'dashboard and widget exclude platform and PHP data and their status signals' => function () {
        $options = Simula_Security_Telemetry_Config::defaults();
        $options['enabled_metrics']['platform_info'] = 1;
        $options['enabled_metrics']['php_runtime_posture'] = 1;
        $state = [
            'last_export' => 1000,
            'slow_metric_cache' => [
                'platform_info' => ['database' => ['name' => 'mysql', 'version' => 'DO_NOT_DISPLAY_DATABASE']],
                'php_runtime_posture' => ['version' => 'DO_NOT_DISPLAY_PHP', 'version_supported' => 0, 'display_errors_enabled' => 1],
            ],
        ];
        $model = Simula_Security_Telemetry_Admin_Dashboard::view_model($options, $state, 1000);
        sstfw_assert_same('healthy', $model['overall_status']);
        sstfw_assert_true(!isset($model['platform']) && !isset($model['runtime']));
        $GLOBALS['sstfw_options'][Simula_Security_Telemetry_Config::OPTION] = $options;
        $GLOBALS['sstfw_options'][Simula_Security_Telemetry_Config::STATE] = $state;
        foreach (['dashboard_page', 'dashboard_widget'] as $method) {
            ob_start();
            Simula_Security_Telemetry_Admin::$method();
            $html = ob_get_clean();
            foreach (['Platform information', 'PHP runtime posture', 'DO_NOT_DISPLAY_DATABASE', 'DO_NOT_DISPLAY_PHP'] as $forbidden) {
                sstfw_assert_true(strpos($html, $forbidden) === false, 'Dashboard rendered removed content: ' . $forbidden);
            }
            foreach (['Exporter health', 'WordPress posture', 'Change signals'] as $expected) {
                sstfw_assert_true(strpos($html, $expected) !== false);
            }
        }
    },
    'admin dashboard registers a separate top-level page with the configured capability' => function () {
        Simula_Security_Telemetry_Admin::admin_menu();
        $menu = $GLOBALS['sstfw_admin_menu'][Simula_Security_Telemetry_Config::DASHBOARD_SLUG] ?? [];

        sstfw_assert_same(Simula_Security_Telemetry_Config::CAPABILITY, $menu['capability'] ?? null);
        sstfw_assert_same('Telemetry', $menu['menu_title'] ?? null);
        sstfw_assert_same(['Simula_Security_Telemetry_Admin', 'dashboard_page'], $menu['callback'] ?? null);
        sstfw_assert_same('', $menu['icon_url'] ?? null);
        sstfw_assert_same(76, $menu['position'] ?? null);
        sstfw_assert_true(isset($GLOBALS['sstfw_options_menu'][Simula_Security_Telemetry_Config::SLUG]));
    },
    'admin dashboard denies rendering to users without the configured capability' => function () {
        $GLOBALS['sstfw_test_current_user_can'] = false;
        ob_start();
        Simula_Security_Telemetry_Admin::dashboard_page();
        $output = ob_get_clean();
        $GLOBALS['sstfw_test_current_user_can'] = true;

        sstfw_assert_same('', $output);
    },
    'dashboard widget registers and renders the safe telemetry summary' => function () {
        $GLOBALS['sstfw_dashboard_widgets'] = [];
        Simula_Security_Telemetry_Admin::register_dashboard_widget();
        $widget = $GLOBALS['sstfw_dashboard_widgets']['sstfw_security_telemetry'] ?? [];
        sstfw_assert_same('Security Telemetry', $widget['widget_name'] ?? null);
        sstfw_assert_same(['Simula_Security_Telemetry_Admin', 'dashboard_widget'], $widget['callback'] ?? null);

        $options = Simula_Security_Telemetry_Config::defaults();
        $options['grafana_dashboard_url'] = 'https://grafana.example.test/d/security';
        $GLOBALS['sstfw_options'][Simula_Security_Telemetry_Config::OPTION] = $options;
        $GLOBALS['sstfw_options'][Simula_Security_Telemetry_Config::STATE] = [
            'last_export' => time(),
            'slow_metric_cache' => ['wordfence_posture' => ['marker' => 'DO_NOT_DISPLAY_WORDFENCE_DATA']],
        ];

        ob_start();
        Simula_Security_Telemetry_Admin::dashboard_widget();
        $output = ob_get_clean();

        sstfw_assert_true(strpos($output, 'Overall status:') !== false);
        sstfw_assert_true(strpos($output, 'Exporter health') !== false);
        sstfw_assert_true(strpos($output, 'View Security Telemetry dashboard') !== false);
        sstfw_assert_true(strpos($output, 'https://grafana.example.test/d/security') !== false);
        sstfw_assert_true(strpos($output, 'DO_NOT_DISPLAY_WORDFENCE_DATA') === false);
    },
    'admin dashboard allowlists cached WordPress data while excluding Wordfence data' => function () {
        $options = Simula_Security_Telemetry_Config::defaults();
        $options['enabled_metrics']['php_runtime_posture'] = 1;
        $options['grafana_dashboard_url'] = 'https://grafana.example.test/d/security';
        $state = [
            'last_export' => 990,
            'slow_metric_cache_at' => 980,
            'slow_metric_cache' => [
                'php_runtime_posture' => ['version' => '8.3.27', 'version_supported' => 1, 'sapi' => 'fpm-fcgi', 'architecture' => '64', 'display_errors_enabled' => 0, 'expose_php_enabled' => 0, 'allow_url_fopen_enabled' => 1, 'opcache' => ['enabled' => 1]],
                'wordpress_posture' => ['wordpress_version' => '6.9', 'core_update_available' => 0, 'plugin_update_available_total' => 1, 'theme_update_available_total' => 0, 'admin_users_without_2fa_total' => 99],
                'wordpress_drift' => ['settings' => ['debug_enabled' => 0, 'file_edit_allowed' => 0, 'file_mods_allowed' => 0], 'asset_drift_windows' => ['plugins_added' => ['24h' => 1], 'plugins_removed' => ['24h' => 0]], 'wordfence_posture' => ['version' => 'sensitive']],
                'ioc_metrics' => ['content' => ['script_tags' => ['post' => 1]], 'files' => ['upload_php_files_total' => 1, 'upload_executable_files_total' => 1, 'theme_files_modified' => ['24h' => 1]]],
                'wordfence_posture' => ['version' => 'sensitive'],
            ],
        ];

        $model = Simula_Security_Telemetry_Admin_Dashboard::view_model($options, $state, 1000);
        $serialized = serialize($model);

        sstfw_assert_same(1, $model['changes']['data']['upload_php_files']);
        sstfw_assert_same('https://grafana.example.test/d/security', $model['grafana_url']);
        sstfw_assert_same('success', $model['exporter']['last_result']);
        sstfw_assert_true(strpos($serialized, 'wordfence') === false);
        sstfw_assert_true(strpos($serialized, 'sensitive') === false);
        sstfw_assert_true(strpos($serialized, 'admin_users_without_2fa_total') === false);
    },
    'admin dashboard represents disabled and unavailable sources explicitly' => function () {
        $options = Simula_Security_Telemetry_Config::defaults();
        $options['enabled_metrics']['php_runtime_posture'] = 0;
        $model = Simula_Security_Telemetry_Admin_Dashboard::view_model($options, [], 1000);

        sstfw_assert_same('unavailable', $model['wordpress']['status']);
        sstfw_assert_same('unavailable', $model['changes']['status']);
    },
    'grafana handoff permits only absolute HTTPS URLs' => function () {
        sstfw_assert_same('https://grafana.example.test/d/abc', Simula_Security_Telemetry_Admin_Dashboard::grafana_url('https://grafana.example.test/d/abc'));
        sstfw_assert_same('', Simula_Security_Telemetry_Admin_Dashboard::grafana_url('http://grafana.example.test/d/abc'));
        sstfw_assert_same('', Simula_Security_Telemetry_Admin_Dashboard::grafana_url('javascript:alert(1)'));
        sstfw_assert_same('', sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_grafana_dashboard_url', ['http://grafana.example.test/d/abc']));
    },
];
