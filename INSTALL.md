# Registrar Module Installation Guide

Follow this guide to install and configure the Domain Reseller Registrar Module on WHMCS.

## Step 1: Download the Module

Download the latest release package or clone this repository.

## Step 2: Upload to Your Server

Upload module files into your WHMCS installation at:

```text
/modules/registrars/
```

## Step 3: Verify Folder Path

After upload/extraction, confirm this path exists:

```text
/modules/registrars/domain_reseller_registrar/
```

## Step 4: Access WHMCS Admin

Sign in to your WHMCS admin panel.

## Step 5: Open Domain Registrars

Navigate to:

```text
Configuration > System Settings > Domain Registrars
```

## Step 6: Activate the Registrar

Find **Avalon Hosting Services** and click **Activate**.

## Step 7: Configure the Module

Provide required values:

- API Endpoint
- API Key

Optional:

- Enable Module Log for troubleshooting

## Step 8: Save Configuration

Click **Save Changes**.

## Step 9: Assign Registrar to TLDs (Optional)

Navigate to **Domain Pricing** and assign this registrar to the TLDs you want to manage.

## Step 10: Sync TLD Pricing (Optional)

Use WHMCS TLD sync/import tools to align local pricing with provider pricing returned by the module.

## Important Notes

- Ensure your server can reach the configured API endpoint over HTTPS.
- Keep your API key private.
- Enable WHMCS Module Log only when needed for debugging.

## Related Documentation

- [README.md](README.md)
- [API.md](API.md)
- [SECURITY.md](SECURITY.md)
