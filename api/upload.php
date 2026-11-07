<?php
/**
 * AI Design Studio - Safe Image Upload & Delivery Endpoint
 * 
 * - POST /api/upload.php: Uploads an image (max 5MB, finfo MIME verification, randomized name).
 * - GET  /api/upload.php?file=img_xxx.png: Safely streams uploaded images with CORS headers.
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/auth.php';

const MAX_UPLOAD_BYTES = 5242880; // 5 MB

const ALLOWED_UPLOAD_MIMES = [
    'image/jpeg'    => 'jpg',
    'image/png'     => 'png',
    'image/webp'    => 'webp',
    'image/gif'     => 'gif',
    'image/svg+xml' => 'svg'
];

// -----------------------------------------------------------------------------
// GET: Serve Uploaded Image Safely
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $file = trim((string)($_GET['file'] ?? ''));

    // Strict regex check: prevent directory traversal
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|webp|gif|svg)$/i', $file)) {
        http_response_code(404);
        exit('File not found.');
    }

    $uploadDir = dirname(__DIR__) . '/storage/uploads';
    $filePath = $uploadDir . '/' . $file;

    if (!file_exists($filePath) || !is_file($filePath)) {
        http_response_code(404);
        exit('File not found.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($filePath);
    if (!isset(ALLOWED_UPLOAD_MIMES[$mime])) {
        $mime = 'application/octet-stream';
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Access-Control-Allow-Origin: *');
    header('X-Content-Type-Options: nosniff');

    readfile($filePath);
    exit;
}

// -----------------------------------------------------------------------------
// POST: Process Image Upload
// -----------------------------------------------------------------------------
send_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method Not Allowed.'], 405);
}

verify_csrf_or_exit();

// Check uploaded file
$fileData = $_FILES['image'] ?? ($_FILES['file'] ?? null);
if (!$fileData || !isset($fileData['error'])) {
    json_response(['success' => false, 'error' => 'No image file uploaded.'], 422);
}

if ($fileData['error'] !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => 'Uploaded file exceeds server upload_max_filesize limit.',
        UPLOAD_ERR_FORM_SIZE  => 'Uploaded file exceeds MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
    ];
    $errMsg = $errMap[$fileData['error']] ?? 'Unknown upload error.';
    json_response(['success' => false, 'error' => $errMsg], 400);
}

// Size check
if ($fileData['size'] > MAX_UPLOAD_BYTES) {
    json_response(['success' => false, 'error' => 'Image exceeds 5MB size limit.'], 413);
}

// MIME type verification with finfo
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($fileData['tmp_name']);

// Handle SVG XML
if ($detectedMime === 'text/plain' || $detectedMime === 'text/xml' || $detectedMime === 'image/svg') {
    $content = file_get_contents($fileData['tmp_name']);
    if (stripos($content, '<svg') !== false) {
        $detectedMime = 'image/svg+xml';
    }
}

if (!isset(ALLOWED_UPLOAD_MIMES[$detectedMime])) {
    json_response([
        'success' => false,
        'error'   => 'Invalid file format. Allowed formats: PNG, JPG, WEBP, GIF, SVG.'
    ], 415);
}

$ext = ALLOWED_UPLOAD_MIMES[$detectedMime];
$safeFilename = 'img_' . bin2hex(random_bytes(16)) . '.' . $ext;

$uploadDir = dirname(__DIR__) . '/storage/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$targetPath = $uploadDir . '/' . $safeFilename;

if (!move_uploaded_file($fileData['tmp_name'], $targetPath)) {
    app_log("Failed to move uploaded file to {$targetPath}", 'ERROR', 'upload.log');
    json_response(['success' => false, 'error' => 'Failed to store uploaded image on server.'], 500);
}

// Safe relative URL
$publicUrl = 'api/upload.php?file=' . urlencode($safeFilename);

json_response([
    'success'  => true,
    'url'      => $publicUrl,
    'filename' => $safeFilename,
    'size'     => (int)$fileData['size'],
    'mime'     => $detectedMime
]);
