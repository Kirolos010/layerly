<?php
/**
 * AI Design Studio - User Profile & Account Settings
 */

define('APP_INIT', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

send_security_headers();
init_secure_session();

// Protected page: require login
if (!is_logged_in()) {
    redirect('login?next=' . urlencode('profile'));
}

$currentUser = get_auth_user();
if (!$currentUser) {
    redirect('login');
}

$csrfToken = get_csrf_token();
$currentLang = get_current_lang();
$isArabic = ($currentLang === 'ar');
$dir = $isArabic ? 'rtl' : 'ltr';

$successMsg = null;
$errorMsg = null;

// Count user generations
try {
    $stmtCount = db_query("SELECT COUNT(*) FROM generations WHERE user_id = ?", [$currentUser['id']]);
    $userGenerationsCount = (int)$stmtCount->fetchColumn();
} catch (\Throwable $e) {
    $userGenerationsCount = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_exit();

    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $prefLang = in_array($_POST['preferred_lang'] ?? '', ['ar', 'en'], true) ? $_POST['preferred_lang'] : 'ar';

        if (empty($name)) {
            $errorMsg = t('invalid_input');
        } else {
            try {
                db_query("UPDATE users SET name = ?, preferred_lang = ? WHERE id = ?", [$name, $prefLang, $currentUser['id']]);
                $_SESSION['lang'] = $prefLang;
                $successMsg = t('profile_updated_success');
                // Refresh user
                $currentUser['name'] = $name;
                $currentUser['preferred_lang'] = $prefLang;
            } catch (\Throwable $e) {
                app_log("Profile update error: " . $e->getMessage(), 'ERROR', 'auth.log');
                $errorMsg = t('db_error');
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        // Fetch user password_hash
        try {
            $stmt = db_query("SELECT password_hash FROM users WHERE id = ?", [$currentUser['id']]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($currentPassword, (string)$hash)) {
                $errorMsg = t('current_password_incorrect');
            } elseif ($newPassword !== $confirmPassword) {
                $errorMsg = t('passwords_do_not_match');
            } else {
                $pwCheck = validate_password($newPassword);
                if (!$pwCheck['valid']) {
                    $errorMsg = $pwCheck['error'];
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    db_query("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $currentUser['id']]);
                    $successMsg = t('password_updated_success');
                }
            }
        } catch (\Throwable $e) {
            app_log("Password change error: " . $e->getMessage(), 'ERROR', 'auth.log');
            $errorMsg = t('db_error');
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
    <title><?= e(t('profile')) ?> - <?= e(t('app_name')) ?></title>

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
                <p class="app-subtitle"><?= e(t('profile')) ?></p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-sm theme-toggle-btn" id="theme-toggle" title="<?= e(t('theme_toggle')) ?>">
                    <span class="theme-icon">☀️</span> <span class="theme-text"><?= e(t('theme_light')) ?></span>
                </button>
                <a href="?lang=<?= $isArabic ? 'en' : 'ar' ?>" class="btn-sm">🌐 <?= e(t('switch_language')) ?></a>
                <a href="<?= e(url('projects')) ?>" class="btn-sm">📁 <?= e(t('my_designs')) ?></a>
                <a href="<?= e(url('history')) ?>" class="btn-sm">📜 <?= e(t('history')) ?></a>
                <a href="<?= e(url('/')) ?>" class="btn-sm"><?= e(t('back_to_studio')) ?></a>
                <a href="<?= e(url('logout')) ?>" class="btn-sm" style="color: var(--danger);"><?= e(t('logout')) ?></a>
            </div>
        </header>

        <div class="auth-wrapper" style="min-height: auto; margin-top: 1rem;">
            <div class="auth-card" style="max-width: 600px;">
                <div class="auth-header">
                    <h2><?= e($currentUser['name']) ?></h2>
                    <p><?= e($currentUser['email']) ?> &bull; <?= e(t('stats_objects')) ?>: <?= $userGenerationsCount ?></p>
                </div>

                <?php if ($successMsg): ?>
                    <div class="alert alert-success" style="display: block;">
                        <?= e($successMsg) ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger" style="display: block;">
                        <?= e($errorMsg) ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Info Form -->
                <form method="POST" action="<?= e(url('profile')) ?>" style="margin-bottom: 2rem;">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <h3 style="font-size: 1.1rem; color: #93c5fd; margin-bottom: 1rem;">
                        👤 <?= e(t('profile')) ?>
                    </h3>

                    <div class="form-group">
                        <label for="name"><?= e(t('name')) ?></label>
                        <input type="text" id="name" name="name" value="<?= e($currentUser['name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email_disabled"><?= e(t('email')) ?></label>
                        <input type="email" id="email_disabled" value="<?= e($currentUser['email']) ?>" disabled style="opacity: 0.6; cursor: not-allowed;">
                    </div>

                    <div class="form-group">
                        <label for="preferred_lang"><?= e(t('preferred_lang')) ?></label>
                        <select id="preferred_lang" name="preferred_lang">
                            <option value="ar" <?= $currentUser['preferred_lang'] === 'ar' ? 'selected' : '' ?>><?= e(t('arabic')) ?></option>
                            <option value="en" <?= $currentUser['preferred_lang'] === 'en' ? 'selected' : '' ?>><?= e(t('english')) ?></option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= e(t('save_changes')) ?>
                    </button>
                </form>

                <!-- Change Password Form -->
                <form method="POST" action="<?= e(url('profile')) ?>" style="padding-top: 1.5rem; border-top: 1px solid var(--border);">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="change_password">

                    <h3 style="font-size: 1.1rem; color: #93c5fd; margin-bottom: 1rem;">
                        🔒 <?= e(t('update_password')) ?>
                    </h3>

                    <div class="form-group">
                        <label for="current_password"><?= e(t('current_password')) ?></label>
                        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label for="new_password"><?= e(t('new_password')) ?></label>
                        <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
                        <small style="color: var(--text-muted); font-size: 0.78rem;"><?= e(t('password_requires_letter_and_number')) ?></small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><?= e(t('confirm_password')) ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-accent">
                        <?= e(t('update_password')) ?>
                    </button>
                </form>
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
