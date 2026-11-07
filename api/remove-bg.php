<?php
/**
 * AI Design Studio - Background Removal Endpoint (Phase 5)
 * 
 * POST /api/remove-bg.php
 * Separates the main subject from an image layer using Hugging Face Inference API (RMBG)
 */

define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/rate-limiter.php';
require_once dirname(__DIR__) . '/includes/ai-client.php';

// Security headers
send_security_headers();

// Verify HTTP POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'success' => false,
        'error'   => 'Method Not Allowed. Use POST.',
        'code'    => 'METHOD_NOT_ALLOWED'
    ], 405);
}

// Verify CSRF token
verify_csrf_or_exit();

// Enforce per-IP rate limit
enforce_rate_limit_or_exit();

// Read input (JSON or form-data)
$input = get_json_input();
if (empty($input)) {
    $input = $_POST;
}

$imageData = trim((string)($input['image_data'] ?? ($input['image_url'] ?? '')));
if (empty($imageData)) {
    json_response([
        'success' => false,
        'error'   => t('image_required'),
        'code'    => 'IMAGE_REQUIRED'
    ], 422);
}

// Execute background removal
$result = ai_remove_background_rmbg($imageData);

if (!$result['success']) {
    $httpCode = 500;
    if (($result['code'] ?? '') === 'AUTH_REQUIRED') {
        $httpCode = 401;
    } elseif (($result['code'] ?? '') === 'RATE_LIMIT') {
        $httpCode = 429;
    } elseif (($result['code'] ?? '') === 'MODEL_LOADING') {
        $httpCode = 503;
    } elseif (($result['code'] ?? '') === 'INVALID_IMAGE' || ($result['code'] ?? '') === 'EMPTY_IMAGE') {
        $httpCode = 422;
    }

    json_response([
        'success' => false,
        'error'   => $result['error'] ?? t('rmbg_failed'),
        'code'    => $result['code'] ?? 'RMBG_FAILED'
    ], $httpCode);
}

json_response([
    'success'   => true,
    'message'   => t('subject_separated_success'),
    'image_url' => $result['image_url'] ?? null,
    'data_url'  => $result['data_url'] ?? null,
    'model'     => $result['model'] ?? 'briaai/RMBG-1.4'
]);

