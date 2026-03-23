<?php

if (!defined("WHMCS")) die("This file cannot be accessed directly");

use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Results\ResultsList;
use WHMCS\Database\Capsule;

function domain_reseller_registrar_MetaData() {
    return [
        'DisplayName' => 'Avalon Hosting Services',
        'APIVersion' => '1.0.0',
        'Description' => 'This Registrar allows you to offer a wide variety of TLD straight from your Provider System.',
    ];
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
        return ['error' => 'cURL Error: ' . $curlError];
    }

    $decodedResponse = json_decode($response, true);

    if ($moduleLog) {
        logModuleCall($registrarName, $action, ['endpoint' => $customApiEndpoint, 'request_action' => $action, 'request_params' => $apiParams], $decodedResponse);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON response from API: ' . $response];
    }

    if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'success') {
        return $decodedResponse['data'];
    } else {
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
                'postcode' => $params['adminpostcode'],
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
                'postcode' => $params['adminpostcode'],
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
    return $response;
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
    
    $response = reseller_callAPI($params, 'RegisterNameserver', $params);
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    return $response;
}

function domain_reseller_registrar_ModifyNameserver($params) {

    $response = reseller_callAPI($params, 'ModifyNameserver', $params);
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    return $response;
}

function domain_reseller_registrar_DeleteNameserver($params) {
    
    $response = reseller_callAPI($params, 'DeleteNameserver', $params);
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    return $response;
}

function domain_reseller_registrar_GetDNS($params) {
    $response = reseller_callAPI($params, 'GetDNS', $params);
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    return $response;
}

function domain_reseller_registrar_SaveDNS($params) {

    $response = reseller_callAPI($params, 'SaveDNS', $params);
    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }
    
    return $response;
}

function domain_reseller_registrar_GetDomainSuggestions($params) {

    $response = reseller_callAPI($params, 'GetDomainSuggestions', $params);
    
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

        $item->setEppRequired(true);

        $results[] = $item;
    }

    return $results;
}
