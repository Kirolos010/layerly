<?php
/**
 * AI Design Studio - User Login
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
$email = '';
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// Check if guest has previous designs to migrate
$guestToken = $_SESSION['guest_token'] ?? ($_COOKIE['guest_token'] ?? null);
$guestDesignsCount = $guestToken ? get_guest_designs_count($guestToken) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_exit();

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    $ip = get_client_ip();
    $rateCheckIp = check_auth_rate_limit('login_ip', $ip);
    $rateCheckEmail = check_auth_rate_limit('login_email', $email);

    if (!$rateCheckIp['allowed']) {
        $error = $rateCheckIp['message'];
    } elseif (!$rateCheckEmail['allowed']) {
        $error = $rateCheckEmail['message'];
    } elseif (empty($email) || empty($password)) {
        $error = t('invalid_credentials');
    } else {
        try {
            $stmt = db_query("SELECT * FROM users WHERE email = ?", [$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Clear any recorded failures
                clear_auth_failures('login_ip', $ip);
                clear_auth_failures('login_email', $email);

                login_user($user, true);

                $redirect = $_SESSION['login_redirect'] ?? 'index.php';
                unset($_SESSION['login_redirect']);
                header("Location: {$redirect}");
                exit;
            } else {
                // Record failure to protect against brute-force
                record_auth_failure('login_ip', $ip);
                record_auth_failure('login_email', $email);

                // Generic error message: NEVER reveal whether the email exists!
                $error = t('invalid_credentials');
            }
        } catch (\Throwable $e) {
            app_log("Login error: " . $e->getMessage(), 'ERROR', 'auth.log');
            $error = t('db_error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('login')) ?> - <?= e(t('app_name')) ?></title>

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
                <a href="register.php" class="btn-sm"><?= e(t('register')) ?></a>
            </div>
        </header>

        <div class="auth-wrapper">
            <div class="auth-card">
                <div class="auth-header">
                    <h2><?= e(t('login')) ?></h2>
                    <p><?= e(t('app_tagline')) ?></p>
                </div>

                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success" style="display: block;">
                        <?= e($flashSuccess) ?>
                    </div>
                <?php endif; ?>

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

                <form method="POST" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="form-group">
                        <label for="email"><?= e(t('email')) ?></label>
                        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                            <label for="password" style="margin-bottom: 0;"><?= e(t('password')) ?></label>
                            <a href="forgot-password.php" style="font-size: 0.8rem; color: var(--primary); text-decoration: none;">
                                <?= e(t('forgot_password')) ?>
                            </a>
                        </div>
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <?= e(t('login')) ?>
                    </button>
                </form>

                <div class="auth-footer">
                    <?= e(t('dont_have_account')) ?>
                    <a href="register.php"><?= e(t('register')) ?></a>
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

