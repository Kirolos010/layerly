<?php
define('APP_INIT', true);
require __DIR__ . '/../config.php';

$key = GEMINI_API_KEY;
$refImage = "C:/Users/Kirolos Samir0/.gemini/antigravity/brain/17b16749-06fc-422d-bb1e-f521886da31d/.user_uploaded/media_1794006378956_1c6f42a9.jpg";

if (!file_exists($refImage)) {
    die("Ref image not found at $refImage\n");
}

$raw = file_get_contents($refImage);
$b64 = base64_encode($raw);

echo "Image size: " . strlen($raw) . " bytes\n";

// Test 1: Gemini 2.5 Flash Vision (extract structured JSON text blocks)
$visionModel = "gemini-2.5-flash";
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$visionModel}:generateContent?key=" . urlencode($key);

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Analyze this poster image and return structured text blocks in JSON.'],
                [
                    'inlineData' => [
                        'mimeType' => 'image/jpeg',
                        'data' => $b64
                    ]
                ]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Vision Test ($visionModel) HTTP Code: $code\n";
echo "Response excerpt: " . substr($res, 0, 800) . "\n";

