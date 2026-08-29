<?php

/**
 * Covers domain_reseller_registrar_GetTldPricing() against the real
 * provider response shape: dotted TLD keys, numeric (not "1yr") year keys,
 * and tld_features carrying addon flags rather than an eppcode flag.
 */

drr_test_reset();
\WHMCS\Database\Capsule::$currencyRow = (object) ['code' => 'USD'];

drr_test_queue_http([
    'http_code' => 200,
    'response' => json_encode([
        'status' => 'success',
        'data' => [
            'tlds' => [
                '.com' => [
                    'register' => ['1' => 10.99, '2' => 21.98],
                    'transfer' => ['1' => 10.99],
                    'renew' => ['1' => 12.99],
                ],
                '.net' => [
                    'register' => ['1' => 11.99],
                ],
                '.free' => [
                    // no positive register price -> must be skipped entirely
                    'register' => ['1' => 0],
                ],
            ],
            'tld_features' => [
                // real API reports addon enablement, not an eppcode flag
                '.com' => ['dnsmanagement' => true, 'emailforwarding' => true, 'idprotection' => true],
                '.net' => ['eppcode' => false],
            ],
            'currency' => ['id' => 1, 'code' => 'USD', 'prefix' => '$', 'suffix' => ''],
        ],
    ]),
]);

$results = domain_reseller_registrar_GetTldPricing(drr_test_base_params());

drr_assert(!isset($results['error']), 'GetTldPricing does not return an error for a well-formed response');
drr_assert_equals(2, count($results), 'the zero-priced TLD is skipped, leaving 2 imported items');

$byExtension = [];
foreach ($results as $item) {
    $byExtension[$item->extension] = $item;
}

drr_assert(isset($byExtension['com']), 'the leading dot is stripped from the TLD ("com", not ".com")');
$com = $byExtension['com'] ?? null;
if ($com) {
    drr_assert_equals(10.99, $com->registerPrice, 'com register price read from the numeric "1" year key');
    drr_assert_equals(12.99, $com->renewPrice, 'com renew price read correctly');
    drr_assert_equals(10.99, $com->transferPrice, 'com transfer price read correctly');
    drr_assert_equals(1, $com->minYears, 'com min years is 1');
    drr_assert_equals(2, $com->maxYears, 'com max years is 2');
    drr_assert_equals(null, $com->years, 'com has contiguous years 1-2, so setYears() is NOT called (WHMCS infers the range from min/max)');
    drr_assert_equals(true, $com->eppRequired, 'com has no eppcode override, so EPP defaults to required');
}

$net = $byExtension['net'] ?? null;
if ($net) {
    drr_assert_equals(1, $net->minYears, 'net min years is 1 (only one year of pricing)');
    drr_assert_equals(1, $net->maxYears, 'net max years is 1');
    drr_assert_equals(null, $net->years, 'a single available year does not call setYears()');
    drr_assert_equals(false, $net->eppRequired, 'net explicitly sets eppcode:false, which overrides the default');
}

echo "GetTldPricingTest done.\n";
