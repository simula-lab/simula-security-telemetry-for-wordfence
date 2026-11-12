<?php
/** Assert that ZIP installation uses one canonical, active plugin directory. */

$expected = 'simula-security-telemetry-for-wordfence/simula-security-telemetry-for-wordfence.php';
$matches = [];
foreach (get_plugins() as $file => $plugin) {
    if (basename($file) === 'simula-security-telemetry-for-wordfence.php') {
        $matches[] = $file;
    }
}

if ($matches !== [$expected] || !is_plugin_active($expected)) {
    WP_CLI::error('Expected exactly one active canonical plugin installation: ' . wp_json_encode($matches));
}

WP_CLI::success('ZIP installed in the canonical plugin directory with no duplicates.');
