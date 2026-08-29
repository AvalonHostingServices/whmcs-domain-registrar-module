---
title: "Hooks and Assets"
description: "Admin-area hook behavior, self-update, error reporting, and the stylesheet shipped with the module."
---

The repository has one non-registrar callback file: `modules/registrars/domain_reseller_registrar/hooks.php`. It registers an admin hook that injects the module stylesheet into the WHMCS admin area, and runs the daily self-update check.

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

## `DailyCronJob` hook — self-update

```php
add_hook('DailyCronJob', 1, 'drr_check_update');
```

`drr_check_update()` runs once a day and checks **this repository's GitHub Releases** — never the reseller API — for a newer version than the running `DRR_VERSION`.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `vars` | `array` | — | WHMCS cron hook variables. The source does not use them. |

Behavior:

1. Reads the module's own config (`getRegistrarConfigOptions()`); if `autoUpdate` is explicitly off, returns immediately without any network call.
2. Calls `GET https://api.github.com/repos/AvalonHostingServices/whmcs-domain-registrar-module/releases/latest`.
3. Compares the release's tag against `DRR_VERSION` (`drr_is_newer_version()`, tolerant of a leading `v`); returns if not newer.
4. Downloads the release's `whmcs-domain-registrar-module-<tag>.zip` asset and its `.sha256` checksum asset, and verifies the zip's SHA-256 digest with `hash_equals()` **before extracting anything**. A mismatch aborts the update.
5. Extracts the verified zip and copies its files over the installed module directory (`drr_copy_and_rename_files()`), preserving the reseller's configured `DisplayName` and any existing `logo.png`, and renaming the main PHP file to match the installed folder name (for white-labeled installs).
6. Reports any failure at any step (`drr_report_error()`) and logs it via `logActivity()`.

Example — disabling self-update for one install:

```php
<?php

// In WHMCS: System Settings > Domain Registrars > Avalon Hosting Services,
// uncheck "Automatic Updates". drr_check_update() then no-ops on every cron run.
```

<Callout type="warn">Self-update deliberately never trusts the reseller API to supply update code — only this repository's own signed release assets, checksum-verified before extraction. If you fork this module for white-labeling, point `DRR_GITHUB_REPO` at your own fork's releases (and publish matching `.sha256` assets), or disable `autoUpdate` entirely.</Callout>

## Error reporting — GlitchTip

`drr_report_error($action, $message, $context = [])`, defined in `domain_reseller_registrar.php` alongside `reseller_callAPI()`, posts genuine transport failures — cURL errors, malformed JSON, an unexpected non-2xx response, and self-update failures — to a fixed GlitchTip project maintained by Avalon Hosting Services, using the Sentry v7 "store" protocol over the same `drr_http_request()` seam as every other network call.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `action` | `string` | — | The registrar action or lifecycle event that failed (e.g. `RenewDomain`, `check_module_update`). |
| `message` | `string` | — | Short human-readable description of the failure. |
| `context` | `array` | `[]` | Extra diagnostic fields — HTTP code, cURL error text, tag name, etc. |

Always-on, no config toggle — but always fails silently (wrapped in try/catch), so a reporting hiccup can never break a registrar call, and only curated, non-PII context is ever sent (never raw `$params`/`$apiParams`, contact details, or API credentials). Documented business errors are **not** reported — see [Provider API Transport](/docs/provider-api-transport) for how that's distinguished from a genuine failure now that the provider documents every business error as non-2xx.

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
  - `modules/registrars/domain_reseller_registrar/hooks.php` (`drr_check_update()`, `drr_is_newer_version()`, `drr_copy_and_rename_files()`, `drr_recursive_delete()`)
  - `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php` (`drr_http_request()`, `drr_report_error()` — `hooks.php` depends on `DRR_VERSION` being defined and `require_once`s this file if it isn't, since only `hooks.php` is guaranteed loaded on every WHMCS request)
  - `modules/registrars/domain_reseller_registrar/css/style.css`
- The hook computes the stylesheet URL dynamically from `__DIR__` and `$_SERVER['DOCUMENT_ROOT']`.

Related pages:

- [Architecture](/docs/architecture)
- [Troubleshooting and Logging](/docs/guides/troubleshooting-and-logging)
