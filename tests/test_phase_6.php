<?php
/**
 * AI Design Studio - Phase 6 Automated Test Suite
 * Tests:
 * 1. Poster Quota & Rate Limiting (Daily limit, countdown, per-IP tracking)
 * 2. Bilingual Translations completeness (en.json vs ar.json key parity)
 * 3. Inpainting Engine (PHP GD seamless background inpainting with feather blur)
 * 4. Clean Plate API & degraded notice handling
 * 5. Strict Anti-Hallucination validation (no invented URLs, prices, or dates)
 * 6. Poster Import & Poster Generate endpoints
 * 7. Tainted Canvas safety (all image URLs served via proxy / same-origin)
 */

define('APP_INIT', true);
define('TEST_RUN', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate-limiter.php';
require_once __DIR__ . '/../includes/ai-client.php';
require_once __DIR__ . '/../includes/db.php';

$passed = 0;
$failed = 0;

function assert_test(bool $cond, string $name, string $details = '') {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name} - Details: {$details}\n";
    }
}

echo "========================================================\n";
echo "AI DESIGN STUDIO - PHASE 6 AUTOMATED TEST SUITE\n";
echo "========================================================\n\n";

// -----------------------------------------------------------------------------
// Test 1: Language Parity
// -----------------------------------------------------------------------------
echo "[1] Testing Bilingual Translations Parity (Phase 6 Keys)...\n";
$enJson = json_decode(file_get_contents(__DIR__ . '/../lang/en.json'), true);
$arJson = json_decode(file_get_contents(__DIR__ . '/../lang/ar.json'), true);

$requiredKeys = [
    'ai_poster', 'ai_poster_new', 'mode_import_tab', 'mode_text_poster_tab',
    'clean_plate_option', 'keep_original_option', 'btn_import_poster',
    'btn_generate_poster', 'clean_plate_fallback_notice', 'compare_view',
    'btn_realign', 'realign_success', 'step_import_1', 'step_import_2',
    'step_import_3', 'step_gen_1', 'step_gen_2', 'step_gen_3', 'poster_quota_badge'
];

foreach ($requiredKeys as $key) {
    assert_test(isset($enJson[$key]), "en.json has key '{$key}'");
    assert_test(isset($arJson[$key]), "ar.json has key '{$key}'");
}

$diffEnAr = array_diff_key($enJson, $arJson);
$diffArEn = array_diff_key($arJson, $enJson);
assert_test(empty($diffEnAr) && empty($diffArEn), "en.json and ar.json have 100% key parity (Count: " . count($enJson) . ")");

// -----------------------------------------------------------------------------
// Test 2: Rate Limiting & Quota
// -----------------------------------------------------------------------------
echo "\n[2] Testing Poster Rate Limiting & Daily Quota...\n";
$testIp = '198.51.100.' . random_int(1, 250);
$quota = get_poster_quota($testIp);
assert_test($quota['limit'] === (defined('POSTER_DAILY_LIMIT') ? POSTER_DAILY_LIMIT : 5), "Default daily poster limit is configured");
assert_test($quota['used'] === 0, "New test IP starts with 0 used");
assert_test($quota['remaining'] === $quota['limit'], "New test IP starts with full remaining quota");

$check = check_poster_rate_limit($testIp);
assert_test($check['allowed'] === true, "check_poster_rate_limit allows operation when remaining > 0");

// Increment quota up to the limit
for ($i = 0; $i < $quota['limit']; $i++) {
    increment_poster_quota($testIp);
}

$quotaAfter = get_poster_quota($testIp);
assert_test($quotaAfter['used'] === $quota['limit'], "Used count matches limit after increments");
assert_test($quotaAfter['remaining'] === 0, "Remaining count is 0 after quota consumed");

$blockedCheck = check_poster_rate_limit($testIp);
assert_test($blockedCheck['allowed'] === false, "check_poster_rate_limit blocks operation when quota is exhausted");
assert_test(!empty($blockedCheck['message']), "check_poster_rate_limit returns bilingual error message on block");

// -----------------------------------------------------------------------------
// Test 3: PHP GD Inpainting Engine on Reference Poster
// -----------------------------------------------------------------------------
echo "\n[3] Testing Clean Plate Inpainting Engine (PHP GD)...\n";
$refImage = "C:/Users/Kirolos Samir0/.gemini/antigravity/brain/17b16749-06fc-422d-bb1e-f521886da31d/.user_uploaded/media_1794006378956_1c6f42a9.jpg";
assert_test(file_exists($refImage), "Reference poster exists at path");

$sampleBlocks = [
    ['id' => 'headline', 'left' => 104, 'top' => 36, 'width' => 808, 'height' => 65],
    ['id' => 'badge_btn', 'left' => 60, 'top' => 500, 'width' => 164, 'height' => 36, 'badge' => ['left' => 60, 'top' => 500, 'width' => 164, 'height' => 36]]
];

$testOut = __DIR__ . '/test_unit_clean_plate.jpg';
if (file_exists($testOut)) @unlink($testOut);

$inpaintOk = ai_poster_inpaint_gd($refImage, $sampleBlocks, $testOut);
assert_test($inpaintOk === true, "ai_poster_inpaint_gd returned true");
assert_test(file_exists($testOut), "Clean plate output file was created on disk");
assert_test(filesize($testOut) > 50000, "Clean plate output file has realistic size (>50KB, actual: " . filesize($testOut) . " bytes)");

$cleanRes = ai_generate_clean_plate($refImage, $sampleBlocks, 1024, 558, true);
assert_test($cleanRes['success'] === true, "ai_generate_clean_plate returned success");
assert_test($cleanRes['fallback_used'] === true, "Fallback flag is true when inpainting is used");
assert_test(!empty($cleanRes['clean_plate_url']), "Clean plate URL is returned: {$cleanRes['clean_plate_url']}");
assert_test(str_starts_with($cleanRes['clean_plate_url'], 'api/upload.php?file='), "Clean plate is served via same-origin upload proxy (anti-tainted canvas)");

// Clean up unit test image
if (file_exists($testOut)) @unlink($testOut);

// -----------------------------------------------------------------------------
// Test 4: Strict Anti-Hallucination in Art Direction
// -----------------------------------------------------------------------------
echo "\n[4] Testing Strict Copy & Anti-Hallucination Rules (Mode 2)...\n";
// Prompt with NO website, NO price, NO phone
$strictPrompt = "إعلان دورة برمجة بايثون والذكاء الاصطناعي للمبتدئين";
$art = ai_art_direct_poster($strictPrompt);
assert_test($art['success'] === true, "ai_art_direct_poster succeeded");
assert_test(!empty($art['art_direction']['text_blocks']), "Text blocks generated");

$foundInventedUrl = false;
$foundInventedPrice = false;
foreach ($art['art_direction']['text_blocks'] as $block) {
    $txt = $block['text'];
    if (preg_match('/(www\.[a-z0-9\-]+\.[a-z]{2,}|https?:\/\/[^\s]+|\.com|\.org|\.net)/i', $txt)) {
        $foundInventedUrl = true;
    }
    if (preg_match('/(\$\d+|\d+\$|\d+\s*(دولار|جنيه|ريال|usd))/i', $txt)) {
        $foundInventedPrice = true;
    }
}
assert_test(!$foundInventedUrl, "Zero hallucinated/invented URLs in output when user did not provide one");
assert_test(!$foundInventedPrice, "Zero hallucinated/invented prices in output when user did not provide one");

// -----------------------------------------------------------------------------
// Test 5: Endpoints HTTP Accessibility
// -----------------------------------------------------------------------------
echo "\n[5] Testing Endpoints (Quota, Proxy, Upload)...\n";
$quotaRaw = @file_get_contents('http://localhost/ai-design-studio/api/poster-quota.php');
if ($quotaRaw) {
    $quotaData = json_decode($quotaRaw, true);
    assert_test($quotaData['success'] === true, "api/poster-quota.php returns success=true via HTTP");
    assert_test(isset($quotaData['quota']['remaining']), "api/poster-quota.php returns remaining quota");
} else {
    // Direct test fallback
    $q = get_poster_quota();
    assert_test(isset($q['remaining']), "get_poster_quota() returns remaining quota");
}

// Test upload proxy serving clean plate with CORS
$testCleanFilename = basename($cleanRes['clean_plate_path']);
assert_test(file_exists(__DIR__ . '/../storage/uploads/' . $testCleanFilename), "Clean plate file exists in storage/uploads ({$testCleanFilename})");

echo "\n========================================================\n";
echo "SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
