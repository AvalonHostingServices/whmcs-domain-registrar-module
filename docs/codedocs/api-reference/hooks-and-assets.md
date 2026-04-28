---
title: "Hooks and Assets"
description: "Admin-area hook behavior and the stylesheet shipped with the module."
---

The repository has one non-registrar callback file: `modules/registrars/domain_reseller_registrar/hooks.php`. It registers an admin hook that injects the module stylesheet into the WHMCS admin area.

## Import Path

```php
modules/registrars/domain_reseller_registrar/hooks.php
```

## `AdminAreaHeaderOutput` hook

```php
add_hook('AdminAreaHeaderOutput', 1, function (array $vars): string { ... });
```

Builds a relative path to `css/style.css` and returns a `<link>` tag so the stylesheet loads in WHMCS admin.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `vars` | `array` | — | WHMCS admin template variables passed to the hook closure. The source does not use them. |

Return type:

```php
string
```

Example:

```php
<?php

add_hook('AdminAreaHeaderOutput', 1, function (array $vars): string {
    return '<link rel="stylesheet" href="/modules/registrars/domain_reseller_registrar/css/style.css" />';
});
```

## `css/style.css`

The stylesheet disables pointer interaction and reduces opacity for selected inputs:

- `#Registrantcustomwhois tbody tr td input`
- `#Registrantcustomwhois tbody tr td select`
- `#Technicalcustomwhois tbody tr td input[name="contactdetails[Technical][Contact Id]"]`
- `#Billingcustomwhois tbody tr td input[name="contactdetails[Billing][Contact Id]"]`
- `#Admincustomwhois tbody tr td input[name="contactdetails[Admin][Contact Id]"]`

Example behavior:

```css
#Registrantcustomwhois tbody tr td input {
    pointer-events: none;
    opacity: 0.5;
}
```

That makes the inputs effectively read-only from the operator's perspective.

## Why It Exists

The registrar module assumes some contact identifier fields are provider-managed and should not be edited casually in the WHMCS admin UI. Shipping the CSS as a hook-based asset keeps that behavior isolated from the main callback file and avoids changing WHMCS core templates.

## Operational Impact

This hook is easy to overlook because it does not talk to the provider API, but it still affects day-to-day support work. If an administrator reports that certain contact inputs are greyed out or cannot be clicked, that behavior is part of the module design. The right fix is usually to update the provider-side contact record or adjust the module stylesheet, not to search for a transport or authentication bug.

## Notes

- Source files:
  - `modules/registrars/domain_reseller_registrar/hooks.php`
  - `modules/registrars/domain_reseller_registrar/css/style.css`
- The hook computes the stylesheet URL dynamically from `__DIR__` and `$_SERVER['DOCUMENT_ROOT']`.

Related pages:

- [Architecture](/docs/architecture)
- [Troubleshooting and Logging](/docs/guides/troubleshooting-and-logging)
