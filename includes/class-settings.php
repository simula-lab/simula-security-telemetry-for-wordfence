<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Settings {
    /** Registers the plugin settings and sanitization callback. */
    public static function register_settings() {
        register_setting(
            'sstfw_metrics',
            Simula_Security_Telemetry_Config::OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_options'],
                'default'           => Simula_Security_Telemetry_Config::defaults(),
            ]
        );
    }

    /** Loads plugin options merged with defaults. */
    public static function get_options() {
        $options = get_option(Simula_Security_Telemetry_Config::OPTION, []);
        $options = wp_parse_args(is_array($options) ? $options : [], Simula_Security_Telemetry_Config::defaults());
        $options['admin_identity_mode'] = self::sanitize_admin_identity_mode($options['admin_identity_mode'] ?? Simula_Security_Telemetry_Config::defaults()['admin_identity_mode']);
        $options['enabled_metrics'] = self::normalize_enabled_metrics($options['enabled_metrics'] ?? []);

        return $options;
    }

    /** Loads the exporter runtime state from the database. */
    public static function get_state() {
        $state = get_option(Simula_Security_Telemetry_Config::STATE, []);

        return is_array($state) ? $state : [];
    }

    // /** Migrates plugin-owned storage from the previous wfne prefix to the sstfw prefix. */
    // public static function migrate_legacy_storage() {
    //     $legacy_options = get_option(Simula_Security_Telemetry_Config::LEGACY_OPTION, false);
    //     $current_options = get_option(Simula_Security_Telemetry_Config::OPTION, false);

    //     if (is_array($legacy_options)) {
    //         $legacy_options = self::normalize_legacy_options($legacy_options);

    //         if ($current_options === false) {
    //             add_option(Simula_Security_Telemetry_Config::OPTION, $legacy_options, '', false);
    //         } elseif (is_array($current_options)) {
    //             $defaults              = Simula_Security_Telemetry_Config::defaults();
    //             $current_with_defaults = wp_parse_args($current_options, $defaults);
    //             $merged_options        = $current_with_defaults == $defaults
    //                 ? wp_parse_args($legacy_options, $defaults)
    //                 : wp_parse_args($current_options, $legacy_options);
    //             update_option(Simula_Security_Telemetry_Config::OPTION, $merged_options, false);
    //         }

    //         delete_option(Simula_Security_Telemetry_Config::LEGACY_OPTION);
    //     }

    //     $legacy_state  = get_option(Simula_Security_Telemetry_Config::LEGACY_STATE, false);
    //     $current_state = get_option(Simula_Security_Telemetry_Config::STATE, false);

    //     if (is_array($legacy_state)) {
    //         if ($current_state === false) {
    //             add_option(Simula_Security_Telemetry_Config::STATE, $legacy_state, '', false);
    //         } elseif (is_array($current_state)) {
    //             update_option(Simula_Security_Telemetry_Config::STATE, array_merge($legacy_state, $current_state), false);
    //         }
    //         delete_option(Simula_Security_Telemetry_Config::LEGACY_STATE);
    //     }

    //     wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::LEGACY_CRON_HOOK);
    //     wp_clear_scheduled_hook(Simula_Security_Telemetry_Config::LEGACY_SLOW_CRON_HOOK);
    // }

    /** Sanitizes submitted settings, updates scheduling, and writes disabled metrics when needed. */
    public static function sanitize_options($input) {
        $defaults = Simula_Security_Telemetry_Config::defaults();
        $input    = is_array($input) ? $input : [];
        $output   = [];

        $output['enabled']              = empty($input['enabled']) ? 0 : 1;
        $output['cron_interval']        = self::sanitize_cron_interval($input['cron_interval'] ?? $defaults['cron_interval']);
        $output['slow_cron_interval']   = self::sanitize_slow_cron_interval($input['slow_cron_interval'] ?? $defaults['slow_cron_interval']);
        $output['prom_file']            = self::sanitize_prom_file($input['prom_file'] ?? $defaults['prom_file']);
        $output['metric_prefix']        = self::sanitize_metric_prefix($input['metric_prefix'] ?? $defaults['metric_prefix']);
        $output['site_label']           = sanitize_text_field(wp_unslash((string) ($input['site_label'] ?? $defaults['site_label'])));
        $output['incident_log_enabled'] = empty($input['incident_log_enabled']) ? 0 : 1;
        $output['incident_log_file']    = self::sanitize_incident_log_file($input['incident_log_file'] ?? $defaults['incident_log_file']);
        $output['incident_log_format']  = self::sanitize_incident_log_format($input['incident_log_format'] ?? $defaults['incident_log_format']);
        $output['incident_max_rows']    = self::sanitize_incident_max_rows($input['incident_max_rows'] ?? $defaults['incident_max_rows']);
        $output['privacy_ip_mode']      = self::sanitize_privacy_ip_mode($input['privacy_ip_mode'] ?? $defaults['privacy_ip_mode']);
        $output['privacy_drop_url_query'] = empty($input['privacy_drop_url_query']) ? 0 : 1;
        $output['privacy_drop_referer'] = empty($input['privacy_drop_referer']) ? 0 : 1;
        $output['privacy_drop_user_agent'] = empty($input['privacy_drop_user_agent']) ? 0 : 1;
        $output['privacy_exclude_private_ips'] = empty($input['privacy_exclude_private_ips']) ? 0 : 1;
        $output['privacy_retention_note'] = self::sanitize_retention_note($input['privacy_retention_note'] ?? $defaults['privacy_retention_note']);
        $output['admin_identity_mode'] = self::sanitize_admin_identity_mode($input['admin_identity_mode'] ?? $defaults['admin_identity_mode']);
        $output['enabled_metrics']      = self::sanitize_enabled_metrics($input['enabled_metrics'] ?? []);

        if ($output['site_label'] === '') {
            $output['site_label'] = $defaults['site_label'];
        }

        self::sync_schedule($output);

        if (!$output['enabled']) {
            Simula_Security_Telemetry_Output::write_disabled_metrics($output);
        }

        return $output;
    }

    /** Checks whether a metric family is enabled for export. */
    public static function is_metric_enabled($options, $metric_key) {
        $enabled_metrics = self::normalize_enabled_metrics($options['enabled_metrics'] ?? []);

        return !empty($enabled_metrics[$metric_key]);
    }

    /** Ensures the cron event is scheduled only while exporting is enabled. */
    public static function sync_schedule($options) {
        self::sync_single_schedule(
            Simula_Security_Telemetry_Config::CRON_HOOK,
            self::sanitize_cron_interval($options['cron_interval'] ?? Simula_Security_Telemetry_Config::defaults()['cron_interval']),
            !empty($options['enabled'])
        );
        self::sync_single_schedule(
            Simula_Security_Telemetry_Config::SLOW_CRON_HOOK,
            self::sanitize_slow_cron_interval($options['slow_cron_interval'] ?? Simula_Security_Telemetry_Config::defaults()['slow_cron_interval']),
            !empty($options['enabled'])
        );
    }

    /** Synchronizes one named cron hook with the requested interval and enabled state. */
    private static function sync_single_schedule($hook, $interval, $enabled) {
        $scheduled = wp_next_scheduled($hook);

        if ($enabled) {
            $event = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event($hook) : false;

            if ($event && $event->schedule !== $interval) {
                wp_clear_scheduled_hook($hook);
                $scheduled = false;
            }

            if (!$scheduled) {
                wp_schedule_event(time() + 60, $interval, $hook);
            }

            return;
        }

        if ($scheduled) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /** Formats a stored export timestamp for display in the admin UI. */
    public static function format_state_time($timestamp) {
        $timestamp = (int) $timestamp;

        if ($timestamp <= 0) {
            return __('Never', 'simula-security-telemetry-for-wordfence');
        }

        return gmdate('Y-m-d H:i:s', $timestamp) . ' UTC';
    }

    /** Formats a duration in seconds for operator-facing status output. */
    public static function format_state_duration($seconds) {
        $seconds = max(0, (int) $seconds);

        if ($seconds <= 0) {
            return __('0 seconds', 'simula-security-telemetry-for-wordfence');
        }

        $units = [
            'day'    => 86400,
            'hour'   => 3600,
            'minute' => 60,
            'second' => 1,
        ];
        $parts = [];

        foreach ($units as $label => $size) {
            if ($seconds < $size && $parts === []) {
                continue;
            }

            $count = intdiv($seconds, $size);
            if ($count <= 0) {
                continue;
            }

            $parts[] = sprintf(
                /* translators: 1: count, 2: unit label */
                _n('%1$d %2$s', '%1$d %2$ss', $count, 'simula-security-telemetry-for-wordfence'),
                $count,
                $label
            );
            $seconds %= $size;

            if (count($parts) >= 2) {
                break;
            }
        }

        return implode(' ', $parts);
    }

    /** Resolves a configured cron schedule name to its interval in seconds when possible. */
    public static function schedule_interval_seconds($schedule) {
        $schedule = (string) $schedule;
        if ($schedule === '') {
            return 0;
        }

        $schedules = function_exists('wp_get_schedules') ? wp_get_schedules() : [];
        if (isset($schedules[$schedule]['interval'])) {
            return max(0, (int) $schedules[$schedule]['interval']);
        }

        $fallbacks = [
            'sstfw_five_minutes'    => 300,
            'sstfw_fifteen_minutes' => 900,
            'sstfw_thirty_minutes'  => 1800,
            'hourly'                => HOUR_IN_SECONDS,
            'twicedaily'            => 12 * HOUR_IN_SECONDS,
            'daily'                 => DAY_IN_SECONDS,
        ];

        return isset($fallbacks[$schedule]) ? (int) $fallbacks[$schedule] : 0;
    }

    /** Returns the next scheduled timestamp for a cron hook, or 0 when none is queued. */
    public static function next_scheduled_timestamp($hook) {
        return max(0, (int) wp_next_scheduled($hook));
    }

    /** Returns a human-readable exporter freshness summary relative to a configured interval. */
    public static function freshness_summary($last_timestamp, $interval_seconds, $enabled, $subject_label, $now = null) {
        $now             = $now === null ? time() : (int) $now;
        $last_timestamp  = (int) $last_timestamp;
        $interval_seconds = max(0, (int) $interval_seconds);
        $subject_label   = (string) $subject_label;

        if (!$enabled) {
            return __('Exporter disabled.', 'simula-security-telemetry-for-wordfence');
        }

        if ($last_timestamp <= 0) {
            return sprintf(
                /* translators: %s: subject label like "Fast export" */
                __('No %s has completed yet.', 'simula-security-telemetry-for-wordfence'),
                strtolower($subject_label)
            );
        }

        if ($interval_seconds <= 0) {
            return sprintf(
                /* translators: %s: subject label like "Fast export" */
                __('No interval is configured for %s freshness checks.', 'simula-security-telemetry-for-wordfence'),
                strtolower($subject_label)
            );
        }

        $age = max(0, $now - $last_timestamp);
        if ($age > $interval_seconds) {
            return sprintf(
                /* translators: 1: subject label, 2: overdue duration, 3: configured interval duration */
                __('%1$s is overdue by %2$s relative to the configured %3$s interval.', 'simula-security-telemetry-for-wordfence'),
                $subject_label,
                self::format_state_duration($age - $interval_seconds),
                self::format_state_duration($interval_seconds)
            );
        }

        return sprintf(
            /* translators: 1: subject label, 2: current age, 3: configured interval duration */
            __('%1$s is within the configured interval at %2$s old out of %3$s.', 'simula-security-telemetry-for-wordfence'),
            $subject_label,
            self::format_state_duration($age),
            self::format_state_duration($interval_seconds)
        );
    }

    /** Validates and normalizes the configured Prometheus output file path. */
    private static function sanitize_prom_file($value) {
        $default = Simula_Security_Telemetry_Config::defaults()['prom_file'];

        return Simula_Security_Telemetry_Util::sanitize_file_setting_path(
            $value,
            $default,
            'sstfw-prom-file',
            __('The Prometheus file path must be absolute. The default path has been restored.', 'simula-security-telemetry-for-wordfence'),
            'sstfw-prom-file-extension',
            '/\.prom$/',
            __('The Prometheus file path must end with .prom. The default path has been restored.', 'simula-security-telemetry-for-wordfence')
        );
    }

    /** Validates and normalizes the configured incident log output path. */
    private static function sanitize_incident_log_file($value) {
        $default = Simula_Security_Telemetry_Config::defaults()['incident_log_file'];

        return Simula_Security_Telemetry_Util::sanitize_file_setting_path(
            $value,
            $default,
            'sstfw-incident-log-file',
            __('The incident log file path must be absolute. The default path has been restored.', 'simula-security-telemetry-for-wordfence'),
            'sstfw-incident-log-file-extension',
            '/\.(?:log|jsonl)$/',
            __('The incident log file path must end with .log or .jsonl. The default path has been restored.', 'simula-security-telemetry-for-wordfence')
        );
    }

    /** Converts the configured metric prefix into a Prometheus-safe identifier. */
    private static function sanitize_metric_prefix($value) {
        $value = wp_unslash((string) $value);
        $value = preg_replace('/[^a-zA-Z0-9_:]/', '_', $value);

        if ($value === '' || !preg_match('/^[a-zA-Z_:]/', $value)) {
            $value = 'wordpress_wordfence';
        }

        return $value;
    }

    /** Validates the configured WP-Cron interval. */
    private static function sanitize_cron_interval($value) {
        $value     = sanitize_key(wp_unslash((string) $value));
        $intervals = Simula_Security_Telemetry_Metrics::cron_interval_labels();

        return isset($intervals[$value]) ? $value : Simula_Security_Telemetry_Config::defaults()['cron_interval'];
    }

    /** Validates the configured slow collector WP-Cron interval. */
    private static function sanitize_slow_cron_interval($value) {
        $value     = sanitize_key(wp_unslash((string) $value));
        $intervals = Simula_Security_Telemetry_Metrics::slow_cron_interval_labels();

        return isset($intervals[$value]) ? $value : Simula_Security_Telemetry_Config::defaults()['slow_cron_interval'];
    }

    /** Validates the configured incident log format. */
    private static function sanitize_incident_log_format($value) {
        $value = sanitize_key(wp_unslash((string) $value));

        return in_array($value, ['text', 'jsonl'], true) ? $value : Simula_Security_Telemetry_Config::defaults()['incident_log_format'];
    }

    /** Validates the configured IP privacy mode for incident logs. */
    private static function sanitize_privacy_ip_mode($value) {
        $value = sanitize_key(wp_unslash((string) $value));

        return in_array($value, ['full', 'truncate', 'hash', 'drop'], true) ? $value : Simula_Security_Telemetry_Config::defaults()['privacy_ip_mode'];
    }

    /** Validates the configured administrator identity label mode. */
    private static function sanitize_admin_identity_mode($value) {
        $value = sanitize_key(wp_unslash((string) $value));

        return in_array($value, ['hashed', 'id_only', 'disabled'], true) ? $value : Simula_Security_Telemetry_Config::defaults()['admin_identity_mode'];
    }

    /** Validates the maximum number of incident rows exported per run. */
    private static function sanitize_incident_max_rows($value) {
        $value = absint($value);

        if ($value < 1) {
            $value = Simula_Security_Telemetry_Config::defaults()['incident_max_rows'];
        }

        return min($value, 10000);
    }

    /** Sanitizes the operator-facing incident retention note. */
    private static function sanitize_retention_note($value) {
        $value = wp_strip_all_tags(wp_unslash((string) $value));
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $value);
        $value = trim(is_string($value) ? $value : '');

        if (strlen($value) > 200) {
            $value = substr($value, 0, 200);
        }

        return $value;
    }

    /** Normalizes stored metric settings to include every known metric family. */
    private static function normalize_enabled_metrics($value) {
        $defaults = Simula_Security_Telemetry_Config::default_enabled_metrics();
        $value    = is_array($value) ? $value : [];

        foreach ($defaults as $metric_key => $default_value) {
            if (array_key_exists($metric_key, $value)) {
                $defaults[$metric_key] = empty($value[$metric_key]) ? 0 : 1;
            }
        }

        return $defaults;
    }

    /** Sanitizes submitted metric settings from the admin form. */
    private static function sanitize_enabled_metrics($value) {
        $sanitized = Simula_Security_Telemetry_Config::default_enabled_metrics();
        $value     = is_array($value) ? $value : [];

        foreach ($sanitized as $metric_key => $default_value) {
            $sanitized[$metric_key] = empty($value[$metric_key]) ? 0 : 1;
        }

        return $sanitized;
    }

    /** Converts legacy wfne-prefixed schedule values to their sstfw-prefixed equivalents. */
    // private static function normalize_legacy_options($options) {
    //     $options = is_array($options) ? $options : [];
    //     $schedule_map = [
    //         'wfne_five_minutes'    => 'sstfw_five_minutes',
    //         'wfne_fifteen_minutes' => 'sstfw_fifteen_minutes',
    //         'wfne_thirty_minutes'  => 'sstfw_thirty_minutes',
    //     ];

    //     foreach (['cron_interval', 'slow_cron_interval'] as $key) {
    //         if (isset($options[$key], $schedule_map[$options[$key]])) {
    //             $options[$key] = $schedule_map[$options[$key]];
    //         }
    //     }

    //     return $options;
    // }
}
