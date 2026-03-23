# Domain Reseller Registrar Module for WHMCS

Official GitHub repository for the Avalon Hosting Services Domain Reseller Registrar Module.

Marketplace listing:
[WHMCS Marketplace Listing](https://marketplace.whmcs.com/product/5396-domain-reseller-module-for-whmcs-avalon-hosting-services)

Current stable release: **v2.0.1**

## Purpose

This repository is for WHMCS users and domain resellers who want to:

- Download the registrar module files
- Report bugs and request features
- Contribute improvements through pull requests

## Installation

1. Download or clone this repository.
2. Install the module using either method:
   - **Method A (copy folder):** Copy `modules/registrars/domain_reseller_registrar` into your WHMCS installation under `modules/registrars/`.
   - **Method B (zip extraction):** Download the project as ZIP and extract it at the root of your WHMCS installation so the `modules/registrars/domain_reseller_registrar` path is created correctly.
3. In WHMCS admin area, go to **System Settings > Domain Registrars** and activate **Avalon Hosting Services**.
4. Configure:
   - API Endpoint
   - API Key
   - Optional Module Log

## Requirements

- WHMCS with registrar module support
- PHP cURL extension enabled
- Valid Domain Reseller API endpoint and API key from Avalon Hosting Services

## Versioning

This project uses semantic versioning tags (for example, `v2.0.1`).

See [CHANGELOG.md](CHANGELOG.md) for release details.

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening pull requests.

## Security

Please report vulnerabilities through the process in [SECURITY.md](SECURITY.md).

## License

See [LICENSE](LICENSE).
