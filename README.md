# registrar-mcp-server

An MCP (Model Context Protocol) server for the [Avalon Hosting Services Domain Reseller Registrar Module](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module). Exposes the Avalon Domain Reseller API to LLMs, enabling AI assistants to check availability, manage nameservers, contacts, locks, transfers, and domain lifecycle — all through well-typed, safety-gated tools.

> **WHMCS Module**: [AvalonHostingServices/whmcs-domain-registrar-module](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module)  
> **WHMCS Marketplace**: [Domain Reseller Module for WHMCS — Avalon Hosting Services](https://marketplace.whmcs.com/product/5396-domain-reseller-module-for-whmcs-avalon-hosting-services)  
> **Current stable module release**: v2.0.1

---

## Features

- **17 tools** covering the full registrar API surface
- **Strict Zod input validation** — rejects unknown fields, enforces types and ranges
- **Safety gates** — destructive operations require `confirm: "I_CONFIRM"` and a UUID `client_request_id`
- **Boolean normalization** — accepts `true`/`false`/`"1"`/`"yes"` for boolean fields
- **Contact role normalization** — `Technical` and `tech` both map correctly upstream
- **Dual transport** — `stdio` for local use (Claude Desktop, MCP Inspector), `http` for hosted/multi-client use
- **Structured error classes** — `auth_error`, `rate_limit`, `timeout`, `upstream_contract_violation`, etc.
- **Response truncation** — large responses are capped at 25,000 characters with a pagination hint
- **Markdown + JSON output** — read tools accept a `response_format` param

---

## Prerequisites

- Node.js ≥ 18
- npm ≥ 9
- A running instance of the registrar JSON API (see [API.md](./API.md))

---

## Installation

```bash
git clone https://github.com/AvalonHostingServices/whmcs-domain-registrar-module.git
cd whmcs-domain-registrar-module
npm install
npm run build
```

> The MCP server source lives in the root of the WHMCS module repository.

---

## Configuration

All configuration is via environment variables. No secrets are stored in code.

| Variable            | Required | Description                                       |
| ------------------- | -------- | ------------------------------------------------- |
| `REGISTRAR_API_URL` | ✅        | Full URL of the registrar JSON API endpoint       |
| `REGISTRAR_API_KEY` | ✅        | API key sent in every upstream request envelope   |
| `TRANSPORT`         | ❌        | `stdio` (default) or `http`                       |
| `PORT`              | ❌        | HTTP port when `TRANSPORT=http` (default: `3000`) |

---

## Running

### stdio (local — Claude Desktop, MCP Inspector)

```bash
REGISTRAR_API_URL=https://your-registrar-api.example.com \
REGISTRAR_API_KEY=your_secret_key \
npm start
```

### Streamable HTTP (hosted / multi-client)

```bash
REGISTRAR_API_URL=https://your-registrar-api.example.com \
REGISTRAR_API_KEY=your_secret_key \
TRANSPORT=http \
PORT=3000 \
npm start
```

The server will listen at `http://localhost:3000/mcp`.  
A health check endpoint is available at `GET /health`.

### Development (auto-reload)

```bash
REGISTRAR_API_URL=... REGISTRAR_API_KEY=... npm run dev
```

---

## Testing with MCP Inspector

```bash
npx @modelcontextprotocol/inspector node dist/index.js
```

Set the environment variables in the Inspector's env panel before connecting.

---

## Claude Desktop Integration

Add to your `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "registrar": {
      "command": "node",
      "args": ["/absolute/path/to/registrar-mcp-server/dist/index.js"],
      "env": {
        "REGISTRAR_API_URL": "https://your-registrar-api.example.com",
        "REGISTRAR_API_KEY": "your_secret_key"
      }
    }
  }
}
```

---

## Tool Reference

### Read Tools

All read tools support a `response_format` parameter: `"markdown"` (default, human-readable) or `"json"` (machine-readable).

#### `registrar_check_availability`
Check whether a domain name is available for registration.

| Param             | Type   | Required | Description                          |
| ----------------- | ------ | -------- | ------------------------------------ |
| `domainid`        | number | ✅        | WHMCS domain ID                      |
| `domainname`      | string | ✅        | Full domain name, e.g. `example.com` |
| `domain`          | string | ✅        | SLD portion only, e.g. `example`     |
| `response_format` | string | ❌        | `markdown` \| `json`                 |

---

#### `registrar_get_nameservers`
Retrieve the nameservers currently configured for a domain.

| Param             | Type   | Required | Description          |
| ----------------- | ------ | -------- | -------------------- |
| `domainid`        | number | ✅        | WHMCS domain ID      |
| `domainname`      | string | ✅        | Full domain name     |
| `response_format` | string | ❌        | `markdown` \| `json` |

Returns: `ns1`–`ns5` (empty string if slot is unused).

---

#### `registrar_get_contact_details`
Get contact details for all roles (Registrant, Admin, Tech, Billing).

| Param             | Type   | Required | Description          |
| ----------------- | ------ | -------- | -------------------- |
| `domainid`        | number | ✅        | WHMCS domain ID      |
| `domainname`      | string | ✅        | Full domain name     |
| `response_format` | string | ❌        | `markdown` \| `json` |

---

#### `registrar_get_epp_code`
Retrieve the EPP / transfer authorization code. Treat the returned value as sensitive.

| Param        | Type   | Required | Description      |
| ------------ | ------ | -------- | ---------------- |
| `domainid`   | number | ✅        | WHMCS domain ID  |
| `domainname` | string | ✅        | Full domain name |

---

#### `registrar_get_registrar_lock`
Get the current registrar (transfer) lock status.

| Param             | Type   | Required | Description          |
| ----------------- | ------ | -------- | -------------------- |
| `domainid`        | number | ✅        | WHMCS domain ID      |
| `domainname`      | string | ✅        | Full domain name     |
| `response_format` | string | ❌        | `markdown` \| `json` |

---

#### `registrar_sync_domain`
Synchronize the registration state of a domain — active, cancelled, transferred away, expiry date.

| Param             | Type   | Required | Description                         |
| ----------------- | ------ | -------- | ----------------------------------- |
| `domainid`        | number | ✅        | WHMCS domain ID                     |
| `domainname`      | string | ✅        | Full domain name                    |
| `sld`             | string | ✅        | Second-level domain, e.g. `example` |
| `tld`             | string | ✅        | Top-level domain, e.g. `com`        |
| `response_format` | string | ❌        | `markdown` \| `json`                |

---

#### `registrar_sync_transfer`
Synchronize the transfer status for a domain currently being transferred.

| Param             | Type   | Required | Description          |
| ----------------- | ------ | -------- | -------------------- |
| `domainid`        | number | ✅        | WHMCS domain ID      |
| `domainname`      | string | ✅        | Full domain name     |
| `sld`             | string | ✅        | Second-level domain  |
| `tld`             | string | ✅        | Top-level domain     |
| `response_format` | string | ❌        | `markdown` \| `json` |

---

#### `registrar_get_tld_pricing`
Retrieve TLD pricing for import into WHMCS.

| Param             | Type   | Required | Description                     |
| ----------------- | ------ | -------- | ------------------------------- |
| `currency`        | string | ✅        | WHMCS currency code, e.g. `USD` |
| `response_format` | string | ❌        | `markdown` \| `json`            |

---

### Write Tools

Write tools require a `client_request_id` (UUID v4) for idempotency.  
Destructive tools additionally require `confirm: "I_CONFIRM"` as an explicit safety gate.

#### `registrar_register_domain` ⚠️ Destructive
Register a new domain. May incur charges.

| Param               | Type          | Required | Description                                                  |
| ------------------- | ------------- | -------- | ------------------------------------------------------------ |
| `domainid`          | number        | ✅        | WHMCS domain ID                                              |
| `domainname`        | string        | ✅        | Full domain name                                             |
| `regperiod`         | number        | ✅        | Years (1–10)                                                 |
| `dnsmanagement`     | boolean       | ✅        | Enable DNS management                                        |
| `emailforwarding`   | boolean       | ✅        | Enable email forwarding                                      |
| `idprotection`      | boolean       | ✅        | Enable WHOIS privacy                                         |
| `contacts`          | object        | ✅        | Map of `registrant`/`admin`/`tech`/`billing` contact objects |
| `client_request_id` | string (UUID) | ✅        | Idempotency key                                              |
| `confirm`           | `"I_CONFIRM"` | ✅        | Destructive action confirmation                              |

---

#### `registrar_transfer_domain` ⚠️ Destructive
Initiate a domain transfer. May incur charges.

| Param               | Type          | Required | Description                |
| ------------------- | ------------- | -------- | -------------------------- |
| `domainid`          | number        | ✅        |                            |
| `domainname`        | string        | ✅        |                            |
| `eppcode`           | string        | ✅        | Transfer auth code         |
| `regperiod`         | number        | ✅        | Years (1–10)               |
| `dnsmanagement`     | boolean       | ✅        |                            |
| `emailforwarding`   | boolean       | ✅        |                            |
| `idprotection`      | boolean       | ✅        |                            |
| `nameservers`       | string[]      | ✅        | Min 2 nameserver hostnames |
| `contacts`          | object        | ✅        | Contact map                |
| `client_request_id` | string (UUID) | ✅        |                            |
| `confirm`           | `"I_CONFIRM"` | ✅        |                            |

---

#### `registrar_renew_domain` ⚠️ Destructive
Renew a domain registration. May incur charges.

| Param               | Type          | Required | Description  |
| ------------------- | ------------- | -------- | ------------ |
| `domainid`          | number        | ✅        |              |
| `domainname`        | string        | ✅        |              |
| `regperiod`         | number        | ✅        | Years (1–10) |
| `client_request_id` | string (UUID) | ✅        |              |
| `confirm`           | `"I_CONFIRM"` | ✅        |              |

---

#### `registrar_set_nameservers`
Update nameservers for a domain. `ns1` and `ns2` are required.

| Param               | Type          | Required | Description |
| ------------------- | ------------- | -------- | ----------- |
| `domainid`          | number        | ✅        |             |
| `domainname`        | string        | ✅        |             |
| `ns1`               | string        | ✅        |             |
| `ns2`               | string        | ✅        |             |
| `ns3`–`ns5`         | string        | ❌        |             |
| `client_request_id` | string (UUID) | ✅        |             |

---

#### `registrar_set_contact_details` ⚠️ Destructive
Update contact details for one or more roles.

| Param               | Type          | Required | Description                  |
| ------------------- | ------------- | -------- | ---------------------------- |
| `domainid`          | number        | ✅        |                              |
| `domainname`        | string        | ✅        |                              |
| `contactdetails`    | object        | ✅        | Map of role → contact fields |
| `client_request_id` | string (UUID) | ✅        |                              |
| `confirm`           | `"I_CONFIRM"` | ✅        |                              |

Contact field keys: `First_Name`, `Last_Name`, `Company_Name`, `Email`, `Address_1`, `Address_2`, `City`, `State`, `Zip`, `Country` (2-letter ISO), `Phone` (E.164 format).

---

#### `registrar_set_registrar_lock`
Enable or disable the registrar transfer lock.

| Param               | Type          | Required | Description                         |
| ------------------- | ------------- | -------- | ----------------------------------- |
| `domainid`          | number        | ✅        |                                     |
| `domainname`        | string        | ✅        |                                     |
| `lockstatus`        | boolean       | ✅        | `true` = locked, `false` = unlocked |
| `client_request_id` | string (UUID) | ✅        |                                     |

---

#### `registrar_toggle_id_protect`
Enable or disable WHOIS ID protection.

| Param               | Type          | Required | Description                        |
| ------------------- | ------------- | -------- | ---------------------------------- |
| `domainid`          | number        | ✅        |                                    |
| `domainname`        | string        | ✅        |                                    |
| `idprotect`         | boolean       | ✅        | `true` = enable, `false` = disable |
| `client_request_id` | string (UUID) | ✅        |                                    |

---

#### `registrar_release_domain_tag` ⚠️ Destructive
Change the IPS tag / release to another registrar (registry-dependent, e.g. `.uk`). May be irreversible.

| Param               | Type          | Required | Description |
| ------------------- | ------------- | -------- | ----------- |
| `domainid`          | number        | ✅        |             |
| `domainname`        | string        | ✅        |             |
| `newtag`            | string        | ✅        | New IPS tag |
| `client_request_id` | string (UUID) | ✅        |             |
| `confirm`           | `"I_CONFIRM"` | ✅        |             |

---

#### `registrar_request_delete` ⚠️ Destructive — Irreversible
Submit a domain deletion request. Once processed by the registry this cannot be undone.

| Param               | Type          | Required | Description |
| ------------------- | ------------- | -------- | ----------- |
| `domainid`          | number        | ✅        |             |
| `domainname`        | string        | ✅        |             |
| `client_request_id` | string (UUID) | ✅        |             |
| `confirm`           | `"I_CONFIRM"` | ✅        |             |

---

## Error Reference

Errors are returned as structured MCP tool errors with a descriptive prefix.

| Prefix                        | Cause                             | Action                                |
| ----------------------------- | --------------------------------- | ------------------------------------- |
| `auth_error`                  | Invalid or missing API key (401)  | Check `REGISTRAR_API_KEY`             |
| `permission_denied`           | Key lacks permission (403)        | Check API key scope                   |
| `not_found`                   | Endpoint not found (404)          | Check `REGISTRAR_API_URL`             |
| `rate_limit`                  | Too many requests (429)           | Wait and retry                        |
| `timeout`                     | Upstream did not respond in time  | Retry once; check network             |
| `connection_error`            | Could not reach upstream          | Check `REGISTRAR_API_URL` and network |
| `registrar_error`             | Upstream returned `status: error` | See the message for details           |
| `upstream_contract_violation` | Response missing `status` field   | Check API implementation              |
| `unexpected_error`            | Unclassified error                | See full message                      |

---

## Security Notes

- `REGISTRAR_API_KEY` is never echoed in tool outputs or logs.
- All inputs are validated via Zod `.strict()` schemas — unknown fields are rejected.
- Destructive tools require explicit `confirm: "I_CONFIRM"` to prevent accidental LLM-triggered mutations.
- All write tools require a `client_request_id` UUID to prevent duplicate submissions.
- When using `TRANSPORT=http` locally, bind to `127.0.0.1` and avoid exposing the port publicly without authentication middleware.

---

## Project Structure

```
src/
├── index.ts                  — Server entry point (stdio + HTTP transport)
├── constants.ts              — Shared limits, timeouts, role aliases
├── types.ts                  — TypeScript interfaces and enums
├── schemas/
│   └── common.ts             — Zod schemas (domainid, domainname, contacts, safety tokens)
├── services/
│   ├── registrarClient.ts    — Upstream HTTP client + error mapping
│   └── errors.ts             — toolError / toolText response helpers
└── tools/
    ├── readTools.ts          — 8 read-only tools
    └── writeTools.ts         — 9 write tools with safety gates
```

---

## Related Documentation

### This Repository

- [API.md](./API.md) — Upstream registrar API contract (request envelope, all actions, data shapes)

### WHMCS Module (AvalonHostingServices/whmcs-domain-registrar-module)

- [README.md](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/blob/main/README.md) — Module overview and quick start
- [INSTALL.md](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/blob/main/INSTALL.md) — Full installation and WHMCS activation guide
- [DOCUMENTATION.md](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/blob/main/DOCUMENTATION.md) — Central documentation index
- [API.md](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/blob/main/API.md) — Canonical API reference
- [CHANGELOG.md](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/blob/main/CHANGELOG.md) — Version history
- [SECURITY.md](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/blob/main/SECURITY.md) — Security reporting process
- [CONTRIBUTING.md](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/blob/main/CONTRIBUTING.md) — Contribution guidelines

### External

- [WHMCS Marketplace Listing](https://marketplace.whmcs.com/product/5396-domain-reseller-module-for-whmcs-avalon-hosting-services)
- [Avalon Hosting Services](https://avalonhosting.services/)
- [Model Context Protocol Specification](https://modelcontextprotocol.io/)
