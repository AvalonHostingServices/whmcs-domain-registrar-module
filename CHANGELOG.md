# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

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
