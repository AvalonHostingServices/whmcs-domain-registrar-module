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
  <Step>
    ### If a sync action always fails, check for the flat-envelope gotcha

    `Sync` and `TransferSync` responses have no `status`/`data` envelope — a flat object, HTTP 200 either way, with `error` always present (empty string on success). If every sync reports failure even though your provider endpoint looks correct, confirm your response omits `status`/`data` for these two actions specifically, and that `error` is genuinely empty on success rather than `null` or absent.
  </Step>
  <Step>
    ### Self-update didn't apply a new version

    Self-update pulls from this repository's GitHub Releases, not your provider API — nothing on the provider side can cause or fix this. Check, in order: the "Automatic Updates" config option isn't unchecked; the WHMCS server has outbound HTTPS access to `api.github.com` and `github.com`'s release asset CDN; and, if a download did happen but nothing changed, that the release actually publishes a matching `.sha256` asset — a checksum mismatch or a missing checksum asset aborts the update silently from the admin's perspective (it's reported to GlitchTip, but not surfaced anywhere in WHMCS admin).
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

### Contact fields come back empty

`GetContactDetails` reads WHMCS's own space-separated WHOIS field names first — `"First Name"`, `"Email Address"`, `"Phone Number"`, `"Postcode"`, etc. Returning only underscored keys (`First_Name`, `Email`, `Phone`) without the space-separated equivalents will leave WHMCS-visible fields blank, since those are only a fallback.

### Contact names are split incorrectly

If you only return `Full_Name` (and neither `"First Name"` nor `First_Name`), the module splits it on spaces as a last resort. Return explicit first/last name fields when you need precise parsing.

### GlitchTip gets flooded with routine business errors

The provider documents every business error (redemption period, insufficient balance, etc.) as **non-2xx** — the module distinguishes "expected business error" from "genuine failure" by whether the body is a well-formed `{"status":"error","message":"..."}` shape, not by HTTP status. If your error responses omit `status` or `message`, or use a different shape, they'll be reported to GlitchTip as anomalies even when they're routine.

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
