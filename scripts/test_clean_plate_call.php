<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/ai-client.php';

$refImage = "C:/Users/Kirolos Samir0/.gemini/antigravity/brain/17b16749-06fc-422d-bb1e-f521886da31d/.user_uploaded/media_1794006378956_1c6f42a9.jpg";

echo "Testing ai_generate_clean_plate with forceFallback=true...\n";
$blocks = [
    ['id' => 'b1', 'left' => 104, 'top' => 36, 'width' => 808, 'height' => 65],
    ['id' => 'btn', 'left' => 60, 'top' => 500, 'width' => 164, 'height' => 36, 'badge' => ['left' => 60, 'top' => 500, 'width' => 164, 'height' => 36]]
];

$res = ai_generate_clean_plate($refImage, $blocks, 1024, 558, true);
echo "Clean plate result:\n";
print_r($res);
