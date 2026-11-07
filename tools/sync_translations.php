<?php
/**
 * AI Design Studio - Tool: Sync & Verify Bilingual Translations
 * 
 * Verifies 100% key parity between lang/en.json and lang/ar.json.
 * Formats JSON with pretty-print and unescaped Unicode characters.
 */

define('APP_INIT', true);

$langDir = dirname(__DIR__) . '/lang';
$enFile = $langDir . '/en.json';
$arFile = $langDir . '/ar.json';

if (!file_exists($enFile) || !file_exists($arFile)) {
    echo "Error: Translation files not found in {$langDir}\n";
    exit(1);
}

$en = json_decode((string)file_get_contents($enFile), true);
$ar = json_decode((string)file_get_contents($arFile), true);

if (!is_array($en) || !is_array($ar)) {
    echo "Error: Failed to parse JSON files\n";
    exit(1);
}

$enKeys = array_keys($en);
$arKeys = array_keys($ar);

$missingInAr = array_diff($enKeys, $arKeys);
$missingInEn = array_diff($arKeys, $enKeys);

echo "========================================================\n";
echo "   AI Design Studio - Translation Parity Checker        \n";
echo "========================================================\n";
echo "English keys count: " . count($enKeys) . "\n";
echo "Arabic keys count:  " . count($arKeys) . "\n";

$hasMismatch = false;

if (!empty($missingInAr)) {
    $hasMismatch = true;
    echo "\n[FAIL] Missing in Arabic (" . count($missingInAr) . "):\n";
    foreach ($missingInAr as $k) {
        echo "  - {$k}\n";
    }
}

if (!empty($missingInEn)) {
    $hasMismatch = true;
    echo "\n[FAIL] Missing in English (" . count($missingInEn) . "):\n";
    foreach ($missingInEn as $k) {
        echo "  - {$k}\n";
    }
}

if (!$hasMismatch) {
    echo "\n[PASS] Perfect parity! 100% of keys match across languages.\n";
}

// Option to format/beautify in place if passed --format
if (in_array('--format', $argv ?? [], true)) {
    ksort($en);
    ksort($ar);
    file_put_contents($enFile, json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    file_put_contents($arFile, json_encode($ar, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    echo "Both files sorted and formatted successfully.\n";
}

exit($hasMismatch ? 1 : 0);

