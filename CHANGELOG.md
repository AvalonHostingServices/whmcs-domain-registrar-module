# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

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

Tag recommendation: `v2.1.0`
