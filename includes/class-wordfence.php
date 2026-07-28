<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Wordfence {
    /** Returns the resolved Wordfence hits table name. */
    public static function wordfence_hits_table() {
        return Simula_Security_Telemetry_Wordfence_Schema::wordfence_hits_table();
    }

    /** Checks whether a database table exists, using a local cache. */
    public static function table_exists($table) {
        return Simula_Security_Telemetry_Wordfence_Schema::table_exists($table);
    }

    /** Returns the first column name that exists from a list of candidates. */
    public static function first_available_column($table, $candidates) {
        return Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, $candidates);
    }

    /** Builds the SQL condition used to identify blocked requests in a hits table. */
    public static function blocked_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::blocked_where_sql($table);
    }

    /** Builds the SQL condition used to detect failed login activity. */
    public static function failed_login_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::failed_login_where_sql($table);
    }

    /** Builds the SQL condition used to detect throttled or rate-limited requests. */
    public static function rate_limited_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::rate_limited_where_sql($table);
    }

    /** Builds the SQL condition used to detect username/password brute-force activity. */
    public static function brute_force_username_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::brute_force_username_where_sql($table);
    }

    /** Builds the SQL condition used to detect XML-RPC brute-force activity. */
    public static function brute_force_xmlrpc_where_sql($table) {
        return Simula_Security_Telemetry_Wordfence_Collector::brute_force_xmlrpc_where_sql($table);
    }

    /** Builds SQL SELECT expressions that count matching rows across configured time windows. */
    public static function build_window_count_select_sql($prefix, $condition_sql, $time_identifier, $windows) {
        return Simula_Security_Telemetry_Wordfence_Collector::build_window_count_select_sql($prefix, $condition_sql, $time_identifier, $windows);
    }

    /** Collects the top blocked attack sources by country and normalized IP range. */
    public static function collect_top_attack_sources($table, $time_identifier, $blocked_where, $since_timestamp) {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_top_attack_sources($table, $time_identifier, $blocked_where, $since_timestamp);
    }

    /** Collects current IP and user lockout totals from available Wordfence tables. */
    public static function collect_lockout_counts($now) {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_lockout_counts($now);
    }

    /** Collects Wordfence two-factor status and protected-user counts. */
    public static function collect_two_factor_metrics() {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_two_factor_metrics();
    }

    /** Collects scan issue totals grouped by severity and finding category. */
    public static function collect_scan_issue_metrics() {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_scan_issue_metrics();
    }

    /** Collects latest source timestamps from Wordfence hit and scan tables. */
    public static function collect_source_freshness($hits_table, $time_identifier, $blocked_where, $now) {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_source_freshness($hits_table, $time_identifier, $blocked_where, $now);
    }

    /** Collects Wordfence installation and runtime posture. */
    public static function collect_wordfence_posture() {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_wordfence_posture();
    }

    /** Collects WordPress update and administrator 2FA posture. */
    public static function collect_wordpress_posture() {
        return Simula_Security_Telemetry_Wordfence_Collector::collect_wordpress_posture();
    }

    /** Builds the likely table names for a Wordfence table suffix. */
    public static function wordfence_table_candidates($suffix) {
        return Simula_Security_Telemetry_Wordfence_Schema::wordfence_table_candidates($suffix);
    }

    /** Returns the column metadata for a table, cached by table name. */
    public static function table_columns($table) {
        return Simula_Security_Telemetry_Wordfence_Schema::table_columns($table);
    }
}

