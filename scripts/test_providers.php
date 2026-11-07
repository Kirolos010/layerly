<?php
define('APP_INIT', true);
require __DIR__ . '/../config.php';

$token = HUGGINGFACE_API_TOKEN;

// 1. Test HF router
$model = "black-forest-labs/FLUX.1-schnell";
$urls = [
    "https://router.huggingface.co/hf-inference/models/{$model}",
    "https://api-inference.huggingface.co/models/{$model}"
];

foreach ($urls as $url) {
    echo "Testing URL: $url\n";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(['inputs' => 'futuristic glowing cybernetic brain, clean tech poster background, no text']),
        CURLOPT_TIMEOUT => 15
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "Result: HTTP $code, Err: $err, bytes: " . strlen($res) . "\n";
    if ($code === 200) {
        echo "HF Success!\n";
        break;
    }
}

// 2. Test Pollinations.ai
echo "Testing Pollinations.ai...\n";
$pollUrl = "https://image.pollinations.ai/prompt/" . urlencode("cybernetic digital brain with glowing nodes, cyber dark tech, no text") . "?width=1080&height=1080&nologo=true";
$ch = curl_init($pollUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_FOLLOWLOCATION => true
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);
echo "Pollinations Result: HTTP $code, Err: $err, bytes: " . strlen($res) . "\n";

