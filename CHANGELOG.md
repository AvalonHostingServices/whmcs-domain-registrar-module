# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

## [2.3.0] - 2026-08-29

### Fixed

- `Sync` and `TransferSync` were always reported to WHMCS as failed, even on a successful sync — their
  provider response has no `status`/`data` envelope (a flat object, HTTP 200 either way), which
  `reseller_callAPI` didn't account for, and the wrapper functions used `isset($response['error'])` where
  the provider always includes an `error` key (empty string on success). Both are fixed.
- `GetContactDetails` read the wrong field names entirely (`Full_Name`, `Company_Name`, `Zip`, bare `Email`/
  `Phone`, …) — the provider actually returns WHMCS's own WHOIS-format space-separated keys (`"First Name"`,
  `"Email Address"`, `"Phone Number"`, `"Postcode"`, …), so registrant/admin/tech/billing contact fields came
  back empty. Now reads the real field names first, with the old spellings kept as fallbacks.
- `RegisterDomain` never forwarded the nameservers chosen at checkout to the provider, so every new
  registration silently ignored them. Now sent, matching `TransferDomain`'s existing behavior.
- `RegisterDomain`/`TransferDomain` no longer send a `contacts` object — the provider API does not accept
  one for these actions (it uses the reseller's own account contact, not the registering client's WHMCS
  profile), so sending it was needless PII exposure with no effect.
- `GetTldPricing` assumed TLD keys had no leading dot and year keys were formatted like `"1yr"`; the provider
  actually sends `".com"`-style keys and plain numeric year keys (`"1"`, `"2"`). Also, EPP-required detection
  read a `tld_features[tld].eppcode` field the provider does not send, so every TLD was silently imported as
  *not* requiring an EPP code — TLDs that actually need one could fail to prompt for it at transfer time.
  Every TLD now defaults to EPP-required unless the provider explicitly says otherwise.
- Error reporting to GlitchTip was gated on HTTP status (only non-2xx reported), on the assumption that
  documented business errors return 2xx. The provider's real contract returns **non-2xx for every business
  error** (redemption period, insufficient balance, etc.), which would have reported routine business errors
  as if they were transport failures. Reporting now keys off whether the response is a well-formed
  `{"status":"error","message":"..."}` body, not HTTP status.
- Self-update now verifies the downloaded release package against a published SHA-256 checksum before
  extracting anything, and no longer assumes a release zip's internal layout that didn't match how
  `.github/workflows/release.yml` actually packages it.

### Added

- **Self-update now sources from this repository's GitHub Releases**, not the reseller API — a separate,
  independently-versioned distribution channel, so the API is never trusted to supply update code. Adds a new
  "Automatic Updates" config option (`autoUpdate`, on by default) to opt out per install.
- Every request now carries a best-effort `locale` field so the provider can localize `message` text; the
  module's own small set of hardcoded fallback strings are now routed through a `drr_t()` translation table
  (English-only for now — a starting point for future locales, not a claim of full localization).
- `reseller_callAPI` now retries once on a connection failure that never reached the provider at all
  (DNS/connect failure) — never on a timeout mid-request or any response actually received.
- A small, dependency-free test suite under `tests/` (`php tests/run.php`, also run in CI) covering the
  bugs above and the self-update helpers.

## [2.2.0] - 2026-08-29

### Added in 2.2.0

- Error reporting: transport-level failures (cURL errors, malformed API responses, non-2xx provider
  responses, and auto-update failures) are now reported to a GlitchTip project maintained by Avalon Hosting
  Services, giving centralized visibility into integration issues across installs. Documented business-error
  responses (e.g. "domain in redemption period") are not reported. Only the action name, HTTP status, and
  error message are sent — never request payloads, contact details, domain names, or API credentials. See
  [API.md](API.md#error-reporting).
- `reseller_callAPI` now fails fast with a clear "Registrar module is not configured" error when the API
  Endpoint or API Key is empty, instead of surfacing a raw cURL DNS-resolution error.

### Fixed in 2.2.0

- `GetRegistrarLock` previously returned the provider's raw response unnormalized, assuming it already used
  WHMCS's internal `lockstatus` key. It now normalizes `lockstatus`/`status`/`lockenabled` from the provider
  into the key WHMCS expects. See [API.md](API.md#getregistrarlock).
- `RegisterNameserver`, `ModifyNameserver`, `DeleteNameserver`, `GetDNS`, `SaveDNS`, and
  `GetDomainSuggestions` were forwarding the full WHMCS params array — including `customApiKey` and
  `customApiEndpoint` — as the request payload, which also meant the API key could be written in plaintext
  into the WHMCS Module Log when logging was enabled. These now strip module-config keys before forwarding.
- `CheckAvailability` no longer emits a PHP warning when the provider response omits `status`; it now returns
  a clear error instead.

## [2.1.0] - 2026-08-29

### Added in 2.1.0

- Restored module self-update: a daily cron hook checks the provider API (`check_module_update`) and, when
  a newer version is available, downloads and installs it in place, preserving the configured `DisplayName`
  and existing `logo.png`.
- Restored per-TLD EPP-required detection in `GetTldPricing` via the provider's `tld_features` map, instead
  of assuming every TLD requires an EPP/Auth code.
- `APIVersion` is now driven by a single `DRR_VERSION` constant, kept in sync with the version in
  `whmcs.json`.
- Documentation index for easier navigation.
- API reference with concrete request/response examples.
- Installation guide for WHMCS setup and activation.

### Fixed in 2.1.0

- `RegisterDomain` and `TransferDomain` were sending the *admin* contact's postcode for the *tech* contact.
  The tech contact's own `techpostcode` is now used.

### Changed in 2.1.0

- Expanded repository documentation structure and cross-linking.

## [2.0.1] - 2026-03-23

### Added in 2.0.1

- Public repository community files for issue reporting, feature requests, and pull requests.
- Contributor documentation and repository governance files.

### Changed in 2.0.1

- Module metadata now explicitly includes version `2.0.1` and repository support links.

---

Tag recommendation: `v2.2.0`
