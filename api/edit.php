<?php
/**
 * AI Design Studio - Edit Design Endpoint
 * 
 * POST /api/edit.php
 * Receives current design JSON + natural language instruction and returns updated, sanitized JSON.
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

$instruction = trim((string)($input['instruction'] ?? ''));
if (empty($instruction)) {
    json_response([
        'success' => false,
        'error'   => t('instruction_required'),
        'code'    => 'INSTRUCTION_REQUIRED'
    ], 422);
}

if (mb_strlen($instruction) > 2000) {
    $instruction = mb_substr($instruction, 0, 2000);
}

// Extract current design JSON
$currentJsonRaw = $input['current_json'] ?? null;
if (empty($currentJsonRaw)) {
    json_response([
        'success' => false,
        'error'   => t('design_json_required'),
        'code'    => 'DESIGN_JSON_REQUIRED'
    ], 422);
}

// If passed as string, decode it
if (is_string($currentJsonRaw)) {
    $currentDesign = extract_and_decode_json($currentJsonRaw);
} elseif (is_array($currentJsonRaw)) {
    $currentDesign = $currentJsonRaw;
} else {
    $currentDesign = null;
}

if ($currentDesign === null || empty($currentDesign['canvas'])) {
    json_response([
        'success' => false,
        'error'   => t('invalid_json'),
        'code'    => 'INVALID_DESIGN_JSON'
    ], 422);
}

// Pre-sanitize input design
$currentDesign = sanitize_design_json($currentDesign);

// Call AI edit with automatic retry
$result = ai_edit_design($currentDesign, $instruction);

if (!$result['success']) {
    record_generation_history('edit', $instruction, ['previous_design_summary' => 'edit_attempt'], [
        'error' => $result['error'] ?? 'Edit failed'
    ], 'failed');

    json_response([
        'success' => false,
        'error'   => $result['error'] ?? t('ai_invalid_response'),
        'code'    => 'AI_EDIT_FAILED'
    ], 500);
}

// Record successful edit in history
$generationId = record_generation_history('edit', $instruction, ['previous_canvas' => $currentDesign['canvas'] ?? []], $result['data'], 'success');

json_response([
    'success'       => true,
    'message'       => t('edited_success'),
    'data'          => $result['data'],
    'provider'      => $result['provider'],
    'generation_id' => $generationId
]);

