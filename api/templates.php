<?php
/**
 * AI Design Studio - Templates API Endpoint
 * 
 * - GET /api/templates.php?action=list: List all available starter templates.
 * - GET /api/templates.php?action=get&id={template_id}: Retrieve and sanitize a specific template.
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/sanitizer.php';

send_security_headers();

$action = trim((string)($_GET['action'] ?? 'list'));
$templatesDir = dirname(__DIR__) . '/templates';

if ($action === 'list') {
    $files = glob($templatesDir . '/*.json') ?: [];
    $templates = [];

    foreach ($files as $file) {
        $content = @file_get_contents($file);
        if (!$content) continue;

        $data = json_decode($content, true);
        if (!$data || empty($data['canvas'])) continue;

        $templates[] = [
            'id'        => $data['id'] ?? basename($file, '.json'),
            'name_ar'   => $data['name_ar'] ?? 'قالب تصميم',
            'name_en'   => $data['name_en'] ?? 'Design Template',
            'category'  => $data['category'] ?? 'social',
            'language'  => $data['language'] ?? 'en',
            'tags'      => $data['tags'] ?? [],
            'width'     => (int)($data['canvas']['width'] ?? 1080),
            'height'    => (int)($data['canvas']['height'] ?? 1080),
            'background'=> (string)($data['canvas']['background'] ?? '#ffffff'),
            'objects_count' => count($data['objects'] ?? [])
        ];
    }

    json_response([
        'success'   => true,
        'templates' => $templates,
        'total'     => count($templates)
    ]);
}

if ($action === 'get') {
    $id = trim((string)($_GET['id'] ?? ''));

    // Validate ID to strictly avoid directory traversal
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
        json_response([
            'success' => false,
            'error'   => 'Invalid template identifier.',
            'code'    => 'INVALID_TEMPLATE_ID'
        ], 400);
    }

    $filePath = $templatesDir . '/' . $id . '.json';
    if (!file_exists($filePath) || !is_file($filePath)) {
        json_response([
            'success' => false,
            'error'   => 'Template not found.',
            'code'    => 'TEMPLATE_NOT_FOUND'
        ], 404);
    }

    $rawContent = file_get_contents($filePath);
    $templateData = json_decode($rawContent, true);

    if (!$templateData || empty($templateData['canvas'])) {
        json_response([
            'success' => false,
            'error'   => 'Corrupted template file.',
            'code'    => 'CORRUPTED_TEMPLATE'
        ], 500);
    }

    // Sanitize template design using the AI schema sanitizer
    $sanitized = sanitize_design_json($templateData);

    json_response([
        'success'  => true,
        'template' => [
            'id'       => $templateData['id'] ?? $id,
            'name_ar'  => $templateData['name_ar'] ?? '',
            'name_en'  => $templateData['name_en'] ?? '',
            'category' => $templateData['category'] ?? 'social',
            'language' => $templateData['language'] ?? 'en',
            'design'   => $sanitized
        ]
    ]);
}

json_response([
    'success' => false,
    'error'   => 'Unknown action. Allowed: list, get.',
    'code'    => 'INVALID_ACTION'
], 400);

