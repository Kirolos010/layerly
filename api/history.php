<?php
/**
 * AI Design Studio - Generation History API Endpoint
 * 
 * Supports:
 * - GET  /api/history.php?action=list&page=1&limit=20&type=all
 * - GET  /api/history.php?action=get&id=123
 * - POST /api/history.php?action=delete (id, csrf_token)
 * - POST /api/history.php?action=clear (csrf_token, type)
 * Enforces strict ownership checks on every action.
 */

define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/security.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/HistoryRepository.php';

// Security headers
send_security_headers();

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));

switch ($action) {
    case 'list':
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $type = trim((string)($_GET['type'] ?? 'all'));

        $historyData = get_history_list($page, $limit, $type);

        json_response([
            'success'    => true,
            'data'       => $historyData['items'],
            'pagination' => $historyData['pagination'],
            'is_guest'   => $historyData['is_guest']
        ]);
        break;

    case 'get':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            json_response([
                'success' => false,
                'error'   => t('invalid_input'),
                'code'    => 'INVALID_ID'
            ], 422);
        }

        $item = get_history_item($id);
        if (!$item) {
            json_response([
                'success' => false,
                'error'   => t('history_item_not_found'),
                'code'    => 'NOT_FOUND'
            ], 404);
        }

        json_response([
            'success' => true,
            'data'    => $item
        ]);
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response([
                'success' => false,
                'error'   => 'Method Not Allowed. Use POST.',
                'code'    => 'METHOD_NOT_ALLOWED'
            ], 405);
        }

        verify_csrf_or_exit();

        $input = get_json_input();
        if (empty($input)) {
            $input = $_POST;
        }

        $id = isset($input['id']) ? (int)$input['id'] : 0;
        if ($id <= 0) {
            json_response([
                'success' => false,
                'error'   => t('invalid_input'),
                'code'    => 'INVALID_ID'
            ], 422);
        }

        $deleted = delete_history_item($id);
        if (!$deleted) {
            json_response([
                'success' => false,
                'error'   => t('history_item_not_found'),
                'code'    => 'DELETE_FAILED'
            ], 404);
        }

        json_response([
            'success' => true,
            'message' => t('history_item_deleted')
        ]);
        break;

    case 'clear':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response([
                'success' => false,
                'error'   => 'Method Not Allowed. Use POST.',
                'code'    => 'METHOD_NOT_ALLOWED'
            ], 405);
        }

        verify_csrf_or_exit();

        $input = get_json_input();
        if (empty($input)) {
            $input = $_POST;
        }

        $type = !empty($input['type']) ? trim((string)$input['type']) : null;
        $deletedCount = clear_all_history($type);

        json_response([
            'success'       => true,
            'message'       => t('history_cleared_success'),
            'deleted_count' => $deletedCount
        ]);
        break;

    default:
        json_response([
            'success' => false,
            'error'   => 'Unknown action: ' . htmlspecialchars($action),
            'code'    => 'UNKNOWN_ACTION'
        ], 400);
}

