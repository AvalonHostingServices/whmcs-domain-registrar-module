---
title: "Troubleshooting and Logging"
description: "Diagnose transport failures, schema mismatches, and admin UI issues in the registrar module."
---

This guide focuses on the failure modes that follow directly from the source code. The module is small, so most issues reduce to four buckets: wrong callback shape, invalid JSON, provider schema mismatches, or WHMCS admin UI expectations.

<Steps>
  <Step>
    ### Enable module logging temporarily

    Turn on `Enable Module Log` in the registrar configuration. This makes `reseller_callAPI()` call `logModuleCall()` with:

    - the endpoint URL
    - the action name
    - the request params
    - the decoded response

    That is the fastest way to see what WHMCS actually sent to your provider endpoint.
  </Step>
  <Step>
    ### Check the response envelope before anything else

    The module fails early when the response is not valid JSON or when `status` is not `success`. A safe failure response is:

```json
{
  "status": "error",
  "message": "Domain is in redemption period"
}
```

    A plain HTML error page, upstream stack trace, or empty body will be surfaced as `Invalid JSON response from API`.
  </Step>
  <Step>
    ### Verify callback-specific fields

    Many issues come from returning the wrong keys for a specific callback:

    - `GetNameservers` needs `ns1` through `ns5`
    - `GetEPPCode` needs `eppcode`
    - `Sync` needs `active`, `cancelled`, `transferredAway`, and `expirydate`
    - `TransferSync` needs `completed`, `failed`, `expirydate`, and `reason`
    - `GetTldPricing` needs both `currency` and `tlds`

    If the transport is working but the WHMCS screen is still wrong, this is the next place to look.
  </Step>
  <Step>
    ### Inspect admin contact UI behavior

    The module registers `AdminAreaHeaderOutput` in `hooks.php` and injects `css/style.css`. That stylesheet disables certain `Contact Id` inputs in the admin contact editor. If operators report that those fields look locked, that behavior is intentional and comes from the module, not from a browser or theme issue.
  </Step>
</Steps>

## Common Symptoms

### `Invalid JSON response from API`

Your endpoint returned something that `json_decode()` could not parse. This often happens when a PHP warning, reverse-proxy error page, or HTML framework error template is emitted before the JSON body.

### Registrar actions succeed for some features but not others

That usually means the base transport works, but one callback contract is wrong. For example, `RenewDomain` may work while `TransferSync` fails because the provider never returns `completed` or `failed`.

### TLD pricing import is empty

Check these three conditions:

- `currency` exists in the response.
- `tlds` exists and is non-empty.
- each TLD has a positive `register.1yr` price.

### Contact names are split incorrectly

If you only return `Full_Name`, the module splits on spaces. Return explicit `First_Name` and `Last_Name` when you need precise parsing.

## Quick Diagnostic Endpoint

Use this endpoint to prove that the transport layer itself is healthy:

```php
<?php

header('Content-Type: application/json');

$request = json_decode(file_get_contents('php://input'), true);

echo json_encode([
    'status' => 'success',
    'data' => [
        'echo_action' => $request['action'] ?? null,
        'echo_params' => $request['params'] ?? [],
    ],
]);
```

If WHMCS receives a decoded response here, your remaining problem is callback schema mapping rather than connectivity or authentication.

## Related Reading

- [Provider API Transport](/docs/provider-api-transport)
- [Contact Normalization](/docs/contact-normalization)
