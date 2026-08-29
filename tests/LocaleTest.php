<?php

/** Covers drr_current_locale() and drr_t(). */

drr_test_reset();

drr_assert_equals('spanish', drr_current_locale(['language' => 'Spanish']), 'params[language] wins, case-normalized');
drr_assert_equals('english', drr_current_locale([]), 'falls back to english when nothing is available');

$_SESSION['Language'] = 'french';
drr_assert_equals('french', drr_current_locale([]), 'falls back to $_SESSION[Language] when params carries none');
unset($_SESSION['Language']);

drr_assert_equals('cURL Error: boom', drr_t('curl_error', 'english', ['detail' => 'boom']), 'drr_t substitutes :vars');
drr_assert_equals('Registrar module is not configured. Please set the API Endpoint and API Key.', drr_t('not_configured', 'klingon'), 'an unknown locale falls back to English');

echo "LocaleTest done.\n";
