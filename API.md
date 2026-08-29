# Domain Reseller Registrar API Reference

This document describes the API contract used by the WHMCS registrar module.

The module sends JSON POST requests to the configured API endpoint with this envelope.

## Base Request Envelope

```json
{
  "api_key": "<your_api_key>",
  "action": "<ActionName>",
  "locale": "<current WHMCS locale, e.g. \"english\">",
  "params": {
    "...": "action-specific parameters"
  }
}
```

`locale` is a best-effort read of the current WHMCS client/admin language (see `drr_current_locale()`); implementers may use it to localize `message` text in the response. It is not guaranteed accurate — WHMCS does not document a reliable source for it inside registrar-module functions — so treat it as a hint, and always fall back to English if unrecognized.

## Base Response Envelope

Success:

```json
{
  "status": "success",
  "data": {
    "...": "action-specific response data"
  }
}
```

Error:

```json
{
  "status": "error",
  "message": "Human readable error",
  "error_code": "optional_machine_readable_code",
  "data": {
    "...": "optional diagnostics"
  }
}
```

`error_code` is optional. When present, the module currently passes it through unchanged as an extra `error_code` key on its own error return — it is not yet mapped to anything locally. Some errors add other extra keys alongside `message` (for example an IP-whitelist rejection also returns `client_ip` and `whitelisted_ips`); the module preserves the full decoded body under `details`.

## Common Parameters

- `domainid`: WHMCS domain ID
- `domainname`: Full domain name, for example `example.com`
- `regperiod`: Registration period in years

## Quick Example

Example request body sent by the module:

```json
{
  "api_key": "your_api_key",
  "action": "RenewDomain",
  "params": {
    "domainid": 123,
    "domainname": "example.com",
    "regperiod": 1
  }
}
```

Example success response:

```json
{
  "status": "success",
  "data": {
    "renewed": true,
    "expirydate": "2027-03-25"
  }
}
```

Example error response:

```json
{
  "status": "error",
  "message": "Domain is in redemption period"
}
```

## Action Reference

### RegisterDomain

Registers a new domain.

The provider pulls contact/WHOIS data from the reseller's own account, not from the registering client's WHMCS
profile — this action does **not** accept a `contacts` object. Nameservers chosen at checkout are optional.

Required params:

- `domainid`
- `domainname`
- `regperiod`
- `dnsmanagement`
- `emailforwarding`
- `idprotection`

Optional params:

- `nameservers` (array, up to 5)

Example:

```json
{
  "api_key": "your_api_key",
  "action": "RegisterDomain",
  "params": {
    "domainid": 123,
    "domainname": "example.com",
    "regperiod": 1,
    "nameservers": ["ns1.example.com", "ns2.example.com"],
    "dnsmanagement": true,
    "emailforwarding": false,
    "idprotection": true
  }
}
```

### TransferDomain

Transfers an existing domain. Same rationale as RegisterDomain — no `contacts` object.

Required params:

- `domainid`
- `domainname`
- `eppcode`
- `regperiod`
- `dnsmanagement`
- `emailforwarding`
- `idprotection`

Optional params:

- `nameservers` (array, up to 5) — applied once the transfer completes

Example:

```json
{
  "api_key": "your_api_key",
  "action": "TransferDomain",
  "params": {
    "domainid": 456,
    "domainname": "example.net",
    "eppcode": "AUTH-ABC-123",
    "regperiod": 1,
    "dnsmanagement": true,
    "emailforwarding": false,
    "idprotection": false,
    "nameservers": ["ns1.example.net", "ns2.example.net"]
  }
}
```

### RenewDomain

Renews a domain.

Required params:

- `domainid`
- `domainname`
- `regperiod`

### RequestDelete

Submits a delete request.

Required params:

- `domainid`
- `domainname`

### GetNameservers

Returns nameservers for a domain.

Required params:

- `domainid`
- `domainname`

Expected data keys:

- `ns1`, `ns2`, `ns3`, `ns4`, `ns5`

Example success data:

```json
{
  "status": "success",
  "data": {
    "ns1": "ns1.example.com",
    "ns2": "ns2.example.com",
    "ns3": "",
    "ns4": "",
    "ns5": ""
  }
}
```

### SaveNameservers

Updates nameservers for a domain.

Required params:

- `domainid`
- `domainname`
- `ns1`
- `ns2`
- `ns3` (optional)
- `ns4` (optional)
- `ns5` (optional)

Example:

```json
{
  "api_key": "your_api_key",
  "action": "SaveNameservers",
  "params": {
    "domainid": 123,
    "domainname": "example.com",
    "ns1": "ns1.provider-dns.net",
    "ns2": "ns2.provider-dns.net"
  }
}
```

### GetContactDetails

Gets contact details for available roles.

Required params:

- `domainid`
- `domainname`

Possible response objects:

- `Registrant`
- `Admin`
- `Technical` or `Tech`
- `Billing`

This action is a direct passthrough of WHMCS's own `DomainGetWhoisInfo`, so each contact object uses WHMCS's
space-separated WHOIS field names — not the underscored names used by `SaveContactDetails` below:

- `First Name`, `Last Name`, `Company Name`
- `Email Address`
- `Address 1`, `Address 2`
- `City`, `State`, `Postcode`, `Country`
- `Phone Number`

`drr_normalize_contact()` in the module reads these first, with the underscored/`Full_Name`/`Zip` spellings kept
only as defensive fallbacks in case a different backend varies.

Example success data:

```json
{
  "status": "success",
  "data": {
    "Registrant": {
      "First Name": "John",
      "Last Name": "Doe",
      "Company Name": "Example Inc",
      "Email Address": "john@example.com",
      "Address 1": "123 Main Street",
      "Address 2": "",
      "City": "Dhaka",
      "State": "Dhaka",
      "Postcode": "1207",
      "Country": "BD",
      "Phone Number": "+8801000000000"
    }
  }
}
```

### SaveContactDetails

Saves contact details for one or more contact roles.

Required params:

- `domainid`
- `domainname`
- `contactdetails`

The module sends normalized fields like:

- `First_Name`, `Last_Name`, `Company_Name`
- `Email`
- `Address_1`, `Address_2`
- `City`, `State`, `Zip`, `Country`
- `Phone`

Example:

```json
{
  "api_key": "your_api_key",
  "action": "SaveContactDetails",
  "params": {
    "domainid": 123,
    "domainname": "example.com",
    "contactdetails": {
      "Registrant": {
        "First_Name": "John",
        "Last_Name": "Doe",
        "Email": "john@example.com",
        "Address_1": "123 Main Street",
        "City": "Dhaka",
        "State": "Dhaka",
        "Zip": "1207",
        "Country": "BD",
        "Phone": "+8801000000000"
      }
    }
  }
}
```

### GetEPPCode

Retrieves transfer auth code.

Required params:

- `domainid`
- `domainname`

Expected data keys:

- `eppcode`

Example success data:

```json
{
  "status": "success",
  "data": {
    "eppcode": "AUTH-ABC-123"
  }
}
```

### ReleaseDomain

Changes IPS tag / transfer tag (registry-dependent).

Required params:

- `domainid`
- `domainname`
- `newtag`

### IDProtectToggle

Enables/disables ID protection.

Required params:

- `domainid`
- `domainname`
- `idprotect` (boolean-like)

Example:

```json
{
  "api_key": "your_api_key",
  "action": "IDProtectToggle",
  "params": {
    "domainid": 123,
    "domainname": "example.com",
    "idprotect": true
  }
}
```

### GetRegistrarLock

Gets registrar lock status.

Required params:

- `domainid`
- `domainname`

Expected data keys:

- `lockstatus` (preferred) or `status`: one of `locked`, `unlocked`, or `transferlock unavailable`

Example success data:

```json
{
  "status": "success",
  "data": {
    "lockstatus": "locked"
  }
}
```

### SaveRegistrarLock

Sets registrar lock status.

Required params:

- `domainid`
- `domainname`
- `lockstatus` (boolean)

Example:

```json
{
  "api_key": "your_api_key",
  "action": "SaveRegistrarLock",
  "params": {
    "domainid": 123,
    "domainname": "example.com",
    "lockstatus": true
  }
}
```

### CheckAvailability

Checks domain availability.

Required params:

- `domainid`
- `domain`
- `domainname`

Expected data keys:

- `status`

Example success data:

```json
{
  "status": "success",
  "data": {
    "status": "available"
  }
}
```

### Sync

Synchronizes registration state.

Required params:

- `domainid`
- `domainname`
- `sld`
- `tld`

Expected data keys:

- `active`
- `cancelled`
- `transferredAway`
- `expirydate`

Example success data:

```json
{
  "status": "success",
  "data": {
    "active": true,
    "cancelled": false,
    "transferredAway": false,
    "expirydate": "2027-03-25"
  }
}
```

### TransferSync

Synchronizes transfer status.

Required params:

- `domainid`
- `domainname`
- `sld`
- `tld`

Expected data keys:

- `completed`
- `failed`
- `expirydate`
- `reason`

Example success data:

```json
{
  "status": "success",
  "data": {
    "completed": true,
    "failed": false,
    "expirydate": "2027-03-25",
    "reason": ""
  }
}
```

### RegisterNameserver

Creates a child nameserver (glue record).

The module forwards WHMCS parameters directly in `params`.

### ModifyNameserver

Updates a child nameserver.

The module forwards WHMCS parameters directly in `params`.

### DeleteNameserver

Deletes a child nameserver.

The module forwards WHMCS parameters directly in `params`.

### GetDNS

Returns DNS records for a domain.

The module forwards WHMCS parameters directly in `params`.

### SaveDNS

Saves DNS records for a domain.

The module forwards WHMCS parameters directly in `params`.

### GetDomainSuggestions

Returns suggested domains.

The module forwards WHMCS parameters directly in `params`.

### GetTldPricing

Returns provider TLD pricing mapped into WHMCS import format.

The module sends:

- `currency`: Default WHMCS currency code

Expected response data:

- `currency` object containing at least `code` (other keys such as `id`/`prefix`/`suffix` are ignored)
- `tlds` map keyed by TLD **with a leading dot** (e.g. `".com"`), each containing `register`/`renew`/`transfer`
  pricing objects keyed by plain year numbers (e.g. `"1"`, `"2"`); `"1yr"`-style keys are also accepted, as a
  fallback. Each TLD's pricing object may also carry an `addons` object (see note below).
- `tld_features` (optional): map keyed by the same dotted TLD, reporting which addons (`dnsmanagement`,
  `emailforwarding`, `idprotection`) are enabled for that TLD. **Not currently used to determine whether a
  TLD needs an EPP/Auth code** — the module has no live signal for that today, so every TLD is imported as
  EPP-required by default (the safe default for gTLDs/most ccTLDs — an unneeded code costs nothing, a missed
  one breaks the transfer). An explicit `tld_features[tld].eppcode` value, if ever sent, overrides that
  default; this key is not part of the current response but the module supports it for forward-compatibility.

A TLD is only imported if it has a positive `register` price for year 1; a TLD missing that is skipped.

Example success data:

```json
{
  "status": "success",
  "data": {
    "tlds": {
      ".com": {
        "register": { "1": 10.99, "2": 21.98 },
        "transfer": { "1": 10.99 },
        "renew": { "1": 12.99 },
        "addons": {
          "dnsmanagement": { "label": "DNS Management", "register": 2.00, "transfer": 2.00, "renew": 2.00 },
          "emailforwarding": { "label": "Email Forwarding", "register": 1.50, "transfer": 1.50, "renew": 1.50 },
          "idprotection": { "label": "ID Protection", "register": 3.00, "transfer": 3.00, "renew": 3.00 }
        }
      },
      ".net": {
        "register": { "1": 11.99 },
        "transfer": { "1": 11.99 },
        "renew": { "1": 13.99 }
      }
    },
    "tld_features": {
      ".com": { "dnsmanagement": true, "emailforwarding": true, "idprotection": true },
      ".net": { "dnsmanagement": true, "emailforwarding": false, "idprotection": true }
    },
    "currency": { "id": 1, "code": "USD", "prefix": "$", "suffix": "" }
  }
}
```

The module currently imports register/renew/transfer pricing and the derived min/max registration years only;
the per-TLD `addons` pricing object is not yet synced into WHMCS (WHMCS's domain-addon pricing is configured
separately from the registrar TLD importer).

### Self-update

Module self-update is **not** driven by this API. It is a `DailyCronJob` hook (`drr_check_update()` in
`hooks.php`) that checks this repository's [GitHub Releases](https://github.com/AvalonHostingServices/whmcs-domain-registrar-module/releases)
directly — a separate, independently-versioned distribution channel from the reseller API. The API is never
trusted to supply update code, so there is nothing for an API implementer to build for this; a previous
version of this document described a `check_module_update` action, which no longer exists.

The module compares `DRR_VERSION` against the latest GitHub release's tag, and if newer, downloads that
release's `whmcs-domain-registrar-module-<tag>.zip` asset, verifies it against the matching `.sha256` asset
before extracting anything, then installs it in place — preserving the reseller's configured `DisplayName`
and existing `logo.png`. This can be disabled per-install via the module's "Automatic Updates" config option.

## Notes for API Implementers

- All requests are sent as `Content-Type: application/json`.
- Module timeout is 60 seconds. A request is retried once, only when it never reached the provider at all
  (DNS/connect failure) — never on a timeout mid-request or any response actually received, since
  register/transfer/renew are not safe to resend blind.
- Module expects well-formed JSON responses.
- Successful responses must use `status: success` and return payload under `data`.
- Any other status is treated as an error and shown to WHMCS.
- `Sync` and `TransferSync` are the exception to the above: see their sections for their flat, non-enveloped
  response shape.

## Error Reporting

Starting in v2.2.0, the module reports transport-level failures — cURL errors, malformed JSON, and non-2xx
HTTP responses from your API — to a fixed GlitchTip project maintained by Avalon Hosting Services, so
integration problems can be caught and fixed across installs. Documented business-error responses (any
`status: error` returned with a 2xx HTTP status, e.g. "Domain is in redemption period") are **not** reported;
only genuine transport/server failures are. Only the action name, HTTP status code, and error message are
sent — never request payloads, contact details, domain names, or API credentials.

## Related Documentation

- [README.md](README.md)
- [INSTALL.md](INSTALL.md)
- [DOCUMENTATION.md](DOCUMENTATION.md)
