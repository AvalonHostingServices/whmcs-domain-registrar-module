<?php

/**
 * Covers the pure/filesystem-only pieces of the GitHub-based self-update
 * flow: version comparison, and the copy/rename/DisplayName-preservation
 * step, using real temp directories instead of a network download.
 */

drr_test_reset();

drr_assert_equals(true, drr_is_newer_version('v2.3.0', '2.2.0'), 'a newer tagged version (with "v" prefix) is detected as newer');
drr_assert_equals(false, drr_is_newer_version('2.2.0', '2.2.0'), 'an identical version is not newer');
drr_assert_equals(false, drr_is_newer_version('2.1.0', '2.2.0'), 'an older version is not newer');
drr_assert_equals(false, drr_is_newer_version('', '2.2.0'), 'an empty latest version is never newer');
drr_assert_equals(true, drr_is_newer_version('v2.10.0', 'v2.9.0'), 'numeric version segments compare correctly, not lexically');

// --- drr_copy_and_rename_files: release layout -> installed module dir,
//     preserving a pre-existing logo.png and a white-labeled DisplayName ---
$base = sys_get_temp_dir() . '/drr_selfupdate_test_' . uniqid();
$src = $base . '/src';
$dst = $base . '/dst';
mkdir($src, 0777, true);
mkdir($dst, 0777, true);

file_put_contents($src . '/domain_reseller_registrar.php', "<?php\nreturn ['DisplayName' => 'New Upstream Name'];\n");
file_put_contents($src . '/logo.png', 'new-logo-bytes');
file_put_contents($dst . '/logo.png', 'existing-reseller-logo-bytes');

drr_copy_and_rename_files($src, $dst, 'my_whitelabeled_registrar', 'My White-labeled Registrar');

$installedPhp = $dst . '/my_whitelabeled_registrar.php';
drr_assert(file_exists($installedPhp), 'the main PHP file is renamed to match the installed module folder name');

$installedContent = file_exists($installedPhp) ? file_get_contents($installedPhp) : '';
drr_assert(strpos($installedContent, 'My White-labeled Registrar') !== false, 'the reseller\'s existing DisplayName is preserved over the upstream one');
drr_assert(strpos($installedContent, 'New Upstream Name') === false, 'the upstream DisplayName is not left in place');

drr_assert_equals('existing-reseller-logo-bytes', file_exists($dst . '/logo.png') ? file_get_contents($dst . '/logo.png') : null, 'an existing logo.png is kept rather than overwritten');

drr_recursive_delete($base);
drr_assert(!is_dir($base), 'drr_recursive_delete removes the whole temp tree');

echo "SelfUpdateTest done.\n";
