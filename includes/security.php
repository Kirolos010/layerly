<?php
/**
 * AI Design Studio - Security & Utility Helpers
 * 
 * Includes:
 * - Security headers (CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy)
 * - CSRF token generation & validation
 * - Input validation & sanitization
 * - Safe JSON handling & response formatting
 * - Multilingual translation helper
 * - Secure file-based error and event logging
 */

defined('APP_INIT') or define('APP_INIT', true);

// Start secure session if not active
function init_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $lifetime = 86400 * 7; // 7 days

        if (!headers_sent()) {
            $cookiePath = (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL : '/';
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => $cookiePath,
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }
}

/**
 * Send standard HTTP security headers
 */
function send_security_headers(): void {
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');

    // CSP allowing Google Fonts and cdnjs for Fabric.js / assets
    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com data:; "
         . "img-src 'self' data: https: blob:; "
         . "connect-src 'self'; "
         . "frame-ancestors 'self'; "
         . "base-uri 'self'; "
         . "form-action 'self';";

    header("Content-Security-Policy: {$csp}");
}

/**
 * Generate or retrieve current CSRF token
 */
function get_csrf_token(): string {
    init_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token
 */
function validate_csrf_token(?string $token): bool {
    init_secure_session();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF token on POST requests or terminate with 403
 */
function verify_csrf_or_exit(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = null;

    // 1. Check HTTP header X-CSRF-Token
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    // 2. Check standard POST field
    elseif (!empty($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    // 3. Check JSON payload
    else {
        $json = get_json_input();
        if (is_array($json) && !empty($json['csrf_token'])) {
            $token = $json['csrf_token'];
        }
    }

    if (!validate_csrf_token($token)) {
        app_log("CSRF validation failed for IP: " . get_client_ip(), 'WARNING', 'security.log');
        json_response([
            'success' => false,
            'error'   => t('csrf_invalid'),
            'code'    => 'CSRF_INVALID'
        ], 403);
    }
}

/**
 * Safely get client IP address
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    // Validate IPv4 or IPv6
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    return '127.0.0.1';
}

/**
 * Escape string for HTML output
 */
function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Read and decode JSON request body
 */
function get_json_input(): ?array {
    static $input = null;
    if ($input !== null) {
        return $input;
    }

    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        $input = [];
        return $input;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $input = [];
        return $input;
    }

    $input = $decoded;
    return $input;
}

/**
 * Send JSON response and terminate
 */
function json_response(array $data, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Load raw translation dictionary array for a given language
 */
function load_translations(string $lang = 'ar'): array {
    static $cache = [];
    $lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';

    if (!isset($cache[$lang])) {
        $jsonFile = dirname(__DIR__) . "/lang/{$lang}.json";
        $phpFile = dirname(__DIR__) . "/lang/{$lang}.php";

        if (file_exists($jsonFile)) {
            $raw = @file_get_contents($jsonFile);
            $cache[$lang] = json_decode((string)$raw, true) ?: [];
        } elseif (file_exists($phpFile)) {
            $cache[$lang] = require $phpFile;
        } else {
            $cache[$lang] = [];
        }
    }

    return $cache[$lang];
}

/**
 * Multilingual Translation Helper
 */
function t(string $key, ?string $lang = null, array $replace = []): string {
    if ($lang === null) {
        $lang = get_current_lang();
    }
    $lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';

    $dict = load_translations($lang);
    $line = $dict[$key] ?? null;

    // Fallback to English
    if ($line === null && $lang !== 'en') {
        $enDict = load_translations('en');
        $line = $enDict[$key] ?? null;
    }

    if ($line === null) {
        $line = $key;
    }

    if (!empty($replace)) {
        foreach ($replace as $k => $v) {
            $line = str_replace([":{$k}", "{" . $k . "}"], (string)$v, $line);
        }
    }

    return $line;
}

/**
 * Get current active language ('ar' or 'en')
 * Resolves via: query param -> session -> persistent cookie -> Accept-Language header -> default
 */
function get_current_lang(): string {
    init_secure_session();

    // 1. Explicit query parameter override
    if (!empty($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
        if (!headers_sent()) {
            $cookiePath = (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL : '/';
            @setcookie('design_studio_lang', $_GET['lang'], [
                'expires' => time() + (365 * 86400),
                'path' => $cookiePath,
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        }
        return $_GET['lang'];
    }

    // 2. Active Session
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['ar', 'en'], true)) {
        return $_SESSION['lang'];
    }

    // 3. Persistent Cookie
    if (!empty($_COOKIE['design_studio_lang']) && in_array($_COOKIE['design_studio_lang'], ['ar', 'en'], true)) {
        $_SESSION['lang'] = $_COOKIE['design_studio_lang'];
        return $_COOKIE['design_studio_lang'];
    }

    // 4. Auto-detect from Accept-Language header
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $arPos = strpos($accept, 'ar');
        $enPos = strpos($accept, 'en');
        if ($arPos !== false && ($enPos === false || $arPos <= $enPos)) {
            $_SESSION['lang'] = 'ar';
            return 'ar';
        }
        if ($enPos !== false) {
            $_SESSION['lang'] = 'en';
            return 'en';
        }
    }

    // 5. Configured Default or fallback
    if (defined('APP_DEFAULT_LANG') && in_array(APP_DEFAULT_LANG, ['ar', 'en'], true)) {
        return APP_DEFAULT_LANG;
    }

    return 'ar';
}

/**
 * Detect whether text is predominantly Arabic
 */
function is_arabic_text(string $text): bool {
    // Unicode ranges for Arabic scripts: \x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}
    return (bool)preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
}

/**
 * Secure application logger
 */
function app_log(string $message, string $level = 'INFO', string $file = 'app.log'): void {
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $ip = get_client_ip();
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$level}] [IP: {$ip}] {$message}" . PHP_EOL;

    @file_put_contents("{$logDir}/{$file}", $line, FILE_APPEND);
}
