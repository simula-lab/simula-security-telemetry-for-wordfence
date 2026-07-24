#!/usr/bin/env php
<?php
/**
 * Validates Prometheus output against required line patterns.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tests/bin/assert-prom-patterns.php <prom-file> <required-patterns-file>\n");
    exit(2);
}

$prom_file = $argv[1];
$required_file = $argv[2];

if (!is_readable($prom_file)) {
    fwrite(STDERR, "Prometheus file is not readable: $prom_file\n");
    exit(1);
}

if (!is_readable($required_file)) {
    fwrite(STDERR, "Required pattern list is not readable: $required_file\n");
    exit(1);
}

$content = file_get_contents($prom_file);
if ($content === false || trim($content) === '') {
    fwrite(STDERR, "Prometheus file is empty: $prom_file\n");
    exit(1);
}

$required = file($required_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ((array) $required as $pattern) {
    $pattern = trim($pattern);
    if ($pattern === '' || strpos($pattern, '#') === 0) {
        continue;
    }

    if (@preg_match('~' . $pattern . '~m', '') === false) {
        fwrite(STDERR, "Invalid required pattern: $pattern\n");
        exit(1);
    }

    if (!preg_match('~' . $pattern . '~m', $content)) {
        fwrite(STDERR, "Required pattern missing: $pattern\n");
        exit(1);
    }
}

echo "Prometheus pattern validation passed.\n";
