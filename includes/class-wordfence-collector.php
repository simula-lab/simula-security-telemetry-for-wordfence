<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Wordfence_Collector {
    /** Builds the SQL condition used to identify blocked requests in a hits table. */
    public static function blocked_where_sql($table) {
        $clauses = [];

        $action_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['action']);
        if ($action_column !== null) {
            $action_identifier = self::quote_identifier($action_column);
            $clauses[]         = '(' . $action_identifier . " IS NOT NULL AND $action_identifier LIKE 'blocked:%')";
        }

        $status_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['statusCode', 'status']);
        if ($status_column !== null) {
            $clauses[] = self::quote_identifier($status_column) . ' IN (403, 503)';
        }

        return self::combine_where_any($clauses);
    }

    /** Builds the SQL condition used to detect failed login activity. */
    public static function failed_login_where_sql($table) {
        return self::text_search_where_sql(
            $table,
            ['action', 'URL', 'url', 'requestUri', 'path'],
            ['loginfail', 'login failure', 'failed login', 'invalid username', 'incorrect password']
        );
    }

    /** Builds the SQL condition used to detect throttled or rate-limited requests. */
    public static function rate_limited_where_sql($table) {
        return self::text_search_where_sql(
            $table,
            ['action', 'URL', 'url', 'requestUri', 'path'],
            ['throttle', 'throttled', 'rate limit', 'rate-limit', 'rate_limited']
        );
    }

    /** Builds the SQL condition used to detect username/password brute-force activity. */
    public static function brute_force_username_where_sql($table) {
        $xmlrpc_where = self::brute_force_xmlrpc_where_sql($table);
        $username_sql = self::combine_where_any([
            self::failed_login_where_sql($table),
            self::text_search_where_sql(
                $table,
                ['action', 'URL', 'url', 'requestUri', 'path'],
                ['brute force', 'brute-force', 'wp-login.php', 'login attempt']
            ),
        ]);

        if ($username_sql === '0=1') {
            return $username_sql;
        }

        if ($xmlrpc_where !== '0=1') {
            return '(' . $username_sql . ' AND NOT ' . $xmlrpc_where . ')';
        }

        return $username_sql;
    }

    /** Builds the SQL condition used to detect XML-RPC brute-force activity. */
    public static function brute_force_xmlrpc_where_sql($table) {
        return self::text_search_where_sql(
            $table,
            ['action', 'URL', 'url', 'requestUri', 'path'],
            ['xmlrpc', 'xml-rpc']
        );
    }

    /** Builds SQL SELECT expressions that count matching rows across configured time windows. */
    public static function build_window_count_select_sql($prefix, $condition_sql, $time_identifier, $windows) {
        $selects = [];

        foreach (Simula_Security_Telemetry_Config::WINDOWS as $window) {
            $selects[] = sprintf(
                'SUM(CASE WHEN %1$s >= %2$d AND %3$s THEN 1 ELSE 0 END) AS %4$s_count_%5$s',
                $time_identifier,
                (int) $windows[$window],
                $condition_sql,
                $prefix,
                $window
            );
        }

        return implode(",\n                ", $selects);
    }

    /** Collects the top blocked attack sources by country and normalized IP range. */
    public static function collect_top_attack_sources($table, $time_identifier, $blocked_where, $since_timestamp) {
        global $wpdb;

        $sources        = [];
        $table_identifier = self::quote_identifier($table);
        $country_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['ctry', 'countryCode', 'country']);

        if ($country_column !== null) {
            $country_identifier = self::quote_identifier($country_column);
            $country_rows       = Simula_Security_Telemetry_Util::db_get_results(
                "SELECT $country_identifier AS source_name, COUNT(*) AS count_total
                FROM $table_identifier
                WHERE $time_identifier >= " . (int) $since_timestamp . " AND $blocked_where AND $country_identifier IS NOT NULL AND $country_identifier <> ''
                GROUP BY $country_identifier
                ORDER BY count_total DESC
                LIMIT 10",
                ARRAY_A
            );

            foreach ((array) $country_rows as $row) {
                if (empty($row['source_name'])) {
                    continue;
                }

                $sources[] = [
                    'source_type' => 'country',
                    'source'      => (string) $row['source_name'],
                    'count_total' => (int) ($row['count_total'] ?? 0),
                ];
            }
        }

        $ip_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['IP', 'ip']);
        if ($ip_column !== null) {
            $ip_identifier = self::quote_identifier($ip_column);
            $ip_rows       = Simula_Security_Telemetry_Util::db_get_results(
                "SELECT $ip_identifier AS source_ip, COUNT(*) AS count_total
                FROM $table_identifier
                WHERE $time_identifier >= " . (int) $since_timestamp . " AND $blocked_where AND $ip_identifier IS NOT NULL
                GROUP BY $ip_identifier
                ORDER BY count_total DESC
                LIMIT 100",
                ARRAY_A
            );
            $ip_ranges = [];

            foreach ((array) $ip_rows as $row) {
                $range = self::normalize_ip_range($row['source_ip'] ?? '');
                if ($range === '') {
                    continue;
                }

                if (!isset($ip_ranges[$range])) {
                    $ip_ranges[$range] = 0;
                }

                $ip_ranges[$range] += (int) ($row['count_total'] ?? 0);
            }

            arsort($ip_ranges);

            foreach (array_slice($ip_ranges, 0, 10, true) as $range => $count) {
                $sources[] = [
                    'source_type' => 'ip_range',
                    'source'      => (string) $range,
                    'count_total' => (int) $count,
                ];
            }
        }

        return $sources;
    }

    /** Collects current IP and user lockout totals from available Wordfence tables. */
    public static function collect_lockout_counts($now) {
        global $wpdb;

        $counts           = ['ip' => 0, 'user' => 0];
        $blocked_ip_table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfBlockedIPLog');

        if (Simula_Security_Telemetry_Wordfence_Schema::table_exists($blocked_ip_table)) {
            $blocked_ip_table_identifier = self::quote_identifier($blocked_ip_table);
            $ip_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($blocked_ip_table, ['IP', 'ip']);
            if ($ip_column !== null) {
                $ip_identifier = self::quote_identifier($ip_column);
                $lockout_where = self::lockout_active_where_sql($blocked_ip_table, $now);
                $query         = 'SELECT COUNT(DISTINCT ' . $ip_identifier . ") AS total FROM $blocked_ip_table_identifier";

                if ($lockout_where !== '') {
                    $query .= ' WHERE ' . $lockout_where;
                }

                $counts['ip'] = (int) Simula_Security_Telemetry_Util::db_get_var($query);
            }

            $user_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($blocked_ip_table, ['username', 'userName', 'user_id', 'userID', 'userId']);
            if ($user_column !== null) {
                $user_identifier = self::quote_identifier($user_column);
                $lockout_where   = self::lockout_active_where_sql($blocked_ip_table, $now);
                $query           = 'SELECT COUNT(DISTINCT ' . $user_identifier . ") AS total FROM $blocked_ip_table_identifier WHERE $user_identifier IS NOT NULL AND $user_identifier <> ''";

                if ($lockout_where !== '') {
                    $query .= ' AND ' . $lockout_where;
                }

                $counts['user'] = (int) Simula_Security_Telemetry_Util::db_get_var($query);
            }
        }

        $login_table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfLogins');
        if ($counts['user'] === 0 && Simula_Security_Telemetry_Wordfence_Schema::table_exists($login_table)) {
            $user_column   = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($login_table, ['username', 'userName', 'user_id', 'userID', 'userId']);
            $lockout_where = self::lockout_active_where_sql($login_table, $now);

            if ($user_column !== null && $lockout_where !== '') {
                $user_identifier = self::quote_identifier($user_column);
                $login_table_identifier = self::quote_identifier($login_table);
                $counts['user']  = (int) Simula_Security_Telemetry_Util::db_get_var(
                    'SELECT COUNT(DISTINCT ' . $user_identifier . ") AS total FROM $login_table_identifier WHERE $user_identifier IS NOT NULL AND $user_identifier <> '' AND " . $lockout_where
                );
            }
        }

        return $counts;
    }

    /** Collects Wordfence two-factor status and protected-user counts. */
    public static function collect_two_factor_metrics() {
        global $wpdb;

        $metrics        = ['enabled' => 0, 'protected_users' => 0];
        $secrets_table  = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfls_2fa_secrets');
        $settings_table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfls_settings');

        if (Simula_Security_Telemetry_Wordfence_Schema::table_exists($secrets_table)) {
            $secrets_table_identifier = self::quote_identifier($secrets_table);
            $user_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($secrets_table, ['user_id', 'userID', 'userId', 'user']);
            if ($user_column !== null) {
                $metrics['protected_users'] = (int) Simula_Security_Telemetry_Util::db_get_var(
                    'SELECT COUNT(DISTINCT ' . self::quote_identifier($user_column) . ") FROM $secrets_table_identifier"
                );
            } else {
                $metrics['protected_users'] = (int) Simula_Security_Telemetry_Util::db_get_var("SELECT COUNT(*) FROM $secrets_table_identifier");
            }
        }

        if ($metrics['protected_users'] > 0) {
            $metrics['enabled'] = 1;
        } elseif (Simula_Security_Telemetry_Wordfence_Schema::table_exists($settings_table)) {
            $settings_table_identifier = self::quote_identifier($settings_table);
            $metrics['enabled'] = (int) (Simula_Security_Telemetry_Util::db_get_var("SELECT COUNT(*) FROM $settings_table_identifier") > 0);
        }

        return $metrics;
    }

    /** Collects scan issue totals grouped by severity and finding category. */
    public static function collect_scan_issue_metrics() {
        global $wpdb;

        $metrics = [
            'severity'        => [],
            'malware'         => 0,
            'file_change'     => 0,
            'vulnerabilities' => [
                'core'   => 0,
                'plugin' => 0,
                'theme'  => 0,
            ],
        ];
        $table   = Simula_Security_Telemetry_Wordfence_Schema::scan_issue_table();

        if ($table === null) {
            return $metrics;
        }

        $table_identifier = self::quote_identifier($table);
        $severity_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['severity', 'level', 'status']);
        if ($severity_column !== null) {
            $severity_identifier = self::quote_identifier($severity_column);
            $metrics['severity'] = Simula_Security_Telemetry_Util::db_get_results(
                "SELECT $severity_identifier AS severity, COUNT(*) AS count_total
                FROM $table_identifier
                GROUP BY $severity_identifier
                ORDER BY count_total DESC",
                ARRAY_A
            );
        }

        $text_columns = self::available_columns($table, ['type', 'shortMsg', 'longMsg', 'description', 'solution', 'data', 'ignoreInfo']);
        if ($text_columns === []) {
            return $metrics;
        }

        // Prefer the structured issue type for malware classification. Broad text matching
        // caused non-malware issues like skipped scan paths and unknown files to be counted
        // as malware when their human-readable descriptions mentioned malware scans.
        $malware_where = self::scan_issue_type_where_sql($table, ['malware', 'malicious', 'backdoor', 'trojan', 'phishing', 'virus', 'webshell']);
        $file_where    = self::text_search_where_sql_from_columns($text_columns, ['file changed', 'changed file', 'modified file', 'unknown file', 'file contents changed']);
        $vuln_where    = self::text_search_where_sql_from_columns($text_columns, ['vulnerab', 'outdated', 'security update', 'update available']);
        $core_where    = self::combine_where_all([
            self::text_search_where_sql_from_columns($text_columns, ['core']),
            $vuln_where,
        ]);
        $plugin_where  = self::combine_where_all([
            self::text_search_where_sql_from_columns($text_columns, ['plugin']),
            $vuln_where,
        ]);
        $theme_where   = self::combine_where_all([
            self::text_search_where_sql_from_columns($text_columns, ['theme']),
            $vuln_where,
        ]);
        $row           = Simula_Security_Telemetry_Util::db_get_row(
            "SELECT
                SUM(CASE WHEN $malware_where THEN 1 ELSE 0 END) AS malware_total,
                SUM(CASE WHEN $file_where THEN 1 ELSE 0 END) AS file_change_total,
                SUM(CASE WHEN $core_where THEN 1 ELSE 0 END) AS core_total,
                SUM(CASE WHEN $plugin_where THEN 1 ELSE 0 END) AS plugin_total,
                SUM(CASE WHEN $theme_where THEN 1 ELSE 0 END) AS theme_total
            FROM $table_identifier",
            ARRAY_A
        );

        $metrics['malware']                   = isset($row['malware_total']) ? (int) $row['malware_total'] : 0;
        $metrics['file_change']               = isset($row['file_change_total']) ? (int) $row['file_change_total'] : 0;
        $metrics['vulnerabilities']['core']   = isset($row['core_total']) ? (int) $row['core_total'] : 0;
        $metrics['vulnerabilities']['plugin'] = isset($row['plugin_total']) ? (int) $row['plugin_total'] : 0;
        $metrics['vulnerabilities']['theme']  = isset($row['theme_total']) ? (int) $row['theme_total'] : 0;

        return $metrics;
    }

    /** Collects latest source timestamps from Wordfence hit and scan tables. */
    public static function collect_source_freshness($hits_table, $time_identifier, $blocked_where, $now) {
        global $wpdb;

        $freshness = [
            'latest_hit'         => 0,
            'latest_blocked_hit' => 0,
            'latest_scan'        => 0,
            'scan_age'           => 0,
        ];

        if (Simula_Security_Telemetry_Wordfence_Schema::table_exists($hits_table)) {
            $hits_table_identifier = self::quote_identifier($hits_table);
            $freshness['latest_hit'] = (int) Simula_Security_Telemetry_Util::db_get_var("SELECT COALESCE(MAX($time_identifier), 0) FROM $hits_table_identifier");
            if ($blocked_where !== '0=1') {
                $freshness['latest_blocked_hit'] = (int) Simula_Security_Telemetry_Util::db_get_var("SELECT COALESCE(MAX($time_identifier), 0) FROM $hits_table_identifier WHERE $blocked_where");
            }
        }

        $freshness['latest_scan'] = self::collect_latest_scan_timestamp();
        $freshness['scan_age']    = $freshness['latest_scan'] > 0 ? max(0, (int) $now - (int) $freshness['latest_scan']) : 0;

        return $freshness;
    }

    /** Collects Wordfence installation, version, firewall, live traffic, scan, and license posture. */
    public static function collect_wordfence_posture() {
        $version      = self::wordfence_version();
        $installed    = $version !== '' || Simula_Security_Telemetry_Wordfence_Schema::table_exists(Simula_Security_Telemetry_Wordfence_Schema::wordfence_hits_table());
        $license_type = self::wordfence_license_type();

        return [
            'installed'            => $installed ? 1 : 0,
            'version'              => $version !== '' ? $version : 'unknown',
            'firewall_enabled'     => self::wordfence_config_enabled(['firewallEnabled', 'wafEnabled'], $installed ? 1 : 0),
            'firewall_optimized'   => self::wordfence_firewall_optimized(),
            'live_traffic_enabled' => self::wordfence_config_enabled(['liveTrafficEnabled', 'liveTraffic'], 0),
            'scan_enabled'         => self::wordfence_config_enabled(['scansEnabled', 'scheduledScansEnabled'], $installed ? 1 : 0),
            'license_type'         => $license_type,
        ];
    }

    /** Collects WordPress update and administrator 2FA posture. */
    public static function collect_wordpress_posture() {
        $admin_ids      = self::administrator_user_ids();
        $protected_ids  = self::two_factor_protected_user_ids();
        $without_2fa    = 0;
        $protected_flip = array_fill_keys(array_map('intval', $protected_ids), true);

        foreach ($admin_ids as $admin_id) {
            if (empty($protected_flip[(int) $admin_id])) {
                $without_2fa++;
            }
        }

        return [
            'wordpress_version'             => self::wordpress_version(),
            'core_update_available'        => self::core_update_available(),
            'plugin_update_available_total' => self::plugin_update_count(),
            'theme_update_available_total'  => self::theme_update_count(),
            'admin_users_total'             => count($admin_ids),
            'admin_users_without_2fa_total' => $without_2fa,
        ];
    }

    /** Filters a list of candidate column names down to those present in a table. */
    private static function available_columns($table, $candidates) {
        return Simula_Security_Telemetry_Util::resolve_available_candidates(
            Simula_Security_Telemetry_Wordfence_Schema::table_columns($table),
            $candidates
        );
    }

    /** Finds the latest timestamp-like value from the current Wordfence scan issue table. */
    private static function collect_latest_scan_timestamp() {
        global $wpdb;

        $table = Simula_Security_Telemetry_Wordfence_Schema::scan_issue_table();
        if ($table === null) {
            return 0;
        }

        $column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column(
            $table,
            ['lastUpdated', 'last_update', 'updated_at', 'modified', 'ctime', 'time', 'created_at']
        );

        if ($column === null) {
            return 0;
        }

        $table_identifier = self::quote_identifier($table);
        $value = Simula_Security_Telemetry_Util::db_get_var('SELECT COALESCE(MAX(' . self::quote_identifier($column) . "), 0) FROM $table_identifier");

        return self::normalize_timestamp_value($value);
    }

    /** Normalizes numeric or parseable date values into Unix timestamps. */
    private static function normalize_timestamp_value($value) {
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value) && $value !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? 0 : max(0, (int) $timestamp);
        }

        return 0;
    }

    /** Returns the detected Wordfence version. */
    private static function wordfence_version() {
        if (defined('WORDFENCE_VERSION')) {
            return (string) WORDFENCE_VERSION;
        }

        if (!function_exists('get_plugins')) {
            $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_readable($plugin_file)) {
                require_once $plugin_file;
            }
        }

        if (function_exists('get_plugins')) {
            $plugins = get_plugins();
            if (isset($plugins['wordfence/wordfence.php']['Version'])) {
                return (string) $plugins['wordfence/wordfence.php']['Version'];
            }
        }

        return '';
    }

    /** Reads a boolean-ish Wordfence config value when wfConfig is available. */
    private static function wordfence_config_enabled($keys, $default) {
        if (!class_exists('wfConfig') || !method_exists('wfConfig', 'get')) {
            return (int) $default;
        }

        foreach ((array) $keys as $key) {
            $value = wfConfig::get($key, null);
            if ($value !== null && $value !== '') {
                return empty($value) ? 0 : 1;
            }
        }

        return (int) $default;
    }

    /** Detects whether the Wordfence firewall appears optimized. */
    private static function wordfence_firewall_optimized() {
        if (defined('WFWAF_AUTO_PREPEND') && WFWAF_AUTO_PREPEND) {
            return 1;
        }

        if (class_exists('wfConfig') && method_exists('wfConfig', 'get')) {
            $status = wfConfig::get('wafStatus', '');
            if (is_string($status) && stripos($status, 'enabled') !== false) {
                return 1;
            }
        }

        return 0;
    }

    /** Returns free, premium, or unknown for the Wordfence license type. */
    private static function wordfence_license_type() {
        if (class_exists('wfConfig') && method_exists('wfConfig', 'get')) {
            $is_paid = wfConfig::get('isPaid', null);
            if ($is_paid !== null && $is_paid !== '') {
                return empty($is_paid) ? 'free' : 'premium';
            }
        }

        return 'unknown';
    }

    /** Returns administrator user IDs. */
    private static function administrator_user_ids() {
        if (!function_exists('get_users')) {
            return [];
        }

        $users = get_users([
            'role'   => 'administrator',
            'fields' => 'ID',
        ]);

        return array_map('intval', is_array($users) ? $users : []);
    }

    /** Returns user IDs with Wordfence two-factor secrets when available. */
    private static function two_factor_protected_user_ids() {
        global $wpdb;

        $table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_table('wfls_2fa_secrets');
        if (!Simula_Security_Telemetry_Wordfence_Schema::table_exists($table)) {
            return [];
        }

        $user_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['user_id', 'userID', 'userId', 'user']);
        if ($user_column === null) {
            return [];
        }

        $table_identifier = self::quote_identifier($table);

        return array_map('intval', (array) Simula_Security_Telemetry_Util::db_get_col('SELECT DISTINCT ' . self::quote_identifier($user_column) . " FROM $table_identifier"));
    }

    /** Returns the installed WordPress version, or unknown if the runtime cannot provide it. */
    private static function wordpress_version() {
        if (function_exists('get_bloginfo')) {
            $version = (string) get_bloginfo('version');
            if ($version !== '') {
                return $version;
            }
        }

        global $wp_version;

        if (is_string($wp_version) && $wp_version !== '') {
            return $wp_version;
        }

        return 'unknown';
    }

    /** Returns whether a WordPress core update is available. */
    private static function core_update_available() {
        $updates = function_exists('get_site_transient') ? get_site_transient('update_core') : null;
        if (!is_object($updates) || empty($updates->updates) || !is_array($updates->updates)) {
            return 0;
        }

        foreach ($updates->updates as $update) {
            if (is_object($update) && isset($update->response) && $update->response === 'upgrade') {
                return 1;
            }
        }

        return 0;
    }

    /** Returns the number of plugin updates available. */
    private static function plugin_update_count() {
        $updates = function_exists('get_site_transient') ? get_site_transient('update_plugins') : null;

        return is_object($updates) && isset($updates->response) && is_array($updates->response) ? count($updates->response) : 0;
    }

    /** Returns the number of theme updates available. */
    private static function theme_update_count() {
        $updates = function_exists('get_site_transient') ? get_site_transient('update_themes') : null;

        return is_object($updates) && isset($updates->response) && is_array($updates->response) ? count($updates->response) : 0;
    }

    /** Builds a text-search SQL condition across matching columns in a table. */
    private static function text_search_where_sql($table, $candidate_columns, $terms) {
        return self::text_search_where_sql_from_columns(self::available_columns($table, $candidate_columns), $terms);
    }

    /** Builds a type-based scan issue SQL condition using structured Wordfence issue types when present. */
    private static function scan_issue_type_where_sql($table, $terms) {
        $type_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['type']);
        if ($type_column === null) {
            return self::text_search_where_sql(
                $table,
                ['shortMsg', 'longMsg', 'data'],
                $terms
            );
        }

        return self::text_search_where_sql_from_columns([$type_column], $terms);
    }

    /** Builds a text-search SQL condition from a specific list of columns and terms. */
    private static function text_search_where_sql_from_columns($columns, $terms) {
        global $wpdb;

        $clauses = [];

        foreach ((array) $columns as $column) {
            $identifier = self::quote_identifier($column);

            foreach ((array) $terms as $term) {
                $like      = '%' . strtolower($wpdb->esc_like((string) $term)) . '%';
                $clauses[] = "LOWER(COALESCE(CAST($identifier AS CHAR), '')) LIKE '" . esc_sql($like) . "'";
            }
        }

        return self::combine_where_any($clauses);
    }

    /** Escapes a database identifier for use in dynamic SQL fragments. */
    private static function quote_identifier($identifier) {
        return Simula_Security_Telemetry_Util::quote_identifier($identifier);
    }

    /** Combines SQL clauses with OR and returns an always-false condition when empty. */
    private static function combine_where_any($clauses) {
        $filtered = [];

        foreach ((array) $clauses as $clause) {
            $clause = trim((string) $clause);
            if ($clause === '' || $clause === '0=1') {
                continue;
            }

            $filtered[] = $clause;
        }

        if ($filtered === []) {
            return '0=1';
        }

        return '(' . implode(' OR ', $filtered) . ')';
    }

    /** Combines SQL clauses with AND and returns an always-false condition when empty. */
    private static function combine_where_all($clauses) {
        $filtered = [];

        foreach ((array) $clauses as $clause) {
            $clause = trim((string) $clause);
            if ($clause === '') {
                continue;
            }

            if ($clause === '0=1') {
                return '0=1';
            }

            $filtered[] = $clause;
        }

        if ($filtered === []) {
            return '0=1';
        }

        return '(' . implode(' AND ', $filtered) . ')';
    }

    /** Builds the SQL condition used to identify active lockouts in a table. */
    private static function lockout_active_where_sql($table, $now) {
        $clauses = [];

        foreach (['expiration', 'blockedUntil', 'expiresAt', 'lockedOutUntil', 'lockoutTime'] as $column) {
            if (Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, [$column]) !== null) {
                $clauses[] = self::quote_identifier($column) . ' > ' . (int) $now;
            }
        }

        foreach (['blocked', 'lockedOut', 'isLocked'] as $column) {
            if (Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, [$column]) !== null) {
                $clauses[] = self::quote_identifier($column) . ' = 1';
            }
        }

        $status_column = Simula_Security_Telemetry_Wordfence_Schema::first_available_column($table, ['status']);
        if ($status_column !== null) {
            $status_identifier = self::quote_identifier($status_column);
            $clauses[]         = "LOWER(COALESCE(CAST($status_identifier AS CHAR), '')) LIKE '%lock%'";
        }

        if ($clauses === []) {
            return '';
        }

        return self::combine_where_any($clauses);
    }

    /** Normalizes an IP value into a /24 IPv4 or /64 IPv6 range label. */
    private static function normalize_ip_range($value) {
        if (!is_scalar($value)) {
            return '';
        }

        $ip = trim((string) $value);
        if ($ip === '' || preg_match('/[[:cntrl:]]/', $ip)) {
            return '';
        }

        if (ctype_digit($ip)) {
            $numeric_ip = (float) $ip;
            if ($numeric_ip >= 0 && $numeric_ip <= 4294967295) {
                $ip = (string) long2ip((int) $numeric_ip);
            }
        }

        if (strpos($ip, '.') !== false) {
            $parts = explode('.', $ip);
            if (count($parts) >= 4) {
                return implode('.', array_slice($parts, 0, 3)) . '.0/24';
            }
        }

        if (strpos($ip, ':') !== false) {
            $packed = @inet_pton($ip);
            if ($packed === false) {
                return '';
            }

            $hex = bin2hex($packed);

            return sprintf(
                '%s:%s:%s:%s::/64',
                substr($hex, 0, 4),
                substr($hex, 4, 4),
                substr($hex, 8, 4),
                substr($hex, 12, 4)
            );
        }

        return '';
    }
}
