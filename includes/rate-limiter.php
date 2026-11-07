<?php
/**
 * AI Design Studio - MySQL-backed Rate Limiter
 * 
 * Enforces per-IP rate limits stored in MySQL:
 * - Default 10 requests / minute
 * - Default 100 requests / day
 * Includes automatic periodic cleanup (no cron required).
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

/**
 * Check and record a rate limit hit for an IP address
 * 
 * @param string|null $ip
 * @return array ['allowed' => bool, 'reason' => string|null, 'retry_after' => int, 'minute_count' => int, 'day_count' => int]
 */
function check_rate_limit(?string $ip = null): array {
    $ip = $ip ?: get_client_ip();
    $now = time();

    $minuteLimit = defined('RATE_LIMIT_PER_MINUTE') ? RATE_LIMIT_PER_MINUTE : 10;
    $dayLimit    = defined('RATE_LIMIT_PER_DAY') ? RATE_LIMIT_PER_DAY : 100;

    $minuteWindowStart = (int)(floor($now / 60) * 60);
    $dayWindowStart    = (int)(floor($now / 86400) * 86400);

    try {
        $db = get_db();

        // 1. Probabilistic cleanup of old windows (approx 1 in 50 calls)
        if (random_int(1, 50) === 1) {
            $cleanupThreshold = $now - (86400 * 2); // 2 days ago
            $cleanStmt = $db->prepare("DELETE FROM rate_limits WHERE window_start < ?");
            $cleanStmt->execute([$cleanupThreshold]);
        }

        // 2. Increment minute counter
        $stmtMin = $db->prepare("
            INSERT INTO rate_limits (ip, window_type, window_start, count)
            VALUES (?, 'minute', ?, 1)
            ON DUPLICATE KEY UPDATE count = count + 1
        ");
        $stmtMin->execute([$ip, $minuteWindowStart]);

        // Get current minute count
        $getMin = $db->prepare("
            SELECT count FROM rate_limits 
            WHERE ip = ? AND window_type = 'minute' AND window_start = ?
        ");
        $getMin->execute([$ip, $minuteWindowStart]);
        $minuteCount = (int)($getMin->fetchColumn() ?: 1);

        if ($minuteCount > $minuteLimit) {
            $retryAfter = max(1, 60 - ($now % 60));
            return [
                'allowed'      => false,
                'reason'       => 'minute',
                'retry_after'  => $retryAfter,
                'message'      => t('rate_limit_minute'),
                'minute_count' => $minuteCount,
                'day_count'    => 0,
            ];
        }

        // 3. Increment day counter
        $stmtDay = $db->prepare("
            INSERT INTO rate_limits (ip, window_type, window_start, count)
            VALUES (?, 'day', ?, 1)
            ON DUPLICATE KEY UPDATE count = count + 1
        ");
        $stmtDay->execute([$ip, $dayWindowStart]);

        // Get current day count
        $getDay = $db->prepare("
            SELECT count FROM rate_limits 
            WHERE ip = ? AND window_type = 'day' AND window_start = ?
        ");
        $getDay->execute([$ip, $dayWindowStart]);
        $dayCount = (int)($getDay->fetchColumn() ?: 1);

        if ($dayCount > $dayLimit) {
            $retryAfter = max(1, 86400 - ($now % 86400));
            return [
                'allowed'      => false,
                'reason'       => 'day',
                'retry_after'  => $retryAfter,
                'message'      => t('rate_limit_day'),
                'minute_count' => $minuteCount,
                'day_count'    => $dayCount,
            ];
        }

        return [
            'allowed'      => true,
            'reason'       => null,
            'retry_after'  => 0,
            'minute_count' => $minuteCount,
            'day_count'    => $dayCount,
        ];

    } catch (PDOException $e) {
        // If rate limits table does not exist or DB connection fails, log and fail gracefully
        app_log("Rate limiter DB error: " . $e->getMessage(), 'ERROR', 'rate_limit.log');
        
        // Allow request in development if rate limit table not installed yet
        return [
            'allowed'      => true,
            'reason'       => null,
            'retry_after'  => 0,
            'minute_count' => 1,
            'day_count'    => 1,
        ];
    }
}

/**
 * Enforce rate limit on the current request or terminate with HTTP 429
 */
function enforce_rate_limit_or_exit(): void {
    $result = check_rate_limit();

    if (!$result['allowed']) {
        if (!headers_sent()) {
            http_response_code(429);
            header('Retry-After: ' . $result['retry_after']);
        }
        json_response([
            'success'     => false,
            'error'       => $result['message'],
            'code'        => 'RATE_LIMIT_EXCEEDED',
            'limit_type'  => $result['reason'],
            'retry_after' => $result['retry_after'],
        ], 429);
    }
}

/**
 * Get current daily poster quota usage and remaining generations
 * 
 * @param string|null $ip
 * @return array ['limit' => int, 'used' => int, 'remaining' => int, 'reset_seconds' => int]
 */
function get_poster_quota(?string $ip = null): array {
    $ip = $ip ?: get_client_ip();
    $now = time();
    $limit = defined('POSTER_DAILY_LIMIT') ? (int)POSTER_DAILY_LIMIT : 5;
    $dayWindowStart = (int)(floor($now / 86400) * 86400);

    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT count FROM rate_limits 
            WHERE ip = ? AND window_type = 'poster_d' AND window_start = ?
        ");
        $stmt->execute([$ip, $dayWindowStart]);
        $used = (int)($stmt->fetchColumn() ?: 0);
        $remaining = max(0, $limit - $used);
        $resetSeconds = max(1, 86400 - ($now % 86400));

        return [
            'limit'         => $limit,
            'used'          => $used,
            'remaining'     => $remaining,
            'reset_seconds' => $resetSeconds
        ];
    } catch (PDOException $e) {
        app_log("Poster rate limiter DB error: " . $e->getMessage(), 'ERROR', 'rate_limit.log');
        return [
            'limit'         => $limit,
            'used'          => 0,
            'remaining'     => $limit,
            'reset_seconds' => 86400
        ];
    }
}

/**
 * Check if the user has remaining poster quota for today
 * 
 * @param string|null $ip
 * @return array ['allowed' => bool, 'remaining' => int, 'limit' => int, 'message' => ?string]
 */
function check_poster_rate_limit(?string $ip = null): array {
    $quota = get_poster_quota($ip);
    if ($quota['remaining'] <= 0) {
        return [
            'allowed'   => false,
            'remaining' => 0,
            'limit'     => $quota['limit'],
            'message'   => t('poster_quota_exceeded')
        ];
    }
    return [
        'allowed'   => true,
        'remaining' => $quota['remaining'],
        'limit'     => $quota['limit'],
        'message'   => null
    ];
}

/**
 * Record a poster generation operation
 * 
 * @param string|null $ip
 */
function increment_poster_quota(?string $ip = null): void {
    $ip = $ip ?: get_client_ip();
    $now = time();
    $dayWindowStart = (int)(floor($now / 86400) * 86400);

    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO rate_limits (ip, window_type, window_start, count)
            VALUES (?, 'poster_d', ?, 1)
            ON DUPLICATE KEY UPDATE count = count + 1
        ");
        $stmt->execute([$ip, $dayWindowStart]);
    } catch (PDOException $e) {
        app_log("Failed to increment poster quota: " . $e->getMessage(), 'ERROR', 'rate_limit.log');
    }
}

