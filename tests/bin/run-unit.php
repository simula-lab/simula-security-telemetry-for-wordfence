#!/usr/bin/env php
<?php
/**
 * Minimal unit test runner for dependency-free helper tests.
 *
 * @package Simula_Security_Telemetry_for_Wordfence
 */

function sstfw_run_unit_tests($quiet = false) {
    $root = dirname(__DIR__, 2);

    require_once $root . '/tests/unit/bootstrap.php';

    $test_files = glob($root . '/tests/unit/*Test.php') ?: [];
    sort($test_files);

    $total = 0;
    $failed = 0;

    foreach ($test_files as $test_file) {
        $tests = require $test_file;

        foreach ((array) $tests as $name => $test) {
            $total++;

            try {
                $test();
                if (!$quiet) {
                    echo ".";
                }
            } catch (Throwable $exception) {
                $failed++;
                echo "\nFAIL $name: " . $exception->getMessage() . "\n";
            }
        }
    }

    if (!$quiet) {
        echo "\n";
    }

    if ($failed > 0) {
        echo "Unit tests failed: $failed of $total\n";
        return 1;
    }

    echo "Unit tests passed: $total\n";
    return 0;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(sstfw_run_unit_tests(false));
}
