<?php
/**
 * Output unit tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

return [
    'escape_label escapes prometheus label special characters' => function () {
        sstfw_assert_same('path\\\\with\\n\\"quote\\"', Simula_Security_Telemetry_Output::escape_label("path\\with\n\"quote\""));
    },
    'format_number removes unnecessary decimals' => function () {
        sstfw_assert_same('42', Simula_Security_Telemetry_Output::format_number(42.0));
        sstfw_assert_same('3.141593', Simula_Security_Telemetry_Output::format_number(3.14159265));
        sstfw_assert_same('2.5', Simula_Security_Telemetry_Output::format_number('2.500000'));
    },
    'metric family rendering writes help type and samples' => function () {
        $lines = [];
        Simula_Security_Telemetry_Output::append_metric_family(
            $lines,
            'wordpress_wordfence_test_total',
            'counter',
            'A test metric.',
            [
                ['labels' => ['site' => 'example.test', 'kind' => 'unit'], 'value' => 2.0],
                ['labels' => [], 'value' => 'NaN'],
            ]
        );

        sstfw_assert_same('# HELP wordpress_wordfence_test_total A test metric.', $lines[0]);
        sstfw_assert_same('# TYPE wordpress_wordfence_test_total counter', $lines[1]);
        sstfw_assert_same('wordpress_wordfence_test_total{site="example.test",kind="unit"} 2', $lines[2]);
        sstfw_assert_same('wordpress_wordfence_test_total NaN', $lines[3]);
    },
    'classify_error_type maps unbounded messages to bounded labels' => function () {
        sstfw_assert_same('wordfence_missing', Simula_Security_Telemetry_Output::classify_error_type('Wordfence table not found'));
        sstfw_assert_same('schema_unsupported', Simula_Security_Telemetry_Output::classify_error_type('Unsupported Wordfence schema'));
        sstfw_assert_same('incident_failed', Simula_Security_Telemetry_Output::classify_error_type('Incident log append failed'));
        sstfw_assert_same('write_failed', Simula_Security_Telemetry_Output::classify_error_type('Directory is not writable'));
        sstfw_assert_same('unknown', Simula_Security_Telemetry_Output::classify_error_type('Something else'));
    },
];
