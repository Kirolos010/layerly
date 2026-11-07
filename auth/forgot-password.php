<?php
/**
 * AI Design Studio - Forgot Password Request
 */

define('APP_INIT', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

send_security_headers();
init_secure_session();

$csrfToken = get_csrf_token();
$currentLang = get_current_lang();
$isArabic = ($currentLang === 'ar');
$dir = $isArabic ? 'rtl' : 'ltr';

$error = null;
$notice = null;
$debugLink = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_exit();

    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if (empty($email) || !validate_email($email)) {
        $notice = t('reset_link_sent_notice');
    } else {
        $res = request_password_reset($email);
        if (!$res['success']) {
            $error = $res['error'];
        } else {
            $notice = $res['message'];
            $debugLink = $res['debug_link'] ?? null;
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
    <title><?= e(t('forgot_password')) ?> - <?= e(t('app_name')) ?></title>

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
                    <h2><?= e(t('forgot_password')) ?></h2>
                    <p><?= e(t('reset_password_title')) ?></p>
                </div>

                <?php if ($notice): ?>
                    <div class="alert alert-success" style="display: block;">
                        <?= e($notice) ?>
                    </div>
                <?php endif; ?>

                <?php if ($debugLink): ?>
                    <div class="alert alert-info" style="display: block; word-break: break-all; margin-top: 1rem;">
                        <strong><?= e(t('mail_debug_link_notice')) ?></strong><br>
                        <a href="<?= e($debugLink) ?>" style="color: #60a5fa; text-decoration: underline;">
                            <?= e($debugLink) ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="display: block;">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= e(url('forgot-password')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="form-group">
                        <label for="email"><?= e(t('email')) ?></label>
                        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <?= e(t('send_reset_link')) ?>
                    </button>
                </form>

                <div class="auth-footer">
                    <a href="<?= e(url('login')) ?>"><?= e(t('remember_password')) ?> <?= e(t('login')) ?></a>
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
