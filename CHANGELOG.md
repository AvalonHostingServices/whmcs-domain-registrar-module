# Changelog

All notable changes to `registrar-mcp-server` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).  
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] — 2026-04-24

### Added

- Initial release of `registrar-mcp-server`.
- **8 read-only tools** (`registrar_check_availability`, `registrar_get_nameservers`,
  `registrar_get_contact_details`, `registrar_get_epp_code`, `registrar_get_registrar_lock`,
  `registrar_sync_domain`, `registrar_sync_transfer`, `registrar_get_tld_pricing`).
- **9 write tools** (`registrar_register_domain`, `registrar_transfer_domain`,
  `registrar_renew_domain`, `registrar_set_nameservers`, `registrar_set_contact_details`,
  `registrar_set_registrar_lock`, `registrar_toggle_id_protect`,
  `registrar_release_domain_tag`, `registrar_request_delete`).
- Zod strict schemas with E.164 phone, UUID v4, and FQDN validation.
- Safety gate pattern: destructive tools require `confirm: "I_CONFIRM"` and a `client_request_id` UUID.
- Typed error classes: `auth_error`, `permission_denied`, `not_found`, `rate_limit`,
  `timeout`, `connection_error`, `registrar_error`, `upstream_contract_violation`,
  `unexpected_error`.
- Dual transport: stdio (default) and Streamable HTTP (`TRANSPORT=http`).
- Health check endpoint at `GET /health` (HTTP transport).
- Built for the [Avalon Hosting Services Domain Reseller Registrar Module v2.0.1](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module).
