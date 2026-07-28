<?php
/**
 * Minimal Prometheus textfile validation for fixture smoke tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tests/bin/assert-prom.php <prom-file> <required-metrics-file>\n");
    exit(2);
}

$prom_file = $argv[1];
$required_file = $argv[2];

if (!is_readable($prom_file)) {
    fwrite(STDERR, "Prometheus file is not readable: $prom_file\n");
    exit(1);
}

if (!is_readable($required_file)) {
    fwrite(STDERR, "Required metric list is not readable: $required_file\n");
    exit(1);
}

$content = file_get_contents($prom_file);
if ($content === false || trim($content) === '') {
    fwrite(STDERR, "Prometheus file is empty: $prom_file\n");
    exit(1);
}

$lines = preg_split('/\R/', $content);
$families = [];

foreach ($lines as $line_number => $line) {
    $line = trim($line);

    if ($line === '') {
        continue;
    }

    if (strpos($line, '# HELP ') === 0 || strpos($line, '# TYPE ') === 0) {
        $parts = preg_split('/\s+/', $line);
        if (!isset($parts[2]) || !preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:]*$/', $parts[2])) {
            fwrite(STDERR, 'Invalid metadata line ' . ($line_number + 1) . ": $line\n");
            exit(1);
        }
        $families[$parts[2]] = true;
        continue;
    }

    if (!preg_match('/^([a-zA-Z_:][a-zA-Z0-9_:]*)(\{[^}]*\})?\s+[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?$/', $line, $matches)) {
        fwrite(STDERR, 'Invalid sample line ' . ($line_number + 1) . ": $line\n");
        exit(1);
    }

    $families[$matches[1]] = true;
}

$required = file($required_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ((array) $required as $metric) {
    $metric = trim($metric);
    if ($metric === '' || strpos($metric, '#') === 0) {
        continue;
    }

    if (empty($families[$metric])) {
        fwrite(STDERR, "Required metric family missing: $metric\n");
        exit(1);
    }
}

echo "Prometheus output validation passed.\n";
