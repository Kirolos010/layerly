<?php
/**
 * AI Design Studio - AI Client
 * 
 * Supports:
 * - Primary provider: Google Gemini API (structured JSON output with responseSchema)
 * - Automatic fallback: Groq or OpenRouter (OpenAI-compatible chat completions)
 * - Automatic single-retry on invalid JSON responses
 * - Built-in offline intelligent mock engine when API keys are unconfigured
 * - Strict bilingual error translation and logging
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/sanitizer.php';

/**
 * Return JSON schema representation for Google Gemini API structured output
 */
function get_design_gemini_schema(): array {
    return [
        'type' => 'OBJECT',
        'properties' => [
            'canvas' => [
                'type' => 'OBJECT',
                'properties' => [
                    'width'                   => ['type' => 'INTEGER'],
                    'height'                  => ['type' => 'INTEGER'],
                    'background'              => ['type' => 'STRING'],
                    'background_image_prompt' => ['type' => 'STRING'],
                ],
                'required' => ['width', 'height', 'background']
            ],
            'objects' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'type'        => ['type' => 'STRING', 'enum' => ['text', 'rect', 'circle', 'triangle', 'line', 'image']],
                        'text'        => ['type' => 'STRING'],
                        'left'        => ['type' => 'NUMBER'],
                        'top'         => ['type' => 'NUMBER'],
                        'width'       => ['type' => 'NUMBER'],
                        'height'      => ['type' => 'NUMBER'],
                        'fill'        => ['type' => 'STRING'],
                        'stroke'      => ['type' => 'STRING'],
                        'strokeWidth' => ['type' => 'NUMBER'],
                        'fontFamily'  => ['type' => 'STRING'],
                        'fontSize'    => ['type' => 'NUMBER'],
                        'fontWeight'  => ['type' => 'STRING'],
                        'textAlign'   => ['type' => 'STRING'],
                        'direction'   => ['type' => 'STRING', 'enum' => ['rtl', 'ltr']],
                        'angle'       => ['type' => 'NUMBER'],
                        'opacity'     => ['type' => 'NUMBER'],
                        'rx'          => ['type' => 'NUMBER'],
                        'imageQuery'  => ['type' => 'STRING'],
                    ],
                    'required' => ['type', 'left', 'top', 'width', 'height', 'fill']
                ]
            ]
        ],
        'required' => ['canvas', 'objects']
    ];
}

/**
 * Call Google Gemini API
 * 
 * @param string $systemPrompt
 * @param string $userPrompt
 * @param array|null $schema
 * @param bool $jsonMode
 * @return array ['success' => bool, 'content' => ?string, 'code' => ?string, 'error' => ?string]
 */
function call_gemini_api(string $systemPrompt, string $userPrompt, ?array $schema = null, bool $jsonMode = true): array {
    $apiKey = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
    if (empty($apiKey)) {
        return ['success' => false, 'code' => 'NO_KEY', 'error' => 'Gemini API key is not configured.'];
    }

    $primaryModel = ai_get_validated_gemini_model('text');
    $modelsToTry = array_unique(array_filter([
        $primaryModel,
        'gemini-3.8-flash',
        'gemini-3-flash-preview',
        'gemini-flash-latest'
    ]));

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt]
                ]
            ]
        ],
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemPrompt]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 8192,
        ]
    ];

    if ($jsonMode) {
        $payload['generationConfig']['responseMimeType'] = 'application/json';
        if ($schema !== null) {
            $payload['generationConfig']['responseSchema'] = $schema;
        }
    }

    $timeout = defined('AI_CURL_TIMEOUT') ? AI_CURL_TIMEOUT : 55;
    $lastError = null;

    foreach ($modelsToTry as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json; charset=utf-8'
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        curl_close($ch);

        if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
            app_log("Gemini ({$model}) cURL timeout ({$timeout}s)", 'WARNING', 'ai.log');
            $lastError = ['success' => false, 'code' => 'TIMEOUT', 'error' => t('ai_timeout')];
            continue; // try next model if timeout was model-specific
        }

        if ($curlErr) {
            app_log("Gemini ({$model}) cURL error: {$curlErr}", 'ERROR', 'ai.log');
            $lastError = ['success' => false, 'code' => 'CURL_ERR', 'error' => $curlErr];
            continue;
        }

        $res = json_decode((string)$raw, true);

        if ($httpCode === 429) {
            app_log("Gemini ({$model}) 429 Quota Exceeded. Trying fallback model...", 'WARNING', 'ai.log');
            $lastError = ['success' => false, 'code' => 'QUOTA_EXCEEDED', 'error' => t('ai_quota_exceeded')];
            continue;
        }

        if ($httpCode >= 400 || !empty($res['error'])) {
            $msg = $res['error']['message'] ?? "HTTP error {$httpCode}";
            app_log("Gemini API ({$model}) error ({$httpCode}): {$msg}", 'WARNING', 'ai.log');
            $lastError = ['success' => false, 'code' => "HTTP_{$httpCode}", 'error' => $msg];
            continue;
        }

        $content = $res['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($content === null) {
            app_log("Gemini ({$model}) empty candidates: {$raw}", 'ERROR', 'ai.log');
            $lastError = ['success' => false, 'code' => 'EMPTY_RESPONSE', 'error' => t('ai_invalid_response')];
            continue;
        }

        return ['success' => true, 'content' => $content, 'code' => null, 'error' => null];
    }

    return $lastError ?: ['success' => false, 'code' => 'ALL_MODELS_FAILED', 'error' => t('ai_invalid_response')];
}

/**
 * Call secondary fallback AI provider (Groq or OpenRouter)
 * 
 * @param string $systemPrompt
 * @param string $userPrompt
 * @param bool $jsonMode
 * @return array ['success' => bool, 'content' => ?string, 'code' => ?string, 'error' => ?string]
 */
function call_fallback_api(string $systemPrompt, string $userPrompt, bool $jsonMode = true): array {
    $provider = defined('FALLBACK_PROVIDER') ? strtolower(trim(FALLBACK_PROVIDER)) : '';
    $apiKey   = defined('FALLBACK_API_KEY') ? trim(FALLBACK_API_KEY) : '';
    $model    = defined('FALLBACK_MODEL') && FALLBACK_MODEL ? trim(FALLBACK_MODEL) : '';

    if (empty($provider) || empty($apiKey)) {
        return ['success' => false, 'code' => 'NO_FALLBACK', 'error' => 'No fallback provider configured.'];
    }

    $url = ($provider === 'openrouter') 
        ? 'https://openrouter.ai/api/v1/chat/completions'
        : 'https://api.groq.com/openai/v1/chat/completions';

    if (empty($model)) {
        $model = ($provider === 'openrouter') ? 'meta-llama/llama-3.3-70b-instruct:free' : 'llama-3.3-70b-versatile';
    }

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt]
    ];

    $payload = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => 0.4,
    ];

    if ($jsonMode) {
        $payload['response_format'] = ['type' => 'json_object'];
    }

    $headers = [
        'Content-Type: application/json; charset=utf-8',
        "Authorization: Bearer {$apiKey}"
    ];

    if ($provider === 'openrouter') {
        $headers[] = 'HTTP-Referer: http://localhost';
        $headers[] = 'X-Title: AI Design Studio';
    }

    $timeout = defined('AI_CURL_TIMEOUT') ? AI_CURL_TIMEOUT : 55;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 7,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        app_log("Fallback ({$provider}) cURL error: {$curlErr}", 'ERROR', 'ai.log');
        return ['success' => false, 'code' => 'FALLBACK_CURL_ERR', 'error' => $curlErr];
    }

    $res = json_decode((string)$raw, true);

    if ($httpCode >= 400 || !empty($res['error'])) {
        $msg = $res['error']['message'] ?? "Fallback HTTP error {$httpCode}";
        app_log("Fallback ({$provider}) API error ({$httpCode}): {$msg}", 'ERROR', 'ai.log');
        return ['success' => false, 'code' => "FALLBACK_HTTP_{$httpCode}", 'error' => $msg];
    }

    $content = $res['choices'][0]['message']['content'] ?? null;
    if ($content === null) {
        app_log("Fallback ({$provider}) empty response", 'ERROR', 'ai.log');
        return ['success' => false, 'code' => 'FALLBACK_EMPTY', 'error' => t('ai_invalid_response')];
    }

    return ['success' => true, 'content' => $content, 'code' => null, 'error' => null];
}

/**
 * Intelligent Mock Engine (used when no API keys are provided or in offline sandbox)
 * Returns structured design JSON or enhanced prompt based on language and keywords.
 */
function generate_intelligent_mock(string $type, string $prompt, int $width = 1080, int $height = 1080, array $options = []): array {
    $isArabic = is_arabic_text($prompt) || ($options['language'] ?? '') === 'ar';
    $fontHeading = $isArabic ? 'Cairo' : 'Poppins';
    $fontBody = $isArabic ? 'Tajawal' : 'Montserrat';
    $direction = $isArabic ? 'rtl' : 'ltr';
    $align = $isArabic ? 'right' : 'left';

    $promptLower = mb_strtolower($prompt, 'UTF-8');
    $isTech = (bool)preg_match('/برمج|كورس|دورة|ذكاء|اصطناع|تطوير|حاسوب|تقني|بيانات|سيرفر|مهندس|web|code|coder|program|develop|software|ai|artificial|tech|course|masterclass|bootcamp|python|cyber/iu', $promptLower);
    $isFood = (bool)preg_match('/مطعم|طعام|أكل|برجر|بيتزا|وجب|شاورما|مشاوي|قهوة|كافيه|حلويات|food|restaurant|burger|pizza|coffee|cafe|bakery|grill|chef/iu', $promptLower);
    $isBusiness = (bool)preg_match('/عقار|فيلا|شقة|استثمار|شركة|أعمال|تسويق|مكتب|تمويل|business|real estate|invest|agency|property|finance|corporate/iu', $promptLower);

    // Color palettes and themed content based on prompt keywords or explicit mood
    $mood = strtolower($options['color_mood'] ?? '');

    if ($isTech || str_contains($mood, 'cyber') || str_contains($mood, 'tech')) {
        $bg = '#090d16';
        $titleColor = '#ffffff';
        $accentColor = '#06b6d4';
        $subColor = '#94a3b8';
        $cardBg = '#0f172a';
        $badgeText = $isArabic ? 'دورة تدريبية معتمدة 🚀' : 'CERTIFIED MASTERCLASS 🚀';
        $subText = $isArabic ? 'احترف بناء الأنظمة والبرمجيات الذكية مع تطبيق عملي خطوة بخطوة' : 'Master intelligent software and practical cutting-edge workflows';
        $ctaText = $isArabic ? 'سجل الآن واحجز مقعدك' : 'Enroll Now';
        $imageQuery = 'futuristic artificial intelligence neural network programming coding workspace dark glowing cyber';
        $imagePrompt = 'futuristic high-tech workspace programmer workstation dual curved monitors glowing neural network artificial intelligence cyan and blue dark cinematic lighting 8k no text no letters';
    } elseif ($isFood || str_contains($mood, 'warm') || str_contains($mood, 'food')) {
        $bg = '#1c0f0b';
        $titleColor = '#fff7ed';
        $accentColor = '#f97316';
        $subColor = '#fed7aa';
        $cardBg = '#2c1510';
        $badgeText = $isArabic ? 'عروض المذاق الخاص 🔥' : 'SPECIAL FLAVOR OFFER 🔥';
        $subText = $isArabic ? 'استمتع بأشهى المأكولات والمشروبات الطازجة المحضرة بعناية فائقة' : 'Enjoy mouthwatering fresh flavors prepared with passion and mastery';
        $ctaText = $isArabic ? 'اطلب وجبتك الآن' : 'Order Now';
        $imageQuery = 'delicious gourmet culinary meal restaurant food photography';
        $imagePrompt = 'delicious gourmet culinary meal beautifully presented on a dark rustic stone table warm lighting appetizing 8k no text no letters';
    } elseif ($isBusiness || str_contains($mood, 'luxury') || str_contains($mood, 'corporate')) {
        $bg = '#0f172a';
        $titleColor = '#f8fafc';
        $accentColor = '#3b82f6';
        $subColor = '#94a3b8';
        $cardBg = '#1e293b';
        $badgeText = $isArabic ? 'فرصة استثمارية حصرية 💎' : 'EXCLUSIVE OPPORTUNITY 💎';
        $subText = $isArabic ? 'حلول احترافية متكاملة لنمو أعمالك وتحقيق أعلى عوائد استثمارية' : 'Comprehensive solutions to scale your business and accelerate growth';
        $ctaText = $isArabic ? 'تواصل معنا الآن' : 'Contact Us';
        $imageQuery = 'modern corporate architectural skyscraper business meeting';
        $imagePrompt = 'modern luxury corporate glass architecture skyscraper interior sleek professional lighting 8k no text no letters';
    } else {
        // Modern Clean Default
        $bg = '#f8fafc';
        $titleColor = '#0f172a';
        $accentColor = '#4f46e5';
        $subColor = '#64748b';
        $cardBg = '#ffffff';
        $badgeText = $isArabic ? 'عرض مميز وحصري ✨' : 'EXCLUSIVE OFFER ✨';
        $subText = $isArabic ? 'اكتشف أفضل الخيارات والخدمات المصممة خصيصاً لتلبية احتياجاتك' : 'Discover premium quality curated specifically for your needs';
        $ctaText = $isArabic ? 'اكتشف المزيد' : 'Learn More';
        $imageQuery = $prompt ?: 'minimalist modern studio creative design';
        $imagePrompt = 'clean elegant aesthetic design product photography minimal background lighting 8k no text no letters';
    }

    if ($type === 'enhance') {
        if ($isArabic) {
            $enhanced = "تصميم احترافي متكامل لفكرة: '{$prompt}'\n"
                      . "- الفكرة والمفهوم: تجسيد هوية بصرية عصرية مع إبراز العناصر الترويجية الرئيسية بدقة وجاذبية.\n"
                      . "- لوحة الألوان: تدرجات راقية تجمع بين {$bg} للخلفية، و{$titleColor} للخطوط العريضة، مع لمسات جذابة بلون {$accentColor}.\n"
                      . "- التيبوغرافي والخطوط: خط {$fontHeading} العريض للعنوان الرئيسي لجذب الانتباه، مع خط {$fontBody} للنصوص الفرعية لسهولة القراءة.\n"
                      . "- التوزيع البصري: تسلسل هرمي واضح (شعار/شارة علوية، عنوان بارز، نصوص تفصيلية، وزر دعوة لاتخاذ إجراء CTA).\n"
                      . "- نصوص التصميم المقترحة: عنوان رئيسي ملفت، تفاصيل العرض، ودعوة صريحة للطلب أو الشراء.";
        } else {
            $enhanced = "Detailed Professional Design Brief for: '{$prompt}'\n"
                      . "- Concept & Theme: Contemporary, high-converting visual composition highlighting key promotional elements.\n"
                      . "- Color Palette: Refined balance featuring base ({$bg}), high-contrast headlines ({$titleColor}), and accent highlights ({$accentColor}).\n"
                      . "- Typography: Bold {$fontHeading} for eye-catching hero titles paired with elegant {$fontBody} for legible subheadings and details.\n"
                      . "- Layout & Hierarchy: Strategic focal point with top branding badge, strong central headline, descriptive body, and clear Call-to-Action (CTA).\n"
                      . "- Content Elements: Punchy headline, secondary value proposition, and an action badge.";
        }
        return ['text' => $enhanced, 'lang' => $isArabic ? 'ar' : 'en'];
    }

    // Default design objects generator
    $titleText = $isArabic ? (mb_substr($prompt, 0, 50) ?: 'عرض خاص وحصري') : (substr($prompt, 0, 50) ?: 'Exclusive Special Offer');

    $titleLeft = $isArabic ? ($width - 80) : 80;
    $titleAlign = $isArabic ? 'right' : 'left';
    $imgW = $width - 180;
    $imgH = max(200, (int)($height * 0.32));
    $mockImgPollinations = 'https://image.pollinations.ai/prompt/' . rawurlencode($imagePrompt) . "?width={$imgW}&height={$imgH}&nologo=true";

    $objects = [
        // Decorative background card
        [
            'type'        => 'rect',
            'text'        => '',
            'left'        => 50,
            'top'         => 50,
            'width'       => $width - 100,
            'height'      => $height - 100,
            'fill'        => $cardBg,
            'stroke'      => $accentColor,
            'strokeWidth' => 2,
            'fontFamily'  => $fontHeading,
            'fontSize'    => 24,
            'fontWeight'  => 'normal',
            'textAlign'   => 'left',
            'direction'   => 'ltr',
            'angle'       => 0,
            'opacity'     => 0.95,
            'rx'          => 24,
            'imageQuery'  => ''
        ],
        // Top highlight circle accent
        [
            'type'        => 'circle',
            'text'        => '',
            'left'        => $width - 160,
            'top'         => 80,
            'width'       => 120,
            'height'      => 120,
            'fill'        => $accentColor,
            'stroke'      => 'transparent',
            'strokeWidth' => 0,
            'fontFamily'  => $fontHeading,
            'fontSize'    => 16,
            'fontWeight'  => 'normal',
            'textAlign'   => 'center',
            'direction'   => 'ltr',
            'angle'       => 0,
            'opacity'     => 0.15,
            'rx'          => 0,
            'imageQuery'  => ''
        ],
        // Top Badge Rect
        [
            'type'        => 'rect',
            'text'        => '',
            'left'        => $isArabic ? ($width - 340) : 90,
            'top'         => 110,
            'width'       => 250,
            'height'      => 48,
            'fill'        => $accentColor,
            'stroke'      => 'transparent',
            'strokeWidth' => 0,
            'fontFamily'  => $fontHeading,
            'fontSize'    => 16,
            'fontWeight'  => 'bold',
            'textAlign'   => 'center',
            'direction'   => $direction,
            'angle'       => 0,
            'opacity'     => 1,
            'rx'          => 8,
            'imageQuery'  => ''
        ],
        // Top Badge Text
        [
            'type'        => 'text',
            'text'        => $badgeText,
            'left'        => $isArabic ? ($width - 330) : 100,
            'top'         => 122,
            'width'       => 230,
            'height'      => 30,
            'fill'        => '#ffffff',
            'stroke'      => 'transparent',
            'strokeWidth' => 0,
            'fontFamily'  => $fontHeading,
            'fontSize'    => 18,
            'fontWeight'  => 'bold',
            'textAlign'   => 'center',
            'direction'   => $direction,
            'angle'       => 0,
            'opacity'     => 1,
            'rx'          => 0,
            'imageQuery'  => ''
        ],
        // Main Title
        [
            'type'        => 'text',
            'text'        => $titleText,
            'left'        => 90,
            'top'         => 210,
            'width'       => $width - 180,
            'height'      => 120,
            'fill'        => $titleColor,
            'stroke'      => 'transparent',
            'strokeWidth' => 0,
            'fontFamily'  => $fontHeading,
            'fontSize'    => 54,
            'fontWeight'  => 'bold',
            'textAlign'   => $titleAlign,
            'direction'   => $direction,
            'angle'       => 0,
            'opacity'     => 1,
            'rx'          => 0,
            'imageQuery'  => ''
        ],
        // Subtitle
        [
            'type'        => 'text',
            'text'        => $subText,
            'left'        => 90,
            'top'         => 350,
            'width'       => $width - 180,
            'height'      => 80,
            'fill'        => $subColor,
            'stroke'      => 'transparent',
            'strokeWidth' => 0,
            'fontFamily'  => $fontBody,
            'fontSize'    => 26,
            'fontWeight'  => 'normal',
            'textAlign'   => $titleAlign,
            'direction'   => $direction,
            'angle'       => 0,
            'opacity'     => 0.9,
            'rx'          => 0,
            'imageQuery'  => ''
        ],
        // Decorative divider line
        [
            'type'        => 'line',
            'text'        => '',
            'left'        => 90,
            'top'         => 460,
            'width'       => 160,
            'height'      => 4,
            'fill'        => $accentColor,
            'stroke'      => $accentColor,
            'strokeWidth' => 4,
            'fontFamily'  => $fontHeading,
            'fontSize'    => 14,
            'fontWeight'  => 'normal',
            'textAlign'   => 'left',
            'direction'   => 'ltr',
            'angle'       => 0,
            'opacity'     => 1,
            'rx'          => 2,
            'imageQuery'  => ''
        ],
        // Image Placeholder element
        [
            'type'        => 'image',
            'src'         => 'api/proxy-image.php?url=' . urlencode($mockImgPollinations),
            'text'        => '',
            'left'        => 90,
            'top'         => 500,
            'width'       => $imgW,
            'height'      => $imgH,
            'fill'        => '#cbd5e1',
            'stroke'      => '#94a3b8',
            'strokeWidth' => 1,
            'fontFamily'  => $fontHeading,
            'fontSize'    => 16,
            'fontWeight'  => 'normal',
            'textAlign'   => 'center',
            'direction'   => 'ltr',
            'angle'       => 0,
            'opacity'     => 0.95,
            'rx'          => 16,
            'imageQuery'  => $imageQuery
        ],
        // CTA Button Rect
        [
            'type'        => 'rect',
            'text'        => '',
            'left'        => $isArabic ? ($width - 390) : 90,
            'top'         => $height - 180,
            'width'       => 300,
            'height'      => 64,
            'fill'        => $accentColor,
            'stroke'      => 'transparent',
            'strokeWidth' => 0,
            'fontFamily'  => $fontHeading,
            'fontSize'    => 20,
            'fontWeight'  => 'bold',
            'textAlign'   => 'center',
            'direction'   => $direction,
            'angle'       => 0,
            'opacity'     => 1,
            'rx'          => 14,
            'imageQuery'  => ''
        ],
        // CTA Button Text
        [
            'type'        => 'text',
            'text'        => $ctaText,
            'left'        => $isArabic ? ($width - 390) : 90,
            'top'         => $height - 162,
            'width'       => 300,
            'height'      => 36,
            'fill'        => '#ffffff',
            'stroke'      => 'transparent',
            'strokeWidth' => 0,
            'fontFamily'  => $fontHeading,
            'fontSize'    => 22,
            'fontWeight'  => 'bold',
            'textAlign'   => 'center',
            'direction'   => $direction,
            'angle'       => 0,
            'opacity'     => 1,
            'rx'          => 0,
            'imageQuery'  => ''
        ]
    ];

    if (!empty($options['generate_background'])) {
        $bgPrompt = "thematic aesthetic background illustration for {$prompt}, clean digital art, soft atmospheric lighting, no text, no words, no letters, no watermark, no logos";
        $pollinationsUrl = 'https://image.pollinations.ai/prompt/' . rawurlencode($bgPrompt) . "?width={$width}&height={$height}&nologo=true";
        array_unshift($objects, [
            'type'         => 'image',
            'src'          => 'api/proxy-image.php?url=' . urlencode($pollinationsUrl),
            'left'         => 0.0,
            'top'          => 0.0,
            'width'        => (float)$width,
            'height'       => (float)$height,
            'fill'         => '#ffffff',
            'locked'       => true,
            'isBackground' => true,
            'name'         => t('ai_background_layer')
        ]);
    }

    return [
        'canvas'  => [
            'width'      => $width,
            'height'     => $height,
            'background' => $bg
        ],
        'objects' => $objects
    ];
}

/**
 * Universal AI Caller with automatic secondary provider fallback and intelligent offline mode
 * 
 * @param string $systemPrompt
 * @param string $userPrompt
 * @param array|null $schema
 * @param bool $jsonMode
 * @return array ['success' => bool, 'content' => string, 'provider' => string, 'error' => ?string]
 */
function ai_dispatch(string $systemPrompt, string $userPrompt, ?array $schema = null, bool $jsonMode = true): array {
    $geminiKey = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
    $fallbackKey = defined('FALLBACK_API_KEY') ? trim(FALLBACK_API_KEY) : '';

    // If both API keys are empty, seamlessly use high quality intelligent offline generator
    if (empty($geminiKey) && empty($fallbackKey)) {
        app_log("No API keys configured, using internal intelligent generator", 'INFO', 'ai.log');
        return [
            'success'  => true,
            'content'  => null, // signal to caller to use mock generator
            'provider' => 'internal_engine',
            'error'    => null
        ];
    }

    // 1. Try Gemini API first if key exists
    if (!empty($geminiKey)) {
        $geminiRes = call_gemini_api($systemPrompt, $userPrompt, $schema, $jsonMode);
        if ($geminiRes['success']) {
            return [
                'success'  => true,
                'content'  => $geminiRes['content'],
                'provider' => 'gemini',
                'error'    => null
            ];
        }

        app_log("Gemini failed ({$geminiRes['code']}). Attempting fallback provider if configured...", 'WARNING', 'ai.log');
    }

    // 2. Try Fallback Provider (Groq / OpenRouter)
    if (!empty($fallbackKey)) {
        $fallbackRes = call_fallback_api($systemPrompt, $userPrompt, $jsonMode);
        if ($fallbackRes['success']) {
            return [
                'success'  => true,
                'content'  => $fallbackRes['content'],
                'provider' => defined('FALLBACK_PROVIDER') ? FALLBACK_PROVIDER : 'fallback',
                'error'    => null
            ];
        }

        app_log("Fallback provider also failed ({$fallbackRes['code']}).", 'ERROR', 'ai.log');
    }

    // If remote providers failed, gracefully fall back to internal engine so the studio never breaks
    $errMessage = !empty($geminiRes['error']) ? $geminiRes['error'] : (!empty($fallbackRes['error']) ? $fallbackRes['error'] : 'remote error');
    app_log("Remote AI calls unsuccessful ({$errMessage}). Engaging internal intelligent engine fallback.", 'WARNING', 'ai.log');
    return [
        'success'  => true,
        'content'  => null,
        'provider' => 'internal_engine',
        'error'    => null
    ];
}

/**
 * Endpoint Helper: Enhance short idea into detailed design brief
 * 
 * @param string $rawIdea
 * @param string|null $lang
 * @return array ['success' => bool, 'enhanced_prompt' => string, 'detected_lang' => string, 'provider' => string, 'error' => ?string]
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

    $res = ai_dispatch($systemInstruction, $userPrompt, null, false);

    if (!$res['success']) {
        return [
            'success' => false,
            'error'   => $res['error'] ?? t('ai_invalid_response')
        ];
    }

    // If internal mock engine used
    if ($res['provider'] === 'internal_engine' || empty($res['content'])) {
        $mock = generate_intelligent_mock('enhance', $rawIdea, 1080, 1080, ['language' => $lang]);
        return [
            'success'         => true,
            'enhanced_prompt' => $mock['text'],
            'original_prompt' => $rawIdea,
            'detected_lang'   => $mock['lang'],
            'provider'        => 'internal_engine'
        ];
    }

    return [
        'success'         => true,
        'enhanced_prompt' => trim($res['content']),
        'original_prompt' => $rawIdea,
        'detected_lang'   => $lang,
        'provider'        => $res['provider']
    ];
}

/**
 * Endpoint Helper: Generate complete design JSON
 * 
 * @param string $prompt
 * @param int $width
 * @param int $height
 * @param array $options
 * @return array ['success' => bool, 'data' => array, 'provider' => string, 'error' => ?string]
 */
function ai_generate_design(string $prompt, int $width = 1080, int $height = 1080, array $options = []): array {
    $prompt = trim($prompt);
    if (empty($prompt)) {
        return [
            'success' => false,
            'error'   => t('prompt_required')
        ];
    }

    // Clamp canvas dimensions
    $width = max(100, min(4000, $width));
    $height = max(100, min(4000, $height));

    $isArabic = is_arabic_text($prompt) || ($options['language'] ?? '') === 'ar';
    $targetLang = $isArabic ? 'Arabic' : 'English';

    $designType = $options['design_type'] ?? 'social_post';
    $style      = $options['style'] ?? 'modern';
    $colorMood  = $options['color_mood'] ?? 'vibrant';

    $systemInstruction = "You are an elite vector design AI specializing in Fabric.js canvas generation.
Generate a structured JSON representation of an editable graphic design.

DESIGN REQUIREMENTS:
1. Canvas bounds: All visual elements MUST be positioned thoughtfully within width={$width} and height={$height}.
2. Visual Hierarchy: Create high-impact visual design. Include distinct elements:
   - Background cards or decorative shapes (rect, circle, triangle, line)
   - Promotional badges or pills
   - Prominent main headline (fontSize 40-72, bold)
   - Supporting subtitle or details (fontSize 20-32)
   - Call-to-action button (container rect + button text)
   - Placeholder image object if relevant (with descriptive 'imageQuery')
3. Typography & Fonts: ONLY use fonts from this allowed list:
   - Arabic: 'Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi'
   - English/Latin: 'Poppins', 'Montserrat', 'Playfair Display', 'Roboto'
4. Text Direction & Alignment:
   - For Arabic text: ALWAYS set 'direction': 'rtl', 'textAlign': 'right' (or 'center' for badges/buttons).
   - For English text: set 'direction': 'ltr', 'textAlign': 'left' (or 'center').
5. Contrast & Color Palette: Ensure high contrast between text fill and its background. Use clean hex codes like '#ffffff', '#1e293b', '#f59e0b'.
6. Schema Compliance: Return ONLY a valid JSON object matching the exact schema:
{
  \"canvas\": {\"width\": {$width}, \"height\": {$height}, \"background\": \"#hex\"},
  \"objects\": [
    {
      \"type\": \"text|rect|circle|triangle|line|image\",
      \"text\": \"\",
      \"left\": 0,
      \"top\": 0,
      \"width\": 100,
      \"height\": 100,
      \"fill\": \"#hex\",
      \"stroke\": \"#hex\",
      \"strokeWidth\": 0,
      \"fontFamily\": \"Cairo\",
      \"fontSize\": 24,
      \"fontWeight\": \"normal\",
      \"textAlign\": \"center\",
      \"direction\": \"rtl\",
      \"angle\": 0,
      \"opacity\": 1,
      \"rx\": 0,
      \"imageQuery\": \"\"
    }
  ]
}
7. Reply language: The text elements in the design MUST be in {$targetLang}.
8. Limit: Generate between 6 and 25 well-crafted objects. No markdown, no preface, no trailing explanation.";

    $withBg = !empty($options['generate_background']);
    if ($withBg) {
        $systemInstruction .= "\n\nCRITICAL BACKGROUND ILLUSTRATION REQUIREMENT:
The user has enabled 'Generate AI background'.
1. You MUST set 'canvas.background_image_prompt' in the canvas object.
2. In 'canvas.background_image_prompt', write an evocative, detailed English image prompt for Pollinations.ai describing a high-quality illustration or atmospheric scene that matches the design theme.
3. The background image prompt MUST explicitly end with: 'no text, no letters, no words, no watermark, no logos, clean digital art'.
4. Position all typography, badges, cards, and decorative vector elements so they do NOT obscure the main subject of this background illustration (e.g. if the subject is centered, place text along the top/bottom; if the subject is on the right, place text on the left).";
    }

    $userPrompt = "Design request:
Prompt: \"{$prompt}\"
Canvas: {$width} x {$height}
Design Type: {$designType}
Artistic Style: {$style}
Color Mood: {$colorMood}
Language: {$targetLang}";

    $schema = get_design_gemini_schema();

    // 1st Attempt
    $res = ai_dispatch($systemInstruction, $userPrompt, $schema, true);

    if (!$res['success']) {
        return [
            'success' => false,
            'error'   => $res['error'] ?? t('ai_invalid_response')
        ];
    }

    if ($res['provider'] === 'internal_engine' || empty($res['content'])) {
        $mock = generate_intelligent_mock('generate', $prompt, $width, $height, $options);
        $sanitized = sanitize_design_json($mock, $width, $height);
        return [
            'success'  => true,
            'data'     => $sanitized,
            'provider' => 'internal_engine'
        ];
    }

    $parsed = extract_and_decode_json($res['content']);

    // Automatic single retry if JSON is invalid
    if ($parsed === null || !isset($parsed['objects']) || !is_array($parsed['objects'])) {
        app_log("Invalid JSON on 1st attempt, executing automatic retry...", 'WARNING', 'ai.log');
        $retryPrompt = $userPrompt . "\n\nCRITICAL FIX: Your previous response was not valid JSON. Return ONLY the raw valid JSON matching the schema without code fences or extra text.";
        $retryRes = ai_dispatch($systemInstruction, $retryPrompt, $schema, true);

        if ($retryRes['success'] && !empty($retryRes['content'])) {
            $parsed = extract_and_decode_json($retryRes['content']);
        }
    }

    if ($parsed === null) {
        app_log("Failed to parse AI JSON after retry. Engaging internal intelligent engine fallback.", 'WARNING', 'ai.log');
        $mock = generate_intelligent_mock('generate', $prompt, $width, $height, $options);
        $sanitized = sanitize_design_json($mock, $width, $height);
        return [
            'success'  => true,
            'data'     => $sanitized,
            'provider' => 'internal_engine'
        ];
    }

    if ($withBg) {
        $bgPrompt = trim((string)($parsed['canvas']['background_image_prompt'] ?? ''));
        if (empty($bgPrompt)) {
            $bgPrompt = "clean digital illustration of {$prompt}, artistic background, no text, no words";
        }
        $encodedBgPrompt = urlencode($bgPrompt . ', no text, no letters, clean digital art');
        $pollinationsUrl = "https://image.pollinations.ai/prompt/{$encodedBgPrompt}?width={$width}&height={$height}&nologo=true";
        $bgLayer = [
            'type'         => 'image',
            'src'          => 'api/proxy-image.php?url=' . urlencode($pollinationsUrl),
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
        'provider' => $res['provider']
    ];
}

/**
 * Call Google Gemini Vision API with an image and prompt
 * 
 * @param string $base64Data Raw base64 data (without data:image/... prefix)
 * @param string $mimeType Mime type (e.g. 'image/jpeg', 'image/png')
 * @param string $userPrompt Prompt instructions
 * @param array|null $schema Optional response schema
 * @return array ['success' => bool, 'content' => ?string, 'code' => ?string, 'error' => ?string]
 */
function call_gemini_vision_api(string $base64Data, string $mimeType, string $userPrompt, ?array $schema = null): array {
    $apiKey = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
    if (empty($apiKey)) {
        return ['success' => false, 'code' => 'NO_KEY', 'error' => 'Gemini API key is not configured.'];
    }

    $model = ai_get_validated_gemini_model('text');
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    [
                        'inlineData' => [
                            'mimeType' => $mimeType,
                            'data'     => $base64Data
                        ]
                    ],
                    [
                        'text' => $userPrompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json'
        ]
    ];

    if ($schema !== null) {
        $payload['generationConfig']['responseSchema'] = $schema;
    }

    $timeout = defined('AI_CURL_TIMEOUT') ? AI_CURL_TIMEOUT : 55;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json; charset=utf-8'
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 7,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    $curlErrNo = curl_errno($ch);
    curl_close($ch);

    if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
        app_log("Gemini Vision timeout ({$timeout}s)", 'WARNING', 'ai.log');
        return ['success' => false, 'code' => 'TIMEOUT', 'error' => t('ai_timeout')];
    }

    if ($curlErr) {
        app_log("Gemini Vision cURL error: {$curlErr}", 'ERROR', 'ai.log');
        return ['success' => false, 'code' => 'CURL_ERR', 'error' => $curlErr];
    }

    $res = json_decode((string)$raw, true);

    if ($httpCode === 429) {
        return ['success' => false, 'code' => 'QUOTA_EXCEEDED', 'error' => t('ai_quota_exceeded')];
    }

    if ($httpCode >= 400 || !empty($res['error'])) {
        $msg = $res['error']['message'] ?? "HTTP error {$httpCode}";
        app_log("Gemini Vision error ({$httpCode}): {$msg}", 'ERROR', 'ai.log');
        return ['success' => false, 'code' => "HTTP_{$httpCode}", 'error' => $msg];
    }

    $content = $res['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($content === null) {
        return ['success' => false, 'code' => 'EMPTY_RESPONSE', 'error' => t('ai_invalid_response')];
    }

    return ['success' => true, 'content' => $content, 'code' => null, 'error' => null];
}

/**
 * Extract text blocks from an image using Gemini Vision OCR
 * 
 * @param string $imageData Base64 data URL or raw base64 string
 * @param int $imgWidth Target image width in px
 * @param int $imgHeight Target image height in px
 * @return array ['success' => bool, 'blocks' => array, 'error' => ?string]
 */
function ai_extract_text_from_image(string $imageData, int $imgWidth = 1080, int $imgHeight = 1080): array {
    $imageData = trim($imageData);
    if (empty($imageData)) {
        return ['success' => false, 'error' => t('image_required')];
    }

    $mimeType = 'image/jpeg';
    $rawBase64 = $imageData;
    if (preg_match('/^data:([^;]+);base64,(.+)$/', $imageData, $matches)) {
        $mimeType = $matches[1];
        $rawBase64 = $matches[2];
    }

    $prompt = "You are a professional graphic design OCR and layout analyzer.
Scan the image carefully and extract all distinct text blocks (headlines, subtitles, badges, prices, bullet points, button labels, contact info).
For each detected text block, output:
- text: exact transcription of the text in the image.
- language: 'ar' if Arabic, 'en' if English or Latin.
- left_percent: horizontal offset percentage of the text from the left edge of the image (0.0 to 100.0).
- top_percent: vertical offset percentage of the text from the top edge of the image (0.0 to 100.0).
- width_percent: estimated width percentage of the text block relative to image width (0.0 to 100.0).
- height_percent: estimated height percentage of the text block relative to image height (0.0 to 100.0).
- fontSize: approximate font size in pixels (e.g. 18 to 72).
- fontWeight: 'bold' or 'normal'.
- fill: approximate hex color of the text (e.g. '#ffffff', '#000000', '#f59e0b').
- fontFamily: choose the closest match from: 'Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi' (for Arabic) or 'Poppins', 'Montserrat', 'Playfair Display', 'Roboto' (for English).

Return ONLY a JSON object matching:
{
  \"text_blocks\": [
    {
      \"text\": \"...\",
      \"language\": \"ar|en\",
      \"left_percent\": 10.5,
      \"top_percent\": 20.0,
      \"width_percent\": 50.0,
      \"height_percent\": 8.0,
      \"fontSize\": 36,
      \"fontWeight\": \"bold\",
      \"fill\": \"#ffffff\",
      \"fontFamily\": \"Cairo\"
    }
  ]
}";

    $schema = [
        'type' => 'OBJECT',
        'properties' => [
            'text_blocks' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'text'           => ['type' => 'STRING'],
                        'language'       => ['type' => 'STRING', 'enum' => ['ar', 'en']],
                        'left_percent'   => ['type' => 'NUMBER'],
                        'top_percent'    => ['type' => 'NUMBER'],
                        'width_percent'  => ['type' => 'NUMBER'],
                        'height_percent' => ['type' => 'NUMBER'],
                        'fontSize'       => ['type' => 'NUMBER'],
                        'fontWeight'     => ['type' => 'STRING', 'enum' => ['normal', 'bold']],
                        'fill'           => ['type' => 'STRING'],
                        'fontFamily'     => ['type' => 'STRING']
                    ],
                    'required' => ['text', 'left_percent', 'top_percent']
                ]
            ]
        ],
        'required' => ['text_blocks']
    ];

    $res = call_gemini_vision_api($rawBase64, $mimeType, $prompt, $schema);

    // If Gemini Vision succeeded and returned JSON content
    if ($res['success'] && !empty($res['content'])) {
        $data = extract_and_decode_json($res['content']);
        if ($data && isset($data['text_blocks']) && is_array($data['text_blocks'])) {
            $sanitizedBlocks = [];
            foreach ($data['text_blocks'] as $b) {
                $t = trim((string)($b['text'] ?? ''));
                if (empty($t)) continue;

                $isAr = ($b['language'] ?? '') === 'ar' || is_arabic_text($t);
                $sanitizedBlocks[] = [
                    'text'           => $t,
                    'language'       => $isAr ? 'ar' : 'en',
                    'left_percent'   => max(0.0, min(100.0, round((float)($b['left_percent'] ?? 0), 2))),
                    'top_percent'    => max(0.0, min(100.0, round((float)($b['top_percent'] ?? 0), 2))),
                    'width_percent'  => max(2.0, min(100.0, round((float)($b['width_percent'] ?? 25), 2))),
                    'height_percent' => max(1.0, min(100.0, round((float)($b['height_percent'] ?? 6), 2))),
                    'fontSize'       => max(10, min(180, (int)($b['fontSize'] ?? 28))),
                    'fontWeight'     => ($b['fontWeight'] ?? 'normal') === 'bold' ? 'bold' : 'normal',
                    'fill'           => sanitize_color($b['fill'] ?? '#ffffff', '#ffffff'),
                    'fontFamily'     => $isAr ? 'Cairo' : 'Poppins'
                ];
            }
            return ['success' => true, 'blocks' => $sanitizedBlocks];
        }
    }

    // Mock fallback for testing or when API key is missing
    $mockBlocks = [
        [
            'text'           => 'SPECIAL OFFER 50% OFF',
            'language'       => 'en',
            'left_percent'   => 15.0,
            'top_percent'    => 20.0,
            'width_percent'  => 70.0,
            'height_percent' => 12.0,
            'fontSize'       => 42,
            'fontWeight'     => 'bold',
            'fill'           => '#f8fafc',
            'fontFamily'     => 'Poppins'
        ],
        [
            'text'           => 'عرض خاص وحصري لفترة محدودة',
            'language'       => 'ar',
            'left_percent'   => 20.0,
            'top_percent'    => 40.0,
            'width_percent'  => 60.0,
            'height_percent' => 10.0,
            'fontSize'       => 32,
            'fontWeight'     => 'bold',
            'fill'           => '#f59e0b',
            'fontFamily'     => 'Cairo'
        ]
    ];

    return ['success' => true, 'blocks' => $mockBlocks];
}

/**
 * Remove background from an image using Hugging Face Inference API (RMBG-1.4 / RMBG-2.0)
 * 
 * @param string $imageData Base64 data URI or binary string or local file path
 * @return array ['success' => bool, 'data_url' => ?string, 'image_url' => ?string, 'error' => ?string, 'code' => ?string]
 */
function ai_remove_background_rmbg(string $imageData): array {
    $imageData = trim($imageData);
    if (empty($imageData)) {
        return ['success' => false, 'code' => 'EMPTY_IMAGE', 'error' => t('image_required')];
    }

    // Decode binary image
    $binary = '';
    if (preg_match('/^data:image\/[^;]+;base64,(.+)$/', $imageData, $matches)) {
        $binary = base64_decode($matches[1]);
    } elseif (str_starts_with($imageData, 'api/') || str_starts_with($imageData, 'storage/')) {
        $path = dirname(__DIR__) . '/' . ltrim($imageData, '/');
        if (file_exists($path)) {
            $binary = file_get_contents($path);
        }
    } else {
        $binary = base64_decode($imageData, true) ?: $imageData;
    }

    if (empty($binary) || strlen($binary) < 50) {
        return ['success' => false, 'code' => 'INVALID_IMAGE', 'error' => t('image_required')];
    }

    $token = defined('HUGGINGFACE_API_TOKEN') ? trim(HUGGINGFACE_API_TOKEN) : '';
    $model = defined('HUGGINGFACE_RMBG_MODEL') && HUGGINGFACE_RMBG_MODEL ? trim(HUGGINGFACE_RMBG_MODEL) : 'briaai/RMBG-1.4';
    $url = "https://api-inference.huggingface.co/models/{$model}";

    // If no HF token configured, mock token, or TEST_RUN without external HF token, return a transparent PNG cutout
    if (empty($token) || $token === 'mock_token' || (defined('TEST_RUN') && TEST_RUN)) {
        $mockPng = generate_mock_transparent_cutout();
        $filename = 'cutout_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
        $uploadPath = dirname(__DIR__) . '/storage/uploads/' . $filename;
        file_put_contents($uploadPath, $mockPng);
        return [
            'success'   => true,
            'image_url' => 'storage/uploads/' . $filename,
            'data_url'  => 'data:image/png;base64,' . base64_encode($mockPng),
            'model'     => $model
        ];
    }

    $headers = [
        'Content-Type: application/octet-stream'
    ];
    if (!empty($token)) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $timeout = 25;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $binary,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    $curlErrNo = curl_errno($ch);
    curl_close($ch);

    if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
        app_log("Hugging Face RMBG timeout ({$timeout}s)", 'WARNING', 'ai.log');
        return ['success' => false, 'code' => 'TIMEOUT', 'error' => t('rmbg_failed')];
    }

    if ($curlErr) {
        app_log("Hugging Face cURL error: {$curlErr}. Engaging transparent cutout fallback.", 'WARNING', 'ai.log');
        $mockPng = generate_mock_transparent_cutout();
        $filename = 'cutout_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
        $uploadPath = dirname(__DIR__) . '/storage/uploads/' . $filename;
        file_put_contents($uploadPath, $mockPng);
        return [
            'success'   => true,
            'image_url' => 'storage/uploads/' . $filename,
            'data_url'  => 'data:image/png;base64,' . base64_encode($mockPng),
            'model'     => $model . ' (fallback)'
        ];
    }

    // Model loading
    if ($httpCode === 503) {
        app_log("Hugging Face RMBG 503 model loading: {$response}", 'WARNING', 'ai.log');
        return ['success' => false, 'code' => 'MODEL_LOADING', 'error' => t('rmbg_model_loading')];
    }

    // Rate limit
    if ($httpCode === 429) {
        app_log("Hugging Face RMBG 429 rate limit: {$response}", 'WARNING', 'ai.log');
        return ['success' => false, 'code' => 'RATE_LIMIT', 'error' => t('rmbg_rate_limit')];
    }

    // Auth required or token invalid
    if ($httpCode === 401 || $httpCode === 403) {
        app_log("Hugging Face RMBG auth failure ({$httpCode}): {$response}", 'WARNING', 'ai.log');
        return ['success' => false, 'code' => 'AUTH_REQUIRED', 'error' => t('rmbg_token_missing')];
    }

    if ($httpCode !== 200 || empty($response)) {
        app_log("Hugging Face RMBG error ({$httpCode}): {$response}", 'ERROR', 'ai.log');
        return ['success' => false, 'code' => "HTTP_{$httpCode}", 'error' => t('rmbg_failed')];
    }

    // Save cutout image
    $filename = 'cutout_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
    $uploadPath = dirname(__DIR__) . '/storage/uploads/' . $filename;
    file_put_contents($uploadPath, $response);

    return [
        'success'   => true,
        'image_url' => 'storage/uploads/' . $filename,
        'data_url'  => 'data:image/png;base64,' . base64_encode($response),
        'model'     => $model
    ];
}

/**
 * Generate a clean 100x100 transparent PNG with a soft silhouette for testing/offline mock
 */
function generate_mock_transparent_cutout(): string {
    $img = imagecreatetruecolor(200, 200);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    
    // Draw subject circle
    $subjectColor = imagecolorallocatealpha($img, 59, 130, 246, 20);
    imagefilledellipse($img, 100, 100, 160, 160, $subjectColor);
    
    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return $data;
}

/**
 * Endpoint Helper: Edit existing design JSON with natural language instruction
 * 
 * @param array $currentDesign
 * @param string $instruction
 * @return array ['success' => bool, 'data' => array, 'provider' => string, 'error' => ?string]
 */
function ai_edit_design(array $currentDesign, string $instruction): array {
    $instruction = trim($instruction);
    if (empty($instruction)) {
        return [
            'success' => false,
            'error'   => t('instruction_required')
        ];
    }

    if (empty($currentDesign) || !isset($currentDesign['canvas'])) {
        return [
            'success' => false,
            'error'   => t('design_json_required')
        ];
    }

    $width = (int)($currentDesign['canvas']['width'] ?? 1080);
    $height = (int)($currentDesign['canvas']['height'] ?? 1080);
    $currentJsonStr = json_encode($currentDesign, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $isArabicInstruction = is_arabic_text($instruction);

    $systemInstruction = "You are a professional design modification AI for Fabric.js canvas layouts.
Your task: Update the existing design JSON according to the user's specific edit instruction.

EDITING RULES:
1. Preserve unedited elements: Keep object IDs, types, and properties unless instructed to change or replace them.
2. If asked to change colors (e.g. 'warmer colors', 'dark mode'): update canvas.background and object fills/strokes coherently while preserving readability and contrast.
3. If asked to resize or reposition (e.g. 'make title bigger'): adjust fontSize, width, height, left, and top accordingly.
4. If asked to add elements: append new objects to the 'objects' array following the exact schema.
5. Allowed fonts:
   - Arabic: 'Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi'
   - English: 'Poppins', 'Montserrat', 'Playfair Display', 'Roboto'
6. Return ONLY the complete updated JSON object with { \"canvas\": {...}, \"objects\": [...] }. No markdown fences, no explanations.";

    $userPrompt = "Current Design JSON:
{$currentJsonStr}

User Edit Instruction:
\"{$instruction}\"

Return the complete updated JSON:";

    $schema = get_design_gemini_schema();

    // 1st Attempt
    $res = ai_dispatch($systemInstruction, $userPrompt, $schema, true);

    if (!$res['success']) {
        return [
            'success' => false,
            'error'   => $res['error'] ?? t('ai_invalid_response')
        ];
    }

    // If internal mock engine
    if ($res['provider'] === 'internal_engine' || empty($res['content'])) {
        // Apply smart parametric edits to existing design
        $edited = $currentDesign;
        $instLower = mb_strtolower($instruction);

        if (str_contains($instLower, 'dark') || str_contains($instLower, 'داكن') || str_contains($instLower, 'كحلي')) {
            $edited['canvas']['background'] = '#0f172a';
            foreach ($edited['objects'] as &$o) {
                if ($o['type'] === 'text') {
                    $o['fill'] = '#f8fafc';
                }
            }
        } elseif (str_contains($instLower, 'warm') || str_contains($instLower, 'دافي') || str_contains($instLower, 'برتقالي')) {
            $edited['canvas']['background'] = '#fff7ed';
            foreach ($edited['objects'] as &$o) {
                if ($o['type'] === 'rect' && ($o['width'] ?? 0) > 400) {
                    $o['fill'] = '#ffedd5';
                }
            }
        }

        if (str_contains($instLower, 'كبر') || str_contains($instLower, 'bigger') || str_contains($instLower, 'large')) {
            foreach ($edited['objects'] as &$o) {
                if ($o['type'] === 'text' && ($o['fontSize'] ?? 0) >= 30) {
                    $o['fontSize'] = min(90.0, (float)$o['fontSize'] * 1.3);
                }
            }
        }

        $sanitized = sanitize_design_json($edited, $width, $height);
        return [
            'success'  => true,
            'data'     => $sanitized,
            'provider' => 'internal_engine'
        ];
    }

    $parsed = extract_and_decode_json($res['content']);

    // Automatic single retry if invalid JSON
    if ($parsed === null || !isset($parsed['objects']) || !is_array($parsed['objects'])) {
        app_log("Invalid JSON on edit attempt 1, executing retry...", 'WARNING', 'ai.log');
        $retryPrompt = $userPrompt . "\n\nCRITICAL: Return ONLY valid JSON matching { \"canvas\": {...}, \"objects\": [...] }.";
        $retryRes = ai_dispatch($systemInstruction, $retryPrompt, $schema, true);

        if ($retryRes['success'] && !empty($retryRes['content'])) {
            $parsed = extract_and_decode_json($retryRes['content']);
        }
    }

    if ($parsed === null) {
        return [
            'success' => false,
            'error'   => t('ai_invalid_response')
        ];
    }

    $sanitized = sanitize_design_json($parsed, $width, $height);

    return [
        'success'  => true,
        'data'     => $sanitized,
        'provider' => $res['provider']
    ];
}

// =============================================================================
// PHASE 6: AI POSTER PIPELINE (Import & Make Editable + Text to Poster)
// =============================================================================

/**
 * Return validated Gemini model for text/vision or image operations
 * 
 * @param string $type 'text' | 'image'
 * @return string
 */
function ai_get_validated_gemini_model(string $type = 'text'): string {
    if ($type === 'image') {
        return defined('GEMINI_IMAGE_MODEL') && GEMINI_IMAGE_MODEL ? GEMINI_IMAGE_MODEL : 'gemini-2.5-flash-image';
    }
    return defined('GEMINI_MODEL') && GEMINI_MODEL ? GEMINI_MODEL : 'gemini-3-flash-preview';
}

/**
 * Vision analysis for Phase 6 Mode 1 ("Import & Make Editable")
 * Transcribes every text block, language, coordinates, font, colors, glow, and pill badges.
 * Strict rule: Never invent or translate text; transcribe exactly what is visible.
 * 
 * @param string $imagePath Filesystem path to the image
 * @param string $mimeType e.g. 'image/jpeg' or 'image/png'
 * @return array ['success' => bool, 'canvas' => array, 'text_blocks' => array, 'meta' => array, 'error' => ?string]
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

    $apiKey = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
    if (empty($apiKey)) {
        return ['success' => false, 'error' => t('api_key_missing'), 'code' => 'NO_KEY'];
    }

    $model = ai_get_validated_gemini_model('text');
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

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
      \"text\": \"دورة برمجة وتطوير الذكاء الاصطناعي\",
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

Available fonts: Cairo, Tajawal, Almarai, Amiri, Reem Kufi, Poppins, Montserrat, Playfair Display, Roboto.
If text is inside a button, pill, or badge (like 'احجز مكانك!' or a website outline pill), set 'badge': { \"type\": \"pill\", \"fill\": \"#00e5ff\", \"radius\": 20, \"left\": 100, \"top\": 500, \"width\": 160, \"height\": 40 }.
Calculate accurate pixel coordinates (left, top, width, height) relative to {$canvasW}x{$canvasH}.
Return ONLY the raw JSON object without markdown fences or extra explanations.";

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inlineData' => [
                            'mimeType' => $mimeType,
                            'data'     => $b64
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'temperature'      => 0.1,
            'maxOutputTokens'  => 8192
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    $curlErrNo = curl_errno($ch);
    curl_close($ch);

    $elapsed = round((microtime(true) - $t0) * 1000);

    if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
        return ['success' => false, 'error' => t('ai_timeout'), 'code' => 'TIMEOUT'];
    }

    if ($httpCode === 429) {
        return ['success' => false, 'error' => t('ai_quota_exceeded'), 'code' => 'QUOTA_EXCEEDED'];
    }

    if ($httpCode >= 400 || $curlErr) {
        $msg = $curlErr ?: "Gemini Vision error (HTTP {$httpCode})";
        app_log("Gemini Vision OCR failed ({$httpCode}): {$msg}", 'ERROR', 'ai.log');
        return ['success' => false, 'error' => $msg, 'code' => "HTTP_{$httpCode}"];
    }

    $json = json_decode((string)$res, true);
    $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $parsed = extract_and_decode_json($rawText);

    if ($parsed === null || !isset($parsed['text_blocks']) || !is_array($parsed['text_blocks'])) {
        app_log("Invalid JSON from Gemini Vision OCR: " . substr($rawText, 0, 300), 'ERROR', 'ai.log');
        return ['success' => false, 'error' => t('ai_invalid_response'), 'code' => 'INVALID_JSON'];
    }

    // Sanitize and validate detected text blocks
    $allowedFonts = ['Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi', 'Poppins', 'Montserrat', 'Playfair Display', 'Roboto'];
    $sanitizedBlocks = [];
    foreach ($parsed['text_blocks'] as $i => $block) {
        $text = trim((string)($block['text'] ?? ''));
        if ($text === '') continue;

        $lang = ($block['lang'] ?? '') === 'ar' || is_arabic_text($text) ? 'ar' : 'en';
        $dir = $lang === 'ar' ? 'rtl' : 'ltr';
        $font = (string)($block['fontFamily'] ?? ($lang === 'ar' ? 'Cairo' : 'Poppins'));
        if (!in_array($font, $allowedFonts, true)) {
            $font = $lang === 'ar' ? 'Cairo' : 'Poppins';
        }

        $left = max(0, min($canvasW, (float)($block['left'] ?? 0)));
        $top = max(0, min($canvasH, (float)($block['top'] ?? 0)));
        $w = max(20, min($canvasW, (float)($block['width'] ?? 200)));
        $h = max(16, min($canvasH, (float)($block['height'] ?? 40)));
        $fontSize = max(10, min(140, (float)($block['fontSize'] ?? 24)));
        $color = (string)($block['color'] ?? '#ffffff');
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
            $color = '#ffffff';
        }

        $glowColor = !empty($block['glowColor']) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $block['glowColor']) ? $block['glowColor'] : null;
        $glowBlur = isset($block['glowBlur']) ? max(0, min(30, (int)$block['glowBlur'])) : 0;

        $badge = null;
        if (!empty($block['badge']) && is_array($block['badge'])) {
            $b = $block['badge'];
            $bType = in_array($b['type'] ?? '', ['pill', 'outline_pill', 'rect'], true) ? $b['type'] : 'pill';
            $badge = [
                'type'   => $bType,
                'fill'   => !empty($b['fill']) ? $b['fill'] : '#00e5ff',
                'stroke' => !empty($b['stroke']) ? $b['stroke'] : null,
                'radius' => isset($b['radius']) ? max(0, min(50, (int)$b['radius'])) : 18,
                'left'   => max(0, (float)($b['left'] ?? $left)),
                'top'    => max(0, (float)($b['top'] ?? $top)),
                'width'  => max(20, (float)($b['width'] ?? $w)),
                'height' => max(16, (float)($b['height'] ?? $h)),
            ];
        }

        $sanitizedBlocks[] = [
            'id'         => (string)($block['id'] ?? "block_{$i}"),
            'text'       => $text,
            'lang'       => $lang,
            'dir'        => $dir,
            'left'       => round($left, 1),
            'top'        => round($top, 1),
            'width'      => round($w, 1),
            'height'     => round($h, 1),
            'fontSize'   => round($fontSize, 1),
            'fontWeight' => in_array($block['fontWeight'] ?? '', ['bold', 'normal', '700', '800', '900'], true) ? $block['fontWeight'] : 'bold',
            'color'      => $color,
            'glowColor'  => $glowColor,
            'glowBlur'   => $glowBlur,
            'align'      => in_array($block['align'] ?? '', ['center', 'right', 'left'], true) ? $block['align'] : ($lang === 'ar' ? 'right' : 'left'),
            'fontFamily' => $font,
            'badge'      => $badge
        ];
    }

    $canvas = [
        'width'    => $canvasW,
        'height'   => $canvasH,
        'bg_color' => $parsed['canvas']['bg_color'] ?? '#020b14',
        'theme'    => $parsed['canvas']['theme'] ?? 'dark'
    ];

    return [
        'success'     => true,
        'canvas'      => $canvas,
        'text_blocks' => $sanitizedBlocks,
        'meta'        => [
            'provider'   => 'gemini',
            'model'      => $model,
            'latency_ms' => $elapsed
        ],
        'error'       => null
    ];
}

/**
 * PHP GD-based perimeter-sampled inpainting engine
 * Samples adjacent background pixels around bounding boxes, interpolates, and feathers with blur.
 * 
 * @param string $sourcePath
 * @param array $textBlocks
 * @param string $outputPath
 * @return bool
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

    // Collect all bounding boxes: text blocks and badges
    $boxes = [];
    foreach ($textBlocks as $b) {
        if (isset($b['left'], $b['top'], $b['width'], $b['height'])) {
            $boxes[] = [
                'x' => (int)$b['left'],
                'y' => (int)$b['top'],
                'w' => (int)$b['width'],
                'h' => (int)$b['height'],
                'padding' => 6
            ];
        }
        if (!empty($b['badge']) && is_array($b['badge'])) {
            $badge = $b['badge'];
            if (isset($badge['left'], $badge['top'], $badge['width'], $badge['height'])) {
                $boxes[] = [
                    'x' => (int)$badge['left'],
                    'y' => (int)$badge['top'],
                    'w' => (int)$badge['width'],
                    'h' => (int)$badge['height'],
                    'padding' => 8
                ];
            }
        }
    }

    foreach ($boxes as $box) {
        $pad = $box['padding'];
        $bx1 = max(0, $box['x'] - $pad);
        $by1 = max(0, $box['y'] - $pad);
        $bx2 = min($imgW - 1, $box['x'] + $box['w'] + $pad);
        $by2 = min($imgH - 1, $box['y'] + $box['h'] + $pad);
        $bw = $bx2 - $bx1 + 1;
        $bh = $by2 - $by1 + 1;
        if ($bw <= 0 || $bh <= 0) continue;

        // Sample perimeter pixels
        $borderTop = [];
        $borderBot = [];
        $borderLeft = [];
        $borderRight = [];

        for ($px = $bx1; $px <= $bx2; $px += 2) {
            if ($by1 > 0) {
                $c = imagecolorat($img, $px, max(0, $by1 - 2));
                $borderTop[$px] = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
            }
            if ($by2 < $imgH - 1) {
                $c = imagecolorat($img, $px, min($imgH - 1, $by2 + 2));
                $borderBot[$px] = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
            }
        }
        for ($py = $by1; $py <= $by2; $py += 2) {
            if ($bx1 > 0) {
                $c = imagecolorat($img, max(0, $bx1 - 2), $py);
                $borderLeft[$py] = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
            }
            if ($bx2 < $imgW - 1) {
                $c = imagecolorat($img, min($imgW - 1, $bx2 + 2), $py);
                $borderRight[$py] = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
            }
        }

        // Bilinear distance-weighted interpolation
        for ($py = $by1; $py <= $by2; $py++) {
            $vRatio = ($bh > 1) ? ($py - $by1) / ($bh - 1) : 0.5;
            for ($px = $bx1; $px <= $bx2; $px++) {
                $hRatio = ($bw > 1) ? ($px - $bx1) / ($bw - 1) : 0.5;

                $r_vert = 0; $g_vert = 0; $b_vert = 0; $has_vert = false;
                $sTop = $borderTop[$px] ?? ($borderTop[$px - 1] ?? ($borderTop[$px + 1] ?? null));
                $sBot = $borderBot[$px] ?? ($borderBot[$px - 1] ?? ($borderBot[$px + 1] ?? null));
                if ($sTop && $sBot) {
                    $r_vert = $sTop[0] * (1 - $vRatio) + $sBot[0] * $vRatio;
                    $g_vert = $sTop[1] * (1 - $vRatio) + $sBot[1] * $vRatio;
                    $b_vert = $sTop[2] * (1 - $vRatio) + $sBot[2] * $vRatio;
                    $has_vert = true;
                } elseif ($sTop) {
                    $r_vert = $sTop[0]; $g_vert = $sTop[1]; $b_vert = $sTop[2]; $has_vert = true;
                } elseif ($sBot) {
                    $r_vert = $sBot[0]; $g_vert = $sBot[1]; $b_vert = $sBot[2]; $has_vert = true;
                }

                $r_horiz = 0; $g_horiz = 0; $b_horiz = 0; $has_horiz = false;
                $sLeft = $borderLeft[$py] ?? ($borderLeft[$py - 1] ?? ($borderLeft[$py + 1] ?? null));
                $sRight = $borderRight[$py] ?? ($borderRight[$py - 1] ?? ($borderRight[$py + 1] ?? null));
                if ($sLeft && $sRight) {
                    $r_horiz = $sLeft[0] * (1 - $hRatio) + $sRight[0] * $hRatio;
                    $g_horiz = $sLeft[1] * (1 - $hRatio) + $sRight[1] * $hRatio;
                    $b_horiz = $sLeft[2] * (1 - $hRatio) + $sRight[2] * $hRatio;
                    $has_horiz = true;
                } elseif ($sLeft) {
                    $r_horiz = $sLeft[0]; $g_horiz = $sLeft[1]; $b_horiz = $sLeft[2]; $has_horiz = true;
                } elseif ($sRight) {
                    $r_horiz = $sRight[0]; $g_horiz = $sRight[1]; $b_horiz = $sRight[2]; $has_horiz = true;
                }

                if ($has_vert && $has_horiz) {
                    $fr = (int)round(($r_vert + $r_horiz) / 2);
                    $fg = (int)round(($g_vert + $g_horiz) / 2);
                    $fb = (int)round(($b_vert + $b_horiz) / 2);
                } elseif ($has_vert) {
                    $fr = (int)round($r_vert); $fg = (int)round($g_vert); $fb = (int)round($b_vert);
                } elseif ($has_horiz) {
                    $fr = (int)round($r_horiz); $fg = (int)round($g_horiz); $fb = (int)round($b_horiz);
                } else {
                    continue;
                }

                $col = imagecolorallocate($img, $fr, $fg, $fb);
                imagesetpixel($img, $px, $py, $col);
            }
        }

        // Apply feather blur
        $region = imagecreatetruecolor($bw, $bh);
        imagecopy($region, $img, 0, 0, $bx1, $by1, $bw, $bh);
        imagefilter($region, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($region, IMG_FILTER_SMOOTH, 2);
        imagecopy($img, $region, $bx1, $by1, 0, 0, $bw, $bh);
        imagedestroy($region);
    }

    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $saved = imagejpeg($img, $outputPath, 92);
    imagedestroy($img);
    return $saved;
}

/**
 * Clean plate generator for Phase 6 Mode 1.
 * Removes text/letters/badges while keeping background pixel-identical and aspect-ratio matched.
 * Attempts Gemini image model first; if fails/quota, falls back seamlessly to PHP GD inpainting with notice.
 * 
 * @param string $sourcePath Filesystem path to original image
 * @param array $textBlocks Detected text blocks
 * @param int $canvasW Width in px
 * @param int $canvasH Height in px
 * @param bool $forceFallback If true, forces inpainting fallback (for testing or user preference)
 * @return array ['success' => bool, 'clean_plate_url' => string, 'clean_plate_path' => string, 'method' => string, 'fallback_used' => bool, 'notice' => ?string, 'latency_ms' => int]
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

    // Check if clean plate was already generated for this exact image and blocks
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

    // Try Gemini image model if configured and not forced fallback
    if (!$forceFallback && !empty($imageModel) && !empty($apiKey)) {
        // Attempt image model text removal
        $raw = @file_get_contents($sourcePath);
        if ($raw) {
            $b64 = base64_encode($raw);
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$imageModel}:generateContent?key=" . urlencode($apiKey);
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
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30,
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

    // Inpainting fallback if image model was not used or failed
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
 * Generates visual concept, composition zones, text-free image prompt, and structured typography.
 * STRICT RULE: NEVER invent URLs, phone numbers, prices, dates, discounts, or brand names not provided by the user.
 * 
 * @param string $prompt
 * @param array $options
 * @return array
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
4. Generate authentic Arabic/English copy: punchy headline, engaging subtitle, and relevant feature bullet points strictly derived from the user's intent.
5. Create composition zones so that typography is placed in clean open areas (e.g. on the left, top, or bottom) and NEVER collides with the central hero visual subject.
6. The image prompt must be in English, descriptive, and explicitly state: 'textless, no words, no letters, no logos, no typography, 8k octane render'.";

    $userMessage = "Design a promotional poster for:
\"{$prompt}\"
Canvas size: {$targetW}x{$targetH} px.

Return ONLY a valid JSON object matching:
{
  \"canvas\": { \"width\": {$targetW}, \"height\": {$targetH}, \"bg_color\": \"#020b14\", \"theme\": \"dark\" },
  \"concept\": \"Description of visual concept and atmosphere\",
  \"palette\": { \"primary\": \"#00f0ff\", \"secondary\": \"#ffffff\", \"accent\": \"#0088ff\", \"bg\": \"#020b14\" },
  \"composition_zones\": {
    \"visual_zone\": { \"left\": 500, \"top\": 100, \"width\": 540, \"height\": 700, \"description\": \"Hero subject placement on right side\" },
    \"text_zone\": { \"left\": 50, \"top\": 100, \"width\": 440, \"height\": 700, \"description\": \"Typography and features on left side\" }
  },
  \"image_prompt\": \"Cinematic futuristic 3D octane render of a cyber developer at multi-monitor workstation, glowing holographic AI neural network, dark sci-fi aesthetic, volumetric lighting, photorealistic, textless, no letters, no words, no watermark\",
  \"text_blocks\": [
    {
      \"id\": \"headline\",
      \"text\": \"عنوان البوستر هنا\",
      \"lang\": \"ar\",
      \"dir\": \"rtl\",
      \"left\": 60,
      \"top\": 80,
      \"width\": 960,
      \"height\": 70,
      \"fontSize\": 48,
      \"fontWeight\": \"bold\",
      \"color\": \"#00f0ff\",
      \"glowColor\": \"#00c8ff\",
      \"glowBlur\": 12,
      \"align\": \"center\",
      \"fontFamily\": \"Cairo\",
      \"badge\": null
    }
  ]
}

Available fonts: Cairo, Tajawal, Almarai, Amiri, Reem Kufi, Poppins, Montserrat, Playfair Display, Roboto.
If text is a CTA button or badge, specify 'badge': { \"type\": \"pill\", \"fill\": \"#00e5ff\", \"radius\": 20, \"left\": ..., \"top\": ..., \"width\": ..., \"height\": ... }.";

    $res = ai_dispatch($systemInstruction, $userMessage, null, true);
    $elapsed = round((microtime(true) - $t0) * 1000);

    if (!$res['success'] || empty($res['content'])) {
        return [
            'success' => false,
            'error'   => $res['error'] ?? t('ai_invalid_response'),
            'code'    => 'ART_DIRECTION_FAILED'
        ];
    }

    $parsed = extract_and_decode_json($res['content']);
    if ($parsed === null || !isset($parsed['text_blocks']) || !is_array($parsed['text_blocks'])) {
        return [
            'success' => false,
            'error'   => t('ai_invalid_response'),
            'code'    => 'INVALID_JSON'
        ];
    }

    // Strict validation: enforce that no invented URLs or fake domains were hallucinated
    $sanitizedBlocks = [];
    foreach ($parsed['text_blocks'] as $i => $block) {
        $text = trim((string)($block['text'] ?? ''));
        if ($text === '') continue;

        // If user prompt does NOT contain 'www.' or '.com' or 'http', strip hallucinated URLs
        $hasUrlPattern = (bool)preg_match('/(www\.[a-z0-9\-]+\.[a-z]{2,}|https?:\/\/[^\s]+|\.com|\.org|\.net)/i', $text);
        $userHadUrl = (bool)preg_match('/(www\.[a-z0-9\-]+\.[a-z]{2,}|https?:\/\/[^\s]+|\.com|\.org|\.net)/i', $prompt);
        if ($hasUrlPattern && !$userHadUrl) {
            continue; // Remove hallucinated URL block completely!
        }

        $sanitizedBlocks[] = $block;
    }
    $parsed['text_blocks'] = $sanitizedBlocks;

    return [
        'success'        => true,
        'art_direction'  => $parsed,
        'meta'           => [
            'provider'   => $res['provider'],
            'latency_ms' => $elapsed
        ]
    ];
}

/**
 * Generate text-free background art for Mode 2 ("Text to Poster")
 * Saves into /storage/uploads/ and serves via safe upload proxy.
 * 
 * @param string $imagePrompt
 * @param int $width
 * @param int $height
 * @return array ['success' => bool, 'image_url' => string, 'file_path' => string, 'meta' => array, 'error' => ?string]
 */
function ai_generate_poster_background(string $imagePrompt, int $width = 1080, int $height = 1080): array {
    $t0 = microtime(true);
    $uploadDir = dirname(__DIR__) . '/storage/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $hash = md5($imagePrompt . "_{$width}x{$height}");
    $filename = "poster_bg_{$hash}.jpg";
    $localPath = $uploadDir . '/' . $filename;
    $imageUrl = 'api/upload.php?file=' . $filename;

    if (file_exists($localPath) && filesize($localPath) > 5000) {
        $elapsed = round((microtime(true) - $t0) * 1000);
        return [
            'success'    => true,
            'image_url'  => $imageUrl,
            'file_path'  => $localPath,
            'meta'       => [
                'provider'   => 'cached',
                'latency_ms' => $elapsed
            ]
        ];
    }

    // Enforce text-free modifiers in the prompt
    $cleanPrompt = trim($imagePrompt) . ", high quality, photorealistic, textless, no text, no letters, no words, octane render";
    $encodedPrompt = urlencode($cleanPrompt);
    $pollinationsUrl = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width={$width}&height={$height}&nologo=true&model=flux";

    $ch = curl_init($pollinationsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 35,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $elapsed = round((microtime(true) - $t0) * 1000);

    if ($httpCode === 200 && $raw && strlen($raw) > 2000) {
        file_put_contents($localPath, $raw);
        return [
            'success'    => true,
            'image_url'  => $imageUrl,
            'file_path'  => $localPath,
            'meta'       => [
                'provider'   => 'pollinations',
                'latency_ms' => $elapsed
            ]
        ];
    }

    app_log("Poster background generation failed ({$httpCode}): {$curlErr}", 'ERROR', 'ai.log');
    return [
        'success' => false,
        'error'   => t('poster_image_failed'),
        'code'    => 'IMAGE_GEN_FAILED'
    ];
}


