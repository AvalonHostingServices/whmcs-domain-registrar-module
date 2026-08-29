---
title: "Getting Started"
description: "Install, configure, and validate the Avalon Hosting Services WHMCS registrar module."
---

The Avalon Hosting Services registrar module connects WHMCS domain operations to the Avalon Domain Reseller API.

## The Problem

- WHMCS expects a long list of registrar callback functions with strict names and response shapes, which makes a custom integration tedious to build from scratch.
- A provider API rarely matches WHMCS field names directly, especially for contact records, registrar lock state, nameserver operations, and transfer sync state.
- Domain pricing imports need to be translated into WHMCS `ImportItem` objects, not just returned as raw JSON.
- Admin-side behavior matters too: even contact editing screens sometimes need UI adjustments to keep provider-owned fields from being changed incorrectly.

## The Solution

This module packages the whole bridge into one registrar module directory. WHMCS discovers callback functions from `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`, every callback funnels requests through `reseller_callAPI()`, and the module normalizes the provider responses that WHMCS cares about.

```php
<?php

// Minimal upstream API example that works with the module's JSON envelope.
$request = json_decode(file_get_contents('php://input'), true);

if (($request['api_key'] ?? '') !== 'demo-key') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid API key',
    ]);
    exit;
}

if (($request['action'] ?? '') === 'CheckAvailability') {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'status' => 'available',
        ],
    ]);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Unsupported action',
]);
```

When WHMCS calls `domain_reseller_registrar_CheckAvailability()`, the module posts the standard envelope to your provider endpoint and converts the success payload into the array WHMCS expects.

## Installation

<Callout type="info">This project is not published as an npm package. The tabs below use the requested package-manager layout, but each path installs the registrar module by copying the WHMCS module directory into your application.</Callout>

" "bun"]}>
  <Tab value="npm">

```bash
git clone https://github.com/avalonhostingservices/whmcs-domain-registrar-module.git
cp -R whmcs-domain-registrar-module/modules/registrars/domain_reseller_registrar /path/to/whmcs/modules/registrars/
```

  </Tab>
  <Tab value="pnpm">

```bash
git clone https://github.com/avalonhostingservices/whmcs-domain-registrar-module.git
cp -R whmcs-domain-registrar-module/modules/registrars/domain_reseller_registrar /path/to/whmcs/modules/registrars/
```

  </Tab>
  <Tab value="yarn">

```bash
git clone https://github.com/avalonhostingservices/whmcs-domain-registrar-module.git
cp -R whmcs-domain-registrar-module/modules/registrars/domain_reseller_registrar /path/to/whmcs/modules/registrars/
```

  </Tab>
  <Tab value="bun">

```bash
git clone https://github.com/avalonhostingservices/whmcs-domain-registrar-module.git
cp -R whmcs-domain-registrar-module/modules/registrars/domain_reseller_registrar /path/to/whmcs/modules/registrars/
```

  </Tab>
</Tabs>

After copying the files, activate **Avalon Hosting Services** under **System Settings > Domain Registrars** and provide the `API Endpoint` and `API Key` values from your Avalon reseller setup.

## Quick Start

The minimum viable setup is:

1. Copy `modules/registrars/domain_reseller_registrar` into your WHMCS instance.
2. Activate the registrar in WHMCS admin.
3. Point `API Endpoint` at a provider URL that accepts the JSON envelope from [API Transport](/docs/provider-api-transport).
4. Assign the registrar to at least one TLD.

Example provider response for a lock lookup:

```json
{
  "status": "success",
  "data": {
    "lockenabled": "locked"
  }
}
```

Expected result:

```text
WHMCS opens the Registrar Lock screen and shows the domain as locked.
```

For a full setup flow, continue with [Installation and Activation](/docs/guides/installation-and-activation) and [Implementing the Provider API](/docs/guides/implementing-the-provider-api).

## Key Features

- Supports registration, renewal, transfer, delete request, EPP retrieval, ID protection, registrar lock, DNS, and child nameserver operations.
- Uses one transport helper, `reseller_callAPI()`, for the entire provider API contract — including a connect-failure-only retry and a best-effort `locale` field on every request.
- Normalizes provider contact payloads into WHMCS-friendly contact arrays.
- Supports domain sync, transfer sync, and TLD pricing import through WHMCS-native classes.
- Adds an admin header hook that injects CSS to prevent editing specific provider-managed contact ID inputs.
- Self-updates from this repository's GitHub Releases (checksum-verified before install, opt-out via config) and reports genuine transport failures to a fixed GlitchTip project — both independent of the reseller API.

<Cards>
  <Card title="Architecture" href="/docs/architecture">See how WHMCS callbacks, transport, hooks, and pricing import fit together.</Card>
  <Card title="Core Concepts" href="/docs/registrar-callback-lifecycle">Understand the callback lifecycle, API envelope, and normalization rules.</Card>
  <Card title="API Reference" href="/docs/api-reference/metadata-and-config">Review every exported callback, helper, and hook with signatures and examples.</Card>
</Cards>
