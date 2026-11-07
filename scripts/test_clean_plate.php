<?php
$refImage = 'C:/Users/Kirolos Samir0/.gemini/antigravity/brain/17b16749-06fc-422d-bb1e-f521886da31d/.user_uploaded/media_1794006378956_1c6f42a9.jpg';
$jsonFile = __DIR__ . '/test_vision_result.json';

if (!file_exists($refImage) || !file_exists($jsonFile)) {
    die("Missing files\n");
}

$data = json_decode(file_get_contents($jsonFile), true);
$img = imagecreatefromjpeg($refImage);
$width = imagesx($img);
$height = imagesy($img);

echo "Image dimensions: {$width}x{$height}\n";

// Function to inpaint a bounding box
function inpaint_box(&$img, $x, $y, $w, $h, $padding = 4) {
    $imgW = imagesx($img);
    $imgH = imagesy($img);

    $bx1 = max(0, $x - $padding);
    $by1 = max(0, $y - $padding);
    $bx2 = min($imgW - 1, $x + $w + $padding);
    $by2 = min($imgH - 1, $y + $h + $padding);

    $bw = $bx2 - $bx1 + 1;
    $bh = $by2 - $by1 + 1;

    // Sample border pixels (top, bottom, left, right perimeter)
    $borderColors = [];
    // Top & bottom edges (samples outside bx1..bx2)
    for ($px = $bx1; $px <= $bx2; $px += 2) {
        if ($by1 > 0) {
            $c = imagecolorat($img, $px, max(0, $by1 - 2));
            $borderColors['top'][$px] = ['r' => ($c >> 16) & 0xFF, 'g' => ($c >> 8) & 0xFF, 'b' => $c & 0xFF];
        }
        if ($by2 < $imgH - 1) {
            $c = imagecolorat($img, $px, min($imgH - 1, $by2 + 2));
            $borderColors['bottom'][$px] = ['r' => ($c >> 16) & 0xFF, 'g' => ($c >> 8) & 0xFF, 'b' => $c & 0xFF];
        }
    }
    // Left & right edges
    for ($py = $by1; $py <= $by2; $py += 2) {
        if ($bx1 > 0) {
            $c = imagecolorat($img, max(0, $bx1 - 2), $py);
            $borderColors['left'][$py] = ['r' => ($c >> 16) & 0xFF, 'g' => ($c >> 8) & 0xFF, 'b' => $c & 0xFF];
        }
        if ($bx2 < $imgW - 1) {
            $c = imagecolorat($img, min($imgW - 1, $bx2 + 2), $py);
            $borderColors['right'][$py] = ['r' => ($c >> 16) & 0xFF, 'g' => ($c >> 8) & 0xFF, 'b' => $c & 0xFF];
        }
    }

    // Bilinear / distance-weighted interpolation inside the box
    for ($py = $by1; $py <= $by2; $py++) {
        $vRatio = ($bh > 1) ? ($py - $by1) / ($bh - 1) : 0.5;
        for ($px = $bx1; $px <= $bx2; $px++) {
            $hRatio = ($bw > 1) ? ($px - $bx1) / ($bw - 1) : 0.5;

            // Interpolate from top/bottom and left/right
            $r_vert = 0; $g_vert = 0; $b_vert = 0; $has_vert = false;
            $sample_top = $borderColors['top'][$px] ?? null;
            $sample_bot = $borderColors['bottom'][$px] ?? null;
            if ($sample_top && $sample_bot) {
                $r_vert = $sample_top['r'] * (1 - $vRatio) + $sample_bot['r'] * $vRatio;
                $g_vert = $sample_top['g'] * (1 - $vRatio) + $sample_bot['g'] * $vRatio;
                $b_vert = $sample_top['b'] * (1 - $vRatio) + $sample_bot['b'] * $vRatio;
                $has_vert = true;
            } elseif ($sample_top) {
                $r_vert = $sample_top['r']; $g_vert = $sample_top['g']; $b_vert = $sample_top['b'];
                $has_vert = true;
            } elseif ($sample_bot) {
                $r_vert = $sample_bot['r']; $g_vert = $sample_bot['g']; $b_vert = $sample_bot['b'];
                $has_vert = true;
            }

            $r_horiz = 0; $g_horiz = 0; $b_horiz = 0; $has_horiz = false;
            $sample_left = $borderColors['left'][$py] ?? null;
            $sample_right = $borderColors['right'][$py] ?? null;
            if ($sample_left && $sample_right) {
                $r_horiz = $sample_left['r'] * (1 - $hRatio) + $sample_right['r'] * $hRatio;
                $g_horiz = $sample_left['g'] * (1 - $hRatio) + $sample_right['g'] * $hRatio;
                $b_horiz = $sample_left['b'] * (1 - $hRatio) + $sample_right['b'] * $hRatio;
                $has_horiz = true;
            } elseif ($sample_left) {
                $r_horiz = $sample_left['r']; $g_horiz = $sample_left['g']; $b_horiz = $sample_left['b'];
                $has_horiz = true;
            } elseif ($sample_right) {
                $r_horiz = $sample_right['r']; $g_horiz = $sample_right['g']; $b_horiz = $sample_right['b'];
                $has_horiz = true;
            }

            if ($has_vert && $has_horiz) {
                $finalR = round(($r_vert + $r_horiz) / 2);
                $finalG = round(($g_vert + $g_horiz) / 2);
                $finalB = round(($b_vert + $b_horiz) / 2);
            } elseif ($has_vert) {
                $finalR = round($r_vert); $finalG = round($g_vert); $finalB = round($b_vert);
            } elseif ($has_horiz) {
                $finalR = round($r_horiz); $finalG = round($g_horiz); $finalB = round($b_horiz);
            } else {
                continue;
            }

            $col = imagecolorallocate($img, $finalR, $finalG, $finalB);
            imagesetpixel($img, $px, $py, $col);
        }
    }

    // Apply mild feather/blur over the inpaint rectangle region
    // Extract region, blur, copy back with feather alpha at boundaries
    $region = imagecreatetruecolor($bw, $bh);
    imagecopy($region, $img, 0, 0, $bx1, $by1, $bw, $bh);
    imagefilter($region, IMG_FILTER_GAUSSIAN_BLUR);
    imagefilter($region, IMG_FILTER_SMOOTH, 2);
    imagecopy($img, $region, $bx1, $by1, 0, 0, $bw, $bh);
    imagedestroy($region);
}

// Inpaint every text block and badge
foreach ($data['text_blocks'] as $block) {
    echo "Inpainting: {$block['id']} ({$block['left']}, {$block['top']}, {$block['width']}, {$block['height']})\n";
    inpaint_box($img, $block['left'], $block['top'], $block['width'], $block['height'], 6);
    if (!empty($block['badge'])) {
        $badge = $block['badge'];
        echo "Inpainting badge: {$block['id']} ({$badge['left']}, {$badge['top']}, {$badge['width']}, {$badge['height']})\n";
        inpaint_box($img, $badge['left'], $badge['top'], $badge['width'], $badge['height'], 8);
    }
}

$outPath = __DIR__ . '/test_clean_plate_output.jpg';
imagejpeg($img, $outPath, 92);
imagedestroy($img);
echo "Clean plate saved to {$outPath}\n";

