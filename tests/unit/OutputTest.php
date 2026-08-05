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
    'firewall summary rendering emits bounded aggregate samples' => function () {
        $data = [
            'flags' => [
                'firewall_blocks_window' => true,
                'firewall_blocks_available' => true,
                'firewall_blocks_collection_success' => true,
                'firewall_blocks_source_info' => true,
                'firewall_blocks_latest_timestamp_seconds' => true,
            ],
            'prefix' => 'wordpress_wordfence',
            'site' => 'example.test',
            'firewall_blocks' => [
                'available' => 1,
                'collection_success' => 1,
                'source' => 'wfBlockedIPLog',
                'schema' => 'wfblockediplog-unixday-blocktype-blockcount',
                'latest_timestamp' => 172800,
                'counts' => [
                    '24h' => ['complex' => 52, 'brute_force' => 451, 'blocklist' => 0, 'other' => 0],
                    '7d' => ['complex' => 121, 'brute_force' => 1221, 'blocklist' => 0, 'other' => 0],
                    '30d' => ['complex' => 627, 'brute_force' => 5227, 'blocklist' => 0, 'other' => 0],
                ],
            ],
        ];

        $lines = sstfw_invoke_private_static('Simula_Security_Telemetry_Service', 'render_firewall_block_metrics', [$data]);
        $body = implode("\n", $lines);

        sstfw_assert_true(strpos($body, '# TYPE wordpress_wordfence_firewall_blocks_window gauge') !== false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_firewall_blocks_available{site="example.test"} 1') !== false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_firewall_blocks_collection_success{site="example.test"} 1') !== false);
        sstfw_assert_true(strpos($body, 'source="wfBlockedIPLog",schema="wfblockediplog-unixday-blocktype-blockcount"') !== false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_firewall_blocks_window{site="example.test",category="complex",window="24h"} 52') !== false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_firewall_blocks_window{site="example.test",category="brute_force",window="30d"} 5227') !== false);
    },
    'firewall summary unavailable does not emit fabricated category counts' => function () {
        $data = [
            'flags' => [
                'firewall_blocks_window' => true,
                'firewall_blocks_available' => true,
                'firewall_blocks_collection_success' => true,
                'firewall_blocks_source_info' => true,
                'firewall_blocks_latest_timestamp_seconds' => true,
            ],
            'prefix' => 'wordpress_wordfence',
            'site' => 'example.test',
            'firewall_blocks' => [
                'available' => 0,
                'collection_success' => 0,
                'schema' => 'unsupported',
                'counts' => [],
            ],
        ];

        $lines = sstfw_invoke_private_static('Simula_Security_Telemetry_Service', 'render_firewall_block_metrics', [$data]);
        $body = implode("\n", $lines);

        sstfw_assert_true(strpos($body, 'wordpress_wordfence_firewall_blocks_available{site="example.test"} 0') !== false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_firewall_blocks_collection_success{site="example.test"} 0') !== false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_firewall_blocks_window{') === false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_firewall_blocks_source_info{') === false);
    },
    'blocked hit row aliases render equal to legacy values' => function () {
        $data = [
            'flags' => [
                'blocked_events_total' => true,
                'blocked_hit_rows_total' => true,
                'blocked_events_window' => true,
                'blocked_hit_rows_window' => true,
                'blocked_events_by_status_24h' => false,
                'top_attack_sources_24h' => false,
            ],
            'prefix' => 'wordpress_wordfence',
            'site' => 'example.test',
            'blocked_total' => 3651,
            'window_counts' => [
                'blocked_count_5m' => 3,
                'blocked_count_1h' => 9,
                'blocked_count_24h' => 88,
                'blocked_count_7d' => 395,
            ],
        ];

        $lines = sstfw_invoke_private_static('Simula_Security_Telemetry_Service', 'render_blocked_event_metrics', [$data]);
        $body = implode("\n", $lines);

        sstfw_assert_true(strpos($body, 'wordpress_wordfence_blocked_events_total{site="example.test"} 3651') !== false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_blocked_hit_rows_total{site="example.test"} 3651') !== false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_blocked_events_window{site="example.test",window="24h"} 88') !== false);
        sstfw_assert_true(strpos($body, 'wordpress_wordfence_blocked_hit_rows_window{site="example.test",window="24h"} 88') !== false);
    },
];
