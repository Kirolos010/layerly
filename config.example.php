<?php
/**
 * AI Design Studio - Configuration Template
 * 
 * Copy this file to `config.php` and fill in your settings.
 * DO NOT commit `config.php` with real credentials to version control.
 */

// Prevent direct web access
if (!defined('APP_INIT') && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// -----------------------------------------------------------------------------
// Database Configuration
// -----------------------------------------------------------------------------
define('DB_HOST', '127.0.0.1');          // Database host (usually 127.0.0.1 or localhost)
define('DB_PORT', 3306);                 // MySQL Port (default: 3306)
define('DB_NAME', 'ai_design_studio');   // Database name
define('DB_USER', 'root');               // Database user (Laragon default: root)
define('DB_PASS', '');                   // Database password (Laragon default: empty)
define('DB_CHARSET', 'utf8mb4');         // Full Unicode support including emojis

// -----------------------------------------------------------------------------
// AI Provider Configuration (Google Gemini)
// -----------------------------------------------------------------------------
// Get your free API key at: https://aistudio.google.com/app/apikey
define('GEMINI_API_KEY', '');
define('GEMINI_MODEL', 'gemini-3-flash-preview'); // Free tier model (e.g. gemini-3.5-flash or gemini-2.5-flash)
define('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'); // Optional Gemini image-edit/generation model
define('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'); // Gemini API base endpoint
define('GEMINI_TIMEOUT', 25);            // Timeout for Gemini calls in seconds
define('GEMINI_CONNECT_TIMEOUT', 7);     // Connect timeout in seconds

// -----------------------------------------------------------------------------
// Optional Secondary AI Fallback Provider (Groq or OpenRouter)
// -----------------------------------------------------------------------------
// Used automatically if Gemini encounters 429 rate limit, 5xx error, or timeout.
// Free providers: 'groq' (https://console.groq.com) or 'openrouter' (https://openrouter.ai)
define('FALLBACK_PROVIDER', 'groq');     // 'groq' or 'openrouter' (leave blank to disable)
define('FALLBACK_API_KEY', '');          // Fallback provider API key
define('FALLBACK_MODEL', 'llama-3.3-70b-versatile'); // Groq: llama-3.3-70b-versatile | OpenRouter: meta-llama/llama-3.3-70b-instruct:free
define('FALLBACK_API_BASE_URL', 'https://api.groq.com/openai/v1/chat/completions'); // Fallback API endpoint
define('FALLBACK_TIMEOUT', 20);          // Timeout in seconds
define('FALLBACK_CONNECT_TIMEOUT', 5);   // Connect timeout in seconds

// -----------------------------------------------------------------------------
// Hugging Face API Configuration (Background Removal: RMBG-1.4 / RMBG-2.0)
// -----------------------------------------------------------------------------
// Optional free access token from: https://huggingface.co/settings/tokens
define('HUGGINGFACE_API_TOKEN', '');
define('HUGGINGFACE_RMBG_MODEL', 'briaai/RMBG-1.4'); // Default RMBG model
define('HUGGINGFACE_API_BASE_URL', 'https://api-inference.huggingface.co/models'); // Hugging Face base endpoint
define('HUGGINGFACE_TIMEOUT', 25);       // Timeout in seconds
define('HUGGINGFACE_CONNECT_TIMEOUT', 8); // Connect timeout in seconds

// -----------------------------------------------------------------------------
// Pollinations.ai Configuration (Free Text-Free Background Illustrations)
// -----------------------------------------------------------------------------
define('POLLINATIONS_ENABLED', true);    // Free text-free background generation
define('POLLINATIONS_BASE_URL', 'https://image.pollinations.ai'); // Pollinations base URL

// -----------------------------------------------------------------------------
// AI Request Pipeline & Deadlines
// -----------------------------------------------------------------------------
define('AI_REQUEST_DEADLINE', 30);       // Max total seconds allowed across all providers before aborting

// -----------------------------------------------------------------------------
// Rate Limiting (Per IP address, tracked in MySQL)
// -----------------------------------------------------------------------------
define('RATE_LIMIT_PER_MINUTE', 10);     // Max AI requests per minute per IP
define('RATE_LIMIT_PER_DAY', 100);       // Max AI requests per day per IP
define('POSTER_DAILY_LIMIT', 5);         // Max AI poster operations per day per IP/user
define('AUTH_RATE_LIMIT_MAX', 5);        // Max login/reset attempts per window
define('AUTH_RATE_LIMIT_WINDOW', 900);   // Window in seconds (15 minutes)

// -----------------------------------------------------------------------------
// Generation History Settings
// -----------------------------------------------------------------------------
define('HISTORY_MAX_PER_USER', 200);     // Max history rows stored per user/guest

// -----------------------------------------------------------------------------
// Email & Password Reset (PHP mail() with optional SMTP fallback)
// -----------------------------------------------------------------------------
define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', 'AI Design Studio');
define('SMTP_ENABLED', false);           // Set true to use SMTP instead of PHP mail()
define('SMTP_HOST', 'localhost');        // e.g. smtp.gmail.com or mail.byethost.com
define('SMTP_PORT', 587);                // 587 (TLS) or 465 (SSL)
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_SECURE', 'tls');            // 'tls' or 'ssl'

// -----------------------------------------------------------------------------
// Security & System Settings
// -----------------------------------------------------------------------------
// Secret key required to access diagnostics.php (e.g., diagnostics.php?secret=YOUR_SECRET)
define('DIAGNOSTICS_SECRET', 'change_this_secret_in_production_9988');

// Debug mode: TRUE for local development, FALSE for production (InfinityFree, ByetHost, etc.)
// When FALSE, errors are logged to /storage/logs and NEVER displayed to users.
define('DEBUG', true);

// Application metadata
define('APP_NAME', 'AI Design Studio');
define('APP_VERSION', '1.0.0');
define('APP_DEFAULT_LANG', 'ar');        // 'ar' or 'en'

// Timeout for outgoing AI API cURL requests (in seconds)
define('AI_CURL_TIMEOUT', 55);

// -----------------------------------------------------------------------------
// Error Reporting Setup
// -----------------------------------------------------------------------------
if (defined('DEBUG') && DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/storage/logs/php_errors.log');
}

