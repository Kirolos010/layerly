<?php
/**
 * AI Design Studio - JSON Sanitizer & Validator
 * 
 * Validates, cleans, and clamps the structured design JSON against the strict schema:
 * - Canvas dimensions & background
 * - Whitelisted object types (text, rect, circle, triangle, line, image)
 * - Whitelisted typography & fonts (Cairo, Tajawal, Almarai, Amiri, Reem Kufi, Poppins, Montserrat, Playfair Display, Roboto)
 * - Automatic RTL detection for Arabic strings
 * - Max 60 objects limit
 * - Strips unknown / injected properties
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once __DIR__ . '/security.php';

const ALLOWED_OBJECT_TYPES = [
    'text',
    'rect',
    'circle',
    'triangle',
    'line',
    'image'
];

const ALLOWED_FONTS = [
    'Cairo',
    'Tajawal',
    'Almarai',
    'Amiri',
    'Reem Kufi',
    'Poppins',
    'Montserrat',
    'Playfair Display',
    'Roboto'
];

const ALLOWED_FONT_WEIGHTS = [
    'normal',
    'bold',
    '300',
    '400',
    '500',
    '600',
    '700',
    '800'
];

const ALLOWED_TEXT_ALIGNS = [
    'left',
    'center',
    'right',
    'justify'
];

const MAX_OBJECTS_COUNT = 60;

/**
 * Extract and decode JSON string from raw AI response text (handles code fences, comments)
 * 
 * @param string $raw
 * @return array|null
 */
function extract_and_decode_json(string $raw): ?array {
    $clean = trim($raw);

    // Remove markdown code fences if present (```json ... ``` or ``` ...)
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $clean, $matches)) {
        $clean = trim($matches[1]);
    }

    // Try direct decode
    $decoded = json_decode($clean, true);
    if (is_array($decoded) && (isset($decoded['canvas']) || isset($decoded['objects']))) {
        return $decoded;
    }

    // Fallback: search between first '{' and last '}'
    $firstBrace = strpos($clean, '{');
    $lastBrace = strrpos($clean, '}');
    if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
        $sub = substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
        $decoded = json_decode($sub, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

/**
 * Validate and clean a color string (#hex, rgba, transparent)
 */
function sanitize_color(?string $color, string $default = '#000000'): string {
    if ($color === null) {
        return $default;
    }
    $color = trim($color);

    if (in_array(strtolower($color), ['transparent', 'none'], true)) {
        return strtolower($color);
    }

    // Hex color: #RGB, #RRGGBB, #RRGGBBAA
    if (preg_match('/^#([a-f0-9]{3,4}|[a-f0-9]{6}|[a-f0-9]{8})$/i', $color)) {
        return $color;
    }

    // rgba / rgb format: rgba(r,g,b,a)
    if (preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(?:,\s*[\d\.]+\s*)?\)$/i', $color)) {
        return $color;
    }

    // If pure 6 or 3 hex chars without leading #
    if (preg_match('/^[a-f0-9]{6}$/i', $color) || preg_match('/^[a-f0-9]{3}$/i', $color)) {
        return '#' . $color;
    }

    return $default;
}

/**
 * Sanitize and validate the complete Design JSON structure
 * 
 * @param array $data
 * @param int $defaultWidth
 * @param int $defaultHeight
 * @return array Sanitized and clamped design schema
 */
function sanitize_design_json(array $data, int $defaultWidth = 1080, int $defaultHeight = 1080): array {
    // 1. Sanitize Canvas
    $canvasRaw = $data['canvas'] ?? [];
    $width = isset($canvasRaw['width']) ? (int)$canvasRaw['width'] : $defaultWidth;
    $height = isset($canvasRaw['height']) ? (int)$canvasRaw['height'] : $defaultHeight;

    // Clamp dimensions
    $width = max(100, min(4000, $width));
    $height = max(100, min(4000, $height));
    $background = sanitize_color($canvasRaw['background'] ?? '#ffffff', '#ffffff');

    $sanitizedCanvas = [
        'width'      => $width,
        'height'     => $height,
        'background' => $background
    ];

    // 2. Sanitize Objects
    $objectsRaw = $data['objects'] ?? [];
    if (!is_array($objectsRaw)) {
        $objectsRaw = [];
    }

    // Limit object count to MAX_OBJECTS_COUNT (60)
    if (count($objectsRaw) > MAX_OBJECTS_COUNT) {
        $objectsRaw = array_slice($objectsRaw, 0, MAX_OBJECTS_COUNT);
    }

    $sanitizedObjects = [];

    foreach ($objectsRaw as $obj) {
        if (!is_array($obj)) {
            continue;
        }

        $type = strtolower(trim((string)($obj['type'] ?? '')));
        if (!in_array($type, ALLOWED_OBJECT_TYPES, true)) {
            continue; // Skip unrecognized element types
        }

        $text = (string)($obj['text'] ?? '');
        $isArabic = is_arabic_text($text);

        // Direction: auto-detect if Arabic, or clamp to ltr|rtl
        $direction = strtolower(trim((string)($obj['direction'] ?? '')));
        if ($direction !== 'rtl' && $direction !== 'ltr') {
            $direction = $isArabic ? 'rtl' : 'ltr';
        }

        // Font Family validation
        $fontFamily = trim((string)($obj['fontFamily'] ?? ''));
        $matchedFont = null;
        foreach (ALLOWED_FONTS as $allowedFont) {
            if (strcasecmp($allowedFont, $fontFamily) === 0) {
                $matchedFont = $allowedFont;
                break;
            }
        }
        if (!$matchedFont) {
            $matchedFont = $isArabic ? 'Cairo' : 'Poppins';
        }

        // Font Weight validation
        $fontWeight = strtolower(trim((string)($obj['fontWeight'] ?? 'normal')));
        if (!in_array($fontWeight, ALLOWED_FONT_WEIGHTS, true)) {
            $fontWeight = in_array($fontWeight, ['700', '800', 'bold', 'bolder'], true) ? 'bold' : 'normal';
        }

        // Text Align validation
        $textAlign = strtolower(trim((string)($obj['textAlign'] ?? '')));
        if (!in_array($textAlign, ALLOWED_TEXT_ALIGNS, true)) {
            $textAlign = ($direction === 'rtl') ? 'right' : 'left';
        }

        // Font Size
        $fontSize = isset($obj['fontSize']) ? (float)$obj['fontSize'] : 24.0;
        $fontSize = max(8.0, min(300.0, round($fontSize, 1)));

        // Coordinates & Dimensions clamping
        $left = isset($obj['left']) ? round((float)$obj['left'], 2) : 0.0;
        $top = isset($obj['top']) ? round((float)$obj['top'], 2) : 0.0;
        $w = isset($obj['width']) ? max(1.0, min(4000.0, round((float)$obj['width'], 2))) : 100.0;
        $h = isset($obj['height']) ? max(1.0, min(4000.0, round((float)$obj['height'], 2))) : 100.0;

        // Stroke & Stroke Width
        $strokeWidth = isset($obj['strokeWidth']) ? max(0.0, min(50.0, round((float)$obj['strokeWidth'], 2))) : 0.0;
        $stroke = isset($obj['stroke']) && $obj['stroke'] !== '' ? sanitize_color($obj['stroke'], 'transparent') : 'transparent';

        // Fill color
        $fillDefault = ($type === 'text') ? '#1e293b' : '#3b82f6';
        $fill = sanitize_color($obj['fill'] ?? $fillDefault, $fillDefault);

        // Opacity
        $opacity = isset($obj['opacity']) ? max(0.0, min(1.0, round((float)$obj['opacity'], 2))) : 1.0;

        // Angle
        $angle = isset($obj['angle']) ? fmod((float)$obj['angle'], 360.0) : 0.0;
        if ($angle < 0) {
            $angle += 360.0;
        }
        $angle = round($angle, 1);

        // Border radius rx (for rect)
        $rx = isset($obj['rx']) ? max(0.0, min(500.0, round((float)$obj['rx'], 1))) : 0.0;

        // Image Query (for image objects)
        $imageQuery = trim((string)($obj['imageQuery'] ?? ''));
        if (mb_strlen($imageQuery) > 200) {
            $imageQuery = mb_substr($imageQuery, 0, 200);
        }

        // Build sanitized element strictly matching the schema
        $sanitizedObj = [
            'type'        => $type,
            'text'        => $text,
            'left'        => $left,
            'top'         => $top,
            'width'       => $w,
            'height'      => $h,
            'fill'        => $fill,
            'stroke'      => $stroke,
            'strokeWidth' => $strokeWidth,
            'fontFamily'  => $matchedFont,
            'fontSize'    => $fontSize,
            'fontWeight'  => $fontWeight,
            'textAlign'   => $textAlign,
            'direction'   => $direction,
            'angle'       => $angle,
            'opacity'     => $opacity,
            'rx'          => $rx,
            'imageQuery'  => $imageQuery
        ];

        if (!empty($obj['src']) && is_string($obj['src'])) {
            $src = trim($obj['src']);
            if (str_starts_with($src, 'api/') || str_starts_with($src, 'storage/') || str_starts_with($src, 'http://') || str_starts_with($src, 'https://') || str_starts_with($src, 'data:image/')) {
                $sanitizedObj['src'] = $src;
            }
        }

        if (!empty($obj['locked'])) {
            $sanitizedObj['locked'] = true;
        }

        if (!empty($obj['isBackground'])) {
            $sanitizedObj['isBackground'] = true;
        }

        if (!empty($obj['name']) && is_string($obj['name'])) {
            $sanitizedObj['name'] = mb_substr(strip_tags(trim($obj['name'])), 0, 100);
        }

        $sanitizedObjects[] = $sanitizedObj;
    }

    return [
        'canvas'  => $sanitizedCanvas,
        'objects' => $sanitizedObjects
    ];
}

