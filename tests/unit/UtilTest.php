<?php
/**
 * Utility unit tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

return [
    'quote_identifier accepts safe database identifiers' => function () {
        sstfw_assert_same('`wfHits`', Simula_Security_Telemetry_Util::quote_identifier('wfHits'));
        sstfw_assert_same('`wp_123_table`', Simula_Security_Telemetry_Util::quote_identifier('wp_123_table'));
    },
    'quote_identifier rejects unsafe database identifiers' => function () {
        sstfw_assert_same('``', Simula_Security_Telemetry_Util::quote_identifier('wp_users; DROP TABLE wp_users'));
        sstfw_assert_same('``', Simula_Security_Telemetry_Util::quote_identifier('has-dash'));
    },
    'candidate resolution returns available columns in requested order' => function () {
        $columns = [
            'ctime' => ['Field' => 'ctime'],
            'IP' => ['Field' => 'IP'],
            'action' => ['Field' => 'action'],
        ];

        sstfw_assert_same('IP', Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['ip', 'IP']));
        sstfw_assert_same(['ctime', 'action'], Simula_Security_Telemetry_Util::resolve_available_candidates($columns, ['missing', 'ctime', 'action']));
    },
    'file setting path validation keeps valid absolute paths' => function () {
        $GLOBALS['sstfw_settings_errors'] = [];

        $path = Simula_Security_Telemetry_Util::sanitize_file_setting_path(
            '/tmp/wordfence.prom',
            '/tmp/default.prom',
            'absolute',
            'Absolute required',
            'extension',
            '/\.prom$/',
            'Prom required'
        );

        sstfw_assert_same('/tmp/wordfence.prom', $path);
        sstfw_assert_same([], $GLOBALS['sstfw_settings_errors']);
    },
    'file setting path validation restores default for invalid paths' => function () {
        $GLOBALS['sstfw_settings_errors'] = [];

        $path = Simula_Security_Telemetry_Util::sanitize_file_setting_path(
            'relative.txt',
            '/tmp/default.prom',
            'absolute',
            'Absolute required',
            'extension',
            '/\.prom$/',
            'Prom required'
        );

        sstfw_assert_same('/tmp/default.prom', $path);
        sstfw_assert_same('absolute', $GLOBALS['sstfw_settings_errors'][0]['code']);
    },
];
