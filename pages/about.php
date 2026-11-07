<?php
/**
 * AI Design Studio - About & Help Page (Phase 4 Liquid Glass)
 * 
 * Bilingual guide, feature showcase, and quick help.
 */

define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

send_security_headers();
init_secure_session();

$csrfToken = get_csrf_token();
$currentLang = get_current_lang();
$isArabic = ($currentLang === 'ar');
$dir = $isArabic ? 'rtl' : 'ltr';
$otherLang = $isArabic ? 'en' : 'ar';
$switchLangUrl = url('about?lang=' . $otherLang);

$isLoggedIn = is_logged_in();
$currentUser = get_auth_user();
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= e(url('/')) ?>/">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    
    <!-- SEO & Metadata -->
    <title><?= e(t('about_title')) ?> - <?= e(t('app_name')) ?></title>
    <meta name="description" content="<?= e(t('about_tagline')) ?> - <?= e(t('meta_desc')) ?>">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e(t('about_title')) ?> - <?= e(t('app_name')) ?>">
    <meta property="og:description" content="<?= e(t('about_tagline')) ?>">
    <meta property="og:locale" content="<?= $isArabic ? 'ar_AR' : 'en_US' ?>">
    <meta property="og:site_name" content="AI Design Studio">

    <!-- Multilingual Alternate hreflang -->
    <link rel="alternate" hreflang="ar" href="<?= e(url('about?lang=ar')) ?>">
    <link rel="alternate" hreflang="en" href="<?= e(url('about?lang=en')) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= e(url('about')) ?>">

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
<body>
    <!-- Background Animated Mesh Gradient Orbs -->
    <div class="glass-mesh-backdrop" aria-hidden="true">
        <div class="mesh-orb mesh-orb-1"></div>
        <div class="mesh-orb mesh-orb-2"></div>
        <div class="mesh-orb mesh-orb-3"></div>
        <div class="mesh-orb mesh-orb-4"></div>
    </div>

    <div class="container" style="max-width: 1200px; padding: 1.5rem 1rem 4rem;">
        <!-- Header -->
        <header class="app-header">
            <div class="app-brand-group">
                <div class="app-logo-badge">✨</div>
                <div>
                    <a href="<?= e(url('/')) ?>" style="text-decoration: none;">
                        <h1 class="app-title"><?= e(t('app_name')) ?></h1>
                    </a>
                    <p class="app-subtitle"><?= e(t('about')) ?></p>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-sm theme-toggle-btn" id="theme-toggle" title="<?= e(t('theme_toggle')) ?>">
                    <span class="theme-icon">☀️</span> <span class="theme-text"><?= e(t('theme_light')) ?></span>
                </button>
                <a href="<?= e($switchLangUrl) ?>" class="btn-sm" title="<?= e(t('switch_language')) ?>">🌐 <?= e(t('switch_language')) ?></a>
                <a href="<?= e(url('templates')) ?>" class="btn-sm">🎨 <?= e(t('templates')) ?></a>
                <a href="<?= e(url('projects')) ?>" class="btn-sm">📁 <?= e(t('my_designs')) ?></a>
                <a href="<?= e(url('history')) ?>" class="btn-sm">📜 <?= e(t('history')) ?></a>
                
                <?php if ($isLoggedIn && $currentUser): ?>
                    <a href="<?= e(url('profile')) ?>" class="user-badge">👤 <?= e($currentUser['name']) ?></a>
                    <a href="<?= e(url('logout')) ?>" class="btn-sm" style="color: var(--danger);"><?= e(t('logout')) ?></a>
                <?php else: ?>
                    <a href="<?= e(url('login')) ?>" class="btn-sm"><?= e(t('login')) ?></a>
                    <a href="<?= e(url('register')) ?>" class="btn-sm btn-primary"><?= e(t('register')) ?></a>
                <?php endif; ?>

                <a href="<?= e(url('/')) ?>" class="btn btn-sm btn-primary">+ <?= e(t('new_design')) ?></a>
            </div>
        </header>

        <!-- Hero Section -->
        <div class="glass-panel" style="text-align: center; padding: 3.5rem 2rem; margin-bottom: 2.5rem; position: relative; overflow: hidden;">
            <div style="font-size: 3.8rem; margin-bottom: 1rem; filter: drop-shadow(0 4px 16px rgba(99,102,241,0.4));">✨</div>
            <h2 style="font-size: 2.3rem; font-weight: 800; margin-bottom: 0.75rem; letter-spacing: -0.02em;">
                <?= e(t('about_title')) ?>
            </h2>
            <p style="font-size: 1.15rem; color: var(--accent); font-weight: 600; margin-bottom: 1.25rem;">
                <?= e(t('about_tagline')) ?>
            </p>
            <p style="max-width: 780px; margin: 0 auto 2rem; color: var(--text-muted); font-size: 0.95rem; line-height: 1.8;">
                <?= $isArabic 
                    ? 'منصة تصميم متكاملة فائقة السرعة تدمج قدرات الذكاء الاصطناعي التوليدي مع محرر متجهات مرن، مخصصة لإنتاج تصاميم جرافيكية احترافية ثنائية اللغة (العربية والإنجليزية) بأعلى درجات الدقة والجمالية.' 
                    : 'A comprehensive, high-velocity design platform merging generative AI intelligence with a fluid vector canvas editor, tailored for professional bilingual graphic design (Arabic and English) with exquisite typography and modern Liquid Glass aesthetics.' 
                ?>
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="<?= e(url('/')) ?>" class="btn btn-primary" style="padding: 0.75rem 1.75rem; font-size: 0.95rem;">
                    🚀 <?= $isArabic ? 'بدء التصميم الآن' : 'Launch Studio Canvas' ?>
                </a>
                <a href="<?= e(url('templates')) ?>" class="btn btn-secondary" style="padding: 0.75rem 1.75rem; font-size: 0.95rem;">
                    🎨 <?= e(t('explore_templates')) ?>
                </a>
            </div>
        </div>

        <!-- Core Features Grid -->
        <h3 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 1.25rem;">
            <?= $isArabic ? 'المميزات الجوهرية للاستوديو' : 'Core Studio Capabilities' ?>
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
            <!-- Feature 1: AI Engine -->
            <div class="glass-card" style="padding: 1.75rem;">
                <div style="font-size: 2.2rem; margin-bottom: 1rem;">🤖</div>
                <h4 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text);">
                    <?= e(t('about_feature_ai_title')) ?>
                </h4>
                <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.7;">
                    <?= e(t('about_feature_ai_desc')) ?>
                </p>
            </div>

            <!-- Feature 2: Native RTL & Arabic Fonts -->
            <div class="glass-card" style="padding: 1.75rem;">
                <div style="font-size: 2.2rem; margin-bottom: 1rem;">✍️</div>
                <h4 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text);">
                    <?= e(t('about_feature_rtl_title')) ?>
                </h4>
                <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.7;">
                    <?= e(t('about_feature_rtl_desc')) ?>
                </p>
            </div>

            <!-- Feature 3: Liquid Glass Design -->
            <div class="glass-card" style="padding: 1.75rem;">
                <div style="font-size: 2.2rem; margin-bottom: 1rem;">💎</div>
                <h4 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text);">
                    <?= e(t('about_feature_glass_title')) ?>
                </h4>
                <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.7;">
                    <?= e(t('about_feature_glass_desc')) ?>
                </p>
            </div>

            <!-- Feature 4: High-Res Export -->
            <div class="glass-card" style="padding: 1.75rem;">
                <div style="font-size: 2.2rem; margin-bottom: 1rem;">⚡</div>
                <h4 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text);">
                    <?= e(t('about_feature_export_title')) ?>
                </h4>
                <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.7;">
                    <?= e(t('about_feature_export_desc')) ?>
                </p>
            </div>
        </div>

        <!-- How It Works / Quick Help Guide -->
        <div class="glass-panel" style="padding: 2.5rem; margin-bottom: 3rem;">
            <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                💡 <?= $isArabic ? 'دليل الاستخدام السريع' : 'Quick Usage Guide & Help' ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                <div>
                    <h5 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--accent);">
                        1. <?= $isArabic ? 'التوليد الذكي بواسطة AI' : 'Generative AI Prompting' ?>
                    </h5>
                    <p style="font-size: 0.86rem; color: var(--text-muted); line-height: 1.7;">
                        <?= $isArabic 
                            ? 'اكتب وصفاً لفكرتك (مثل: "بوستر إعلان لقهوة مختصة بتدرجات دافئة وخصم 20%")، اختر اللغة والمقاس، واضغط "توليد". سيقوم النظام بإنشاء عناصر مستقلة قابلة للتحريك.' 
                            : 'Type your concept (e.g., "Specialty coffee shop flyer with warm tones and 20% discount"), select language and size, then click Generate. The AI constructs modular, editable vector elements.' 
                        ?>
                    </p>
                </div>

                <div>
                    <h5 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--accent);">
                        2. <?= $isArabic ? 'التحرير والطبقات المتطورة' : 'Visual Vector Editing' ?>
                    </h5>
                    <p style="font-size: 0.86rem; color: var(--text-muted); line-height: 1.7;">
                        <?= $isArabic 
                            ? 'انقر نقراً مزدوجاً على أي نص لتعديله فوراً. استخدم شريط الأدوات لإضافة أشكال، صور، وأيقونات، ولوحة الطبقات لإعادة الترتيب والقفل.' 
                            : 'Double-click any text element for instant in-place editing. Add shapes, custom images, and icons via the toolbar, and organize stacking with the Layers panel.' 
                        ?>
                    </p>
                </div>

                <div>
                    <h5 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--accent);">
                        3. <?= $isArabic ? 'شريط التعديل الذكي العائم' : 'Floating AI Command Bar' ?>
                    </h5>
                    <p style="font-size: 0.86rem; color: var(--text-muted); line-height: 1.7;">
                        <?= $isArabic 
                            ? 'في أسفل الكانفاس، استخدم شريط التعديل الذكي لإعطاء أوامر مثل: "اجعل الخلفية ليلية"، "غيّر الخط إلى خط عربي عريض"، ليقوم الذكاء الاصطناعي بتحديث التصميم بسلاسة.' 
                            : 'At the bottom of the canvas, use the floating command bar to instruct the AI (e.g., "Change background to dark navy", "Make heading bolder") without starting over.' 
                        ?>
                    </p>
                </div>

                <div>
                    <h5 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--accent);">
                        4. <?= $isArabic ? 'التصدير والحفظ السحابي' : 'Export & Cloud Saving' ?>
                    </h5>
                    <p style="font-size: 0.86rem; color: var(--text-muted); line-height: 1.7;">
                        <?= $isArabic 
                            ? 'صدّر تصاميمك بدقة 1x، 2x، 3x بصيغ PNG الشفافة، أو JPG، أو SVG، أو كملف JSON للمشروع. كما يتم الحفظ التلقائي على جهازك وحسابك.' 
                            : 'Export at 1x, 2x, or 3x resolutions in transparent PNG, crisp JPG, vector SVG, or raw project JSON. Your work auto-saves automatically.' 
                        ?>
                    </p>
                </div>
            </div>

            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--glass-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <span style="font-size: 0.86rem; color: var(--text-muted);">
                    ⌨️ <?= $isArabic ? 'اضغط علامة الاستفهام (?) في المحرر لفتح قائمة اختصارات لوحة المفاتيح' : 'Press (?) in the studio editor anytime to inspect all keyboard shortcuts.' ?>
                </span>
                <a href="<?= e(url('/')) ?>" class="btn btn-sm btn-secondary"><?= e(t('return_to_studio')) ?></a>
            </div>
        </div>

        <!-- Footer -->
        <footer style="text-align: center; font-size: 0.85rem; color: var(--text-muted); padding: 1rem 0;">
            <p><?= e(t('app_name')) ?> &copy; <?= date('Y') ?> &bull; <?= e(t('tagline')) ?></p>
        </footer>
    </div>

    <!-- Theme Manager Script -->
    <script type="module">
        import { ThemeManager } from './assets/js/editor/theme-manager.js';
        ThemeManager.init();
    </script>
</body>
</html>
