---
title: "Implementing the Provider API"
description: "Build an upstream API service that matches the module's request envelope and action set."
---

This guide is for the service behind `API Endpoint`. The module is only a WHMCS-side bridge; your provider platform still has to accept the module's JSON envelope and return action-specific payloads that WHMCS can understand after translation.

<Steps>
  <Step>
    ### Accept the standard request envelope

    Every call arrives as JSON:

```json
{
  "api_key": "your_api_key",
  "action": "RegisterDomain",
  "params": {
    "domainid": 123,
    "domainname": "example.com"
  }
}
```

    Your API must parse that envelope and route by `action`. The repository's `API.md` lists the supported actions and the expected data contracts.
  </Step>
  <Step>
    ### Validate credentials and keep JSON responses consistent

    The module checks the decoded JSON body, not the HTTP status code. That means your failure path still needs to return a JSON envelope:

```json
{
  "status": "error",
  "message": "Human readable failure"
}
```

    Keep that structure consistent across auth, validation, and upstream provider failures.
  </Step>
  <Step>
    ### Implement action handlers for the callbacks you plan to use

    At minimum, most production setups need handlers for:

    - `RegisterDomain`
    - `RenewDomain`
    - `TransferDomain`
    - `GetNameservers`
    - `SaveNameservers`
    - `GetContactDetails`
    - `SaveContactDetails`
    - `GetEPPCode`
    - `Sync`
    - `TransferSync`
    - `GetTldPricing`

    If your WHMCS instance uses DNS management, child nameservers, or domain suggestions, implement those actions too.
  </Step>
  <Step>
    ### Normalize your provider model to the module contract

    The module already handles some translation, but you still need to return the right keys. For example:

    - `GetNameservers` should expose `ns1` through `ns5`
    - `GetEPPCode` should expose `eppcode`
    - `TransferSync` should expose `completed`, `failed`, `expirydate`, and `reason`
    - `GetTldPricing` should expose a `currency` object and a `tlds` map
  </Step>
</Steps>

## Complete Runnable Example

```php
<?php

header('Content-Type: application/json');

$request = json_decode(file_get_contents('php://input'), true);
$apiKey = $request['api_key'] ?? '';
$action = $request['action'] ?? '';
$params = $request['params'] ?? [];

if ($apiKey !== 'demo-key') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid API key',
    ]);
    exit;
}

$handlers = [
    'RenewDomain' => function (array $params): array {
        return [
            'status' => 'success',
            'data' => [
                'renewed' => true,
                'expirydate' => '2027-03-25',
            ],
        ];
    },
    'GetContactDetails' => function (array $params): array {
        // WHMCS's own WHOIS-info field names — the module reads these first.
        return [
            'status' => 'success',
            'data' => [
                'Registrant' => [
                    'First Name' => 'John',
                    'Last Name' => 'Doe',
                    'Email Address' => 'john@example.com',
                    'Address 1' => '123 Main Street',
                    'City' => 'Austin',
                    'State' => 'TX',
                    'Postcode' => '78701',
                    'Country' => 'US',
                    'Phone Number' => '+15125550123',
                ],
            ],
        ];
    },
];

// Sync/TransferSync are the exception: no status/data envelope, and
// `error` must always be present (empty string on success).
if ($action === 'Sync') {
    echo json_encode([
        'active' => true,
        'cancelled' => false,
        'transferredAway' => false,
        'expirydate' => '2027-03-25',
        'error' => '',
    ]);
    exit;
}

$response = $handlers[$action] ?? null;

if ($response === null) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unknown action: ' . $action,
    ]);
    exit;
}

echo json_encode($response($params));
```

## Real-World Notes

- `RegisterDomain` and `TransferDomain` do **not** send a `contacts` object — build your own registration flow to use the reseller's own account contact instead of expecting WHMCS to supply one. They do send up to 5 `nameservers`.
- `RegisterNameserver`, `ModifyNameserver`, `DeleteNameserver`, `GetDNS`, `SaveDNS`, and `GetDomainSuggestions` forward the entire `$params` array, so your provider service must tolerate extra WHMCS keys.
- `GetTldPricing` receives the default WHMCS currency code, not an arbitrary client-side selector. Reply with TLD keys prefixed by a dot (`.com`) and plain numeric year keys (`"1"`, `"2"`) — see [Importing TLD Pricing](/docs/guides/importing-tld-pricing).
- Every request includes a best-effort `locale` field. If you can localize `message` text by it, do so — that's currently the only path for localized business-error text to reach the WHMCS admin/client.
- `Sync` and `TransferSync` do **not** use the `status`/`data` envelope — return a flat object with `active`/`cancelled`/`transferredAway`/`expirydate`/`error` (or `completed`/`failed`/`expirydate`/`reason`/`error`), HTTP 200, with `error` as an empty string on success.
- Self-update is no longer this API's responsibility — the module checks GitHub Releases directly. There is no `check_module_update` action to implement.

## Related Reading

- [Provider API Transport](/docs/provider-api-transport)
- [API Reference: Sync and Pricing](/docs/api-reference/sync-and-pricing)
