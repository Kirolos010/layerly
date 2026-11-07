<?php
define('APP_INIT', true);
require __DIR__ . '/../config.php';

$key = GEMINI_API_KEY;
$refImage = "C:/Users/Kirolos Samir0/.gemini/antigravity/brain/17b16749-06fc-422d-bb1e-f521886da31d/.user_uploaded/media_1794006378956_1c6f42a9.jpg";

$raw = file_get_contents($refImage);
$b64 = base64_encode($raw);

$testModels = [
    'gemini-3.8-flash',
    'gemini-3.5-flash',
    'gemini-3-flash-preview',
    'gemini-flash-latest',
    'gemini-2.5-flash-image'
];

foreach ($testModels as $m) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key=" . urlencode($key);
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'List the main headline text from this image and its language.'],
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
        CURLOPT_TIMEOUT => 20
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Model $m -> HTTP $code\n";
    if ($code === 200) {
        $json = json_decode($res, true);
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        echo "Response:\n$text\n";
        break;
    } else {
        echo "Error: " . substr($res, 0, 300) . "\n";
    }
}

