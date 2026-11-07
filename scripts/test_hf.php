<?php
define('APP_INIT', true);
require __DIR__ . '/../config.php';

$token = HUGGINGFACE_API_TOKEN;
echo "HF Token: " . substr($token, 0, 8) . "...\n";

// Test Hugging Face FLUX or SD
$model = "black-forest-labs/FLUX.1-schnell";
$url = "https://api-inference.huggingface.co/models/{$model}";

$payload = [
    'inputs' => 'A cybernetic digital brain with glowing nodes and futuristic developer at workstation, 3d render, dark tech background, no text, no words'
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 25
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HF FLUX Test -> HTTP $code\n";
if ($err) echo "cURL Error: $err\n";
echo "Response length: " . strlen($res) . " bytes\n";
if ($code !== 200) {
    echo "Response: " . substr($res, 0, 400) . "\n";
}

