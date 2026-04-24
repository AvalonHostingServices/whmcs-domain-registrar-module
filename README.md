# Avalon Domain Reseller — AI Assistant (MCP)

Manage your Avalon Hosting Services reseller account using your AI assistant. Ask in plain English to check availability, register or transfer domains, update nameservers and contacts, manage locks, and more — all without leaving your AI client.

> Powered by the [Avalon Hosting Services Domain Reseller Registrar Module](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module) v2.0.1  
> [WHMCS Marketplace listing](https://marketplace.whmcs.com/product/5396-domain-reseller-module-for-whmcs-avalon-hosting-services) &nbsp;·&nbsp; [avalonhosting.services](https://avalonhosting.services/)

---

## What you can do

Once connected, ask your AI assistant in plain English:

> *"Is example.com available?"*  
> *"What nameservers is example.com using?"*  
> *"Change the nameservers on example.com to ns1.provider.com and ns2.provider.com"*  
> *"Register example.com for 2 years"*  
> *"Transfer example.com — here's the EPP code: XXXX"*  
> *"Lock example.com against transfers"*  
> *"Get the EPP code for example.com so I can move it out"*  
> *"Renew example.com for 1 year"*  
> *"What does a .com domain cost?"*

---

## Prerequisites

- **Your reseller API key** — available in your WHMCS admin panel. Contact [Avalon Hosting Services](https://avalonhosting.services/) if you are unsure where to find it.
- **A compatible AI assistant** — Claude Desktop, Cursor, Windsurf, or any MCP-capable client.

You do **not** need to install Node.js or run any server yourself. The MCP server is hosted by Avalon Hosting Services.

---

## Connecting your AI assistant

Use the following endpoint and your API key. Nothing else is required.

> **MCP endpoint:** `https://registrar-mcp.avalonhosting.services/mcp`  
> *(Contact [Avalon Hosting Services](https://avalonhosting.services/) to confirm the exact endpoint URL for your account.)*

### Claude Desktop

Open your Claude Desktop configuration file:

- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`

Add the following (or merge into an existing `mcpServers` block):

```json
{
  "mcpServers": {
    "domain-reseller": {
      "url": "https://registrar-mcp.avalonhosting.services/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_RESELLER_API_KEY"
      }
    }
  }
}
```

Restart Claude Desktop. You will see **domain-reseller** listed as a connected tool source.

### Cursor

Create or edit `.cursor/mcp.json` in your project, or `~/.cursor/mcp.json` globally:

```json
{
  "mcpServers": {
    "domain-reseller": {
      "url": "https://registrar-mcp.avalonhosting.services/mcp",
      "headers": {
        "Authorization": "Bearer YOUR_RESELLER_API_KEY"
      }
    }
  }
}
```

### Windsurf

Go to **Settings → MCP → Add Server** and enter:

| Field        | Value                                    |
| ------------ | ---------------------------------------- |
| URL          | `https://registrar-mcp.avalonhosting.services/mcp` |
| Header name  | `Authorization`                          |
| Header value | `Bearer YOUR_RESELLER_API_KEY`           |

### Other MCP clients

Any client that supports **Streamable HTTP MCP transport** can connect:

- **Endpoint**: `POST https://registrar-mcp.avalonhosting.services/mcp`
- **Required header**: `Authorization: Bearer YOUR_RESELLER_API_KEY`

---

## Example conversations

```
You:  Is avalon-test.com available?
AI:   Checking now... avalon-test.com is available ✅

You:  Point example.com to ns1.cloudflare.com and ns2.cloudflare.com
AI:   Updating nameservers for example.com...
      ✅ Done. ns1 → ns1.cloudflare.com, ns2 → ns2.cloudflare.com

You:  Renew example.com for 2 years
AI:   I'm about to renew example.com for 2 years. This will incur a charge.
      To confirm, reply with "I_CONFIRM" and a unique request ID
      (or ask me to generate one).

You:  I_CONFIRM — use request ID 550e8400-e29b-41d4-a716-446655440000
AI:   ✅ example.com renewed for 2 years.
```

---

## About safety confirmations

For any action that **costs money or cannot easily be undone** — registering, transferring, renewing, deleting a domain, or updating contact details — your AI will pause and ask for explicit confirmation before proceeding.

You will need to:

1. Confirm intent by including `I_CONFIRM` in your reply.
2. Provide a unique request ID (a UUID — ask the AI to generate one if you don't have one handy).

This prevents accidental domain changes triggered by ambiguous instructions.

---

## Available capabilities

### Domain lookup (read-only)

| Ask your AI…                       | What happens                                          |
| ---------------------------------- | ----------------------------------------------------- |
| "Is X available?"                  | Checks domain availability                            |
| "What nameservers is X using?"     | Returns ns1–ns5                                       |
| "Show the contact details for X"   | Returns registrant / admin / tech / billing contacts  |
| "Get the EPP / auth code for X"    | Returns the transfer authorization code               |
| "Is X locked against transfers?"   | Shows registrar lock status                           |
| "Sync the status of X"             | Refreshes expiry date and active status from registry |
| "What's the transfer status of X?" | Returns in-progress transfer state                    |
| "What does a .com domain cost?"    | Returns TLD pricing                                   |

### Domain management (write — require confirmation)

Actions marked ⚠️ will prompt for `I_CONFIRM` before executing.

| Ask your AI…                          | What happens                                  |
| ------------------------------------- | --------------------------------------------- |
| "Register X for N years"              | Registers the domain ⚠️                        |
| "Transfer X using EPP code XXXX"      | Initiates a transfer ⚠️                        |
| "Renew X for N years"                 | Renews the domain ⚠️                           |
| "Change nameservers on X to …"        | Updates ns1–ns5                               |
| "Update the contact details for X"    | Saves new contact data ⚠️                      |
| "Lock / unlock X"                     | Toggles the transfer lock                     |
| "Enable / disable ID protection on X" | Toggles WHOIS privacy                         |
| "Release X to registrar tag NEW-TAG"  | Releases to another registrar ⚠️               |
| "Delete X"                            | Submits a deletion request ⚠️ **irreversible** |

---

## Troubleshooting

| Error               | Meaning                                | What to do                                                          |
| ------------------- | -------------------------------------- | ------------------------------------------------------------------- |
| `auth_error`        | Your API key was rejected              | Double-check the key in your AI client config                       |
| `permission_denied` | Key lacks permission for this action   | Contact Avalon support to verify API key scope                      |
| `not_found`         | The MCP endpoint URL is wrong          | Confirm the endpoint URL with Avalon                                |
| `rate_limit`        | Too many requests at once              | Wait a moment and retry                                             |
| `timeout`           | The registrar took too long to respond | Retry once; contact support if it persists                          |
| `registrar_error`   | The registrar rejected the request     | Read the message — it will say why (e.g. domain already registered) |
| `unexpected_error`  | Something unexpected went wrong        | Contact Avalon support and share the full error message             |

---

## Support

- **Avalon Hosting Services**: [avalonhosting.services](https://avalonhosting.services/)
- **WHMCS Module on Marketplace**: [Domain Reseller Module for WHMCS](https://marketplace.whmcs.com/product/5396-domain-reseller-module-for-whmcs-avalon-hosting-services)
- **Module GitHub repository**: [AvalonHostingServices/whmcs-domain-registrar-module](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module)

---

## Tool Reference

Full parameter reference for advanced users or direct API integrations.

### Read Tools

All read tools accept an optional `response_format` parameter: `"markdown"` (default) or `"json"`.

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

All write tools require a `client_request_id` (UUID v4) for idempotency.  
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
| `domainid`          | number        | ✅        | WHMCS domain ID            |
| `domainname`        | string        | ✅        | Full domain name           |
| `eppcode`           | string        | ✅        | Transfer auth code         |
| `regperiod`         | number        | ✅        | Years (1–10)               |
| `dnsmanagement`     | boolean       | ✅        |                            |
| `emailforwarding`   | boolean       | ✅        |                            |
| `idprotection`      | boolean       | ✅        |                            |
| `nameservers`       | string[]      | ✅        | Min 2 nameserver hostnames |
| `contacts`          | object        | ✅        | Contact map                |
| `client_request_id` | string (UUID) | ✅        | Idempotency key            |
| `confirm`           | `"I_CONFIRM"` | ✅        |                            |

---

#### `registrar_renew_domain` ⚠️ Destructive
Renew a domain registration. May incur charges.

| Param               | Type          | Required | Description      |
| ------------------- | ------------- | -------- | ---------------- |
| `domainid`          | number        | ✅        | WHMCS domain ID  |
| `domainname`        | string        | ✅        | Full domain name |
| `regperiod`         | number        | ✅        | Years (1–10)     |
| `client_request_id` | string (UUID) | ✅        | Idempotency key  |
| `confirm`           | `"I_CONFIRM"` | ✅        |                  |

---

#### `registrar_set_nameservers`
Update nameservers for a domain. `ns1` and `ns2` are required.

| Param               | Type          | Required | Description      |
| ------------------- | ------------- | -------- | ---------------- |
| `domainid`          | number        | ✅        | WHMCS domain ID  |
| `domainname`        | string        | ✅        | Full domain name |
| `ns1`               | string        | ✅        |                  |
| `ns2`               | string        | ✅        |                  |
| `ns3`–`ns5`         | string        | ❌        |                  |
| `client_request_id` | string (UUID) | ✅        | Idempotency key  |

---

#### `registrar_set_contact_details` ⚠️ Destructive
Update contact details for one or more roles.

| Param               | Type          | Required | Description                  |
| ------------------- | ------------- | -------- | ---------------------------- |
| `domainid`          | number        | ✅        | WHMCS domain ID              |
| `domainname`        | string        | ✅        | Full domain name             |
| `contactdetails`    | object        | ✅        | Map of role → contact fields |
| `client_request_id` | string (UUID) | ✅        | Idempotency key              |
| `confirm`           | `"I_CONFIRM"` | ✅        |                              |

Contact field keys: `First_Name`, `Last_Name`, `Company_Name`, `Email`, `Address_1`, `Address_2`, `City`, `State`, `Zip`, `Country` (2-letter ISO), `Phone` (E.164 format, e.g. `+14155552671`).

---

#### `registrar_set_registrar_lock`
Enable or disable the registrar transfer lock.

| Param               | Type          | Required | Description                         |
| ------------------- | ------------- | -------- | ----------------------------------- |
| `domainid`          | number        | ✅        | WHMCS domain ID                     |
| `domainname`        | string        | ✅        | Full domain name                    |
| `lockstatus`        | boolean       | ✅        | `true` = locked, `false` = unlocked |
| `client_request_id` | string (UUID) | ✅        | Idempotency key                     |

---

#### `registrar_toggle_id_protect`
Enable or disable WHOIS ID protection.

| Param               | Type          | Required | Description                        |
| ------------------- | ------------- | -------- | ---------------------------------- |
| `domainid`          | number        | ✅        | WHMCS domain ID                    |
| `domainname`        | string        | ✅        | Full domain name                   |
| `idprotect`         | boolean       | ✅        | `true` = enable, `false` = disable |
| `client_request_id` | string (UUID) | ✅        | Idempotency key                    |

---

#### `registrar_release_domain_tag` ⚠️ Destructive
Change the IPS tag / release to another registrar (registry-dependent, e.g. `.uk`). May be irreversible.

| Param               | Type          | Required | Description      |
| ------------------- | ------------- | -------- | ---------------- |
| `domainid`          | number        | ✅        | WHMCS domain ID  |
| `domainname`        | string        | ✅        | Full domain name |
| `newtag`            | string        | ✅        | New IPS tag      |
| `client_request_id` | string (UUID) | ✅        | Idempotency key  |
| `confirm`           | `"I_CONFIRM"` | ✅        |                  |

---

#### `registrar_request_delete` ⚠️ Destructive — Irreversible
Submit a domain deletion request. Once processed by the registry this cannot be undone.

| Param               | Type          | Required | Description      |
| ------------------- | ------------- | -------- | ---------------- |
| `domainid`          | number        | ✅        | WHMCS domain ID  |
| `domainname`        | string        | ✅        | Full domain name |
| `client_request_id` | string (UUID) | ✅        | Idempotency key  |
| `confirm`           | `"I_CONFIRM"` | ✅        |                  |

---

## Related Documentation

- [Avalon Hosting Services](https://avalonhosting.services/)
- [WHMCS Module — GitHub](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module)
- [WHMCS Module — Marketplace](https://marketplace.whmcs.com/product/5396-domain-reseller-module-for-whmcs-avalon-hosting-services)
- [Module API Reference](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/blob/main/API.md)
- [Module Documentation](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/blob/main/DOCUMENTATION.md)

---

## For server administrators

The sections below are for those deploying or maintaining the MCP server itself.

### Server environment variables

| Variable            | Required | Description                                                                                         |
| ------------------- | -------- | --------------------------------------------------------------------------------------------------- |
| `REGISTRAR_API_URL` | ✅        | Static API endpoint: `https://manage.avalonhosting.services/modules/addons/domain_reseller/api.php` |
| `TRANSPORT`         | ❌        | `stdio` (default) or `http`                                                                         |
| `PORT`              | ❌        | HTTP port when `TRANSPORT=http` (default: `3000`)                                                   |

`REGISTRAR_API_KEY` is **not** a server-side env var — each reseller supplies their own key per-request via the `Authorization: Bearer` header.

### Running (Streamable HTTP)

```bash
REGISTRAR_API_URL=https://manage.avalonhosting.services/modules/addons/domain_reseller/api.php \
TRANSPORT=http \
PORT=3000 \
npm start
```

Health check: `GET /health`

### Installation from source

```bash
git clone https://github.com/AvalonHostingServices/whmcs-domain-registrar-module.git
cd whmcs-domain-registrar-module
npm install
npm run build
```

### Project structure

```
src/
├── index.ts                  — Server entry point (stdio + HTTP transport)
├── constants.ts              — Shared limits, timeouts, role aliases
├── types.ts                  — TypeScript interfaces and enums
├── schemas/
│   └── common.ts             — Zod schemas (domainid, domainname, contacts, safety tokens)
├── services/
│   ├── registrarClient.ts    — Upstream HTTP client + error mapping
│   ├── requestContext.ts     — Per-request AsyncLocalStorage for reseller API key
│   └── errors.ts             — toolError / toolText response helpers
└── tools/
    ├── readTools.ts          — 8 read-only tools
    └── writeTools.ts         — 9 write tools with safety gates
```
