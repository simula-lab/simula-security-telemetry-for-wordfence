<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Metrics {
    /** Hooks the plugin into WordPress actions, filters, and lifecycle events. */
    public static function init() {
        // Simula_Security_Telemetry_Settings::migrate_legacy_storage();

        add_action('admin_menu', ['Simula_Security_Telemetry_Admin', 'admin_menu']);
        add_action('admin_init', ['Simula_Security_Telemetry_Settings', 'register_settings']);
        add_action(Simula_Security_Telemetry_Config::CRON_HOOK, ['Simula_Security_Telemetry_Service', 'export_fast']);
        add_action(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK, ['Simula_Security_Telemetry_Service', 'export_slow']);
        add_filter('cron_schedules', [__CLASS__, 'cron_schedules']);
        add_filter('plugin_action_links_' . plugin_basename(SSTFW_PLUGIN_FILE), ['Simula_Security_Telemetry_Admin', 'plugin_action_links']);

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command(Simula_Security_Telemetry_Config::CLI_COMMAND, 'Simula_Security_Telemetry_CLI');
        }

        register_activation_hook(SSTFW_PLUGIN_FILE, [__CLASS__, 'activate']);
        register_deactivation_hook(SSTFW_PLUGIN_FILE, [__CLASS__, 'deactivate']);
        register_uninstall_hook(SSTFW_PLUGIN_FILE, [__CLASS__, 'uninstall']);
    }

    /** Returns the selectable schedule labels for cron interval settings. */
    public static function cron_interval_labels() {
        return [
            'sstfw_five_minutes'    => __('Every five minutes', 'simula-security-telemetry-for-wordfence'),
            'sstfw_fifteen_minutes' => __('Every fifteen minutes', 'simula-security-telemetry-for-wordfence'),
            'sstfw_thirty_minutes'  => __('Every thirty minutes', 'simula-security-telemetry-for-wordfence'),
            'hourly'               => __('Hourly', 'simula-security-telemetry-for-wordfence'),
        ];
    }

    /** Returns the selectable schedule labels for slow collector settings. */
    public static function slow_cron_interval_labels() {
        return [
            'hourly'     => __('Hourly', 'simula-security-telemetry-for-wordfence'),
            'twicedaily' => __('Twice daily', 'simula-security-telemetry-for-wordfence'),
            'daily'      => __('Daily', 'simula-security-telemetry-for-wordfence'),
        ];
    }

    /** Registers the custom cron schedules used by the exporter. */
    public static function cron_schedules($schedules) {
        $schedules['sstfw_five_minutes'] = [
            'interval' => 300,
            'display'  => __('Every five minutes', 'simula-security-telemetry-for-wordfence'),
        ];
        $schedules['sstfw_fifteen_minutes'] = [
            'interval' => 900,
            'display'  => __('Every fifteen minutes', 'simula-security-telemetry-for-wordfence'),
        ];
        $schedules['sstfw_thirty_minutes'] = [
            'interval' => 1800,
            'display'  => __('Every thirty minutes', 'simula-security-telemetry-for-wordfence'),
        ];

        return $schedules;
    }

    /** Initializes options, schedules exports, and writes the first metrics file on activation. */
    public static function activate() {
        if (!get_option(Simula_Security_Telemetry_Config::OPTION)) {
            add_option(Simula_Security_Telemetry_Config::OPTION, Simula_Security_Telemetry_Config::defaults(), '', false);
        }

        $options = Simula_Security_Telemetry_Settings::get_options();
        Simula_Security_Telemetry_Incidents::initialize_cursor_if_needed();
        Simula_Security_Telemetry_Settings::sync_schedule($options);

        if ($options['enabled']) {
            Simula_Security_Telemetry_Service::export();
            return;
        }

        Simula_Security_Telemetry_Output::write_disabled_metrics($options);
    }

    /** Unschedules the exporter cron job when the plugin is deactivated. */
    public static function deactivate() {
        wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::CRON_HOOK);
        wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK);
    }

    /** Removes plugin data and deletes only the generated metrics file on uninstall. */
    public static function uninstall() {
        $options = Simula_Security_Telemetry_Settings::get_options();

        wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::CRON_HOOK);
        wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK);
        delete_option(Simula_Security_Telemetry_Config::OPTION);
        delete_option(Simula_Security_Telemetry_Config::STATE);

        if (!empty($options['prom_file']) && is_string($options['prom_file']) && preg_match('/\.prom$/', $options['prom_file'])) {
            @wp_delete_file($options['prom_file']);
        }
    }
}
