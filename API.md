# Domain Reseller Registrar API Reference

This document describes the API contract used by the WHMCS registrar module.

The module sends JSON POST requests to the configured API endpoint with this envelope.

## Base Request Envelope

```json
{
  "api_key": "<your_api_key>",
  "action": "<ActionName>",
  "params": {
    "...": "action-specific parameters"
  }
}
```

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
  "data": {
    "...": "optional diagnostics"
  }
}
```

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

Required params:

- `domainid`
- `domainname`
- `regperiod`
- `dnsmanagement`
- `emailforwarding`
- `idprotection`
- `contacts` with `registrant`, `admin`, `tech`, `billing`

Example:

```json
{
  "api_key": "your_api_key",
  "action": "RegisterDomain",
  "params": {
    "domainid": 123,
    "domainname": "example.com",
    "regperiod": 1,
    "dnsmanagement": true,
    "emailforwarding": false,
    "idprotection": true,
    "contacts": {
      "registrant": {
        "firstname": "John",
        "lastname": "Doe",
        "email": "john@example.com"
      },
      "admin": {
        "firstname": "John",
        "lastname": "Doe",
        "email": "john@example.com"
      },
      "tech": {
        "firstname": "Jane",
        "lastname": "Doe",
        "email": "jane@example.com"
      },
      "billing": {
        "firstname": "John",
        "lastname": "Doe",
        "email": "billing@example.com"
      }
    }
  }
}
```

### TransferDomain

Transfers an existing domain.

Required params:

- `domainid`
- `domainname`
- `eppcode`
- `regperiod`
- `dnsmanagement`
- `emailforwarding`
- `idprotection`
- `nameservers` (array)
- `contacts` with `registrant`, `admin`, `tech`, `billing`

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
    "nameservers": ["ns1.example.net", "ns2.example.net"],
    "contacts": {
      "registrant": {
        "firstname": "John",
        "lastname": "Doe",
        "email": "john@example.com"
      }
    }
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

Example success data:

```json
{
  "status": "success",
  "data": {
    "Registrant": {
      "Full_Name": "John Doe",
      "Email": "john@example.com",
      "Address": "123 Main Street",
      "City": "Dhaka",
      "State": "Dhaka",
      "Zip": "1207",
      "Country": "BD",
      "Phone": "+8801000000000"
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

- `currency` object containing at least `code`
- `tlds` map containing pricing for `register`, `renew`, `transfer` by year keys like `1yr`

## Notes for API Implementers

- All requests are sent as `Content-Type: application/json`.
- Module timeout is 60 seconds.
- Module expects well-formed JSON responses.
- Successful responses must use `status: success` and return payload under `data`.
- Any other status is treated as an error and shown to WHMCS.

## Related Documentation

- [README.md](README.md)
- [INSTALL.md](INSTALL.md)
- [DOCUMENTATION.md](DOCUMENTATION.md)
