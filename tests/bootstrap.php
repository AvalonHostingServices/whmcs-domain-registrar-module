<?php

/**
 * Test bootstrap: minimal WHMCS stand-ins so the module's plain PHP files can
 * be loaded and exercised standalone, without a real WHMCS installation.
 *
 * This directory is never packaged into a release — .github/workflows/release.yml
 * zips only modules/registrars/domain_reseller_registrar/.
 */

error_reporting(E_ALL);

define('WHMCS', true);

if (!function_exists('add_hook')) {
    // hooks.php calls this at include time to register the admin-CSS hook;
    // tests don't need WHMCS's real hook dispatcher.
    function add_hook($hookName, $priority, $callback) {
    }
}

if (!function_exists('logActivity')) {
    function logActivity($message) {
        $GLOBALS['__drr_test_activity_log'][] = $message;
    }
}

if (!function_exists('logModuleCall')) {
    function logModuleCall(...$args) {
        $GLOBALS['__drr_test_module_log'][] = $args;
    }
}

/**
 * Queue-based stand-in for every network call the module makes (provider
 * API, GlitchTip error reporting, GitHub release checks/downloads) — all of
 * it goes through drr_http_request(). Push expected results with
 * drr_test_queue_http() before calling code under test, then inspect
 * $GLOBALS['__drr_http_calls'] to assert on what was actually requested.
 */
function drr_http_request(array $curlOptions) {
    $GLOBALS['__drr_http_calls'][] = $curlOptions;
    if (empty($GLOBALS['__drr_http_queue'])) {
        // Most commonly hit by drr_report_error() firing during a test that
        // didn't bother stubbing a response for it — harmless default.
        return ['response' => false, 'error' => 'unstubbed drr_http_request call', 'errno' => 0, 'http_code' => 0];
    }
    return array_shift($GLOBALS['__drr_http_queue']);
}

function drr_test_queue_http(array $result) {
    $GLOBALS['__drr_http_queue'][] = array_merge(
        ['response' => false, 'error' => '', 'errno' => 0, 'http_code' => 0],
        $result
    );
}

function drr_test_reset() {
    $GLOBALS['__drr_http_queue'] = [];
    $GLOBALS['__drr_http_calls'] = [];
    $GLOBALS['__drr_test_activity_log'] = [];
    $GLOBALS['__drr_test_module_log'] = [];
    \WHMCS\Database\Capsule::$currencyRow = null;
}

$GLOBALS['__drr_test_pass'] = 0;
$GLOBALS['__drr_test_fail'] = 0;

function drr_assert($condition, $message) {
    if ($condition) {
        $GLOBALS['__drr_test_pass']++;
        return;
    }
    $GLOBALS['__drr_test_fail']++;
    echo "FAIL: {$message}\n";
}

function drr_assert_equals($expected, $actual, $message) {
    $ok = $expected === $actual;
    if (!$ok) {
        $message .= ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
    drr_assert($ok, $message);
}

require __DIR__ . '/stubs/whmcs-classes.php';

$moduleDir = dirname(__DIR__) . '/modules/registrars/domain_reseller_registrar';
require_once $moduleDir . '/domain_reseller_registrar.php';
require_once $moduleDir . '/hooks.php';

/** Base $params every reseller_callAPI/domain_reseller_registrar_* test call needs. */
function drr_test_base_params(array $overrides = []) {
    return array_merge([
        'customApiEndpoint' => 'https://example.test/api.php',
        'customApiKey' => 'test-key',
        'moduleLog' => false,
        'language' => 'english',
    ], $overrides);
}
