<?php
/**
 * AI Design Studio - Security Access & .htaccess Protection Test
 * 
 * Verifies that all sensitive files and internal directories return 403 Forbidden
 * via Apache .htaccess rules, while public assets and clean routes remain accessible.
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

function http_get(string $url, bool $followRedirects = false): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    $headers = substr((string)$raw, 0, $headerSize);
    $body = substr((string)$raw, $headerSize);

    return [
        'code' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'redirect' => $redirectUrl
    ];
}

// Auto-detect working local test domain
$testHosts = [
    'http://layerly.test',
    'http://localhost/layerly',
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
    $baseUrl = 'http://localhost/layerly';
}

echo "========================================================\n";
echo "   AI Design Studio - Security Access Verification     \n";
echo "========================================================\n";

// -----------------------------------------------------------------------------
// SECTION 1: Sensitive Files & Directory 403 Forbidden Protection
// -----------------------------------------------------------------------------
echo "\n--- SECTION 1: Sensitive Files & Directory Access (403 Expected) ---\n";

$blockedTargets = [
    'config.php'             => 'Direct access to config.php',
    'includes/'              => 'Direct access to includes/ directory',
    'includes/db.php'        => 'Direct access to includes/db.php',
    'includes/security.php'  => 'Direct access to includes/security.php',
    'storage/'               => 'Direct access to storage/ directory',
    'storage/logs/'          => 'Direct access to storage/logs/ directory',
    'database/'              => 'Direct access to database/ directory',
    'database/migrations/'   => 'Direct access to database/migrations/ directory',
    'starter-templates/'     => 'Direct access to starter-templates/ directory',
    'lang/'                  => 'Direct access to lang/ directory',
    'tests/'                 => 'Direct access to tests/ directory',
    'tests/run_all.php'      => 'Direct access to tests/run_all.php',
    'tools/'                 => 'Direct access to tools/ directory',
    'tools/migrate.php'      => 'Direct access to tools/migrate.php',
    'docs/'                  => 'Direct access to docs/ directory'
];

foreach ($blockedTargets as $path => $desc) {
    $res = http_get("{$baseUrl}/{$path}");
    assert_test("{$desc} is blocked (403 Forbidden)", $res['code'] === 403, "HTTP status: {$res['code']}");
}

// -----------------------------------------------------------------------------
// SECTION 2: Public Assets Whitelist Access (200 Expected)
// -----------------------------------------------------------------------------
echo "\n--- SECTION 2: Public Assets Whitelist (200 Expected) ---\n";

$allowedAssets = [
    'assets/css/style.css'              => 'Liquid Glass CSS stylesheet',
    'assets/js/app.js'                  => 'Main JavaScript application file',
    'assets/js/editor/canvas-manager.js'=> 'Canvas Manager JS module',
    'assets/js/editor/theme-manager.js' => 'Theme Manager JS module'
];

foreach ($allowedAssets as $path => $desc) {
    $res = http_get("{$baseUrl}/{$path}");
    assert_test("{$desc} is publicly accessible (200 OK)", $res['code'] === 200, "HTTP status: {$res['code']}");
}

// -----------------------------------------------------------------------------
// SECTION 3: Legacy URL Redirects (301 Moved Permanently)
// -----------------------------------------------------------------------------
echo "\n--- SECTION 3: Legacy PHP URLs 301 Redirect to Clean URLs ---\n";

$legacyRedirects = [
    'login.php'           => 'login',
    'register.php'        => 'register',
    'logout.php'          => 'logout',
    'forgot-password.php' => 'forgot-password',
    'reset-password.php'  => 'reset-password',
    'profile.php'         => 'profile',
    'projects.php'        => 'projects',
    'history.php'         => 'history',
    'templates.php'       => 'templates',
    'about.php'           => 'about'
];

foreach ($legacyRedirects as $oldPhp => $cleanTarget) {
    $res = http_get("{$baseUrl}/{$oldPhp}");
    $is301 = ($res['code'] === 301);
    $redirectsToClean = str_contains($res['headers'], "Location: ") && (str_contains($res['headers'], "/{$cleanTarget}") || str_contains($res['redirect'] ?? '', $cleanTarget));
    assert_test("{$oldPhp} returns 301 redirect to /{$cleanTarget}", $is301 && $redirectsToClean, "HTTP {$res['code']}, Redirect: {$res['redirect']}");
}

// -----------------------------------------------------------------------------
// SECTION 4: Clean URL Routing (200 Expected)
// -----------------------------------------------------------------------------
echo "\n--- SECTION 4: Clean URL Routing (200 Expected) ---\n";

$cleanRoutes = [
    'login'           => 'Clean route /login renders auth/login.php',
    'register'        => 'Clean route /register renders auth/register.php',
    'forgot-password' => 'Clean route /forgot-password renders auth/forgot-password.php',
    'projects'        => 'Clean route /projects renders account/projects.php',
    'templates'       => 'Clean route /templates renders pages/templates.php',
    'history'         => 'Clean route /history renders account/history.php',
    'about'           => 'Clean route /about renders pages/about.php'
];

foreach ($cleanRoutes as $route => $desc) {
    $res = http_get("{$baseUrl}/{$route}");
    assert_test("{$desc} (HTTP 200)", $res['code'] === 200, "HTTP status: {$res['code']}");
}

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "\n========================================================\n";
echo "SECURITY TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
