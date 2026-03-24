# Release Process

This document describes the release flow for the Domain Reseller Registrar Module.

## Overview

Releases are automated through GitHub Actions.

When you push a version tag (for example `v2.0.1`), the release workflow will:

1. Package only `modules/registrars/domain_reseller_registrar/`
2. Create `.zip` and `.tar.gz` archives
3. Generate SHA256 checksum files
4. Publish a GitHub Release and attach all artifacts

## Release Steps

### 1. Prepare Changes

Before tagging:

1. Merge approved pull requests into `main`
2. Update [CHANGELOG.md](CHANGELOG.md)
3. Update module version in [modules/registrars/domain_reseller_registrar/whmcs.json](modules/registrars/domain_reseller_registrar/whmcs.json)
4. Update stable version in [README.md](README.md), if needed

### 2. Create and Push Tag

Use semantic version tags prefixed with `v`.

```bash
# Recommended: signed annotated tag
git tag -s v2.0.1 -m "Release version 2.0.1"

# Push tag to trigger release workflow
git push origin v2.0.1
```

### 3. Verify Workflow

1. Open the Actions tab in GitHub
2. Confirm the Release workflow succeeds
3. Open the Releases page and verify attached artifacts:
   - `whmcs-domain-registrar-module-vX.Y.Z.zip`
   - `whmcs-domain-registrar-module-vX.Y.Z.zip.sha256`
   - `whmcs-domain-registrar-module-vX.Y.Z.tar.gz`
   - `whmcs-domain-registrar-module-vX.Y.Z.tar.gz.sha256`

## Install Path Expectations

Release archives include only:

```text
modules/
  registrars/
    domain_reseller_registrar/
```

Resellers should extract the archive at WHMCS root so the path is created exactly.

## Verify Checksums

```bash
# Linux/macOS
sha256sum -c whmcs-domain-registrar-module-v2.0.1.zip.sha256

# Windows PowerShell
(Get-FileHash -Path "whmcs-domain-registrar-module-v2.0.1.zip" -Algorithm SHA256).Hash
```

## Pre-release Tags

Tags like `v2.1.0-beta.1` are treated as pre-releases automatically.

## Troubleshooting

If a release fails:

1. Check the failing step in the Actions logs
2. Verify the tag format starts with `v`
3. Confirm `modules/registrars/domain_reseller_registrar/` exists in the tagged commit
4. Re-run workflow or push a corrected tag
