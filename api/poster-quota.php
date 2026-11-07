<?php
/**
 * AI Design Studio - Poster Quota Status Endpoint
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate-limiter.php';

send_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

$quota = get_poster_quota();

json_response([
    'success' => true,
    'quota'   => $quota
]);
