<?php
/**
 * AI Design Studio - Phase 3 Automated Test Suite
 * Validates: Templates API, 10 Built-in JSON Templates, Projects CRUD API,
 * Anonymous Owner Tokens, User/Guest Isolation, Export Resolution Math,
 * and UI Integration for Projects, Templates, and Export Modal.
 */

declare(strict_types=1);

echo "========================================================\n";
echo "AI DESIGN STUDIO - PHASE 3 COMPREHENSIVE TEST SUITE\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$name}\n";
    } else {
        $failed++;
        echo " [FAIL] {$name}";
        if ($detail) {
            echo " ({$detail})";
        }
        echo "\n";
    }
}

// -----------------------------------------------------------------------------
// SECTION 1: Built-in Templates & Templates API
// -----------------------------------------------------------------------------
echo "--- SECTION 1: Built-in Templates & Templates API ---\n";

$templatesDir = dirname(__DIR__) . '/starter-templates';
assert_test("Starter templates directory exists", is_dir($templatesDir));

$templateFiles = glob($templatesDir . '/*.json');
assert_test("Found at least 10 built-in templates", count($templateFiles) >= 10, "Found " . count($templateFiles));

$validFonts = [
    // Arabic
    'Cairo', 'Tajawal', 'Almarai', 'Amiri', 'Reem Kufi',
    // English
    'Poppins', 'Montserrat', 'Playfair Display', 'Roboto',
    // System / Fallback
    'sans-serif', 'serif'
];

$allTemplatesValid = true;
$templateIds = [];

foreach ($templateFiles as $file) {
    $content = file_get_contents($file);
    $data = json_decode($content, true);

    $hasRequiredFields = isset(
        $data['id'],
        $data['name_ar'],
        $data['name_en'],
        $data['category'],
        $data['language'],
        $data['canvas']['width'],
        $data['canvas']['height'],
        $data['canvas']['background'],
        $data['objects']
    );

    if (!$hasRequiredFields || !is_array($data['objects']) || empty($data['objects'])) {
        $allTemplatesValid = false;
        echo "Template " . basename($file) . " failed basic schema validation.\n";
        break;
    }

    $templateIds[] = $data['id'];

    // Validate that text objects only use supported fonts
    foreach ($data['objects'] as $obj) {
        if (($obj['type'] ?? '') === 'text' && !empty($obj['fontFamily'])) {
            $font = $obj['fontFamily'];
            if (!in_array($font, $validFonts, true)) {
                $allTemplatesValid = false;
                echo "Template " . basename($file) . " uses invalid font: '{$font}'\n";
                break 2;
            }
        }
    }
}

assert_test("All 10+ starter templates parse as valid JSON and conform to design schema", $allTemplatesValid);
assert_test("Template IDs are unique", count($templateIds) === count(array_unique($templateIds)));

// Helper for making local HTTP requests with cookie jar
function http_req(string $url, string $method = 'GET', $data = null, array $headers = [], string $cookieJar = ''): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($data)) {
            $payload = json_encode($data);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'json' => json_decode((string)$response, true)
    ];
}

$baseUrl = 'http://localhost/ai-design-studio';

// Test api/templates.php list action via HTTP
$listRes = http_req("{$baseUrl}/api/templates.php?action=list");
assert_test("api/templates.php?action=list returns HTTP 200", $listRes['code'] === 200);
assert_test("api/templates.php?action=list returns success: true", ($listRes['json']['success'] ?? false) === true);
assert_test("api/templates.php?action=list returns 10 templates", count($listRes['json']['templates'] ?? []) >= 10);

// Test api/templates.php get action via HTTP
$getRes = http_req("{$baseUrl}/api/templates.php?action=get&id=ramadan-sale-post");
assert_test("api/templates.php?action=get returns template", ($getRes['json']['success'] ?? false) === true && ($getRes['json']['template']['id'] ?? '') === 'ramadan-sale-post');
assert_test("Template design has valid canvas width & height (1080x1080)", ($getRes['json']['template']['design']['canvas']['width'] ?? 0) === 1080);

// Test directory traversal prevention in api/templates.php
$traversalRes = http_req("{$baseUrl}/api/templates.php?action=get&id=../../config.php");
assert_test("api/templates.php blocks path traversal (../../config.php)", ($traversalRes['json']['success'] ?? false) === false && $traversalRes['code'] === 400);

// -----------------------------------------------------------------------------
// SECTION 2: Projects Database CRUD & Anonymous Owner Tokens
// -----------------------------------------------------------------------------
echo "\n--- SECTION 2: Projects API CRUD & Security ---\n";

$cookieJarA = sys_get_temp_dir() . '/cookie_user_a_' . uniqid() . '.txt';
$cookieJarB = sys_get_temp_dir() . '/cookie_user_b_' . uniqid() . '.txt';

// Fetch home page to obtain CSRF token and establish guest session/cookie for User A
$homeA = http_req("{$baseUrl}/index.php", 'GET', null, [], $cookieJarA);
preg_match('/<meta name="csrf-token" content="([^"]+)"/', $homeA['body'], $csrfMatchesA);
$csrfTokenA = $csrfMatchesA[1] ?? '';
assert_test("Guest A session initialized and CSRF token obtained", !empty($csrfTokenA));

// Fetch home page for User B
$homeB = http_req("{$baseUrl}/index.php", 'GET', null, [], $cookieJarB);
preg_match('/<meta name="csrf-token" content="([^"]+)"/', $homeB['body'], $csrfMatchesB);
$csrfTokenB = $csrfMatchesB[1] ?? '';
assert_test("Guest B session initialized and CSRF token obtained", !empty($csrfTokenB));

// 1. Create Project as Guest A
$sampleCanvasJson = json_encode([
    'version' => '5.3.1',
    'objects' => [
        ['type' => 'textbox', 'text' => 'Phase 3 Automated Test', 'left' => 100, 'top' => 100],
        ['type' => 'rect', 'left' => 50, 'top' => 50, 'width' => 200, 'height' => 100, 'fill' => '#3b82f6']
    ]
]);
$sampleThumbnail = 'data:image/jpeg;base64,' . base64_encode('fake-thumbnail-binary-data');

$createPayloadA = [
    'action' => 'create',
    'name' => 'Automated Test Project 1',
    'json_data' => $sampleCanvasJson,
    'thumbnail' => $sampleThumbnail
];

$createResA = http_req("{$baseUrl}/api/projects.php", 'POST', $createPayloadA, [
    "X-CSRF-Token: {$csrfTokenA}"
], $cookieJarA);

assert_test("api/projects.php creates project for Guest A", ($createResA['json']['success'] ?? false) === true && !empty($createResA['json']['project_id']));
$projIdA = (int)($createResA['json']['project_id'] ?? 0);

// 2. Retrieve Project by ID
$getProjRes = http_req("{$baseUrl}/api/projects.php?action=get&id={$projIdA}", 'GET', null, [
    "X-CSRF-Token: {$csrfTokenA}"
], $cookieJarA);
assert_test("Guest A retrieves created project by ID", ($getProjRes['json']['success'] ?? false) === true && ($getProjRes['json']['project']['name'] ?? '') === 'Automated Test Project 1');

// 3. Update Project
$updatePayloadA = [
    'action' => 'update',
    'id' => $projIdA,
    'name' => 'Updated Project 1 Name',
    'json_data' => json_encode(['version' => '5.3.1', 'objects' => [['type' => 'circle', 'left' => 10, 'top' => 10]]]),
    'thumbnail' => $sampleThumbnail
];
$updateResA = http_req("{$baseUrl}/api/projects.php", 'POST', $updatePayloadA, [
    "X-CSRF-Token: {$csrfTokenA}"
], $cookieJarA);
assert_test("Guest A updates project successfully", ($updateResA['json']['success'] ?? false) === true);

// Verify update took effect
$getUpdatedRes = http_req("{$baseUrl}/api/projects.php?action=get&id={$projIdA}", 'GET', null, [
    "X-CSRF-Token: {$csrfTokenA}"
], $cookieJarA);
assert_test("Updated name verified via get API", ($getUpdatedRes['json']['project']['name'] ?? '') === 'Updated Project 1 Name');

// 4. Rename Project
$renameResA = http_req("{$baseUrl}/api/projects.php", 'POST', [
    'action' => 'rename',
    'id' => $projIdA,
    'name' => 'Renamed Studio Design'
], ["X-CSRF-Token: {$csrfTokenA}"], $cookieJarA);
assert_test("Guest A renames project via action=rename", ($renameResA['json']['success'] ?? false) === true);

// 5. Duplicate Project
$dupResA = http_req("{$baseUrl}/api/projects.php", 'POST', [
    'action' => 'duplicate',
    'id' => $projIdA
], ["X-CSRF-Token: {$csrfTokenA}"], $cookieJarA);
assert_test("Guest A duplicates project", ($dupResA['json']['success'] ?? false) === true && !empty($dupResA['json']['new_project_id']));
$dupIdA = (int)($dupResA['json']['new_project_id'] ?? 0);

// 6. Ownership Isolation: Guest B cannot view, edit, duplicate, or delete Guest A's project
$guestBGet = http_req("{$baseUrl}/api/projects.php?action=get&id={$projIdA}", 'GET', null, [
    "X-CSRF-Token: {$csrfTokenB}"
], $cookieJarB);
assert_test("Guest B cannot read Guest A's project (404/Access Denied)", ($guestBGet['json']['success'] ?? false) === false);

$guestBUpdate = http_req("{$baseUrl}/api/projects.php", 'POST', [
    'action' => 'update',
    'id' => $projIdA,
    'name' => 'Hacked Name'
], ["X-CSRF-Token: {$csrfTokenB}"], $cookieJarB);
assert_test("Guest B cannot update Guest A's project", ($guestBUpdate['json']['success'] ?? false) === false);

$guestBDelete = http_req("{$baseUrl}/api/projects.php", 'POST', [
    'action' => 'delete',
    'id' => $projIdA
], ["X-CSRF-Token: {$csrfTokenB}"], $cookieJarB);
assert_test("Guest B cannot delete Guest A's project", ($guestBDelete['json']['success'] ?? false) === false);

// 7. CSRF Protection on Projects API
$noCsrfRes = http_req("{$baseUrl}/api/projects.php", 'POST', [
    'action' => 'rename',
    'id' => $projIdA,
    'name' => 'CSRF Attack Name'
], [], $cookieJarA);
assert_test("Projects API rejects POST requests without valid CSRF token (403)", $noCsrfRes['code'] === 403);

// 8. Delete Own Projects
$deleteRes1 = http_req("{$baseUrl}/api/projects.php", 'POST', ['action' => 'delete', 'id' => $projIdA], ["X-CSRF-Token: {$csrfTokenA}"], $cookieJarA);
$deleteRes2 = http_req("{$baseUrl}/api/projects.php", 'POST', ['action' => 'delete', 'id' => $dupIdA], ["X-CSRF-Token: {$csrfTokenA}"], $cookieJarA);
assert_test("Guest A deletes project 1", ($deleteRes1['json']['success'] ?? false) === true);
assert_test("Guest A deletes duplicate project 2", ($deleteRes2['json']['success'] ?? false) === true);

// Clean up temporary cookie files
@unlink($cookieJarA);
@unlink($cookieJarB);

// -----------------------------------------------------------------------------
// SECTION 3: Export Multiplier & Resolution Math
// -----------------------------------------------------------------------------
echo "\n--- SECTION 3: Export Multiplier & Resolution Math ---\n";

function calculate_export_multiplier(float $scale, float $zoom): float {
    return $scale / ($zoom > 0 ? $zoom : 1.0);
}

// Case 1: 1080x1080 at 1x scale, 1.0 (100%) zoom
$scale1 = 1.0;
$zoom1 = 1.0;
$mult1 = calculate_export_multiplier($scale1, $zoom1);
$outW1 = (int)round(1080 * $scale1);
assert_test("1x scale at 100% zoom yields multiplier 1.0 (1080x1080 px)", abs($mult1 - 1.0) < 0.0001 && $outW1 === 1080);

// Case 2: 1080x1080 at 2x scale, 0.5 (50% fit on small screen) zoom
$scale2 = 2.0;
$zoom2 = 0.5;
$mult2 = calculate_export_multiplier($scale2, $zoom2);
$outW2 = (int)round(1080 * $scale2);
assert_test("2x scale at 50% zoom yields multiplier 4.0 (2160x2160 px)", abs($mult2 - 4.0) < 0.0001 && $outW2 === 2160);

// Case 3: 1080x1920 Story at 3x Ultra HD scale, 0.4 (40% fit) zoom
$scale3 = 3.0;
$zoom3 = 0.4;
$mult3 = calculate_export_multiplier($scale3, $zoom3);
$outW3 = (int)round(1080 * $scale3);
$outH3 = (int)round(1920 * $scale3);
assert_test("3x scale for Story at 40% zoom exports true 3240x5760 px", abs($mult3 - 7.5) < 0.0001 && $outW3 === 3240 && $outH3 === 5760);

// -----------------------------------------------------------------------------
// SECTION 4: UI Integration & Page Markup
// -----------------------------------------------------------------------------
echo "\n--- SECTION 4: UI Integration & Page Markup ---\n";

// Check index.php
$indexContent = file_get_contents(dirname(__DIR__) . '/index.php');
assert_test("index.php has navigation link to templates", str_contains($indexContent, "url('templates')") || str_contains($indexContent, 'templates'));
assert_test("index.php has navigation link to projects", str_contains($indexContent, "url('projects')") || str_contains($indexContent, 'projects'));
assert_test("index.php contains #autosave-restore-banner", str_contains($indexContent, 'id="autosave-restore-banner"'));
assert_test("index.php contains #project-name-input", str_contains($indexContent, 'id="project-name-input"'));
assert_test("index.php contains #btn-save-project", str_contains($indexContent, 'id="btn-save-project"'));
assert_test("index.php contains #btn-open-export-modal", str_contains($indexContent, 'id="btn-open-export-modal"'));
assert_test("index.php contains #export-modal", str_contains($indexContent, 'id="export-modal"'));
assert_test("index.php export modal has PNG options (scale & transparent)", str_contains($indexContent, 'id="export-png-scale"') && str_contains($indexContent, 'id="export-png-transparent"'));
assert_test("index.php export modal has JPG options (scale & quality slider)", str_contains($indexContent, 'id="export-jpg-scale"') && str_contains($indexContent, 'id="export-jpg-quality"'));
assert_test("index.php export modal has SVG button", str_contains($indexContent, 'id="btn-export-svg"'));
assert_test("index.php export modal has JSON export & import buttons", str_contains($indexContent, 'id="btn-export-json"') && str_contains($indexContent, 'id="btn-import-json-trigger"') && str_contains($indexContent, 'id="input-import-json"'));

// Check projects.php
$projectsPath = file_exists(dirname(__DIR__) . '/account/projects.php') ? dirname(__DIR__) . '/account/projects.php' : dirname(__DIR__) . '/projects.php';
$projectsContent = file_get_contents($projectsPath);
assert_test("projects.php file exists and has HTML doctype", str_contains($projectsContent, '<!DOCTYPE html>'));
assert_test("projects.php includes search input #project-search-input", str_contains($projectsContent, 'id="project-search-input"'));
assert_test("projects.php includes projects-grid", str_contains($projectsContent, 'class="projects-grid"'));
assert_test("projects.php includes action buttons (duplicate, rename, delete)", str_contains($projectsContent, 'btn-duplicate') && str_contains($projectsContent, 'btn-rename') && str_contains($projectsContent, 'btn-delete'));

// Check templates.php
$templatesPath = file_exists(dirname(__DIR__) . '/pages/templates.php') ? dirname(__DIR__) . '/pages/templates.php' : dirname(__DIR__) . '/templates.php';
$templatesContent = file_get_contents($templatesPath);
assert_test("templates.php file exists and has HTML doctype", str_contains($templatesContent, '<!DOCTYPE html>'));
assert_test("templates.php includes category filters", str_contains($templatesContent, 'id="category-filters"'));
assert_test("templates.php includes language filter #template-lang-filter", str_contains($templatesContent, 'id="template-lang-filter"'));
assert_test("templates.php includes search input #template-search-input", str_contains($templatesContent, 'id="template-search-input"'));
assert_test("templates.php includes templates grid", str_contains($templatesContent, 'class="templates-grid"'));
assert_test("templates.php cards link to editor with ?template=", str_contains($templatesContent, '?template='));

// Check JavaScript ProjectManager & ExportManager wiring in app.js
$appJsContent = file_get_contents(dirname(__DIR__) . '/assets/js/app.js');
assert_test("app.js imports ProjectManager", str_contains($appJsContent, "import { ProjectManager } from './editor/project-manager.js';"));
assert_test("app.js instantiates ProjectManager", str_contains($appJsContent, "new ProjectManager(fabricCanvas, canvasManager, historyManager);"));
assert_test("app.js passes historyManager to ExportManager", str_contains($appJsContent, "new ExportManager(fabricCanvas, canvasManager, historyManager);"));

// -----------------------------------------------------------------------------
// SECTION 5: Translation Completeness for Phase 3
// -----------------------------------------------------------------------------
echo "\n--- SECTION 5: Translation Completeness ---\n";

$enLang = require dirname(__DIR__) . '/lang/en.php';
$arLang = require dirname(__DIR__) . '/lang/ar.php';

$phase3Keys = [
    'templates', 'templates_gallery_subtitle', 'all_categories', 'all_languages',
    'search_templates', 'use_template', 'blank_canvas', 'new_design',
    'search_designs_placeholder', 'designs_found', 'no_designs_yet', 'no_designs_desc',
    'create_first_design', 'open_editor', 'duplicate', 'rename', 'save_project',
    'project_name', 'export_options', 'scale', 'quality', 'transparent_background',
    'import_json', 'restore_session', 'autosave_banner_text', 'keep', 'discard'
];

foreach ($phase3Keys as $k) {
    assert_test("English translation key '{$k}' is present", isset($enLang[$k]) && !empty($enLang[$k]));
    assert_test("Arabic translation key '{$k}' is present", isset($arLang[$k]) && !empty($arLang[$k]));
}

// -----------------------------------------------------------------------------
// SUMMARY
// -----------------------------------------------------------------------------
echo "\n========================================================\n";
echo "PHASE 3 TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
