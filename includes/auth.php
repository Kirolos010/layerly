<?php
/**
 * AI Design Studio - Authentication & User Management
 * 
 * Secure user accounts, sessions, password resets, and guest migration.
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

/**
 * Determine if current connection is HTTPS
 */
function is_https_connection(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * Get or initialize persistent Guest Token (used for tracking guest history)
 */
function get_or_create_guest_token(): string {
    init_secure_session();

    if (!empty($_SESSION['guest_token']) && preg_match('/^[a-f0-9]{64}$/', $_SESSION['guest_token'])) {
        return $_SESSION['guest_token'];
    }

    if (!empty($_COOKIE['guest_token']) && preg_match('/^[a-f0-9]{64}$/', $_COOKIE['guest_token'])) {
        $_SESSION['guest_token'] = $_COOKIE['guest_token'];
        return $_SESSION['guest_token'];
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['guest_token'] = $token;

    if (!headers_sent()) {
        $cookiePath = (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL : '/';
        setcookie('guest_token', $token, [
            'expires'  => time() + (86400 * 365), // 1 year
            'path'     => $cookiePath,
            'domain'   => '',
            'secure'   => is_https_connection(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    return $token;
}

/**
 * Check if a user is currently authenticated
 */
function is_logged_in(): bool {
    init_secure_session();
    return !empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

/**
 * Get currently authenticated user ID or null
 */
function get_current_user_id(): ?int {
    return is_logged_in() ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current logged in user array or null
 */
function get_auth_user(): ?array {
    static $currentUser = null;

    if (!is_logged_in()) {
        return null;
    }

    if ($currentUser !== null) {
        return $currentUser;
    }

    $userId = get_current_user_id();
    try {
        $stmt = db_query("SELECT id, name, email, preferred_lang, created_at, last_login_at FROM users WHERE id = ?", [$userId]);
        $user = $stmt->fetch();
        if ($user) {
            $currentUser = $user;
            return $currentUser;
        }
    } catch (\Throwable $e) {
        app_log("Error fetching user {$userId}: " . $e->getMessage(), 'ERROR', 'auth.log');
    }

    // User ID in session was deleted from DB
    logout_user();
    return null;
}

/**
 * Authenticate and log in user
 */
function login_user(array $user, bool $migrateGuest = true): void {
    init_secure_session();
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        @session_regenerate_id(true);
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];

    if (!empty($user['preferred_lang'])) {
        $_SESSION['lang'] = $user['preferred_lang'];
    }

    // Update last_login_at
    try {
        db_query("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
    } catch (\Throwable $e) {
        app_log("Error updating last_login_at: " . $e->getMessage(), 'WARNING', 'auth.log');
    }

    // Automatically attach guest generations and projects to account
    if ($migrateGuest) {
        $guestToken = $_SESSION['guest_token'] ?? ($_COOKIE['guest_token'] ?? null);
        if (!empty($guestToken)) {
            $migrated = migrate_guest_data_to_user((int)$user['id'], $guestToken);
            if ($migrated > 0) {
                $_SESSION['flash_success'] = t('guest_migrated_notice', null, ['count' => $migrated]);
            }
        }
    }
}

/**
 * Log out user safely
 */
function logout_user(): void {
    init_secure_session();
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        @session_regenerate_id(true);
    }
}

/**
 * Count guest history & projects
 */
function get_guest_designs_count(?string $guestToken = null): int {
    if (empty($guestToken)) {
        $guestToken = $_SESSION['guest_token'] ?? ($_COOKIE['guest_token'] ?? '');
    }
    if (empty($guestToken)) {
        return 0;
    }

    try {
        $stmtGen = db_query("SELECT COUNT(*) FROM generations WHERE guest_token = ?", [$guestToken]);
        $genCount = (int)$stmtGen->fetchColumn();

        $stmtProj = db_query("SELECT COUNT(*) FROM projects WHERE owner_token = ? AND user_id IS NULL", [$guestToken]);
        $projCount = (int)$stmtProj->fetchColumn();

        return $genCount + $projCount;
    } catch (\Throwable $e) {
        return 0;
    }
}

/**
 * Migrate guest generations and projects to newly logged-in/registered user
 */
function migrate_guest_data_to_user(int $userId, string $guestToken): int {
    if (empty($guestToken)) {
        return 0;
    }

    $migrated = 0;
    try {
        // 1. Migrate generations
        $stmtGen = db_query("
            UPDATE generations 
            SET user_id = ?, guest_token = NULL 
            WHERE guest_token = ? AND user_id IS NULL
        ", [$userId, $guestToken]);
        $migrated += $stmtGen->rowCount();

        // 2. Migrate projects
        $stmtProj = db_query("
            UPDATE projects 
            SET user_id = ? 
            WHERE owner_token = ? AND user_id IS NULL
        ", [$userId, $guestToken]);
        $migrated += $stmtProj->rowCount();

        app_log("Migrated {$migrated} items from guest token {$guestToken} to user {$userId}", 'INFO', 'auth.log');
    } catch (\Throwable $e) {
        app_log("Guest data migration error: " . $e->getMessage(), 'ERROR', 'auth.log');
    }

    return $migrated;
}

/**
 * Validate email address
 */
function validate_email(string $email): bool {
    $email = trim($email);
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 255;
}

/**
 * Validate password strength
 * Requires at least 8 characters, with at least one letter and one number
 */
function validate_password(string $password): array {
    if (mb_strlen($password) < 8) {
        return ['valid' => false, 'error' => t('password_too_short')];
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'error' => t('password_requires_letter_and_number')];
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Rate limit check for sensitive authentication endpoints (Login, Register, Forgot Password)
 */
function check_auth_rate_limit(string $action, string $identifier): array {
    $maxAttempts = defined('AUTH_RATE_LIMIT_MAX') ? AUTH_RATE_LIMIT_MAX : 5;
    $windowSeconds = defined('AUTH_RATE_LIMIT_WINDOW') ? AUTH_RATE_LIMIT_WINDOW : 900; // 15 mins
    $now = time();
    $windowStart = (int)(floor($now / $windowSeconds) * $windowSeconds);

    $key = substr("a_{$action}_" . hash('sha256', $identifier), 0, 45);

    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT count FROM rate_limits 
            WHERE ip = ? AND window_type = 'auth' AND window_start = ?
        ");
        $stmt->execute([$key, $windowStart]);
        $count = (int)($stmt->fetchColumn() ?: 0);

        if ($count >= $maxAttempts) {
            $retryAfter = max(1, ($windowStart + $windowSeconds) - $now);
            return [
                'allowed'     => false,
                'retry_after' => $retryAfter,
                'message'     => t('auth_rate_limited')
            ];
        }

        return ['allowed' => true, 'retry_after' => 0, 'message' => ''];
    } catch (\Throwable $e) {
        return ['allowed' => true, 'retry_after' => 0, 'message' => ''];
    }
}

/**
 * Record a failed authentication attempt
 */
function record_auth_failure(string $action, string $identifier): void {
    $windowSeconds = defined('AUTH_RATE_LIMIT_WINDOW') ? AUTH_RATE_LIMIT_WINDOW : 900;
    $now = time();
    $windowStart = (int)(floor($now / $windowSeconds) * $windowSeconds);
    $key = substr("a_{$action}_" . hash('sha256', $identifier), 0, 45);

    try {
        $db = get_db();
        $stmt = $db->prepare("
            INSERT INTO rate_limits (ip, window_type, window_start, count)
            VALUES (?, 'auth', ?, 1)
            ON DUPLICATE KEY UPDATE count = count + 1
        ");
        $stmt->execute([$key, $windowStart]);
    } catch (\Throwable $e) {
        app_log("Auth rate limit record error: " . $e->getMessage(), 'ERROR', 'auth.log');
    }
}

/**
 * Clear auth failures upon successful login
 */
function clear_auth_failures(string $action, string $identifier): void {
    $key = substr("a_{$action}_" . hash('sha256', $identifier), 0, 45);
    try {
        db_query("DELETE FROM rate_limits WHERE ip = ? AND window_type = 'auth'", [$key]);
    } catch (\Throwable $e) {
        // Non-critical
    }
}

/**
 * Send password reset email or log link if mail is not configured
 */
function request_password_reset(string $email): array {
    $email = strtolower(trim($email));

    // Rate limiting per IP and per Email
    $ip = get_client_ip();
    $ipLimit = check_auth_rate_limit('reset_ip', $ip);
    if (!$ipLimit['allowed']) {
        return ['success' => false, 'error' => $ipLimit['message'], 'debug_link' => null];
    }

    $emailLimit = check_auth_rate_limit('reset_email', $email);
    if (!$emailLimit['allowed']) {
        return ['success' => false, 'error' => $emailLimit['message'], 'debug_link' => null];
    }

    try {
        $stmt = db_query("SELECT id, name, email FROM users WHERE email = ?", [$email]);
        $user = $stmt->fetch();

        // If user not found, return generic success message (DO NOT leak whether email exists!)
        if (!$user) {
            record_auth_failure('reset_email', $email);
            return [
                'success'    => true,
                'message'    => t('reset_link_sent_notice'),
                'debug_link' => null
            ];
        }

        // Clean up expired tokens
        db_query("DELETE FROM password_resets WHERE expires_at < NOW() OR user_id = ?", [$user['id']]);

        // Generate secure 32-byte token
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        db_query("
            INSERT INTO password_resets (user_id, token_hash, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ", [$user['id'], $tokenHash]);

        // Build reset URL
        $baseUrl = defined('FULL_BASE_URL') ? FULL_BASE_URL : ((is_https_connection() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));
        $resetUrl = "{$baseUrl}/reset-password?token={$rawToken}";

        $subject = t('reset_email_subject');
        $body = "Hello {$user['name']},\n\n"
              . "We received a request to reset your password for AI Design Studio.\n"
              . "Click the link below to set a new password:\n\n"
              . "{$resetUrl}\n\n"
              . "This link will expire in 1 hour. If you did not make this request, please ignore this email.";

        $mailSent = send_app_mail($user['email'], $subject, nl2br(htmlspecialchars($body)), $body);

        $debugLink = (defined('DEBUG') && DEBUG) ? $resetUrl : null;

        return [
            'success'    => true,
            'message'    => t('reset_link_sent_notice'),
            'debug_link' => $debugLink,
            'mail_sent'  => $mailSent
        ];

    } catch (\Throwable $e) {
        app_log("Password reset error: " . $e->getMessage(), 'ERROR', 'auth.log');
        return [
            'success'    => false,
            'error'      => t('db_error'),
            'debug_link' => null
        ];
    }
}

/**
 * Verify reset token
 */
function verify_reset_token(string $rawToken): ?array {
    if (empty($rawToken) || strlen($rawToken) !== 64) {
        return null;
    }

    $tokenHash = hash('sha256', $rawToken);

    try {
        $stmt = db_query("
            SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, u.name, u.email 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token_hash = ? AND pr.expires_at > NOW()
            LIMIT 1
        ", [$tokenHash]);

        return $stmt->fetch() ?: null;
    } catch (\Throwable $e) {
        app_log("Verify reset token error: " . $e->getMessage(), 'ERROR', 'auth.log');
        return null;
    }
}

/**
 * Complete password reset
 */
function complete_password_reset(string $rawToken, string $newPassword): array {
    $tokenData = verify_reset_token($rawToken);
    if (!$tokenData) {
        return ['success' => false, 'error' => t('invalid_or_expired_token')];
    }

    $val = validate_password($newPassword);
    if (!$val['valid']) {
        return ['success' => false, 'error' => $val['error']];
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    try {
        $db = get_db();
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $tokenData['user_id']]);

        $delStmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $delStmt->execute([$tokenData['user_id']]);

        $db->commit();

        app_log("Password reset successfully for user ID {$tokenData['user_id']}", 'INFO', 'auth.log');
        return ['success' => true, 'message' => t('password_reset_success')];
    } catch (\Throwable $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        app_log("Complete password reset error: " . $e->getMessage(), 'ERROR', 'auth.log');
        return ['success' => false, 'error' => t('db_error')];
    }
}

/**
 * Send email using mail() or optional SMTP
 */
function send_app_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@localhost';
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'AI Design Studio';

    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: {$fromName} <{$from}>",
        "Reply-To: {$from}",
        "X-Mailer: PHP/" . PHP_VERSION
    ];

    // Log the email attempt
    $logMsg = date('[Y-m-d H:i:s]') . " Mail to <{$to}> | Subject: {$subject}\n";
    if (defined('DEBUG') && DEBUG) {
        $logMsg .= "Body: {$textBody}\n\n";
    }
    @file_put_contents(dirname(__DIR__) . '/storage/logs/mail.log', $logMsg, FILE_APPEND);

    // Standard PHP mail() execution
    $sent = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    return (bool)$sent;
}
