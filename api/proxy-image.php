<?php
/**
 * AI Design Studio - Same-Origin Safe Image Proxy
 * 
 * Prevents canvas tainting (CORS) in Fabric.js while protecting against SSRF,
 * private network access, port scanning, and oversized payloads.
 * 
 * GET /api/proxy-image.php?url=https%3A%2F%2F...
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';

// Allowed image mime types
const ALLOWED_MIME_TYPES = [
    'image/jpeg'    => 'jpg',
    'image/png'     => 'png',
    'image/webp'    => 'webp',
    'image/gif'     => 'gif',
    'image/svg+xml' => 'svg'
];

const MAX_IMAGE_BYTES = 10485760; // 10 MB

/**
 * Check if IP address is private, loopback, or reserved (SSRF protection)
 */
function is_private_ip(string $ip): bool {
    // Validate IP format
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return true;
    }

    // Filter private and reserved ranges
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return true;
    }

    // Explicit loopback and cloud metadata checks
    if ($ip === '127.0.0.1' || $ip === '::1' || $ip === '0.0.0.0' || $ip === '169.254.169.254') {
        return true;
    }

    // Check link-local (169.254.0.0/16)
    if (str_starts_with($ip, '169.254.')) {
        return true;
    }

    return false;
}

/**
 * Validate URL host against SSRF
 */
function validate_safe_host(string $url): ?string {
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }

    $scheme = strtolower($parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }

    $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
    if ($port !== 80 && $port !== 443) {
        return null;
    }

    $host = $parts['host'];

    // Block localhost names
    if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
        return null;
    }

    // Resolve DNS
    $ips = @gethostbynamel($host);
    if (empty($ips)) {
        return null;
    }

    foreach ($ips as $ip) {
        if (is_private_ip($ip)) {
            return null; // Block if any resolved IP is in a private/restricted subnet
        }
    }

    return $ips[0];
}

// -----------------------------------------------------------------------------
// Request Processing
// -----------------------------------------------------------------------------

if (defined('TEST_RUN') && TEST_RUN === true) {
    return;
}

// Handle CORS preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Max-Age: 86400');
    exit;
}

$rawUrl = trim((string)($_GET['url'] ?? ''));
if (empty($rawUrl)) {
    http_response_code(400);
    json_response(['success' => false, 'error' => 'Missing "url" parameter.'], 400);
}

// Sanitize URL
$resolvedIp = validate_safe_host($rawUrl);
if ($resolvedIp === null) {
    http_response_code(403);
    json_response(['success' => false, 'error' => 'URL blocked or host resolved to an unauthorized/private address.'], 403);
}

// Fetch image via cURL with strict controls
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $rawUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 7,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'AIDesignStudio/1.0 (+https://localhost)',
    CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_HTTPHEADER     => [
        'Accept: image/webp,image/png,image/jpeg,image/*;q=0.8'
    ]
]);

$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr || $httpCode < 200 || $httpCode >= 300 || empty($imageData)) {
    http_response_code(502);
    json_response([
        'success' => false,
        'error'   => 'Could not fetch image: ' . ($curlErr ?: "HTTP {$httpCode}")
    ], 502);
}

// Validate size
$byteLength = strlen($imageData);
if ($byteLength > MAX_IMAGE_BYTES) {
    http_response_code(413);
    json_response(['success' => false, 'error' => 'Image exceeds 10MB maximum proxy size.'], 413);
}

// Verify actual image MIME type using finfo
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->buffer($imageData);

// Allow SVG if finfo returns text/plain or text/xml but contains valid <svg
if ($detectedMime === 'text/plain' || $detectedMime === 'text/xml' || $detectedMime === 'image/svg') {
    if (stripos($imageData, '<svg') !== false) {
        $detectedMime = 'image/svg+xml';
    }
}

if (!isset(ALLOWED_MIME_TYPES[$detectedMime])) {
    http_response_code(415);
    json_response(['success' => false, 'error' => 'Fetched file is not an allowed image format.'], 415);
}

// Output image with permissive CORS headers for Fabric.js
header('Content-Type: ' . $detectedMime);
header('Content-Length: ' . $byteLength);
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('X-Content-Type-Options: nosniff');

echo $imageData;
exit;
