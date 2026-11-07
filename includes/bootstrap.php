<?php
/**
 * AI Design Studio - Application Bootstrap
 * Defines filesystem BASE_PATH and auto-detected BASE_URL for root and subfolder deployments.
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Auto-detect BASE_URL
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
    $appDir = str_replace('\\', '/', BASE_PATH);

    $subDir = '';
    if (!empty($docRoot) && strpos($appDir, $docRoot) === 0) {
        $subDir = substr($appDir, strlen($docRoot));
    } else {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        if ($dir !== '/' && $dir !== '.') {
            $parts = explode('/', trim($dir, '/'));
            $cleanParts = [];
            foreach ($parts as $p) {
                if (in_array($p, ['auth', 'account', 'pages', 'admin', 'api', 'tools', 'tests', 'starter-templates'], true)) {
                    break;
                }
                $cleanParts[] = $p;
            }
            $subDir = !empty($cleanParts) ? '/' . implode('/', $cleanParts) : '';
        }
    }

    $subDir = rtrim($subDir, '/');
    define('BASE_SUBDIR', $subDir);
    define('BASE_URL', $subDir);
    define('FULL_BASE_URL', rtrim($protocol . $host . $subDir, '/'));
}

/**
 * Build relative URL with BASE_URL prefix
 */
function url(string $path = ''): string {
    $clean = ltrim($path, '/');
    if ($clean === '') {
        return BASE_URL !== '' ? BASE_URL : '/';
    }
    return BASE_URL . '/' . $clean;
}

/**
 * Build asset URL
 */
function asset(string $path): string {
    return url($path);
}

/**
 * Validate redirect destination to prevent open redirect vulnerabilities
 */
function safe_redirect_url(?string $target, string $default = '/'): string {
    if (empty($target)) {
        return url($default);
    }
    // Reject absolute URLs with foreign protocols/hosts
    if (preg_match('/^https?:\/\//i', $target)) {
        $targetHost = parse_url($target, PHP_URL_HOST);
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (strcasecmp((string)$targetHost, (string)$currentHost) === 0) {
            return $target;
        }
        return url($default);
    }
    // Disallow protocol-relative URLs (e.g. //evil.com)
    if (str_starts_with($target, '//')) {
        return url($default);
    }
    // Strip leading BASE_URL if present to avoid duplication
    if (BASE_URL !== '' && str_starts_with($target, BASE_URL)) {
        return $target;
    }
    return url($target);
}

/**
 * Perform HTTP redirect
 */
function redirect(string $path, int $code = 302): void {
    $dest = safe_redirect_url($path);
    if (!headers_sent()) {
        header("Location: {$dest}", true, $code);
    } else {
        echo "<script>window.location.href=" . json_encode($dest) . ";</script>";
    }
    exit;
}

// Load core configuration and helpers if not already loaded
if (file_exists(BASE_PATH . '/config.php')) {
    require_once BASE_PATH . '/config.php';
} elseif (file_exists(BASE_PATH . '/config.example.php')) {
    require_once BASE_PATH . '/config.example.php';
}

require_once BASE_PATH . '/includes/security.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/rate-limiter.php';
require_once BASE_PATH . '/includes/sanitizer.php';
require_once BASE_PATH . '/includes/HistoryRepository.php';
