<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Wordfence_Schema {
    /** Returns the resolved Wordfence hits table name. */
    public static function wordfence_hits_table() {
        return self::wordfence_table_aliases(['wfHits', 'wfhits']);
    }

    /** Checks whether a database table exists, using a local cache. */
    public static function table_exists($table) {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $cache[$table] = self::find_existing_table_name([$table]) !== null;

        return $cache[$table];
    }

    /** Returns the first column name that exists from a list of candidates. */
    public static function first_available_column($table, $candidates) {
        return Simula_Security_Telemetry_Util::resolve_first_candidate(self::table_columns($table), $candidates);
    }

    /** Builds the likely table names for a Wordfence table suffix. */
    public static function wordfence_table_candidates($suffix) {
        global $wpdb;

        $candidates = [];
        $prefixes   = [
            (string) $wpdb->prefix,
            isset($wpdb->base_prefix) ? (string) $wpdb->base_prefix : (string) $wpdb->prefix,
        ];

        foreach ($prefixes as $prefix) {
            if ($prefix === '') {
                continue;
            }

            $table = $prefix . $suffix;
            if (!in_array($table, $candidates, true)) {
                $candidates[] = $table;
            }
        }

        return $candidates;
    }

    /** Resolves a Wordfence table suffix to the best matching database table name. */
    public static function wordfence_table($suffix) {
        static $cache = [];

        if (isset($cache[$suffix])) {
            return $cache[$suffix];
        }

        $table = self::find_existing_table_name(self::wordfence_table_candidates($suffix));
        if ($table !== null) {
            $cache[$suffix] = $table;
            return $cache[$suffix];
        }

        $matches = self::discover_wordfence_tables($suffix);
        if (count($matches) === 1) {
            $cache[$suffix] = $matches[0];
            return $cache[$suffix];
        }

        $candidates     = self::wordfence_table_candidates($suffix);
        $cache[$suffix] = isset($candidates[0]) ? $candidates[0] : (string) $suffix;

        return $cache[$suffix];
    }

    /** Returns the column metadata for a table, cached by table name. */
    public static function table_columns($table) {
        static $cache = [];
        global $wpdb;

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        if (!self::table_exists($table)) {
            $cache[$table] = [];
            return $cache[$table];
        }

        $table_identifier = Simula_Security_Telemetry_Util::quote_identifier($table);
        $rows             = Simula_Security_Telemetry_Util::db_get_results("SHOW COLUMNS FROM $table_identifier", ARRAY_A);
        $columns          = [];

        foreach ((array) $rows as $row) {
            if (!isset($row['Field'])) {
                continue;
            }

            $columns[(string) $row['Field']] = $row;
        }

        $cache[$table] = $columns;

        return $cache[$table];
    }

    /** Returns the Wordfence scan issue table currently available in the database. */
    public static function scan_issue_table() {
        #TODO: account for the situation where wf table names has no capital letters
        foreach (['wfIssues', 'wfPendingIssues'] as $suffix) {
            $table = self::wordfence_table($suffix);
            if (self::table_exists($table)) {
                return $table;
            }
        }

        return null;
    }

    /** Resolves a Wordfence table from multiple known suffix aliases. */
    private static function wordfence_table_aliases($suffixes) {
        static $cache = [];

        $suffixes  = array_values(array_unique(array_filter(array_map('strval', (array) $suffixes))));
        $cache_key = implode('|', $suffixes);

        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        foreach ($suffixes as $suffix) {
            $resolved = self::wordfence_table($suffix);
            if (self::table_exists($resolved)) {
                $cache[$cache_key] = $resolved;
                return $cache[$cache_key];
            }
        }

        $fallback          = isset($suffixes[0]) ? self::wordfence_table($suffixes[0]) : '';
        $cache[$cache_key] = $fallback;

        return $cache[$cache_key];
    }

    /** Returns the first existing table name that matches the provided candidates. */
    private static function find_existing_table_name($candidates) {
        $tables = self::database_tables();

        foreach ((array) $candidates as $candidate) {
            foreach ($tables as $table) {
                if (strcasecmp($table, (string) $candidate) === 0) {
                    return $table;
                }
            }
        }

        return null;
    }

    /** Returns the list of database tables, cached for repeated lookups. */
    private static function database_tables() {
        static $cache = null;
        global $wpdb;

        if ($cache !== null) {
            return $cache;
        }

        $rows  = Simula_Security_Telemetry_Util::db_get_col('SHOW TABLES');
        $cache = [];

        foreach ((array) $rows as $table) {
            $table = (string) $table;
            if ($table !== '') {
                $cache[] = $table;
            }
        }

        return $cache;
    }

    /** Finds database tables whose names end with the requested Wordfence suffix. */
    private static function discover_wordfence_tables($suffix) {
        static $cache = [];

        $suffix = (string) $suffix;

        if (isset($cache[$suffix])) {
            return $cache[$suffix];
        }

        $rows    = self::database_tables();
        $matches = [];

        foreach ((array) $rows as $table) {
            $table = (string) $table;
            if ($table === '') {
                continue;
            }

            if (strlen($table) < strlen($suffix) || strcasecmp(substr($table, -strlen($suffix)), $suffix) !== 0) {
                continue;
            }

            if (!in_array($table, $matches, true)) {
                $matches[] = $table;
            }
        }

        $cache[$suffix] = $matches;

        return $cache[$suffix];
    }
}

