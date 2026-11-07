<?php
/**
 * AI Design Studio - 404 Not Found Page (Phase 4 Liquid Glass)
 */

define('APP_INIT', true);

http_response_code(404);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

send_security_headers();
init_secure_session();

$csrfToken = get_csrf_token();
$currentLang = get_current_lang();
$isArabic = ($currentLang === 'ar');
$dir = $isArabic ? 'rtl' : 'ltr';
$otherLang = $isArabic ? 'en' : 'ar';
$switchLangUrl = '?lang=' . $otherLang;
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - <?= e(t('page_not_found')) ?> - <?= e(t('app_name')) ?></title>
    
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
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 1.5rem;">
    <!-- Background Animated Mesh Gradient Orbs -->
    <div class="glass-mesh-backdrop" aria-hidden="true">
        <div class="mesh-orb mesh-orb-1"></div>
        <div class="mesh-orb mesh-orb-2"></div>
        <div class="mesh-orb mesh-orb-3"></div>
        <div class="mesh-orb mesh-orb-4"></div>
    </div>

    <!-- 404 Glass Container -->
    <div class="glass-panel" style="max-width: 520px; width: 100%; text-align: center; padding: 3.5rem 2.5rem; position: relative; z-index: 10;">
        <div style="font-size: 5rem; font-weight: 800; line-height: 1; margin-bottom: 0.5rem; background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            404
        </div>
        
        <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--text);">
            <?= e(t('page_not_found')) ?>
        </h1>
        
        <p style="font-size: 0.95rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem;">
            <?= e(t('page_not_found_desc')) ?>
        </p>

        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <a href="index.php" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                🚀 <?= e(t('return_to_studio')) ?>
            </a>
            <a href="projects.php" class="btn btn-secondary" style="padding: 0.75rem 1.5rem;">
                📁 <?= e(t('my_designs')) ?>
            </a>
            <a href="<?= e($switchLangUrl) ?>" class="btn btn-secondary" style="padding: 0.75rem 1.25rem;">
                🌐 <?= e(t('switch_language')) ?>
            </a>
        </div>
    </div>

    <!-- Theme Manager Script -->
    <script type="module">
        import { ThemeManager } from './assets/js/editor/theme-manager.js';
        ThemeManager.init();
    </script>
</body>
</html>
