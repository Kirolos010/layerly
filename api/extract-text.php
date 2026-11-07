<?php
/**
 * AI Design Studio - Vision OCR Text Extraction Endpoint (Phase 5)
 * 
 * POST /api/extract-text.php
 * Extracts all visible text blocks from an image as structured editable layers
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

$width = isset($input['width']) ? (int)$input['width'] : 1080;
$height = isset($input['height']) ? (int)$input['height'] : 1080;

$result = ai_extract_text_from_image($imageData, $width, $height);

if (!$result['success']) {
    json_response([
        'success' => false,
        'error'   => $result['error'] ?? t('no_text_detected'),
        'code'    => 'VISION_EXTRACTION_FAILED'
    ], 500);
}

$blocks = $result['blocks'] ?? [];

json_response([
    'success'      => true,
    'message'      => count($blocks) > 0 ? t('text_extracted_success') : t('no_text_detected'),
    'text_blocks'  => $blocks,
    'total_blocks' => count($blocks)
]);

