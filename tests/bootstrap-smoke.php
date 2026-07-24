<?php
/**
 * Lightweight bootstrap smoke check for split-file loading and hook wiring.
 *
 * This intentionally stubs only the WordPress APIs used during plugin load.
 * It does not replace functional tests in a real WordPress runtime.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

define('ABSPATH', __DIR__ . '/wordpress/');

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}

$GLOBALS['sstfw_smoke_actions']            = [];
$GLOBALS['sstfw_smoke_filters']            = [];
$GLOBALS['sstfw_smoke_activation_hooks']   = [];
$GLOBALS['sstfw_smoke_deactivation_hooks'] = [];
$GLOBALS['sstfw_smoke_uninstall_hooks']    = [];

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return rtrim(dirname($file), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback) {
        $GLOBALS['sstfw_smoke_actions'][$hook][] = $callback;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback) {
        $GLOBALS['sstfw_smoke_filters'][$hook][] = $callback;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        $GLOBALS['sstfw_smoke_activation_hooks'][$file] = $callback;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        $GLOBALS['sstfw_smoke_deactivation_hooks'][$file] = $callback;
    }
}

if (!function_exists('register_uninstall_hook')) {
    function register_uninstall_hook($file, $callback) {
        $GLOBALS['sstfw_smoke_uninstall_hooks'][$file] = $callback;
    }
}

if (!class_exists('WP_CLI')) {
    final class WP_CLI {
        public static $commands = [];

        public static function add_command($name, $callable) {
            self::$commands[$name] = $callable;
        }
    }
}

function sstfw_smoke_expect($condition, $message) {
    if ($condition) {
        return;
    }

    fwrite(STDERR, "Smoke check failed: $message\n");
    exit(1);
}

function sstfw_smoke_has_callback($callbacks, $callback) {
    return in_array($callback, $callbacks ?? [], true);
}

$plugin_file = dirname(__DIR__) . '/simula-security-telemetry-for-wordfence.php';

require $plugin_file;

sstfw_smoke_expect(defined('SSTFW_PLUGIN_FILE'), 'SSTFW_PLUGIN_FILE is not defined.');
sstfw_smoke_expect(SSTFW_PLUGIN_FILE === $plugin_file, 'SSTFW_PLUGIN_FILE does not point at the root plugin file.');

$required_classes = [
    'Simula_Security_Telemetry_Config',
    'Simula_Security_Telemetry_Util',
    'Simula_Security_Telemetry_Settings',
    'Simula_Security_Telemetry_Output',
    'Simula_Security_Telemetry_Wordfence_Schema',
    'Simula_Security_Telemetry_Wordfence_Collector',
    'Simula_Security_Telemetry_Wordfence',
    'Simula_Security_Telemetry_Incidents',
    'Simula_Security_Telemetry_Service',
    'Simula_Security_Telemetry_Admin',
    'Simula_Security_Telemetry_CLI',
    'Simula_Security_Telemetry_Metrics',
];

foreach ($required_classes as $class) {
    sstfw_smoke_expect(class_exists($class), "$class did not load.");
}

sstfw_smoke_expect(
    sstfw_smoke_has_callback($GLOBALS['sstfw_smoke_actions']['admin_menu'] ?? [], ['Simula_Security_Telemetry_Admin', 'admin_menu']),
    'admin_menu action is not wired to the admin controller.'
);
sstfw_smoke_expect(
    sstfw_smoke_has_callback($GLOBALS['sstfw_smoke_actions']['admin_init'] ?? [], ['Simula_Security_Telemetry_Settings', 'register_settings']),
    'admin_init action is not wired to settings registration.'
);
sstfw_smoke_expect(
    sstfw_smoke_has_callback($GLOBALS['sstfw_smoke_actions'][Simula_Security_Telemetry_Config::CRON_HOOK] ?? [], ['Simula_Security_Telemetry_Service', 'export_fast']),
    'fast cron hook is not wired to export_fast.'
);
sstfw_smoke_expect(
    sstfw_smoke_has_callback($GLOBALS['sstfw_smoke_actions'][Simula_Security_Telemetry_Config::SLOW_CRON_HOOK] ?? [], ['Simula_Security_Telemetry_Service', 'export_slow']),
    'slow cron hook is not wired to export_slow.'
);
sstfw_smoke_expect(
    sstfw_smoke_has_callback($GLOBALS['sstfw_smoke_filters']['cron_schedules'] ?? [], ['Simula_Security_Telemetry_Metrics', 'cron_schedules']),
    'cron_schedules filter is not wired.'
);

$action_links_hook = 'plugin_action_links_' . plugin_basename($plugin_file);
sstfw_smoke_expect(
    sstfw_smoke_has_callback($GLOBALS['sstfw_smoke_filters'][$action_links_hook] ?? [], ['Simula_Security_Telemetry_Admin', 'plugin_action_links']),
    'plugin action links filter is not wired to the root plugin basename.'
);

sstfw_smoke_expect(
    ($GLOBALS['sstfw_smoke_activation_hooks'][$plugin_file] ?? null) === ['Simula_Security_Telemetry_Metrics', 'activate'],
    'activation hook is not wired to the root plugin file.'
);
sstfw_smoke_expect(
    ($GLOBALS['sstfw_smoke_deactivation_hooks'][$plugin_file] ?? null) === ['Simula_Security_Telemetry_Metrics', 'deactivate'],
    'deactivation hook is not wired to the root plugin file.'
);
sstfw_smoke_expect(
    ($GLOBALS['sstfw_smoke_uninstall_hooks'][$plugin_file] ?? null) === ['Simula_Security_Telemetry_Metrics', 'uninstall'],
    'uninstall hook is not wired to the root plugin file.'
);

sstfw_smoke_expect(
    (WP_CLI::$commands[Simula_Security_Telemetry_Config::CLI_COMMAND] ?? null) === 'Simula_Security_Telemetry_CLI',
    'WP-CLI command is not registered.'
);

$schedules = Simula_Security_Telemetry_Metrics::cron_schedules([]);
foreach (['sstfw_five_minutes', 'sstfw_fifteen_minutes', 'sstfw_thirty_minutes'] as $schedule) {
    sstfw_smoke_expect(isset($schedules[$schedule]), "$schedule cron schedule is missing.");
}

foreach (['export', 'export_fast', 'export_slow', 'export_metrics_only', 'export_incidents_only'] as $method) {
    sstfw_smoke_expect(is_callable(['Simula_Security_Telemetry_Service', $method]), "Service::$method is not callable.");
}

echo "Bootstrap smoke checks passed.\n";
