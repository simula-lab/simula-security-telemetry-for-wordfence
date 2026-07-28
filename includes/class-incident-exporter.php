<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Incidents {
    /** Initializes the incident cursor from the current maximum Wordfence hit ID and returns the resulting state. */
    public static function initialize_cursor_if_needed($state = null, $persist_state = true) {
        global $wpdb;

        $state = is_array($state) ? $state : Simula_Security_Telemetry_Settings::get_state();
        if (!empty($state['incident_cursor_initialized'])) {
            return $state;
        }

        $table      = Simula_Security_Telemetry_Wordfence_Schema::wordfence_hits_table();
        $last_id    = 0;
        $id_column  = null;

        if (Simula_Security_Telemetry_Wordfence_Schema::table_exists($table)) {
            $id_column = Simula_Security_Telemetry_Util::resolve_first_candidate(
                Simula_Security_Telemetry_Wordfence_Schema::table_columns($table),
                ['id']
            );
        }

        if ($id_column !== null) {
            $table_identifier = self::quote_identifier($table);
            $last_id = (int) Simula_Security_Telemetry_Util::db_get_var(
                'SELECT COALESCE(MAX(' . self::quote_identifier($id_column) . "), 0) FROM $table_identifier"
            );
        }

        $state['incident_cursor_initialized'] = 1;
        $state['last_incident_id']            = max(0, $last_id);
        if ($persist_state) {
            update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
        }

        return $state;
    }

    /** Resets the incident cursor so the next run can backfill from the start of the hits table. */
    public static function reset_cursor() {
        $state = Simula_Security_Telemetry_Settings::get_state();

        $state['incident_cursor_initialized'] = 1;
        $state['last_incident_id']            = 0;
        $state['last_incident_error']         = '';
        update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
    }

    /** Exports new blocked Wordfence incidents as text or JSON Lines log lines. */
    public static function export($options = null, $state = null, $persist_state = true) {
        global $wpdb;

        $options = is_array($options) ? $options : Simula_Security_Telemetry_Settings::get_options();
        $state   = is_array($state) ? $state : Simula_Security_Telemetry_Settings::get_state();
        if (empty($options['enabled'])) {
            return [
                'ok'      => false,
                'message' => __('Exporter is disabled. Enable the exporter to run both metrics and incident log exports.', 'simula-security-telemetry-for-wordfence'),
                'state'   => $state,
            ];
        }

        if (empty($options['incident_log_enabled'])) {
            return [
                'ok'      => true,
                'message' => __('Incident log export disabled.', 'simula-security-telemetry-for-wordfence'),
                'state'   => $state,
            ];
        }

        $table = Simula_Security_Telemetry_Wordfence_Schema::wordfence_hits_table();

        if (!Simula_Security_Telemetry_Wordfence_Schema::table_exists($table)) {
            $message = sprintf(
                /* translators: %s: Comma-separated list of Wordfence table names that were checked. */
                __('Wordfence table not found. Tried: %s', 'simula-security-telemetry-for-wordfence'),
                implode(', ', Simula_Security_Telemetry_Wordfence_Schema::wordfence_table_candidates('wfHits'))
            );

            return self::update_failure_state($state, $options, $message, $persist_state);
        }

        $state  = self::initialize_cursor_if_needed($state, $persist_state);
        $schema = self::resolve_schema($table);

        if ($schema['id'] === null) {
            return self::update_failure_state(
                $state,
                $options,
                __('Unsupported Wordfence hits schema: missing an incident ID column.', 'simula-security-telemetry-for-wordfence'),
                $persist_state
            );
        }

        if ($schema['time'] === null && empty($schema['time_columns'])) {
            return self::update_failure_state(
                $state,
                $options,
                __('Unsupported Wordfence hits schema: missing an incident timestamp column.', 'simula-security-telemetry-for-wordfence'),
                $persist_state
            );
        }

        $where_sql = Simula_Security_Telemetry_Wordfence_Collector::blocked_where_sql($table);
        if ($where_sql === '0=1') {
            return self::update_failure_state(
                $state,
                $options,
                __('Unsupported Wordfence hits schema: blocked incident filtering is unavailable.', 'simula-security-telemetry-for-wordfence'),
                $persist_state
            );
        }

        $last_id       = isset($state['last_incident_id']) ? (int) $state['last_incident_id'] : 0;
        $max_rows      = isset($options['incident_max_rows']) ? (int) $options['incident_max_rows'] : Simula_Security_Telemetry_Config::defaults()['incident_max_rows'];
        $limit         = min(max($max_rows, 1), 10000);
        $id_identifier = self::quote_identifier($schema['id']);
        $table_identifier = self::quote_identifier($table);
        $rows          = Simula_Security_Telemetry_Util::db_get_results(
            "SELECT * FROM $table_identifier
                WHERE $id_identifier > " . (int) $last_id . " AND $where_sql
                ORDER BY $id_identifier ASC
                LIMIT " . (int) $limit,
            ARRAY_A
        );

        if ($wpdb->last_error !== '') {
            return self::update_failure_state($state, $options, $wpdb->last_error, $persist_state);
        }

        if (empty($rows)) {
            $state['last_incident_export']        = time();
            $state['last_incident_exported_rows'] = 0;
            $state['last_incident_log_file']      = $options['incident_log_file'];
            $state['last_incident_error']         = '';
            if ($persist_state) {
                update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
            }

            return [
                'ok'      => true,
                'message' => __('No new Wordfence incidents to append.', 'simula-security-telemetry-for-wordfence'),
                'state'   => $state,
            ];
        }

        $lines       = [];
        $max_seen_id = $last_id;

        foreach ((array) $rows as $row) {
            $row_id = isset($row[$schema['id']]) ? (int) $row[$schema['id']] : 0;
            if ($row_id > $max_seen_id) {
                $max_seen_id = $row_id;
            }

            $line = self::row_to_log_line($row, $table, $options, $schema);
            if ($line === null) {
                continue;
            }

            if (!is_string($line) || $line === '') {
                return self::update_failure_state(
                    $state,
                    $options,
                    __('Failed formatting a Wordfence incident log line.', 'simula-security-telemetry-for-wordfence'),
                    $persist_state
                );
            }

            $lines[] = $line . "\n";
        }

        if (empty($lines)) {
            $state['last_incident_id']            = $max_seen_id;
            $state['last_incident_export']        = time();
            $state['last_incident_exported_rows'] = 0;
            $state['last_incident_log_file']      = $options['incident_log_file'];
            $state['last_incident_error']         = '';
            if ($persist_state) {
                update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
            }

            return [
                'ok'      => true,
                'message' => __('No Wordfence incidents were appended after privacy filters.', 'simula-security-telemetry-for-wordfence'),
                'state'   => $state,
            ];
        }

        $write = self::append_log($options['incident_log_file'], implode('', $lines));
        if (!$write['ok']) {
            return self::update_failure_state($state, $options, $write['message'], $persist_state);
        }

        $exported_count = count($lines);
        $state['last_incident_id']            = $max_seen_id;
        $state['last_incident_export']        = time();
        $state['last_incident_exported_rows'] = $exported_count;
        $state['last_incident_log_file']      = $options['incident_log_file'];
        $state['last_incident_error']         = '';
        if ($persist_state) {
            update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
        }

        return [
            'ok'      => true,
            'message' => sprintf(
                /* translators: 1: Number of incident rows appended, 2: Incident log file path. */
                __('Appended %1$d Wordfence incidents to %2$s.', 'simula-security-telemetry-for-wordfence'),
                $exported_count,
                $options['incident_log_file']
            ),
            'state'   => $state,
        ];
    }

    /** Returns a sample incident log line for operator-facing admin UI help text. */
    public static function sample_log_line($options = null) {
        $options = is_array($options) ? $options : Simula_Security_Telemetry_Settings::get_options();

        $event_ts = strtotime('2026-05-23T12:34:56+00:00');
        $context  = [
            'site'        => (string) ($options['site_label'] ?? wp_parse_url(home_url('/'), PHP_URL_HOST)),
            'hostname'    => self::clean_string(function_exists('gethostname') ? gethostname() : ''),
            'blog_id'     => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1,
            'hit_id'      => 123,
            'level'       => 'CRITICAL',
            'ip'          => '203.0.113.10',
            'status'      => 403,
            'action'      => 'blocked:waf',
            'reason'      => 'SQL injection attempt',
            'method'      => 'POST',
            'url'         => '/wp-admin/admin-ajax.php',
            'referer'     => empty($options['privacy_drop_referer']) ? 'https://example.com/' : null,
            'user_agent'  => empty($options['privacy_drop_user_agent']) ? 'curl/8.0' : null,
            'country'     => 'NO',
            'wf_table'    => 'wp_wfHits',
        ];
        $context['ip'] = self::apply_ip_privacy($context['ip'], $options);
        $context['url'] = self::apply_url_privacy($context['url'], $options);
        $context['referer'] = self::apply_url_privacy($context['referer'], $options);
        $retention_note = self::privacy_retention_note($options);
        if ($retention_note !== null) {
            $context['retention_note'] = $retention_note;
        }

        return self::incident_format($options) === 'jsonl'
            ? self::format_json_line($event_ts, $context)
            : self::format_log_line($event_ts, $context);
    }

    /** Resolves the Wordfence hits schema columns used by the incident exporter. */
    private static function resolve_schema($table) {
        $columns = Simula_Security_Telemetry_Wordfence_Schema::table_columns($table);

        return [
            'columns'      => $columns,
            'id'           => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['id']),
            'time'         => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['attackLogTime', 'ctime', 'time']),
            'time_columns' => Simula_Security_Telemetry_Util::resolve_available_candidates($columns, ['attackLogTime', 'ctime', 'time']),
            'status'       => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['statusCode', 'status']),
            'action'       => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['action']),
            'reason'       => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['actionDescription', 'description', 'msg', 'message', 'reason']),
            'method'       => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['method', 'httpMethod', 'requestMethod']),
            'url'          => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['URL', 'url', 'uri', 'requestUri', 'request_uri', 'path']),
            'referer'      => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['referer', 'Referer', 'referrer']),
            'user_agent'   => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['UA', 'user_agent', 'userAgent']),
            'country'      => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['ctry', 'countryCode', 'country']),
            'ip'           => Simula_Security_Telemetry_Util::resolve_first_candidate($columns, ['IP', 'ip', 'ipaddress', 'ipAddress']),
        ];
    }

    /** Maps a Wordfence hit row into a text or JSON Lines incident log line. */
    private static function row_to_log_line($row, $table, $options, $schema) {
        $event_ts   = self::row_event_timestamp($row, $schema);
        $status     = self::column_value($row, $schema['status']);
        $raw_ip     = self::normalize_ip(self::column_value($row, $schema['ip']));

        if (!empty($options['privacy_exclude_private_ips']) && self::is_private_or_reserved_ip($raw_ip)) {
            return null;
        }

        $url            = self::apply_url_privacy(self::clean_string(self::column_value($row, $schema['url'])), $options);
        $referer        = empty($options['privacy_drop_referer']) ? self::apply_url_privacy(self::clean_string(self::column_value($row, $schema['referer'])), $options) : null;
        $user_agent     = empty($options['privacy_drop_user_agent']) ? self::clean_string(self::column_value($row, $schema['user_agent'])) : null;
        $retention_note = self::privacy_retention_note($options);
        $action       = self::clean_string(self::column_value($row, $schema['action']));
        $reason       = self::clean_string(self::column_value($row, $schema['reason']));
        $context    = [
            'site'       => (string) ($options['site_label'] ?? wp_parse_url(home_url('/'), PHP_URL_HOST)),
            'hostname'   => self::clean_string(function_exists('gethostname') ? gethostname() : ''),
            'blog_id'    => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1,
            'hit_id'     => isset($schema['id'], $row[$schema['id']]) ? (int) $row[$schema['id']] : null,
            'level'      => self::incident_log_level($status, $action, $reason, $url),
            'ip'         => self::apply_ip_privacy($raw_ip, $options),
            'status'     => is_numeric($status) ? (int) $status : self::clean_string($status),
            'action'     => $action,
            'reason'     => $reason,
            'method'     => self::clean_string(self::column_value($row, $schema['method'])),
            'url'        => $url,
            'referer'    => $referer,
            'user_agent' => $user_agent,
            'country'    => self::clean_string(self::column_value($row, $schema['country'])),
            'wf_table'   => self::clean_string($table),
        ];

        if ($retention_note !== null) {
            $context['retention_note'] = $retention_note;
        }

        return self::incident_format($options) === 'jsonl'
            ? self::format_json_line($event_ts, $context)
            : self::format_log_line($event_ts, $context);
    }

    /** Formats one incident as a PHP-style log line with a UTC timestamp prefix. */
    private static function format_log_line($event_ts, $context) {
        $parts = [];
        $level = self::normalize_log_level($context['level'] ?? null);
        unset($context['level']);

        foreach ((array) $context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = self::format_log_field($key, $value);
        }

        $message = 'Wordfence blocked request';
        if (!empty($parts)) {
            $message .= ': ' . implode(' ', $parts);
        }

        return sprintf('[%s UTC] %s %s', gmdate('d-M-Y H:i:s', (int) $event_ts), $level, $message);
    }

    /** Formats one incident as a JSON Lines event. */
    private static function format_json_line($event_ts, $context) {
        $event = ['timestamp' => gmdate('c', (int) $event_ts)];

        foreach ((array) $context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $event[$key] = $value;
        }

        $json = wp_json_encode($event, JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }

    /** Returns the configured incident output format. */
    private static function incident_format($options) {
        $format = isset($options['incident_log_format']) ? (string) $options['incident_log_format'] : 'text';

        return $format === 'jsonl' ? 'jsonl' : 'text';
    }

    /** Returns the best available source timestamp for a Wordfence hit row. */
    private static function row_event_timestamp($row, $schema) {
        $columns = !empty($schema['time_columns']) && is_array($schema['time_columns'])
            ? $schema['time_columns']
            : [$schema['time'] ?? null];

        foreach ($columns as $column) {
            $timestamp = self::normalize_event_timestamp(self::column_value($row, $column));
            if ($timestamp > 0) {
                return $timestamp;
            }
        }

        return time();
    }

    /** Normalizes Wordfence timestamp column values into Unix seconds. */
    private static function normalize_event_timestamp($value) {
        if (!is_scalar($value)) {
            return 0;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            $timestamp = (float) $value;
            if ($timestamp <= 0) {
                return 0;
            }

            while ($timestamp > 9999999999) {
                $timestamp /= 1000;
            }

            return (int) $timestamp;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? 0 : max(0, (int) $timestamp);
    }

    /** Assigns a bounded log level to a Wordfence incident. */
    private static function incident_log_level($status, $action, $reason, $url) {
        $haystack = strtolower(implode(' ', array_filter([
            is_scalar($status) ? (string) $status : '',
            is_scalar($action) ? (string) $action : '',
            is_scalar($reason) ? (string) $reason : '',
            is_scalar($url) ? (string) $url : '',
        ], 'strlen')));

        if (preg_match('/\b(sql|xss|rce|xxe|lfi|rfi)\b|injection|cross[- ]site|remote code|command execution|path traversal|directory traversal|file upload|web shell|shell upload|backdoor|malware|exploit/i', $haystack)) {
            return 'CRITICAL';
        }

        if (preg_match('/loginfail|login failure|failed login|invalid username|incorrect password|throttle|throttled|rate[-_ ]?limit|xmlrpc|xml-rpc|brute force|brute-force|wp-login\.php/i', $haystack)) {
            return 'INFO';
        }

        return 'WARN';
    }

    /** Returns one of the supported incident log levels. */
    private static function normalize_log_level($level) {
        $level = strtoupper((string) $level);

        return in_array($level, ['INFO', 'WARN', 'CRITICAL'], true) ? $level : 'WARN';
    }

    /** Formats one context field in key=value form while quoting free-text values. */
    private static function format_log_field($key, $value) {
        if (is_int($value) || is_float($value)) {
            return sprintf('%s=%s', $key, $value);
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value);

        return sprintf('%s="%s"', $key, $escaped);
    }

    /** Returns a row value only when the resolved column is present. */
    private static function column_value($row, $column) {
        if ($column === null || !array_key_exists($column, $row)) {
            return null;
        }

        return $row[$column];
    }

    /** Appends content to the incident log using the WordPress filesystem API. */
    private static function append_log($file, $content) {
        $directory  = dirname($file);
        $filesystem = Simula_Security_Telemetry_Util::filesystem();

        if ($filesystem === null) {
            return [
                'ok'      => false,
                'message' => __('Could not initialize the WordPress filesystem API.', 'simula-security-telemetry-for-wordfence'),
            ];
        }

        if (!$filesystem->is_dir($directory)) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: Incident log directory path. */
                    __('Incident log directory does not exist: %s', 'simula-security-telemetry-for-wordfence'),
                    $directory
                ),
            ];
        }

        if (!$filesystem->is_writable($directory) && !($filesystem->exists($file) && $filesystem->is_writable($file))) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: Incident log file path. */
                    __('Incident log path is not writable by PHP: %s', 'simula-security-telemetry-for-wordfence'),
                    $file
                ),
            ];
        }

        $existing = $filesystem->exists($file) ? $filesystem->get_contents($file) : '';
        if ($existing === false) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: Incident log file path. */
                    __('Could not read the incident log for append: %s', 'simula-security-telemetry-for-wordfence'),
                    $file
                ),
            ];
        }

        if (!$filesystem->put_contents($file, $existing . $content, FS_CHMOD_FILE)) {
            return [
                'ok'      => false,
                'message' => sprintf(
                    /* translators: %s: Incident log file path. */
                    __('Failed appending the incident log: %s', 'simula-security-telemetry-for-wordfence'),
                    $file
                ),
            ];
        }

        return [
            'ok'      => true,
            'message' => __('Incident log appended.', 'simula-security-telemetry-for-wordfence'),
        ];
    }

    /** Updates incident-specific failure state and returns a normalized error result. */
    private static function update_failure_state($state, $options, $message, $persist_state = true) {
        $state                           = is_array($state) ? $state : [];
        $state['last_incident_export']   = time();
        $state['last_incident_exported_rows'] = 0;
        $state['last_incident_log_file'] = $options['incident_log_file'] ?? '';
        $state['last_incident_error']    = (string) $message;
        if ($persist_state) {
            update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
        }

        return [
            'ok'      => false,
            'message' => (string) $message,
            'state'   => $state,
        ];
    }

    /** Applies the configured IP privacy mode to an incident IP field. */
    private static function apply_ip_privacy($ip, $options) {
        $ip = self::clean_string($ip);
        if ($ip === null) {
            return null;
        }

        $mode = isset($options['privacy_ip_mode']) ? (string) $options['privacy_ip_mode'] : 'full';

        if ($mode === 'drop') {
            return null;
        }

        if ($mode === 'hash') {
            $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_SALT') ? AUTH_SALT : SSTFW_PLUGIN_FILE);

            return 'sha256:' . hash_hmac('sha256', $ip, $salt);
        }

        if ($mode === 'truncate') {
            return self::truncate_ip($ip);
        }

        return $ip;
    }

    /** Removes query strings from incident URLs when configured. */
    private static function apply_url_privacy($url, $options) {
        $url = self::clean_string($url);
        if ($url === null || empty($options['privacy_drop_url_query'])) {
            return $url;
        }

        $query_pos = strpos($url, '?');
        if ($query_pos === false) {
            return $url;
        }

        $url = substr($url, 0, $query_pos);

        return $url === '' ? null : $url;
    }

    /** Returns the configured retention note when present. */
    private static function privacy_retention_note($options) {
        $note = self::clean_string($options['privacy_retention_note'] ?? '');

        return $note === '' ? null : $note;
    }

    /** Checks whether an IP is private, loopback, link-local, or otherwise reserved. */
    private static function is_private_or_reserved_ip($ip) {
        $ip = self::clean_string($ip);
        if ($ip === null || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** Truncates IPv4 to /24 and IPv6 to /64 for privacy-preserving incident logs. */
    private static function truncate_ip($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return implode('.', array_slice($parts, 0, 3)) . '.0/24';
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if ($packed !== false && strlen($packed) === 16) {
                $hex = bin2hex(substr($packed, 0, 8));

                return sprintf(
                    '%s:%s:%s:%s::/64',
                    substr($hex, 0, 4),
                    substr($hex, 4, 4),
                    substr($hex, 8, 4),
                    substr($hex, 12, 4)
                );
            }
        }

        return $ip;
    }

    /** Normalizes a scalar value into a safe plain-text log field. */
    private static function clean_string($value) {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = self::normalize_binary_string($value);
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value);
        $value = trim(is_string($value) ? $value : '');

        return $value === '' ? null : $value;
    }

    /** Normalizes stored IP values from plain text, packed binary, or numeric IPv4 forms. */
    private static function normalize_ip($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $plain = self::normalize_binary_string($value);

            if (ctype_digit($plain)) {
                $numeric_ip = (float) $plain;
                if ($numeric_ip >= 0 && $numeric_ip <= 4294967295) {
                    return (string) long2ip((int) $numeric_ip);
                }
            }

            if (filter_var($plain, FILTER_VALIDATE_IP)) {
                return $plain;
            }

            $length = strlen($value);
            if ($length === 4 || $length === 16) {
                $decoded = @inet_ntop($value);
                if ($decoded !== false) {
                    return $decoded;
                }
            }

            return self::clean_string($plain);
        }

        if (is_scalar($value)) {
            return self::clean_string((string) $value);
        }

        return null;
    }

    /** Converts invalid UTF-8 strings into a stable hex representation. */
    private static function normalize_binary_string($value) {
        if (!is_string($value)) {
            return $value;
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            $hex = bin2hex($value);
            return $hex === '' ? '' : 'hex:' . $hex;
        }

        return $value;
    }

    /** Escapes a database identifier for use in dynamic incident queries. */
    private static function quote_identifier($identifier) {
        return Simula_Security_Telemetry_Util::quote_identifier($identifier);
    }
}

