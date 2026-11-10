<?php
/**
 * Platform version metadata visible to the WordPress PHP process.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Platform_Collector {
    /** Collects independent best-effort probes without shell commands or new connections. */
    public static function collect() {
        global $wpdb;

        $database = self::probe(static function () use ($wpdb) {
            if (is_object($wpdb) && is_callable([$wpdb, 'db_server_info'])) {
                return $wpdb->db_server_info();
            }
            return is_object($wpdb) && is_callable([$wpdb, 'db_version']) ? $wpdb->db_version() : '';
        });
        $banner = self::probe(static function () {
            return function_exists('apache_get_version') ? apache_get_version() : '';
        });
        if ($banner === '') {
            $banner = isset($_SERVER['SERVER_SOFTWARE']) && is_string($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
        }

        return [
            'web_server' => self::parse_web_server($banner),
            'database' => self::parse_database($database),
            'os' => self::parse_os_release(self::probe(static function () {
                foreach (['/etc/os-release', '/usr/lib/os-release'] as $path) {
                    if (@is_readable($path)) {
                        $contents = @file_get_contents($path, false, null, 0, 16384);
                        if (is_string($contents) && $contents !== '') {
                            return $contents;
                        }
                    }
                }
                return '';
            })),
            'kernel' => [
                'name' => self::token(self::probe(static function () {
                    return function_exists('php_uname') ? php_uname('s') : '';
                })),
                'version' => self::token(self::probe(static function () {
                    return function_exists('php_uname') ? php_uname('r') : '';
                })),
            ],
        ];
    }

    /** Failed or disabled probes must not interrupt other collectors. */
    private static function probe($callback) {
        try {
            $value = @$callback();
            return is_string($value) ? $value : '';
        } catch (Throwable $exception) {
            return '';
        }
    }

    /** Keeps only bounded version/identifier tokens, never paths or whole banners. */
    private static function token($value) {
        return is_string($value) && preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._+~:\-]{0,127}$/D', $value) ? $value : 'unknown';
    }

    /** Reads only distribution ID and VERSION_ID; os-release is never executed. */
    public static function parse_os_release($contents) {
        $result = ['id' => 'unknown', 'version' => 'unknown'];
        foreach (explode("\n", substr((string) $contents, 0, 16384)) as $line) {
            if (preg_match('/^(ID|VERSION_ID)=(.*)$/D', trim($line), $matches)) {
                $value = $matches[2];
                if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                    $value = substr($value, 1, -1);
                }
                $result[$matches[1] === 'ID' ? 'id' : 'version'] = self::token($value);
            }
        }
        return $result;
    }

    /** Extracts the web server product only, excluding modules and banner suffixes. */
    public static function parse_web_server($banner) {
        if (preg_match('/\b(Apache|nginx|Microsoft-IIS|LiteSpeed|OpenLiteSpeed|Caddy)(?:\/([0-9][a-zA-Z0-9._+~\-]{0,127}))?(?=[\s\/]|$)/i', substr((string) $banner, 0, 1024), $matches)) {
            return ['name' => strtolower($matches[1]), 'version' => self::token($matches[2] ?? '')];
        }
        return ['name' => 'unknown', 'version' => 'unknown'];
    }

    /** Uses the connected database server version, never the PHP client library version. */
    public static function parse_database($banner) {
        $banner = substr((string) $banner, 0, 1024);
        $mariadb = stripos($banner, 'MariaDB') !== false;
        if ($mariadb && strpos($banner, '5.5.5-') === 0) {
            $banner = substr($banner, 6);
        }
        if (preg_match('/^([0-9]+\.[0-9]+\.[0-9]+(?:[a-zA-Z0-9._+~:\-]*))(?=\s|$)/', $banner, $matches)) {
            return ['name' => $mariadb ? 'mariadb' : 'mysql', 'version' => self::token($matches[1])];
        }
        return ['name' => $mariadb ? 'mariadb' : 'unknown', 'version' => 'unknown'];
    }
}
