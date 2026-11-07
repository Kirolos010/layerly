<?php
/**
 * AI Design Studio - Password Reset
 */

define('APP_INIT', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

send_security_headers();
init_secure_session();

$csrfToken = get_csrf_token();
$currentLang = get_current_lang();
$isArabic = ($currentLang === 'ar');
$dir = $isArabic ? 'rtl' : 'ltr';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenValid = false;
$userData = null;
$error = null;

if (!empty($token)) {
    $userData = verify_reset_token($token);
    $tokenValid = ($userData !== null);
}

if (!$tokenValid && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = t('invalid_or_expired_token');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_exit();

    $newPassword = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!$tokenValid) {
        $error = t('invalid_or_expired_token');
    } elseif ($newPassword !== $confirmPassword) {
        $error = t('passwords_do_not_match');
    } else {
        $res = complete_password_reset($token, $newPassword);
        if ($res['success']) {
            $_SESSION['flash_success'] = t('password_reset_success');
            redirect('login');
        } else {
            $error = $res['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>" data-theme="dark">
<head>
    <base href="<?= e(url('/')) ?>/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('reset_password_title')) ?> - <?= e(t('app_name')) ?></title>

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
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
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
                <a href="<?= e(url('/')) ?>" style="text-decoration: none;">
                    <h1 class="app-title"><?= e(t('app_name')) ?></h1>
                </a>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-sm theme-toggle-btn" id="theme-toggle" title="<?= e(t('theme_toggle')) ?>">
                    <span class="theme-icon">☀️</span> <span class="theme-text"><?= e(t('theme_light')) ?></span>
                </button>
                <a href="?lang=<?= $isArabic ? 'en' : 'ar' ?>" class="btn-sm">🌐 <?= e(t('switch_language')) ?></a>
                <a href="<?= e(url('login')) ?>" class="btn-sm"><?= e(t('login')) ?></a>
            </div>
        </header>

        <div class="auth-wrapper">
            <div class="auth-card">
                <div class="auth-header">
                    <h2><?= e(t('reset_password_title')) ?></h2>
                    <p><?= e(t('reset_password_desc')) ?></p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="display: block;">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($tokenValid): ?>
                    <form method="POST" action="<?= e(url('reset-password')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="token" value="<?= e($token) ?>">

                        <div class="form-group">
                            <label for="password"><?= e(t('new_password')) ?></label>
                            <input type="password" id="password" name="password" required autofocus autocomplete="new-password">
                            <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                                <?= e(t('password_help')) ?>
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password"><?= e(t('confirm_password')) ?></label>
                            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                            <?= e(t('set_new_password')) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="<?= e(url('forgot-password')) ?>" class="btn btn-primary" style="display: inline-block;">
                            <?= e(t('request_new_link')) ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="auth-footer">
                    <a href="<?= e(url('login')) ?>">&larr; <?= e(t('back_to_login')) ?></a>
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
