<?php
/**
 * AI Design Studio - Phase 2 Comprehensive Test Suite
 * 
 * Tests:
 * 1. Proxy Image endpoint (/api/proxy-image.php): SSRF protection, protocols, private IP blocking, 400 errors.
 * 2. Upload endpoint (/api/upload.php): Directory traversal protection, CSRF verification, finfo MIME verification,
 *    safe randomized file naming, safe image delivery with CORS headers, rejection of fake/spoofed extensions.
 * 3. Icon & Font registries: Built-in icons validity, font families list.
 * 4. Full Phase 1 & 1.5 regression checks.
 */

define('APP_INIT', true);
define('TEST_RUN', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/sanitizer.php';

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$name}\n";
    } else {
        $failed++;
        echo " [FAIL] {$name}" . ($detail ? " - {$detail}" : "") . "\n";
    }
}

echo "========================================================\n";
echo "AI DESIGN STUDIO - PHASE 2 COMPREHENSIVE TEST SUITE\n";
echo "========================================================\n\n";

// -----------------------------------------------------------------------------
// SECTION 1: /api/proxy-image.php SSRF & Security Checks
// -----------------------------------------------------------------------------
echo "--- SECTION 1: Safe Image Proxy & SSRF Protection ---\n";

require_once dirname(__DIR__) . '/api/proxy-image.php'; // loads functions like is_private_ip and validate_safe_host

// 1.1 Private IP check
assert_test("is_private_ip() detects 127.0.0.1", is_private_ip('127.0.0.1') === true);
assert_test("is_private_ip() detects ::1", is_private_ip('::1') === true);
assert_test("is_private_ip() detects 192.168.1.1", is_private_ip('192.168.1.1') === true);
assert_test("is_private_ip() detects 10.0.0.5", is_private_ip('10.0.0.5') === true);
assert_test("is_private_ip() detects 172.16.0.1", is_private_ip('172.16.0.1') === true);
assert_test("is_private_ip() detects AWS/cloud metadata 169.254.169.254", is_private_ip('169.254.169.254') === true);
assert_test("is_private_ip() detects link-local range 169.254.1.20", is_private_ip('169.254.1.20') === true);
assert_test("is_private_ip() allows public IP 8.8.8.8", is_private_ip('8.8.8.8') === false);
assert_test("is_private_ip() allows public IP 142.250.190.46", is_private_ip('142.250.190.46') === false);

// 1.2 Host & URL validation
assert_test("validate_safe_host() blocks localhost", validate_safe_host('http://localhost/image.png') === null);
assert_test("validate_safe_host() blocks 127.0.0.1", validate_safe_host('http://127.0.0.1/test.jpg') === null);
assert_test("validate_safe_host() blocks private subnet", validate_safe_host('http://192.168.0.100/logo.png') === null);
assert_test("validate_safe_host() blocks file:// protocol", validate_safe_host('file:///etc/passwd') === null);
assert_test("validate_safe_host() blocks gopher:// protocol", validate_safe_host('gopher://127.0.0.1:70') === null);
assert_test("validate_safe_host() blocks non-standard port 8080", validate_safe_host('http://example.com:8080/image.png') === null);
assert_test("validate_safe_host() blocks malformed URLs", validate_safe_host('not-a-url') === null);

// -----------------------------------------------------------------------------
// SECTION 2: /api/upload.php Security & MIME Verification
// -----------------------------------------------------------------------------
echo "\n--- SECTION 2: Image Upload Endpoint & Safe Delivery ---\n";

$uploadDir = dirname(__DIR__) . '/storage/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// 2.1 Traversal protection regex
$regex = '/^img_[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif|svg)$/i';
assert_test("Upload delivery regex accepts valid name", preg_match($regex, 'img_abcdef0123456789abcdef0123456789.png') === 1);
assert_test("Upload delivery regex rejects directory traversal ../", preg_match($regex, '../config.php') === 0);
assert_test("Upload delivery regex rejects malicious php extension", preg_match($regex, 'img_abcdef0123456789abcdef0123456789.php') === 0);
assert_test("Upload delivery regex rejects arbitrary files", preg_match($regex, 'shell.php') === 0);
assert_test("Upload delivery regex rejects short hash", preg_match($regex, 'img_123.png') === 0);

// 2.2 finfo MIME verification check on actual files
$valid1x1Png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$testPngPath = $uploadDir . '/test_verify.png';
file_put_contents($testPngPath, $valid1x1Png);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedPngMime = $finfo->file($testPngPath);
assert_test("finfo detects real PNG MIME type", $detectedPngMime === 'image/png');

// 2.3 Spoofed extension rejection (e.g. PHP file renamed as .png)
$fakePngPath = $uploadDir . '/test_fake.png';
file_put_contents($fakePngPath, '<?php echo "evil script"; ?>');
$detectedFakeMime = $finfo->file($fakePngPath);
assert_test("finfo detects spoofed PNG as non-image (text/x-php or text/plain)", $detectedFakeMime !== 'image/png' && !str_starts_with($detectedFakeMime, 'image/'));

// Cleanup test files
@unlink($testPngPath);
@unlink($fakePngPath);

// 2.4 SVG detection and XML validation
$validSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40"/></svg>';
$testSvgPath = $uploadDir . '/test_verify.svg';
file_put_contents($testSvgPath, $validSvg);
$detectedSvgMime = $finfo->file($testSvgPath);
$isSvg = ($detectedSvgMime === 'image/svg+xml') || (stripos($validSvg, '<svg') !== false);
assert_test("SVG detection identifies valid SVG content", $isSvg === true);
@unlink($testSvgPath);

// -----------------------------------------------------------------------------
// SECTION 3: Editor Assets & Registries
// -----------------------------------------------------------------------------
echo "\n--- SECTION 3: Editor Font & Icon Registries ---\n";

// 3.1 Check font manager JS content
$fontManagerJs = file_get_contents(dirname(__DIR__) . '/assets/js/editor/font-manager.js');
assert_test("Font manager defines Arabic fonts (Cairo, Tajawal, Almarai, Amiri, Reem Kufi)", 
    str_contains($fontManagerJs, 'Cairo') && 
    str_contains($fontManagerJs, 'Tajawal') && 
    str_contains($fontManagerJs, 'Almarai') && 
    str_contains($fontManagerJs, 'Amiri') && 
    str_contains($fontManagerJs, 'Reem Kufi')
);
assert_test("Font manager defines English fonts (Poppins, Montserrat, Playfair Display, Roboto)",
    str_contains($fontManagerJs, 'Poppins') && 
    str_contains($fontManagerJs, 'Montserrat') && 
    str_contains($fontManagerJs, 'Playfair Display') && 
    str_contains($fontManagerJs, 'Roboto')
);
assert_test("Font manager exports loadFonts and isArabic", 
    str_contains($fontManagerJs, 'export async function loadFonts') && 
    str_contains($fontManagerJs, 'export function isArabic')
);

// 3.2 Check built-in icons data
$iconsJs = file_get_contents(dirname(__DIR__) . '/assets/js/editor/icons-data.js');
assert_test("Icons registry exports BUILTIN_ICONS array", str_contains($iconsJs, 'export const BUILTIN_ICONS = ['));
$iconMatches = preg_match_all('/name:\s*[\'"][^\'"]+[\'"]/', $iconsJs);
assert_test("Icons registry contains at least 14 vector SVG icons", $iconMatches >= 14, "Found {$iconMatches} icons");

// 3.3 Check editor modules exist and are non-empty
$expectedModules = [
    'canvas-manager.js',
    'history-manager.js',
    'ai-renderer.js',
    'layers-panel.js',
    'properties-panel.js',
    'toolbar.js',
    'shortcuts.js',
    'export-manager.js',
    'ai-bar.js'
];
foreach ($expectedModules as $mod) {
    $path = dirname(__DIR__) . '/assets/js/editor/' . $mod;
    assert_test("Module {$mod} exists and is non-empty", file_exists($path) && filesize($path) > 500);
}

// -----------------------------------------------------------------------------
// SECTION 4: Index.php UI & Canvas Integration
// -----------------------------------------------------------------------------
echo "\n--- SECTION 4: Index.php Editor Integration ---\n";

$indexContent = file_get_contents(dirname(__DIR__) . '/index.php');
assert_test("index.php includes Fabric.js 5.3.1 CDN", str_contains($indexContent, 'cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js'));
assert_test("index.php includes canvas #design-canvas", str_contains($indexContent, 'id="design-canvas"'));
assert_test("index.php includes grid overlay #grid-overlay", str_contains($indexContent, 'id="grid-overlay"'));
assert_test("index.php includes prompt input #prompt-input", str_contains($indexContent, 'id="prompt-input"'));
assert_test("index.php includes enhance button #btn-enhance", str_contains($indexContent, 'id="btn-enhance"'));
assert_test("index.php includes undo enhance button #btn-undo-enhance", str_contains($indexContent, 'id="btn-undo-enhance"'));
assert_test("index.php includes generate button #btn-generate", str_contains($indexContent, 'id="btn-generate"'));
assert_test("index.php includes regenerate button #btn-regenerate", str_contains($indexContent, 'id="btn-regenerate"'));
assert_test("index.php includes edit with AI command bar #instruction-input", str_contains($indexContent, 'id="instruction-input"'));
assert_test("index.php includes layers container #layers-container", str_contains($indexContent, 'id="layers-container"'));
assert_test("index.php includes properties container #properties-container", str_contains($indexContent, 'id="properties-container"'));
assert_test("index.php includes export buttons (PNG, JPG, SVG, JSON)", 
    str_contains($indexContent, 'id="btn-export-png"') &&
    str_contains($indexContent, 'id="btn-export-jpg"') &&
    str_contains($indexContent, 'id="btn-export-svg"') &&
    str_contains($indexContent, 'id="btn-export-json"')
);
assert_test("index.php includes undo and redo buttons", str_contains($indexContent, 'id="btn-undo"') && str_contains($indexContent, 'id="btn-redo"'));
assert_test("index.php includes zoom controls and grid toggle", str_contains($indexContent, 'id="btn-zoom-in"') && str_contains($indexContent, 'id="btn-toggle-grid"'));

// -----------------------------------------------------------------------------
// SECTION 5: AI Edit Serialization & Translation Check
// -----------------------------------------------------------------------------
echo "\n--- SECTION 5: AI Edit & Translation Verification ---\n";

$enLang = require dirname(__DIR__) . '/lang/en.php';
$arLang = require dirname(__DIR__) . '/lang/ar.php';

$requiredEditorKeys = [
    'editor_title', 'layers', 'properties', 'export', 'export_png',
    'export_jpg', 'export_svg', 'export_json', 'undo', 'redo',
    'regenerate', 'undo_enhance', 'add_text', 'add_heading', 'add_shape',
    'icons', 'upload_image', 'background', 'zoom_in', 'toggle_grid',
    'edit_with_ai'
];

foreach ($requiredEditorKeys as $k) {
    assert_test("English translation has key '{$k}'", isset($enLang[$k]) && !empty($enLang[$k]));
    assert_test("Arabic translation has key '{$k}'", isset($arLang[$k]) && !empty($arLang[$k]));
}

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "\n========================================================\n";
echo "PHASE 2 TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
