<?php
/**
 * AI Design Studio - Saved Projects API Endpoint
 * 
 * Manages saved designs in MySQL for both authenticated users and anonymous guests.
 * Actions: list, get, create, update, duplicate, rename, delete.
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/sanitizer.php';

send_security_headers();
init_secure_session();

const MAX_JSON_DATA_BYTES = 5242880; // 5 MB
const MAX_THUMBNAIL_BYTES = 524288;  // 512 KB

$ownerToken = get_or_create_guest_token();
$isLoggedIn = is_logged_in();
$userId = $isLoggedIn ? (int)$_SESSION['user_id'] : null;

$input = get_json_input() ?: $_POST;
$action = trim((string)($_GET['action'] ?? ($input['action'] ?? 'list')));

// Helper to check ownership and retrieve project
function get_owned_project(int $projectId, ?int $userId, string $ownerToken): ?array {
    $db = get_db();
    if ($userId !== null) {
        $stmt = $db->prepare("
            SELECT * FROM projects 
            WHERE id = ? AND (user_id = ? OR owner_token = ?)
            LIMIT 1
        ");
        $stmt->execute([$projectId, $userId, $ownerToken]);
    } else {
        $stmt = $db->prepare("
            SELECT * FROM projects 
            WHERE id = ? AND owner_token = ? AND user_id IS NULL
            LIMIT 1
        ");
        $stmt->execute([$projectId, $ownerToken]);
    }
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// -----------------------------------------------------------------------------
// GET: List Projects
// -----------------------------------------------------------------------------
if ($action === 'list') {
    $db = get_db();

    if ($userId !== null) {
        $stmt = $db->prepare("
            SELECT id, name, thumbnail, created_at, updated_at 
            FROM projects 
            WHERE user_id = ? OR owner_token = ?
            ORDER BY updated_at DESC 
            LIMIT 100
        ");
        $stmt->execute([$userId, $ownerToken]);
    } else {
        $stmt = $db->prepare("
            SELECT id, name, thumbnail, created_at, updated_at 
            FROM projects 
            WHERE owner_token = ? AND user_id IS NULL
            ORDER BY updated_at DESC 
            LIMIT 100
        ");
        $stmt->execute([$ownerToken]);
    }

    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    json_response([
        'success'  => true,
        'projects' => $projects,
        'total'    => count($projects)
    ]);
}

// -----------------------------------------------------------------------------
// GET: Retrieve Specific Project
// -----------------------------------------------------------------------------
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(['success' => false, 'error' => 'Invalid project ID.'], 400);
    }

    $project = get_owned_project($id, $userId, $ownerToken);
    if (!$project) {
        json_response([
            'success' => false,
            'error'   => 'Project not found or you do not have permission to access it.',
            'code'    => 'PROJECT_NOT_FOUND'
        ], 404);
    }

    json_response([
        'success' => true,
        'project' => [
            'id'         => (int)$project['id'],
            'name'       => $project['name'],
            'json_data'  => $project['json_data'],
            'thumbnail'  => $project['thumbnail'],
            'created_at' => $project['created_at'],
            'updated_at' => $project['updated_at']
        ]
    ]);
}

// -----------------------------------------------------------------------------
// Mutations (POST only, CSRF protected)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method Not Allowed.'], 405);
}

verify_csrf_or_exit();
$input = get_json_input() ?: $_POST;

// 1. Create Project
if ($action === 'create') {
    $name = trim((string)($input['name'] ?? 'Untitled Design'));
    if (empty($name)) {
        $name = 'Untitled Design';
    }
    if (mb_strlen($name) > 255) {
        $name = mb_substr($name, 0, 255);
    }

    $jsonData = trim((string)($input['json_data'] ?? ''));
    if (empty($jsonData)) {
        json_response(['success' => false, 'error' => 'Project design data is required.'], 422);
    }
    if (strlen($jsonData) > MAX_JSON_DATA_BYTES) {
        json_response(['success' => false, 'error' => 'Project design data exceeds 5MB size limit.'], 413);
    }

    // Verify valid JSON
    json_decode($jsonData);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_response(['success' => false, 'error' => 'Invalid JSON structure provided.'], 422);
    }

    $thumbnail = trim((string)($input['thumbnail'] ?? ''));
    if (!empty($thumbnail) && strlen($thumbnail) > MAX_THUMBNAIL_BYTES) {
        $thumbnail = null; // Discard oversized thumbnail gracefully
    }

    $db = get_db();
    $stmt = $db->prepare("
        INSERT INTO projects (user_id, owner_token, name, json_data, thumbnail, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$userId, $ownerToken, $name, $jsonData, $thumbnail]);

    $newId = (int)$db->lastInsertId();

    json_response([
        'success'    => true,
        'message'    => 'Project saved successfully.',
        'project_id' => $newId,
        'name'       => $name
    ]);
}

// 2. Update Project
if ($action === 'update') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        json_response(['success' => false, 'error' => 'Invalid project ID.'], 400);
    }

    $existing = get_owned_project($id, $userId, $ownerToken);
    if (!$existing) {
        json_response(['success' => false, 'error' => 'Project not found or unauthorized.'], 404);
    }

    $name = trim((string)($input['name'] ?? $existing['name']));
    if (empty($name)) {
        $name = $existing['name'];
    }
    if (mb_strlen($name) > 255) {
        $name = mb_substr($name, 0, 255);
    }

    $jsonData = trim((string)($input['json_data'] ?? $existing['json_data']));
    if (strlen($jsonData) > MAX_JSON_DATA_BYTES) {
        json_response(['success' => false, 'error' => 'Project design data exceeds 5MB limit.'], 413);
    }

    $thumbnail = isset($input['thumbnail']) ? trim((string)$input['thumbnail']) : $existing['thumbnail'];
    if (!empty($thumbnail) && strlen($thumbnail) > MAX_THUMBNAIL_BYTES) {
        $thumbnail = $existing['thumbnail'];
    }

    $db = get_db();
    $stmt = $db->prepare("
        UPDATE projects 
        SET name = ?, json_data = ?, thumbnail = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$name, $jsonData, $thumbnail, $id]);

    json_response([
        'success'    => true,
        'message'    => 'Project updated successfully.',
        'project_id' => $id,
        'name'       => $name
    ]);
}

// 3. Duplicate Project
if ($action === 'duplicate') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        json_response(['success' => false, 'error' => 'Invalid project ID.'], 400);
    }

    $orig = get_owned_project($id, $userId, $ownerToken);
    if (!$orig) {
        json_response(['success' => false, 'error' => 'Project not found or unauthorized.'], 404);
    }

    $copyName = 'Copy of ' . $orig['name'];
    if (mb_strlen($copyName) > 255) {
        $copyName = mb_substr($copyName, 0, 255);
    }

    $db = get_db();
    $stmt = $db->prepare("
        INSERT INTO projects (user_id, owner_token, name, json_data, thumbnail, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$userId, $ownerToken, $copyName, $orig['json_data'], $orig['thumbnail']]);

    $newId = (int)$db->lastInsertId();

    json_response([
        'success'        => true,
        'message'        => 'Project duplicated successfully.',
        'project_id'     => $newId,
        'new_project_id' => $newId,
        'name'           => $copyName
    ]);
}

// 4. Rename Project
if ($action === 'rename') {
    $id = (int)($input['id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));

    if ($id <= 0 || empty($name)) {
        json_response(['success' => false, 'error' => 'Valid ID and non-empty name are required.'], 422);
    }
    if (mb_strlen($name) > 255) {
        $name = mb_substr($name, 0, 255);
    }

    $existing = get_owned_project($id, $userId, $ownerToken);
    if (!$existing) {
        json_response(['success' => false, 'error' => 'Project not found or unauthorized.'], 404);
    }

    $db = get_db();
    $stmt = $db->prepare("UPDATE projects SET name = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$name, $id]);

    json_response([
        'success'    => true,
        'message'    => 'Project renamed successfully.',
        'project_id' => $id,
        'name'       => $name
    ]);
}

// 5. Delete Project
if ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        json_response(['success' => false, 'error' => 'Invalid project ID.'], 400);
    }

    $existing = get_owned_project($id, $userId, $ownerToken);
    if (!$existing) {
        json_response(['success' => false, 'error' => 'Project not found or unauthorized.'], 404);
    }

    $db = get_db();
    $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$id]);

    json_response([
        'success'    => true,
        'message'    => 'Project deleted successfully.',
        'project_id' => $id
    ]);
}

json_response([
    'success' => false,
    'error'   => 'Unknown action requested.'
], 400);
