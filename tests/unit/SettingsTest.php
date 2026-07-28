<?php
/**
 * Settings unit tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

return [
    'schedule_interval_seconds resolves built-in and custom schedules' => function () {
        sstfw_assert_same(300, Simula_Security_Telemetry_Settings::schedule_interval_seconds('sstfw_five_minutes'));
        sstfw_assert_same(900, Simula_Security_Telemetry_Settings::schedule_interval_seconds('sstfw_fifteen_minutes'));
        sstfw_assert_same(3600, Simula_Security_Telemetry_Settings::schedule_interval_seconds('hourly'));
        sstfw_assert_same(120, Simula_Security_Telemetry_Settings::schedule_interval_seconds('custom_every_two_minutes'));
        sstfw_assert_same(0, Simula_Security_Telemetry_Settings::schedule_interval_seconds('missing'));
    },
    'format_state_duration uses compact two-part output' => function () {
        sstfw_assert_same('0 seconds', Simula_Security_Telemetry_Settings::format_state_duration(0));
        sstfw_assert_same('59 seconds', Simula_Security_Telemetry_Settings::format_state_duration(59));
        sstfw_assert_same('1 hour 1 minute', Simula_Security_Telemetry_Settings::format_state_duration(3661));
        sstfw_assert_same('1 day 1 hour', Simula_Security_Telemetry_Settings::format_state_duration(90000));
    },
    'freshness_summary handles disabled missing overdue and healthy states' => function () {
        sstfw_assert_same('Exporter disabled.', Simula_Security_Telemetry_Settings::freshness_summary(100, 60, false, 'Fast export', 120));
        sstfw_assert_same('No fast export has completed yet.', Simula_Security_Telemetry_Settings::freshness_summary(0, 60, true, 'Fast export', 120));
        sstfw_assert_same('No interval is configured for fast export freshness checks.', Simula_Security_Telemetry_Settings::freshness_summary(100, 0, true, 'Fast export', 120));
        sstfw_assert_same('Fast export is overdue by 1 minute relative to the configured 1 minute interval.', Simula_Security_Telemetry_Settings::freshness_summary(60, 60, true, 'Fast export', 180));
        sstfw_assert_same('Fast export is within the configured interval at 30 seconds old out of 1 minute.', Simula_Security_Telemetry_Settings::freshness_summary(90, 60, true, 'Fast export', 120));
    },
    'get_options upgrades older stored options with conservative v3 defaults' => function () {
        $GLOBALS['sstfw_options'][Simula_Security_Telemetry_Config::OPTION] = [
            'enabled' => 1,
            'metric_prefix' => 'wordpress_wordfence',
            'enabled_metrics' => [
                'export_success' => 1,
                'plugin_info' => 1,
                'admin_users_total' => 0,
            ],
        ];

        $options = Simula_Security_Telemetry_Settings::get_options();

        sstfw_assert_same('hashed', $options['admin_identity_mode']);
        sstfw_assert_same(0, $options['enabled_metrics']['plugin_inventory_info']);
        sstfw_assert_same(0, $options['enabled_metrics']['admin_user_info']);
        sstfw_assert_same(1, $options['enabled_metrics']['wordpress_version_info']);
        sstfw_assert_same(0, $options['enabled_metrics']['admin_users_total']);

        unset($GLOBALS['sstfw_options'][Simula_Security_Telemetry_Config::OPTION]);
    },
    'private sanitizers normalize submitted values' => function () {
        sstfw_assert_same('wordpress_wordfence', sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_metric_prefix', ['1 bad prefix']));
        sstfw_assert_same('valid_prefix', sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_metric_prefix', ['valid prefix']));
        sstfw_assert_same('jsonl', sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_incident_log_format', ['jsonl']));
        sstfw_assert_same('text', sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_incident_log_format', ['xml']));
        sstfw_assert_same('hash', sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_privacy_ip_mode', ['hash']));
        sstfw_assert_same('full', sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_privacy_ip_mode', ['invalid']));
        sstfw_assert_same('id_only', sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_admin_identity_mode', ['id_only']));
        sstfw_assert_same('hashed', sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_admin_identity_mode', ['raw']));
        sstfw_assert_same(10000, sstfw_invoke_private_static('Simula_Security_Telemetry_Settings', 'sanitize_incident_max_rows', [50000]));
    },
];
