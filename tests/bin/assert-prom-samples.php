<?php
/**
 * Validates exact Prometheus sample lines.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tests/bin/assert-prom-samples.php <prom-file> <required-samples-file>\n");
    exit(2);
}

$prom_file = $argv[1];
$required_file = $argv[2];

if (!is_readable($prom_file)) {
    fwrite(STDERR, "Prometheus file is not readable: $prom_file\n");
    exit(1);
}

if (!is_readable($required_file)) {
    fwrite(STDERR, "Required sample list is not readable: $required_file\n");
    exit(1);
}

$content = file_get_contents($prom_file);
$samples = array_fill_keys(array_filter(array_map('trim', preg_split('/\R/', (string) $content))), true);
$required = file($required_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ((array) $required as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0) {
        continue;
    }

    if (empty($samples[$line])) {
        fwrite(STDERR, "Required sample missing: $line\n");
        exit(1);
    }
}

echo "Prometheus sample validation passed.\n";
