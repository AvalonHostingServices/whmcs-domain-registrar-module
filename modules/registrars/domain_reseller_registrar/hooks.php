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

function drr_check_update($vars) {
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

        $customApiEndpoint = $config['customApiEndpoint'] ?? '';
        $customApiKey = $config['customApiKey'] ?? '';

        if (empty($customApiEndpoint) || empty($customApiKey)) {
            return;
        }

        if (!defined('DRR_VERSION')) {
           require_once __DIR__ . '/' . $moduleName . '.php';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $customApiEndpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'api_key' => $customApiKey,
            'action' => 'check_module_update',
            'params' => [
                'current_version' => DRR_VERSION
            ]
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return;
        }

        $resDecoded = json_decode($response, true);
        if (isset($resDecoded['status']) && $resDecoded['status'] === 'success') {
            $updateData = $resDecoded['data'] ?? [];
            $latestVersion = $updateData['latest_version'] ?? '';
            $downloadUrl = $updateData['download_url'] ?? '';

            if ($latestVersion && $downloadUrl && version_compare($latestVersion, DRR_VERSION, '>')) {
                $targetDir = dirname(__DIR__); // modules/registrars
                $zipFile = tempnam(sys_get_temp_dir(), 'drr_') . '.zip';

                $dlCh = curl_init();
                curl_setopt($dlCh, CURLOPT_URL, $downloadUrl);
                curl_setopt($dlCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($dlCh, CURLOPT_TIMEOUT, 120);
                curl_setopt($dlCh, CURLOPT_FOLLOWLOCATION, true);
                $zipData = curl_exec($dlCh);
                curl_close($dlCh);

                if ($zipData) {
                    file_put_contents($zipFile, $zipData);
                    if (class_exists('ZipArchive') && extension_loaded('zip')) {
                        $zip = new ZipArchive();
                        if ($zip->open($zipFile) === true) {
                            $tempExtractDir = sys_get_temp_dir() . '/drr_extract_' . uniqid();
                            if (!is_dir($tempExtractDir)) {
                                mkdir($tempExtractDir, 0777, true);
                            }
                            $zip->extractTo($tempExtractDir);
                            $zip->close();

                            $srcDir = $tempExtractDir;
                            if (is_dir($tempExtractDir . '/domain_reseller_registrar')) {
                                $srcDir = $tempExtractDir . '/domain_reseller_registrar';
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
                        }
                    }
                    unlink($zipFile);
                }
            }
        }
    } catch (\Exception $e) {
        logActivity(($moduleName ?? 'domain_reseller_registrar') . " Registrar Auto-Update Error: " . $e->getMessage());
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
