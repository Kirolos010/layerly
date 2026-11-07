<?php
/**
 * AI Design Studio - Generate Design Endpoint
 * 
 * POST /api/generate.php
 * Generates structured JSON design matching the strict Fabric.js schema.
 */

define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/rate-limiter.php';
require_once dirname(__DIR__) . '/includes/ai-client.php';
require_once dirname(__DIR__) . '/includes/sanitizer.php';
require_once dirname(__DIR__) . '/includes/HistoryRepository.php';

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

$prompt = trim((string)($input['prompt'] ?? ''));
if (empty($prompt)) {
    json_response([
        'success' => false,
        'error'   => t('prompt_required'),
        'code'    => 'PROMPT_REQUIRED'
    ], 422);
}

if (mb_strlen($prompt) > 4000) {
    $prompt = mb_substr($prompt, 0, 4000);
}

// Canvas size & options
$width = isset($input['width']) ? (int)$input['width'] : 1080;
$height = isset($input['height']) ? (int)$input['height'] : 1080;

$options = [
    'design_type'         => sanitize_text($input['design_type'] ?? 'social_post', 50),
    'style'               => sanitize_text($input['style'] ?? 'modern', 50),
    'color_mood'          => sanitize_text($input['color_mood'] ?? 'vibrant', 50),
    'language'            => sanitize_text($input['language'] ?? 'auto', 10),
    'generate_background' => !empty($input['generate_background']) && in_array($input['generate_background'], [true, 'true', '1', 1], true),
];

function sanitize_text(mixed $val, int $maxLength = 100): string {
    $clean = trim((string)$val);
    $clean = strip_tags($clean);
    return mb_substr($clean, 0, $maxLength);
}

// Call AI generation with automatic single-retry
$result = ai_generate_design($prompt, $width, $height, $options);

if (!$result['success']) {
    record_generation_history('generate', $prompt, $options, [
        'error' => $result['error'] ?? 'Generation failed'
    ], 'failed');

    json_response([
        'success' => false,
        'error'   => $result['error'] ?? t('ai_invalid_response'),
        'code'    => 'AI_GENERATION_FAILED'
    ], 500);
}

// Record successful generation in history
$generationId = record_generation_history('generate', $prompt, $options, $result['data'], 'success');

json_response([
    'success'       => true,
    'message'       => t('generated_success'),
    'data'          => $result['data'],
    'provider'      => $result['provider'],
    'generation_id' => $generationId
]);

