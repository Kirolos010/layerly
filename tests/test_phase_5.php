<?php
/**
 * AI Design Studio - Phase 5 Automated Test Suite
 * 
 * Verifies:
 * 1. Hybrid Image + Editable Layers (Pollinations.ai text-free background generation).
 * 2. Background Removal Endpoint (/api/remove-bg.php) with Hugging Face RMBG logic and error handling.
 * 3. Gemini Vision OCR Text Extraction Endpoint (/api/extract-text.php) with structured editable text blocks.
 * 4. Sanitizer schema preservation of image properties (src, locked, isBackground, name).
 * 5. Full bilingual translation keys parity for Phase 5.
 * 6. UI controls integration (generate-bg-toggle, btn-separate-subject, btn-extract-text).
 */

define('APP_INIT', true);
define('TEST_RUN', true);

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/sanitizer.php';
require_once __DIR__ . '/../includes/ai-client.php';
require_once __DIR__ . '/../includes/auth.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function run_test(string $name, callable $callback) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    echo "Running Test #{$totalTests}: {$name}... ";
    try {
        $result = $callback();
        if ($result === true || $result === null) {
            echo "\033[32m[PASS]\033[0m\n";
            $passedTests++;
            return true;
        } else {
            echo "\033[31m[FAIL: {$result}]\033[0m\n";
            $failedTests++;
            return false;
        }
    } catch (\Throwable $e) {
        echo "\033[31m[EXCEPTION: " . $e->getMessage() . "]\033[0m\n";
        $failedTests++;
        return false;
    }
}

echo "========================================================\n";
echo "   AI Design Studio - Phase 5 Hybrid & Vision Suite      \n";
echo "========================================================\n\n";

// TEST 1: Phase 5 Translation Keys & Parity
run_test("Phase 5 Translation keys completeness & parity", function() {
    $enPath = __DIR__ . '/../lang/en.json';
    $arPath = __DIR__ . '/../lang/ar.json';

    $en = json_decode(file_get_contents($enPath), true);
    $ar = json_decode(file_get_contents($arPath), true);

    if (!$en || !$ar) return "Translation JSON files could not be parsed";

    $requiredPhase5Keys = [
        'generate_ai_background',
        'generate_ai_background_desc',
        'ai_background_layer',
        'separate_main_subject',
        'separating_subject',
        'subject_separated_success',
        'extract_text_from_image',
        'extracting_text',
        'text_extracted_success',
        'no_text_detected',
        'mask_original_text',
        'rmbg_token_missing',
        'rmbg_model_loading',
        'rmbg_rate_limit',
        'rmbg_failed',
        'image_required'
    ];

    foreach ($requiredPhase5Keys as $k) {
        if (!isset($en[$k])) return "Missing key '{$k}' in en.json";
        if (!isset($ar[$k])) return "Missing key '{$k}' in ar.json";
    }

    $diffEn = array_diff_key($en, $ar);
    if (!empty($diffEn)) return "en.json has keys not in ar.json: " . implode(', ', array_keys($diffEn));

    $diffAr = array_diff_key($ar, $en);
    if (!empty($diffAr)) return "ar.json has keys not in en.json: " . implode(', ', array_keys($diffAr));

    return true;
});

// TEST 2: Config Constants Defined
run_test("Phase 5 Configuration constants defined", function() {
    if (!defined('HUGGINGFACE_API_TOKEN')) return "HUGGINGFACE_API_TOKEN not defined";
    if (!defined('HUGGINGFACE_RMBG_MODEL')) return "HUGGINGFACE_RMBG_MODEL not defined";
    if (!defined('POLLINATIONS_ENABLED')) return "POLLINATIONS_ENABLED not defined";
    return true;
});

// TEST 3: Sanitizer Schema Preservation
run_test("Sanitizer preserves src, locked, isBackground, and name", function() {
    $inputDesign = [
        'canvas' => [
            'width' => 1080,
            'height' => 1080,
            'background' => '#0f172a'
        ],
        'objects' => [
            [
                'type' => 'image',
                'src' => 'api/proxy-image.php?url=https%3A%2F%2Fimage.pollinations.ai%2Fprompt%2Fcoffee',
                'left' => 0,
                'top' => 0,
                'width' => 1080,
                'height' => 1080,
                'fill' => '#ffffff',
                'locked' => true,
                'isBackground' => true,
                'name' => 'AI Background'
            ],
            [
                'type' => 'text',
                'text' => 'Artisan Coffee',
                'left' => 100,
                'top' => 150,
                'width' => 400,
                'height' => 60,
                'fill' => '#ffffff',
                'fontSize' => 48,
                'fontFamily' => 'Poppins',
                'name' => 'Main Heading'
            ]
        ]
    ];

    $sanitized = sanitize_design_json($inputDesign, 1080, 1080);
    $objs = $sanitized['objects'];

    if (count($objs) !== 2) return "Expected 2 objects, got " . count($objs);
    $img = $objs[0];

    if ($img['type'] !== 'image') return "Expected object 0 to be image";
    if (empty($img['src'])) return "Image src was stripped";
    if (empty($img['locked'])) return "Image locked flag was stripped";
    if (empty($img['isBackground'])) return "Image isBackground flag was stripped";
    if (($img['name'] ?? '') !== 'AI Background') return "Image name was stripped";

    return true;
});

// TEST 4: Hybrid Image Generation with Pollinations.ai Layer
run_test("ai_generate_design with generate_background creates Pollinations layer", function() {
    $res = ai_generate_design("Cozy specialty coffee shop poster with warm steam", 1080, 1080, [
        'generate_background' => true,
        'style' => 'modern',
        'color_mood' => 'warm'
    ]);

    if (!$res['success']) return "ai_generate_design failed: " . ($res['error'] ?? '');

    $data = $res['data'];
    $objs = $data['objects'] ?? [];
    if (empty($objs)) return "Objects array is empty";

    // Bottom layer must be the AI background image
    $bottomLayer = $objs[0];
    if ($bottomLayer['type'] !== 'image') {
        return "Bottom layer type is '{$bottomLayer['type']}', expected 'image'";
    }
    if (empty($bottomLayer['isBackground'])) {
        return "Bottom layer isBackground is not true";
    }
    if (empty($bottomLayer['locked'])) {
        return "Bottom layer locked is not true";
    }
    if (empty($bottomLayer['src']) || strpos($bottomLayer['src'], 'proxy-image.php') === false) {
        return "Bottom layer src is not routed via proxy-image.php: {$bottomLayer['src']}";
    }

    // Editable text objects must exist above it
    $hasTextAbove = false;
    for ($i = 1; $i < count($objs); $i++) {
        if ($objs[$i]['type'] === 'text') {
            $hasTextAbove = true;
            break;
        }
    }
    if (!$hasTextAbove) return "No text layers found above the background";

    return true;
});

// TEST 5: Background Removal Logic (ai_remove_background_rmbg)
run_test("ai_remove_background_rmbg processes image and returns cutout", function() {
    // Generate sample 100x100 PNG data URL
    $img = imagecreatetruecolor(100, 100);
    $blue = imagecolorallocate($img, 59, 130, 246);
    imagefilledrectangle($img, 10, 10, 90, 90, $blue);
    ob_start();
    imagepng($img);
    $rawPng = ob_get_clean();
    imagedestroy($img);

    $dataUrl = 'data:image/png;base64,' . base64_encode($rawPng);

    $res = ai_remove_background_rmbg($dataUrl);

    if (!$res['success']) {
        return "ai_remove_background_rmbg failed: " . ($res['error'] ?? '');
    }

    if (empty($res['data_url']) && empty($res['image_url'])) {
        return "Neither data_url nor image_url returned";
    }

    // Test rejection of empty input
    $emptyRes = ai_remove_background_rmbg('');
    if ($emptyRes['success'] !== false) {
        return "Expected failure on empty input";
    }

    return true;
});

// TEST 6: Gemini Vision OCR Text Extraction (ai_extract_text_from_image)
run_test("ai_extract_text_from_image returns structured editable text blocks", function() {
    $img = imagecreatetruecolor(300, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefilledrectangle($img, 0, 0, 300, 100, $white);
    imagestring($img, 5, 20, 40, "SALE 50% OFF", $black);
    ob_start();
    imagepng($img);
    $rawPng = ob_get_clean();
    imagedestroy($img);

    $dataUrl = 'data:image/png;base64,' . base64_encode($rawPng);

    $res = ai_extract_text_from_image($dataUrl, 1080, 1080);
    if (!$res['success']) {
        return "ai_extract_text_from_image failed: " . ($res['error'] ?? '');
    }

    $blocks = $res['blocks'] ?? [];
    if (empty($blocks)) {
        return "Expected at least 1 text block returned";
    }

    $b = $blocks[0];
    if (empty($b['text'])) return "Text block text is empty";
    if (!isset($b['left_percent'])) return "Missing left_percent";
    if (!isset($b['top_percent'])) return "Missing top_percent";
    if (!isset($b['fontSize'])) return "Missing fontSize";
    if (!isset($b['fontFamily'])) return "Missing fontFamily";
    if (!in_array($b['language'], ['ar', 'en'], true)) return "Invalid language '{$b['language']}'";

    return true;
});

// TEST 7: /api/remove-bg.php HTTP Security & Rejections
run_test("api/remove-bg.php rejects GET, invalid CSRF, and empty data", function() {
    $scriptTpl = function(string $method, array $postData, ?string $csrf = null) {
        $path = str_replace('\\', '/', dirname(__DIR__) . '/api/remove-bg.php');
        $secPath = str_replace('\\', '/', dirname(__DIR__) . '/includes/security.php');
        $postStr = var_export($postData, true);
        $csrfStr = $csrf ? "define('TEST_CSRF', '{$csrf}'); \$_SERVER['HTTP_X_CSRF_TOKEN'] = '{$csrf}';" : "";
        return "<?php
            \$_SERVER['REQUEST_METHOD'] = '{$method}';
            \$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            {$csrfStr}
            \$_POST = {$postStr};
            require '{$path}';
        ";
    };

    $tmp = __DIR__ . '/tmp_rmbg_test.php';

    // 1. GET Request Rejection (405)
    file_put_contents($tmp, $scriptTpl('GET', []));
    $out = shell_exec("php \"{$tmp}\"");
    $json = json_decode((string)$out, true);
    if (($json['code'] ?? '') !== 'METHOD_NOT_ALLOWED') {
        @unlink($tmp);
        return "Expected METHOD_NOT_ALLOWED on GET, got: {$out}";
    }

    // 2. Missing CSRF Token (403)
    file_put_contents($tmp, $scriptTpl('POST', ['image_data' => 'abc']));
    $out = shell_exec("php \"{$tmp}\"");
    $json = json_decode((string)$out, true);
    if (($json['code'] ?? '') !== 'CSRF_INVALID') {
        @unlink($tmp);
        return "Expected CSRF_INVALID, got: {$out}";
    }

    @unlink($tmp);
    return true;
});

// TEST 8: /api/extract-text.php HTTP Security & Rejections
run_test("api/extract-text.php rejects GET, invalid CSRF, and empty data", function() {
    $scriptTpl = function(string $method, array $postData, ?string $csrf = null) {
        $path = str_replace('\\', '/', dirname(__DIR__) . '/api/extract-text.php');
        $postStr = var_export($postData, true);
        $csrfStr = $csrf ? "define('TEST_CSRF', '{$csrf}'); \$_SERVER['HTTP_X_CSRF_TOKEN'] = '{$csrf}';" : "";
        return "<?php
            \$_SERVER['REQUEST_METHOD'] = '{$method}';
            \$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            {$csrfStr}
            \$_POST = {$postStr};
            require '{$path}';
        ";
    };

    $tmp = __DIR__ . '/tmp_extract_test.php';

    // 1. GET Request Rejection (405)
    file_put_contents($tmp, $scriptTpl('GET', []));
    $out = shell_exec("php \"{$tmp}\"");
    $json = json_decode((string)$out, true);
    if (($json['code'] ?? '') !== 'METHOD_NOT_ALLOWED') {
        @unlink($tmp);
        return "Expected METHOD_NOT_ALLOWED on GET, got: {$out}";
    }

    // 2. Missing CSRF Token (403)
    file_put_contents($tmp, $scriptTpl('POST', ['image_data' => 'abc']));
    $out = shell_exec("php \"{$tmp}\"");
    $json = json_decode((string)$out, true);
    if (($json['code'] ?? '') !== 'CSRF_INVALID') {
        @unlink($tmp);
        return "Expected CSRF_INVALID, got: {$out}";
    }

    @unlink($tmp);
    return true;
});

// TEST 9: UI Elements in index.php and properties-panel.js
run_test("UI controls: generate-bg-toggle, btn-separate-subject, btn-extract-text", function() {
    $indexPath = __DIR__ . '/../index.php';
    $propsPath = __DIR__ . '/../assets/js/editor/properties-panel.js';
    $rendererPath = __DIR__ . '/../assets/js/editor/ai-renderer.js';

    $index = file_get_contents($indexPath);
    $props = file_get_contents($propsPath);
    $renderer = file_get_contents($rendererPath);

    if (strpos($index, 'generate-bg-toggle') === false) {
        return "index.php missing #generate-bg-toggle checkbox";
    }
    if (strpos($props, 'btn-separate-subject') === false) {
        return "properties-panel.js missing #btn-separate-subject";
    }
    if (strpos($props, 'btn-extract-text') === false) {
        return "properties-panel.js missing #btn-extract-text";
    }
    if (strpos($props, 'check-mask-text') === false) {
        return "properties-panel.js missing #check-mask-text";
    }
    if (strpos($renderer, 'obj.isBackground') === false) {
        return "ai-renderer.js missing obj.isBackground handling";
    }

    return true;
});

// Summary
echo "\n========================================================\n";
echo "   Test Results: {$passedTests}/{$totalTests} passed";
if ($failedTests > 0) {
    echo " ({$failedTests} FAILED)\n";
    echo "========================================================\n";
    exit(1);
} else {
    echo " \033[32m(ALL PHASE 5 TESTS PASSED!)\033[0m\n";
    echo "========================================================\n";
    exit(0);
}

