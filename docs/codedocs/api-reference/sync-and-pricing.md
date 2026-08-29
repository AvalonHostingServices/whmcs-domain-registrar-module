---
title: "Sync and Pricing"
description: "Registrar sync callbacks and the WHMCS pricing import adapter."
---

These callbacks are the most WHMCS-specific functions in the module. They are defined in `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`.

## `domain_reseller_registrar_Sync`

```php
function domain_reseller_registrar_Sync(array $params): array
```

Polls the provider for domain lifecycle state and returns the fields WHMCS sync expects. The provider's response for this action has no `status`/`data` envelope — a flat object, HTTP 200 whether the sync succeeded or not, with `error` always present (empty string on success) — so the check is `!empty($response['error'])`, not `isset()`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS sync request including `domain`, `sld`, `tld`, and module config. |

Returns:

```php
[
    'active' => true,
    'cancelled' => false,
    'transferredAway' => false,
    'expirydate' => '2027-03-25',
    'error' => '',
]
```

Example:

```php
<?php

$providerResponse = [
    'active' => true,
    'cancelled' => false,
    'transferredAway' => false,
    'expirydate' => '2027-03-25',
];
```

## `domain_reseller_registrar_TransferSync`

```php
function domain_reseller_registrar_TransferSync(array $params): array
```

Polls the provider for transfer status and always returns the transfer-specific result keys WHMCS expects.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS transfer sync request including `domain`, `sld`, `tld`, and module config. |

Returns:

```php
[
    'completed' => true,
    'expirydate' => '2027-03-25',
    'failed' => false,
    'reason' => '',
    'error' => '',
]
```

Example:

```php
<?php

$providerResponse = [
    'completed' => true,
    'failed' => false,
    'expirydate' => '2027-03-25',
    'reason' => '',
];
```

## `domain_reseller_registrar_GetTldPricing`

```php
function domain_reseller_registrar_GetTldPricing(array $params): ResultsList|array
```

Looks up the default WHMCS currency, requests provider pricing for that currency, and converts the response into a `WHMCS\Results\ResultsList` of `WHMCS\Domain\TopLevel\ImportItem` objects. On failure it returns an error array instead.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS module config and runtime context. |

Example:

```php
<?php

$providerResponse = [
    'currency' => ['code' => 'USD'],
    'tlds' => [
        // Leading dot, plain numeric year keys — both are the provider's real shape.
        '.com' => [
            'register' => ['1' => 10.99],
            'renew' => ['1' => 11.99],
            'transfer' => ['1' => 9.99],
        ],
    ],
    'tld_features' => [
        '.com' => ['dnsmanagement' => true, 'emailforwarding' => true, 'idprotection' => true],
    ],
];
```

## Notes

- Source file: `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`
- `Sync()` and `TransferSync()` use `$params['domain']`, not `$params['domainname']`.
- `GetTldPricing()` defaults to `USD` when the WHMCS default currency lookup returns no row.
- TLDs without a positive one-year register price are skipped.
- TLD keys arrive with a leading dot (`.com`) and are stripped before import; pricing years are numeric (`"1"`), with `"1yr"`-style keys accepted only as a fallback.
- Every imported TLD defaults to EPP-required, since `tld_features` currently reports addon enablement, not an EPP flag.

Related pages:

- [Sync and Pricing Import](/docs/sync-and-pricing)
- [Importing TLD Pricing](/docs/guides/importing-tld-pricing)
