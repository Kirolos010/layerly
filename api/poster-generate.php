<?php
/**
 * AI Design Studio - AI Poster Mode 2 Endpoint: "Text to Poster"
 * 
 * Flow:
 * 1. Checks quota & caches identical normalized prompt.
 * 2. Step 1 (Art Direction): Gemini text model generates visual concept, composition zones,
 *    and exact copy strictly adhering to anti-hallucination rules (NEVER invent URLs, prices, dates).
 * 3. Step 2 (Image Pass): Generates text-free background/subject via provider chain (Gemini/HF/Pollinations).
 * 4. Step 3 (Scene Assembly): Assembles Fabric.js layers (locked background, badges, typography in safe zones).
 * 5. Returns structured project JSON with observability meta.
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

$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);
if (!is_array($body)) {
    $body = $_POST;
}

$prompt = trim((string)($body['prompt'] ?? ''));
if (empty($prompt)) {
    json_response(['success' => false, 'error' => t('prompt_required')], 422);
}

$stylePreset = trim((string)($body['style_preset'] ?? 'cyber_tech'));
$width = max(320, min(2560, (int)($body['width'] ?? 1080)));
$height = max(320, min(2560, (int)($body['height'] ?? 1080)));
$step = trim((string)($body['step'] ?? 'all'));
$cacheId = trim((string)($body['cache_id'] ?? ''));

// Normalize prompt for caching
$promptHash = md5(mb_strtolower($prompt) . "_{$stylePreset}_{$width}x{$height}");
$cacheFile = $cacheDir . '/gen_' . $promptHash . '.json';

// Return cached generation if identical request and step is all
if (file_exists($cacheFile) && $step === 'all') {
    $cached = json_decode(file_get_contents($cacheFile), true);
    if ($cached && isset($cached['objects'])) {
        $cached['meta']['cached'] = true;
        $cached['quota'] = get_poster_quota();
        json_response($cached);
    }
}

// Quota check
$quotaCheck = check_poster_rate_limit();
if (!$quotaCheck['allowed']) {
    json_response([
        'success'   => false,
        'error'     => $quotaCheck['message'] ?: t('poster_quota_exceeded'),
        'remaining' => 0,
        'code'      => 'QUOTA_EXCEEDED'
    ], 429);
}

// Step 1: Art Direction
$artRes = ai_art_direct_poster($prompt, [
    'width'        => $width,
    'height'       => $height,
    'style_preset' => $stylePreset
]);

if (!$artRes['success']) {
    json_response([
        'success' => false,
        'step'    => 'art_direction',
        'error'   => $artRes['error'] ?: 'Art direction pass failed.',
        'code'    => $artRes['code'] ?? 'ART_DIRECTION_FAILED'
    ], 500);
}

$art = $artRes['art_direction'];
$canvas = [
    'width'    => $width,
    'height'   => $height,
    'bg_color' => $art['canvas']['bg_color'] ?? '#020b14',
    'theme'    => $art['canvas']['theme'] ?? 'dark'
];
$textBlocks = $art['text_blocks'] ?? [];
$imagePrompt = $art['image_prompt'] ?? "photorealistic futuristic cyber background, volumetric lighting, textless, no words, no letters";

if ($step === 'art_direction') {
    json_response([
        'success'       => true,
        'step'          => 'art_direction',
        'cache_id'      => $promptHash,
        'canvas'        => $canvas,
        'art_direction' => $art,
        'meta'          => $artRes['meta']
    ]);
}

// Step 2: Background Image Generation
$imgRes = ai_generate_poster_background($imagePrompt, $width, $height);
if (!$imgRes['success']) {
    // Honest bilingual reporting: DO NOT fake a canned poster!
    json_response([
        'success' => false,
        'step'    => 'image_pass',
        'error'   => $imgRes['error'] ?: t('poster_image_failed'),
        'code'    => 'IMAGE_GEN_FAILED',
        'options' => [
            'can_retry'       => true,
            'can_use_solid_bg'=> true,
            'can_upload_bg'   => true
        ]
    ], 502);
}

$bgImageUrl = $imgRes['image_url'];

// Step 3: Typography & Badges Assembly
$objects = [];

// 1. Bottom locked background image
$objects[] = [
    'id'                => 'bg_poster_art',
    'type'              => 'image',
    'src'               => $bgImageUrl,
    'left'              => 0,
    'top'               => 0,
    'width'             => (float)$width,
    'height'            => (float)$height,
    'selectable'        => false,
    'evented'           => false,
    'lockMovementX'     => true,
    'lockMovementY'     => true,
    'lockRotation'      => true,
    'lockScalingX'      => true,
    'lockScalingY'      => true,
    'isBackgroundLayer' => true
];

// 2. Add badge shapes and text objects
foreach ($textBlocks as $i => $block) {
    if (!empty($block['badge']) && is_array($block['badge'])) {
        $b = $block['badge'];
        $rx = (float)($b['radius'] ?? 18);
        $fill = $b['fill'] ?? '#00e5ff';
        $stroke = $b['stroke'] ?? null;

        $objects[] = [
            'id'          => "badge_" . ($block['id'] ?? $i),
            'type'        => 'rect',
            'left'        => (float)($b['left'] ?? $block['left']),
            'top'         => (float)($b['top'] ?? $block['top']),
            'width'       => (float)($b['width'] ?? $block['width']),
            'height'      => (float)($b['height'] ?? $block['height']),
            'fill'        => $fill,
            'stroke'      => $stroke,
            'strokeWidth' => $stroke ? 2 : 0,
            'rx'          => $rx,
            'ry'          => $rx,
            'opacity'     => 1,
            'selectable'  => true,
            'isBadge'     => true
        ];
    }

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
        'id'         => (string)($block['id'] ?? "text_{$i}"),
        'type'       => 'text',
        'text'       => $block['text'],
        'left'       => (float)$block['left'],
        'top'        => (float)$block['top'],
        'width'      => (float)$block['width'],
        'height'     => (float)$block['height'],
        'fontSize'   => (float)$block['fontSize'],
        'fontFamily' => $block['fontFamily'] ?? 'Cairo',
        'fontWeight' => $block['fontWeight'] ?? 'bold',
        'fill'       => $block['color'] ?? '#ffffff',
        'textAlign'  => $block['align'] ?? 'center',
        'direction'  => $block['dir'] ?? 'rtl',
        'shadow'     => $shadow,
        'selectable' => true,
        'isEditable' => true
    ];
}

// Increment quota
increment_poster_quota();
$remainingQuota = get_poster_quota();

// Save generation history
$userId = get_current_user_id();
$guestToken = get_or_set_guest_token();
$resultPayload = [
    'canvas'  => $canvas,
    'objects' => $objects,
    'meta'    => [
        'mode'                 => 'text_to_poster',
        'provider'             => $artRes['meta']['provider'] ?? 'gemini',
        'model'                => ai_get_validated_gemini_model('text'),
        'image_provider'       => $imgRes['meta']['provider'] ?? 'pollinations',
        'art_latency_ms'       => $artRes['meta']['latency_ms'] ?? 0,
        'image_latency_ms'     => $imgRes['meta']['latency_ms'] ?? 0,
        'clean_plate_method'   => 'generated_background',
        'fallback_used'        => false,
        'notice'               => null
    ]
];

save_generation_history(
    $userId,
    $guestToken,
    'poster_generate',
    $prompt,
    ['style_preset' => $stylePreset, 'width' => $width, 'height' => $height],
    $resultPayload,
    'success'
);

// Cache full payload
$responsePayload = [
    'success'            => true,
    'step'               => 'complete',
    'cache_id'           => $promptHash,
    'canvas'             => $canvas,
    'objects'            => $objects,
    'clean_plate_url'    => $bgImageUrl,
    'clean_plate_method' => 'generated_background',
    'fallback_used'      => false,
    'notice'             => null,
    'meta'               => $resultPayload['meta'],
    'quota'              => $remainingQuota
];
file_put_contents($cacheFile, json_encode($responsePayload));

json_response($responsePayload);
