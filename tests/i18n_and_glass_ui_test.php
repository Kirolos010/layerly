<?php
/**
 * AI Design Studio - Phase 4 Automated Test Suite
 * 
 * Verifies:
 * 1. Bilingual JSON dictionaries (en.json, ar.json) validity & complete key parity.
 * 2. PHP translation array fallback (en.php, ar.php).
 * 3. Server-side i18n functions (get_current_lang, t, placeholder replacement).
 * 4. Bilingual API endpoint (/api/i18n.php) responses for Arabic and English.
 * 5. Liquid Glass CSS tokens, Dark/Light theme variables, RTL logical properties.
 * 6. New pages (about.php, 404.php) structure, status codes, and elements.
 * 7. Unified theme toggle and zero-flash script integration across all studio pages.
 */

define('APP_INIT', true);

require_once __DIR__ . '/../includes/security.php';
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
echo "   AI Design Studio - Phase 4 Bilingual & Glass Suite   \n";
echo "========================================================\n\n";

// TEST 1: en.json and ar.json validity and key parity
run_test("Bilingual JSON dictionaries validity & key parity", function() {
    $enPath = __DIR__ . '/../lang/en.json';
    $arPath = __DIR__ . '/../lang/ar.json';
    
    if (!file_exists($enPath) || !file_exists($arPath)) {
        return "Translation JSON files missing";
    }

    $enData = json_decode(file_get_contents($enPath), true);
    $arData = json_decode(file_get_contents($arPath), true);

    if (!is_array($enData) || json_last_error() !== JSON_ERROR_NONE) {
        return "en.json is invalid JSON: " . json_last_error_msg();
    }
    if (!is_array($arData) || json_last_error() !== JSON_ERROR_NONE) {
        return "ar.json is invalid JSON: " . json_last_error_msg();
    }

    $enCount = count($enData);
    $arCount = count($arData);

    if ($enCount < 230) {
        return "en.json has fewer than 230 keys (found {$enCount})";
    }
    if ($arCount < 230) {
        return "ar.json has fewer than 230 keys (found {$arCount})";
    }

    $missingInAr = array_diff_key($enData, $arData);
    if (!empty($missingInAr)) {
        return "Keys in en.json missing from ar.json: " . implode(', ', array_keys($missingInAr));
    }

    $missingInEn = array_diff_key($arData, $enData);
    if (!empty($missingInEn)) {
        return "Keys in ar.json missing from en.json: " . implode(', ', array_keys($missingInEn));
    }

    return true;
});

// TEST 2: PHP fallback dictionaries (en.php and ar.php)
run_test("PHP translation arrays parity and synchronization", function() {
    $enPhp = require __DIR__ . '/../lang/en.php';
    $arPhp = require __DIR__ . '/../lang/ar.php';

    if (!is_array($enPhp) || !is_array($arPhp)) {
        return "en.php or ar.php does not return an array";
    }

    $diff = array_diff_key($enPhp, $arPhp);
    if (!empty($diff)) {
        return "Keys in en.php missing from ar.php: " . implode(', ', array_keys($diff));
    }

    return true;
});

// TEST 3: Server-side translation function `t()` and placeholder replacement
run_test("Translation function t() and parameter interpolation", function() {
    $appNameEn = t('app_name', 'en');
    $appNameAr = t('app_name', 'ar');

    if ($appNameEn !== 'AI Design Studio') {
        return "Expected 'AI Design Studio' for English, got: {$appNameEn}";
    }
    if (empty($appNameAr) || $appNameAr === 'app_name') {
        return "Expected Arabic app name, got: {$appNameAr}";
    }

    // Test parameter replacement
    $interpolated = t('guest_migrated_notice', 'en', ['count' => 5]);
    if (strpos($interpolated, '5') === false) {
        return "Expected '5' in interpolated string, got: {$interpolated}";
    }

    // Test unknown key fallback
    $unknown = t('totally_non_existent_key_xyz_123', 'en');
    if ($unknown !== 'totally_non_existent_key_xyz_123') {
        return "Expected raw key fallback for unknown key, got: {$unknown}";
    }

    return true;
});

// TEST 4: Language detection logic
run_test("get_current_lang() detection precedence", function() {
    // 1. Explicit GET parameter
    $_GET['lang'] = 'ar';
    $lang = get_current_lang();
    if ($lang !== 'ar') {
        return "Expected 'ar' from \$_GET, got: {$lang}";
    }

    $_GET['lang'] = 'en';
    $lang = get_current_lang();
    if ($lang !== 'en') {
        return "Expected 'en' from \$_GET, got: {$lang}";
    }
    unset($_GET['lang']);

    // 2. Session
    $_SESSION['lang'] = 'ar';
    $lang = get_current_lang();
    if ($lang !== 'ar') {
        return "Expected 'ar' from \$_SESSION, got: {$lang}";
    }
    unset($_SESSION['lang']);

    return true;
});

// TEST 5: API Endpoint `/api/i18n.php`
run_test("API Endpoint /api/i18n.php response for ar and en", function() {
    $scriptTpl = function(string $lang) {
        $path = str_replace('\\', '/', dirname(__DIR__) . '/api/i18n.php');
        return "<?php \$_GET['lang'] = '{$lang}'; require '{$path}';";
    };

    $tmpFile = __DIR__ . '/tmp_i18n_runner.php';

    // Test ar
    file_put_contents($tmpFile, $scriptTpl('ar'));
    $arOutput = shell_exec("php \"{$tmpFile}\"");
    $arRes = json_decode((string)$arOutput, true);

    if (!$arRes || !($arRes['success'] ?? false)) {
        @unlink($tmpFile);
        return "Invalid response from api/i18n.php?lang=ar: {$arOutput}";
    }
    if ($arRes['lang'] !== 'ar' || $arRes['dir'] !== 'rtl') {
        @unlink($tmpFile);
        return "Expected lang=ar, dir=rtl; got lang={$arRes['lang']}, dir={$arRes['dir']}";
    }
    if (!isset($arRes['strings']['app_name'])) {
        @unlink($tmpFile);
        return "Missing 'app_name' translation in ar payload";
    }

    // Test en
    file_put_contents($tmpFile, $scriptTpl('en'));
    $enOutput = shell_exec("php \"{$tmpFile}\"");
    $enRes = json_decode((string)$enOutput, true);
    @unlink($tmpFile);

    if (!$enRes || !($enRes['success'] ?? false)) {
        return "Invalid response from api/i18n.php?lang=en: {$enOutput}";
    }
    if ($enRes['lang'] !== 'en' || $enRes['dir'] !== 'ltr') {
        return "Expected lang=en, dir=ltr; got lang={$enRes['lang']}, dir={$enRes['dir']}";
    }
    if (!isset($enRes['strings']['app_name'])) {
        return "Missing 'app_name' translation in en payload";
    }

    return true;
});

// TEST 6: Liquid Glass CSS System validation
run_test("Liquid Glass CSS tokens, theme variables & RTL properties", function() {
    $cssPath = __DIR__ . '/../assets/css/style.css';
    if (!file_exists($cssPath)) {
        return "style.css not found";
    }
    $css = file_get_contents($cssPath);

    $requiredTokens = [
        ':root',
        '[data-theme="dark"]',
        '[data-theme="light"]',
        '--glass-bg',
        '--glass-blur',
        '--glass-border',
        '--glass-specular',
        '--glass-shadow',
        'backdrop-filter:',
        '.glass-mesh-backdrop',
        '.mesh-orb',
        'body.canvas-interacting',
        '[dir="rtl"]',
        'margin-inline-start',
        'padding-inline',
        '.auth-card',
        '.history-card'
    ];

    foreach ($requiredTokens as $token) {
        if (strpos($css, $token) === false) {
            return "style.css is missing required token or rule: '{$token}'";
        }
    }

    return true;
});

// TEST 7: Page existence, zero-flash script and theme toggle button
run_test("Page consistency: theme script, mesh backdrop & theme toggle", function() {
    $pages = [
        'index.php',
        'projects.php',
        'templates.php',
        'about.php',
        '404.php',
        'history.php',
        'login.php',
        'register.php',
        'profile.php',
        'forgot-password.php',
        'reset-password.php'
    ];

    foreach ($pages as $page) {
        $filePath = __DIR__ . '/../' . $page;
        if (!file_exists($filePath)) {
            return "Required page missing: {$page}";
        }
        $content = file_get_contents($filePath);

        if (strpos($content, 'data-theme="dark"') === false) {
            return "Page {$page} is missing data-theme=\"dark\" default attribute";
        }
        if (strpos($content, 'design_studio_theme') === false) {
            return "Page {$page} is missing zero-flash theme initializer script in <head>";
        }
        if (strpos($content, 'glass-mesh-backdrop') === false) {
            return "Page {$page} is missing .glass-mesh-backdrop container";
        }
        if ($page !== '404.php' && strpos($content, 'theme-toggle') === false) {
            return "Page {$page} is missing theme-toggle button";
        }
    }

    return true;
});

// TEST 8: about.php content & structure
run_test("about.php structure and feature cards", function() {
    $aboutPath = __DIR__ . '/../about.php';
    $content = file_get_contents($aboutPath);

    $expectedSnippets = [
        'about_title',
        'about_tagline',
        'about_feature_ai_title',
        'about_feature_rtl_title',
        'about_feature_glass_title',
        'about_feature_export_title',
        'ThemeManager.init()'
    ];

    foreach ($expectedSnippets as $snippet) {
        if (strpos($content, $snippet) === false) {
            return "about.php is missing required snippet: '{$snippet}'";
        }
    }

    return true;
});

// TEST 9: 404.php content & HTTP status code
run_test("404.php structure and 404 status header", function() {
    $notFoundPath = __DIR__ . '/../404.php';
    $content = file_get_contents($notFoundPath);

    if (strpos($content, 'http_response_code(404)') === false) {
        return "404.php does not set http_response_code(404)";
    }
    if (strpos($content, 'page_not_found') === false) {
        return "404.php missing page_not_found translation reference";
    }
    if (strpos($content, 'return_to_studio') === false) {
        return "404.php missing return_to_studio button reference";
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
    echo " \033[32m(ALL TESTS PASSED!)\033[0m\n";
    echo "========================================================\n";
    exit(0);
}
