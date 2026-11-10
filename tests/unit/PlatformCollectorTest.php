<?php
/** Platform metadata collection, failure isolation, caching, and rendering tests. */

return [
    'platform parses distribution metadata without evaluating shell values' => function () {
        sstfw_assert_same(['id' => 'ubuntu', 'version' => '24.04'], Simula_Security_Telemetry_Platform_Collector::parse_os_release("NAME=Ubuntu\nID=ubuntu\nVERSION_ID=\"24.04\"\nPRETTY_NAME=ignored\n"));
        sstfw_assert_same(['id' => 'debian', 'version' => 'unknown'], Simula_Security_Telemetry_Platform_Collector::parse_os_release("ID='debian'\nVERSION_ID=\"$(whoami)\""));
        sstfw_assert_same(['id' => 'unknown', 'version' => 'unknown'], Simula_Security_Telemetry_Platform_Collector::parse_os_release(''));
    },
    'platform parses product versions and excludes banner details' => function () {
        sstfw_assert_same(['name' => 'apache', 'version' => '2.4.62'], Simula_Security_Telemetry_Platform_Collector::parse_web_server('Apache/2.4.62 (Ubuntu) OpenSSL/3.0 PHP/8.3'));
        sstfw_assert_same(['name' => 'nginx', 'version' => 'unknown'], Simula_Security_Telemetry_Platform_Collector::parse_web_server('nginx'));
        sstfw_assert_same(['name' => 'microsoft-iis', 'version' => '10.0'], Simula_Security_Telemetry_Platform_Collector::parse_web_server('Microsoft-IIS/10.0'));
        sstfw_assert_same(['name' => 'unknown', 'version' => 'unknown'], Simula_Security_Telemetry_Platform_Collector::parse_web_server('/private/server/path'));
        sstfw_assert_same(['name' => 'mariadb', 'version' => '10.11.6-MariaDB-0+deb12u1'], Simula_Security_Telemetry_Platform_Collector::parse_database('5.5.5-10.11.6-MariaDB-0+deb12u1'));
        sstfw_assert_same(['name' => 'mysql', 'version' => '8.0.40-0ubuntu0.24.04.1'], Simula_Security_Telemetry_Platform_Collector::parse_database('8.0.40-0ubuntu0.24.04.1'));
        sstfw_assert_same(['name' => 'unknown', 'version' => 'unknown'], Simula_Security_Telemetry_Platform_Collector::parse_database(''));
    },
    'platform collection uses connected database and isolates probe failures' => function () {
        $previous = $GLOBALS['wpdb'] ?? null;
        $server = $_SERVER['SERVER_SOFTWARE'] ?? null;
        try {
            $GLOBALS['wpdb'] = new class {
                public function db_server_info() { return '8.0.40'; }
            };
            $platform = Simula_Security_Telemetry_Platform_Collector::collect();
            sstfw_assert_same(['name' => 'mysql', 'version' => '8.0.40'], $platform['database']);
            $GLOBALS['wpdb'] = new class {
                public function db_server_info() { throw new RuntimeException('restricted'); }
            };
            $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.62 (Ubuntu)';
            $platform = Simula_Security_Telemetry_Platform_Collector::collect();
            sstfw_assert_same(['name' => 'unknown', 'version' => 'unknown'], $platform['database']);
            sstfw_assert_same(['web_server', 'database', 'os', 'kernel'], array_keys($platform));
            foreach ($platform as $info) {
                foreach ($info as $value) {
                    sstfw_assert_true(strlen($value) <= 128 && strpos($value, '/') === false);
                }
            }
        } finally {
            $GLOBALS['wpdb'] = $previous;
            if ($server === null) { unset($_SERVER['SERVER_SOFTWARE']); } else { $_SERVER['SERVER_SOFTWARE'] = $server; }
        }
    },
    'platform group is enabled by default and preserves data through slow cache' => function () {
        $defaults = Simula_Security_Telemetry_Config::defaults();
        sstfw_assert_same(1, $defaults['enabled_metrics']['platform_info']);
        $platform = ['database' => ['name' => 'mysql', 'version' => '8.0.40']];
        $cache = sstfw_invoke_private_static('Simula_Security_Telemetry_Service', 'slow_metric_cache_from_data', [['platform_info' => $platform]]);
        $data = sstfw_invoke_private_static('Simula_Security_Telemetry_Service', 'apply_cached_slow_metrics', [[], ['slow_metric_cache' => $cache]]);
        sstfw_assert_same($platform, $data['platform_info']);
        $data += ['flags' => ['platform_info' => true], 'prefix' => 'custom', 'site' => 'example.test'];
        $body = implode("\n", sstfw_invoke_private_static('Simula_Security_Telemetry_Service', 'render_platform_metrics', [$data]));
        sstfw_assert_true(strpos($body, 'custom_database_info{site="example.test",name="mysql",version="8.0.40"} 1') !== false);
        sstfw_assert_true(strpos($body, 'custom_os_info{site="example.test",id="unknown",version="unknown"} 1') !== false);
        $data['flags']['platform_info'] = false;
        sstfw_assert_same([], sstfw_invoke_private_static('Simula_Security_Telemetry_Service', 'render_platform_metrics', [$data]));
    },
];
