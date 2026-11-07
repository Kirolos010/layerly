<?php
/**
 * AI Design Studio - Automated Phase 1 Test Suite
 */

define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/rate-limiter.php';
require_once dirname(__DIR__) . '/includes/sanitizer.php';
require_once dirname(__DIR__) . '/includes/ai-client.php';

$testCount = 0;
$passCount = 0;
$failCount = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $testCount, $passCount, $failCount;
    $testCount++;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$name}" . ($detail ? " - {$detail}" : "") . "\n";
    }
}

echo "====================================================\n";
echo " AI DESIGN STUDIO - PHASE 1 AUTOMATED TEST SUITE\n";
echo "====================================================\n\n";

// TEST 1: CSRF & Security
echo "[1] Testing Security & CSRF System:\n";
init_secure_session();
$token = get_csrf_token();
assert_test("CSRF Token generated", !empty($token) && strlen($token) === 64, "Token: " . substr($token, 0, 10) . "...");
assert_test("CSRF Token validation passes with correct token", validate_csrf_token($token));
assert_test("CSRF Token validation rejects invalid token", !validate_csrf_token("invalid_token_12345"));
assert_test("Arabic text detection works for Arabic", is_arabic_text("تصميم عروض رمضان"));
assert_test("Arabic text detection rejects pure English", !is_arabic_text("Ramadan Sale 50% Off"));
assert_test("HTML escaping prevents XSS", e('<script>alert("xss")</script>') === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');

// TEST 2: Database Connection
echo "\n[2] Testing Database & Tables:\n";
try {
    $db = get_db();
    assert_test("Database PDO connection successful", $db instanceof PDO);
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    assert_test("Table 'projects' exists", in_array('projects', $tables, true));
    assert_test("Table 'rate_limits' exists", in_array('rate_limits', $tables, true));
} catch (Exception $e) {
    assert_test("Database connected", false, $e->getMessage());
}

// TEST 3: Rate Limiter
echo "\n[3] Testing Rate Limiter per IP in MySQL:\n";
$testIp = '192.168.100.' . rand(10, 250);
// Reset any existing test records
db_query("DELETE FROM rate_limits WHERE ip = ?", [$testIp]);

// First request should be allowed
$r1 = check_rate_limit($testIp);
assert_test("Rate limit initial request allowed", $r1['allowed'] === true, "Minute count: {$r1['minute_count']}");

// Simulate requests up to the minute limit (10)
for ($i = 2; $i <= 10; $i++) {
    $r = check_rate_limit($testIp);
}
assert_test("Rate limit request #10 allowed", $r['allowed'] === true, "Minute count: {$r['minute_count']}");

// Request #11 should be blocked (exceeded 10/min)
$r11 = check_rate_limit($testIp);
assert_test("Rate limit request #11 blocked (10/min exceeded)", $r11['allowed'] === false && $r11['reason'] === 'minute', "Retry after: {$r11['retry_after']}s");

// Clean up test IP
db_query("DELETE FROM rate_limits WHERE ip = ?", [$testIp]);

// TEST 4: Sanitizer & Clamping
echo "\n[4] Testing Schema Sanitizer & Clamping:\n";
$rawPayload = [
    'canvas' => [
        'width' => 999999, // Should clamp to 4000
        'height' => 10,    // Should clamp to 100
        'background' => 'invalid_color_rgb', // Should sanitize
        'injected_key' => 'evil' // Should be stripped
    ],
    'objects' => [
        [
            'type' => 'text',
            'text' => 'عرض خاص وحصري بمناسبة الافتتاح',
            'left' => 100.555,
            'top' => 200.777,
            'width' => 500,
            'height' => 80,
            'fill' => '#1e293b',
            'fontFamily' => 'UnknownFont123', // Should fallback to Cairo for Arabic
            'fontSize' => 550, // Should clamp to 300
            'direction' => 'unknown', // Should auto-detect rtl for Arabic
            'unknown_field' => 'strip_me'
        ],
        [
            'type' => 'malicious_type', // Should be omitted completely
            'text' => 'Hacker Element'
        ],
        [
            'type' => 'rect',
            'left' => 50,
            'top' => 50,
            'width' => 300,
            'height' => 150,
            'fill' => '#3b82f6',
            'rx' => 15,
            'opacity' => 1.5 // Should clamp to 1.0
        ]
    ]
];

$sanitized = sanitize_design_json($rawPayload, 1080, 1080);
assert_test("Canvas width clamped to 4000", $sanitized['canvas']['width'] === 4000);
assert_test("Canvas height clamped to 100", $sanitized['canvas']['height'] === 100);
assert_test("Canvas injected_key stripped", !isset($sanitized['canvas']['injected_key']));
assert_test("Disallowed object type omitted", count($sanitized['objects']) === 2);
assert_test("Arabic text font fallback to Cairo", $sanitized['objects'][0]['fontFamily'] === 'Cairo');
assert_test("Arabic text direction auto-set to rtl", $sanitized['objects'][0]['direction'] === 'rtl');
assert_test("Text fontSize clamped to 300", $sanitized['objects'][0]['fontSize'] === 300.0);
assert_test("Unknown field stripped from object", !isset($sanitized['objects'][0]['unknown_field']));
assert_test("Opacity clamped to 1.0", $sanitized['objects'][1]['opacity'] === 1.0);

// TEST 5: AI Enhance Function (Arabic & English)
echo "\n[5] Testing AI Enhance (Arabic & English):\n";
$arIdea = "إعلان تخفيضات ملابس صيفية بأسلوب عصري";
$arEnhanceRes = ai_enhance_prompt($arIdea, 'ar');
assert_test("AI Enhance Arabic idea succeeds", $arEnhanceRes['success'] === true);
assert_test("AI Enhance Arabic detected_lang is 'ar'", ($arEnhanceRes['detected_lang'] ?? '') === 'ar');
assert_test("AI Enhance returns detailed brief", mb_strlen($arEnhanceRes['enhanced_prompt']) > 100, "Length: " . mb_strlen($arEnhanceRes['enhanced_prompt']) . " chars");

$enIdea = "Minimalist luxury perfume sale with gold accents";
$enEnhanceRes = ai_enhance_prompt($enIdea, 'en');
assert_test("AI Enhance English idea succeeds", $enEnhanceRes['success'] === true);
assert_test("AI Enhance English detected_lang is 'en'", ($enEnhanceRes['detected_lang'] ?? '') === 'en');
assert_test("AI Enhance returns English brief", strlen($enEnhanceRes['enhanced_prompt']) > 100, "Length: " . strlen($enEnhanceRes['enhanced_prompt']) . " chars");

// TEST 6: AI Generate Design (Arabic & English)
echo "\n[6] Testing AI Generate Design (Arabic & English):\n";
$arGen = ai_generate_design("بوست انستقرام لمطعم برجر مع خصم 30%", 1080, 1080, [
    'design_type' => 'social_post',
    'style'       => 'modern',
    'color_mood'  => 'warm_sunset',
    'language'    => 'ar'
]);

assert_test("AI Generate Arabic design succeeds", $arGen['success'] === true);
assert_test("Generated canvas width is 1080", $arGen['data']['canvas']['width'] === 1080);
assert_test("Generated canvas height is 1080", $arGen['data']['canvas']['height'] === 1080);
assert_test("Objects array has items", count($arGen['data']['objects']) >= 5, "Count: " . count($arGen['data']['objects']));

// Verify all objects conform to schema
$allObjectsValid = true;
$hasArabicFont = false;
$hasRtl = false;
foreach ($arGen['data']['objects'] as $obj) {
    if (!in_array($obj['type'], ['text', 'rect', 'circle', 'triangle', 'line', 'image'], true)) {
        $allObjectsValid = false;
    }
    if (in_array($obj['fontFamily'], ['Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi'], true)) {
        $hasArabicFont = true;
    }
    if ($obj['direction'] === 'rtl') {
        $hasRtl = true;
    }
}
assert_test("All objects conform to schema whitelist", $allObjectsValid);
assert_test("Arabic fonts used for Arabic design", $hasArabicFont);
assert_test("RTL direction applied for Arabic elements", $hasRtl);

$enGen = ai_generate_design("Tech conference banner in San Francisco with modern neon theme", 1200, 630, [
    'design_type' => 'banner',
    'style'       => 'bold',
    'color_mood'  => 'vibrant',
    'language'    => 'en'
]);
assert_test("AI Generate English design succeeds", $enGen['success'] === true);
assert_test("English canvas size matches requested (1200x630)", $enGen['data']['canvas']['width'] === 1200 && $enGen['data']['canvas']['height'] === 630);

// TEST 7: AI Edit Design
echo "\n[7] Testing AI Edit Design:\n";
$editRes = ai_edit_design($arGen['data'], "كبّر حجم العنوان واجعل لون الخلفية كحلي داكن");
assert_test("AI Edit design succeeds", $editRes['success'] === true);
assert_test("Edited design retains valid canvas and objects", !empty($editRes['data']['canvas']) && !empty($editRes['data']['objects']));

// TEST 8: Multilingual Translations
echo "\n[8] Testing Translations (ar/en):\n";
assert_test("Translation ar returns Arabic text", t('rate_limit_minute', 'ar') !== 'rate_limit_minute' && is_arabic_text(t('rate_limit_minute', 'ar')));
assert_test("Translation en returns English text", t('rate_limit_minute', 'en') !== 'rate_limit_minute' && !is_arabic_text(t('rate_limit_minute', 'en')));

echo "\n====================================================\n";
echo " TEST SUMMARY: {$passCount}/{$testCount} Passed (" . round(($passCount / $testCount) * 100) . "%)\n";
if ($failCount > 0) {
    echo " FAILED: {$failCount} tests failed!\n";
} else {
    echo " ALL TESTS PASSED SUCCESSFULLY! 🎉\n";
}
echo "====================================================\n";

exit($failCount === 0 ? 0 : 1);
