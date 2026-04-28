---
title: "Transfer and Security"
description: "Registrar callbacks for ID protection, EPP codes, registrar lock state, and transfer status checks."
---

This page covers the security- and transfer-oriented callbacks in `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`.

## `domain_reseller_registrar_GetEPPCode`

```php
function domain_reseller_registrar_GetEPPCode(array $params): array
```

Fetches the transfer authorization code from the provider.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS domain context and module config. |

Returns:

```php
['eppcode' => 'AUTH-ABC-123']
```

Example:

```php
<?php

$providerResponse = ['eppcode' => 'AUTH-ABC-123'];
```

## `domain_reseller_registrar_IDProtectToggle`

```php
function domain_reseller_registrar_IDProtectToggle(array $params): array
```

Sends `protectenable` upstream as `idprotect`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS params containing `protectenable`, domain context, and module config. |

Returns `['success' => true]` or `['error' => ...]`.

Example:

```php
<?php

$providerPayload = [
    'domainid' => 123,
    'domainname' => 'example.com',
    'idprotect' => true,
];
```

## `domain_reseller_registrar_GetRegistrarLock`

```php
function domain_reseller_registrar_GetRegistrarLock(array $params): array
```

Returns the decoded provider lock state directly unless an error occurs.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS domain context and module config. |

Typical return:

```php
['lockenabled' => 'locked']
```

Example:

```php
<?php

$providerResponse = ['lockenabled' => 'unlocked'];
```

## `domain_reseller_registrar_SaveRegistrarLock`

```php
function domain_reseller_registrar_SaveRegistrarLock(array $params): array
```

Converts WHMCS `lockenabled` into a boolean `lockstatus` before sending it upstream.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS params containing `lockenabled`, domain context, and module config. |

Returns `['success' => true]` or `['error' => ...]`.

Example:

```php
<?php

$providerPayload = [
    'domainid' => 123,
    'domainname' => 'example.com',
    'lockstatus' => true,
];
```

## `domain_reseller_registrar_CheckAvailability`

```php
function domain_reseller_registrar_CheckAvailability(array $params): array
```

Checks whether a domain is available by sending both `domain` and `domainname` keys upstream.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS params containing the domain to inspect and module config. |

Returns:

```php
[
    'success' => true,
    'status' => 'available',
]
```

Example:

```php
<?php

$providerResponse = ['status' => 'available'];
```

## Notes

- Source file: `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`
- `GetEPPCode()` falls back to `EPP Code not available.` when the provider omits `eppcode`.
- `SaveRegistrarLock()` treats only the literal WHMCS value `locked` as `true`.

Related pages:

- [Registration and Renewal](/docs/api-reference/registration-and-renewal)
- [Sync and Pricing](/docs/api-reference/sync-and-pricing)
