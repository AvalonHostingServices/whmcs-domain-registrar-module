<?php

/**
 * Dependency-free test runner: no PHPUnit/composer, matching this project's
 * "no build step" design. Run with: php tests/run.php
 */

require __DIR__ . '/bootstrap.php';

$testFiles = [
    'LocaleTest.php',
    'ResellerCallApiTest.php',
    'GetTldPricingTest.php',
    'SelfUpdateTest.php',
];

foreach ($testFiles as $file) {
    require __DIR__ . '/' . $file;
}

$pass = $GLOBALS['__drr_test_pass'];
$fail = $GLOBALS['__drr_test_fail'];

echo "\n{$pass} passed, {$fail} failed.\n";

exit($fail > 0 ? 1 : 0);
