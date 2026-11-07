<?php
/**
 * AI Design Studio - Automated Test Runner
 * Executes all feature test suites and aggregates results.
 */

define('APP_INIT', true);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('403 Forbidden - Tests can only be executed via CLI.');
}

$tests = [
    'Diagnostics'            => 'diagnostics_test.php',
    'HTTP Endpoints'         => 'http_endpoints_test.php',
    'Design Engine'          => 'design_engine_test.php',
    'Auth & History'         => 'auth_and_history_test.php',
    'Editor Integration'     => 'editor_integration_test.php',
    'Export & Templates'     => 'export_and_templates_test.php',
    'i18n & Glass UI'        => 'i18n_and_glass_ui_test.php',
    'Hybrid & Vision'        => 'hybrid_vision_test.php',
    'Poster Pipeline'        => 'poster_pipeline_test.php',
    'Link Integrity Crawler' => 'link_check_test.php',
    'Security Access'        => 'security_access_test.php',
];

echo "========================================================\n";
echo "   AI Design Studio - Full Test Suite Runner            \n";
echo "========================================================\n\n";

$allPassed = true;
$results = [];

foreach ($tests as $title => $file) {
    echo ">>> Running: {$title} ({$file})...\n";
    $cmd = "php " . escapeshellarg(__DIR__ . '/' . $file);
    passthru($cmd, $code);
    $passed = ($code === 0);
    $results[$title] = $passed;
    if (!$passed) {
        $allPassed = false;
    }
    echo "\n--------------------------------------------------------\n\n";
}

echo "========================================================\n";
echo "                   FINAL TEST SUMMARY                   \n";
echo "========================================================\n";
foreach ($results as $title => $passed) {
    $status = $passed ? "[PASS]" : "[FAIL]";
    echo str_pad($title, 35) . " {$status}\n";
}
echo "========================================================\n";

if ($allPassed) {
    echo " 🎉 ALL FEATURE TEST SUITES PASSED SUCCESSFULLY! 🎉\n";
    exit(0);
} else {
    echo " ⚠️ SOME TESTS FAILED. Check logs above.\n";
    exit(1);
}

