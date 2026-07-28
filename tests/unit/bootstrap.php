<?php
/**
 * Unit-test bootstrap with minimal WordPress API stubs.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

define('ABSPATH', dirname(__DIR__, 2) . '/tests/wordpress/');
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('OBJECT', 'OBJECT');
define('ARRAY_A', 'ARRAY_A');
define('FS_CHMOD_FILE', 0644);

$GLOBALS['sstfw_settings_errors'] = [];
$GLOBALS['sstfw_options'] = [];
$GLOBALS['sstfw_scheduled'] = [];
$GLOBALS['sstfw_test_plugins'] = [];
$GLOBALS['sstfw_test_active_plugins'] = [];
$GLOBALS['sstfw_test_network_active_plugins'] = [];
$GLOBALS['sstfw_test_site_transients'] = [];
$GLOBALS['sstfw_test_users'] = [];
$GLOBALS['sstfw_test_user_meta'] = [];
$GLOBALS['sstfw_test_roles'] = [];
$GLOBALS['sstfw_test_options']['users_can_register'] = 0;
$GLOBALS['sstfw_test_options']['default_role'] = 'subscriber';

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default') {
        return (int) $number === 1 ? $single : $plural;
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql($value) {
        return addslashes((string) $value);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path($path) {
        return str_replace('\\', '/', (string) $path);
    }
}

if (!function_exists('add_settings_error')) {
    function add_settings_error($setting, $code, $message, $type = 'error') {
        $GLOBALS['sstfw_settings_errors'][] = compact('setting', 'code', 'message', 'type');
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.test' . $path;
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '') {
        return 'https://example.test' . $path;
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        return array_merge((array) $defaults, (array) $args);
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        if (array_key_exists($name, $GLOBALS['sstfw_test_options'])) {
            return $GLOBALS['sstfw_test_options'][$name];
        }

        return array_key_exists($name, $GLOBALS['sstfw_options']) ? $GLOBALS['sstfw_options'][$name] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null) {
        $GLOBALS['sstfw_options'][$name] = $value;
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook) {
        return $GLOBALS['sstfw_scheduled'][$hook] ?? 0;
    }
}

if (!function_exists('wp_get_schedules')) {
    function wp_get_schedules() {
        return [
            'custom_every_two_minutes' => ['interval' => 120],
        ];
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($value) {
        return strip_tags((string) $value);
    }
}

if (!function_exists('get_plugins')) {
    function get_plugins() {
        return $GLOBALS['sstfw_test_plugins'];
    }
}

if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin_file) {
        return in_array((string) $plugin_file, $GLOBALS['sstfw_test_active_plugins'], true);
    }
}

if (!function_exists('is_plugin_active_for_network')) {
    function is_plugin_active_for_network($plugin_file) {
        return in_array((string) $plugin_file, $GLOBALS['sstfw_test_network_active_plugins'], true);
    }
}

if (!function_exists('get_site_transient')) {
    function get_site_transient($transient) {
        return $GLOBALS['sstfw_test_site_transients'][$transient] ?? false;
    }
}

if (!function_exists('get_users')) {
    function get_users($args = []) {
        if (($args['fields'] ?? null) === 'ID') {
            return array_map(
                static function ($user) {
                    return is_object($user) && isset($user->ID) ? (int) $user->ID : (int) ($user['ID'] ?? 0);
                },
                $GLOBALS['sstfw_test_users']
            );
        }

        return $GLOBALS['sstfw_test_users'];
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        foreach ($GLOBALS['sstfw_test_users'] as $user) {
            if ((is_object($user) && (int) $user->ID === (int) $user_id) || (is_array($user) && (int) ($user['ID'] ?? 0) === (int) $user_id)) {
                return $user;
            }
        }

        return false;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        return $GLOBALS['sstfw_test_user_meta'][(int) $user_id][$key] ?? ($single ? '' : []);
    }
}

if (!function_exists('wp_roles')) {
    function wp_roles() {
        return (object) ['roles' => $GLOBALS['sstfw_test_roles']];
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook_name, $value) {
        return $value;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        return 'unit-test-site-salt-' . (string) $scheme;
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs((int) $value);
    }
}

if (!class_exists('Simula_Security_Telemetry_Config')) {
    require_once dirname(__DIR__, 2) . '/includes/class-config.php';
    require_once dirname(__DIR__, 2) . '/includes/class-util.php';
    require_once dirname(__DIR__, 2) . '/includes/class-metrics.php';
    require_once dirname(__DIR__, 2) . '/includes/class-settings.php';
    require_once dirname(__DIR__, 2) . '/includes/class-output.php';
    require_once dirname(__DIR__, 2) . '/includes/class-wordfence-schema.php';
    require_once dirname(__DIR__, 2) . '/includes/class-wordfence-collector.php';
    require_once dirname(__DIR__, 2) . '/includes/class-wordpress-collector.php';
}

function sstfw_assert_same($expected, $actual, $message = '') {
    if ($expected === $actual) {
        return;
    }

    throw new RuntimeException(($message !== '' ? $message . ' ' : '') . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

function sstfw_assert_true($condition, $message = 'Expected condition to be true.') {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function sstfw_invoke_private_static($class, $method, array $args = []) {
    $reflection = new ReflectionMethod($class, $method);

    return $reflection->invokeArgs(null, $args);
}
