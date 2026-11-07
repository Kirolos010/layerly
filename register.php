<?php
/**
 * AI Design Studio - User Registration
 */

define('APP_INIT', true);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

send_security_headers();
init_secure_session();

// If already logged in, redirect to home
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$csrfToken = get_csrf_token();
$currentLang = get_current_lang();
$isArabic = ($currentLang === 'ar');
$dir = $isArabic ? 'rtl' : 'ltr';

$error = null;
$name = '';
$email = '';
$prefLang = $currentLang;

// Check if guest has previous designs to migrate
$guestToken = $_SESSION['guest_token'] ?? ($_COOKIE['guest_token'] ?? null);
$guestDesignsCount = $guestToken ? get_guest_designs_count($guestToken) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_exit();

    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $prefLang = in_array($_POST['preferred_lang'] ?? '', ['ar', 'en'], true) ? $_POST['preferred_lang'] : $currentLang;

    // Rate limiting per IP
    $ip = get_client_ip();
    $rateCheck = check_auth_rate_limit('register_ip', $ip);
    if (!$rateCheck['allowed']) {
        $error = $rateCheck['message'];
    } elseif (empty($name)) {
        $error = t('invalid_input');
    } elseif (!validate_email($email)) {
        $error = t('invalid_input');
    } elseif ($password !== $confirmPassword) {
        $error = t('passwords_do_not_match');
    } else {
        $pwCheck = validate_password($password);
        if (!$pwCheck['valid']) {
            $error = $pwCheck['error'];
        } else {
            try {
                // Check if email already registered
                $stmt = db_query("SELECT id FROM users WHERE email = ?", [$email]);
                if ($stmt->fetch()) {
                    record_auth_failure('register_ip', $ip);
                    $error = t('email_already_exists');
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $insertStmt = db_query("
                        INSERT INTO users (name, email, password_hash, preferred_lang, created_at, last_login_at)
                        VALUES (?, ?, ?, ?, NOW(), NOW())
                    ", [$name, $email, $hash, $prefLang]);

                    $newUserId = (int)get_db()->lastInsertId();
                    clear_auth_failures('register_ip', $ip);

                    // Fetch user and log in
                    $newUser = [
                        'id'             => $newUserId,
                        'name'           => $name,
                        'email'          => $email,
                        'preferred_lang' => $prefLang
                    ];

                    login_user($newUser, true);

                    $_SESSION['flash_success'] = t('account_created_success');
                    header('Location: index.php');
                    exit;
                }
            } catch (\Throwable $e) {
                app_log("Registration error: " . $e->getMessage(), 'ERROR', 'auth.log');
                $error = t('db_error');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('register')) ?> - <?= e(t('app_name')) ?></title>

    <!-- Zero-Flash Theme Auto-Initializer -->
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('design_studio_theme');
                if (!theme) {
                    var match = document.cookie.match(/(?:^|; )design_studio_theme=([^;]*)/);
                    theme = match ? match[1] : null;
                }
                if (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                    theme = 'light';
                }
                document.documentElement.setAttribute('data-theme', theme || 'dark');
            } catch(e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Background Animated Mesh Gradient Orbs -->
    <div class="glass-mesh-backdrop" aria-hidden="true">
        <div class="mesh-orb mesh-orb-1"></div>
        <div class="mesh-orb mesh-orb-2"></div>
        <div class="mesh-orb mesh-orb-3"></div>
        <div class="mesh-orb mesh-orb-4"></div>
    </div>

    <div class="container">
        <header class="app-header">
            <div>
                <a href="index.php" style="text-decoration: none;">
                    <h1 class="app-title"><?= e(t('app_name')) ?></h1>
                </a>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-sm theme-toggle-btn" id="theme-toggle" title="<?= e(t('theme_toggle')) ?>">
                    <span class="theme-icon">☀️</span> <span class="theme-text"><?= e(t('theme_light')) ?></span>
                </button>
                <a href="?lang=<?= $isArabic ? 'en' : 'ar' ?>" class="btn-sm">🌐 <?= e(t('switch_language')) ?></a>
                <a href="login.php" class="btn-sm"><?= e(t('login')) ?></a>
            </div>
        </header>

        <div class="auth-wrapper">
            <div class="auth-card">
                <div class="auth-header">
                    <h2><?= e(t('register')) ?></h2>
                    <p><?= e(t('app_tagline')) ?></p>
                </div>

                <?php if ($guestDesignsCount > 0): ?>
                    <div class="alert alert-info" style="display: block; margin-bottom: 1.25rem;">
                        ✨ <?= e(t('guest_migrated_notice', null, ['count' => $guestDesignsCount])) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="display: block;">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="form-group">
                        <label for="name"><?= e(t('name')) ?></label>
                        <input type="text" id="name" name="name" value="<?= e($name) ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="email"><?= e(t('email')) ?></label>
                        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password"><?= e(t('password')) ?></label>
                        <input type="password" id="password" name="password" required autocomplete="new-password">
                        <small style="color: var(--text-muted); font-size: 0.78rem;"><?= e(t('password_requires_letter_and_number')) ?></small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><?= e(t('confirm_password')) ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label for="preferred_lang"><?= e(t('preferred_lang')) ?></label>
                        <select id="preferred_lang" name="preferred_lang">
                            <option value="ar" <?= $prefLang === 'ar' ? 'selected' : '' ?>><?= e(t('arabic')) ?></option>
                            <option value="en" <?= $prefLang === 'en' ? 'selected' : '' ?>><?= e(t('english')) ?></option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <?= e(t('register')) ?>
                    </button>
                </form>

                <div class="auth-footer">
                    <?= e(t('already_have_account')) ?>
                    <a href="login.php"><?= e(t('login')) ?></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Manager Script -->
    <script type="module">
        import { ThemeManager } from './assets/js/editor/theme-manager.js';
        ThemeManager.init();
    </script>
</body>
</html>

