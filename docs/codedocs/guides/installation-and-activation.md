---
title: "Installation and Activation"
description: "Copy the registrar module into WHMCS, activate it, and validate the first provider call."
---

This guide walks through the real installation path for the module. There is no package manager release; you install it by copying the registrar directory into your WHMCS application and configuring the provider endpoint and API key in admin.

<Steps>
  <Step>
    ### Copy the registrar module into WHMCS

    Put the source directory in the path WHMCS expects:

```bash
cp -R modules/registrars/domain_reseller_registrar /path/to/whmcs/modules/registrars/
```

    After copying, this path must exist:

```text
/path/to/whmcs/modules/registrars/domain_reseller_registrar
```

    The repository's own `CONTRIBUTING.md` calls out that this path must remain unchanged, which reflects the WHMCS module discovery rules.
  </Step>
  <Step>
    ### Activate the registrar

    In WHMCS admin, open:

```text
System Settings > Domain Registrars
```

    Find **Avalon Hosting Services** and click **Activate**. The display name comes from `domain_reseller_registrar_MetaData()` and `domain_reseller_registrar_getConfigArray()`.
  </Step>
  <Step>
    ### Configure the required settings

    Enter values for:

    - `API Endpoint`
    - `API Key`

    Optional:

    - `Enable Module Log`
    - `Automatic Updates` (checked by default) — leave it on to receive new module versions automatically from GitHub Releases once a day; uncheck it to manage updates manually. This is unrelated to your `API Endpoint`, and the reseller API is never used for updates.

    These fields are declared in `modules/registrars/domain_reseller_registrar/domain_reseller_registrar.php`. `API Endpoint` is the URL cURL posts JSON to, and `API Key` becomes the `api_key` field in every request body.
  </Step>
  <Step>
    ### Validate connectivity with a simple action

    Assign the registrar to one TLD, then use a registrar action that exercises a cheap callback such as nameserver lookup or registrar lock lookup. A minimal compatible provider response is:

```json
{
  "status": "success",
  "data": {
    "lockenabled": "locked"
  }
}
```

    If the module is wired correctly, the WHMCS admin screen loads the domain state without showing a registrar error.
  </Step>
</Steps>

## Complete Example Provider Stub

Use this temporary endpoint to verify installation before you connect the real provider service:

```php
<?php

$request = json_decode(file_get_contents('php://input'), true);

if (($request['api_key'] ?? '') !== 'test-key') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid API key',
    ]);
    exit;
}

$action = $request['action'] ?? '';

if ($action === 'GetRegistrarLock') {
    echo json_encode([
        'status' => 'success',
        'data' => ['lockenabled' => 'locked'],
    ]);
    exit;
}

if ($action === 'GetNameservers') {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'ns1' => 'ns1.provider.net',
            'ns2' => 'ns2.provider.net',
            'ns3' => '',
            'ns4' => '',
            'ns5' => '',
        ],
    ]);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Unsupported action: ' . $action,
]);
```

## Expected Outcome

- WHMCS shows the registrar as active.
- Registrar actions begin issuing JSON POST requests to your configured endpoint.
- If `Enable Module Log` is turned on, WHMCS Module Log records the action name, request params, and decoded response.

## Related Reading

- [Provider API Transport](/docs/provider-api-transport)
- [Troubleshooting and Logging](/docs/guides/troubleshooting-and-logging)
