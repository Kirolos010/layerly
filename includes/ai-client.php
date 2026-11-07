<?php
/**
 * AI Design Studio - AI Client Facade
 * 
 * Provides backwards-compatible API layer for AI operations.
 * Modular implementation is divided across:
 * - includes/ai/GeminiClient.php       (Google Gemini text, vision, and ListModels)
 * - includes/ai/HuggingFaceClient.php  (RMBG background removal)
 * - includes/ai/PollinationsClient.php (Text-free background illustration generation)
 * - includes/ai/ProviderChain.php      (Failover, deadlines, 429 flood control, JSON repair)
 * - includes/ai/Prompts.php            (Structured schemas and system prompts)
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/sanitizer.php';
require_once __DIR__ . '/ai/Prompts.php';
require_once __DIR__ . '/ai/GeminiClient.php';
require_once __DIR__ . '/ai/HuggingFaceClient.php';
require_once __DIR__ . '/ai/PollinationsClient.php';
require_once __DIR__ . '/ai/ProviderChain.php';

/**
 * Gemini responseSchema for structured Canvas JSON generation
 */
function get_design_gemini_schema(): array {
    return Prompts::getDesignSchema();
}

/**
 * Call Google Gemini API (generateContent)
 */
function call_gemini_api(string $systemPrompt, string $userPrompt, ?array $schema = null, $jsonMode = true, ?string $overrideKey = null): array {
    if (is_string($jsonMode)) {
        $overrideKey = $jsonMode;
        $jsonMode = true;
    }
    return GeminiClient::callText($systemPrompt, $userPrompt, $schema, (bool)$jsonMode, $overrideKey);
}

/**
 * Call secondary fallback provider (Groq or OpenRouter)
 */
function call_fallback_api(string $systemPrompt, string $userPrompt, bool $jsonMode = true): array {
    return ProviderChain::callFallback($systemPrompt, $userPrompt, $jsonMode);
}

/**
 * Dispatch an AI request through the ordered provider chain
 */
function ai_dispatch(string $systemPrompt, string $userPrompt, ?array $schema = null, bool $jsonMode = true): array {
    return ProviderChain::dispatch($systemPrompt, $userPrompt, $schema, $jsonMode);
}

/**
 * Enhance a user's rough idea into a detailed creative brief
 */
function ai_enhance_prompt(string $rawIdea, ?string $lang = null): array {
    $rawIdea = trim($rawIdea);
    if (empty($rawIdea)) {
        return [
            'success' => false,
            'error'   => t('prompt_required')
        ];
    }

    $isArabic = is_arabic_text($rawIdea);
    if ($lang === null) {
        $lang = $isArabic ? 'ar' : 'en';
    }

    $systemInstruction = "You are an expert graphic design creative director and art strategist.
Your task: Take a user's brief design idea or keyword and expand it into a rich, detailed, professional design brief.
CRITICAL RULES:
1. You MUST reply in the EXACT SAME LANGUAGE as the user wrote (" . ($isArabic ? "Arabic" : "English") . ").
2. Include:
   - Creative Theme & Concept
   - Harmonious Color Palette (primary, secondary, accent with emotional mood)
   - Visual Hierarchy & Layout strategy
   - Typography suggestions (Available fonts: Cairo, Tajawal, Almarai, Amiri, Reem Kufi for Arabic; Poppins, Montserrat, Playfair Display, Roboto for English/Latin)
   - Specific Text Content (Catchy main title, engaging subtitle, call-to-action text, badge/offer text)
3. Return ONLY clear, structured text suitable to guide design element generation. Do not output JSON.";

    $userPrompt = "Please expand and enhance this design idea into a complete design brief in " . ($isArabic ? "Arabic" : "English") . ":\n\"{$rawIdea}\"";

    $res = ProviderChain::dispatch($systemInstruction, $userPrompt, null, false);

    if (!$res['success'] || empty($res['content'])) {
        return [
            'success' => false,
            'code'    => $res['code'] ?? 'AI_FAILED',
            'error'   => $res['error'] ?? t('ai_invalid_response')
        ];
    }

    return [
        'success'         => true,
        'enhanced_prompt' => trim($res['content']),
        'original_prompt' => $rawIdea,
        'detected_lang'   => $lang,
        'provider'        => $res['provider'],
        'model'           => $res['model'] ?? null
    ];
}

/**
 * Generate complete design JSON layout
 */
function ai_generate_design(string $prompt, int $width = 1080, int $height = 1080, array $options = []): array {
    $prompt = trim($prompt);
    if (empty($prompt)) {
        return ['success' => false, 'error' => t('prompt_required')];
    }

    $isArabic = is_arabic_text($prompt) || ($options['language'] ?? '') === 'ar';
    $withBg = !empty($options['generate_background']);

    $systemInstruction = Prompts::getDesignSystemPrompt($isArabic, $width, $height, $options, $withBg);
    $schema = Prompts::getDesignSchema();

    $userPrompt = "Create a complete, polished graphic design JSON for the following brief:
Brief: \"{$prompt}\"
Design Style: " . ($options['style'] ?? 'modern') . "
Color Mood: " . ($options['color_mood'] ?? 'balanced') . "
Canvas: {$width}x{$height} px
Language: " . ($isArabic ? 'Arabic' : 'English');

    $res = ProviderChain::dispatch($systemInstruction, $userPrompt, $schema, true);

    if (!$res['success'] || empty($res['content'])) {
        return [
            'success' => false,
            'code'    => $res['code'] ?? 'AI_FAILED',
            'error'   => $res['error'] ?? t('ai_invalid_response')
        ];
    }

    $parsed = extract_and_decode_json($res['content']);

    // Single repair retry if invalid
    if ($parsed === null || !isset($parsed['objects']) || !is_array($parsed['objects'])) {
        $parsed = ProviderChain::repairJson($res['content'], $systemInstruction, $schema);
    }

    if ($parsed === null || !isset($parsed['objects']) || !is_array($parsed['objects'])) {
        app_log("Failed to parse AI JSON after retry", 'ERROR', 'ai.log');
        return [
            'success' => false,
            'code'    => 'INVALID_JSON',
            'error'   => t('ai_invalid_response')
        ];
    }

    if ($withBg) {
        $bgPrompt = trim((string)($parsed['canvas']['background_image_prompt'] ?? ''));
        if (empty($bgPrompt)) {
            $bgPrompt = "clean digital illustration of {$prompt}, artistic background, no text, no words";
        }
        $proxyUrl = PollinationsClient::buildProxyUrl($bgPrompt, $width, $height);
        $bgLayer = [
            'type'         => 'image',
            'src'          => $proxyUrl,
            'left'         => 0,
            'top'          => 0,
            'width'        => $width,
            'height'       => $height,
            'fill'         => '#000000',
            'locked'       => true,
            'isBackground' => true,
            'name'         => 'AI Background'
        ];
        array_unshift($parsed['objects'], $bgLayer);
    }

    $sanitized = sanitize_design_json($parsed, $width, $height);

    return [
        'success'  => true,
        'data'     => $sanitized,
        'provider' => $res['provider'],
        'model'    => $res['model'] ?? null
    ];
}

/**
 * Call Google Gemini Vision API
 */
function call_gemini_vision_api(string $base64Data, string $mimeType, string $userPrompt, ?array $schema = null): array {
    return GeminiClient::callVision($base64Data, $mimeType, $userPrompt, $schema);
}

/**
 * OCR text extraction from an uploaded image
 */
function ai_extract_text_from_image(string $imageData, int $imgWidth = 1080, int $imgHeight = 1080): array {
    if (empty(trim($imageData))) {
        return ['success' => false, 'code' => 'EMPTY_IMAGE', 'error' => t('image_empty')];
    }

    $mimeType = 'image/jpeg';
    $base64 = $imageData;

    if (preg_match('/^data:(image\/\w+);base64,(.+)$/', $imageData, $matches)) {
        $mimeType = $matches[1];
        $base64 = $matches[2];
    }

    $schema = Prompts::getOcrSchema();

    $prompt = "You are a professional OCR and graphic design vision system.
Analyze this design image carefully.
Detect and extract every text block, word, heading, or label visible in the image.
For each text block return:
- text: The exact visible text content
- left_percent: Horizontal start position as a percentage from left (0 to 100)
- top_percent: Vertical start position as a percentage from top (0 to 100)
- fontSize: Estimated font size in pixels (relative to canvas height {$imgHeight}px)
- fontWeight: 'bold' or 'normal'
- color: Hex color of the text (e.g. '#ffffff' or '#1e293b')
- fontFamily: Closest matching Google Font ('Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi' for Arabic; 'Poppins', 'Montserrat', 'Playfair Display', 'Roboto' for English)
- language: 'ar' for Arabic text, 'en' for English/Latin text

Return ONLY valid JSON matching the schema.";

    $res = GeminiClient::callVision($base64, $mimeType, $prompt, $schema);

    if (!$res['success'] || empty($res['content'])) {
        return [
            'success' => false,
            'code'    => $res['code'] ?? 'OCR_FAILED',
            'error'   => $res['error'] ?? t('ai_invalid_response'),
            'blocks'  => []
        ];
    }

    $parsed = extract_and_decode_json($res['content']);
    if ($parsed === null || empty($parsed['blocks']) || !is_array($parsed['blocks'])) {
        return [
            'success' => false,
            'code'    => 'EMPTY_OCR',
            'error'   => t('ai_invalid_response'),
            'blocks'  => []
        ];
    }

    // Normalize coordinates to canvas pixel values
    $blocks = [];
    foreach ($parsed['blocks'] as $b) {
        $text = trim((string)($b['text'] ?? ''));
        if (empty($text)) continue;

        $leftPercent = max(0.0, min(100.0, (float)($b['left_percent'] ?? 0)));
        $topPercent = max(0.0, min(100.0, (float)($b['top_percent'] ?? 0)));
        $left = round(($leftPercent / 100.0) * $imgWidth);
        $top = round(($topPercent / 100.0) * $imgHeight);

        $isAr = is_arabic_text($text) || ($b['language'] ?? '') === 'ar';
        $blocks[] = [
            'text'         => $text,
            'left'         => $left,
            'top'          => $top,
            'left_percent' => $leftPercent,
            'top_percent'  => $topPercent,
            'fontSize'     => max(12.0, min(200.0, (float)($b['fontSize'] ?? 32))),
            'fontWeight'   => ($b['fontWeight'] ?? 'normal') === 'bold' ? 'bold' : 'normal',
            'fill'         => !empty($b['color']) ? $b['color'] : '#ffffff',
            'fontFamily'   => !empty($b['fontFamily']) ? $b['fontFamily'] : ($isAr ? 'Cairo' : 'Poppins'),
            'direction'    => $isAr ? 'rtl' : 'ltr',
            'textAlign'    => $isAr ? 'right' : 'left',
            'language'     => $isAr ? 'ar' : 'en'
        ];
    }

    return [
        'success'  => true,
        'blocks'   => $blocks,
        'provider' => 'gemini',
        'model'    => $res['model'] ?? null
    ];
}

/**
 * Remove image background via Hugging Face RMBG
 */
function ai_remove_background_rmbg(string $imageData): array {
    return HuggingFaceClient::removeBackground($imageData);
}

/**
 * Edit existing design JSON with natural language instruction
 */
function ai_edit_design(array $currentDesign, string $instruction): array {
    $instruction = trim($instruction);
    if (empty($instruction)) {
        return ['success' => false, 'error' => t('prompt_required')];
    }

    $width  = (int)($currentDesign['canvas']['width'] ?? 1080);
    $height = (int)($currentDesign['canvas']['height'] ?? 1080);

    $systemInstruction = "You are an expert graphic designer and UI/UX art director.
A user wants to modify their existing Fabric.js graphic design JSON layout using natural language instructions.
CRITICAL RULES:
1. Preserve all existing elements, texts, and IDs unless the user specifically asks to change, move, replace, or delete them.
2. If the user asks to change colors, update the 'fill' and 'stroke' properties appropriately.
3. If the user asks to resize or move an element, update 'fontSize', 'width', 'height', 'left', or 'top'.
4. If the user asks to add an element, insert a new object conforming to the schema.
5. All Arabic text must have direction='rtl', textAlign='right' or 'center', and use Arabic fonts ('Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi').
6. Return ONLY the complete, updated valid JSON object conforming to the schema.";

    $cleanDesignJson = json_encode($currentDesign, JSON_UNESCAPED_UNICODE);
    $userPrompt = "CURRENT DESIGN JSON:
{$cleanDesignJson}

MODIFICATION INSTRUCTION:
\"{$instruction}\"

Return the complete updated JSON:";

    $schema = Prompts::getDesignSchema();

    $res = ProviderChain::dispatch($systemInstruction, $userPrompt, $schema, true);

    if (!$res['success'] || empty($res['content'])) {
        return [
            'success' => false,
            'code'    => $res['code'] ?? 'AI_FAILED',
            'error'   => $res['error'] ?? t('ai_invalid_response')
        ];
    }

    $parsed = extract_and_decode_json($res['content']);

    if ($parsed === null || !isset($parsed['objects']) || !is_array($parsed['objects'])) {
        $parsed = ProviderChain::repairJson($res['content'], $systemInstruction, $schema);
    }

    if ($parsed === null || !isset($parsed['objects']) || !is_array($parsed['objects'])) {
        return [
            'success' => false,
            'code'    => 'INVALID_JSON',
            'error'   => t('ai_invalid_response')
        ];
    }

    $sanitized = sanitize_design_json($parsed, $width, $height);

    return [
        'success'  => true,
        'data'     => $sanitized,
        'provider' => $res['provider'],
        'model'    => $res['model'] ?? null
    ];
}

/**
 * Validate configured Gemini model against ListModels API
 */
function ai_get_validated_gemini_model(string $type = 'text'): string {
    return GeminiClient::validateModel($type);
}

/**
 * Vision analysis for Phase 6 Mode 1 ("Import & Make Editable")
 */
function ai_vision_analyze_poster(string $imagePath, string $mimeType = 'image/jpeg'): array {
    $t0 = microtime(true);
    if (!file_exists($imagePath)) {
        return ['success' => false, 'error' => 'Source image not found.', 'code' => 'FILE_NOT_FOUND'];
    }

    $imgInfo = @getimagesize($imagePath);
    $canvasW = $imgInfo ? (int)$imgInfo[0] : 1024;
    $canvasH = $imgInfo ? (int)$imgInfo[1] : 558;

    $raw = file_get_contents($imagePath);
    if ($raw === false) {
        return ['success' => false, 'error' => 'Failed to read image data.', 'code' => 'READ_FAILED'];
    }
    $b64 = base64_encode($raw);

    $prompt = "You are an expert graphic designer and OCR system.
Analyze this poster image ({$canvasW}x{$canvasH}).
Identify and transcribe EVERY text block on the image exactly as written.
Do not invent or translate text.

Output a valid JSON object matching this structure:
{
  \"canvas\": { \"width\": {$canvasW}, \"height\": {$canvasH}, \"bg_color\": \"#020b14\", \"theme\": \"dark\" },
  \"text_blocks\": [
    {
      \"id\": \"headline_1\",
      \"text\": \"Text exactly as shown\",
      \"lang\": \"ar\",
      \"dir\": \"rtl\",
      \"left\": 100,
      \"top\": 50,
      \"width\": 800,
      \"height\": 60,
      \"fontSize\": 42,
      \"fontWeight\": \"bold\",
      \"color\": \"#00f0ff\",
      \"glowColor\": \"#00c8ff\",
      \"glowBlur\": 10,
      \"align\": \"right\",
      \"fontFamily\": \"Cairo\",
      \"badge\": null
    }
  ]
}

Whitelisted fonts:
Arabic: 'Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi'
English: 'Poppins', 'Montserrat', 'Playfair Display', 'Roboto'";

    $res = GeminiClient::callVision($b64, $mimeType, $prompt);

    if (!$res['success'] || empty($res['content'])) {
        return [
            'success' => false,
            'error'   => $res['error'] ?? 'Vision analysis failed.',
            'code'    => $res['code'] ?? 'VISION_FAILED'
        ];
    }

    $data = extract_and_decode_json($res['content']);
    if (!$data || empty($data['text_blocks'])) {
        return [
            'success' => false,
            'error'   => 'Could not extract text structure from image.',
            'code'    => 'PARSE_ERROR'
        ];
    }

    // Whitelist fonts
    $allowedFonts = ['Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi', 'Poppins', 'Montserrat', 'Playfair Display', 'Roboto'];
    $allowedLangs = ['ar', 'en'];

    $sanitizedBlocks = [];
    foreach ($data['text_blocks'] as $idx => $b) {
        $text = trim((string)($b['text'] ?? ''));
        if (empty($text)) continue;

        $isAr = is_arabic_text($text) || (($b['lang'] ?? '') === 'ar');
        $lang = $isAr ? 'ar' : 'en';
        $dir  = $isAr ? 'rtl' : 'ltr';

        $font = (string)($b['fontFamily'] ?? '');
        if (!in_array($font, $allowedFonts, true)) {
            $font = $isAr ? 'Cairo' : 'Poppins';
        }

        $block = [
            'id'         => (string)($b['id'] ?? ('block_' . $idx)),
            'text'       => $text,
            'lang'       => $lang,
            'dir'        => $dir,
            'left'       => (float)($b['left'] ?? 0),
            'top'        => (float)($b['top'] ?? 0),
            'width'      => (float)($b['width'] ?? 200),
            'height'     => (float)($b['height'] ?? 40),
            'fontSize'   => (float)($b['fontSize'] ?? 24),
            'fontWeight' => ($b['fontWeight'] ?? 'normal') === 'bold' ? 'bold' : 'normal',
            'color'      => (string)($b['color'] ?? ($isAr ? '#ffffff' : '#000000')),
            'glowColor'  => !empty($b['glowColor']) ? (string)$b['glowColor'] : null,
            'glowBlur'   => !empty($b['glowBlur']) ? (float)$b['glowBlur'] : 0,
            'align'      => in_array($b['align'] ?? '', ['left', 'center', 'right'], true) ? $b['align'] : ($isAr ? 'right' : 'left'),
            'fontFamily' => $font,
            'badge'      => !empty($b['badge']) && is_array($b['badge']) ? $b['badge'] : null
        ];
        $sanitizedBlocks[] = $block;
    }

    $elapsed = round((microtime(true) - $t0) * 1000);

    return [
        'success'     => true,
        'canvas'      => [
            'width'    => $canvasW,
            'height'   => $canvasH,
            'bg_color' => $data['canvas']['bg_color'] ?? '#020b14',
            'theme'    => $data['canvas']['theme'] ?? 'dark'
        ],
        'text_blocks' => $sanitizedBlocks,
        'meta'        => [
            'count'      => count($sanitizedBlocks),
            'latency_ms' => $elapsed,
            'model'      => $res['model'] ?? null
        ]
    ];
}

/**
 * Pure PHP GD Inpainting Engine: Text Box Erase & Soft Diffusion Fallback
 */
function ai_poster_inpaint_gd(string $sourcePath, array $textBlocks, string $outputPath): bool {
    $info = @getimagesize($sourcePath);
    if (!$info) return false;
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $img = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $img = @imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    if (!$img) return false;

    if (!imageistruecolor($img)) {
        imagepalettetotruecolor($img);
    }

    $imgW = imagesx($img);
    $imgH = imagesy($img);

    $boxes = [];
    foreach ($textBlocks as $b) {
        if (isset($b['left'], $b['top'], $b['width'], $b['height'])) {
            $boxes[] = [
                'x' => (int)$b['left'],
                'y' => (int)$b['top'],
                'w' => (int)$b['width'],
                'h' => (int)$b['height']
            ];
        }
        if (!empty($b['badge']) && is_array($b['badge'])) {
            $badge = $b['badge'];
            if (isset($badge['left'], $badge['top'], $badge['width'], $badge['height'])) {
                $boxes[] = [
                    'x' => (int)$badge['left'],
                    'y' => (int)$badge['top'],
                    'w' => (int)$badge['width'],
                    'h' => (int)$badge['height']
                ];
            }
        }
    }

    foreach ($boxes as $box) {
        $pad = 6;
        $x0 = max(0, $box['x'] - $pad);
        $y0 = max(0, $box['y'] - $pad);
        $x1 = min($imgW - 1, $box['x'] + $box['w'] + $pad);
        $y1 = min($imgH - 1, $box['y'] + $box['h'] + $pad);

        $boxW = $x1 - $x0 + 1;
        $boxH = $y1 - $y0 + 1;

        if ($boxW <= 0 || $boxH <= 0) continue;

        $topColors = [];
        $botColors = [];
        for ($x = $x0; $x <= $x1; $x += max(1, (int)($boxW / 12))) {
            $sampleYTop = max(0, $y0 - 4);
            $sampleYBot = min($imgH - 1, $y1 + 4);
            $topColors[] = imagecolorat($img, $x, $sampleYTop);
            $botColors[] = imagecolorat($img, $x, $sampleYBot);
        }

        $avgRTop = 0; $avgGTop = 0; $avgBTop = 0;
        foreach ($topColors as $c) {
            $avgRTop += ($c >> 16) & 0xFF;
            $avgGTop += ($c >> 8) & 0xFF;
            $avgBTop += $c & 0xFF;
        }
        $countTop = max(1, count($topColors));
        $avgRTop /= $countTop; $avgGTop /= $countTop; $avgBTop /= $countTop;

        $avgRBot = 0; $avgGBot = 0; $avgBBot = 0;
        foreach ($botColors as $c) {
            $avgRBot += ($c >> 16) & 0xFF;
            $avgGBot += ($c >> 8) & 0xFF;
            $avgBBot += $c & 0xFF;
        }
        $countBot = max(1, count($botColors));
        $avgRBot /= $countBot; $avgGBot /= $countBot; $avgBBot /= $countBot;

        for ($y = $y0; $y <= $y1; $y++) {
            $ratio = $boxH > 1 ? ($y - $y0) / ($boxH - 1) : 0.5;
            $r = (int)round((1.0 - $ratio) * $avgRTop + $ratio * $avgRBot);
            $g = (int)round((1.0 - $ratio) * $avgGTop + $ratio * $avgGBot);
            $b = (int)round((1.0 - $ratio) * $avgBTop + $ratio * $avgBBot);

            $noise = rand(-4, 4);
            $r = max(0, min(255, $r + $noise));
            $g = max(0, min(255, $g + $noise));
            $b = max(0, min(255, $b + $noise));

            $color = imagecolorallocate($img, $r, $g, $b);
            imageline($img, $x0, $y, $x1, $y, $color);
        }
    }

    for ($i = 0; $i < 2; $i++) {
        imagefilter($img, IMG_FILTER_SMOOTH, 2);
    }

    $ok = imagejpeg($img, $outputPath, 92);
    imagedestroy($img);
    return $ok;
}

/**
 * Generate clean plate background for imported poster
 */
function ai_generate_clean_plate(string $sourcePath, array $textBlocks, int $canvasW, int $canvasH, bool $forceFallback = false): array {
    $t0 = microtime(true);
    $uploadDir = dirname(__DIR__) . '/storage/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $hash = md5_file($sourcePath) ?: bin2hex(random_bytes(8));
    $filename = "clean_plate_{$hash}.jpg";
    $outputPath = $uploadDir . '/' . $filename;
    $cleanUrl = 'api/upload.php?file=' . $filename;

    if (file_exists($outputPath) && filesize($outputPath) > 1000) {
        $elapsed = round((microtime(true) - $t0) * 1000);
        return [
            'success'          => true,
            'clean_plate_url'  => $cleanUrl,
            'clean_plate_path' => $outputPath,
            'method'           => 'inpainting_fallback',
            'fallback_used'    => true,
            'notice'           => t('clean_plate_fallback_notice'),
            'latency_ms'       => $elapsed
        ];
    }

    $imageModel = defined('GEMINI_IMAGE_MODEL') ? trim(GEMINI_IMAGE_MODEL) : '';
    $apiKey = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
    $usedFallback = true;
    $method = 'inpainting_fallback';
    $notice = t('clean_plate_fallback_notice');

    if (!$forceFallback && !empty($imageModel) && !empty($apiKey)) {
        $raw = @file_get_contents($sourcePath);
        if ($raw) {
            $b64 = base64_encode($raw);
            $baseUrl = defined('GEMINI_API_BASE_URL') ? rtrim(GEMINI_API_BASE_URL, '/') : 'https://generativelanguage.googleapis.com/v1beta';
            $url = "{$baseUrl}/models/{$imageModel}:generateContent?key=" . urlencode($apiKey);
            $prompt = "Remove all text, letters, numbers, badges, and logos from this image. Fill in the background seamlessly and preserve every other element, subject, lighting, and texture at the exact same resolution and aspect ratio.";
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => $b64]]
                        ]
                    ]
                ]
            ];
            $timeout = defined('GEMINI_TIMEOUT') ? (int)GEMINI_TIMEOUT : 30;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200) {
                $j = json_decode((string)$res, true);
                $imgData = $j['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
                if ($imgData) {
                    file_put_contents($outputPath, base64_decode($imgData));
                    $usedFallback = false;
                    $method = 'gemini_image_model';
                    $notice = null;
                }
            } else {
                app_log("Gemini image edit unavailable (HTTP {$code}), engaging inpainting fallback.", 'INFO', 'ai.log');
            }
        }
    }

    if ($usedFallback) {
        $ok = ai_poster_inpaint_gd($sourcePath, $textBlocks, $outputPath);
        if (!$ok) {
            return [
                'success' => false,
                'error'   => 'Clean plate inpainting engine failed to process image.',
                'code'    => 'INPAINTING_FAILED'
            ];
        }
    }

    $elapsed = round((microtime(true) - $t0) * 1000);

    return [
        'success'          => true,
        'clean_plate_url'  => $cleanUrl,
        'clean_plate_path' => $outputPath,
        'method'           => $method,
        'fallback_used'    => $usedFallback,
        'notice'           => $notice,
        'latency_ms'       => $elapsed
    ];
}

/**
 * Art-direction pass for Phase 6 Mode 2 ("Text to Poster")
 */
function ai_art_direct_poster(string $prompt, array $options = []): array {
    $t0 = microtime(true);
    $prompt = trim($prompt);
    if (empty($prompt)) {
        return ['success' => false, 'error' => t('prompt_required'), 'code' => 'EMPTY_PROMPT'];
    }

    $targetW = (int)($options['width'] ?? 1080);
    $targetH = (int)($options['height'] ?? 1080);
    $stylePreset = (string)($options['style_preset'] ?? 'cyber_tech');

    $systemInstruction = "You are an elite art director and graphic designer creating modern bilingual promotional posters.
The user wants to generate a high-end poster for: \"{$prompt}\"
Style preset requested: {$stylePreset}

CRITICAL ANTI-HALLUCINATION RULES:
1. NEVER invent or fabricate URLs, website domains (e.g. www.something.com), phone numbers, email addresses, prices, discounts, or dates that the user did not explicitly mention in their request.
2. If the user did not specify a website, leave the website/contact field out entirely.
3. If the user did not specify a price or discount, do NOT invent prices (e.g. '$99' or '50% off').
4. Generate authentic Arabic/English copy: punchy headline, engaging subtitle, and relevant feature bullet points strictly derived from the user's intent.";

    $userPrompt = "Create a complete art direction plan for:
Topic: \"{$prompt}\"
Target Canvas: {$targetW}x{$targetH} px
Style Preset: {$stylePreset}";

    $schema = Prompts::getPosterArtDirectionSchema();

    $res = ProviderChain::dispatch($systemInstruction, $userPrompt, $schema, true);

    if (!$res['success'] || empty($res['content'])) {
        return [
            'success' => false,
            'code'    => $res['code'] ?? 'ART_DIRECTION_FAILED',
            'error'   => $res['error'] ?? 'Art direction failed.'
        ];
    }

    $data = extract_and_decode_json($res['content']);
    $art = $data['art_direction'] ?? null;

    if (!$art || empty($art['text_blocks'])) {
        return [
            'success' => false,
            'code'    => 'INVALID_ART_JSON',
            'error'   => 'Could not parse art direction response.'
        ];
    }

    // Strict Anti-Hallucination Verification
    $userHadUrl = (bool)preg_match('/(www\.[a-z0-9\-]+\.[a-z]{2,}|https?:\/\/[^\s]+|\.com|\.org|\.net)/i', $prompt);
    $userHadPrice = (bool)preg_match('/(\$\d+|\d+\$|\d+\s*(دولار|جنيه|ريال|usd))/i', $prompt);

    $filteredBlocks = [];
    foreach ($art['text_blocks'] as $block) {
        $txt = $block['text'] ?? '';
        if (!$userHadUrl && preg_match('/(www\.[a-z0-9\-]+\.[a-z]{2,}|https?:\/\/[^\s]+|\.com|\.org|\.net)/i', $txt)) {
            continue;
        }
        if (!$userHadPrice && preg_match('/(\$\d+|\d+\$|\d+\s*(دولار|جنيه|ريال|usd))/i', $txt)) {
            continue;
        }
        $filteredBlocks[] = $block;
    }
    $art['text_blocks'] = $filteredBlocks;

    $elapsed = round((microtime(true) - $t0) * 1000);

    return [
        'success'       => true,
        'art_direction' => $art,
        'latency_ms'    => $elapsed,
        'model'         => $res['model'] ?? null
    ];
}

/**
 * Generate text-free AI poster background image
 */
function ai_generate_poster_background(string $imagePrompt, int $width = 1080, int $height = 1080): array {
    $proxyUrl = PollinationsClient::buildProxyUrl($imagePrompt, $width, $height);
    $directUrl = PollinationsClient::buildImageUrl($imagePrompt, $width, $height);

    return [
        'success'   => true,
        'image_url' => $proxyUrl,
        'direct_url'=> $directUrl,
        'model'     => 'pollinations'
    ];
}
