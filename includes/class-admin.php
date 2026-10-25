<?php
/**
 * Simula Security Telemetry for Wordfence include.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Simula_Security_Telemetry_Admin {
    /** Registers the operator dashboard and settings page. */
    public static function admin_menu() {
        add_menu_page(
            __('Security Telemetry', 'simula-security-telemetry-for-wordfence'),
            __('Telemetry', 'simula-security-telemetry-for-wordfence'),
            Simula_Security_Telemetry_Config::CAPABILITY,
            Simula_Security_Telemetry_Config::DASHBOARD_SLUG,
            [__CLASS__, 'dashboard_page'],
            '',
            76
        );

        add_options_page(
            __('Simula Security Telemetry for Wordfence', 'simula-security-telemetry-for-wordfence'),
            __('Security Telemetry', 'simula-security-telemetry-for-wordfence'),
            Simula_Security_Telemetry_Config::CAPABILITY,
            Simula_Security_Telemetry_Config::SLUG,
            [__CLASS__, 'settings_page']
        );
    }

    /** Registers the compact current-state widget for the main WordPress dashboard. */
    public static function register_dashboard_widget() {
        if (!current_user_can(Simula_Security_Telemetry_Config::CAPABILITY)) {
            return;
        }

        wp_add_dashboard_widget(
            'sstfw_security_telemetry',
            __('Security Telemetry', 'simula-security-telemetry-for-wordfence'),
            [__CLASS__, 'dashboard_widget']
        );
    }

    /** Renders the read-only current-state summary on the main WordPress dashboard. */
    public static function dashboard_widget() {
        if (!current_user_can(Simula_Security_Telemetry_Config::CAPABILITY)) {
            return;
        }

        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();
        $model   = Simula_Security_Telemetry_Admin_Dashboard::view_model($options, $state);
        $cards   = [
            __('Exporter health', 'simula-security-telemetry-for-wordfence') => $model['exporter']['status'],
            __('PHP runtime posture', 'simula-security-telemetry-for-wordfence') => $model['runtime']['status'],
            __('WordPress posture', 'simula-security-telemetry-for-wordfence') => $model['wordpress']['status'],
            __('Change signals', 'simula-security-telemetry-for-wordfence') => $model['changes']['status'],
        ];
        ?>
        <p><strong><?php echo esc_html__('Overall status:', 'simula-security-telemetry-for-wordfence'); ?></strong> <?php echo esc_html(self::dashboard_status_label($model['overall_status'])); ?></p>
        <table class="widefat striped"><tbody>
            <?php foreach ($cards as $title => $status) : ?>
                <tr>
                    <td><?php echo esc_html($title); ?></td>
                    <td><strong><?php echo esc_html(self::dashboard_status_label($status)); ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . Simula_Security_Telemetry_Config::DASHBOARD_SLUG)); ?>"><?php echo esc_html__('View Security Telemetry dashboard', 'simula-security-telemetry-for-wordfence'); ?></a>
            <?php if ($model['grafana_url'] !== '') : ?>
                <span aria-hidden="true">&nbsp;|&nbsp;</span><a href="<?php echo esc_url($model['grafana_url']); ?>"><?php echo esc_html__('View Grafana', 'simula-security-telemetry-for-wordfence'); ?></a>
            <?php endif; ?>
        </p>
        <?php
    }

    /** Renders the current-state, non-Wordfence operator dashboard. */
    public static function dashboard_page() {
        if (!current_user_can(Simula_Security_Telemetry_Config::CAPABILITY)) {
            return;
        }

        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();
        $model   = Simula_Security_Telemetry_Admin_Dashboard::view_model($options, $state);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Security Telemetry', 'simula-security-telemetry-for-wordfence'); ?></h1>
            <p><?php echo esc_html__('Current WordPress and PHP posture collected by this plugin. Wordfence activity, scan, incident, and configuration data are not shown here.', 'simula-security-telemetry-for-wordfence'); ?></p>
            <p><strong><?php echo esc_html__('Overall status:', 'simula-security-telemetry-for-wordfence'); ?></strong> <?php echo esc_html(self::dashboard_status_label($model['overall_status'])); ?></p>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; max-width:1200px;">
                <?php self::render_dashboard_card(__('Exporter health', 'simula-security-telemetry-for-wordfence'), $model['exporter']['status'], self::dashboard_exporter_rows($model['exporter'])); ?>
                <?php self::render_dashboard_card(__('PHP runtime posture', 'simula-security-telemetry-for-wordfence'), $model['runtime']['status'], self::dashboard_runtime_rows($model['runtime'])); ?>
                <?php self::render_dashboard_card(__('WordPress posture', 'simula-security-telemetry-for-wordfence'), $model['wordpress']['status'], self::dashboard_wordpress_rows($model['wordpress'])); ?>
                <?php self::render_dashboard_card(__('Change signals', 'simula-security-telemetry-for-wordfence'), $model['changes']['status'], self::dashboard_change_rows($model['changes'])); ?>
            </div>

            <?php if ($model['grafana_url'] !== '') : ?>
                <p style="margin-top:20px;"><a class="button button-secondary" href="<?php echo esc_url($model['grafana_url']); ?>"><?php echo esc_html__('View historical telemetry in Grafana', 'simula-security-telemetry-for-wordfence'); ?></a></p>
            <?php endif; ?>
            <p><a href="<?php echo esc_url(admin_url('options-general.php?page=' . Simula_Security_Telemetry_Config::SLUG)); ?>"><?php echo esc_html__('Configure Security Telemetry', 'simula-security-telemetry-for-wordfence'); ?></a></p>
        </div>
        <?php
    }

    /** Adds a Settings shortcut link on the plugins screen. */
    public static function plugin_action_links($links) {
        $url = admin_url('options-general.php?page=' . Simula_Security_Telemetry_Config::SLUG);

        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url($url),
                esc_html__('Settings', 'simula-security-telemetry-for-wordfence')
            )
        );

        return $links;
    }

    /** Renders the settings page and handles manual export requests. */
    public static function settings_page() {
        if (!current_user_can(Simula_Security_Telemetry_Config::CAPABILITY)) {
            return;
        }

        self::handle_settings_page_actions();

        $options = Simula_Security_Telemetry_Settings::get_options();
        $state   = Simula_Security_Telemetry_Settings::get_state();
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html__('Simula Security Telemetry for Wordfence', 'simula-security-telemetry-for-wordfence'); ?>
                <span style="font-size:14px; font-weight:400; color:#50575e; margin-left:8px;">v<?php echo esc_html(Simula_Security_Telemetry_Config::VERSION); ?></span>
            </h1>
            <p><?php echo esc_html__('Exports Wordfence block telemetry into a Prometheus .prom file for node_exporter textfile collection and blocked-request events into a plain-text incident log.', 'simula-security-telemetry-for-wordfence'); ?></p>

            <?php settings_errors('sstfw_metrics'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('sstfw_metrics'); ?>
                <?php self::render_metrics_settings_section($options); ?>
                <?php self::render_incident_settings_section($options); ?>
                <?php submit_button(); ?>
            </form>

            <hr />

            <?php self::render_manual_actions_section(); ?>
            <?php self::render_current_state_section($options, $state); ?>
            <?php self::render_sample_incident_section($options); ?>
        </div>
        <?php
    }

    /** Handles manual export and cursor reset actions from the settings page. */
    private static function handle_settings_page_actions() {
        if (isset($_POST['sstfw_export_now'])) {
            check_admin_referer('sstfw_export_now');
            $result = Simula_Security_Telemetry_Service::export(true);
            add_settings_error(
                'sstfw_metrics',
                'sstfw-export-now',
                $result['message'],
                $result['ok'] ? 'updated' : 'error'
            );
        }

        if (isset($_POST['sstfw_reset_incident_cursor'])) {
            check_admin_referer('sstfw_reset_incident_cursor');
            Simula_Security_Telemetry_Incidents::reset_cursor();
            add_settings_error(
                'sstfw_metrics',
                'sstfw-reset-incident-cursor',
                __('Incident cursor reset to 0. The next export can backfill retained Wordfence incidents up to the configured row limit.', 'simula-security-telemetry-for-wordfence'),
                'updated'
            );
        }
    }

    /** Renders the Prometheus exporter settings section. */
    private static function render_metrics_settings_section($options) {
        ?>
        <h2><?php echo esc_html__('Prometheus metrics', 'simula-security-telemetry-for-wordfence'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Enable exporter', 'simula-security-telemetry-for-wordfence'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[enabled]" value="1" <?php checked($options['enabled'], 1); ?> />
                        <?php echo esc_html__('Master switch for the exporter. When disabled, both Prometheus metrics and incident log exports are off.', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-cron-interval"><?php echo esc_html__('Cron interval', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <select id="sstfw-cron-interval" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[cron_interval]">
                        <?php foreach (Simula_Security_Telemetry_Metrics::cron_interval_labels() as $interval_key => $interval_label) : ?>
                            <option value="<?php echo esc_attr($interval_key); ?>" <?php selected($options['cron_interval'], $interval_key); ?>>
                                <?php echo esc_html($interval_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Controls how often WP-Cron runs exports while the exporter is enabled.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-slow-cron-interval"><?php echo esc_html__('Slow collector interval', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <select id="sstfw-slow-cron-interval" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[slow_cron_interval]">
                        <?php foreach (Simula_Security_Telemetry_Metrics::slow_cron_interval_labels() as $interval_key => $interval_label) : ?>
                            <option value="<?php echo esc_attr($interval_key); ?>" <?php selected($options['slow_cron_interval'], $interval_key); ?>>
                                <?php echo esc_html($interval_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php echo esc_html__('Refreshes slow-changing scan, two-factor, WordPress posture, and Wordfence posture metrics.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-prom-file"><?php echo esc_html__('Prometheus file path', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-prom-file" class="regular-text code" type="text" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[prom_file]" value="<?php echo esc_attr($options['prom_file']); ?>" />
                    <p class="description"><?php echo esc_html__('Example: /var/lib/node_exporter/textfile_collector/wordfence.prom. The directory must already exist and be writable by PHP.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-metric-prefix"><?php echo esc_html__('Metric prefix', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-metric-prefix" class="regular-text code" type="text" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[metric_prefix]" value="<?php echo esc_attr($options['metric_prefix']); ?>" />
                    <p class="description"><?php echo esc_html__('Prometheus metric prefix. Invalid characters are replaced automatically.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-site-label"><?php echo esc_html__('Site label', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-site-label" class="regular-text" type="text" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[site_label]" value="<?php echo esc_attr($options['site_label']); ?>" />
                    <p class="description"><?php echo esc_html__('Added to every exported metric as the site label value.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-grafana-dashboard-url"><?php echo esc_html__('Grafana dashboard URL', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-grafana-dashboard-url" class="regular-text code" type="url" inputmode="url" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[grafana_dashboard_url]" value="<?php echo esc_attr($options['grafana_dashboard_url']); ?>" placeholder="https://grafana.example.test/d/..." />
                    <p class="description"><?php echo esc_html__('Optional HTTPS link shown on the Security Telemetry dashboard. The plugin does not query or embed Grafana.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Exported metrics', 'simula-security-telemetry-for-wordfence'); ?></th>
                <td>
                    <fieldset>
                        <?php foreach (Simula_Security_Telemetry_Config::metric_definitions() as $metric_key => $metric_definition) : ?>
                            <label style="display:block; margin-bottom:12px;">
                                <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[enabled_metrics][<?php echo esc_attr($metric_key); ?>]" value="1" <?php checked(!empty($options['enabled_metrics'][$metric_key])); ?> />
                                <strong><code><?php echo esc_html($options['metric_prefix'] . '_' . $metric_key); ?></code></strong>
                                <?php echo esc_html($metric_definition['label']); ?>
                                <br />
                                <span class="description"><?php echo esc_html($metric_definition['description']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-admin-identity-mode"><?php echo esc_html__('Admin identity labels', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <select id="sstfw-admin-identity-mode" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[admin_identity_mode]">
                        <option value="hashed" <?php selected($options['admin_identity_mode'], 'hashed'); ?>><?php echo esc_html__('Hash administrator IDs, logins, and display names', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="id_only" <?php selected($options['admin_identity_mode'], 'id_only'); ?>><?php echo esc_html__('Export administrator numeric IDs only', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="disabled" <?php selected($options['admin_identity_mode'], 'disabled'); ?>><?php echo esc_html__('Counts only; no per-admin identity labels', 'simula-security-telemetry-for-wordfence'); ?></option>
                    </select>
                    <p class="description"><?php echo esc_html__('Applies only when the opt-in admin_user_info metric is enabled. Hashed mode is recommended for Prometheus labels because it avoids raw usernames, display names, and email addresses.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /** Renders the incident log settings section. */
    private static function render_incident_settings_section($options) {
        ?>
        <h2><?php echo esc_html__('Incident log', 'simula-security-telemetry-for-wordfence'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Enable incident log export', 'simula-security-telemetry-for-wordfence'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[incident_log_enabled]" value="1" <?php checked($options['incident_log_enabled'], 1); ?> />
                        <?php echo esc_html__('Append blocked Wordfence hits to the incident log on each export run. This runs only while the exporter is enabled.', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-incident-log-file"><?php echo esc_html__('Incident log path', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-incident-log-file" class="regular-text code" type="text" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[incident_log_file]" value="<?php echo esc_attr($options['incident_log_file']); ?>" />
                    <p class="description"><?php echo esc_html__('Use an absolute log file path. A .log suffix is recommended; existing .jsonl paths are still accepted. The directory must already exist and be writable by PHP.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-incident-log-format"><?php echo esc_html__('Incident log format', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <select id="sstfw-incident-log-format" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[incident_log_format]">
                        <option value="text" <?php selected($options['incident_log_format'], 'text'); ?>><?php echo esc_html__('Text', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="jsonl" <?php selected($options['incident_log_format'], 'jsonl'); ?>><?php echo esc_html__('JSON Lines', 'simula-security-telemetry-for-wordfence'); ?></option>
                    </select>
                    <p class="description"><?php echo esc_html__('Text preserves the v1 log format. JSON Lines emits one structured JSON object per blocked event for Loki, ELK, and OpenSearch pipelines.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-incident-max-rows"><?php echo esc_html__('Max incidents per run', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <input id="sstfw-incident-max-rows" class="small-text" type="number" min="1" max="10000" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[incident_max_rows]" value="<?php echo esc_attr((string) $options['incident_max_rows']); ?>" />
                    <p class="description"><?php echo esc_html__('Caps each export pass so large retained Wordfence hit tables do not create long-running admin or cron requests.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-privacy-ip-mode"><?php echo esc_html__('Incident IP privacy', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <select id="sstfw-privacy-ip-mode" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_ip_mode]">
                        <option value="full" <?php selected($options['privacy_ip_mode'], 'full'); ?>><?php echo esc_html__('Log full IP address', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="truncate" <?php selected($options['privacy_ip_mode'], 'truncate'); ?>><?php echo esc_html__('Truncate to IPv4 /24 or IPv6 /64', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="hash" <?php selected($options['privacy_ip_mode'], 'hash'); ?>><?php echo esc_html__('Hash with site salt', 'simula-security-telemetry-for-wordfence'); ?></option>
                        <option value="drop" <?php selected($options['privacy_ip_mode'], 'drop'); ?>><?php echo esc_html__('Drop IP field', 'simula-security-telemetry-for-wordfence'); ?></option>
                    </select>
                    <p class="description"><?php echo esc_html__('Controls how IP addresses are written to text and JSON Lines incident logs. Prometheus top-source metrics already use normalized IP ranges.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Incident privacy filters', 'simula-security-telemetry-for-wordfence'); ?></th>
                <td>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_drop_url_query]" value="1" <?php checked($options['privacy_drop_url_query'], 1); ?> />
                        <?php echo esc_html__('Drop query strings from logged URLs', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_drop_referer]" value="1" <?php checked($options['privacy_drop_referer'], 1); ?> />
                        <?php echo esc_html__('Drop referer fields from incident logs', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                    <label style="display:block; margin-bottom:8px;">
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_drop_user_agent]" value="1" <?php checked($options['privacy_drop_user_agent'], 1); ?> />
                        <?php echo esc_html__('Drop user-agent fields from incident logs', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                    <label style="display:block;">
                        <input type="checkbox" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_exclude_private_ips]" value="1" <?php checked($options['privacy_exclude_private_ips'], 1); ?> />
                        <?php echo esc_html__('Do not append incidents from private, loopback, link-local, or reserved IP ranges', 'simula-security-telemetry-for-wordfence'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sstfw-privacy-retention-note"><?php echo esc_html__('Retention note', 'simula-security-telemetry-for-wordfence'); ?></label>
                </th>
                <td>
                    <textarea id="sstfw-privacy-retention-note" class="large-text" rows="2" maxlength="200" name="<?php echo esc_attr(Simula_Security_Telemetry_Config::OPTION); ?>[privacy_retention_note]"><?php echo esc_textarea($options['privacy_retention_note']); ?></textarea>
                    <p class="description"><?php echo esc_html__('Optional note appended to each incident event so downstream log users can see the local retention expectation. Keep operational retention enforcement in your log pipeline.', 'simula-security-telemetry-for-wordfence'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /** Renders manual exporter actions below the settings form. */
    private static function render_manual_actions_section() {
        ?>
        <form method="post" style="display:inline-block; margin-right: 12px;">
            <?php wp_nonce_field('sstfw_export_now'); ?>
            <?php submit_button(__('Export now', 'simula-security-telemetry-for-wordfence'), 'secondary', 'sstfw_export_now'); ?>
        </form>
        <p class="description"><?php echo esc_html__('Manual export uses the same master exporter toggle. If the exporter is disabled, the button writes disabled metrics and reports that exports are off.', 'simula-security-telemetry-for-wordfence'); ?></p>

        <form method="post" style="display:inline-block;">
            <?php wp_nonce_field('sstfw_reset_incident_cursor'); ?>
            <?php submit_button(__('Reset incident cursor for backfill', 'simula-security-telemetry-for-wordfence'), 'delete', 'sstfw_reset_incident_cursor'); ?>
        </form>
        <?php
    }

    /** Renders the current exporter state table. */
    private static function render_current_state_section($options, $state) {
        $now                  = time();
        $last_export          = isset($state['last_export']) ? (int) $state['last_export'] : 0;
        $last_slow_refresh    = isset($state['slow_metric_cache_at']) ? (int) $state['slow_metric_cache_at'] : 0;
        $fast_interval_seconds = Simula_Security_Telemetry_Settings::schedule_interval_seconds($options['cron_interval'] ?? '');
        $slow_interval_seconds = Simula_Security_Telemetry_Settings::schedule_interval_seconds($options['slow_cron_interval'] ?? '');
        $next_fast_export     = Simula_Security_Telemetry_Settings::next_scheduled_timestamp(Simula_Security_Telemetry_Config::CRON_HOOK);
        $next_slow_export     = Simula_Security_Telemetry_Settings::next_scheduled_timestamp(Simula_Security_Telemetry_Config::SLOW_CRON_HOOK);
        ?>
        <h2><?php echo esc_html__('Current state', 'simula-security-telemetry-for-wordfence'); ?></h2>
        <table class="widefat striped" style="max-width:900px">
            <tbody>
                <?php self::render_state_row(__('Last export timestamp', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::format_state_time($last_export)); ?>
                <?php self::render_state_row(__('Last export age', 'simula-security-telemetry-for-wordfence'), $last_export > 0 ? Simula_Security_Telemetry_Settings::format_state_duration(max(0, $now - $last_export)) : __('Never', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Fast export interval', 'simula-security-telemetry-for-wordfence'), $fast_interval_seconds > 0 ? Simula_Security_Telemetry_Settings::format_state_duration($fast_interval_seconds) : __('Unknown', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Fast export status', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::freshness_summary($last_export, $fast_interval_seconds, !empty($options['enabled']), __('Fast export', 'simula-security-telemetry-for-wordfence'), $now)); ?>
                <?php self::render_state_row(__('Next fast export', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::format_state_time($next_fast_export)); ?>
                <?php self::render_state_row(__('Observed blocked events', 'simula-security-telemetry-for-wordfence'), (string) ($state['blocked_total'] ?? 0)); ?>
                <?php self::render_state_row(__('Firewall Summary source detected', 'simula-security-telemetry-for-wordfence'), empty($state['firewall_blocks_available']) ? __('No', 'simula-security-telemetry-for-wordfence') : __('Yes', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Firewall Summary schema', 'simula-security-telemetry-for-wordfence'), (string) ($state['firewall_blocks_schema'] ?? __('Unknown', 'simula-security-telemetry-for-wordfence'))); ?>
                <?php self::render_state_row(__('Firewall Summary last collection success', 'simula-security-telemetry-for-wordfence'), empty($state['firewall_blocks_collection_success']) ? __('No', 'simula-security-telemetry-for-wordfence') : __('Yes', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Firewall Summary last successful collection', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::format_state_time($state['firewall_blocks_last_success'] ?? null)); ?>
                <?php self::render_state_row(__('Firewall Summary last error type', 'simula-security-telemetry-for-wordfence'), (string) ($state['firewall_blocks_error_type'] ?? '')); ?>
                <?php self::render_state_row(__('Wordfence block metric distinction', 'simula-security-telemetry-for-wordfence'), __('blocked_hit_rows counts retained hit/live-traffic rows; firewall_blocks counts Wordfence aggregate Firewall Summary buckets.', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Last processed hit ID', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_id'] ?? 0)); ?>
                <?php self::render_state_row(__('Last result', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_result'] ?? __('No exports yet.', 'simula-security-telemetry-for-wordfence'))); ?>
                <?php self::render_state_row(__('Last error', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_error'] ?? '')); ?>
                <?php self::render_state_row(__('Incident cursor initialized', 'simula-security-telemetry-for-wordfence'), !empty($state['incident_cursor_initialized']) ? __('Yes', 'simula-security-telemetry-for-wordfence') : __('No', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Last incident export', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::format_state_time($state['last_incident_export'] ?? null)); ?>
                <?php self::render_state_row(__('Last incident hit ID', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_incident_id'] ?? 0)); ?>
                <?php self::render_state_row(__('Last incident row count', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_incident_exported_rows'] ?? 0)); ?>
                <?php self::render_state_row(__('Last incident log file', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_incident_log_file'] ?? $options['incident_log_file'])); ?>
                <?php self::render_state_row(__('Last incident error', 'simula-security-telemetry-for-wordfence'), (string) ($state['last_incident_error'] ?? '')); ?>
                <?php self::render_state_row(__('Last slow collector refresh', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::format_state_time($last_slow_refresh)); ?>
                <?php self::render_state_row(__('Slow collector age', 'simula-security-telemetry-for-wordfence'), $last_slow_refresh > 0 ? Simula_Security_Telemetry_Settings::format_state_duration(max(0, $now - $last_slow_refresh)) : __('Never', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Slow collector interval', 'simula-security-telemetry-for-wordfence'), $slow_interval_seconds > 0 ? Simula_Security_Telemetry_Settings::format_state_duration($slow_interval_seconds) : __('Unknown', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Slow collector status', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::freshness_summary($last_slow_refresh, $slow_interval_seconds, !empty($options['enabled']), __('Slow collector', 'simula-security-telemetry-for-wordfence'), $now)); ?>
                <?php self::render_state_row(__('Next slow collector run', 'simula-security-telemetry-for-wordfence'), Simula_Security_Telemetry_Settings::format_state_time($next_slow_export)); ?>
                <?php self::render_state_row(__('Plugin inventory metric', 'simula-security-telemetry-for-wordfence'), empty($options['enabled_metrics']['plugin_inventory_info']) ? __('Disabled', 'simula-security-telemetry-for-wordfence') : __('Enabled', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Admin inventory metric', 'simula-security-telemetry-for-wordfence'), empty($options['enabled_metrics']['admin_user_info']) ? __('Disabled', 'simula-security-telemetry-for-wordfence') : __('Enabled', 'simula-security-telemetry-for-wordfence')); ?>
                <?php self::render_state_row(__('Admin identity mode', 'simula-security-telemetry-for-wordfence'), (string) ($options['admin_identity_mode'] ?? 'hashed')); ?>
            </tbody>
        </table>
        <?php
    }

    /** Renders the sample incident log block. */
    private static function render_sample_incident_section($options) {
        ?>
        <h2><?php echo esc_html__('Sample incident log line', 'simula-security-telemetry-for-wordfence'); ?></h2>
        <pre style="max-width:900px; overflow:auto;"><?php echo esc_html(Simula_Security_Telemetry_Incidents::sample_log_line($options)); ?></pre>
        <?php
    }

    /** Renders one row in the exporter state table. */
    private static function render_state_row($label, $value) {
        ?>
        <tr>
            <td><strong><?php echo esc_html($label); ?></strong></td>
            <td><?php echo esc_html((string) $value); ?></td>
        </tr>
        <?php
    }

    /** Renders an accessible current-state dashboard card. */
    private static function render_dashboard_card($title, $status, $rows) {
        ?>
        <section class="card" style="margin:0; padding:16px; max-width:none;">
            <h2 style="margin-top:0;"><?php echo esc_html($title); ?></h2>
            <p><strong><?php echo esc_html__('Status:', 'simula-security-telemetry-for-wordfence'); ?></strong> <?php echo esc_html(self::dashboard_status_label($status)); ?></p>
            <table class="widefat striped"><tbody>
                <?php foreach ($rows as $label => $value) : ?>
                    <?php self::render_state_row($label, $value); ?>
                <?php endforeach; ?>
            </tbody></table>
        </section>
        <?php
    }

    /** Returns a text equivalent for every dashboard status. */
    private static function dashboard_status_label($status) {
        $labels = [
            'healthy' => __('Healthy', 'simula-security-telemetry-for-wordfence'),
            'review' => __('Review needed', 'simula-security-telemetry-for-wordfence'),
            'action_required' => __('Action required', 'simula-security-telemetry-for-wordfence'),
            'unavailable' => __('Data unavailable', 'simula-security-telemetry-for-wordfence'),
        ];

        return $labels[$status] ?? $labels['unavailable'];
    }

    /** Formats exporter-health rows without Wordfence-derived state. */
    private static function dashboard_exporter_rows($card) {
        return [
            __('Last export', 'simula-security-telemetry-for-wordfence') => Simula_Security_Telemetry_Settings::format_state_time($card['last_export'] ?? 0),
            __('Most recent exporter result', 'simula-security-telemetry-for-wordfence') => self::dashboard_exporter_result_label($card['last_result'] ?? 'not_run'),
            __('Last slow refresh', 'simula-security-telemetry-for-wordfence') => Simula_Security_Telemetry_Settings::format_state_time($card['slow_refresh'] ?? 0),
            __('Next export', 'simula-security-telemetry-for-wordfence') => Simula_Security_Telemetry_Settings::format_state_time($card['next_export'] ?? 0),
        ];
    }

    /** Formats the cached PHP runtime allowlist for display. */
    private static function dashboard_runtime_rows($card) {
        if (($card['reason'] ?? '') === 'opt_in_disabled') {
            return [__('Availability', 'simula-security-telemetry-for-wordfence') => __('Disabled until PHP runtime posture metrics are enabled in settings.', 'simula-security-telemetry-for-wordfence')];
        }
        if (($card['reason'] ?? '') === 'not_collected') {
            return [__('Availability', 'simula-security-telemetry-for-wordfence') => __('Awaiting the next slow collector run.', 'simula-security-telemetry-for-wordfence')];
        }

        $data = (array) ($card['data'] ?? []);
        return [
            __('PHP version', 'simula-security-telemetry-for-wordfence') => (string) ($data['version'] ?? __('Unknown', 'simula-security-telemetry-for-wordfence')),
            __('Supported version', 'simula-security-telemetry-for-wordfence') => !empty($data['version_supported']) ? __('Yes', 'simula-security-telemetry-for-wordfence') : __('No', 'simula-security-telemetry-for-wordfence'),
            __('SAPI', 'simula-security-telemetry-for-wordfence') => (string) ($data['sapi'] ?? __('Unknown', 'simula-security-telemetry-for-wordfence')),
            __('Display errors enabled', 'simula-security-telemetry-for-wordfence') => !empty($data['display_errors_enabled']) ? __('Yes', 'simula-security-telemetry-for-wordfence') : __('No', 'simula-security-telemetry-for-wordfence'),
            __('OPcache enabled', 'simula-security-telemetry-for-wordfence') => !empty($data['opcache_enabled']) ? __('Yes', 'simula-security-telemetry-for-wordfence') : __('No or unavailable', 'simula-security-telemetry-for-wordfence'),
        ];
    }

    /** Converts bounded exporter result codes into display text. */
    private static function dashboard_exporter_result_label($result) {
        $labels = [
            'success' => __('Successful', 'simula-security-telemetry-for-wordfence'),
            'failed' => __('Failed; review exporter settings and status.', 'simula-security-telemetry-for-wordfence'),
            'not_run' => __('No exports yet.', 'simula-security-telemetry-for-wordfence'),
        ];

        return $labels[$result] ?? $labels['not_run'];
    }

    /** Formats the WordPress-only posture allowlist for display. */
    private static function dashboard_wordpress_rows($card) {
        $data = (array) ($card['data'] ?? []);
        if ($data === []) {
            return [__('Availability', 'simula-security-telemetry-for-wordfence') => __('Awaiting the next slow collector run.', 'simula-security-telemetry-for-wordfence')];
        }

        return [
            __('WordPress version', 'simula-security-telemetry-for-wordfence') => (string) ($data['wordpress_version'] ?? __('Unknown', 'simula-security-telemetry-for-wordfence')),
            __('Core update available', 'simula-security-telemetry-for-wordfence') => !empty($data['core_update_available']) ? __('Yes', 'simula-security-telemetry-for-wordfence') : __('No', 'simula-security-telemetry-for-wordfence'),
            __('Plugin updates', 'simula-security-telemetry-for-wordfence') => (string) ($data['plugin_update_available_total'] ?? 0),
            __('Theme updates', 'simula-security-telemetry-for-wordfence') => (string) ($data['theme_update_available_total'] ?? 0),
            __('Debug enabled', 'simula-security-telemetry-for-wordfence') => !empty($data['debug_enabled']) ? __('Yes', 'simula-security-telemetry-for-wordfence') : __('No', 'simula-security-telemetry-for-wordfence'),
        ];
    }

    /** Formats WordPress-side drift and IoC totals for display. */
    private static function dashboard_change_rows($card) {
        $data = (array) ($card['data'] ?? []);
        if ($data === []) {
            return [__('Availability', 'simula-security-telemetry-for-wordfence') => __('Awaiting the next slow collector run.', 'simula-security-telemetry-for-wordfence')];
        }

        return [
            __('Plugins added or removed', 'simula-security-telemetry-for-wordfence') => (string) ((int) ($data['plugins_added'] ?? 0) + (int) ($data['plugins_removed'] ?? 0)),
            __('Theme files modified', 'simula-security-telemetry-for-wordfence') => (string) ($data['theme_files_modified'] ?? 0),
            __('PHP files in uploads', 'simula-security-telemetry-for-wordfence') => (string) ($data['upload_php_files'] ?? 0),
            __('Executable files in uploads', 'simula-security-telemetry-for-wordfence') => (string) ($data['upload_executable_files'] ?? 0),
            __('Suspicious content signals', 'simula-security-telemetry-for-wordfence') => (string) ($data['suspicious_content'] ?? 0),
        ];
    }
}
