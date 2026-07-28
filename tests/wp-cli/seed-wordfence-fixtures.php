<?php
/**
 * Seeds synthetic Wordfence-like tables for fixture-based smoke tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();
$now = time();

$hits_table = $wpdb->prefix . 'wfHits';
$issues_table = $wpdb->prefix . 'wfIssues';
$blocked_table = $wpdb->prefix . 'wfBlockedIPLog';
$secrets_table = $wpdb->prefix . 'wfls_2fa_secrets';

$wpdb->query(
    "CREATE TABLE IF NOT EXISTS `$hits_table` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `attackLogTime` bigint unsigned NOT NULL,
        `ctime` bigint unsigned NOT NULL,
        `IP` varchar(45) DEFAULT NULL,
        `ctry` varchar(8) DEFAULT NULL,
        `statusCode` int DEFAULT NULL,
        `action` varchar(255) DEFAULT NULL,
        `actionDescription` text DEFAULT NULL,
        `method` varchar(16) DEFAULT NULL,
        `URL` text DEFAULT NULL,
        `referer` text DEFAULT NULL,
        `UA` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `attackLogTime` (`attackLogTime`)
    ) $charset_collate"
);

$wpdb->query(
    "CREATE TABLE IF NOT EXISTS `$issues_table` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `severity` varchar(32) DEFAULT NULL,
        `type` varchar(128) DEFAULT NULL,
        `shortMsg` text DEFAULT NULL,
        `longMsg` text DEFAULT NULL,
        `lastUpdated` bigint unsigned DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) $charset_collate"
);

$wpdb->query(
    "CREATE TABLE IF NOT EXISTS `$blocked_table` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `IP` varchar(45) DEFAULT NULL,
        `username` varchar(191) DEFAULT NULL,
        `expiration` bigint unsigned DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) $charset_collate"
);

$wpdb->query(
    "CREATE TABLE IF NOT EXISTS `$secrets_table` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `user_id` bigint unsigned NOT NULL,
        `secret` varchar(191) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) $charset_collate"
);

$wpdb->query("TRUNCATE TABLE `$hits_table`");
$wpdb->query("TRUNCATE TABLE `$issues_table`");
$wpdb->query("TRUNCATE TABLE `$blocked_table`");
$wpdb->query("TRUNCATE TABLE `$secrets_table`");

$hit_rows = [
    [$now - 60, '203.0.113.10', 'US', 403, 'blocked:waf', 'Blocked by firewall rule', 'POST', '/wp-login.php?bad=1', 'https://example.test/ref', 'Fixture user agent'],
    [$now - 300, '203.0.113.11', 'US', 403, 'loginfail', 'Invalid username', 'POST', '/wp-login.php', '', 'Fixture user agent'],
    [$now - 900, '198.51.100.20', 'DE', 503, 'blocked:rate-limit', 'Rate limit exceeded', 'GET', '/xmlrpc.php', '', 'Fixture user agent'],
    [$now - 1800, '198.51.100.21', 'DE', 403, 'blocked:waf', 'XML-RPC blocked', 'POST', '/xmlrpc.php', '', 'Fixture user agent'],
    [$now - 90000, '192.0.2.42', 'FR', 403, 'blocked:waf', 'Older blocked request', 'GET', '/old-probe', '', 'Fixture user agent'],
];

foreach ($hit_rows as $row) {
    $wpdb->insert(
        $hits_table,
        [
            'attackLogTime' => $row[0],
            'ctime' => $row[0],
            'IP' => $row[1],
            'ctry' => $row[2],
            'statusCode' => $row[3],
            'action' => $row[4],
            'actionDescription' => $row[5],
            'method' => $row[6],
            'URL' => $row[7],
            'referer' => $row[8],
            'UA' => $row[9],
        ],
        ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
    );
}

$issue_rows = [
    ['critical', 'malware', 'Malware signature detected', 'Malicious webshell marker found in fixture file.', $now - 120],
    ['high', 'vulnerability', 'Plugin vulnerability update available', 'A plugin security update is available.', $now - 180],
    ['medium', 'file', 'File contents changed', 'A changed file was detected by the fixture scan.', $now - 240],
];

foreach ($issue_rows as $row) {
    $wpdb->insert(
        $issues_table,
        [
            'severity' => $row[0],
            'type' => $row[1],
            'shortMsg' => $row[2],
            'longMsg' => $row[3],
            'lastUpdated' => $row[4],
        ],
        ['%s', '%s', '%s', '%s', '%d']
    );
}

$admin = get_user_by('login', 'admin');
if ($admin instanceof WP_User) {
    $wpdb->insert(
        $secrets_table,
        [
            'user_id' => (int) $admin->ID,
            'secret' => 'fixture-secret',
        ],
        ['%d', '%s']
    );
}

$wpdb->insert(
    $blocked_table,
    [
        'IP' => '203.0.113.10',
        'username' => 'fixture-user',
        'expiration' => $now + 3600,
    ],
    ['%s', '%s', '%d']
);

if ($wpdb->last_error !== '') {
    WP_CLI::error($wpdb->last_error);
}

WP_CLI::success('Seeded synthetic Wordfence fixture data.');
