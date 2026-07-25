<?php
/**
 * Plugin Name: Simula Security Telemetry for Wordfence
 * Plugin URI:  https://wordpress.org/plugins/simula-security-telemetry-for-wordfence
 * Description: Export metrics and incidents from WordPress and Wordfence into a node_exporter textfile collector .prom file, and .log file
 * Version:     3.0.0-alpha
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      Simula
 * Author URI:  https://simulalab.org
 * License:     GPL v2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simula-security-telemetry-for-wordfence
 * Domain Path: /languages
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}


define('SSTFW_PLUGIN_FILE', __FILE__);
define('SSTFW_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once SSTFW_PLUGIN_DIR . 'includes/class-config.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-util.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-settings.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-output.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-wordfence-schema.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-wordfence-collector.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-wordfence.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-incident-exporter.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-metrics-service.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-admin.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-cli.php';
require_once SSTFW_PLUGIN_DIR . 'includes/class-metrics.php';

Simula_Security_Telemetry_Metrics::init();
