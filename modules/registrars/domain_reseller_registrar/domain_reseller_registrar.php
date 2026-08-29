<?php

if (!defined("WHMCS")) die("This file cannot be accessed directly");

if (!defined('DRR_VERSION')) {
    define('DRR_VERSION', '2.3.0');
}

// GlitchTip project DSN for this module. Fixed and always-on: lets Avalon Hosting
// Services see transport-level failures (cURL errors, malformed API responses,
// non-2xx provider responses) across every install of this module and fix them
// centrally. Never fed request payloads, contact details, or API credentials —
// only technical failure context. See drr_report_error() below.
if (!defined('DRR_GLITCHTIP_DSN')) {
    define('DRR_GLITCHTIP_DSN', 'https://f72aead7349b4eaa9e67073af9f12595@pm.avalonhosting.services/7');
}

// Self-update (see hooks.php drr_check_update()) always pulls from this
// repository's GitHub Releases — a separate, independently-versioned
// distribution channel from the reseller's own API. The API is never
// trusted to supply update code.
if (!defined('DRR_GITHUB_REPO')) {
    define('DRR_GITHUB_REPO', 'AvalonHostingServices/whmcs-domain-registrar-module');
}

use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Results\ResultsList;
use WHMCS\Database\Capsule;

function domain_reseller_registrar_MetaData() {
    return [
        'DisplayName' => 'Avalon Hosting Services',
        'APIVersion' => DRR_VERSION,
        'Description' => 'This Registrar allows you to offer a wide variety of TLD straight from your Provider System.',
    ];
}

if (!function_exists('drr_http_request')) {
    /**
     * Thin, mockable wrapper around a single cURL request. Exists as one seam
     * so every network call in this module (provider API, GlitchTip
     * reporting, GitHub release checks/downloads) shares one implementation,
     * and so tests can stub it out without touching real sockets — see
     * tests/bootstrap.php.
     *
     * @param array $curlOptions CURLOPT_* => value pairs, applied via curl_setopt_array.
     * @return array{response: string|false, error: string, errno: int, http_code: int}
     */
    function drr_http_request(array $curlOptions) {
        $ch = curl_init();
        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'response' => $response,
            'error' => $error,
            'errno' => $errno,
            'http_code' => $httpCode,
        ];
    }
}

/**
 * Reports a technical failure to the module's GlitchTip project using the
 * Sentry "store" protocol (v7). Never includes request payloads, contact
 * details, or API credentials — only the action name and diagnostic context
 * explicitly passed in by the caller. Fails silently: a reporting problem
 * must never surface to, or interrupt, the WHMCS admin/customer.
 *
 * @param string $action  The registrar action or lifecycle event that failed.
 * @param string $message Short human-readable description of the failure.
 * @param array  $context Optional extra diagnostic fields (e.g. http_code).
 */
function drr_report_error($action, $message, array $context = []) {
    try {
        $dsn = parse_url(DRR_GLITCHTIP_DSN);
        if (!$dsn || empty($dsn['host']) || empty($dsn['user']) || empty($dsn['path'])) {
            return;
        }

        $publicKey = $dsn['user'];
        $projectId = ltrim($dsn['path'], '/');
        $port = isset($dsn['port']) ? ':' . $dsn['port'] : '';
        $storeUrl = $dsn['scheme'] . '://' . $dsn['host'] . $port . '/api/' . $projectId . '/store/';

        $event = [
            'event_id' => bin2hex(random_bytes(16)),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => 'error',
            'logger' => 'domain_reseller_registrar',
            'platform' => 'php',
            'release' => DRR_VERSION,
            'server_name' => $_SERVER['HTTP_HOST'] ?? php_uname('n'),
            'message' => $action . ': ' . $message,
            'tags' => [
                'action' => $action,
                'module_version' => DRR_VERSION,
                'php_version' => PHP_VERSION,
            ],
            'extra' => $context,
        ];

        drr_http_request([
            CURLOPT_URL => $storeUrl,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Sentry-Auth: Sentry sentry_version=7, sentry_key=' . $publicKey
                    . ', sentry_client=whmcs-domain-reseller-registrar/' . DRR_VERSION,
            ],
            CURLOPT_POSTFIELDS => json_encode($event),
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
    } catch (\Throwable $e) {
        // Error reporting must never itself break a registrar call.
    }
}

/**
 * Strips WHMCS's own module-config keys (including the API credentials) out
 * of a params array before it is forwarded to the provider API or written to
 * the WHMCS module log, so the API key never appears twice in a request body
 * or in plaintext inside Module Log entries.
 */
function drr_strip_config_params(array $params) {
    return array_diff_key($params, array_flip(['customApiEndpoint', 'customApiKey', 'moduleLog']));
}

function domain_reseller_registrar_getConfigArray() {
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Avalon Hosting Services',
        ],
        'customApiEndpoint' => [
            'FriendlyName' => 'API Endpoint',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
            'Description' => 'The URL to Domain Reseller Addon Module\'s API.',
        ],
        'customApiKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'password',
            'Size' => '64',
            'Default' => '',
            'Description' => 'The API Key generated for the reseller client in the Domain Reseller Area.',
        ],
        'moduleLog' => [
            'FriendlyName' => 'Enable Module Log',
            'Type' => 'yesno',
            'Description' => 'Check if you want to enable module log.',
        ],
        'autoUpdate' => [
            'FriendlyName' => 'Automatic Updates',
            'Type' => 'yesno',
            'Default' => 'on',
            'Description' => 'Automatically install new module versions published on GitHub (checked once daily). Uncheck to manage updates manually.',
        ],
    ];
}

/**
 * Best-effort locale detection for the current WHMCS request. WHMCS does not
 * document a guaranteed key for this in registrar-module $params, so this
 * checks every source that might carry it and falls back to English. Used to
 * (a) tell the remote API what locale to localize its `message` text in, and
 * (b) pick among this module's own small set of hardcoded fallback strings.
 */
function drr_current_locale(array $params) {
    $candidate = $params['language'] ?? $_SESSION['Language'] ?? $_SESSION['adminlang'] ?? 'english';
    $candidate = strtolower(trim((string) $candidate));
    return $candidate !== '' ? $candidate : 'english';
}

/**
 * Translates one of this module's own hardcoded fallback strings (transport
 * errors, config errors). Only covers strings this module generates itself —
 * everything from the provider API (`message`) is expected to already be
 * localized upstream from the `locale` field sent on every request; see
 * API.md.
 *
 * Currently English-only: add a locale key per string below as translations
 * become available. Unknown locales fall back to English.
 */
function drr_t($key, $locale, array $vars = []) {
    static $strings = [
        'not_configured' => [
            'english' => 'Registrar module is not configured. Please set the API Endpoint and API Key.',
        ],
        'curl_error' => [
            'english' => 'cURL Error: :detail',
        ],
        'invalid_json' => [
            'english' => 'Invalid JSON response from API: :detail',
        ],
        'unknown_api_error' => [
            'english' => 'Unknown API error.',
        ],
        'no_availability_status' => [
            'english' => 'Provider did not return an availability status.',
        ],
        'no_tld_pricing' => [
            'english' => 'No TLD pricing data available from provider.',
        ],
        'no_currency_info' => [
            'english' => 'No currency information available.',
        ],
    ];

    $entry = $strings[$key] ?? null;
    if ($entry === null) {
        return $key;
    }

    $text = $entry[strtolower((string) $locale)] ?? $entry['english'];

    foreach ($vars as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }

    return $text;
}

// Actions whose response is a flat JSON object (no status/data envelope) —
// success and failure both arrive as HTTP 200. Documented in API.md; the
// caller distinguishes outcome via its own `error`/`failed` field.
const DRR_FLAT_RESPONSE_ACTIONS = ['Sync', 'TransferSync'];

function reseller_callAPI($params, $action, $apiParams = []) {
    $customApiEndpoint = $params['customApiEndpoint'];
    $customApiKey = $params['customApiKey'];
    $moduleLog = $params['moduleLog'];
    $registrarName = 'domain_reseller_registrar';
    $locale = drr_current_locale($params);

    if (empty($customApiEndpoint) || empty($customApiKey)) {
        return ['error' => drr_t('not_configured', $locale)];
    }

    $payload = json_encode([
        'api_key' => $customApiKey,
        'action' => $action,
        'locale' => $locale,
        'params' => $apiParams,
    ]);

    ob_start();

    // Retried once, and only when the request never reached the provider at
    // all (DNS/connect failure) — never on a timeout mid-request or any
    // response we did receive, since register/transfer/renew are not safe to
    // resend blind.
    $retryableErrnos = [CURLE_COULDNT_RESOLVE_PROXY, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT];
    $maxAttempts = 2;
    $result = ['response' => false, 'error' => '', 'errno' => 0, 'http_code' => 0];

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $result = drr_http_request([
            CURLOPT_URL => $customApiEndpoint,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $isRetryable = $result['response'] === false && in_array($result['errno'], $retryableErrnos, true);
        if (!$isRetryable || $attempt === $maxAttempts) {
            break;
        }
        usleep(300000);
    }

    $response = $result['response'];
    $curlError = $result['error'];
    $httpCode = $result['http_code'];

    ob_end_clean();

    if ($response === false) {
        drr_report_error($action, 'cURL error contacting provider API', ['curl_error' => $curlError]);
        return ['error' => drr_t('curl_error', $locale, ['detail' => $curlError])];
    }

    $decodedResponse = json_decode($response, true);

    if ($moduleLog) {
        logModuleCall($registrarName, $action, ['endpoint' => $customApiEndpoint, 'request_action' => $action, 'request_params' => $apiParams], $decodedResponse);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        drr_report_error($action, 'Invalid JSON response from provider API', ['http_code' => $httpCode]);
        return ['error' => drr_t('invalid_json', $locale, ['detail' => $response])];
    }

    // Sync/TransferSync use a flat response shape with no status/data
    // envelope — hand the decoded body straight to the caller instead of
    // looking for a `status` key that will never be there.
    if (in_array($action, DRR_FLAT_RESPONSE_ACTIONS, true)) {
        if ($httpCode >= 200 && $httpCode < 300) {
            return $decodedResponse;
        }
        drr_report_error($action, 'Provider API returned a non-2xx response for a flat-envelope action', ['http_code' => $httpCode]);
        return ['error' => $decodedResponse['error'] ?? $decodedResponse['message'] ?? drr_t('unknown_api_error', $locale)];
    }

    if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'success') {
        return $decodedResponse['data'];
    } else {
        // Per the provider's documented contract, EVERY business error — not
        // just genuine transport/provider failures — arrives as a non-2xx
        // HTTP status with a well-formed {"status":"error","message":"..."}
        // body (e.g. "domain in redemption period"). So HTTP status alone
        // can no longer tell the two apart; only a response that ISN'T a
        // well-formed documented error (missing status/message, or an
        // unexpected status value) indicates a real anomaly worth reporting.
        $isDocumentedBusinessError = ($decodedResponse['status'] ?? null) === 'error' && isset($decodedResponse['message']);
        if (($httpCode < 200 || $httpCode >= 300) && !$isDocumentedBusinessError) {
            drr_report_error($action, 'Provider API returned an unexpected non-2xx response', [
                'http_code' => $httpCode,
                'message' => $decodedResponse['message'] ?? null,
            ]);
        }
        $errorResult = ['error' => $decodedResponse['message'] ?? drr_t('unknown_api_error', $locale), 'details' => $decodedResponse];
        if (!empty($decodedResponse['error_code'])) {
            $errorResult['error_code'] = $decodedResponse['error_code'];
        }
        return $errorResult;
    }
}

function domain_reseller_registrar_RegisterDomain($params) {
    // The provider API does not accept contact/WHOIS data on this action — it
    // pulls that from the reseller's own account, not from the registering
    // client's WHMCS profile — so only the fields it documents are sent. See
    // API.md#registerdomain.
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'regperiod' => $params['regperiod'],
        'nameservers' => array_values(array_filter([
            $params['ns1'] ?? '', $params['ns2'] ?? '', $params['ns3'] ?? '', $params['ns4'] ?? '', $params['ns5'] ?? '',
        ])),
        'dnsmanagement' => $params['dnsmanagement'],
        'emailforwarding' => $params['emailforwarding'],
        'idprotection' => $params['idprotection'],
    ];

    $response = reseller_callAPI($params, 'RegisterDomain', $apiParams);
    return isset($response['error']) ? ['error' => $response['error']] : ['success' => true];
}

function domain_reseller_registrar_RenewDomain($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'regperiod' => $params['regperiod'],
    ];
    $response = reseller_callAPI($params, 'RenewDomain', $apiParams);
    return isset($response['error']) ? ['error' => $response['error']] : ['success' => true];
}

function domain_reseller_registrar_RequestDelete($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
    ];
    $response = reseller_callAPI($params, 'RequestDelete', $apiParams);
    return isset($response['error']) ? ['error' => $response['error']] : ['success' => true];
}

function domain_reseller_registrar_GetNameservers($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname']
    ];
    $response = reseller_callAPI($params, 'GetNameservers', $apiParams);
    if (isset($response['error'])) return ['error' => $response['error']];
    return [
        'ns1' => $response['ns1'] ?? '',
        'ns2' => $response['ns2'] ?? '',
        'ns3' => $response['ns3'] ?? '',
        'ns4' => $response['ns4'] ?? '',
        'ns5' => $response['ns5'] ?? '',
    ];
}

function domain_reseller_registrar_SaveNameservers($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'ns1' => $params['ns1'],
        'ns2' => $params['ns2'],
        'ns3' => $params['ns3'],
        'ns4' => $params['ns4'],
        'ns5' => $params['ns5'],
    ];
    $response = reseller_callAPI($params, 'SaveNameservers', $apiParams);
    return isset($response['error']) ? ['error' => $response['error']] : ['success' => true];
}

/**
 * Normalizes one contact role's fields from the provider's response into the
 * labels WHMCS expects. The provider's GetContactDetails is a passthrough of
 * WHMCS's own DomainGetWhoisInfo format — space-separated keys like
 * "First Name" / "Email Address" / "Phone Number" / "Postcode" — so those are
 * checked first. Underscored/alternate spellings are kept as fallbacks per
 * the project's existing fallback-chain convention, in case a different
 * backend or a future endpoint varies. See API.md#getcontactdetails.
 */
function drr_normalize_contact(array $contact) {
    $firstName = $contact['First Name'] ?? $contact['First_Name'] ?? '';
    $lastName = $contact['Last Name'] ?? $contact['Last_Name'] ?? '';

    if ($firstName === '' && $lastName === '' && !empty($contact['Full_Name'])) {
        $nameParts = explode(' ', $contact['Full_Name']);
        $firstName = $nameParts[0];
        $lastName = end($nameParts);
    }

    return [
        'Company Name' => $contact['Company Name'] ?? $contact['Company_Name'] ?? '',
        'First Name'   => $firstName,
        'Last Name'    => $lastName,
        'Address 1'    => $contact['Address 1'] ?? $contact['Address'] ?? $contact['Address1'] ?? $contact['Address_1'] ?? '',
        'Address 2'    => $contact['Address 2'] ?? $contact['Address2'] ?? $contact['Address_2'] ?? '',
        'Email'        => $contact['Email Address'] ?? $contact['Email'] ?? '',
        'City'         => $contact['City'] ?? '',
        'State'        => $contact['State'] ?? '',
        'Zip'          => $contact['Postcode'] ?? $contact['Zip'] ?? '',
        'Country'      => $contact['Country'] ?? '',
        'Phone'        => $contact['Phone Number'] ?? $contact['Phone'] ?? $contact['Phone_Number'] ?? '',
    ];
}

function domain_reseller_registrar_GetContactDetails($params)
{
    $apiParams = [
        'domainid'   => $params['domainid'],
        'domainname' => $params['domainname']
    ];

    $response = reseller_callAPI($params, 'GetContactDetails', $apiParams);

    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }

    $results = [];

    if (isset($response['Registrant'])) {
        $results['Registrant'] = drr_normalize_contact($response['Registrant']);
    }

    if (isset($response['Billing'])) {
        $results['Billing'] = drr_normalize_contact($response['Billing']);
    }

    if (isset($response['Technical'])) {
        $results['Technical'] = drr_normalize_contact($response['Technical']);
    } elseif (isset($response['Tech'])) {
        $results['Tech'] = drr_normalize_contact($response['Tech']);
    }

    if (isset($response['Admin'])) {
        $results['Admin'] = drr_normalize_contact($response['Admin']);
    }

    return $results;
}

function domain_reseller_registrar_SaveContactDetails($params) {
    $contactDetails = [];
    foreach ($params['contactdetails'] as $type => $details) {
        $contactDetails[$type] = [
            'First_Name' => $details['First Name'] ?? $details['First_Name'] ?? '',
            'Last_Name' => $details['Last Name'] ?? $details['Last_Name'] ?? '',
            'Company_Name' => $details['Company Name'] ?? $details['Company_Name'] ?? '',
            'Email' => $details['Email'] ?? '',
            'Address_1'     => $details['Address'] ?? $details['Address1'] ?? $details['Address_1'] ?? $details['Address 1'] ?? '',
            'Address_2'     => $details['Address2'] ?? $details['Address_2'] ?? $details['Address 2'] ?? '',
            'City' => $details['City'] ?? '',
            'State' => $details['State'] ?? '',
            'Zip' => $details['Zip'] ?? $details['Postcode'] ?? '',
            'Country' => $details['Country'] ?? '',
            'Phone' => $details['Phone'] ?? $details['Phone_Number'] ?? '',
        ];
    }

    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'contactdetails' => $contactDetails,
    ];
    $response = reseller_callAPI($params, 'SaveContactDetails', $apiParams);
    return isset($response['error']) ? ['error' => $response['error']] : ['success' => true];
}

function domain_reseller_registrar_GetEPPCode($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname']
    ];
    $response = reseller_callAPI($params, 'GetEPPCode', $apiParams);
    if (isset($response['error'])) return ['error' => $response['error']];
    return ['eppcode' => $response['eppcode'] ?? 'EPP Code not available.'];
}

function domain_reseller_registrar_TransferDomain($params) {
    // Same rationale as RegisterDomain — no contacts object; see API.md#transferdomain.
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'nameservers' => array_values(array_filter([
            $params['ns1'] ?? '', $params['ns2'] ?? '', $params['ns3'] ?? '', $params['ns4'] ?? '', $params['ns5'] ?? '',
        ])),
        'eppcode' => $params['eppcode'],
        'regperiod' => $params['regperiod'],
        'dnsmanagement' => $params['dnsmanagement'],
        'emailforwarding' => $params['emailforwarding'],
        'idprotection' => $params['idprotection'],
    ];

    $response = reseller_callAPI($params, 'TransferDomain', $apiParams);
    return isset($response['error']) ? ['error' => $response['error']] : ['success' => true];
}

function domain_reseller_registrar_ReleaseDomain($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'newtag' => $params['transfertag'],
    ];

    $response = reseller_callAPI($params, 'ReleaseDomain', $apiParams);
    return isset($response['error']) ? ['error' => $response['error']] : ['success' => true];
}

function domain_reseller_registrar_IDProtectToggle($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'idprotect' => $params['protectenable'],
    ];

    $response = reseller_callAPI($params, 'IDProtectToggle', $apiParams);
    return isset($response['error']) ? ['error' => $response['error']] : ['success' => true];
}

function domain_reseller_registrar_GetRegistrarLock($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname']
    ];

    $response = reseller_callAPI($params, 'GetRegistrarLock', $apiParams);
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }

    // Normalize whatever key the provider used into the single key WHMCS
    // actually reads, instead of assuming the provider already knows WHMCS's
    // internal format (see API.md for the documented contract).
    $lockStatus = $response['lockstatus'] ?? $response['status'] ?? $response['lockenabled'] ?? 'unlocked';

    return [
        'lockstatus' => $lockStatus,
        'status' => $lockStatus,
    ];
}

function domain_reseller_registrar_SaveRegistrarLock($params) {
    
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'lockstatus' => ($params['lockenabled'] === 'locked')
    ];
    
    $response = reseller_callAPI($params, 'SaveRegistrarLock', $apiParams);
    
    return isset($response['error']) ? 
        ['error' => $response['error']] : 
        ['success' => true];
}

function domain_reseller_registrar_CheckAvailability($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domain' => $params['domainname'],
        'domainname' => $params['domainname'],
    ];
    
    $response = reseller_callAPI($params, 'CheckAvailability', $apiParams);

    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }

    if (empty($response['status'])) {
        drr_report_error('CheckAvailability', 'Provider response missing status field');
        return ['error' => drr_t('no_availability_status', drr_current_locale($params))];
    }

    return [
        'success' => true,
        'status' => $response['status']
    ];
}

function domain_reseller_registrar_Sync($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domain'],
        'sld' => $params['sld'],
        'tld' => $params['tld']
    ];

    $response = reseller_callAPI($params, 'Sync', $apiParams);

    // Sync returns a flat body with an always-present `error` key — empty
    // string on success — so this must check for a non-empty message, not
    // mere key presence.
    if (!empty($response['error'])) {
        return ['error' => $response['error']];
    }

    return [
        'active' => (bool)($response['active'] ?? false),
        'cancelled' => (bool)($response['cancelled'] ?? false),
        'transferredAway' => (bool)($response['transferredAway'] ?? false),
        'expirydate' => $response['expirydate'] ?? '',
        'error' => $response['error'] ?? ''
    ];
}

function domain_reseller_registrar_TransferSync($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domain'],
        'sld' => $params['sld'],
        'tld' => $params['tld']
    ];

    $response = reseller_callAPI($params, 'TransferSync', $apiParams);

    // Same flat-body caveat as Sync — `error` is always present, empty on success.
    if (!empty($response['error'])) {
        return [
            'completed' => false,
            'expirydate' => '',
            'failed' => true,
            'reason' => $response['error'],
            'error' => $response['error']
        ];
    }

    return [
        'completed' => (bool)($response['completed'] ?? false),
        'expirydate' => $response['expirydate'] ?? '',
        'failed' => (bool)($response['failed'] ?? false),
        'reason' => $response['reason'] ?? '',
        'error' => $response['error'] ?? ''
    ];
}

function domain_reseller_registrar_RegisterNameserver($params) {
    
    $response = reseller_callAPI($params, 'RegisterNameserver', drr_strip_config_params($params));
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    return $response;
}

function domain_reseller_registrar_ModifyNameserver($params) {

    $response = reseller_callAPI($params, 'ModifyNameserver', drr_strip_config_params($params));
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    return $response;
}

function domain_reseller_registrar_DeleteNameserver($params) {
    
    $response = reseller_callAPI($params, 'DeleteNameserver', drr_strip_config_params($params));
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    return $response;
}

function domain_reseller_registrar_GetDNS($params) {
    $response = reseller_callAPI($params, 'GetDNS', drr_strip_config_params($params));
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    return $response;
}

function domain_reseller_registrar_SaveDNS($params) {

    $response = reseller_callAPI($params, 'SaveDNS', drr_strip_config_params($params));
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    
    return $response;
}

function domain_reseller_registrar_GetDomainSuggestions($params) {

    $response = reseller_callAPI($params, 'GetDomainSuggestions', drr_strip_config_params($params));
    
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    
    return $response;
}

/**
 * Reads one year's price from a register/renew/transfer pricing block. The
 * provider keys years as plain numbers ("1", "2" — see API.md#gettldpricing);
 * "1yr"-style keys are accepted too, defensively, per this project's existing
 * fallback-chain convention.
 */
function drr_tld_year_price(array $pricingForType, $year) {
    $price = $pricingForType[(string) $year] ?? $pricingForType[$year . 'yr'] ?? null;
    return $price !== null ? (float) $price : null;
}

/**
 * Collects every year key referenced across register/renew/transfer for one
 * TLD, tolerant of both "1" and "1yr" spellings.
 */
function drr_tld_available_years(array $pricing) {
    $years = [];
    foreach (['register', 'renew', 'transfer'] as $type) {
        if (isset($pricing[$type]) && is_array($pricing[$type])) {
            foreach (array_keys($pricing[$type]) as $yearKey) {
                $year = (int) preg_replace('/\D+/', '', (string) $yearKey);
                if ($year > 0) {
                    $years[] = $year;
                }
            }
        }
    }
    $years = array_unique($years);
    sort($years);
    return $years;
}

function domain_reseller_registrar_GetTldPricing($params) {

    $activeCurrency = Capsule::table('tblcurrencies')->where('default', 1)->first();
    $currencyCode = $activeCurrency ? $activeCurrency->code : 'USD';

    $apiParams = [
        'currency' => $currencyCode
    ];
    $response = reseller_callAPI($params, 'GetTldPricing', $apiParams);

    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }

    $tldsData = $response['tlds'] ?? [];
    $tldFeatures = $response['tld_features'] ?? [];
    $currency = $response['currency'] ?? null;

    if (empty($tldsData)) {
        return ['error' => drr_t('no_tld_pricing', drr_current_locale($params))];
    }

    if (!$currency) {
        return ['error' => drr_t('no_currency_info', drr_current_locale($params))];
    }

    $results = new ResultsList;

    foreach ($tldsData as $rawTld => $pricing) {
        // The provider keys TLDs with a leading dot (".com"); WHMCS's importer
        // expects the bare extension.
        $tld = ltrim((string) $rawTld, '.');

        $registerPrice = isset($pricing['register']) && is_array($pricing['register'])
            ? drr_tld_year_price($pricing['register'], 1) : null;
        $renewPrice = isset($pricing['renew']) && is_array($pricing['renew'])
            ? drr_tld_year_price($pricing['renew'], 1) : null;
        $transferPrice = isset($pricing['transfer']) && is_array($pricing['transfer'])
            ? drr_tld_year_price($pricing['transfer'], 1) : null;

        if ($registerPrice === null || $registerPrice <= 0) {
            continue;
        }

        $availableYears = drr_tld_available_years($pricing);

        $minYears = !empty($availableYears) ? min($availableYears) : 1;
        $maxYears = !empty($availableYears) ? max($availableYears) : 10;

        $hasCustomYears = false;
        if (count($availableYears) > 1) {
            for ($i = 0; $i < count($availableYears) - 1; $i++) {
                if ($availableYears[$i + 1] - $availableYears[$i] !== 1) {
                    $hasCustomYears = true;
                    break;
                }
            }
        }

        $item = (new ImportItem)
            ->setExtension($tld)
            ->setMinYears($minYears)
            ->setMaxYears($maxYears)
            ->setRegisterPrice($registerPrice)
            ->setCurrency($currency['code']);

        if ($renewPrice !== null) {
            $item->setRenewPrice($renewPrice);
        }

        if ($transferPrice !== null) {
            $item->setTransferPrice($transferPrice);
        }

        if ($hasCustomYears && !empty($availableYears)) {
            $item->setYears($availableYears);
        }

        // The provider's tld_features map does not currently carry an EPP
        // flag (it reports addon enablement instead — see API.md), so this
        // defaults to "required", the common case for gTLDs/most ccTLDs: a
        // transfer that turns out not to need a code costs nothing extra,
        // while skipping a code a TLD actually needs breaks the transfer.
        // Only an explicit `eppcode` value (if the provider adds one later)
        // overrides that default.
        $features = $tldFeatures[$tld] ?? $tldFeatures['.' . $tld] ?? null;
        $eppRequired = isset($features['eppcode']) ? (bool) $features['eppcode'] : true;
        $item->setEppRequired($eppRequired);

        $results[] = $item;
    }

    return $results;
}
