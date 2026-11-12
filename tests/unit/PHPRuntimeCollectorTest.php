<?php
/**
 * PHP runtime collector unit tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

return [
    'php runtime support policy accepts maintained minors only' => function () {
        sstfw_assert_same(true, Simula_Security_Telemetry_PHP_Runtime_Collector::is_supported_version('8.3.27'));
        sstfw_assert_same(true, Simula_Security_Telemetry_PHP_Runtime_Collector::is_supported_version('8.5.1'));
        sstfw_assert_same(false, Simula_Security_Telemetry_PHP_Runtime_Collector::is_supported_version('8.2.0'));
        sstfw_assert_same(false, Simula_Security_Telemetry_PHP_Runtime_Collector::is_supported_version('invalid'));
    },
    'php runtime normalizers use bounded values' => function () {
        sstfw_assert_same(1, Simula_Security_Telemetry_PHP_Runtime_Collector::normalize_boolean('On'));
        sstfw_assert_same(0, Simula_Security_Telemetry_PHP_Runtime_Collector::normalize_boolean('not-a-boolean'));
        sstfw_assert_same('strict', Simula_Security_Telemetry_PHP_Runtime_Collector::normalize_samesite(' Strict '));
        sstfw_assert_same('empty', Simula_Security_Telemetry_PHP_Runtime_Collector::normalize_samesite(''));
        sstfw_assert_same('unknown', Simula_Security_Telemetry_PHP_Runtime_Collector::normalize_samesite('arbitrary-value'));
    },
    'php runtime byte and duration parsers omit unsafe values' => function () {
        sstfw_assert_same(134217728, Simula_Security_Telemetry_PHP_Runtime_Collector::parse_byte_size('128M'));
        sstfw_assert_same(1024, Simula_Security_Telemetry_PHP_Runtime_Collector::parse_byte_size('1k'));
        sstfw_assert_same(null, Simula_Security_Telemetry_PHP_Runtime_Collector::parse_byte_size('-1'));
        sstfw_assert_same(null, Simula_Security_Telemetry_PHP_Runtime_Collector::parse_byte_size('/private/path'));
        sstfw_assert_same(0, Simula_Security_Telemetry_PHP_Runtime_Collector::parse_nonnegative_integer('0'));
        sstfw_assert_same(null, Simula_Security_Telemetry_PHP_Runtime_Collector::parse_nonnegative_integer('-1'));
    },
    'php runtime extensions are a fixed allowlist' => function () {
        sstfw_assert_same(['curl', 'mbstring', 'mysqli', 'openssl', 'pdo_mysql', 'sodium', 'xml', 'zip', 'opcache'], Simula_Security_Telemetry_PHP_Runtime_Collector::allowed_extensions());
    },
    'php runtime collection returns only documented bounded fields' => function () {
        $runtime = Simula_Security_Telemetry_PHP_Runtime_Collector::collect();

        sstfw_assert_same([
            'version', 'sapi', 'architecture', 'version_supported', 'extensions',
            'display_errors_enabled', 'expose_php_enabled', 'allow_url_fopen_enabled',
            'session_cookie_secure_enabled', 'session_cookie_httponly_enabled',
            'session_cookie_samesite', 'memory_limit_bytes', 'max_execution_time_seconds',
            'upload_max_filesize_bytes', 'post_max_size_bytes', 'opcache',
        ], array_keys($runtime));
        sstfw_assert_same([], array_values(array_diff($runtime['extensions'], Simula_Security_Telemetry_PHP_Runtime_Collector::allowed_extensions())));
    },
];
