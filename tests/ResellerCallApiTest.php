<?php

/**
 * Covers reseller_callAPI()'s response-shape branching and the bugs fixed
 * against the real provider API contract: the Sync/TransferSync flat
 * envelope, and retry-on-connect-failure-only behavior.
 */

drr_test_reset();

// --- not configured: fails fast, makes no network call at all ---
$response = reseller_callAPI(drr_test_base_params(['customApiEndpoint' => '']), 'RenewDomain', []);
drr_assert_equals(['error' => 'Registrar module is not configured. Please set the API Endpoint and API Key.'], $response, 'missing endpoint short-circuits');
drr_assert_equals(0, count($GLOBALS['__drr_http_calls']), 'no HTTP call made when unconfigured');

// --- success: enveloped response, returns the data payload ---
drr_test_reset();
drr_test_queue_http(['response' => json_encode(['status' => 'success', 'data' => ['domainid' => 987]]), 'http_code' => 200]);
$response = reseller_callAPI(drr_test_base_params(), 'RenewDomain', ['domainid' => 987]);
drr_assert_equals(['domainid' => 987], $response, 'success envelope returns data');

// --- documented business error (non-2xx + well-formed status:error/message): NOT reported ---
// Per the real provider docs every business error is non-2xx, so HTTP status
// alone can't distinguish "expected business error" from "genuine failure" —
// only the well-formed {status:error,message} shape can.
drr_test_reset();
drr_test_queue_http(['response' => json_encode(['status' => 'error', 'message' => 'Domain is in redemption period']), 'http_code' => 400]);
$response = reseller_callAPI(drr_test_base_params(), 'RenewDomain', []);
drr_assert_equals('Domain is in redemption period', $response['error'] ?? null, 'business error message passed through');
drr_assert_equals(1, count($GLOBALS['__drr_http_calls']), 'a well-formed documented business error is not reported to GlitchTip');

// --- malformed non-2xx (not a well-formed documented error): IS reported ---
drr_test_reset();
drr_test_queue_http(['response' => json_encode(['unexpected' => 'shape']), 'http_code' => 502]);
$response = reseller_callAPI(drr_test_base_params(), 'RenewDomain', []);
drr_assert_equals(2, count($GLOBALS['__drr_http_calls']), 'a non-2xx response that is not a documented error shape IS reported');

// --- error_code passthrough when the provider sends one ---
drr_test_reset();
drr_test_queue_http(['response' => json_encode(['status' => 'error', 'message' => 'Rate limited', 'error_code' => 'RateLimited_Error']), 'http_code' => 429]);
$response = reseller_callAPI(drr_test_base_params(), 'RenewDomain', []);
drr_assert_equals('RateLimited_Error', $response['error_code'] ?? null, 'error_code is passed through when present');

// --- cURL failure, non-retryable errno: one attempt only, reported ---
drr_test_reset();
drr_test_queue_http(['response' => false, 'error' => 'Operation timed out', 'errno' => CURLE_OPERATION_TIMEDOUT]);
$response = reseller_callAPI(drr_test_base_params(), 'RenewDomain', []);
drr_assert(strpos($response['error'] ?? '', 'Operation timed out') !== false, 'timeout surfaces as cURL error');
drr_assert_equals(2, count($GLOBALS['__drr_http_calls']), 'non-retryable errno: 1 API attempt + 1 GlitchTip report call, no retry of the API call');

// --- cURL failure, retryable errno: retried once, then succeeds ---
drr_test_reset();
drr_test_queue_http(['response' => false, 'error' => 'Could not resolve host', 'errno' => CURLE_COULDNT_RESOLVE_HOST]);
drr_test_queue_http(['response' => json_encode(['status' => 'success', 'data' => ['ok' => true]]), 'http_code' => 200]);
$response = reseller_callAPI(drr_test_base_params(), 'RenewDomain', []);
drr_assert_equals(['ok' => true], $response, 'retryable connect failure recovers on the retry');
drr_assert_equals(2, count($GLOBALS['__drr_http_calls']), 'exactly one retry attempted');

// --- Sync: flat envelope, success (empty error string) ---
drr_test_reset();
drr_test_queue_http(['response' => json_encode(['active' => true, 'cancelled' => false, 'transferredAway' => false, 'expirydate' => '2027-01-01', 'error' => '']), 'http_code' => 200]);
$response = reseller_callAPI(drr_test_base_params(), 'Sync', ['domainid' => 1]);
drr_assert_equals('', $response['error'] ?? null, 'flat Sync success has an empty error string, not an error');
drr_assert_equals(true, $response['active'] ?? null, 'flat Sync success carries through active');

// --- Sync: flat envelope, failure (non-empty error string, still HTTP 200) ---
drr_test_reset();
drr_test_queue_http(['response' => json_encode(['active' => false, 'cancelled' => false, 'transferredAway' => false, 'expirydate' => '', 'error' => 'Domain not found in System.']), 'http_code' => 200]);
$response = reseller_callAPI(drr_test_base_params(), 'Sync', ['domainid' => 1]);
drr_assert_equals('Domain not found in System.', $response['error'] ?? null, 'flat Sync failure surfaces the error string');

// --- domain_reseller_registrar_Sync: must not treat a present-but-empty `error` as a failure ---
// (This is the bug the real provider docs surfaced: `isset($response['error'])` is true even when empty.)
drr_test_reset();
drr_test_queue_http(['response' => json_encode(['active' => true, 'cancelled' => false, 'transferredAway' => false, 'expirydate' => '2027-01-01', 'error' => '']), 'http_code' => 200]);
$result = domain_reseller_registrar_Sync(drr_test_base_params(['domainid' => 1, 'domain' => 'example.com', 'sld' => 'example', 'tld' => 'com']));
drr_assert(!isset($result['error']) || $result['error'] === '', 'Sync wrapper does not report an error on a clean sync');
drr_assert_equals(true, $result['active'] ?? null, 'Sync wrapper surfaces active=true');

drr_test_reset();
drr_test_queue_http(['response' => json_encode(['active' => false, 'cancelled' => false, 'transferredAway' => false, 'expirydate' => '', 'error' => 'Domain not found in System.']), 'http_code' => 200]);
$result = domain_reseller_registrar_Sync(drr_test_base_params(['domainid' => 1, 'domain' => 'example.com', 'sld' => 'example', 'tld' => 'com']));
drr_assert_equals('Domain not found in System.', $result['error'] ?? null, 'Sync wrapper surfaces a real failure');

// --- domain_reseller_registrar_TransferSync: same present-but-empty `error` fix ---
drr_test_reset();
drr_test_queue_http(['response' => json_encode(['completed' => false, 'expirydate' => '', 'failed' => false, 'reason' => '', 'error' => '']), 'http_code' => 200]);
$result = domain_reseller_registrar_TransferSync(drr_test_base_params(['domainid' => 1, 'domain' => 'example.com', 'sld' => 'example', 'tld' => 'com']));
drr_assert_equals(false, $result['failed'] ?? null, 'TransferSync wrapper does not report failed on a clean check-in');

echo "ResellerCallApiTest done.\n";
