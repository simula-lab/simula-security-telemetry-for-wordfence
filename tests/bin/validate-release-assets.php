#!/usr/bin/env php
<?php
/**
 * Validates release documentation and example assets.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

$root = dirname(__DIR__, 2);

function sstfw_asset_fail($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function sstfw_read_file($path) {
    $content = file_get_contents($path);
    if ($content === false) {
        sstfw_asset_fail('Could not read ' . $path);
    }

    return $content;
}

$config = sstfw_read_file($root . '/includes/class-config.php');
if (!preg_match('/public static function metric_definitions\(\).*?return \[(.*?)\];\s*\}\s*\/\*\* Returns the default enabled state/s', $config, $match)) {
    sstfw_asset_fail('Could not locate metric_definitions() in class-config.php');
}

preg_match_all("/^ {12}'([^']+)' => \[/m", $match[1], $metric_matches);
$metric_keys = $metric_matches[1] ?? [];
if ($metric_keys === []) {
    sstfw_asset_fail('No metric definitions were found.');
}

$readme_md  = sstfw_read_file($root . '/README.md');
$readme_txt = sstfw_read_file($root . '/readme.txt');
$plugin_php = sstfw_read_file($root . '/simula-security-telemetry-for-wordfence.php');

if (!preg_match('/^\s*\*\s*Version:\s*([0-9]+(?:\.[0-9]+)+)\s*$/m', $plugin_php, $plugin_version_match)) {
    sstfw_asset_fail('Main plugin file is missing a numeric Version header.');
}

if (!preg_match('/^Stable tag:\s*([0-9]+(?:\.[0-9]+)+)\s*$/mi', $readme_txt, $stable_tag_match)) {
    sstfw_asset_fail('readme.txt is missing a numeric Stable tag.');
}

if ($stable_tag_match[1] !== $plugin_version_match[1]) {
    sstfw_asset_fail('readme.txt Stable tag does not match main plugin Version header.');
}

foreach ($metric_keys as $metric_key) {
    $metric_name = 'wordpress_wordfence_' . $metric_key;
    if (strpos($readme_md, $metric_name) === false) {
        sstfw_asset_fail('README.md is missing metric family: ' . $metric_name);
    }
    if (strpos($readme_txt, $metric_name) === false) {
        sstfw_asset_fail('readme.txt is missing metric family: ' . $metric_name);
    }
}

if (strpos($readme_txt, '= 3.0.0 =') === false) {
    sstfw_asset_fail('readme.txt is missing the 3.0.0 changelog entry.');
}

$dashboard_path = $root . '/examples/grafana/grafana-dashboard-wordfence-security-overview.json';
$dashboard      = json_decode(sstfw_read_file($dashboard_path), true);
if (!is_array($dashboard) || json_last_error() !== JSON_ERROR_NONE) {
    sstfw_asset_fail('Grafana dashboard JSON is invalid: ' . json_last_error_msg());
}

$panels = $dashboard['panels'] ?? [];
if (!is_array($panels) || count($panels) < 1) {
    sstfw_asset_fail('Grafana dashboard has no panels.');
}

$panel_ids = [];
$dashboard_exprs = [];
foreach ($panels as $panel) {
    if (!is_array($panel) || empty($panel['title']) || empty($panel['type'])) {
        sstfw_asset_fail('Grafana dashboard contains a panel without title or type.');
    }

    $id = isset($panel['id']) ? (int) $panel['id'] : 0;
    if ($id <= 0 || isset($panel_ids[$id])) {
        sstfw_asset_fail('Grafana dashboard contains a missing or duplicate panel id.');
    }
    $panel_ids[$id] = true;

    foreach ((array) ($panel['targets'] ?? []) as $target) {
        if (isset($target['expr']) && is_string($target['expr']) && $target['expr'] !== '') {
            $dashboard_exprs[] = $target['expr'];
        }
    }
}

$dashboard_expr_text = implode("\n", $dashboard_exprs);
foreach ([
    'wordpress_wordfence_wordpress_version_info',
    'wordpress_wordfence_plugins_active_total',
    'wordpress_wordfence_plugin_inventory_info',
    'wordpress_wordfence_admin_user_info',
] as $required_metric) {
    if (strpos($dashboard_expr_text, $required_metric) === false) {
        sstfw_asset_fail('Grafana dashboard is missing metric: ' . $required_metric);
    }
}

$alerts = sstfw_read_file($root . '/examples/prometheus/wordfence-alerts.yml');
foreach ([
    'WordfenceExporterStale',
    'WordfenceExporterFailed',
    'WordPressCoreUpdateAvailable',
    'WordPressPluginUpdatesAvailable',
    'WordPressSecurityPluginInactive',
    'WordPressAdminsWithoutTwoFactor',
] as $alert_name) {
    if (!preg_match('/^\s+- alert:\s+' . preg_quote($alert_name, '/') . '\s*$/m', $alerts)) {
        sstfw_asset_fail('Prometheus alerts are missing alert: ' . $alert_name);
    }
}

foreach ([
    'wordpress_wordfence_core_update_available',
    'wordpress_wordfence_plugin_update_available_total',
    'wordpress_wordfence_plugin_inventory_info',
    'wordpress_wordfence_admin_users_without_2fa_total',
] as $required_metric) {
    if (strpos($alerts, $required_metric) === false) {
        sstfw_asset_fail('Prometheus alerts are missing metric: ' . $required_metric);
    }
}

echo "Release documentation and example asset validation passed.\n";
