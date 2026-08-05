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
$logins_table = $wpdb->prefix . 'wfLogins';
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
    "CREATE TABLE IF NOT EXISTS `$logins_table` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `hitID` int DEFAULT NULL,
        `ctime` double(17,6) unsigned NOT NULL,
        `fail` tinyint unsigned NOT NULL,
        `action` varchar(40) NOT NULL,
        `username` varchar(255) NOT NULL,
        `userID` bigint unsigned NOT NULL,
        `IP` varchar(45) DEFAULT NULL,
        `UA` text,
        PRIMARY KEY (`id`),
        KEY `ctime` (`ctime`)
    ) $charset_collate"
);

$wpdb->query(
    "CREATE TABLE IF NOT EXISTS `$blocked_table` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `IP` varchar(45) DEFAULT NULL,
        `countryCode` varchar(8) DEFAULT NULL,
        `unixday` int unsigned DEFAULT NULL,
        `blockType` varchar(32) DEFAULT NULL,
        `blockCount` int unsigned DEFAULT NULL,
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
$wpdb->query("TRUNCATE TABLE `$logins_table`");
$wpdb->query("TRUNCATE TABLE `$issues_table`");
$wpdb->query("TRUNCATE TABLE `$blocked_table`");
$wpdb->query("TRUNCATE TABLE `$secrets_table`");

$hit_rows = [
    [$now - 60, '203.0.113.10', 'US', 403, 'blocked:waf', 'Blocked by firewall rule', 'POST', '/wp-login.php?bad=1', 'https://example.test/ref', 'Fixture user agent'],
    [$now - 300, '203.0.113.11', 'US', 403, 'login', 'Invalid username', 'POST', '/wp-login.php', '', 'Fixture user agent'],
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

$login_rows = [
    [$now - 120, 1, 'loginFailValidUsername', 'admin', 0, '203.0.113.11'],
    [$now - 240, 1, 'loginFailInvalidUsername', 'missing-user', 0, '203.0.113.12'],
    [$now - 4000, 1, 'loginFailValidUsername', 'editor', 0, '203.0.113.13'],
    [$now - (2 * DAY_IN_SECONDS), 1, 'loginFailInvalidUsername', 'probe', 0, '198.51.100.13'],
    [$now - 180, 0, 'loginOK', 'admin', 1, '203.0.113.14'],
];

foreach ($login_rows as $row) {
    $wpdb->insert(
        $logins_table,
        [
            'ctime' => $row[0],
            'fail' => $row[1],
            'action' => $row[2],
            'username' => $row[3],
            'userID' => $row[4],
            'IP' => $row[5],
            'UA' => 'Fixture user agent',
        ],
        ['%f', '%d', '%s', '%s', '%d', '%s', '%s']
    );
}

for ($i = 0; $i < 84; $i++) {
    $wpdb->insert(
        $hits_table,
        [
            'attackLogTime' => $now - 120 - $i,
            'ctime' => $now - 120 - $i,
            'IP' => '203.0.113.50',
            'ctry' => 'US',
            'statusCode' => 403,
            'action' => 'blocked:waf',
            'actionDescription' => 'Bulk fixture blocked request',
            'method' => 'GET',
            'URL' => '/fixture-current-block',
            'referer' => '',
            'UA' => 'Fixture user agent',
        ],
        ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
    );
}

for ($i = 0; $i < 306; $i++) {
    $timestamp = $now - (2 * DAY_IN_SECONDS) - $i;
    $wpdb->insert(
        $hits_table,
        [
            'attackLogTime' => $timestamp,
            'ctime' => $timestamp,
            'IP' => '198.51.100.80',
            'ctry' => 'DE',
            'statusCode' => 403,
            'action' => 'blocked:waf',
            'actionDescription' => 'Bulk fixture older blocked request',
            'method' => 'GET',
            'URL' => '/fixture-week-block',
            'referer' => '',
            'UA' => 'Fixture user agent',
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

$current_day = (int) floor(($now - 60) / DAY_IN_SECONDS);
$week_day    = (int) floor(($now - (2 * DAY_IN_SECONDS)) / DAY_IN_SECONDS);
$month_day   = (int) floor(($now - (8 * DAY_IN_SECONDS)) / DAY_IN_SECONDS);
$aggregate_block_rows = [
    ['203.0.113.60', 'US', $current_day, 'waf', 52],
    ['203.0.113.61', 'US', $current_day, 'brute', 451],
    ['198.51.100.60', 'DE', $week_day, 'badpost', 69],
    ['198.51.100.61', 'DE', $week_day, 'throttle', 770],
    ['192.0.2.60', 'FR', $month_day, 'advanced', 506],
    ['192.0.2.61', 'FR', $month_day, 'brute', 4006],
];

foreach ($aggregate_block_rows as $row) {
    $wpdb->insert(
        $blocked_table,
        [
            'IP' => $row[0],
            'countryCode' => $row[1],
            'unixday' => $row[2],
            'blockType' => $row[3],
            'blockCount' => $row[4],
            'expiration' => 0,
        ],
        ['%s', '%s', '%d', '%s', '%d', '%d']
    );
}

if ($wpdb->last_error !== '') {
    WP_CLI::error($wpdb->last_error);
}

WP_CLI::success('Seeded synthetic Wordfence fixture data.');
