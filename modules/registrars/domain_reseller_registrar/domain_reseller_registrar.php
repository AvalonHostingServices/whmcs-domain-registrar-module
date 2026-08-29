<?php

if (!defined("WHMCS")) die("This file cannot be accessed directly");

if (!defined('DRR_VERSION')) {
    define('DRR_VERSION', '2.2.0');
}

// GlitchTip project DSN for this module. Fixed and always-on: lets Avalon Hosting
// Services see transport-level failures (cURL errors, malformed API responses,
// non-2xx provider responses) across every install of this module and fix them
// centrally. Never fed request payloads, contact details, or API credentials —
// only technical failure context. See drr_report_error() below.
if (!defined('DRR_GLITCHTIP_DSN')) {
    define('DRR_GLITCHTIP_DSN', 'https://f72aead7349b4eaa9e67073af9f12595@pm.avalonhosting.services/7');
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

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $storeUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Sentry-Auth: Sentry sentry_version=7, sentry_key=' . $publicKey
                . ', sentry_client=whmcs-domain-reseller-registrar/' . DRR_VERSION,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_exec($ch);
        curl_close($ch);
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
    ];
}

function reseller_callAPI($params, $action, $apiParams = []) {
    $customApiEndpoint = $params['customApiEndpoint'];
    $customApiKey = $params['customApiKey'];
    $moduleLog = $params['moduleLog'];
    $registrarName = 'domain_reseller_registrar';

    if (empty($customApiEndpoint) || empty($customApiKey)) {
        return ['error' => 'Registrar module is not configured. Please set the API Endpoint and API Key.'];
    }

    ob_start();

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $customApiEndpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'api_key' => $customApiKey,
        'action' => $action,
        'params' => $apiParams,
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    ob_end_clean();



    if ($response === false) {
        drr_report_error($action, 'cURL error contacting provider API', ['curl_error' => $curlError]);
        return ['error' => 'cURL Error: ' . $curlError];
    }

    $decodedResponse = json_decode($response, true);

    if ($moduleLog) {
        logModuleCall($registrarName, $action, ['endpoint' => $customApiEndpoint, 'request_action' => $action, 'request_params' => $apiParams], $decodedResponse);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        drr_report_error($action, 'Invalid JSON response from provider API', ['http_code' => $httpCode]);
        return ['error' => 'Invalid JSON response from API: ' . $response];
    }

    if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'success') {
        return $decodedResponse['data'];
    } else {
        // A documented business-error response (e.g. "domain in redemption period")
        // arrives as HTTP 2xx and is expected, not a bug — only non-2xx responses
        // indicate a real transport/provider-side failure worth reporting.
        if ($httpCode < 200 || $httpCode >= 300) {
            drr_report_error($action, 'Provider API returned a non-2xx response', [
                'http_code' => $httpCode,
                'message' => $decodedResponse['message'] ?? null,
            ]);
        }
        return ['error' => $decodedResponse['message'] ?? 'Unknown API error.', 'details' => $decodedResponse];
    }
}

function domain_reseller_registrar_RegisterDomain($params) {
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'regperiod' => $params['regperiod'],
        'dnsmanagement' => $params['dnsmanagement'],
        'emailforwarding' => $params['emailforwarding'],
        'idprotection' => $params['idprotection'],
        'contacts' => [
            'registrant' => $params,
            'admin' => [
                'firstname' => $params['adminfirstname'],
                'lastname' => $params['adminlastname'],
                'companyname' => $params['admincompanyname'],
                'address1' => $params['adminaddress1'],
                'address2' => $params['adminaddress2'],
                'city' => $params['admincity'],
                'state' => $params['adminstate'],
                'postcode' => $params['adminpostcode'],
                'country' => $params['admincountry'],
                'phonenumber' => $params['adminphonenumber'],
                'email' => $params['adminemail'],
            ],
            'tech' => [
                'firstname' => $params['techfirstname'],
                'lastname' => $params['techlastname'],
                'companyname' => $params['techcompanyname'],
                'address1' => $params['techaddress1'],
                'address2' => $params['techaddress2'],
                'city' => $params['techcity'],
                'state' => $params['techstate'],
                'postcode' => $params['techpostcode'],
                'country' => $params['techcountry'],
                'phonenumber' => $params['techphonenumber'],
                'email' => $params['techemail'],
            ],
            'billing' => [
                'firstname' => $params['billingfirstname'],
                'lastname' => $params['billinglastname'],
                'companyname' => $params['billingcompanyname'],
                'address1' => $params['billingaddress1'],
                'address2' => $params['billingaddress2'],
                'city' => $params['billingcity'],
                'state' => $params['billingstate'],
                'postcode' => $params['billingpostcode'],
                'country' => $params['billingcountry'],
                'phonenumber' => $params['billingphonenumber'],
                'email' => $params['billingemail'],
            ],
        ],
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
        $reg = $response['Registrant'];
        if(isset($reg['Full_Name'])){
            $nameParts = explode(' ' , $reg['Full_Name']);
        }
        $results['Registrant'] = [
            'Company Name'  => $reg['Company_Name'] ?? '',
            'First Name'    => isset($nameParts) ? $nameParts[0] : ($reg['First_Name'] ?? ''),
            'Last Name'     => isset($nameParts) ? end($nameParts) : ($reg['Last_Name'] ?? ''),
            'Address 1'     => $reg['Address'] ?? $reg['Address1'] ?? $reg['Address_1'] ?? '',
            'Address 2'     => $reg['Address2'] ?? $reg['Address_2'] ?? '',
            'Email'         => $reg['Email'] ?? '',
            'City'          => $reg['City'] ?? '',
            'State'         => $reg['State'] ?? '',
            'Zip'      => $reg['Zip'] ?? $reg['Postcode'] ?? '',
            'Country'       => $reg['Country'] ?? '',
            'Phone' => $reg['Phone'] ?? $reg['Phone_Number'] ?? '',
        ];
    }

    if (isset($response['Billing'])) {
        $bill = $response['Billing'];
        if(isset($bill['Full_Name'])){
            $nameParts = explode(' ' , $bill['Full_Name']);
        }
        $results['Billing'] = [
            'Company Name'  => $bill['Company_Name'] ?? '',
            'First Name'    => isset($nameParts) ? $nameParts[0] : ($bill['First_Name'] ?? ''),
            'Last Name'     => isset($nameParts) ? end($nameParts) : ($bill['Last_Name'] ?? ''),
            'Address 1'     => $bill['Address'] ?? $bill['Address1'] ?? $bill['Address_1'] ?? '',
            'Address 2'     => $bill['Address2'] ?? $bill['Address_2'] ?? '',
            'Email'         => $bill['Email'] ?? '',
            'City'          => $bill['City'] ?? '',
            'State'         => $bill['State'] ?? '',
            'Zip'      => $bill['Zip'] ?? $bill['Postcode'] ?? '',
            'Country'       => $bill['Country'] ?? '',
            'Phone' => $bill['Phone_Number'] ?? $bill['Phone'] ?? '',
        ];
    }

    if (isset($response['Technical'])) {
        $tech = $response['Technical'];
        if(isset($tech['Full_Name'])){
            $nameParts = explode(' ' , $tech['Full_Name']);
        }
        $results['Technical'] = [
            'Company Name'  => $tech['Company_Name'] ?? '',
            'First Name'    => isset($nameParts) ? $nameParts[0] : ($tech['First_Name'] ?? ''),
            'Last Name'     => isset($nameParts) ? end($nameParts) : ($tech['Last_Name'] ?? ''),
            'Address 1'     => $tech['Address'] ?? $tech['Address1'] ?? $tech['Address_1'] ?? '',
            'Address 2'     => $tech['Address2'] ?? $tech['Address_2'] ?? '',
            'Email'         => $tech['Email'] ?? '',
            'City'          => $tech['City'] ?? '',
            'State'         => $tech['State'] ?? '',
            'Zip'      => $tech['Zip'] ?? $tech['Postcode'] ?? '',
            'Country'       => $tech['Country'] ?? '',
            'Phone' => $tech['Phone_Number'] ?? $tech['Phone'] ?? '',
        ];
    }
    else if (isset($response['Tech'])) {
        $tech = $response['Tech'];
        if(isset($tech['Full_Name'])){
            $nameParts = explode(' ' , $tech['Full_Name']);
        }
        $results['Tech'] = [
            'Company Name'  => $tech['Company_Name'] ?? '',
            'First Name'    => isset($nameParts) ? $nameParts[0] : ($tech['First_Name'] ?? ''),
            'Last Name'     => isset($nameParts) ? end($nameParts) : ($tech['Last_Name'] ?? ''),
            'Address 1'     => $tech['Address'] ?? $tech['Address1'] ?? $tech['Address_1'] ?? '',
            'Address 2'     => $tech['Address2'] ?? $tech['Address_2'] ?? '',
            'Email'         => $tech['Email'] ?? '',
            'City'          => $tech['City'] ?? '',
            'State'         => $tech['State'] ?? '',
            'Zip'      => $tech['Zip'] ?? $tech['Postcode'] ?? '',
            'Country'       => $tech['Country'] ?? '',
            'Phone' => $tech['Phone_Number'] ?? $tech['Phone'] ?? '',
        ];
    }
    

    if (isset($response['Admin'])) {
        $admin = $response['Admin'];
        if(isset($admin['Full_Name'])){
            $nameParts = explode(' ' , $admin['Full_Name']);
        }
        $results['Admin'] = [
            'Company Name'  => $admin['Company_Name'] ?? '',
            'First Name'    => isset($nameParts) ? $nameParts[0] : ($admin['First_Name'] ?? ''),
            'Last Name'     => isset($nameParts) ? end($nameParts) : ($admin['Last_Name'] ?? ''),
            'Address 1'     => $admin['Address'] ?? $admin['Address1'] ?? $admin['Address_1'] ?? '',
            'Address 2'     => $admin['Address2'] ?? $admin['Address_2'] ?? '',
            'Email'         => $admin['Email'] ?? '',
            'City'          => $admin['City'] ?? '',
            'State'         => $admin['State'] ?? '',
            'Zip'      => $admin['Zip'] ?? $admin['Postcode'] ?? '',
            'Country'       => $admin['Country'] ?? '',
            'Phone' => $admin['Phone_Number'] ?? $admin['Phone'] ?? '',
        ];
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
    $apiParams = [
        'domainid' => $params['domainid'],
        'domainname' => $params['domainname'],
        'nameservers' => array_filter([
            $params['ns1'], $params['ns2'], $params['ns3'], $params['ns4'], $params['ns5']
        ]),
        'eppcode' => $params['eppcode'],
        'regperiod' => $params['regperiod'],
        'dnsmanagement' => $params['dnsmanagement'],
        'emailforwarding' => $params['emailforwarding'],
        'idprotection' => $params['idprotection'],
        'contacts' => [
            'registrant' => $params,
            'admin' => [
                'firstname' => $params['adminfirstname'],
                'lastname' => $params['adminlastname'],
                'companyname' => $params['admincompanyname'],
                'address1' => $params['adminaddress1'],
                'address2' => $params['adminaddress2'],
                'city' => $params['admincity'],
                'state' => $params['adminstate'],
                'postcode' => $params['adminpostcode'],
                'country' => $params['admincountry'],
                'phonenumber' => $params['adminphonenumber'],
                'email' => $params['adminemail'],
            ],
            'tech' => [
                'firstname' => $params['techfirstname'],
                'lastname' => $params['techlastname'],
                'companyname' => $params['techcompanyname'],
                'address1' => $params['techaddress1'],
                'address2' => $params['techaddress2'],
                'city' => $params['techcity'],
                'state' => $params['techstate'],
                'postcode' => $params['techpostcode'],
                'country' => $params['techcountry'],
                'phonenumber' => $params['techphonenumber'],
                'email' => $params['techemail'],
            ],
            'billing' => [
                'firstname' => $params['billingfirstname'],
                'lastname' => $params['billinglastname'],
                'companyname' => $params['billingcompanyname'],
                'address1' => $params['billingaddress1'],
                'address2' => $params['billingaddress2'],
                'city' => $params['billingcity'],
                'state' => $params['billingstate'],
                'postcode' => $params['billingpostcode'],
                'country' => $params['billingcountry'],
                'phonenumber' => $params['billingphonenumber'],
                'email' => $params['billingemail'],
            ],
        ],
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
        return ['error' => 'Provider did not return an availability status.'];
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
    
    if (isset($response['error'])) {
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
    
    if (isset($response['error'])) {
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
        return ['error' => 'No TLD pricing data available from provider.'];
    }

    if (!$currency) {
        return ['error' => 'No currency information available.'];
    }

    $results = new ResultsList;

    foreach ($tldsData as $tld => $pricing) {
        $registerPrice = isset($pricing['register']['1yr']) ? (float)$pricing['register']['1yr'] : null;
        $renewPrice = isset($pricing['renew']['1yr']) ? (float)$pricing['renew']['1yr'] : null;
        $transferPrice = isset($pricing['transfer']['1yr']) ? (float)$pricing['transfer']['1yr'] : null;

        if ($registerPrice === null || $registerPrice <= 0) {
            continue;
        }

        $availableYears = [];
        foreach (['register', 'renew', 'transfer'] as $type) {
            if (isset($pricing[$type]) && is_array($pricing[$type])) {
                foreach (array_keys($pricing[$type]) as $yearKey) {
                    $year = (int)str_replace('yr', '', $yearKey);
                    $availableYears[] = $year;
                }
            }
        }
        $availableYears = array_unique($availableYears);
        sort($availableYears);

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

        // Whether a TLD needs an EPP code is a property of the TLD, so it comes
        // from the provider per extension rather than being assumed for all.
        $item->setEppRequired(!empty($tldFeatures[$tld]['eppcode']));

        $results[] = $item;
    }

    return $results;
}
