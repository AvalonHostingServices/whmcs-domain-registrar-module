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
    ### Return a `currency` object and `tlds` map

    The module expects:

```json
{
  "status": "success",
  "data": {
    "currency": { "code": "USD" },
    "tlds": {
      ".com": {
        "register": { "1yr": "10.99" },
        "renew": { "1yr": "11.99" },
        "transfer": { "1yr": "9.99" }
      }
    }
  }
}
```

    Only the one-year register price is required for a TLD to be imported, but renew and transfer prices will be applied when present.
  </Step>
  <Step>
    ### Preserve the real year options

    The source loops over year keys like `1yr`, `2yr`, and `5yr`. If the year list is non-contiguous, it keeps those exact values by calling `setYears()` on the `ImportItem`. This is the correct way to expose registries that do not support every intermediate term.
  </Step>
  <Step>
    ### Validate the import in WHMCS

    Use the WHMCS TLD pricing import flow after activating the registrar. If a TLD does not appear, inspect whether its `register.1yr` price is missing or less than or equal to zero, because the module silently skips those entries.
  </Step>
</Steps>

## Complete Example Response

```json
{
  "status": "success",
  "data": {
    "currency": {
      "code": "USD"
    },
    "tlds": {
      ".com": {
        "register": { "1yr": "10.99", "2yr": "21.50", "5yr": "52.00" },
        "renew": { "1yr": "11.99", "2yr": "23.50", "5yr": "57.00" },
        "transfer": { "1yr": "9.99" }
      },
      ".net": {
        "register": { "1yr": "12.99" },
        "renew": { "1yr": "13.99" },
        "transfer": { "1yr": "11.99" }
      }
    }
  }
}
```

## What the Module Does With It

- Creates one `ImportItem` per TLD.
- Sets `minYears` and `maxYears` from the available year keys.
- Calls `setYears()` when the years are not a simple contiguous range.
- Sets `register`, `renew`, and `transfer` pricing where available.
- Marks every imported TLD as `EPP required` through `setEppRequired(true)`.

That last point matters operationally: the module assumes transfer authorization codes are part of the normal transfer workflow for imported TLDs.

## Related Reading

- [Sync and Pricing Import](/docs/sync-and-pricing)
- [API Reference: Sync and Pricing](/docs/api-reference/sync-and-pricing)
