<?php
define('APP_INIT', true);
require __DIR__ . '/../config.php';

$key = GEMINI_API_KEY;
$refImage = "C:/Users/Kirolos Samir0/.gemini/antigravity/brain/17b16749-06fc-422d-bb1e-f521886da31d/.user_uploaded/media_1794006378956_1c6f42a9.jpg";

$raw = file_get_contents($refImage);
$b64 = base64_encode($raw);

// Get image dimensions via getimagesize
$imgInfo = getimagesize($refImage);
$imgW = $imgInfo[0];
$imgH = $imgInfo[1];
echo "Image dimensions: {$imgW}x{$imgH}\n";

$model = "gemini-3.5-flash";
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($key);

$prompt = "You are an elite vector graphic designer and computer vision OCR specialist.
Scan this poster image with pixel-level precision.
Extract EVERY single text block visible on the image without exception (headlines, subheadlines, bullet items, badges, URLs, labels, buttons, notes).
STRICT RULES:
1. Do NOT translate or invent text. Transcribe the exact visible characters, accents and words in their original language.
2. For every text block, provide:
   - id: unique string identifier (e.g. 'headline_1', 'bullet_1', 'badge_cta')
   - text: exact transcription, preserving intentional line breaks with \\n
   - language: 'ar' or 'en'
   - direction: 'rtl' for Arabic, 'ltr' for English/Latin
   - bbox_norm: normalized bounding box [ymin, xmin, ymax, xmax] from 0.0 to 1000.0 (where 0 is top/left, 1000 is bottom/right)
   - bbox_px: [left, top, width, height] in real pixels based on {$imgW}x{$imgH}
   - rotation: degrees (0 for horizontal)
   - fontSize_px: estimated font size in pixels (integer)
   - fontWeight: '400', '600', '700', '800'
   - fontStyle: 'normal' or 'italic'
   - textAlign: 'left', 'center', or 'right'
   - color_hex: primary text color in hex (e.g. '#ffffff', '#00f0ff')
   - effects: object with:
       glow_color: hex or null
       glow_blur: integer or 0
       stroke_color: hex or null
       stroke_width: integer or 0
       shadow_color: hex or null
       shadow_blur: integer or 0
       shadow_offset_y: integer or 0
   - matched_font: closest match from: ['Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi', 'Poppins', 'Montserrat', 'Playfair Display', 'Roboto']
   - badge_behind: if this text is placed inside a pill, badge, or button shape, specify:
       has_badge: boolean
       shape_type: 'rect' or 'pill'
       fill_color: hex (e.g. '#00e5ff', '#1e293b')
       border_color: hex or null
       border_width: integer or 0
       corner_radius: integer (e.g. 20 for pill, 6 for rounded rect)
       bbox_px: [left, top, width, height] of the badge

Also return:
- canvas_meta: { width: {$imgW}, height: {$imgH}, dominant_bg_color: hex, theme: 'dark'|'light', style: 'tech_neon'|'minimal'|'corporate' }
- composition_zones: { headline_zone: [ymin, xmin, ymax, xmax], subject_zone: [ymin, xmin, ymax, xmax], footer_zone: [ymin, xmin, ymax, xmax] }

Return ONLY valid JSON matching this schema.";

$schema = [
    'type' => 'OBJECT',
    'properties' => [
        'canvas_meta' => [
            'type' => 'OBJECT',
            'properties' => [
                'width' => ['type' => 'INTEGER'],
                'height' => ['type' => 'INTEGER'],
                'dominant_bg_color' => ['type' => 'STRING'],
                'theme' => ['type' => 'STRING'],
                'style' => ['type' => 'STRING']
            ],
            'required' => ['width', 'height', 'dominant_bg_color']
        ],
        'composition_zones' => [
            'type' => 'OBJECT',
            'properties' => [
                'headline_zone' => ['type' => 'ARRAY', 'items' => ['type' => 'NUMBER']],
                'subject_zone' => ['type' => 'ARRAY', 'items' => ['type' => 'NUMBER']],
                'footer_zone' => ['type' => 'ARRAY', 'items' => ['type' => 'NUMBER']]
            ]
        ],
        'text_blocks' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'id' => ['type' => 'STRING'],
                    'text' => ['type' => 'STRING'],
                    'language' => ['type' => 'STRING'],
                    'direction' => ['type' => 'STRING'],
                    'bbox_norm' => ['type' => 'ARRAY', 'items' => ['type' => 'NUMBER']],
                    'bbox_px' => ['type' => 'ARRAY', 'items' => ['type' => 'NUMBER']],
                    'rotation' => ['type' => 'NUMBER'],
                    'fontSize_px' => ['type' => 'NUMBER'],
                    'fontWeight' => ['type' => 'STRING'],
                    'textAlign' => ['type' => 'STRING'],
                    'color_hex' => ['type' => 'STRING'],
                    'effects' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'glow_color' => ['type' => 'STRING'],
                            'glow_blur' => ['type' => 'NUMBER'],
                            'stroke_color' => ['type' => 'STRING'],
                            'stroke_width' => ['type' => 'NUMBER'],
                            'shadow_color' => ['type' => 'STRING'],
                            'shadow_blur' => ['type' => 'NUMBER']
                        ]
                    ],
                    'matched_font' => ['type' => 'STRING'],
                    'badge_behind' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'has_badge' => ['type' => 'BOOLEAN'],
                            'shape_type' => ['type' => 'STRING'],
                            'fill_color' => ['type' => 'STRING'],
                            'border_color' => ['type' => 'STRING'],
                            'border_width' => ['type' => 'NUMBER'],
                            'corner_radius' => ['type' => 'NUMBER'],
                            'bbox_px' => ['type' => 'ARRAY', 'items' => ['type' => 'NUMBER']]
                        ]
                    ]
                ],
                'required' => ['text', 'bbox_px', 'fontSize_px', 'color_hex']
            ]
        ]
    ],
    'required' => ['canvas_meta', 'text_blocks']
];

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
                [
                    'inlineData' => [
                        'mimeType' => 'image/jpeg',
                        'data' => $b64
                    ]
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'responseMimeType' => 'application/json',
        'responseSchema' => $schema,
        'temperature' => 0.1
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 40
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "Vision Call HTTP $code (err: $err)\n";
if ($code === 200) {
    $json = json_decode($res, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    echo "Parsed Result:\n$text\n";
    file_put_contents(__DIR__ . '/test_vision_result.json', $text);
} else {
    echo "Error: " . substr($res, 0, 500) . "\n";
}

