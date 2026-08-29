---
title: "Metadata and Config"
description: "Public module identity functions and the shared transport helper."
---

All functions on this page are defined in `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`. The source does not declare PHP parameter or return types, but the signatures below describe the effective runtime contract used by WHMCS.

## Import Path

```php
modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php
```

## `domain_reseller_registrar_MetaData`

```php
function domain_reseller_registrar_MetaData(): array
```

Returns the registrar identity metadata shown by WHMCS.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| — | — | — | This callback takes no parameters. |

Returns:

```php
[
    'DisplayName' => 'Avalon Hosting Services',
    'APIVersion' => DRR_VERSION, // e.g. '2.3.0' — kept in sync with whmcs.json
    'Description' => 'This Registrar allows you to offer a wide variety of TLD straight from your Provider System.',
]
```

Example:

```php
<?php

$metadata = domain_reseller_registrar_MetaData();
// WHMCS uses this to label the registrar in admin.
```

## `domain_reseller_registrar_getConfigArray`

```php
function domain_reseller_registrar_getConfigArray(): array
```

Declares the configuration fields that appear in WHMCS admin.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| — | — | — | This callback takes no parameters. |

Returns a config map with:

- `FriendlyName`
- `customApiEndpoint`
- `customApiKey`
- `moduleLog`
- `autoUpdate` — `yesno`, defaults on; opts an install out of the daily self-update check in `hooks.php` when unchecked

Example:

```php
<?php

$config = domain_reseller_registrar_getConfigArray();
// WHMCS renders the module settings from this array.
```

## `reseller_callAPI`

```php
function reseller_callAPI(array $params, string $action, array $apiParams = []): array
```

Shared helper used by almost every public callback. It posts JSON to the configured provider endpoint and returns either the decoded `data` payload or an `error` array.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | Full WHMCS registrar params, including `customApiEndpoint`, `customApiKey`, and `moduleLog`. |
| `action` | `string` | — | Provider action name such as `RenewDomain` or `GetTldPricing`. |
| `apiParams` | `array` | `[]` | Action-specific request payload nested under the outgoing `params` key. |

Return type:

```php
array
```

Possible return shapes:

```php
['ns1' => 'ns1.example.com', 'ns2' => 'ns2.example.com']
```

or

```php
['error' => 'Human readable failure', 'details' => [...]]
```

Example:

```php
<?php

$response = reseller_callAPI(
    [
        'customApiEndpoint' => 'https://provider.example/api',
        'customApiKey' => 'secret',
        'moduleLog' => true,
    ],
    'RenewDomain',
    [
        'domainid' => 123,
        'domainname' => 'example.com',
        'regperiod' => 1,
    ]
);
```

## Notes

- Source file: `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`
- The helper sets a 60-second timeout per attempt, and retries once on a connect/DNS-level failure only.
- It logs only when `moduleLog` is enabled.
- Success/failure is decided by the JSON `status` field, not the HTTP status code — but the HTTP status is still consulted to tell a documented business error apart from a genuine anomaly worth reporting to GlitchTip (see [Provider API Transport](/docs/provider-api-transport)).
- Every request also carries a best-effort `locale` field.

Related pages:

- [Provider API Transport](/docs/provider-api-transport)
- [Registration and Renewal](/docs/api-reference/registration-and-renewal)
