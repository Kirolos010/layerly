<?php
define('APP_INIT', true);
require __DIR__ . '/../config.php';

$key = GEMINI_API_KEY;
$refImage = "C:/Users/Kirolos Samir0/.gemini/antigravity/brain/17b16749-06fc-422d-bb1e-f521886da31d/.user_uploaded/media_1794006378956_1c6f42a9.jpg";

$raw = file_get_contents($refImage);
$b64 = base64_encode($raw);

$imgInfo = getimagesize($refImage);
$imgW = $imgInfo[0];
$imgH = $imgInfo[1];

$model = "gemini-3.5-flash";
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($key);

$prompt = "You are an expert graphic designer and OCR system.
Analyze this poster image ({$imgW}x{$imgH}).
Identify and transcribe EVERY text block on the image exactly as written.
Do not invent or translate text.

Output a valid JSON object with:
{
  \"canvas\": { \"width\": {$imgW}, \"height\": {$imgH}, \"bg_color\": \"#020b14\", \"theme\": \"dark\" },
  \"text_blocks\": [
    {
      \"id\": \"headline_1\",
      \"text\": \"دورة برمجة وتطوير الذكاء الاصطناعي\",
      \"lang\": \"ar\",
      \"dir\": \"rtl\",
      \"left\": 100,
      \"top\": 50,
      \"width\": 800,
      \"height\": 60,
      \"fontSize\": 42,
      \"fontWeight\": \"bold\",
      \"color\": \"#00f0ff\",
      \"glowColor\": \"#00c8ff\",
      \"glowBlur\": 12,
      \"align\": \"right\",
      \"fontFamily\": \"Cairo\",
      \"badge\": null
    }
  ]
}

Available fonts: Cairo, Tajawal, Almarai, Amiri, Reem Kufi, Poppins, Montserrat, Playfair Display, Roboto.
If text is inside a button/pill/badge (like 'احجز مكانك!' or 'www.AICourse2024.com'), set 'badge': { \"type\": \"pill\", \"fill\": \"#00e5ff\", \"radius\": 20, \"left\": ..., \"top\": ..., \"width\": ..., \"height\": ... }.
Calculate accurate pixel coordinates (left, top, width, height) relative to {$imgW}x{$imgH}.
Return ONLY the raw JSON block without markdown formatting or code fences.";

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
                [
                    'inlineData' => [
                        'mimeType' => 'image/jpeg',
                        'data' => $b64
                    ]
                ]
            ]
        ]
    ],
    'generationConfig' => [
        'responseMimeType' => 'application/json',
        'temperature' => 0.1
    ]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 45
]);
$t0 = microtime(true);
$res = curl_exec($ch);
$elapsed = round(microtime(true) - $t0, 2);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "Call completed in {$elapsed}s. HTTP $code (err: $err)\n";
if ($code === 200) {
    $json = json_decode($res, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    echo "Raw text length: " . strlen($text) . " bytes\n";
    echo "Response preview:\n" . substr($text, 0, 1000) . "\n";
    file_put_contents(__DIR__ . '/test_vision_result.json', $text);
}

