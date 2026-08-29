<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

add_hook('AdminAreaHeaderOutput', 1, function ($vars) {

    $dir = __DIR__;
    $base_path = $_SERVER['DOCUMENT_ROOT'];
    $relative_dir = str_replace($base_path, '', $dir);
    $css = $relative_dir . '/css/style.css';

    return '<link rel="stylesheet" href="' . $css . '" />';
});

/**
 * Compares two version strings (each accepting an optional leading "v"),
 * returning true only if $latest is strictly newer than $current.
 */
function drr_is_newer_version($latest, $current) {
    $latest = ltrim((string) $latest, 'vV');
    $current = ltrim((string) $current, 'vV');
    if ($latest === '' || $current === '') {
        return false;
    }
    return version_compare($latest, $current, '>');
}

function drr_check_update($vars) {
    $moduleName = 'domain_reseller_registrar';
    try {
        if (!defined('ROOTDIR')) {
            define('ROOTDIR', dirname(__DIR__, 3));
        }
        $moduleName = basename(__DIR__);
        if (empty($moduleName)) {
            $moduleName = 'domain_reseller_registrar';
        }
        if (!function_exists('getRegistrarConfigOptions')) {
            require_once ROOTDIR . '/includes/registrarfunctions.php';
        }
        $config = getRegistrarConfigOptions($moduleName);

        // Opt-out: any explicitly "off" value disables auto-update. Anything
        // else — including an unset key, for installs that predate this
        // setting — keeps it on, matching the module's prior always-on behavior.
        $autoUpdateSetting = strtolower((string) ($config['autoUpdate'] ?? '1'));
        if (in_array($autoUpdateSetting, ['', '0', 'off', 'no'], true)) {
            return;
        }

        if (!defined('DRR_VERSION')) {
            require_once __DIR__ . '/' . $moduleName . '.php';
        }

        $release = drr_http_request([
            CURLOPT_URL => 'https://api.github.com/repos/' . DRR_GITHUB_REPO . '/releases/latest',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'User-Agent: domain_reseller_registrar-updater',
                'X-GitHub-Api-Version: 2022-11-28',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if ($release['response'] === false || $release['http_code'] < 200 || $release['http_code'] >= 300) {
            drr_report_error('check_module_update', 'Failed to query GitHub for the latest release', [
                'http_code' => $release['http_code'],
                'curl_error' => $release['error'],
            ]);
            return;
        }

        $releaseData = json_decode($release['response'], true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($releaseData['tag_name'])) {
            drr_report_error('check_module_update', 'Malformed response from GitHub releases API');
            return;
        }

        $tagName = $releaseData['tag_name'];
        $latestVersion = ltrim($tagName, 'vV');

        if (!drr_is_newer_version($latestVersion, DRR_VERSION)) {
            return;
        }

        $assets = is_array($releaseData['assets'] ?? null) ? $releaseData['assets'] : [];
        $zipAssetName = "whmcs-domain-registrar-module-{$tagName}.zip";
        $checksumAssetName = $zipAssetName . '.sha256';

        $zipUrl = null;
        $checksumUrl = null;
        foreach ($assets as $asset) {
            $name = $asset['name'] ?? '';
            if ($name === $zipAssetName) {
                $zipUrl = $asset['browser_download_url'] ?? null;
            } elseif ($name === $checksumAssetName) {
                $checksumUrl = $asset['browser_download_url'] ?? null;
            }
        }

        if (!$zipUrl || !$checksumUrl) {
            drr_report_error('check_module_update', 'Release is missing the expected zip or checksum asset', [
                'tag_name' => $tagName,
            ]);
            return;
        }

        $checksumResult = drr_http_request([
            CURLOPT_URL => $checksumUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if ($checksumResult['response'] === false
            || !preg_match('/^([0-9a-fA-F]{64})/', trim((string) $checksumResult['response']), $matches)) {
            drr_report_error('check_module_update', 'Could not retrieve or parse the release checksum', [
                'tag_name' => $tagName,
            ]);
            return;
        }
        $expectedHash = strtolower($matches[1]);

        $zipResult = drr_http_request([
            CURLOPT_URL => $zipUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if ($zipResult['response'] === false) {
            drr_report_error('check_module_update', 'Failed to download the release package', [
                'tag_name' => $tagName,
                'curl_error' => $zipResult['error'],
            ]);
            return;
        }

        // Verify integrity before extracting anything — this module is never
        // trusted to install code it can't authenticate, since the download
        // URL/host being reachable is not proof the bytes are the real release.
        $actualHash = hash('sha256', $zipResult['response']);
        if (!hash_equals($expectedHash, $actualHash)) {
            drr_report_error('check_module_update', 'Downloaded release package failed checksum verification — update aborted', [
                'tag_name' => $tagName,
            ]);
            return;
        }

        $zipFile = tempnam(sys_get_temp_dir(), 'drr_') . '.zip';
        file_put_contents($zipFile, $zipResult['response']);

        if (class_exists('ZipArchive') && extension_loaded('zip')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) === true) {
                $tempExtractDir = sys_get_temp_dir() . '/drr_extract_' . uniqid();
                if (!is_dir($tempExtractDir)) {
                    mkdir($tempExtractDir, 0777, true);
                }
                $zip->extractTo($tempExtractDir);
                $zip->close();

                // Release packages are built as modules/registrars/domain_reseller_registrar/...
                // (see .github/workflows/release.yml); fall back to older/looser
                // layouts defensively.
                if (is_dir($tempExtractDir . '/modules/registrars/domain_reseller_registrar')) {
                    $srcDir = $tempExtractDir . '/modules/registrars/domain_reseller_registrar';
                } elseif (is_dir($tempExtractDir . '/domain_reseller_registrar')) {
                    $srcDir = $tempExtractDir . '/domain_reseller_registrar';
                } else {
                    $srcDir = $tempExtractDir;
                }

                // Get existing DisplayName from active PHP file
                $existingDisplayName = '';
                $currentPhpFile = __DIR__ . '/' . $moduleName . '.php';
                if (file_exists($currentPhpFile)) {
                    $oldContent = file_get_contents($currentPhpFile);
                    if (preg_match("/'DisplayName'\s*=>\s*['\"]([^'\"]+)['\"]/", $oldContent, $matches)) {
                        $existingDisplayName = $matches[1];
                    }
                }

                // Copy and rename files
                drr_copy_and_rename_files($srcDir, __DIR__, $moduleName, $existingDisplayName);

                // Clean up temp directory
                drr_recursive_delete($tempExtractDir);

                logActivity($moduleName . " Registrar Auto-Update: Successfully updated to version " . $latestVersion);
            } else {
                drr_report_error('check_module_update', 'Failed to open downloaded release archive', ['tag_name' => $tagName]);
            }
        } else {
            drr_report_error('check_module_update', 'ZipArchive extension not available; cannot self-update');
        }
        unlink($zipFile);
    } catch (\Exception $e) {
        if (function_exists('drr_report_error')) {
            drr_report_error('check_module_update', $e->getMessage());
        }
        logActivity($moduleName . " Registrar Auto-Update Error: " . $e->getMessage());
    }
}

function drr_copy_and_rename_files($src, $dst, $moduleName, $existingDisplayName) {
    if (!is_dir($dst)) {
        mkdir($dst, 0777, true);
    }

    $dir = opendir($src);
    if (!$dir) {
        return;
    }

    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $srcPath = $src . '/' . $file;
        if (is_dir($srcPath)) {
            drr_copy_and_rename_files($srcPath, $dst . '/' . $file, $moduleName, $existingDisplayName);
        } else {
            // Determine target filename
            $targetFile = $file;
            if ($file === 'domain_reseller_registrar.php') {
                $targetFile = $moduleName . '.php';
            }

            $dstPath = $dst . '/' . $targetFile;

            // Special case: logo.png
            if ($file === 'logo.png' && file_exists($dstPath)) {
                // Keep the old logo
                continue;
            }

            // For PHP and JSON files, we read, replace content, and write
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $isTextFile = in_array(strtolower($ext), ['php', 'json']);
            if ($isTextFile) {
                $content = file_get_contents($srcPath);
                if ($content !== false) {
                    // Replace module name prefix and string
                    $content = str_replace('domain_reseller_registrar', $moduleName, $content);

                    // Replace DisplayName if it was preserved and we are processing the main file
                    if ($file === 'domain_reseller_registrar.php' && !empty($existingDisplayName)) {
                        $escapedDisplayName = str_replace(['\\', '$'], ['\\\\', '\\$'], $existingDisplayName);
                        $content = preg_replace(
                            "/('DisplayName'\s*=>\s*['\"])[^'\"]+(['\"])/",
                            "\${1}" . $escapedDisplayName . "\${2}",
                            $content
                        );
                    }

                    file_put_contents($dstPath, $content);
                }
            } else {
                // For other files, just copy
                copy($srcPath, $dstPath);
            }
        }
    }
    closedir($dir);
}

function drr_recursive_delete($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            drr_recursive_delete($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

add_hook('DailyCronJob', 1, 'drr_check_update');
