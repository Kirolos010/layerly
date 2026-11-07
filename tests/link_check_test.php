<?php
/**
 * AI Design Studio - Comprehensive Link Checker & Crawler Test
 * 
 * Verifies that all clean URLs, navigation links, and internal actions
 * return HTTP 200, contain valid HTML5 structures, include correct <base href>,
 * and reference existing static assets.
 */

define('APP_INIT', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$name}";
        if ($detail) {
            echo " ({$detail})";
        }
        echo "\n";
    }
}

function http_crawl(string $url, ?string $cookieJar = null): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    $headers = substr((string)$raw, 0, $headerSize);
    $body = substr((string)$raw, $headerSize);

    return [
        'code'     => $httpCode,
        'headers'  => $headers,
        'body'     => $body,
        'redirect' => $redirectUrl
    ];
}

// Auto-detect working local test domain
$testHosts = [
    'http://ai-design-studio.test',
    'http://localhost/ai-design-studio',
    'http://127.0.0.1:8000'
];

$baseUrl = null;
foreach ($testHosts as $host) {
    $ch = curl_init("{$host}/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $baseUrl = $host;
        break;
    }
}

if (!$baseUrl) {
    $baseUrl = 'http://localhost/ai-design-studio';
}

echo "========================================================\n";
echo "   AI Design Studio - Crawler & Link Integrity Test     \n";
echo "   Target Base URL: {$baseUrl}                         \n";
echo "========================================================\n\n";

$cookieJar = sys_get_temp_dir() . '/crawler_cookie_' . uniqid() . '.txt';

// -----------------------------------------------------------------------------
// SECTION 1: Guest Route Crawl (Public Pages)
// -----------------------------------------------------------------------------
echo "--- SECTION 1: Public Clean URL Routes (Guest State) ---\n";

$publicRoutes = [
    '/'                 => ['title' => 'app_name', 'hasBase' => true],
    '/login'            => ['title' => 'login',    'hasBase' => true],
    '/register'         => ['title' => 'register', 'hasBase' => true],
    '/forgot-password'  => ['title' => 'forgot_password_title', 'hasBase' => true],
    '/projects'         => ['title' => 'my_designs', 'hasBase' => true],
    '/templates'        => ['title' => 'templates', 'hasBase' => true],
    '/history'          => ['title' => 'history_title', 'hasBase' => true],
    '/about'            => ['title' => 'about_title', 'hasBase' => true],
    '/pages/404.php'    => ['title' => 'page_not_found', 'hasBase' => true]
];

foreach ($publicRoutes as $route => $meta) {
    $targetUrl = rtrim($baseUrl, '/') . $route;
    $res = http_crawl($targetUrl, $cookieJar);

    $is200 = ($res['code'] === 200 || ($route === '/pages/404.php' && $res['code'] === 404));
    assert_test("Route {$route} responds with valid status ({$res['code']})", $is200);

    // Validate HTML structure
    $hasDoctype = str_contains($res['body'], '<!DOCTYPE html>');
    assert_test("Route {$route} renders valid HTML5 document", $hasDoctype);

    // Validate <base href>
    if ($meta['hasBase']) {
        $hasBaseTag = (bool)preg_match('/<base\s+href="[^"]+">/', $res['body']);
        assert_test("Route {$route} includes valid <base href> tag", $hasBaseTag);
    }

    // Validate CSRF token (in meta tag or form input)
    if (!in_array($route, ['/pages/404.php', '/templates'], true)) {
        $hasCsrf = str_contains($res['body'], 'csrf-token') || str_contains($res['body'], 'csrf_token');
        assert_test("Route {$route} embeds CSRF security protection", $hasCsrf);
    }
}

// -----------------------------------------------------------------------------
// SECTION 2: Authentication Wall (Profile Page Protection)
// -----------------------------------------------------------------------------
echo "\n--- SECTION 2: Authentication Wall (Guest Protection) ---\n";

$guestProfile = http_crawl(rtrim($baseUrl, '/') . '/profile', $cookieJar);
$isRedirected = ($guestProfile['code'] === 302 || $guestProfile['code'] === 301);
$pointsToLogin = str_contains($guestProfile['headers'], 'login') || str_contains($guestProfile['redirect'] ?? '', 'login');
assert_test("Guest access to /profile redirects to /login", $isRedirected && $pointsToLogin, "HTTP {$guestProfile['code']}, Location: {$guestProfile['redirect']}");

// -----------------------------------------------------------------------------
// SECTION 3: Admin Diagnostics Route Security
// -----------------------------------------------------------------------------
echo "\n--- SECTION 3: Diagnostics Endpoint Security ---\n";

$diagNoSecret = http_crawl(rtrim($baseUrl, '/') . '/admin/diagnostics.php', $cookieJar);
$isProtected = ($diagNoSecret['code'] === 403 || str_contains($diagNoSecret['body'], 'Diagnostics Authentication') || str_contains($diagNoSecret['body'], 'Access Denied'));
assert_test("admin/diagnostics.php without secret prompts for authentication or returns 403", $isProtected, "HTTP {$diagNoSecret['code']}");

$secretParam = defined('DIAGNOSTICS_SECRET') ? '?secret=' . urlencode(DIAGNOSTICS_SECRET) : '';
$diagWithSecret = http_crawl(rtrim($baseUrl, '/') . '/admin/diagnostics.php' . $secretParam, $cookieJar);
assert_test("admin/diagnostics.php with valid secret succeeds (200 OK)", $diagWithSecret['code'] === 200);

// -----------------------------------------------------------------------------
// SECTION 4: Static Asset File Existence on Disk
// -----------------------------------------------------------------------------
echo "\n--- SECTION 4: Referencing Static Assets on Disk ---\n";

$criticalAssets = [
    'assets/css/style.css',
    'assets/js/app.js',
    'assets/js/editor/canvas-manager.js',
    'assets/js/editor/theme-manager.js',
    'assets/js/editor/toast-manager.js',
    'assets/js/editor/history-manager.js',
    'assets/js/editor/font-manager.js',
    'assets/js/editor/layers-panel.js',
    'assets/js/editor/properties-panel.js',
    'assets/js/editor/toolbar.js',
    'assets/js/editor/export-manager.js',
    'assets/js/editor/project-manager.js',
    'starter-templates/ramadan-sale-post.json',
    'starter-templates/coffee-shop-promo.json',
    'lang/ar.json',
    'lang/en.json'
];

foreach ($criticalAssets as $assetRelPath) {
    $fullPath = BASE_PATH . '/' . $assetRelPath;
    assert_test("Asset '{$assetRelPath}' exists on disk", file_exists($fullPath));
}

// Clean up test cookie jar
@unlink($cookieJar);

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "\n========================================================\n";
echo "CRAWLER TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
