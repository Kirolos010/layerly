<?php
/**
 * AI Design Studio - AI Poster Mode 1 Endpoint: "Import & Make Editable"
 * 
 * Flow:
 * 1. Uploads poster image (max 5MB, finfo MIME verification).
 * 2. Caches by image SHA256/MD5 so repeated uploads don't burn quota.
 * 3. Step 1 (Vision Analysis): Gemini Vision structured OCR (no invented text).
 * 4. Step 2 (Clean Plate): Configured image model or inpainting fallback (PHP GD).
 * 5. Step 3 (Scene Construction): Fabric.js layers (clean plate bg, badges, auto-fit textboxes).
 * 6. Supports step-by-step retry via ?step=analyze | clean_plate | all.
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/history.php';
require_once dirname(__DIR__) . '/includes/rate-limiter.php';
require_once dirname(__DIR__) . '/includes/ai-client.php';

send_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method Not Allowed.'], 405);
}

// Ensure cache directory exists
$cacheDir = dirname(__DIR__) . '/storage/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
$uploadDir = dirname(__DIR__) . '/storage/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$step = trim((string)($_POST['step'] ?? 'all'));
$cacheId = trim((string)($_POST['cache_id'] ?? ''));
$useOriginalBg = !empty($_POST['use_original_bg']) && ($_POST['use_original_bg'] === '1' || $_POST['use_original_bg'] === 'true');
$forceFallback = !empty($_POST['force_fallback']) && ($_POST['force_fallback'] === '1' || $_POST['force_fallback'] === 'true');

// If step is clean_plate only and cache_id is provided
if ($step === 'clean_plate' && !empty($cacheId)) {
    $cacheFile = $cacheDir . '/import_' . preg_replace('/[^a-f0-9]/', '', $cacheId) . '.json';
    if (!file_exists($cacheFile)) {
        json_response(['success' => false, 'error' => 'Analysis session expired. Please re-upload the image.'], 404);
    }
    $sessionData = json_decode(file_get_contents($cacheFile), true);
    if (!$sessionData || empty($sessionData['image_path'])) {
        json_response(['success' => false, 'error' => 'Invalid session data.'], 400);
    }

    $imagePath = $sessionData['image_path'];
    $textBlocks = $sessionData['text_blocks'] ?? [];
    $canvas = $sessionData['canvas'] ?? ['width' => 1024, 'height' => 558];

    if ($useOriginalBg) {
        $cleanRes = [
            'success'          => true,
            'clean_plate_url'  => $sessionData['original_image_url'],
            'clean_plate_path' => $imagePath,
            'method'           => 'kept_original',
            'fallback_used'    => false,
            'notice'           => null,
            'latency_ms'       => 0
        ];
    } else {
        $cleanRes = ai_generate_clean_plate($imagePath, $textBlocks, $canvas['width'], $canvasH = $canvas['height'], $forceFallback);
    }

    if (!$cleanRes['success']) {
        json_response(['success' => false, 'error' => $cleanRes['error'] ?? 'Clean plate generation failed.'], 500);
    }

    $quota = get_poster_quota();
    json_response([
        'success'         => true,
        'step'            => 'clean_plate',
        'cache_id'        => $cacheId,
        'clean_plate_url' => $cleanRes['clean_plate_url'],
        'method'          => $cleanRes['method'],
        'fallback_used'   => $cleanRes['fallback_used'],
        'notice'          => $cleanRes['notice'],
        'latency_ms'      => $cleanRes['latency_ms'] ?? 0,
        'quota'           => $quota
    ]);
}

// Normal upload: verify image file
$fileData = $_FILES['image'] ?? ($_FILES['file'] ?? null);
if (!$fileData || !isset($fileData['error'])) {
    json_response(['success' => false, 'error' => 'No image file uploaded.'], 422);
}

if ($fileData['error'] !== UPLOAD_ERR_OK) {
    json_response(['success' => false, 'error' => 'File upload error code: ' . $fileData['error']], 400);
}

if ($fileData['size'] > 5242880) { // 5MB
    json_response(['success' => false, 'error' => 'Image exceeds 5MB limit.'], 413);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($fileData['tmp_name']);
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

if (!isset($allowedMimes[$mime])) {
    json_response(['success' => false, 'error' => 'Invalid image format. Only JPG, PNG, and WebP are allowed.'], 415);
}

// Compute hash of the file
$fileHash = md5_file($fileData['tmp_name']);
$cacheFile = $cacheDir . '/import_' . $fileHash . '.json';

// Check cache for identical image upload
if (file_exists($cacheFile) && $step === 'all') {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if ($cached && isset($cached['objects'])) {
        $cached['meta']['cached'] = true;
        $cached['quota'] = get_poster_quota();
        json_response($cached);
    }
}

// Rate limiting check
$quotaCheck = check_poster_rate_limit();
if (!$quotaCheck['allowed']) {
    json_response([
        'success'   => false,
        'error'     => $quotaCheck['message'] ?: t('poster_quota_exceeded'),
        'remaining' => 0,
        'code'      => 'QUOTA_EXCEEDED'
    ], 429);
}

// Move to uploads directory
$destExt = $allowedMimes[$mime];
$savedFilename = "poster_src_{$fileHash}.{$destExt}";
$destPath = $uploadDir . '/' . $savedFilename;
if (!file_exists($destPath)) {
    if (!move_uploaded_file($fileData['tmp_name'], $destPath)) {
        copy($fileData['tmp_name'], $destPath);
    }
}
$originalImageUrl = 'api/upload.php?file=' . $savedFilename;

// Step 1: Vision Analysis
$visionRes = ai_vision_analyze_poster($destPath, $mime);
if (!$visionRes['success']) {
    json_response([
        'success' => false,
        'step'    => 'analyze',
        'error'   => $visionRes['error'] ?: 'Vision analysis failed to transcribe poster text.',
        'code'    => $visionRes['code'] ?? 'VISION_FAILED'
    ], 500);
}

$canvas = $visionRes['canvas'];
$textBlocks = $visionRes['text_blocks'];

// Save intermediate state in cache
$sessionData = [
    'file_hash'          => $fileHash,
    'image_path'         => $destPath,
    'original_image_url' => $originalImageUrl,
    'canvas'             => $canvas,
    'text_blocks'        => $textBlocks,
    'created_at'         => time()
];
file_put_contents($cacheFile, json_encode($sessionData));

if ($step === 'analyze') {
    // Return early if only analyze step was requested
    json_response([
        'success'            => true,
        'step'               => 'analyze',
        'cache_id'           => $fileHash,
        'canvas'             => $canvas,
        'text_blocks'        => $textBlocks,
        'original_image_url' => $originalImageUrl,
        'meta'               => $visionRes['meta']
    ]);
}

// Step 2: Clean Plate
if ($useOriginalBg) {
    $cleanRes = [
        'success'          => true,
        'clean_plate_url'  => $originalImageUrl,
        'clean_plate_path' => $destPath,
        'method'           => 'kept_original',
        'fallback_used'    => false,
        'notice'           => null,
        'latency_ms'       => 0
    ];
} else {
    $cleanRes = ai_generate_clean_plate($destPath, $textBlocks, $canvas['width'], $canvas['height'], $forceFallback);
}

if (!$cleanRes['success']) {
    json_response([
        'success' => false,
        'step'    => 'clean_plate',
        'error'   => $cleanRes['error'] ?: 'Clean plate generation failed.',
        'code'    => 'CLEAN_PLATE_FAILED'
    ], 500);
}

// Step 3: Construct Fabric.js Layer Scene
$bgUrl = $cleanRes['clean_plate_url'];
$objects = [];

// 1. Bottom locked background layer
$objects[] = [
    'id'                 => 'bg_clean_plate',
    'type'               => 'image',
    'src'                => $bgUrl,
    'left'               => 0,
    'top'                => 0,
    'width'              => (float)$canvas['width'],
    'height'             => (float)$canvas['height'],
    'selectable'         => false,
    'evented'            => false,
    'lockMovementX'      => true,
    'lockMovementY'      => true,
    'lockRotation'       => true,
    'lockScalingX'       => true,
    'lockScalingY'       => true,
    'isBackgroundLayer'  => true,
    'originalImageUrl'   => $originalImageUrl,
    'cleanPlateUrl'      => $cleanRes['clean_plate_url']
];

// 2. Pill badges and text layers
foreach ($textBlocks as $i => $block) {
    // If block has a badge/pill, add it behind the text
    if (!empty($block['badge'])) {
        $b = $block['badge'];
        $bType = $b['type'] ?? 'pill';
        $rx = (float)($b['radius'] ?? 18);
        $fill = $b['fill'] ?? '#00e5ff';
        $stroke = $b['stroke'] ?? null;
        $strokeWidth = $stroke ? 2 : 0;

        $objects[] = [
            'id'          => "badge_" . ($block['id'] ?? $i),
            'type'        => 'rect',
            'left'        => (float)$b['left'],
            'top'         => (float)$b['top'],
            'width'       => (float)$b['width'],
            'height'      => (float)$b['height'],
            'fill'        => $fill,
            'stroke'      => $stroke,
            'strokeWidth' => $strokeWidth,
            'rx'          => $rx,
            'ry'          => $rx,
            'opacity'     => 1,
            'selectable'  => true,
            'isBadge'     => true,
            'linkedTextId'=> (string)($block['id'] ?? "text_{$i}")
        ];
    }

    // Textbox object
    $fontSize = (float)$block['fontSize'];
    $color = $block['color'] ?? '#ffffff';
    $fontFamily = $block['fontFamily'] ?? ($block['lang'] === 'ar' ? 'Cairo' : 'Poppins');
    $fontWeight = $block['fontWeight'] ?? 'bold';
    $align = $block['align'] ?? ($block['lang'] === 'ar' ? 'right' : 'left');

    $shadow = null;
    if (!empty($block['glowColor']) && !empty($block['glowBlur'])) {
        $shadow = [
            'color'   => $block['glowColor'],
            'blur'    => (int)$block['glowBlur'],
            'offsetX' => 0,
            'offsetY' => 0
        ];
    }

    $objects[] = [
        'id'          => (string)($block['id'] ?? "text_{$i}"),
        'type'        => 'text',
        'text'        => $block['text'],
        'left'        => (float)$block['left'],
        'top'         => (float)$block['top'],
        'width'       => (float)$block['width'],
        'height'      => (float)$block['height'],
        'fontSize'    => $fontSize,
        'fontFamily'  => $fontFamily,
        'fontWeight'  => $fontWeight,
        'fill'        => $color,
        'textAlign'   => $align,
        'direction'   => $block['dir'] ?? 'rtl',
        'shadow'      => $shadow,
        'selectable'  => true,
        'isEditable'  => true,
        'originalBox' => [
            'left'     => (float)$block['left'],
            'top'      => (float)$block['top'],
            'width'    => (float)$block['width'],
            'height'   => (float)$block['height'],
            'fontSize' => $fontSize
        ]
    ];
}

// Decrement quota now that full pipeline succeeded
increment_poster_quota();
$remainingQuota = get_poster_quota();

// Record in generation history
$userId = get_current_user_id();
$guestToken = get_or_set_guest_token();
$resultPayload = [
    'canvas'             => $canvas,
    'objects'            => $objects,
    'original_image_url' => $originalImageUrl,
    'clean_plate_url'    => $cleanRes['clean_plate_url'],
    'text_blocks'        => $textBlocks,
    'meta'               => [
        'mode'               => 'import_and_make_editable',
        'provider'           => $visionRes['meta']['provider'] ?? 'gemini',
        'model'              => $visionRes['meta']['model'] ?? 'gemini-3.5-flash',
        'vision_latency_ms'  => $visionRes['meta']['latency_ms'] ?? 0,
        'clean_plate_latency'=> $cleanRes['latency_ms'] ?? 0,
        'clean_plate_method' => $cleanRes['method'],
        'fallback_used'      => $cleanRes['fallback_used'],
        'notice'             => $cleanRes['notice']
    ]
];

save_generation_history(
    $userId,
    $guestToken,
    'poster_import',
    'Imported Poster: ' . ($fileData['name'] ?? 'poster.jpg'),
    ['filename' => $savedFilename, 'file_hash' => $fileHash],
    $resultPayload,
    'success'
);

// Cache full payload
$responsePayload = [
    'success'            => true,
    'step'               => 'complete',
    'cache_id'           => $fileHash,
    'canvas'             => $canvas,
    'objects'            => $objects,
    'original_image_url' => $originalImageUrl,
    'clean_plate_url'    => $cleanRes['clean_plate_url'],
    'text_blocks'        => $textBlocks,
    'clean_plate_method' => $cleanRes['method'],
    'fallback_used'      => $cleanRes['fallback_used'],
    'notice'             => $cleanRes['notice'],
    'meta'               => $resultPayload['meta'],
    'quota'              => $remainingQuota
];
file_put_contents($cacheFile, json_encode($responsePayload));

json_response($responsePayload);
