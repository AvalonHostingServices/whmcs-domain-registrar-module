---
title: "Nameservers and DNS"
description: "Registrar callbacks for nameserver lookup, glue records, and DNS management."
---

These functions are defined in `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`. They cover standard nameserver changes plus lower-level child nameserver and DNS record operations.

## `domain_reseller_registrar_GetNameservers`

```php
function domain_reseller_registrar_GetNameservers(array $params): array
```

Returns nameservers in the five-key shape WHMCS expects.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS domain context and module config. |

Typical return:

```php
[
    'ns1' => 'ns1.example.com',
    'ns2' => 'ns2.example.com',
    'ns3' => '',
    'ns4' => '',
    'ns5' => '',
]
```

Example:

```php
<?php

$providerResponse = [
    'ns1' => 'ns1.provider.net',
    'ns2' => 'ns2.provider.net',
];
```

## `domain_reseller_registrar_SaveNameservers`

```php
function domain_reseller_registrar_SaveNameservers(array $params): array
```

Sends `ns1` through `ns5` upstream.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS params containing nameserver fields and module config. |

Returns `['success' => true]` or `['error' => ...]`.

Example:

```php
<?php

$providerPayload = [
    'domainid' => 123,
    'domainname' => 'example.com',
    'ns1' => 'ns1.provider.net',
    'ns2' => 'ns2.provider.net',
    'ns3' => '',
    'ns4' => '',
    'ns5' => '',
];
```

## `domain_reseller_registrar_RegisterNameserver`

```php
function domain_reseller_registrar_RegisterNameserver(array $params): array
```

Forwards the full WHMCS `$params` array to the `RegisterNameserver` provider action.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS child nameserver request plus module config. |

Example:

```php
<?php

$providerAction = 'RegisterNameserver';
```

## `domain_reseller_registrar_ModifyNameserver`

```php
function domain_reseller_registrar_ModifyNameserver(array $params): array
```

Forwards the full WHMCS `$params` array to `ModifyNameserver`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS child nameserver update request plus module config. |

Example:

```php
<?php

$providerAction = 'ModifyNameserver';
```

## `domain_reseller_registrar_DeleteNameserver`

```php
function domain_reseller_registrar_DeleteNameserver(array $params): array
```

Forwards the full WHMCS `$params` array to `DeleteNameserver`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS child nameserver delete request plus module config. |

Example:

```php
<?php

$providerAction = 'DeleteNameserver';
```

## `domain_reseller_registrar_GetDNS`

```php
function domain_reseller_registrar_GetDNS(array $params): array
```

Returns the provider DNS payload directly.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS DNS lookup request plus module config. |

Example:

```php
<?php

$providerAction = 'GetDNS';
```

## `domain_reseller_registrar_SaveDNS`

```php
function domain_reseller_registrar_SaveDNS(array $params): array
```

Forwards the full WHMCS DNS payload to the provider.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS DNS save request plus module config. |

Example:

```php
<?php

$providerAction = 'SaveDNS';
```

## Notes

- Source file: `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`
- `GetNameservers()` guarantees five output keys even if the provider returns fewer.
- Glue record and DNS callbacks do not normalize payloads; your provider must understand the WHMCS-style fields it receives.

Related pages:

- [Contacts and Suggestions](/docs/api-reference/contacts-and-suggestions)
- [Provider API Transport](/docs/provider-api-transport)
