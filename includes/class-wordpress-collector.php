<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_WordPress_Collector {
    private const WINDOWS = ['1h', '24h', '7d'];
    private const ROLE_LABELS = ['administrator', 'editor', 'author', 'contributor', 'subscriber', 'other'];
    private const ADMIN_CAPABILITIES = ['manage_options', 'activate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins', 'edit_users', 'promote_users', 'delete_users', 'switch_themes', 'edit_theme_options'];
    private const SENSITIVE_OPTIONS = [
        'siteurl' => 'site_url',
        'home' => 'site_url',
        'default_role' => 'users',
        'users_can_register' => 'users',
        'admin_email' => 'mail',
        'mailserver_url' => 'mail',
        'mailserver_login' => 'mail',
        'cron' => 'cron',
        'active_plugins' => 'plugins',
        'template' => 'plugins',
        'stylesheet' => 'plugins',
    ];

    /** Collects low-cardinality user, role, settings, plugin, and theme posture plus drift windows. */
    public static function collect_sprint6_metrics($state, $now, $windows) {
        $state = is_array($state) ? $state : [];
        $now   = (int) $now;
        $events = self::normalize_event_store($state['wp_drift_events'] ?? [], [
            'plugins_added',
            'plugins_removed',
            'plugins_activated',
            'plugins_deactivated',
        ], $now);
        $current_snapshot = self::plugin_theme_snapshot();
        $previous_snapshot = is_array($state['wp_asset_snapshot'] ?? null) ? $state['wp_asset_snapshot'] : [];

        if ($previous_snapshot !== []) {
            $events = self::append_asset_drift_events($events, $previous_snapshot, $current_snapshot, $now);
        }

        return [
            'metrics' => [
                'user_roles' => self::collect_user_role_metrics($now, $windows),
                'settings' => self::collect_settings_metrics(),
                'asset_posture' => self::collect_asset_posture_metrics($current_snapshot),
                'asset_drift_windows' => self::event_windows($events, $windows),
            ],
            'state' => [
                'wp_asset_snapshot' => $current_snapshot,
                'wp_drift_events' => $events,
            ],
        ];
    }

    /** Collects account event windows and current application-password/session aggregates. */
    public static function collect_account_metrics($state, $now, $windows) {
        $state = is_array($state) ? $state : [];
        $events = self::normalize_event_store($state['account_events'] ?? [], [
            'successful_login',
            'password_reset',
            'email_change',
            'admin_modified',
        ], (int) $now);

        return [
            'metrics' => [
                'event_windows' => self::role_event_windows($events, $windows),
                'application_passwords_total' => self::application_password_count(false),
                'admin_application_passwords_total' => self::application_password_count(true),
                'sessions_by_role' => self::session_counts_by_role(),
            ],
            'state' => [
                'account_events' => $events,
            ],
        ];
    }

    /** Collects cron and option-table persistence signals. */
    public static function collect_cron_option_metrics($state, $now, $windows) {
        $state = is_array($state) ? $state : [];
        $now = (int) $now;
        $events = self::normalize_event_store($state['persistence_events'] ?? [], [
            'cron_new_hooks',
            'options_changed',
            'new_autoload_options',
        ], $now);
        $sensitive_events = self::normalize_grouped_event_store($state['sensitive_option_events'] ?? [], self::option_groups(), $now);

        $cron_snapshot = self::cron_snapshot();
        $option_snapshot = self::option_snapshot();
        $previous_cron_snapshot = is_array($state['cron_snapshot'] ?? null) ? $state['cron_snapshot'] : [];
        $previous_option_snapshot = is_array($state['option_snapshot'] ?? null) ? $state['option_snapshot'] : [];

        if ($previous_cron_snapshot !== []) {
            foreach (array_diff(array_keys($cron_snapshot['hooks']), array_keys($previous_cron_snapshot['hooks'] ?? [])) as $hook) {
                $events['cron_new_hooks'][] = $now;
            }
        }

        if ($previous_option_snapshot !== []) {
            $previous_hashes = $previous_option_snapshot['sensitive_hashes'] ?? [];
            foreach (($option_snapshot['sensitive_hashes'] ?? []) as $name => $record) {
                if (!isset($previous_hashes[$name]) || ($previous_hashes[$name]['hash'] ?? '') !== ($record['hash'] ?? '')) {
                    $events['options_changed'][] = $now;
                    $group = isset($record['group']) ? (string) $record['group'] : 'other';
                    $sensitive_events[$group][] = $now;
                }
            }

            foreach (array_diff(array_keys($option_snapshot['autoload_hashes'] ?? []), array_keys($previous_option_snapshot['autoload_hashes'] ?? [])) as $name) {
                $events['new_autoload_options'][] = $now;
            }
        }

        $events = self::normalize_event_store($events, array_keys($events), $now);
        $sensitive_events = self::normalize_grouped_event_store($sensitive_events, self::option_groups(), $now);

        return [
            'metrics' => [
                'cron' => $cron_snapshot,
                'options' => $option_snapshot,
                'event_windows' => self::event_windows($events, $windows),
                'sensitive_option_windows' => self::grouped_event_windows($sensitive_events, $windows),
            ],
            'state' => [
                'cron_snapshot' => ['hooks' => $cron_snapshot['hooks']],
                'option_snapshot' => [
                    'sensitive_hashes' => $option_snapshot['sensitive_hashes'],
                    'autoload_hashes' => $option_snapshot['autoload_hashes'],
                ],
                'persistence_events' => $events,
                'sensitive_option_events' => $sensitive_events,
            ],
        ];
    }

    /** Collects bounded content and filesystem IoC indicators. */
    public static function collect_ioc_metrics($now, $windows) {
        return [
            'content' => self::content_ioc_metrics($windows),
            'files' => self::file_ioc_metrics((int) $now, $windows),
        ];
    }

    /** Records a successful login event from WordPress hooks. */
    public static function record_successful_login($user_login = '', $user = null) {
        self::record_account_event('successful_login', self::role_for_user($user));
    }

    /** Records a password reset event from WordPress hooks. */
    public static function record_password_reset($user = null) {
        self::record_account_event('password_reset', self::role_for_user($user));
    }

    /** Records user email changes and administrator profile modifications. */
    public static function record_profile_update($user_id = 0, $old_user_data = null, $userdata = null) {
        $user = function_exists('get_userdata') ? get_userdata((int) $user_id) : null;
        $role = self::role_for_user($user);

        $old_email = is_object($old_user_data) && isset($old_user_data->user_email) ? (string) $old_user_data->user_email : '';
        $new_email = is_array($userdata) && isset($userdata['user_email']) ? (string) $userdata['user_email'] : '';
        if ($new_email !== '' && $old_email !== '' && $new_email !== $old_email) {
            self::record_account_event('email_change', $role);
        }

        if ($role === 'administrator') {
            self::record_account_event('admin_modified', 'administrator');
        }
    }

    /** Records application password creation/deletion as a bounded account event. */
    public static function record_application_password_change($user = null) {
        self::record_account_event('application_password_change', self::role_for_user($user));
    }

    /** Collects user and role counts. */
    private static function collect_user_role_metrics($now, $windows) {
        $roles = array_fill_keys(self::ROLE_LABELS, 0);
        $created = [];
        $admin_created = [];
        foreach (self::ROLE_LABELS as $role) {
            $created[$role] = array_fill_keys(self::WINDOWS, 0);
        }
        foreach (self::WINDOWS as $window) {
            $admin_created[$window] = 0;
        }

        foreach (self::users_with_basic_fields() as $user) {
            $role = self::role_for_user($user);
            $roles[$role]++;
            $registered = self::user_registered_timestamp($user);
            if ($registered > 0) {
                foreach (self::WINDOWS as $window) {
                    if ($registered >= (int) ($windows[$window] ?? 0)) {
                        $created[$role][$window]++;
                        if ($role === 'administrator') {
                            $admin_created[$window]++;
                        }
                    }
                }
            }
        }

        $role_data = self::wp_roles_data();
        $capabilities = array_fill_keys(self::ROLE_LABELS, 0);
        $unexpected_admin_caps = 0;
        foreach ($role_data as $role_name => $role) {
            $label = self::bounded_role((string) $role_name);
            $caps = is_array($role['capabilities'] ?? null) ? array_filter($role['capabilities']) : [];
            $capabilities[$label] += count($caps);
            if ($label !== 'administrator') {
                $unexpected_admin_caps += count(array_intersect(array_keys($caps), self::ADMIN_CAPABILITIES));
            }
        }

        return [
            'users_total' => $roles,
            'users_created' => $created,
            'admin_users_created' => $admin_created,
            'roles_total' => count($role_data),
            'role_capabilities_total' => $capabilities,
            'unexpected_admin_capabilities_total' => $unexpected_admin_caps,
        ];
    }

    /** Collects dangerous settings as boolean/metadata gauges. */
    private static function collect_settings_metrics() {
        return [
            'users_can_register_enabled' => self::option_bool('users_can_register'),
            'default_role' => self::bounded_role((string) self::wp_option('default_role', 'subscriber')),
            'file_edit_allowed' => (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) ? 0 : 1,
            'file_mods_allowed' => (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) ? 0 : 1,
            'debug_enabled' => (defined('WP_DEBUG') && WP_DEBUG) ? 1 : 0,
            'debug_display_enabled' => (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) ? 1 : 0,
            'xmlrpc_enabled' => self::filter_enabled('xmlrpc_enabled', true),
            'rest_api_enabled' => self::filter_enabled('rest_enabled', true),
            'search_engine_visibility_enabled' => self::option_bool('blog_public') ? 0 : 1,
            'home_url_hash' => self::stable_hash((string) self::wp_option('home', function_exists('home_url') ? home_url('/') : ''), 'home_url'),
            'site_url_hash' => self::stable_hash((string) self::wp_option('siteurl', function_exists('site_url') ? site_url('/') : ''), 'site_url'),
        ];
    }

    /** Builds a current plugin/theme snapshot for drift and posture. */
    private static function plugin_theme_snapshot() {
        self::ensure_plugin_functions_loaded();

        $plugins = [];
        foreach ((array) (function_exists('get_plugins') ? get_plugins() : []) as $plugin_file => $plugin_data) {
            $plugins[(string) $plugin_file] = self::plugin_state((string) $plugin_file);
        }

        return [
            'plugins' => $plugins,
            'active_theme' => self::active_theme_record(),
        ];
    }

    /** Collects plugin/theme posture that is not already exported by the Wordfence collector. */
    private static function collect_asset_posture_metrics($snapshot) {
        self::ensure_plugin_functions_loaded();

        $themes = function_exists('wp_get_themes') ? wp_get_themes() : [];
        $theme_updates = function_exists('get_site_transient') ? get_site_transient('update_themes') : null;

        return [
            'mu_plugins_total' => function_exists('get_mu_plugins') ? count((array) get_mu_plugins()) : 0,
            'dropins_total' => function_exists('get_dropins') ? count((array) get_dropins()) : 0,
            'active_theme' => is_array($snapshot['active_theme'] ?? null) ? $snapshot['active_theme'] : ['theme' => 'unknown', 'version' => 'unknown'],
            'themes_installed_total' => count((array) $themes),
            'themes_update_available_total' => is_object($theme_updates) && isset($theme_updates->response) && is_array($theme_updates->response) ? count($theme_updates->response) : 0,
        ];
    }

    /** Appends plugin add/remove/activate/deactivate drift events. */
    private static function append_asset_drift_events($events, $previous, $current, $now) {
        $old_plugins = is_array($previous['plugins'] ?? null) ? $previous['plugins'] : [];
        $new_plugins = is_array($current['plugins'] ?? null) ? $current['plugins'] : [];

        foreach (array_diff(array_keys($new_plugins), array_keys($old_plugins)) as $plugin_file) {
            $events['plugins_added'][] = $now;
        }
        foreach (array_diff(array_keys($old_plugins), array_keys($new_plugins)) as $plugin_file) {
            $events['plugins_removed'][] = $now;
        }
        foreach ($new_plugins as $plugin_file => $state) {
            $old_state = $old_plugins[$plugin_file] ?? null;
            if ($old_state !== null && $old_state !== $state) {
                if ($state === 'active' || $state === 'network_active') {
                    $events['plugins_activated'][] = $now;
                } elseif ($old_state === 'active' || $old_state === 'network_active') {
                    $events['plugins_deactivated'][] = $now;
                }
            }
        }

        return $events;
    }

    /** Returns a minimal active-theme metadata record. */
    private static function active_theme_record() {
        if (!function_exists('wp_get_theme')) {
            return ['theme' => 'unknown', 'version' => 'unknown'];
        }

        $theme = wp_get_theme();
        if (!is_object($theme)) {
            return ['theme' => 'unknown', 'version' => 'unknown'];
        }

        $name = method_exists($theme, 'get') ? (string) $theme->get('Name') : '';
        $version = method_exists($theme, 'get') ? (string) $theme->get('Version') : '';

        return [
            'theme' => self::bounded_label($name !== '' ? $name : 'unknown', 120),
            'version' => self::bounded_label($version !== '' ? $version : 'unknown', 64),
        ];
    }

    /** Records an account event into bounded plugin state. */
    private static function record_account_event($type, $role) {
        $state = Simula_Security_Telemetry_Settings::get_state();
        $events = is_array($state['account_events'] ?? null) ? $state['account_events'] : [];
        $type = sanitize_key((string) $type);
        if (!isset($events[$type]) || !is_array($events[$type])) {
            $events[$type] = [];
        }

        $events[$type][] = [
            'ts' => time(),
            'role' => self::bounded_role($role),
        ];
        $state['account_events'] = self::normalize_event_store($events, array_keys($events), time());
        update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
    }

    /** Converts account role events into windowed counts. */
    private static function role_event_windows($events, $windows) {
        $result = [];
        foreach (['successful_login', 'password_reset', 'email_change', 'admin_modified'] as $type) {
            $result[$type] = [];
            foreach (self::ROLE_LABELS as $role) {
                $result[$type][$role] = array_fill_keys(self::WINDOWS, 0);
            }
        }

        foreach ($result as $type => $role_windows) {
            foreach ((array) ($events[$type] ?? []) as $event) {
                $ts = is_array($event) ? (int) ($event['ts'] ?? 0) : (int) $event;
                $role = is_array($event) ? self::bounded_role((string) ($event['role'] ?? 'other')) : 'other';
                foreach (self::WINDOWS as $window) {
                    if ($ts >= (int) ($windows[$window] ?? 0)) {
                        $result[$type][$role][$window]++;
                    }
                }
            }
        }

        return $result;
    }

    /** Counts application passwords using usermeta records. */
    private static function application_password_count($admins_only) {
        $users = self::users_with_basic_fields();
        $total = 0;
        foreach ($users as $user) {
            if ($admins_only && self::role_for_user($user) !== 'administrator') {
                continue;
            }

            $user_id = self::user_id($user);
            if ($user_id <= 0 || !function_exists('get_user_meta')) {
                continue;
            }

            $passwords = get_user_meta($user_id, '_application_passwords', true);
            $total += is_array($passwords) ? count($passwords) : 0;
        }

        return $total;
    }

    /** Counts stored session tokens by bounded role. */
    private static function session_counts_by_role() {
        $counts = array_fill_keys(self::ROLE_LABELS, 0);
        foreach (self::users_with_basic_fields() as $user) {
            $user_id = self::user_id($user);
            if ($user_id <= 0 || !function_exists('get_user_meta')) {
                continue;
            }

            $sessions = get_user_meta($user_id, 'session_tokens', true);
            $counts[self::role_for_user($user)] += is_array($sessions) ? count($sessions) : 0;
        }

        return $counts;
    }

    /** Returns current cron aggregate state plus hook/recurrence details for snapshots. */
    private static function cron_snapshot() {
        $cron = function_exists('_get_cron_array') ? _get_cron_array() : [];
        $hooks = [];
        $recurrences = ['single' => 0, 'hourly' => 0, 'twicedaily' => 0, 'daily' => 0, 'custom' => 0];
        $events_total = 0;
        $suspicious = 0;

        foreach ((array) $cron as $timestamp => $hook_entries) {
            foreach ((array) $hook_entries as $hook => $events) {
                $hooks[(string) $hook] = true;
                foreach ((array) $events as $event) {
                    $events_total++;
                    $schedule = is_array($event) && isset($event['schedule']) ? (string) $event['schedule'] : '';
                    $recurrence = self::bounded_recurrence($schedule);
                    $recurrences[$recurrence]++;
                }
            }
        }

        foreach (array_keys($hooks) as $hook) {
            if (preg_match('/(?:base64|eval|shell|exec|assert|passthru|system|tmp|backdoor|malware|inject)/i', $hook)) {
                $suspicious++;
            }
        }

        return [
            'cron_events_total' => $events_total,
            'cron_hooks_total' => count($hooks),
            'recurrences' => $recurrences,
            'cron_suspicious_hooks_total' => $suspicious,
            'hooks' => $hooks,
        ];
    }

    /** Returns option-table aggregate state and bounded hashes for drift snapshots. */
    private static function option_snapshot() {
        global $wpdb;

        $empty = [
            'options_total' => 0,
            'autoload_options_total' => 0,
            'autoload_options_bytes' => 0,
            'sensitive_hashes' => [],
            'autoload_hashes' => [],
        ];

        if (!is_object($wpdb) || empty($wpdb->options)) {
            return $empty;
        }

        $table = Simula_Security_Telemetry_Util::quote_identifier($wpdb->options);
        $rows = Simula_Security_Telemetry_Util::db_get_row(
            "SELECT COUNT(*) AS options_total,
                SUM(CASE WHEN autoload IN ('yes', 'on', 'auto-on', 'auto') THEN 1 ELSE 0 END) AS autoload_options_total,
                SUM(CASE WHEN autoload IN ('yes', 'on', 'auto-on', 'auto') THEN LENGTH(option_value) ELSE 0 END) AS autoload_options_bytes
            FROM $table",
            ARRAY_A
        );

        $snapshot = [
            'options_total' => (int) ($rows['options_total'] ?? 0),
            'autoload_options_total' => (int) ($rows['autoload_options_total'] ?? 0),
            'autoload_options_bytes' => (int) ($rows['autoload_options_bytes'] ?? 0),
            'sensitive_hashes' => self::sensitive_option_hashes($table),
            'autoload_hashes' => self::autoload_option_hashes($table),
        ];

        return $snapshot;
    }

    /** Returns bounded hashes for sensitive option names. */
    private static function sensitive_option_hashes($table) {
        $quoted_names = [];
        foreach (array_keys(self::SENSITIVE_OPTIONS) as $name) {
            $quoted_names[] = "'" . esc_sql($name) . "'";
        }

        $rows = Simula_Security_Telemetry_Util::db_get_results(
            "SELECT option_name, option_value FROM $table WHERE option_name IN (" . implode(',', $quoted_names) . ")",
            ARRAY_A
        );
        $hashes = [];
        foreach ((array) $rows as $row) {
            $name = (string) ($row['option_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $hashes[$name] = [
                'group' => self::SENSITIVE_OPTIONS[$name] ?? 'other',
                'hash' => self::stable_hash((string) ($row['option_value'] ?? ''), 'option:' . $name),
            ];
        }

        return $hashes;
    }

    /** Returns bounded hashes for autoload option names and values. */
    private static function autoload_option_hashes($table) {
        $rows = Simula_Security_Telemetry_Util::db_get_results(
            "SELECT option_name, option_value FROM $table WHERE autoload IN ('yes', 'on', 'auto-on', 'auto') ORDER BY option_name LIMIT 500",
            ARRAY_A
        );
        $hashes = [];
        foreach ((array) $rows as $row) {
            $name = (string) ($row['option_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $hashes[$name] = self::stable_hash((string) ($row['option_value'] ?? ''), 'autoload:' . $name);
        }

        return $hashes;
    }

    /** Collects bounded content indicators from the posts table. */
    private static function content_ioc_metrics($windows) {
        global $wpdb;

        $defaults = [
            'posts_modified' => self::post_type_window_defaults(),
            'pages_modified' => array_fill_keys(self::WINDOWS, 0),
            'script_tags' => array_fill_keys(['post', 'page', 'other'], 0),
            'iframe_tags' => array_fill_keys(['post', 'page', 'other'], 0),
            'suspicious_redirects' => array_fill_keys(['post', 'page', 'other'], 0),
            'recent_admin_post_edits' => array_fill_keys(self::WINDOWS, 0),
        ];

        if (!is_object($wpdb) || empty($wpdb->posts)) {
            return $defaults;
        }

        $posts_table = Simula_Security_Telemetry_Util::quote_identifier($wpdb->posts);
        foreach (self::WINDOWS as $window) {
            $cutoff = gmdate('Y-m-d H:i:s', (int) ($windows[$window] ?? 0));
            $rows = Simula_Security_Telemetry_Util::db_get_results(
                "SELECT post_type, COUNT(*) AS count_total FROM $posts_table
                WHERE post_modified_gmt >= '" . esc_sql($cutoff) . "'
                    AND post_status NOT IN ('auto-draft', 'trash')
                GROUP BY post_type",
                ARRAY_A
            );
            foreach ((array) $rows as $row) {
                $defaults['posts_modified'][self::bounded_post_type((string) ($row['post_type'] ?? 'other'))][$window] += (int) ($row['count_total'] ?? 0);
            }

            $defaults['pages_modified'][$window] = (int) Simula_Security_Telemetry_Util::db_get_var(
                "SELECT COUNT(*) FROM $posts_table WHERE post_type = 'page' AND post_modified_gmt >= '" . esc_sql($cutoff) . "' AND post_status NOT IN ('auto-draft', 'trash')"
            );
            $defaults['recent_admin_post_edits'][$window] = (int) Simula_Security_Telemetry_Util::db_get_var(
                "SELECT COUNT(*) FROM $posts_table WHERE post_modified_gmt >= '" . esc_sql($cutoff) . "' AND post_status NOT IN ('auto-draft', 'trash') AND post_author IN (" . self::administrator_id_sql_list() . ")"
            );
        }

        $patterns = [
            'script_tags' => '%<script%',
            'iframe_tags' => '%<iframe%',
            'suspicious_redirects' => '%window.location%',
        ];
        foreach ($patterns as $key => $pattern) {
            $rows = Simula_Security_Telemetry_Util::db_get_results(
                "SELECT post_type, COUNT(*) AS count_total FROM $posts_table
                WHERE post_status NOT IN ('auto-draft', 'trash') AND post_content LIKE '" . esc_sql($pattern) . "'
                GROUP BY post_type",
                ARRAY_A
            );
            foreach ((array) $rows as $row) {
                $defaults[$key][self::bounded_post_type((string) ($row['post_type'] ?? 'other'))] += (int) ($row['count_total'] ?? 0);
            }
        }

        return $defaults;
    }

    /** Collects bounded filesystem indicators under WordPress-owned directories. */
    private static function file_ioc_metrics($now, $windows) {
        $metrics = [
            'upload_php_files_total' => 0,
            'upload_executable_files_total' => 0,
            'recent_upload_php_files' => array_fill_keys(self::WINDOWS, 0),
            'plugin_files_modified' => array_fill_keys(self::WINDOWS, 0),
            'theme_files_modified' => array_fill_keys(self::WINDOWS, 0),
            'wp_content_recently_modified_files' => array_fill_keys(['plugins', 'themes', 'uploads', 'mu_plugins'], 0),
        ];

        $upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir(null, false) : [];
        $paths = [
            'uploads' => is_array($upload_dir) ? (string) ($upload_dir['basedir'] ?? '') : '',
            'plugins' => defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/plugins' : ''),
            'themes' => function_exists('get_theme_root') ? get_theme_root() : (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/themes' : ''),
            'mu_plugins' => defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/mu-plugins' : ''),
        ];

        foreach ($paths as $area => $path) {
            foreach (self::scan_files($path, 5000) as $file) {
                $mtime = @filemtime($file);
                $mtime = $mtime === false ? 0 : (int) $mtime;
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $is_php = in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5', 'phar'], true);
                $is_executable = $is_php || in_array($extension, ['cgi', 'pl', 'py', 'sh', 'bash', 'exe'], true);

                if ($area === 'uploads') {
                    if ($is_php) {
                        $metrics['upload_php_files_total']++;
                        foreach (self::WINDOWS as $window) {
                            if ($mtime >= (int) ($windows[$window] ?? 0)) {
                                $metrics['recent_upload_php_files'][$window]++;
                            }
                        }
                    }
                    if ($is_executable) {
                        $metrics['upload_executable_files_total']++;
                    }
                }

                foreach (self::WINDOWS as $window) {
                    if ($mtime >= (int) ($windows[$window] ?? 0)) {
                        if ($area === 'plugins') {
                            $metrics['plugin_files_modified'][$window]++;
                        } elseif ($area === 'themes') {
                            $metrics['theme_files_modified'][$window]++;
                        }
                        if ($window === '24h') {
                            $metrics['wp_content_recently_modified_files'][$area]++;
                        }
                    }
                }
            }
        }

        return $metrics;
    }

    /** Returns files from a bounded recursive scan. */
    private static function scan_files($path, $limit) {
        $path = (string) $path;
        if ($path === '' || !is_dir($path) || !is_readable($path)) {
            return [];
        }

        $files = [];
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file_info) {
                if (count($files) >= $limit) {
                    break;
                }
                if ($file_info->isFile()) {
                    $files[] = $file_info->getPathname();
                }
            }
        } catch (Exception $e) {
            return $files;
        }

        return $files;
    }

    /** Normalizes a simple event store and prunes entries older than seven days. */
    private static function normalize_event_store($events, $types, $now) {
        $normalized = [];
        $cutoff = (int) $now - (7 * DAY_IN_SECONDS);
        foreach ((array) $types as $type) {
            $normalized[$type] = [];
            foreach ((array) ($events[$type] ?? []) as $event) {
                $ts = is_array($event) ? (int) ($event['ts'] ?? 0) : (int) $event;
                if ($ts < $cutoff || $ts <= 0) {
                    continue;
                }
                $normalized[$type][] = is_array($event) ? ['ts' => $ts, 'role' => self::bounded_role((string) ($event['role'] ?? 'other'))] : $ts;
            }
        }

        return $normalized;
    }

    /** Normalizes grouped event stores. */
    private static function normalize_grouped_event_store($events, $groups, $now) {
        $normalized = [];
        $cutoff = (int) $now - (7 * DAY_IN_SECONDS);
        foreach ((array) $groups as $group) {
            $normalized[$group] = [];
            foreach ((array) ($events[$group] ?? []) as $ts) {
                $ts = (int) $ts;
                if ($ts >= $cutoff && $ts > 0) {
                    $normalized[$group][] = $ts;
                }
            }
        }

        return $normalized;
    }

    /** Converts timestamp event arrays into windowed counts. */
    private static function event_windows($events, $windows) {
        $result = [];
        foreach ((array) $events as $type => $timestamps) {
            $result[$type] = array_fill_keys(self::WINDOWS, 0);
            foreach ((array) $timestamps as $ts) {
                $ts = is_array($ts) ? (int) ($ts['ts'] ?? 0) : (int) $ts;
                foreach (self::WINDOWS as $window) {
                    if ($ts >= (int) ($windows[$window] ?? 0)) {
                        $result[$type][$window]++;
                    }
                }
            }
        }

        return $result;
    }

    /** Converts grouped timestamp arrays into windowed counts. */
    private static function grouped_event_windows($events, $windows) {
        $result = [];
        foreach (self::option_groups() as $group) {
            $result[$group] = array_fill_keys(self::WINDOWS, 0);
            foreach ((array) ($events[$group] ?? []) as $ts) {
                foreach (self::WINDOWS as $window) {
                    if ((int) $ts >= (int) ($windows[$window] ?? 0)) {
                        $result[$group][$window]++;
                    }
                }
            }
        }

        return $result;
    }

    /** Returns role label for a user object/array. */
    private static function role_for_user($user) {
        $roles = [];
        if (is_object($user) && isset($user->roles) && is_array($user->roles)) {
            $roles = $user->roles;
        } elseif (is_array($user) && isset($user['roles']) && is_array($user['roles'])) {
            $roles = $user['roles'];
        }

        return self::bounded_role((string) ($roles[0] ?? 'other'));
    }

    /** Returns a bounded role label. */
    private static function bounded_role($role) {
        $role = sanitize_key((string) $role);

        return in_array($role, self::ROLE_LABELS, true) ? $role : 'other';
    }

    /** Returns a bounded post type label. */
    private static function bounded_post_type($post_type) {
        $post_type = sanitize_key((string) $post_type);

        return in_array($post_type, ['post', 'page', 'attachment'], true) ? $post_type : 'other';
    }

    /** Returns defaults for post type/window metric maps. */
    private static function post_type_window_defaults() {
        return [
            'post' => array_fill_keys(self::WINDOWS, 0),
            'page' => array_fill_keys(self::WINDOWS, 0),
            'attachment' => array_fill_keys(self::WINDOWS, 0),
            'other' => array_fill_keys(self::WINDOWS, 0),
        ];
    }

    /** Returns known option groups. */
    private static function option_groups() {
        return ['site_url', 'users', 'mail', 'auth', 'cron', 'plugins', 'other'];
    }

    /** Returns all users available to the current runtime. */
    private static function users_with_basic_fields() {
        if (!function_exists('get_users')) {
            return [];
        }

        $users = get_users(['fields' => 'all']);

        return is_array($users) ? $users : [];
    }

    /** Returns a numeric user ID from supported user shapes. */
    private static function user_id($user) {
        if (is_object($user) && isset($user->ID)) {
            return (int) $user->ID;
        }
        if (is_array($user) && isset($user['ID'])) {
            return (int) $user['ID'];
        }

        return 0;
    }

    /** Returns the user registration timestamp when available. */
    private static function user_registered_timestamp($user) {
        $value = '';
        if (is_object($user) && isset($user->user_registered)) {
            $value = (string) $user->user_registered;
        } elseif (is_array($user) && isset($user['user_registered'])) {
            $value = (string) $user['user_registered'];
        }

        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? 0 : (int) $timestamp;
    }

    /** Returns role metadata from WordPress. */
    private static function wp_roles_data() {
        if (!function_exists('wp_roles')) {
            return [];
        }

        $roles = wp_roles();

        return is_object($roles) && isset($roles->roles) && is_array($roles->roles) ? $roles->roles : [];
    }

    /** Reads an option value when WordPress is available. */
    private static function wp_option($name, $default = '') {
        return function_exists('get_option') ? get_option($name, $default) : $default;
    }

    /** Reads an option as a boolean-ish value. */
    private static function option_bool($name) {
        return empty(self::wp_option($name, 0)) ? 0 : 1;
    }

    /** Applies a WordPress boolean filter when available. */
    private static function filter_enabled($filter, $default) {
        if (function_exists('apply_filters')) {
            return empty(apply_filters($filter, $default)) ? 0 : 1;
        }

        return $default ? 1 : 0;
    }

    /** Loads plugin helper functions when available. */
    private static function ensure_plugin_functions_loaded() {
        if (function_exists('get_plugins') && function_exists('is_plugin_active')) {
            return;
        }

        $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
        if (is_readable($plugin_file)) {
            require_once $plugin_file;
        }
    }

    /** Returns active/network-active/inactive for a plugin file. */
    private static function plugin_state($plugin_file) {
        if (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($plugin_file)) {
            return 'network_active';
        }
        if (function_exists('is_plugin_active') && is_plugin_active($plugin_file)) {
            return 'active';
        }

        return 'inactive';
    }

    /** Normalizes recurrence labels. */
    private static function bounded_recurrence($schedule) {
        $schedule = (string) $schedule;
        if ($schedule === '') {
            return 'single';
        }

        return in_array($schedule, ['hourly', 'twicedaily', 'daily'], true) ? $schedule : 'custom';
    }

    /** Returns a comma-separated SQL list of administrator user IDs. */
    private static function administrator_id_sql_list() {
        $ids = [];
        foreach (self::users_with_basic_fields() as $user) {
            if (self::role_for_user($user) === 'administrator') {
                $ids[] = self::user_id($user);
            }
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));

        return $ids === [] ? '0' : implode(',', $ids);
    }

    /** Caps arbitrary metadata labels. */
    private static function bounded_label($value, $max_length) {
        $value = function_exists('wp_strip_all_tags') ? wp_strip_all_tags((string) $value) : strip_tags((string) $value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = trim(is_string($value) ? $value : '');
        if ($value === '') {
            return 'unknown';
        }

        return strlen($value) > $max_length ? substr($value, 0, $max_length) : $value;
    }

    /** Generates a stable site-local hash for sensitive values. */
    private static function stable_hash($value, $purpose) {
        $salt = function_exists('wp_salt') ? wp_salt('auth') : '';
        if (!is_string($salt) || $salt === '') {
            $salt = defined('AUTH_SALT') ? (string) AUTH_SALT : '';
        }
        if ($salt === '' && function_exists('home_url')) {
            $salt = (string) home_url('/');
        }
        if ($salt === '') {
            $salt = 'simula-security-telemetry-for-wordfence';
        }

        return hash_hmac('sha256', (string) $purpose . ':' . (string) $value, $salt);
    }
}
