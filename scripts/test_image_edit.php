<?php
define('APP_INIT', true);
require __DIR__ . '/../config.php';

$key = GEMINI_API_KEY;
$refImage = "C:/Users/Kirolos Samir0/.gemini/antigravity/brain/17b16749-06fc-422d-bb1e-f521886da31d/.user_uploaded/media_1794006378956_1c6f42a9.jpg";
$raw = file_get_contents($refImage);
$b64 = base64_encode($raw);

$imageModels = [
    'gemini-2.5-flash-image',
    'gemini-3.1-flash-image',
    'gemini-3.1-flash-image-preview',
    'nano-banana-pro-preview'
];

foreach ($imageModels as $m) {
    echo "--- Testing $m ---\n";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key=" . urlencode($key);
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'remove all text, letters, numbers, URLs and badges from this image, keep everything else pixel-identical, same aspect ratio'],
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
        CURLOPT_TIMEOUT => 25
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Model $m -> HTTP $code\n";
    $json = json_decode($res, true);
    if ($code === 200) {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        echo "Candidate parts count: " . count($parts) . "\n";
        foreach ($parts as $p) {
            if (isset($p['inlineData'])) {
                echo "Found inlineData output! mimeType: {$p['inlineData']['mimeType']}, size: " . strlen($p['inlineData']['data']) . "\n";
            }
            if (isset($p['text'])) {
                echo "Text part: " . substr($p['text'], 0, 200) . "\n";
            }
        }
        break;
    } else {
        echo "Error response: " . substr($res, 0, 300) . "\n";
    }
}

