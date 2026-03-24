# Domain Reseller Registrar Module for WHMCS

Official repository for the Avalon Hosting Services Domain Reseller Registrar Module.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![WHMCS](https://img.shields.io/badge/WHMCS-8.0+-green)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)

- Marketplace listing: [WHMCS Marketplace Listing](https://marketplace.whmcs.com/product/5396-domain-reseller-module-for-whmcs-avalon-hosting-services)
- Current stable release: **v2.0.1**

## Overview

This module connects WHMCS domain registrar functions to the Avalon Domain Reseller API.

Key capabilities:

- Register and transfer domains
- Renew and request domain deletion
- Manage nameservers and DNS records
- Manage domain contact details
- Get EPP/Auth codes
- Toggle ID protection and registrar lock
- Domain sync and transfer sync support
- Import TLD pricing from provider API

## Requirements

- WHMCS with registrar module support
- PHP cURL extension enabled
- Valid API endpoint and API key from Avalon Hosting Services

## Quick Installation

1. Download or clone this repository.
2. Copy `modules/registrars/domain_reseller_registrar` into your WHMCS installation under `modules/registrars/`.
3. In WHMCS admin, go to **System Settings > Domain Registrars**.
4. Activate **Avalon Hosting Services**.
5. Configure API Endpoint and API Key.

For the full walkthrough, see [INSTALL.md](INSTALL.md).

## Documentation

- [DOCUMENTATION.md](DOCUMENTATION.md): Central documentation index
- [INSTALL.md](INSTALL.md): Full installation and activation guide
- [API.md](API.md): API request envelope, actions, and data contracts
- [CHANGELOG.md](CHANGELOG.md): Version history
- [RELEASE_PROCESS.md](RELEASE_PROCESS.md): Release and packaging workflow
- [SECURITY.md](SECURITY.md): Security reporting process
- [CONTRIBUTING.md](CONTRIBUTING.md): Contribution guidelines

## Versioning

This project uses semantic version tags (for example, `v2.0.1`).

## Support

- Website: [avalonhosting.services](https://avalonhosting.services)
- Support: [avalonhosting.services](https://avalonhosting.services)

## License

See [LICENSE](LICENSE).
