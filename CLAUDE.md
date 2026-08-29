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

There is no build step or package manager — this is plain PHP designed to be dropped directly into a WHMCS
installation. There is a small, dependency-free test suite under `tests/` (no PHPUnit/composer, to match that
"no build step" design); it is never packaged into a release.

- **Syntax check** (what CI runs, via `.github/workflows/lint.yml` across PHP 8.1–8.4):
  ```bash
  php -l modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php
  php -l modules/registrars/domain_reseller_registrar/hooks.php
  ```
- **Test suite** (also run by CI): `php tests/run.php`. Covers `reseller_callAPI`'s response-shape branching
  (success/business-error/flat-envelope/retry), `GetTldPricing`'s parsing of the real provider shape, locale
  helpers, and the self-update copy/rename logic — all via a `tests/bootstrap.php` that stubs the handful of
  WHMCS globals involved (`add_hook`, `logActivity`, `logModuleCall`, WHMCS's `ImportItem`/`ResultsList`/
  `Capsule`) and a queue-based fake for `drr_http_request()` so no real network call is ever made. Extend the
  fakes in `tests/bootstrap.php`/`tests/stubs/` rather than adding a second mocking approach.
- Beyond that, functional verification requires a real WHMCS install with the module activated under System
  Settings > Domain Registrars (see INSTALL.md).
- **Release packaging** is fully automated by `.github/workflows/release.yml`: pushing a `v*` tag zips/tars
  *only* `modules/registrars/domain_reseller_registrar/` and publishes a GitHub Release. Don't hand-build
  release artifacts; a local `dist/` zip already exists from a past run.

## Architecture

### Single request/response contract

Every WHMCS registrar function in `domain_reseller_registrar.php` funnels through one helper,
`reseller_callAPI($params, $action, $apiParams)`. It:

1. POSTs JSON to the configured `customApiEndpoint` (`{"api_key", "action", "locale", "params"}`) via
   `drr_http_request()` — a thin `curl_setopt_array()` wrapper shared with GlitchTip reporting and the
   GitHub self-update calls, and the seam `tests/bootstrap.php` stubs out. 60s timeout, retried once, and
   only when the request never reached the provider at all (DNS/connect failure) — never on a timeout
   mid-request or any response actually received, since register/transfer/renew aren't safe to resend blind.
2. Optionally logs the call via WHMCS's `logModuleCall()` when the `moduleLog` config option is enabled.
3. Treats `{"status": "success", "data": {...}}` as success (returns `data`), anything else as an error
   (returns `['error' => message, 'details' => decodedResponse]`, plus `error_code` when the provider sends
   one). `Sync`/`TransferSync` are handled separately — see below — since their response has no envelope at
   all.

Every `domain_reseller_registrar_<Action>($params)` function follows the same shape: build `$apiParams` from
WHMCS's `$params`, call `reseller_callAPI`, then translate the result into whatever return shape that
specific WHMCS registrar hook expects (`['success' => true]`, an array of fields, a `ResultsList`, etc.).
The full wire contract (actions, required params, expected response keys, examples) is documented in
[API.md](API.md) — treat that file as the source of truth for the remote API shape, and update it whenever
an action's request/response mapping changes here.

### Where the complexity lives

- **`GetContactDetails`**: normalizes the provider's response into WHMCS's expected labels via
  `drr_normalize_contact()`, one shared helper for all four roles (`Registrant`/`Admin`/`Technical`-or-`Tech`/
  `Billing`) instead of four near-duplicated blocks. The provider's response is a direct passthrough of
  WHMCS's own `DomainGetWhoisInfo`, so it uses **space-separated** keys (`"First Name"`, `"Email Address"`,
  `"Phone Number"`, `"Postcode"`, …) — those are checked first; underscored/alternate spellings
  (`First_Name`, `Full_Name`, `Zip`, …) are kept only as fallbacks per the project's existing fallback-chain
  convention, in case a different backend or a future endpoint varies. Don't assume the underscored spellings
  are what the real API sends.
- **`RegisterDomain` / `TransferDomain`**: send only what the provider documents — `nameservers` (optional,
  up to 5, read from `$params['ns1']`..`['ns5']`) plus the plain scalar fields. They do **not** send a
  `contacts` object: the provider pulls WHOIS/contact data from the reseller's own account, not from the
  registering client's WHMCS profile, so a `contacts` payload here is both unused and needless PII exposure.
  `SaveContactDetails` is unrelated and unaffected — the provider does accept a `contactdetails` object there,
  in either underscored or space-separated form.
- **`GetTldPricing`**: the only function using WHMCS's `ImportItem`/`ResultsList` classes and querying
  `tblcurrencies` via `Capsule` directly (for the default currency code). It filters out any TLD with no
  positive `register` 1yr price, derives min/max registration years from whichever year keys the API
  returned per TLD, and only calls `setYears()` when the available years are non-contiguous. Two provider
  quirks this accounts for: TLD keys arrive **with a leading dot** (`.com`) and are stripped to WHMCS's bare
  form before import; and pricing years are keyed as plain numbers (`"1"`, `"2"`), with `"1yr"`-style keys
  accepted only as a fallback (`drr_tld_year_price()`/`drr_tld_available_years()`). `tld_features` reports
  addon enablement (`dnsmanagement`/`emailforwarding`/`idprotection`), **not** an EPP-required flag — the
  provider currently has no signal for that, so every TLD defaults to EPP-required (the safe choice; an
  explicit `tld_features[tld].eppcode` value, if the provider ever sends one, overrides the default).
- **Nameserver/DNS passthrough functions** (`RegisterNameserver`, `ModifyNameserver`, `DeleteNameserver`,
  `GetDNS`, `SaveDNS`, `GetDomainSuggestions`): forward WHMCS's `$params` to the remote API rather than
  building a curated `$apiParams` — keep this passthrough behavior consistent if adding similar functions.
  They still run `$params` through `drr_strip_config_params()` first to drop `customApiEndpoint`/
  `customApiKey`/`moduleLog` — never forward those raw, since `moduleLog` writes `request_params` verbatim
  into the WHMCS Module Log.
- **`Sync` / `TransferSync`**: the provider's response for these two actions has no `status`/`data` envelope
  at all — a flat JSON object, HTTP 200 on both success and failure, with an *always-present* `error` key
  (empty string on success). `reseller_callAPI` special-cases both actions (`DRR_FLAT_RESPONSE_ACTIONS`) to
  hand back the decoded body as-is instead of forcing it through the enveloped-response branch. The wrapper
  functions then check `!empty($response['error'])`, never `isset()` — `isset()` is true even for the
  empty-string success case, which previously made every sync report a failure.
- **Self-update** (`hooks.php`, `drr_check_update()`): a `DailyCronJob` hook, opt-out via the `autoUpdate`
  config field. It checks **this repository's GitHub Releases** directly (`DRR_GITHUB_REPO`), never the
  reseller API — a separate, independently-versioned distribution channel, so a compromised/misbehaving API
  endpoint can't push arbitrary code. It downloads the matching `whmcs-domain-registrar-module-<tag>.zip`
  release asset and verifies it against the release's `.sha256` asset (`hash_equals` on a SHA-256 digest)
  *before* extracting anything; a checksum mismatch aborts the update and reports it. Preserves the
  reseller's `DisplayName` and `logo.png`. Depends on `DRR_VERSION` (and `drr_http_request()`/
  `drr_report_error()`) being defined — it `require_once`s the main module file if not, since only
  `hooks.php` is guaranteed loaded on every WHMCS request.
- **Error reporting** (`drr_report_error()` in `domain_reseller_registrar.php`): posts genuine transport
  failures (cURL errors, invalid JSON, non-2xx provider responses that aren't a well-formed documented
  error, auto-update failures) to a fixed GlitchTip project via the Sentry v7 store protocol, using the DSN
  in `DRR_GLITCHTIP_DSN`, over the same `drr_http_request()` seam as everything else. Always-on, no config
  toggle. Only ever pass curated, non-PII context (action name, HTTP code, error message) — never raw
  `$params`/`$apiParams`, and it must fail silently (wrapped in try/catch) so a reporting hiccup can never
  break a registrar call. Documented business errors are intentionally *not* reported — but per the
  provider's actual contract, those arrive as **non-2xx**, not 2xx as an earlier version of this doc assumed,
  so the signal is "well-formed `{status:error, message:...}` body", not HTTP status — see
  [API.md](API.md#error-reporting).
- **Localization** (`drr_current_locale()` / `drr_t()`): every request carries a best-effort `locale` field
  (from `$params['language']`, then `$_SESSION['Language']`/`$_SESSION['adminlang']`, else `english`) so the
  provider can localize `message` text — WHMCS doesn't document a guaranteed locale source inside registrar
  functions, so treat this as a hint, not a guarantee. `drr_t()` is a small, currently English-only table for
  this module's *own* ~7 hardcoded fallback strings (config/transport errors) — add a locale key per string
  as translations become available; don't invent translations you can't verify.

### Config

Module settings (`domain_reseller_registrar_getConfigArray()`) are four fields: `customApiEndpoint` (text),
`customApiKey` (password), `moduleLog` (yesno), `autoUpdate` (yesno, defaults on). These are always present
in `$params` on every call and are what `reseller_callAPI`/`drr_check_update` read — don't rename them
without updating both the config array and every call site. `DRR_GLITCHTIP_DSN` and `DRR_GITHUB_REPO` are
separate, hardcoded constants (not reseller-configurable settings) — they're Avalon's own monitoring
endpoint and this repository, the same for every install.

### Docs map

- [README.md](README.md) — overview, quick install, links to everything else
- [INSTALL.md](INSTALL.md) — step-by-step WHMCS activation
- [API.md](API.md) — the full remote API contract (request/response envelope per action) — **the file to
  update when changing what `reseller_callAPI` sends or expects**
- [RELEASE_PROCESS.md](RELEASE_PROCESS.md) — tag-triggered release flow, version bump locations
  (`whmcs.json`, `README.md`, `CHANGELOG.md`)
- [CONTRIBUTING.md](CONTRIBUTING.md) — commit message convention (`fix:`, `feat:`, `docs:`), module path
  must stay `modules/registrars/domain_reseller_registrar`
