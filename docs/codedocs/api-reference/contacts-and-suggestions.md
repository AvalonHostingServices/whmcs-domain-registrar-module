---
title: "Contacts and Suggestions"
description: "Registrar callbacks for contact records and domain suggestion lookups."
---

These callbacks handle contact round-tripping and provider-powered suggestions. All functions are defined in `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`.

## `domain_reseller_registrar_GetContactDetails`

```php
function domain_reseller_registrar_GetContactDetails(array $params): array
```

Fetches provider contact records and normalizes them into WHMCS contact sections such as `Registrant`, `Billing`, `Technical` or `Tech`, and `Admin`, using the shared `drr_normalize_contact()` helper for each role.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS domain context and module config. |

The provider's response is a passthrough of WHMCS's own `DomainGetWhoisInfo`, so it actually arrives using WHMCS's space-separated field names already:

```php
<?php

$providerResponse = [
    'Registrant' => [
        'First Name' => 'John',
        'Last Name' => 'Doe',
        'Email Address' => 'john@example.com',
        'Postcode' => '78701',
    ],
];
```

## `domain_reseller_registrar_SaveContactDetails`

```php
function domain_reseller_registrar_SaveContactDetails(array $params): array
```

Normalizes WHMCS contact labels like `First Name` into provider keys like `First_Name` before posting them upstream.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS params containing `contactdetails`, domain context, and module config. |

Returns `['success' => true]` or `['error' => ...]`.

Example:

```php
<?php

$params['contactdetails'] = [
    'Admin' => [
        'First Name' => 'Jane',
        'Last Name' => 'Doe',
        'Email' => 'jane@example.com',
    ],
];
```

## `domain_reseller_registrar_GetDomainSuggestions`

```php
function domain_reseller_registrar_GetDomainSuggestions(array $params): array
```

Forwards the full WHMCS suggestion request to the provider and returns the provider payload directly.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `params` | `array` | — | WHMCS suggestion request and module config. |

Example:

```php
<?php

$providerAction = 'GetDomainSuggestions';
```

## Notes

- Source file: `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`
- `GetContactDetails()` accepts either `Technical` or `Tech` from the provider, and reads the real space-separated field names first (`"First Name"`, `"Email Address"`, `"Phone Number"`, `"Postcode"`), falling back to underscored/`Full_Name` spellings.
- The `Full_Name` split is a last resort and is lossy for complex names; the real provider already sends explicit `"First Name"`/`"Last Name"`.
- `SaveContactDetails()` rewrites WHMCS labels into provider-style (underscored) keys before transport, so debugging should inspect the outbound payload in Module Log rather than the original admin form labels. The provider accepts either style for this action.

## Common Pattern

In a real deployment, these functions are often used together. An operator opens the contact editor, WHMCS calls `GetContactDetails()`, the module normalizes the provider schema, the operator makes changes, and then `SaveContactDetails()` converts the edited data back into provider keys. `GetDomainSuggestions()` is separate from that lifecycle, but it shares the same pass-through transport behavior: the provider owns the suggestion algorithm, and the module simply brokers the request.

Related pages:

- [Contact Normalization](/docs/contact-normalization)
- [Nameservers and DNS](/docs/api-reference/nameservers-and-dns)
