#!/usr/bin/env php
<?php
/**
 * Runs unit tests and reports line coverage when Xdebug or PCOV is available.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

$root = dirname(__DIR__, 2);
$runtime_dir = $root . '/tests/runtime/coverage';
$include_dir = realpath($root . '/includes');
$allow_missing_driver = in_array('--allow-missing-driver', $argv, true);
$minimum = 0.0;

foreach ($argv as $arg) {
    if (strpos($arg, '--min=') === 0) {
        $minimum = max(0.0, (float) substr($arg, 6));
    }
}

if (!is_dir($runtime_dir) && !mkdir($runtime_dir, 0775, true) && !is_dir($runtime_dir)) {
    fwrite(STDERR, "Could not create coverage runtime directory: $runtime_dir\n");
    exit(1);
}

$driver = null;

if (function_exists('xdebug_start_code_coverage')) {
    $driver = 'xdebug';
    $flags = 0;
    if (defined('XDEBUG_CC_UNUSED')) {
        $flags |= XDEBUG_CC_UNUSED;
    }
    if (defined('XDEBUG_CC_DEAD_CODE')) {
        $flags |= XDEBUG_CC_DEAD_CODE;
    }
    xdebug_start_code_coverage($flags);
} elseif (function_exists('pcov_start')) {
    $driver = 'pcov';
    pcov_start();
}

if ($driver === null) {
    $message = "No PHP line-coverage driver is available. Install or enable Xdebug or PCOV, then run `php tests/bin/coverage.php`.\n";
    if ($allow_missing_driver) {
        echo $message;
        exit(0);
    }

    fwrite(STDERR, $message);
    exit(2);
}

require_once $root . '/tests/bin/run-unit.php';

$test_exit = sstfw_run_unit_tests(true);

if ($driver === 'xdebug') {
    $coverage = xdebug_get_code_coverage();
    xdebug_stop_code_coverage(false);
} else {
    $coverage = pcov_collect();
    pcov_stop();
}

$summary = sstfw_coverage_summary($coverage, $include_dir);
$summary['driver'] = $driver;
$summary['tests_exit_code'] = $test_exit;
$summary['threshold'] = $minimum;

$json_file = $runtime_dir . '/coverage-summary.json';
file_put_contents($json_file, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

printf(
    "Line coverage: %.2f%% (%d/%d executable lines) using %s\n",
    $summary['percentage'],
    $summary['covered_lines'],
    $summary['executable_lines'],
    $driver
);
echo "Coverage summary written to $json_file\n";

if ($test_exit !== 0) {
    exit($test_exit);
}

if ($minimum > 0.0 && $summary['percentage'] < $minimum) {
    fwrite(STDERR, sprintf("Coverage is below %.2f%% threshold.\n", $summary['threshold']));
    exit(1);
}

exit(0);

function sstfw_coverage_summary($coverage, $include_dir) {
    $files = [];
    $covered_lines = 0;
    $executable_lines = 0;

    foreach ((array) $coverage as $file => $lines) {
        $real_file = realpath((string) $file);
        if ($real_file === false || $include_dir === false || strpos($real_file, $include_dir . DIRECTORY_SEPARATOR) !== 0) {
            continue;
        }

        $file_covered = 0;
        $file_executable = 0;

        foreach ((array) $lines as $line => $hits) {
            if ((int) $hits === -2) {
                continue;
            }

            $file_executable++;
            if ((int) $hits > 0) {
                $file_covered++;
            }
        }

        if ($file_executable === 0) {
            continue;
        }

        $covered_lines += $file_covered;
        $executable_lines += $file_executable;
        $files[$real_file] = [
            'covered_lines' => $file_covered,
            'executable_lines' => $file_executable,
            'percentage' => $file_executable > 0 ? round(($file_covered / $file_executable) * 100, 2) : 0.0,
        ];
    }

    ksort($files);

    return [
        'covered_lines' => $covered_lines,
        'executable_lines' => $executable_lines,
        'percentage' => $executable_lines > 0 ? round(($covered_lines / $executable_lines) * 100, 2) : 0.0,
        'files' => $files,
    ];
}
