---
title: "Importing TLD Pricing"
description: "Return provider pricing in the exact format the module maps into WHMCS import records."
---

This guide focuses on `domain_reseller_registrar_GetTldPricing()`. The source does not expose raw provider JSON to WHMCS. Instead, it converts provider pricing into `ImportItem` objects, which means your API response must contain enough detail for that mapping to succeed.

<Steps>
  <Step>
    ### Return the default WHMCS currency code

    The module queries the default currency from the WHMCS database and posts it upstream as:

```json
{
  "action": "GetTldPricing",
  "params": {
    "currency": "USD"
  }
}
```

    Your provider should either honor that currency or fail explicitly with a JSON error message. A missing `currency` object in the response causes the callback to return `No currency information available.`
  </Step>
  <Step>
    ### Return a `currency` object and `tlds` map — TLD keys need a leading dot

    The module expects:

```json
{
  "status": "success",
  "data": {
    "currency": { "code": "USD" },
    "tlds": {
      ".com": {
        "register": { "1": 10.99 },
        "renew": { "1": 11.99 },
        "transfer": { "1": 9.99 }
      }
    }
  }
}
```

    TLD keys are matched **with** a leading dot (`".com"`, not `"com"`) — the module strips it before importing. Year keys are plain numbers (`"1"`); `"1yr"`-style keys are accepted too, as a fallback, but numeric keys are the real shape. Only the one-year register price is required for a TLD to be imported, but renew and transfer prices will be applied when present.
  </Step>
  <Step>
    ### Preserve the real year options

    The source loops over year keys like `1`, `2`, and `5` (or `1yr`/`2yr`/`5yr`). If the year list is non-contiguous, it keeps those exact values by calling `setYears()` on the `ImportItem`. This is the correct way to expose registries that do not support every intermediate term.
  </Step>
  <Step>
    ### EPP-required defaults to true — there's no live signal to say otherwise

    `tld_features` currently reports which **addons** are enabled per TLD (`dnsmanagement`/`emailforwarding`/`idprotection`), not whether a TLD needs an EPP/Auth code to transfer. Because of that, every imported TLD defaults to EPP-required — the safe choice, since an unneeded code costs nothing while a missed one breaks the transfer. If you want to override this for a specific TLD, send `tld_features["<tld>"].eppcode` (`true`/`false`) — the module already checks for it, ready for whenever the API defines it.
  </Step>
  <Step>
    ### Validate the import in WHMCS

    Use the WHMCS TLD pricing import flow after activating the registrar. If a TLD does not appear, inspect whether its year-1 register price (`register["1"]`, or `register["1yr"]`) is missing or less than or equal to zero, because the module silently skips those entries.
  </Step>
</Steps>

## Complete Example Response

```json
{
  "status": "success",
  "data": {
    "currency": {
      "id": 1,
      "code": "USD",
      "prefix": "$",
      "suffix": ""
    },
    "tlds": {
      ".com": {
        "register": { "1": 10.99, "2": 21.50, "5": 52.00 },
        "renew": { "1": 11.99, "2": 23.50, "5": 57.00 },
        "transfer": { "1": 9.99 }
      },
      ".net": {
        "register": { "1": 12.99 },
        "renew": { "1": 13.99 },
        "transfer": { "1": 11.99 }
      }
    },
    "tld_features": {
      ".com": { "dnsmanagement": true, "emailforwarding": true, "idprotection": true },
      ".net": { "dnsmanagement": true, "emailforwarding": false, "idprotection": true }
    }
  }
}
```

## What the Module Does With It

- Creates one `ImportItem` per TLD, stripping the leading dot from the extension.
- Sets `minYears` and `maxYears` from the available year keys.
- Calls `setYears()` when the years are not a simple contiguous range.
- Sets `register`, `renew`, and `transfer` pricing where available.
- Marks every imported TLD `EPP required` via `setEppRequired(true)` by default — unless the response includes an explicit `tld_features["<tld>"].eppcode: false`.

That default matters operationally: the module assumes transfer authorization codes are part of the normal transfer workflow for imported TLDs unless you tell it otherwise.

## Related Reading

- [Sync and Pricing Import](/docs/sync-and-pricing)
- [API Reference: Sync and Pricing](/docs/api-reference/sync-and-pricing)
