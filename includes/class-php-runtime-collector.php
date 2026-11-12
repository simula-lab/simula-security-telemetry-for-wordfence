<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Collects a deliberately small, privacy-safe PHP runtime posture snapshot. */
final class Simula_Security_Telemetry_PHP_Runtime_Collector {
    private const EXTENSIONS = ['curl', 'mbstring', 'mysqli', 'openssl', 'pdo_mysql', 'sodium', 'xml', 'zip', 'opcache'];
    private const SUPPORTED_PHP_MINOR_VERSIONS = ['8.3', '8.4', '8.5'];

    /** Returns the fixed extension allowlist used for metrics. */
    public static function allowed_extensions() {
        return self::EXTENSIONS;
    }

    /** Collects only explicitly allowed runtime details; never calls the PHP information function. */
    public static function collect() {
        $ini = static function ($name) {
            return function_exists('ini_get') ? ini_get($name) : false;
        };
        $version = defined('PHP_VERSION') ? (string) PHP_VERSION : 'unknown';
        $sapi    = defined('PHP_SAPI') ? (string) PHP_SAPI : 'unknown';

        $extensions = [];
        foreach (self::EXTENSIONS as $extension) {
            if (extension_loaded($extension)) {
                $extensions[] = $extension;
            }
        }

        return [
            'version'                       => self::bounded_runtime_label($version, 'unknown'),
            'sapi'                          => self::bounded_runtime_label($sapi, 'unknown'),
            'architecture'                  => PHP_INT_SIZE >= 8 ? '64' : '32',
            'version_supported'             => self::is_supported_version($version) ? 1 : 0,
            'extensions'                    => $extensions,
            'display_errors_enabled'        => self::normalize_boolean($ini('display_errors')),
            'expose_php_enabled'            => self::normalize_boolean($ini('expose_php')),
            'allow_url_fopen_enabled'       => self::normalize_boolean($ini('allow_url_fopen')),
            'session_cookie_secure_enabled' => self::normalize_boolean($ini('session.cookie_secure')),
            'session_cookie_httponly_enabled' => self::normalize_boolean($ini('session.cookie_httponly')),
            'session_cookie_samesite'       => self::normalize_samesite($ini('session.cookie_samesite')),
            'memory_limit_bytes'            => self::parse_byte_size($ini('memory_limit')),
            'max_execution_time_seconds'    => self::parse_nonnegative_integer($ini('max_execution_time')),
            'upload_max_filesize_bytes'     => self::parse_byte_size($ini('upload_max_filesize')),
            'post_max_size_bytes'           => self::parse_byte_size($ini('post_max_size')),
            'opcache'                       => self::collect_opcache_posture($ini),
        ];
    }

    /** Returns whether a PHP release belongs to the maintained support policy. */
    public static function is_supported_version($version) {
        if (!is_string($version) || !preg_match('/^(\d+)\.(\d+)/', $version, $matches)) {
            return false;
        }

        return in_array($matches[1] . '.' . $matches[2], self::SUPPORTED_PHP_MINOR_VERSIONS, true);
    }

    /** Normalizes PHP INI booleans without exposing the original INI value. */
    public static function normalize_boolean($value) {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'on', 'yes', 'true'], true) ? 1 : 0;
    }

    /** Maps arbitrary SameSite input to a bounded label vocabulary. */
    public static function normalize_samesite($value) {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return 'empty';
        }

        return in_array($value, ['lax', 'strict', 'none'], true) ? $value : 'unknown';
    }

    /** Parses a PHP shorthand byte value, omitting invalid and unlimited values. */
    public static function parse_byte_size($value) {
        $value = strtolower(trim((string) $value));
        if ($value === '' || $value === '-1' || !preg_match('/^(\d+)\s*([kmg]?)b?$/', $value, $matches)) {
            return null;
        }

        $bytes = (float) $matches[1];
        $multipliers = ['' => 1, 'k' => 1024, 'm' => 1048576, 'g' => 1073741824];
        $bytes *= $multipliers[$matches[2]];

        return $bytes <= PHP_INT_MAX ? (int) $bytes : null;
    }

    /** Parses a nonnegative integer setting, omitting invalid and unlimited values. */
    public static function parse_nonnegative_integer($value) {
        $value = trim((string) $value);
        if (!preg_match('/^\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    /** Collects bounded OPcache status only when its status API is available. */
    private static function collect_opcache_posture($ini) {
        if (!extension_loaded('opcache') || !function_exists('opcache_get_status')) {
            return [];
        }

        $status = @opcache_get_status(false);
        if (!is_array($status)) {
            return [];
        }

        $memory = is_array($status['memory_usage'] ?? null) ? $status['memory_usage'] : [];
        $used   = isset($memory['used_memory']) ? max(0, (float) $memory['used_memory']) : null;
        $free   = isset($memory['free_memory']) ? max(0, (float) $memory['free_memory']) : null;
        $wasted = isset($memory['wasted_memory']) ? max(0, (float) $memory['wasted_memory']) : 0;
        $total  = $used !== null && $free !== null ? $used + $free + $wasted : 0;

        return [
            'enabled'            => !empty($status['opcache_enabled']) ? 1 : 0,
            'jit_enabled'        => self::normalize_boolean($ini('opcache.jit')) && (int) self::parse_byte_size($ini('opcache.jit_buffer_size')) > 0 ? 1 : 0,
            'memory_usage_ratio' => $total > 0 ? min(1, $used / $total) : null,
        ];
    }

    /** Bounds labels from PHP constants to a conservative Prometheus-safe vocabulary. */
    private static function bounded_runtime_label($value, $fallback) {
        $value = strtolower(trim((string) $value));
        return preg_match('/^[a-z0-9._-]{1,64}$/', $value) ? $value : $fallback;
    }
}
