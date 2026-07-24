<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Output {
    /** Writes a disabled-export metrics file and updates exporter state. */
    public static function write_disabled_metrics($options, $state = [], $disabled_message = null) {
        $state = is_array($state) ? $state : [];
        $site  = self::escape_label($options['site_label']);
        $now   = time();
        $body  = [];
        $disabled_message = is_string($disabled_message) && $disabled_message !== ''
            ? $disabled_message
            : __('Export disabled.', 'simula-security-telemetry-for-wordfence');

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'export_success')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_export_success',
                'gauge',
                'Whether the last Wordfence metrics export succeeded.',
                [
                    ['labels' => ['site' => $site], 'value' => 0],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'plugin_info')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_plugin_info',
                'gauge',
                'Plugin metadata for the exporter.',
                [
                    ['labels' => ['site' => $site, 'version' => self::escape_label(Simula_Security_Telemetry_Config::VERSION)], 'value' => 1],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'enabled')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_enabled',
                'gauge',
                'Whether the exporter master switch is enabled.',
                [
                    ['labels' => ['site' => $site], 'value' => 0],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'last_export_timestamp_seconds')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_last_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the last export attempt.',
                [
                    ['labels' => ['site' => $site], 'value' => $now],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'next_export_timestamp_seconds')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_next_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the next scheduled fast exporter run.',
                [
                    ['labels' => ['site' => $site], 'value' => 0],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'next_slow_export_timestamp_seconds')) {
            self::append_metric_family(
                $body,
                $options['metric_prefix'] . '_next_slow_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the next scheduled slow collector run.',
                [
                    ['labels' => ['site' => $site], 'value' => 0],
                ]
            );
        }

        $state['last_export'] = $now;

        return self::write_metrics(
            $options['prom_file'],
            empty($body) ? '' : implode("\n", $body) . "\n",
            $disabled_message,
            $state
        );
    }

    /** Builds the fallback metric payload used when an export fails. */
    public static function build_failure_metrics($options, $timestamp, $message) {
        $prefix  = $options['metric_prefix'];
        $site    = self::escape_label($options['site_label']);
        $enabled = !empty($options['enabled']);
        $metrics   = [];

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'export_success')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_export_success',
                'gauge',
                'Whether the last Wordfence metrics export succeeded.',
                [
                    ['labels' => ['site' => $site], 'value' => 0],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'plugin_info')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_plugin_info',
                'gauge',
                'Plugin metadata for the exporter.',
                [
                    ['labels' => ['site' => $site, 'version' => self::escape_label(Simula_Security_Telemetry_Config::VERSION)], 'value' => 1],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'enabled')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_enabled',
                'gauge',
                'Whether the exporter master switch is enabled.',
                [
                    ['labels' => ['site' => $site], 'value' => (int) $enabled],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'last_export_timestamp_seconds')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_last_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the last export attempt.',
                [
                    ['labels' => ['site' => $site], 'value' => $timestamp],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'next_export_timestamp_seconds')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_next_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the next scheduled fast exporter run.',
                [
                    ['labels' => ['site' => $site], 'value' => Simula_Security_Telemetry_Settings::next_scheduled_timestamp(Simula_Security_Telemetry_Config::CRON_HOOK)],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'next_slow_export_timestamp_seconds')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_next_slow_export_timestamp_seconds',
                'gauge',
                'Unix timestamp of the next scheduled slow collector run.',
                [
                    ['labels' => ['site' => $site], 'value' => Simula_Security_Telemetry_Settings::next_scheduled_timestamp(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK)],
                ]
            );
        }

        if (Simula_Security_Telemetry_Settings::is_metric_enabled($options, 'error_info')) {
            self::append_metric_family(
                $metrics,
                $prefix . '_error_info',
                'gauge',
                'Static bounded error indicator for the latest export.',
                [
                    ['labels' => ['site' => $site, 'type' => self::escape_label(self::classify_error_type((string) $message))], 'value' => 1],
                ]
            );
        }

        return empty($metrics) ? '' : implode("\n", $metrics) . "\n";
    }

    /** Atomically writes the metrics file and optionally persists the outcome to plugin state. */
    public static function write_metrics($file, $content, $error_message, $state, $persist_state = true) {
        $state      = is_array($state) ? $state : [];
        $directory  = dirname($file);
        $filesystem = Simula_Security_Telemetry_Util::filesystem();
        $ok         = false;
        $message    = '';
        $result_ok  = false;

        if ($filesystem === null) {
            $message = __('Could not initialize the WordPress filesystem API.', 'simula-security-telemetry-for-wordfence');
        } elseif (!preg_match('/\.prom$/', $file)) {
            $message = __('Output file must end with .prom.', 'simula-security-telemetry-for-wordfence');
        } elseif (!$filesystem->is_dir($directory)) {
            $message = sprintf(
                /* translators: %s: Metrics output directory path. */
                __('Output directory does not exist: %s', 'simula-security-telemetry-for-wordfence'),
                $directory
            );
        } elseif (!$filesystem->is_writable($directory)) {
            $message = sprintf(
                /* translators: %s: Metrics output directory path. */
                __('Output directory is not writable by PHP: %s', 'simula-security-telemetry-for-wordfence'),
                $directory
            );
        } else {
            $tmp_name = sprintf(
                '%s/.%s.%s.tmp',
                $directory,
                basename($file),
                wp_generate_password(12, false, false)
            );

            $written = $filesystem->put_contents($tmp_name, $content, FS_CHMOD_FILE);
            if (!$written) {
                $message = __('Failed writing the temporary metrics file.', 'simula-security-telemetry-for-wordfence');
            } elseif (!$filesystem->move($tmp_name, $file, true)) {
                $filesystem->delete($tmp_name);
                $message = __('Failed moving the temporary metrics file into place.', 'simula-security-telemetry-for-wordfence');
            } else {
                $ok        = true;
                $message   = $error_message !== '' ? $error_message : sprintf(
                    /* translators: %s: Metrics output file path. */
                    __('Metrics exported to %s', 'simula-security-telemetry-for-wordfence'),
                    $file
                );
                $result_ok = $error_message === '';
            }
        }

        if (!$ok) {
            $result_ok = false;
        }

        $state['last_result']    = $message;
        $state['last_result_ok'] = $result_ok ? 1 : 0;
        $state['last_error']     = $result_ok ? '' : $message;
        if ($persist_state) {
            update_option(Simula_Security_Telemetry_Config::STATE, $state, false);
        }

        return [
            'ok'      => $result_ok,
            'message' => $message,
            'state'   => $state,
        ];
    }

    /** Maps detailed error text to a bounded metric label value. */
    public static function classify_error_type($message) {
        $message = strtolower((string) $message);

        if (strpos($message, 'wordfence table not found') !== false || strpos($message, 'wordfence missing') !== false) {
            return 'wordfence_missing';
        }

        if (strpos($message, 'unsupported wordfence') !== false || strpos($message, 'schema') !== false) {
            return 'schema_unsupported';
        }

        if (strpos($message, 'incident') !== false || strpos($message, 'log') !== false) {
            return 'incident_failed';
        }

        if (strpos($message, 'write') !== false || strpos($message, 'writable') !== false || strpos($message, 'directory') !== false || strpos($message, 'file') !== false) {
            return 'write_failed';
        }

        return 'unknown';
    }

    /** Appends a HELP/TYPE block and one or more metric samples using pre-escaped label values. */
    public static function append_metric_family(&$lines, $metric_name, $type, $help, $samples) {
        $lines[] = '# HELP ' . $metric_name . ' ' . $help;
        $lines[] = '# TYPE ' . $metric_name . ' ' . $type;

        foreach ((array) $samples as $sample) {
            $labels = isset($sample['labels']) && is_array($sample['labels']) ? $sample['labels'] : [];
            $value  = $sample['value'] ?? 0;
            $lines[] = self::build_metric_sample_line($metric_name, $labels, $value);
        }
    }

    /** Builds a single metric sample line using pre-escaped label values. */
    public static function build_metric_sample_line($metric_name, $labels, $value) {
        $label_sql = self::format_metric_labels($labels);

        return $metric_name . $label_sql . ' ' . self::format_metric_value($value);
    }

    /** Escapes a string for safe use in Prometheus label values. */
    public static function escape_label($value) {
        return str_replace(
            ["\\", "\n", '"'],
            ["\\\\", "\\n", '\\"'],
            (string) $value
        );
    }

    /** Formats numeric values for Prometheus output without unnecessary decimals. */
    public static function format_number($value) {
        if (is_int($value) || floor((float) $value) === (float) $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.6F', (float) $value), '0'), '.');
    }

    /** Formats a metric label set from pre-escaped label values. */
    private static function format_metric_labels($labels) {
        $parts = [];

        foreach ((array) $labels as $key => $value) {
            $parts[] = $key . '="' . (string) $value . '"';
        }

        return $parts === [] ? '' : '{' . implode(',', $parts) . '}';
    }

    /** Formats a metric sample value. */
    private static function format_metric_value($value) {
        if (is_int($value) || is_float($value) || is_numeric($value)) {
            return self::format_number($value);
        }

        return (string) $value;
    }
}

