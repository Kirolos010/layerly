<?php
/**
 * AI Design Studio - AI System Prompts & Schemas
 */

defined('APP_INIT') or define('APP_INIT', true);

class Prompts {

    /**
     * Gemini responseSchema for structured Canvas JSON generation
     */
    public static function getDesignSchema(): array {
        return [
            'type' => 'OBJECT',
            'required' => ['canvas', 'objects'],
            'properties' => [
                'canvas' => [
                    'type' => 'OBJECT',
                    'required' => ['width', 'height', 'background'],
                    'properties' => [
                        'width' => ['type' => 'INTEGER'],
                        'height' => ['type' => 'INTEGER'],
                        'background' => ['type' => 'STRING'],
                        'background_image_prompt' => [
                            'type' => 'STRING',
                            'description' => 'A descriptive prompt for an artistic background illustration without text or letters.'
                        ]
                    ]
                ],
                'objects' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'required' => ['type', 'left', 'top'],
                        'properties' => [
                            'type'        => ['type' => 'STRING', 'enum' => ['text', 'rect', 'circle', 'triangle', 'line', 'image']],
                            'left'        => ['type' => 'NUMBER'],
                            'top'         => ['type' => 'NUMBER'],
                            'width'       => ['type' => 'NUMBER'],
                            'height'      => ['type' => 'NUMBER'],
                            'fill'        => ['type' => 'STRING'],
                            'stroke'      => ['type' => 'STRING'],
                            'strokeWidth' => ['type' => 'NUMBER'],
                            'opacity'     => ['type' => 'NUMBER'],
                            'angle'       => ['type' => 'NUMBER'],
                            'rx'          => ['type' => 'NUMBER'],
                            'ry'          => ['type' => 'NUMBER'],
                            'radius'      => ['type' => 'NUMBER'],
                            'text'        => ['type' => 'STRING'],
                            'fontSize'    => ['type' => 'NUMBER'],
                            'fontFamily'  => ['type' => 'STRING'],
                            'fontWeight'  => ['type' => 'STRING'],
                            'textAlign'   => ['type' => 'STRING', 'enum' => ['left', 'center', 'right', 'justify']],
                            'direction'   => ['type' => 'STRING', 'enum' => ['rtl', 'ltr']],
                            'lineHeight'  => ['type' => 'NUMBER'],
                            'x1'          => ['type' => 'NUMBER'],
                            'y1'          => ['type' => 'NUMBER'],
                            'x2'          => ['type' => 'NUMBER'],
                            'y2'          => ['type' => 'NUMBER'],
                            'shadow'      => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'color'   => ['type' => 'STRING'],
                                    'blur'    => ['type' => 'NUMBER'],
                                    'offsetX' => ['type' => 'NUMBER'],
                                    'offsetY' => ['type' => 'NUMBER']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * System prompt for design generation
     */
    public static function getDesignSystemPrompt(bool $isArabic, int $width, int $height, array $options, bool $withBg): string {
        $fontHeading = $isArabic ? 'Cairo' : 'Poppins';
        $fontBody = $isArabic ? 'Tajawal' : 'Montserrat';
        $direction = $isArabic ? 'rtl' : 'ltr';
        $align = $isArabic ? 'right' : 'left';
        $langStr = $isArabic ? 'Arabic' : 'English';

        $bgInstruction = $withBg
            ? "- A descriptive English image prompt in 'background_image_prompt' field for a high-quality illustration or texture (e.g., 'warm cozy coffee shop interior blurred bokeh warm sunset light no text no letters'). MUST contain NO text, letters, or logos.\n- Position all text and card containers so they harmoniously overlay the background image without obscuring key visual focal points."
            : "- Set canvas 'background' to a rich solid color or dark gradient hex code.";

        return "You are an elite graphic designer and UI/UX art director specialized in social media graphics, posters, and banners.
Generate a complete, production-grade graphic design represented purely as a structured JSON canvas layout with Fabric.js compatible objects.

CANVAS SPECIFICATIONS:
- Resolution: {$width}x{$height} pixels
- Target Language: {$langStr}
- Direction: {$direction}
- Default Heading Font: {$fontHeading}
- Default Body Font: {$fontBody}
- Whitelisted Fonts:
  Arabic: 'Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi'
  English: 'Poppins', 'Montserrat', 'Playfair Display', 'Roboto'

CRITICAL DESIGN RULES:
1. Every design must feel crafted by a human art director, not an AI template.
2. Layering hierarchy:
   - Background: Solid hex color or subtle decorative shapes (rectangles, soft glowing circles).
   {$bgInstruction}
   - Content Cards / Badges: Decorative rounded rectangles with soft opacity for taglines, pill buttons, or CTA banners.
   - Typography Hierarchy:
     * Catchy Badge/Tagline: 18-24px, bold or uppercase
     * Main Title / Headline: 64-96px, bold, prominent
     * Subheadline / Value Proposition: 28-38px
     * Features / Body / Highlights: 20-28px
     * Call to Action (CTA) / Footer: 22-30px, high-contrast button shape behind it
3. Contrast & Legibility:
   - Ensure extreme contrast between text and background.
   - If background is dark, text MUST be pure white or vivid accent tints.
   - If background is light, text MUST be deep charcoal or black.
4. Alignment & Spacing:
   - Respect margin padding (minimum 60px from canvas edges).
   - For Arabic ({$direction}): align text to '{$align}' and set 'direction' to 'rtl'.
   - Avoid overlapping text bounding boxes.
5. Return ONLY valid JSON conforming to the schema.";
    }

    /**
     * System prompt for prompt enhancement
     */
    public static function getEnhanceSystemPrompt(?string $lang = null): string {
        return "You are an expert design creative director and copywriter.
A user will provide a raw, brief idea for a graphic design, poster, or banner.
Your mission is to expand this idea into a rich, detailed, visually stunning design creative brief.

Output requirements:
1. Detect or use the target language (Arabic if the idea is Arabic or lang='ar', English otherwise).
2. Write a comprehensive visual description specifying:
   - The core visual theme and art direction
   - A high-converting headline, subheadline, and call to action (in the target language)
   - Color palette mood (e.g. Luxury Gold & Navy, Cyberpunk Neon Cyan, Warm Terracotta)
   - Layout structure (where elements, images, badges, and text should sit)
   - Typography choices and visual rhythm
3. Respond ONLY with a clean JSON object:
{
  \"enhanced_prompt\": \"Detailed creative brief here...\",
  \"suggested_style\": \"modern|minimalist|bold|luxury|playful\",
  \"suggested_color_mood\": \"warm|cool|neon|monochrome|pastel\",
  \"detected_lang\": \"ar|en\"
}";
    }

    /**
     * OCR Text Extraction schema
     */
    public static function getOcrSchema(): array {
        return [
            'type' => 'OBJECT',
            'required' => ['blocks'],
            'properties' => [
                'blocks' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'required' => ['text', 'left_percent', 'top_percent', 'fontSize', 'color'],
                        'properties' => [
                            'text'         => ['type' => 'STRING'],
                            'left_percent' => ['type' => 'NUMBER'],
                            'top_percent'  => ['type' => 'NUMBER'],
                            'fontSize'     => ['type' => 'NUMBER'],
                            'fontWeight'   => ['type' => 'STRING'],
                            'color'        => ['type' => 'STRING'],
                            'fontFamily'   => ['type' => 'STRING'],
                            'language'     => ['type' => 'STRING', 'enum' => ['ar', 'en']]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Poster Vision Analysis schema (Mode 1)
     */
    public static function getPosterVisionSchema(): array {
        return [
            'type' => 'OBJECT',
            'required' => ['text_blocks', 'background_summary'],
            'properties' => [
                'background_summary' => ['type' => 'STRING'],
                'text_blocks' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'required' => ['id', 'text', 'language', 'direction', 'left', 'top', 'width', 'height', 'fontSize', 'fontFamily', 'fill'],
                        'properties' => [
                            'id'         => ['type' => 'STRING'],
                            'text'       => ['type' => 'STRING'],
                            'language'   => ['type' => 'STRING', 'enum' => ['ar', 'en']],
                            'direction'  => ['type' => 'STRING', 'enum' => ['rtl', 'ltr']],
                            'left'       => ['type' => 'NUMBER'],
                            'top'        => ['type' => 'NUMBER'],
                            'width'      => ['type' => 'NUMBER'],
                            'height'     => ['type' => 'NUMBER'],
                            'fontSize'   => ['type' => 'NUMBER'],
                            'fontWeight' => ['type' => 'STRING'],
                            'fontFamily' => ['type' => 'STRING'],
                            'textAlign'  => ['type' => 'STRING'],
                            'fill'       => ['type' => 'STRING'],
                            'badge'      => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'left'         => ['type' => 'NUMBER'],
                                    'top'          => ['type' => 'NUMBER'],
                                    'width'        => ['type' => 'NUMBER'],
                                    'height'       => ['type' => 'NUMBER'],
                                    'fill'         => ['type' => 'STRING'],
                                    'borderRadius' => ['type' => 'NUMBER']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Poster Art Direction schema (Mode 2)
     */
    public static function getPosterArtDirectionSchema(): array {
        return [
            'type' => 'OBJECT',
            'required' => ['art_direction'],
            'properties' => [
                'art_direction' => [
                    'type' => 'OBJECT',
                    'required' => ['image_prompt', 'color_palette', 'text_blocks'],
                    'properties' => [
                        'image_prompt' => ['type' => 'STRING'],
                        'color_palette' => [
                            'type' => 'OBJECT',
                            'required' => ['background', 'primary_text', 'accent', 'badge_fill'],
                            'properties' => [
                                'background'   => ['type' => 'STRING'],
                                'primary_text' => ['type' => 'STRING'],
                                'accent'       => ['type' => 'STRING'],
                                'badge_fill'   => ['type' => 'STRING']
                            ]
                        ],
                        'text_blocks' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'required' => ['id', 'text', 'role', 'fontSize', 'fontFamily', 'fontWeight', 'fill', 'left', 'top', 'width', 'direction', 'textAlign'],
                                'properties' => [
                                    'id'         => ['type' => 'STRING'],
                                    'text'       => ['type' => 'STRING'],
                                    'role'       => ['type' => 'STRING', 'enum' => ['headline', 'subheadline', 'bullet', 'cta', 'badge', 'contact']],
                                    'fontSize'   => ['type' => 'NUMBER'],
                                    'fontFamily' => ['type' => 'STRING'],
                                    'fontWeight' => ['type' => 'STRING'],
                                    'fill'       => ['type' => 'STRING'],
                                    'left'       => ['type' => 'NUMBER'],
                                    'top'        => ['type' => 'NUMBER'],
                                    'width'      => ['type' => 'NUMBER'],
                                    'direction'  => ['type' => 'STRING', 'enum' => ['rtl', 'ltr']],
                                    'textAlign'  => ['type' => 'STRING', 'enum' => ['right', 'center', 'left']],
                                    'badge'      => [
                                        'type' => 'OBJECT',
                                        'properties' => [
                                            'fill'         => ['type' => 'STRING'],
                                            'borderRadius' => ['type' => 'NUMBER']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}

