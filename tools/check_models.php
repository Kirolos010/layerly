<?php
/**
 * AI Design Studio - Tool: Check Available Gemini Models
 * Queries Google Gemini ListModels API using configured GEMINI_API_KEY.
 */

define('APP_INIT', true);
if (file_exists(__DIR__ . '/../config.php')) {
    require __DIR__ . '/../config.php';
}

$key = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
if (empty($key)) {
    echo "Error: GEMINI_API_KEY is not configured in config.php\n";
    exit(1);
}

$baseUrl = defined('GEMINI_API_BASE_URL') ? rtrim(GEMINI_API_BASE_URL, '/') : 'https://generativelanguage.googleapis.com/v1beta';
$url = "{$baseUrl}/models?key=" . urlencode($key);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $code\n";
if ($err) {
    echo "cURL Error: $err\n";
    exit(1);
}

$data = json_decode((string)$res, true);
if (isset($data['models'])) {
    echo "Found " . count($data['models']) . " models:\n";
    foreach ($data['models'] as $m) {
        $name = $m['name'] ?? '';
        $methods = implode(', ', $m['supportedGenerationMethods'] ?? []);
        echo "- {$name} ({$methods})\n";
    }
} else {
    echo "Response:\n" . substr((string)$res, 0, 1000) . "\n";
}

