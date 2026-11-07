<?php
/**
 * AI Design Studio - Generation History Manager
 * 
 * Records, queries, paginates, and prunes AI generation history for
 * registered users and guests.
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('403 Forbidden - Direct access not allowed.');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';

const MAX_RESULT_JSON_BYTES = 524288; // 512 KB per record to protect free hosting storage

/**
 * Record a generation event into history.
 * Wrapped in try/catch so database failures NEVER disrupt the main request.
 * 
 * @param string $type 'enhance'|'generate'|'edit'
 * @param string $prompt
 * @param array|null $options
 * @param mixed $result Array or JSON string
 * @param string $status 'success'|'failed'
 * @return int|null Inserted ID or null on failure
 */
function record_generation_history(string $type, string $prompt, ?array $options, mixed $result, string $status = 'success'): ?int {
    try {
        $userId = get_current_user_id();
        $guestToken = null;

        if ($userId === null) {
            $guestToken = get_or_create_guest_token();
        }

        $validTypes = ['enhance', 'generate', 'edit'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'generate';
        }

        $promptTrimmed = mb_substr(trim($prompt), 0, 5000);
        $optionsJson = !empty($options) ? json_encode($options, JSON_UNESCAPED_UNICODE) : null;

        if (is_array($result)) {
            $resultJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $resultJson = (string)$result;
        }

        // Limit JSON size to protect shared hosting disk space
        if (strlen($resultJson) > MAX_RESULT_JSON_BYTES) {
            $resultJson = substr($resultJson, 0, MAX_RESULT_JSON_BYTES);
        }

        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO generations (user_id, guest_token, type, prompt, options_json, result_json, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $guestToken,
            $type,
            $promptTrimmed,
            $optionsJson,
            $resultJson,
            $status === 'failed' ? 'failed' : 'success'
        ]);

        $insertedId = (int)$db->lastInsertId();

        // Enforce maximum history rows per user/guest (default 200)
        prune_old_user_history($userId, $guestToken);

        return $insertedId;

    } catch (\Throwable $e) {
        // Log failure securely; NEVER throw to avoid breaking user AI response
        app_log("Failed to save generation history: " . $e->getMessage(), 'ERROR', 'history.log');
        return null;
    }
}

/**
 * Prune history rows exceeding HISTORY_MAX_PER_USER
 */
function prune_old_user_history(?int $userId, ?string $guestToken): void {
    $maxRows = defined('HISTORY_MAX_PER_USER') ? (int)HISTORY_MAX_PER_USER : 200;

    try {
        $db = get_db();
        if ($userId !== null) {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM generations WHERE user_id = ?");
            $countStmt->execute([$userId]);
            $count = (int)$countStmt->fetchColumn();

            if ($count > $maxRows) {
                $excess = $count - $maxRows;
                // Delete oldest rows
                $delStmt = $db->prepare("
                    DELETE FROM generations 
                    WHERE user_id = ? 
                    ORDER BY id ASC 
                    LIMIT {$excess}
                ");
                $delStmt->execute([$userId]);
            }
        } elseif (!empty($guestToken)) {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM generations WHERE user_id IS NULL AND guest_token = ?");
            $countStmt->execute([$guestToken]);
            $count = (int)$countStmt->fetchColumn();

            if ($count > $maxRows) {
                $excess = $count - $maxRows;
                $delStmt = $db->prepare("
                    DELETE FROM generations 
                    WHERE user_id IS NULL AND guest_token = ? 
                    ORDER BY id ASC 
                    LIMIT {$excess}
                ");
                $delStmt->execute([$guestToken]);
            }
        }
    } catch (\Throwable $e) {
        app_log("History prune error: " . $e->getMessage(), 'WARNING', 'history.log');
    }
}

/**
 * Retrieve paginated generation history for current session/user
 */
function get_history_list(int $page = 1, int $limit = 20, string $type = 'all'): array {
    $userId = get_current_user_id();
    $guestToken = ($userId === null) ? get_or_create_guest_token() : null;

    $page = max(1, $page);
    $limit = max(1, min(100, $limit));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];

    if ($userId !== null) {
        $where[] = "user_id = ?";
        $params[] = $userId;
    } else {
        $where[] = "user_id IS NULL AND guest_token = ?";
        $params[] = $guestToken;
    }

    if (in_array($type, ['enhance', 'generate', 'edit'], true)) {
        $where[] = "type = ?";
        $params[] = $type;
    }

    $whereClause = implode(' AND ', $where);

    try {
        $db = get_db();

        // Count total
        $countStmt = $db->prepare("SELECT COUNT(*) FROM generations WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();

        // Fetch paginated items (omitting full result_json for speed; fetch preview snippet)
        $sql = "
            SELECT id, user_id, type, prompt, options_json, status, created_at,
                   SUBSTRING(result_json, 1, 300) AS result_preview
            FROM generations 
            WHERE {$whereClause}
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Format items
        foreach ($items as &$item) {
            $item['options'] = !empty($item['options_json']) ? json_decode($item['options_json'], true) : [];
            unset($item['options_json']);
        }

        $totalPages = (int)ceil($totalItems / $limit);

        return [
            'items'        => $items,
            'pagination'   => [
                'total_items'  => $totalItems,
                'current_page' => $page,
                'per_page'     => $limit,
                'total_pages'  => max(1, $totalPages),
                'has_next'     => $page < $totalPages,
                'has_prev'     => $page > 1
            ],
            'is_guest'     => ($userId === null)
        ];

    } catch (\Throwable $e) {
        app_log("History list error: " . $e->getMessage(), 'ERROR', 'history.log');
        return [
            'items'      => [],
            'pagination' => ['total_items' => 0, 'current_page' => 1, 'per_page' => $limit, 'total_pages' => 1],
            'is_guest'   => ($userId === null)
        ];
    }
}

/**
 * Retrieve a single generation by ID with full result JSON (strictly checks ownership)
 */
function get_history_item(int $id): ?array {
    $userId = get_current_user_id();
    $guestToken = ($userId === null) ? get_or_create_guest_token() : null;

    try {
        $db = get_db();
        if ($userId !== null) {
            $stmt = $db->prepare("SELECT * FROM generations WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("SELECT * FROM generations WHERE id = ? AND user_id IS NULL AND guest_token = ?");
            $stmt->execute([$id, $guestToken]);
        }

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['options'] = !empty($row['options_json']) ? json_decode($row['options_json'], true) : [];
        $row['result'] = json_decode($row['result_json'], true) ?: $row['result_json'];

        return $row;
    } catch (\Throwable $e) {
        app_log("Get history item error: " . $e->getMessage(), 'ERROR', 'history.log');
        return null;
    }
}

/**
 * Delete a single history record with strict ownership check
 */
function delete_history_item(int $id): bool {
    $userId = get_current_user_id();
    $guestToken = ($userId === null) ? get_or_create_guest_token() : null;

    try {
        $db = get_db();
        if ($userId !== null) {
            $stmt = $db->prepare("DELETE FROM generations WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM generations WHERE id = ? AND user_id IS NULL AND guest_token = ?");
            $stmt->execute([$id, $guestToken]);
        }

        return ($stmt->rowCount() > 0);
    } catch (\Throwable $e) {
        app_log("Delete history item error: " . $e->getMessage(), 'ERROR', 'history.log');
        return false;
    }
}

/**
 * Clear all history records for current user/guest
 */
function clear_all_history(?string $type = null): int {
    $userId = get_current_user_id();
    $guestToken = ($userId === null) ? get_or_create_guest_token() : null;

    $where = [];
    $params = [];

    if ($userId !== null) {
        $where[] = "user_id = ?";
        $params[] = $userId;
    } else {
        $where[] = "user_id IS NULL AND guest_token = ?";
        $params[] = $guestToken;
    }

    if (in_array($type, ['enhance', 'generate', 'edit'], true)) {
        $where[] = "type = ?";
        $params[] = $type;
    }

    $whereClause = implode(' AND ', $where);

    try {
        $db = get_db();
        $stmt = $db->prepare("DELETE FROM generations WHERE {$whereClause}");
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (\Throwable $e) {
        app_log("Clear all history error: " . $e->getMessage(), 'ERROR', 'history.log');
        return 0;
    }
}

