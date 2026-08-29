# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A single WHMCS domain registrar module ("Avalon Hosting Services") that lets WHMCS delegate all domain
registrar operations (register, transfer, renew, DNS, nameservers, contacts, locks, EPP codes, TLD pricing
sync, etc.) to a remote reseller API over HTTPS/JSON. The module itself does no registry work — it is a thin
adapter that maps WHMCS's registrar function calls into a fixed JSON request envelope and maps the API's JSON
response back into the shape WHMCS expects.

Everything lives under one path, which must never move:

```
modules/registrars/domain_reseller_registrar/
├── domain_reseller_registrar.php   # all module logic (WHMCS registrar function hooks)
├── hooks.php                       # admin CSS injection + DailyCronJob self-update
├── css/style.css                   # small admin UI stylesheet
├── logo.png
└── whmcs.json                      # marketplace/module metadata (name, version, license, links)
```

## Commands

There is no build step, package manager, or test suite — this is plain PHP designed to be dropped directly
into a WHMCS installation.

- **Syntax check** (what CI runs, via `.github/workflows/lint.yml` across PHP 8.1–8.4):
  ```bash
  php -l modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php
  php -l modules/registrars/domain_reseller_registrar/hooks.php
  ```
- **No unit tests exist.** Functional verification requires a real WHMCS install with the module activated
  under System Settings > Domain Registrars (see INSTALL.md).
- **Release packaging** is fully automated by `.github/workflows/release.yml`: pushing a `v*` tag zips/tars
  *only* `modules/registrars/domain_reseller_registrar/` and publishes a GitHub Release. Don't hand-build
  release artifacts; a local `dist/` zip already exists from a past run.

## Architecture

### Single request/response contract

Every WHMCS registrar function in `domain_reseller_registrar.php` funnels through one helper,
`reseller_callAPI($params, $action, $apiParams)`. It:

1. POSTs JSON to the configured `customApiEndpoint` (`{"api_key", "action", "params"}`), 60s timeout.
2. Optionally logs the call via WHMCS's `logModuleCall()` when the `moduleLog` config option is enabled.
3. Treats `{"status": "success", "data": {...}}` as success (returns `data`), anything else as an error
   (returns `['error' => message, 'details' => decodedResponse]`).

Every `domain_reseller_registrar_<Action>($params)` function follows the same shape: build `$apiParams` from
WHMCS's `$params`, call `reseller_callAPI`, then translate the result into whatever return shape that
specific WHMCS registrar hook expects (`['success' => true]`, an array of fields, a `ResultsList`, etc.).
The full wire contract (actions, required params, expected response keys, examples) is documented in
[API.md](API.md) — treat that file as the source of truth for the remote API shape, and update it whenever
an action's request/response mapping changes here.

### Where the complexity lives

- **`GetContactDetails`**: normalizes multiple possible upstream response shapes into WHMCS's expected
  labels (handles both `Technical` and `Tech` keys, both `Full_Name` and separate `First_Name`/`Last_Name`,
  multiple address/zip/phone key spellings via `??` fallback chains). Follow the existing fallback-chain
  pattern if the upstream API adds another field spelling — don't assume one canonical key.
- **`RegisterDomain` / `TransferDomain`**: build a `contacts` object with `registrant`/`admin`/`tech`/`billing`
  sub-objects pulled from WHMCS's flat `$params` (e.g. `adminfirstname`, `techemail`). Note `registrant` is
  passed as the raw `$params` array, not a curated subset — the remote API is expected to pick out the fields
  it needs.
- **`GetTldPricing`**: the only function using WHMCS's `ImportItem`/`ResultsList` classes and querying
  `tblcurrencies` via `Capsule` directly (for the default currency code). It filters out any TLD with no
  positive `register` 1yr price, derives min/max registration years from whichever `Nyr` keys the API
  returned per TLD, and only calls `setYears()` when the available years are non-contiguous.
- **Nameserver/DNS passthrough functions** (`RegisterNameserver`, `ModifyNameserver`, `DeleteNameserver`,
  `GetDNS`, `SaveDNS`, `GetDomainSuggestions`): forward WHMCS's `$params` to the remote API rather than
  building a curated `$apiParams` — keep this passthrough behavior consistent if adding similar functions.
  They still run `$params` through `drr_strip_config_params()` first to drop `customApiEndpoint`/
  `customApiKey`/`moduleLog` — never forward those raw, since `moduleLog` writes `request_params` verbatim
  into the WHMCS Module Log.
- **Self-update** (`hooks.php`, `drr_check_update()`): a `DailyCronJob` hook that calls the provider API's
  `check_module_update` action and, if a newer version is offered, downloads and installs it in place,
  preserving the reseller's `DisplayName` and `logo.png`. Depends on `DRR_VERSION` being defined — it
  `require_once`s the main module file if not. Not currently opt-out-able from the WHMCS admin UI.
- **Error reporting** (`drr_report_error()` in `domain_reseller_registrar.php`): posts genuine transport
  failures (cURL errors, invalid JSON, non-2xx provider responses, auto-update exceptions) to a fixed
  GlitchTip project via the Sentry v7 store protocol, using the DSN in `DRR_GLITCHTIP_DSN`. Always-on, no
  config toggle. Only ever pass curated, non-PII context (action name, HTTP code, error message) — never
  raw `$params`/`$apiParams`, and it must fail silently (wrapped in try/catch) so a reporting hiccup can
  never break a registrar call. Documented business errors (2xx + `status: error`) are intentionally *not*
  reported — see [API.md](API.md#error-reporting).

### Config

Module settings (`domain_reseller_registrar_getConfigArray()`) are exactly three fields: `customApiEndpoint`
(text), `customApiKey` (password), `moduleLog` (yesno). These are always present in `$params` on every call
and are what `reseller_callAPI` reads — don't rename them without updating both the config array and every
call site. `DRR_GLITCHTIP_DSN` is a separate, hardcoded constant (not a reseller-configurable setting) —
it's Avalon's own monitoring endpoint, the same for every install.

### Docs map

- [README.md](README.md) — overview, quick install, links to everything else
- [INSTALL.md](INSTALL.md) — step-by-step WHMCS activation
- [API.md](API.md) — the full remote API contract (request/response envelope per action) — **the file to
  update when changing what `reseller_callAPI` sends or expects**
- [RELEASE_PROCESS.md](RELEASE_PROCESS.md) — tag-triggered release flow, version bump locations
  (`whmcs.json`, `README.md`, `CHANGELOG.md`)
- [CONTRIBUTING.md](CONTRIBUTING.md) — commit message convention (`fix:`, `feat:`, `docs:`), module path
  must stay `modules/registrars/domain_reseller_registrar`
