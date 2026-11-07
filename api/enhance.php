<?php
/**
 * AI Design Studio - Enhance Idea Endpoint
 * 
 * POST /api/enhance.php
 * Rewrites a short idea into a rich, detailed design brief in the same language.
 */

define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/rate-limiter.php';
require_once dirname(__DIR__) . '/includes/ai-client.php';
require_once dirname(__DIR__) . '/includes/history.php';

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

// Limit prompt size
if (mb_strlen($prompt) > 3000) {
    $prompt = mb_substr($prompt, 0, 3000);
}

$lang = isset($input['lang']) && in_array($input['lang'], ['ar', 'en'], true) ? $input['lang'] : null;

// Call AI client
$result = ai_enhance_prompt($prompt, $lang);

if (!$result['success']) {
    record_generation_history('enhance', $prompt, ['lang' => $lang], [
        'error' => $result['error'] ?? 'AI Error'
    ], 'failed');

    json_response([
        'success' => false,
        'error'   => $result['error'] ?? t('ai_invalid_response'),
        'code'    => 'AI_ERROR'
    ], 500);
}

// Record successful generation in history
$generationId = record_generation_history('enhance', $prompt, ['lang' => $lang], [
    'enhanced_prompt' => $result['enhanced_prompt'],
    'original_prompt' => $result['original_prompt'],
    'detected_lang'   => $result['detected_lang'],
    'provider'        => $result['provider']
], 'success');

json_response([
    'success'         => true,
    'message'         => t('enhanced_success'),
    'enhanced_prompt' => $result['enhanced_prompt'],
    'original_prompt' => $result['original_prompt'],
    'detected_lang'   => $result['detected_lang'],
    'provider'        => $result['provider'],
    'generation_id'   => $generationId
]);

