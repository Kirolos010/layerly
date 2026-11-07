<?php
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/../config.php';
$_GET['secret'] = defined('DIAGNOSTICS_SECRET') ? DIAGNOSTICS_SECRET : '';
ob_start();
include __DIR__ . '/../diagnostics.php';
$html = ob_get_clean();

if (strpos($html, 'System Diagnostics') !== false && strpos($html, 'Passed') !== false) {
    echo "Diagnostics output verified: PASS\n";
    // Check key strings
    preg_match_all('/class="badge badge-([A-Z]+)"/', $html, $matches);
    print_r(array_count_values($matches[1]));
} else {
    echo "Diagnostics output failed!\n";
    echo substr($html, 0, 500);
}
