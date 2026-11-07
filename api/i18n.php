<?php
/**
 * AI Design Studio - Bilingual Translations API Endpoint
 * GET /api/i18n.php?lang=ar | en
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';

send_security_headers();

$requestedLang = trim((string)($_GET['lang'] ?? ''));
if (!in_array($requestedLang, ['ar', 'en'], true)) {
    $requestedLang = get_current_lang();
}

$dict = load_translations($requestedLang);

json_response([
    'success' => true,
    'lang'    => $requestedLang,
    'dir'     => $requestedLang === 'ar' ? 'rtl' : 'ltr',
    'total'   => count($dict),
    'strings' => $dict
]);

