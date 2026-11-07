<?php
define('APP_INIT', true);
require __DIR__ . '/../config.php';

$key = GEMINI_API_KEY;
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($key);

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
if ($err) echo "Error: $err\n";

$data = json_decode($res, true);
if (isset($data['models'])) {
    echo "Found " . count($data['models']) . " models:\n";
    foreach ($data['models'] as $m) {
        $name = $m['name'] ?? '';
        $methods = implode(', ', $m['supportedGenerationMethods'] ?? []);
        echo "- $name ($methods)\n";
    }
} else {
    echo "Response:\n" . substr($res, 0, 1000) . "\n";
}

