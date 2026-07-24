<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Util {
    /** Returns an initialized WordPress filesystem handler, or null when unavailable. */
    public static function filesystem() {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!WP_Filesystem()) {
            return null;
        }

        return $wp_filesystem;
    }

    /** Escapes a single database identifier for use in dynamic SQL fragments. */
    public static function quote_identifier($identifier) {
        $identifier = (string) $identifier;

        if (!preg_match('/\A[A-Za-z0-9_]+\z/', $identifier)) {
            return '``';
        }

        return '`' . esc_sql($identifier) . '`';
    }

    /** Executes internally assembled SQL after identifiers/fragments have been validated. */
    public static function db_get_var($query) {
        global $wpdb;

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_var($query);
    }

    /** Executes internally assembled SQL after identifiers/fragments have been validated. */
    public static function db_get_row($query, $output = OBJECT) {
        global $wpdb;

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_row($query, $output);
    }

    /** Executes internally assembled SQL after identifiers/fragments have been validated. */
    public static function db_get_results($query, $output = OBJECT) {
        global $wpdb;

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results($query, $output);
    }

    /** Executes internally assembled SQL after identifiers/fragments have been validated. */
    public static function db_get_col($query) {
        global $wpdb;

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_col($query);
    }

    /** Returns the first matching candidate from a resolved column metadata map. */
    public static function resolve_first_candidate($columns, $candidates) {
        foreach ((array) $candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /** Filters candidate names down to the entries available in a resolved column metadata map. */
    public static function resolve_available_candidates($columns, $candidates) {
        $available = [];

        foreach ((array) $candidates as $candidate) {
            if (isset($columns[$candidate])) {
                $available[] = $candidate;
            }
        }

        return $available;
    }

    /** Validates and normalizes an absolute file path against an allowed extension pattern. */
    public static function sanitize_file_setting_path($value, $default, $absolute_error_code, $absolute_error_message, $extension_error_code, $extension_pattern, $extension_error_message) {
        $value = trim(wp_unslash((string) $value));
        if ($value === '') {
            $value = (string) $default;
        }

        $value = wp_normalize_path($value);

        if (!self::is_absolute_path($value)) {
            add_settings_error(
                'sstfw_metrics',
                $absolute_error_code,
                $absolute_error_message,
                'error'
            );

            return (string) $default;
        }

        if (!preg_match($extension_pattern, $value)) {
            add_settings_error(
                'sstfw_metrics',
                $extension_error_code,
                $extension_error_message,
                'error'
            );

            return (string) $default;
        }

        return $value;
    }

    /** Checks whether a filesystem path is absolute on Unix or Windows. */
    private static function is_absolute_path($path) {
        return (bool) preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path);
    }
}

