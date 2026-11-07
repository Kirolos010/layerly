<?php
/**
 * AI Design Studio - Phase 4: Final Apple-Style Liquid Glass UI & Bilingual RTL Platform
 * Bilingual (Arabic + English) AI-powered graphic design editor.
 */

define('APP_INIT', true);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';

// Send security headers & initialize session
send_security_headers();
init_secure_session();

$csrfToken = get_csrf_token();
$currentLang = get_current_lang();
$isArabic = ($currentLang === 'ar');
$dir = $isArabic ? 'rtl' : 'ltr';
$otherLang = $isArabic ? 'en' : 'ar';
$switchLangUrl = '?lang=' . $otherLang;

$isLoggedIn = is_logged_in();
$currentUser = get_auth_user();

$guestToken = $_SESSION['guest_token'] ?? ($_COOKIE['guest_token'] ?? null);
$guestDesignsCount = (!$isLoggedIn && $guestToken) ? get_guest_designs_count($guestToken) : 0;

$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

$reusePrompt = trim((string)($_GET['reuse_prompt'] ?? ''));
$loadGenerationId = isset($_GET['load_generation']) ? (int)$_GET['load_generation'] : 0;
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    
    <!-- SEO & Metadata -->
    <title><?= e(t('meta_title')) ?></title>
    <meta name="description" content="<?= e(t('meta_desc')) ?>">
    <meta name="keywords" content="AI design studio, vector canvas, Arabic graphic design, Cairo font, Fabric.js editor, Liquid Glass UI">
    <meta name="author" content="AI Design Studio">
    
    <!-- Open Graph / Social Sharing -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e(t('og_title')) ?>">
    <meta property="og:description" content="<?= e(t('og_desc')) ?>">
    <meta property="og:locale" content="<?= $isArabic ? 'ar_AR' : 'en_US' ?>">
    <meta property="og:locale:alternate" content="<?= $isArabic ? 'en_US' : 'ar_AR' ?>">
    <meta property="og:site_name" content="AI Design Studio">

    <!-- Multilingual Alternate hreflang -->
    <link rel="alternate" hreflang="ar" href="index.php?lang=ar">
    <link rel="alternate" hreflang="en" href="index.php?lang=en">
    <link rel="alternate" hreflang="x-default" href="index.php">

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
    
    <!-- Google Fonts for Arabic & English Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700&family=Amiri:ital,wght@0,400;0,700;1,400&family=Cairo:wght@400;600;700;800&family=Montserrat:wght@400;600;700&family=Playfair+Display:wght@400;600;700&family=Poppins:wght@400;500;600;700&family=Reem+Kufi:wght@500;700&family=Roboto:wght@400;500;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- Fabric.js 5.3.1 CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

    <!-- Injected Translation Map for ES6 Vanilla Modules -->
    <script>
        window.__I18N__ = <?= json_encode(load_translations($currentLang), JSON_UNESCAPED_UNICODE) ?>;
        window.__CURRENT_LANG__ = "<?= e($currentLang) ?>";
        window.i18n = function(key, fallback) {
            return (window.__I18N__ && window.__I18N__[key]) ? window.__I18N__[key] : (fallback || key);
        };
    </script>

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-load-id="<?= $loadGenerationId ?>">
    <!-- Background Animated Mesh Gradient Orbs -->
    <div class="glass-mesh-backdrop" aria-hidden="true">
        <div class="mesh-orb mesh-orb-1"></div>
        <div class="mesh-orb mesh-orb-2"></div>
        <div class="mesh-orb mesh-orb-3"></div>
        <div class="mesh-orb mesh-orb-4"></div>
    </div>

    <!-- SVG Refraction Filter (Chromium glass-refract support) -->
    <svg style="display:none;" aria-hidden="true">
        <filter id="glass-distortion">
            <feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="3" result="noise" />
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="3" xChannelSelector="R" yChannelSelector="G" />
        </filter>
    </svg>

    <div class="container studio-container" style="max-width: 98vw; width: 98%; padding: 0 0.75rem;">
        <!-- Floating Glass Header -->
        <header class="app-header">
            <div class="app-brand-group">
                <div class="app-logo-badge">🎨</div>
                <div>
                    <a href="index.php" style="text-decoration: none;">
                        <h1 class="app-title"><?= e(t('app_name')) ?></h1>
                    </a>
                    <p class="app-subtitle"><?= e(t('app_tagline')) ?></p>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-sm theme-toggle-btn" id="theme-toggle" title="<?= e(t('theme_toggle')) ?>">
                    <span class="theme-icon">☀️</span> <span class="theme-text"><?= e(t('theme_light')) ?></span>
                </button>
                <a href="<?= e($switchLangUrl) ?>" class="btn-sm" title="<?= e(t('switch_language')) ?>">🌐 <?= e(t('switch_language')) ?></a>
                <a href="templates.php" class="btn-sm">🎨 <?= e(t('templates')) ?></a>
                <a href="projects.php" class="btn-sm">📁 <?= e(t('my_designs')) ?></a>
                <a href="history.php" class="btn-sm">📜 <?= e(t('history')) ?></a>
                <a href="about.php" class="btn-sm">ℹ️ <?= e(t('about')) ?></a>
                <button type="button" class="btn-sm" id="btn-open-shortcuts" title="<?= e(t('shortcuts_title')) ?>">⌨️</button>
                
                <?php if ($isLoggedIn): ?>
                    <a href="profile.php" class="user-badge">👤 <?= e($currentUser['name']) ?></a>
                    <a href="logout.php" class="btn-sm" style="color: var(--danger);"><?= e(t('logout')) ?></a>
                <?php else: ?>
                    <a href="login.php" class="btn-sm"><?= e(t('login')) ?></a>
                    <a href="register.php" class="btn-sm btn-primary"><?= e(t('register')) ?></a>
                <?php endif; ?>

                <a href="diagnostics.php?secret=<?= defined('DIAGNOSTICS_SECRET') ? urlencode(DIAGNOSTICS_SECRET) : '' ?>" class="btn-sm" title="Diagnostics">🛠️</a>
            </div>
        </header>

        <!-- Autosave Restore Banner -->
        <div id="autosave-restore-banner" class="guest-banner" style="display: none; background: rgba(59, 130, 246, 0.15); border-color: var(--primary);">
            <div class="guest-banner-text">
                💾 <strong><?= e(t('restore_session')) ?>:</strong> <?= e(t('autosave_banner_text')) ?>
            </div>
            <div class="guest-banner-actions">
                <button type="button" class="btn-sm btn-primary" id="btn-restore-autosave"><?= e(t('keep')) ?></button>
                <button type="button" class="btn-sm" id="btn-discard-autosave"><?= e(t('discard')) ?></button>
            </div>
        </div>

        <!-- Flash Success Notification -->
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" style="display: block;">
                <?= e($flashSuccess) ?>
            </div>
        <?php endif; ?>

        <!-- Guest Mode Alert Banner -->
        <?php if (!$isLoggedIn && $guestDesignsCount > 0): ?>
            <div class="guest-banner">
                <div class="guest-banner-text">
                    ⚠️ <strong><?= e(t('guest_mode')) ?>:</strong> <?= e(t('guest_mode_banner')) ?>
                    <span style="display: block; font-size: 0.82rem; margin-top: 2px; color: var(--text-muted);">
                        <?= e(t('guest_banner_action')) ?>
                    </span>
                </div>
                <div class="guest-banner-actions">
                    <a href="register.php" class="btn-sm btn-primary"><?= e(t('register')) ?></a>
                    <a href="login.php" class="btn-sm"><?= e(t('login')) ?></a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Status Alert Banner -->
        <div id="status-alert" class="alert"></div>

        <!-- Top Studio Action Bar (Presets, Zoom, Undo/Redo, Export) -->
        <div class="editor-topbar">
            <!-- Left Group: History & Zoom -->
            <div class="topbar-group">
                <button type="button" class="topbar-btn" id="btn-undo" title="<?= e(t('undo')) ?>" disabled><span class="rtl-mirror">↶</span> <?= e(t('undo')) ?></button>
                <button type="button" class="topbar-btn" id="btn-redo" title="<?= e(t('redo')) ?>" disabled><span class="rtl-mirror">↷</span> <?= e(t('redo')) ?></button>
                
                <span style="color: var(--border); margin: 0 4px;">|</span>

                <button type="button" class="topbar-btn" id="btn-zoom-out" title="<?= e(t('zoom_out')) ?>">-</button>
                <span class="zoom-pill" id="zoom-percentage">100%</span>
                <button type="button" class="topbar-btn" id="btn-zoom-in" title="<?= e(t('zoom_in')) ?>">+</button>
                <button type="button" class="topbar-btn" id="btn-zoom-fit" title="<?= e(t('zoom_fit')) ?>"><?= e(t('zoom_fit')) ?></button>
                <button type="button" class="topbar-btn" id="btn-zoom-fit-width" title="<?= e(t('zoom_fit_width')) ?>">↔ <?= e(t('zoom_fit_width')) ?></button>
                <button type="button" class="topbar-btn" id="btn-zoom-reset" title="<?= e(t('zoom_reset')) ?>">100%</button>
                <button type="button" class="topbar-btn" id="btn-focus-mode" title="<?= e(t('focus_mode')) ?>">⛶ <?= e(t('focus_mode')) ?></button>
                <button type="button" class="topbar-btn" id="btn-toggle-grid" title="<?= e(t('toggle_grid')) ?>"># Grid</button>
            </div>

            <!-- Middle Group: Dimension Presets -->
            <div class="topbar-group">
                <select id="dimension-preset" style="padding: 0.45rem 0.65rem; font-size: 0.85rem; width: auto;">
                    <option value="square"><?= e(t('preset_square')) ?></option>
                    <option value="story"><?= e(t('preset_story')) ?></option>
                    <option value="youtube"><?= e(t('preset_youtube')) ?></option>
                    <option value="facebook"><?= e(t('preset_facebook')) ?></option>
                    <option value="a4"><?= e(t('preset_a4')) ?></option>
                </select>

                <div style="display: flex; gap: 4px; align-items: center;">
                    <input type="number" id="canvas-width" value="1080" min="100" max="4000" style="width: 72px; padding: 0.45rem; font-size: 0.82rem;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">×</span>
                    <input type="number" id="canvas-height" value="1080" min="100" max="4000" style="width: 72px; padding: 0.45rem; font-size: 0.82rem;">
                    <button type="button" class="topbar-btn" id="btn-apply-dimensions" title="<?= e(t('apply_dimensions')) ?>">✓</button>
                </div>
            </div>

            <!-- Right Group: Project Management & Export -->
            <div class="topbar-group" style="gap: 8px;">
                <input type="text" id="project-name-input" value="Untitled Design" placeholder="<?= e(t('project_name')) ?>" style="max-width: 170px; padding: 0.4rem 0.65rem; font-size: 0.82rem; font-weight: 600; background: var(--glass-bg-subtle); border: 1px solid var(--glass-border); border-radius: var(--radius-pill); color: var(--text);">
                <button type="button" class="topbar-btn" id="btn-save-project" title="<?= e(t('save_project')) ?>">💾 <?= e(t('save_project')) ?></button>
                <span id="save-status-text" style="display: none; font-size: 0.8rem; font-weight: 600;"></span>

                <span style="color: var(--border); margin: 0 2px;">|</span>

                <button type="button" class="topbar-btn btn-primary" id="btn-open-export-modal">💾 <?= e(t('export_options')) ?></button>
            </div>
        </div>

        <!-- AI Prompt Bar (Hero Glass Container) -->
        <div class="ai-prompt-bar glass-refract" id="ai-prompt-bar">
            <!-- Header with Minimize / Expand toggle & AI Poster pipeline -->
            <div class="prompt-bar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem;">
                <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-heading); display: flex; align-items: center; gap: 0.4rem;">
                    <span>✨</span> <span><?= e(t('ai_generator_title')) ?></span>
                </span>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <button type="button" class="btn-poster-sparkle" id="btn-open-poster-modal" title="<?= e(t('ai_poster_desc')) ?>" style="padding: 0.3rem 0.85rem; font-size: 0.82rem; font-weight: 700; border-radius: var(--radius-pill); background: linear-gradient(135deg, rgba(0, 240, 255, 0.2), rgba(120, 0, 255, 0.28)); border: 1px solid rgba(0, 240, 255, 0.6); color: #00f0ff; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 0 14px rgba(0, 240, 255, 0.25);">
                        <span>✨</span> <span><?= e(t('ai_poster')) ?></span>
                        <span style="background: #00f0ff; color: #020b14; padding: 1px 6px; border-radius: 8px; font-size: 0.65rem; font-weight: 800;"><?= e(t('ai_poster_new')) ?></span>
                    </button>
                    <button type="button" class="btn-sm" id="btn-toggle-prompt-bar" title="<?= e(t('collapse_generator')) ?>" style="padding: 0.25rem 0.65rem; font-size: 0.78rem; display: flex; align-items: center; gap: 4px;">
                        <span id="prompt-bar-toggle-icon">▲</span>
                        <span id="prompt-bar-toggle-text"><?= e(t('collapse_generator')) ?></span>
                    </button>
                </div>
            </div>

            <div class="prompt-bar-body">
                <!-- Example Prompts (Arabic & English) -->
                <div class="prompt-chips-wrapper">
                    <span style="font-size: 0.8rem; color: var(--text-muted); align-self: center;"><?= e(t('example_prompts')) ?>:</span>
                    <span class="prompt-chip" data-prompt="تصميم بوست انستغرام عصري لعروض شهر رمضان مع فوانيس ذهبية وخصم 40% وخلفية كحلية راقية">🌙 عروض رمضان الفاخرة</span>
                    <span class="prompt-chip" data-prompt="إعلان ترويجي لكورس برمجة وتطوير الذكاء الاصطناعي مع شارة خصم وألوان زرقاء تقنية">💻 كورس الذكاء الاصطناعي</span>
                    <span class="prompt-chip" data-prompt="Modern luxury coffee shop special weekend offer with elegant typography, warm earth tones, and 30% discount badge">☕ Coffee Shop Promo</span>
                    <span class="prompt-chip" data-prompt="Bold vibrant summer fitness workout club promotional banner with energizing neon typography">🔥 Summer Fitness Club</span>
                </div>

                <!-- Prompt Input with Enhance & Restore -->
                <div class="form-group" style="margin-bottom: 0.75rem;">
                    <textarea id="prompt-input" rows="2" placeholder="<?= e(t('prompt_placeholder')) ?>" style="min-height: 70px;"><?= e($reusePrompt) ?></textarea>
                </div>

                <!-- Generator Controls Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap: 0.75rem; align-items: end;">
                    <div>
                        <label for="design-type"><?= e(t('design_type')) ?></label>
                        <select id="design-type" style="padding: 0.55rem;">
                            <option value="social_post">Social Media Post</option>
                            <option value="story">Story / Reel</option>
                            <option value="banner">Promotional Banner</option>
                            <option value="flyer">Flyer / Poster</option>
                            <option value="quote">Quote Card</option>
                        </select>
                    </div>
                    <div>
                        <label for="design-style"><?= e(t('style')) ?></label>
                        <select id="design-style" style="padding: 0.55rem;">
                            <option value="modern">Modern & Clean</option>
                            <option value="minimalist">Minimalist</option>
                            <option value="bold">Bold & Vibrant</option>
                            <option value="luxury">Luxury & Elegant</option>
                            <option value="corporate">Corporate</option>
                        </select>
                    </div>
                    <div>
                        <label for="color-mood"><?= e(t('color_mood')) ?></label>
                        <select id="color-mood" style="padding: 0.55rem;">
                            <option value="vibrant">Vibrant & Punchy</option>
                            <option value="dark_luxury">Dark & Luxury</option>
                            <option value="warm_sunset">Warm & Sunset</option>
                            <option value="pastel">Soft Pastel</option>
                            <option value="cool_blue">Cool & Tech Blue</option>
                        </select>
                    </div>
                    <div>
                        <label for="text-language"><?= e(t('output_language')) ?></label>
                        <select id="text-language" style="padding: 0.55rem;">
                            <option value="auto">Auto-Detect from Prompt</option>
                            <option value="ar">العربية (Arabic)</option>
                            <option value="en">English</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center; padding-bottom: 0.6rem;">
                        <label for="generate-bg-toggle" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; font-size: 0.82rem; font-weight: 600; color: var(--text);" title="<?= e(t('generate_ai_background_desc')) ?>">
                            <input type="checkbox" id="generate-bg-toggle" style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary);">
                            <span>🖼️ <?= e(t('generate_ai_background')) ?></span>
                        </label>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" id="btn-undo-enhance" class="btn btn-sm" style="display: none; background: #0f172a;" title="<?= e(t('undo_enhance')) ?>">↺</button>
                        <button type="button" id="btn-enhance" class="btn btn-accent" style="padding: 0.6rem 1rem;"><?= e(t('btn_enhance')) ?></button>
                        <button type="button" id="btn-generate" class="btn btn-primary btn-glow" style="padding: 0.6rem 1.4rem;"><?= e(t('btn_generate')) ?></button>
                        <button type="button" id="btn-regenerate" class="btn btn-sm" title="<?= e(t('regenerate')) ?>" style="padding: 0.6rem 0.85rem;">🔁</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main 3-Column Studio Workspace -->
        <div class="editor-workspace">
            <!-- Left Column: Element Toolbox & Background (Floating Dock) -->
            <aside class="tools-sidebar">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                    <span class="tool-section-title" style="margin-bottom: 0;"><?= e(t('tools_title')) ?></span>
                    <button type="button" class="btn-icon" id="btn-collapse-tools" title="<?= e(t('collapse_panel')) ?>">◧</button>
                </div>

                <!-- Add Text -->
                <div>
                    <div class="tool-section-title">🔤 <?= e(t('add_text')) ?></div>
                    <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                        <button type="button" class="tool-btn tool-btn-full" id="tool-add-heading">
                            <span style="font-weight: 700; font-size: 1.1rem;">T</span>
                            <span class="tool-label"><?= e(t('add_heading')) ?></span>
                        </button>
                        <button type="button" class="tool-btn tool-btn-full" id="tool-add-subheading">
                            <span style="font-weight: 600; font-size: 0.95rem;">T</span>
                            <span class="tool-label"><?= e(t('add_subheading')) ?></span>
                        </button>
                        <button type="button" class="tool-btn tool-btn-full" id="tool-add-body">
                            <span style="font-size: 0.85rem;">T</span>
                            <span class="tool-label"><?= e(t('add_body')) ?></span>
                        </button>
                    </div>
                </div>

                <!-- Add Shapes -->
                <div>
                    <div class="tool-section-title">🔷 <?= e(t('add_shape')) ?></div>
                    <div class="tool-btn-grid">
                        <button type="button" class="tool-btn" id="tool-add-rect">
                            <span>⬛</span>
                            <span class="tool-label"><?= e(t('rectangle')) ?></span>
                        </button>
                        <button type="button" class="tool-btn" id="tool-add-circle">
                            <span>⚪</span>
                            <span class="tool-label"><?= e(t('circle')) ?></span>
                        </button>
                        <button type="button" class="tool-btn" id="tool-add-triangle">
                            <span>🔺</span>
                            <span class="tool-label"><?= e(t('triangle')) ?></span>
                        </button>
                        <button type="button" class="tool-btn" id="tool-add-star">
                            <span>⭐</span>
                            <span class="tool-label"><?= e(t('star')) ?></span>
                        </button>
                        <button type="button" class="tool-btn" id="tool-add-line" style="grid-column: span 2;">
                            <span>➖</span>
                            <span class="tool-label"><?= e(t('line')) ?></span>
                        </button>
                    </div>
                </div>

                <!-- Icons & Uploads -->
                <div>
                    <div class="tool-section-title">🎨 <?= e(t('icons')) ?> & <?= e(t('upload_image')) ?></div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <button type="button" class="tool-btn tool-btn-full" id="tool-open-icons">
                            <span>✨</span>
                            <span class="tool-label"><?= e(t('icons')) ?> (14 SVGs)</span>
                        </button>

                        <button type="button" class="tool-btn tool-btn-full" id="tool-upload-image">
                            <span>🖼️</span>
                            <span class="tool-label"><?= e(t('upload_image')) ?> (5MB)</span>
                        </button>
                        <input type="file" id="tool-file-input" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="display: none;">
                    </div>
                </div>

                <!-- Canvas Background Settings -->
                <div>
                    <div class="tool-section-title">🌈 <?= e(t('background')) ?></div>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                        <div class="prop-field">
                            <label><?= e(t('solid_color')) ?></label>
                            <input type="color" id="bg-color-picker" value="#ffffff" style="width: 100%; height: 34px; padding: 2px;">
                        </div>

                        <!-- Gradient -->
                        <div class="prop-field">
                            <label><?= e(t('gradient')) ?></label>
                            <div style="display: flex; gap: 4px; margin-bottom: 4px;">
                                <input type="color" id="bg-grad-color1" value="#1e3a8a" style="flex: 1; height: 30px; padding: 2px;">
                                <input type="color" id="bg-grad-color2" value="#9333ea" style="flex: 1; height: 30px; padding: 2px;">
                            </div>
                            <div style="display: flex; gap: 4px;">
                                <select id="bg-grad-direction" style="font-size: 0.78rem; padding: 0.35rem;">
                                    <option value="vertical">Vertical ↓</option>
                                    <option value="horizontal">Horizontal →</option>
                                    <option value="diagonal">Diagonal ↘</option>
                                </select>
                                <button type="button" class="btn-sm" id="bg-apply-gradient" style="padding: 0.35rem 0.65rem;">Apply</button>
                            </div>
                        </div>

                        <!-- AI Background via Pollinations -->
                        <div class="prop-field">
                            <label><?= e(t('ai_background')) ?></label>
                            <input type="text" id="bg-ai-prompt" placeholder="e.g. abstract neon bokeh lights" style="font-size: 0.8rem; padding: 0.45rem;">
                            <button type="button" class="btn-sm btn-accent" id="bg-btn-ai-gen" style="margin-top: 4px; padding: 0.45rem; width: 100%;">
                                ✨ <?= e(t('generate_ai_bg')) ?>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Center Column: Canvas Viewport & Floating AI Command Bar -->
            <main class="canvas-column" style="position: relative; display: flex; flex-direction: column;">
                <div class="canvas-viewport" id="canvas-viewport">
                    <canvas id="design-canvas"></canvas>
                    <canvas id="grid-overlay" style="display: none; position: absolute; pointer-events: none; z-index: 5;"></canvas>

                    <!-- Floating Glass AI Command Bar (Bottom Center) -->
                    <div class="floating-ai-command-bar" id="floating-ai-bar">
                        <span style="font-size: 1.15rem;">✏️</span>
                        <input type="text" id="instruction-input" class="floating-ai-input" placeholder="<?= e(t('edit_with_ai_placeholder')) ?>" autocomplete="off">
                        <button type="button" id="btn-edit" class="btn btn-sm btn-primary" style="padding: 0.45rem 1rem;" disabled>
                            <?= e(t('btn_apply_ai_edit')) ?>
                        </button>
                    </div>

                    <!-- Floating Viewport HUD (Zoom, Focus, Compare & Realign) -->
                    <div class="canvas-viewport-hud" id="canvas-viewport-hud">
                        <button type="button" class="hud-btn" id="hud-zoom-out" title="<?= e(t('zoom_out')) ?>">−</button>
                        <span class="hud-zoom-val" id="hud-zoom-val" title="<?= e(t('zoom_reset')) ?>">100%</span>
                        <button type="button" class="hud-btn" id="hud-zoom-in" title="<?= e(t('zoom_in')) ?>">+</button>
                        <button type="button" class="hud-btn" id="hud-zoom-fit" title="<?= e(t('zoom_fit')) ?>">⛶</button>
                        <button type="button" class="hud-btn" id="hud-zoom-fit-width" title="<?= e(t('zoom_fit_width')) ?>">↔</button>
                        <button type="button" class="hud-btn" id="hud-focus-mode" title="<?= e(t('focus_mode')) ?>">🖥️</button>
                        
                        <span style="color: var(--border); margin: 0 4px; display: none;" id="hud-poster-divider">|</span>
                        
                        <button type="button" class="topbar-btn" id="btn-compare-original" style="display: none; padding: 0.25rem 0.65rem; font-size: 0.76rem; background: rgba(0, 240, 255, 0.15); border-color: rgba(0, 240, 255, 0.4); color: #00f0ff;" title="<?= e(t('compare_view_hint')) ?>">
                            <span>👁️</span> <span><?= e(t('compare_view')) ?></span>
                        </button>
                        <button type="button" class="topbar-btn" id="btn-realign-layers" style="display: none; padding: 0.25rem 0.65rem; font-size: 0.76rem;" title="<?= e(t('realign_tooltip')) ?>">
                            <span>🎯</span> <span><?= e(t('btn_realign')) ?></span>
                        </button>
                    </div>

                    <!-- Compare View Opacity Slider Floating Widget -->
                    <div id="compare-slider-modal" class="glass-panel" style="display: none; position: absolute; bottom: 65px; left: 50%; transform: translateX(-50%); z-index: 100; padding: 0.5rem 1rem; border-radius: 30px; align-items: center; gap: 0.75rem; box-shadow: 0 8px 32px rgba(0,0,0,0.5); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(0, 240, 255, 0.4);">
                        <span style="font-size: 0.76rem; font-weight: 700; color: #00f0ff;"><?= e(t('rebuilt_poster_label')) ?></span>
                        <input type="range" id="compare-opacity-slider" min="0" max="1" step="0.05" value="0.5" style="width: 130px; cursor: pointer; accent-color: #00f0ff;">
                        <span style="font-size: 0.76rem; font-weight: 700; color: var(--text);"><?= e(t('original_poster_label')) ?></span>
                        <button type="button" id="btn-close-compare-slider" class="btn-icon" style="width: 22px; height: 22px; font-size: 0.7rem; margin-inline-start: 4px;">✕</button>
                    </div>
                </div>
            </main>

            <!-- Right Column: Inspector & Layers Tabs (Floating Inspector) -->
            <aside class="inspector-sidebar">
                <div class="inspector-tabs-header">
                    <button type="button" class="inspector-tab-btn active" id="tab-properties-btn">
                        ⚙️ <?= e(t('properties')) ?>
                    </button>
                    <button type="button" class="inspector-tab-btn" id="tab-layers-btn">
                        📑 <?= e(t('layers')) ?>
                    </button>
                    <button type="button" class="btn-icon" id="btn-collapse-inspector" style="margin-inline-start: auto;" title="<?= e(t('collapse_panel')) ?>">◨</button>
                </div>

                <!-- Properties Pane -->
                <div class="tab-content active" id="tab-properties-pane">
                    <div id="properties-container"></div>
                </div>

                <!-- Layers Pane -->
                <div class="tab-content" id="tab-layers-pane">
                    <div id="layers-container"></div>
                </div>

                <!-- Status & Telemetry Bar -->
                <div style="background: rgba(0, 0, 0, 0.25); border-top: 1px solid var(--border); padding: 0.5rem 0.75rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: var(--text-muted); border-radius: 0 0 var(--radius-lg) var(--radius-lg); flex-wrap: wrap; gap: 4px;">
                    <div><?= e(t('stats_objects')) ?>: <strong id="objects-stat" style="color: var(--text);">0</strong></div>
                    <div><?= e(t('stats_time')) ?>: <strong id="time-stat" style="color: var(--text);">0ms</strong></div>
                    <div><?= e(t('stats_provider')) ?>: <strong id="provider-stat" style="color: var(--text);">-</strong></div>
                    <span id="poster-meta-pill" class="stat-pill" style="display: none; padding: 0.15rem 0.45rem; font-size: 0.68rem; background: rgba(0, 240, 255, 0.15); color: #00f0ff; border: 1px solid rgba(0, 240, 255, 0.35);"></span>
                    <span id="status-pill" class="zoom-pill" data-default="<?= e(t('status_idle')) ?>" style="padding: 0.15rem 0.45rem; font-size: 0.7rem;"><?= e(t('status_idle')) ?></span>
                </div>
            </aside>
        </div>
    </div>

    <!-- Built-in Vector Icons Modal -->
    <div class="modal-overlay" id="icons-modal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 style="font-size: 1.1rem; color: var(--text);">🎨 Built-in Vector Icons</h3>
                <button type="button" class="btn-icon" id="close-icons-modal">✕</button>
            </div>
            <div class="modal-body">
                <div class="icons-modal-grid" id="icons-grid"></div>
            </div>
        </div>
    </div>

    <!-- Advanced Export & Import Modal -->
    <div class="modal-overlay" id="export-modal" style="display: none;">
        <div class="modal-content export-modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 style="font-size: 1.15rem; color: var(--text); display: flex; align-items: center; gap: 8px;">
                    💾 <?= e(t('export_options')) ?>
                </h3>
                <button type="button" class="btn-icon" id="close-export-modal">✕</button>
            </div>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1.1rem; padding: 1.25rem 1.5rem;">
                
                <!-- PNG Section -->
                <div class="export-section-card">
                    <div class="export-section-header">
                        <div>
                            <strong>PNG Image</strong>
                            <span class="export-format-badge">Lossless</span>
                        </div>
                        <span class="export-dim-pill" id="png-dimensions-preview">1080 × 1080 px</span>
                    </div>
                    <div class="export-controls-row">
                        <div class="export-control-group">
                            <label for="export-png-scale"><?= e(t('scale')) ?></label>
                            <select id="export-png-scale" class="form-control-sm">
                                <option value="1">1x (Original)</option>
                                <option value="2">2x (Retina / 2K)</option>
                                <option value="3">3x (Ultra HD / 4K)</option>
                            </select>
                        </div>
                        <div class="export-control-group" style="justify-content: flex-end;">
                            <label class="checkbox-label" style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin-top: 1.4rem;">
                                <input type="checkbox" id="export-png-transparent">
                                <span><?= e(t('transparent_background')) ?></span>
                            </label>
                        </div>
                        <div style="align-self: flex-end;">
                            <button type="button" class="btn btn-sm btn-primary" id="btn-export-png">
                                ⬇️ <?= e(t('export_png')) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- JPG Section -->
                <div class="export-section-card">
                    <div class="export-section-header">
                        <div>
                            <strong>JPG Image</strong>
                            <span class="export-format-badge">Compressed</span>
                        </div>
                        <span class="export-dim-pill" id="jpg-dimensions-preview">1080 × 1080 px</span>
                    </div>
                    <div class="export-controls-row">
                        <div class="export-control-group">
                            <label for="export-jpg-scale"><?= e(t('scale')) ?></label>
                            <select id="export-jpg-scale" class="form-control-sm">
                                <option value="1">1x (Original)</option>
                                <option value="2">2x (High Res)</option>
                                <option value="3">3x (Print 300 DPI)</option>
                            </select>
                        </div>
                        <div class="export-control-group" style="flex: 1.2;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 4px;">
                                <label for="export-jpg-quality"><?= e(t('quality')) ?></label>
                                <span id="export-quality-val" style="font-weight: 600; color: var(--primary);">92%</span>
                            </div>
                            <input type="range" id="export-jpg-quality" min="0.1" max="1.0" step="0.05" value="0.92" style="width: 100%;">
                        </div>
                        <div style="align-self: flex-end;">
                            <button type="button" class="btn btn-sm" id="btn-export-jpg">
                                ⬇️ <?= e(t('export_jpg')) ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SVG Vector & JSON Section -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <!-- SVG -->
                    <div class="export-section-card" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <strong>SVG Vector</strong>
                            <span class="export-format-badge">Scalable</span>
                        </div>
                        <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                            Sharp vector format ideal for logos, web assets, and print.
                        </p>
                        <button type="button" class="btn btn-sm" id="btn-export-svg" style="width: 100%;">
                            ⬇️ <?= e(t('export_svg')) ?>
                        </button>
                    </div>

                    <!-- Project JSON -->
                    <div class="export-section-card" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <strong>Project Backup</strong>
                            <span class="export-format-badge">Editable</span>
                        </div>
                        <p style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                            Download raw editable studio JSON or import past designs.
                        </p>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" class="btn btn-sm" id="btn-export-json" style="flex: 1;" title="<?= e(t('export_json')) ?>">
                                💾 Save JSON
                            </button>
                            <button type="button" class="btn btn-sm" id="btn-import-json-trigger" style="flex: 1;" title="<?= e(t('import_json')) ?>">
                                📂 Import JSON
                            </button>
                            <input type="file" id="input-import-json" accept=".json,application/json" style="display: none;">
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Keyboard Shortcuts Modal -->
    <div class="modal-overlay" id="shortcuts-modal" style="display: none;">
        <div class="modal-content" style="max-width: 580px;">
            <div class="modal-header">
                <h3 style="font-size: 1.15rem; color: var(--text); display: flex; align-items: center; gap: 8px;">
                    ⌨️ <?= e(t('shortcuts_title')) ?>
                </h3>
                <button type="button" class="btn-icon" id="close-shortcuts-modal">✕</button>
            </div>
            <div class="modal-body" style="padding: 1.25rem 1.5rem;">
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                    <?= e(t('shortcuts_desc')) ?>
                </p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="glass-panel" style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-md);">
                        <span style="font-size: 0.82rem; color: var(--text);"><?= e(t('key_undo')) ?></span>
                        <kbd class="zoom-pill">Ctrl + Z</kbd>
                    </div>
                    <div class="glass-panel" style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-md);">
                        <span style="font-size: 0.82rem; color: var(--text);"><?= e(t('key_redo')) ?></span>
                        <kbd class="zoom-pill">Ctrl + Y</kbd>
                    </div>
                    <div class="glass-panel" style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-md);">
                        <span style="font-size: 0.82rem; color: var(--text);"><?= e(t('key_copy')) ?></span>
                        <kbd class="zoom-pill">Ctrl + C</kbd>
                    </div>
                    <div class="glass-panel" style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-md);">
                        <span style="font-size: 0.82rem; color: var(--text);"><?= e(t('key_paste')) ?></span>
                        <kbd class="zoom-pill">Ctrl + V</kbd>
                    </div>
                    <div class="glass-panel" style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-md);">
                        <span style="font-size: 0.82rem; color: var(--text);"><?= e(t('key_duplicate')) ?></span>
                        <kbd class="zoom-pill">Ctrl + D</kbd>
                    </div>
                    <div class="glass-panel" style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-md);">
                        <span style="font-size: 0.82rem; color: var(--text);"><?= e(t('key_delete')) ?></span>
                        <kbd class="zoom-pill">Del / Backspace</kbd>
                    </div>
                    <div class="glass-panel" style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-md);">
                        <span style="font-size: 0.82rem; color: var(--text);"><?= e(t('key_nudge')) ?></span>
                        <kbd class="zoom-pill">Arrows / Shift</kbd>
                    </div>
                    <div class="glass-panel" style="padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-md);">
                        <span style="font-size: 0.82rem; color: var(--text);"><?= e(t('key_save')) ?></span>
                        <kbd class="zoom-pill">Enter in name</kbd>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dedicated Phase 6 AI Poster Modal -->
    <div class="modal-overlay" id="ai-poster-modal" style="display: none;">
        <div class="modal-content glass-refract" style="max-width: 680px; width: 92%;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding: 1rem 1.25rem;">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <h3 style="margin: 0; font-size: 1.2rem; color: var(--text); display: flex; align-items: center; gap: 0.4rem;">
                        <span>✨</span> <span><?= e(t('ai_poster')) ?></span>
                    </h3>
                    <span class="poster-quota-badge stat-pill status-info" style="font-size: 0.74rem;"></span>
                </div>
                <button type="button" class="btn-icon" id="btn-close-poster-modal">✕</button>
            </div>

            <!-- Mode Sub-Tabs -->
            <div class="poster-modal-tabs" style="display: flex; border-bottom: 1px solid var(--border-subtle); background: var(--glass-bg-subtle);">
                <button type="button" class="poster-tab-btn active" id="tab-poster-import" style="flex: 1; padding: 0.8rem; border: none; background: transparent; color: var(--text); font-weight: 600; font-size: 0.88rem; cursor: pointer; border-bottom: 2px solid #00f0ff; transition: all 0.2s;">
                    🖼️ <?= e(t('mode_import_tab')) ?>
                </button>
                <button type="button" class="poster-tab-btn" id="tab-poster-gen" style="flex: 1; padding: 0.8rem; border: none; background: transparent; color: var(--text-muted); font-weight: 600; font-size: 0.88rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.2s;">
                    🚀 <?= e(t('mode_text_poster_tab')) ?>
                </button>
            </div>

            <div class="modal-body" style="padding: 1.25rem;">
                <!-- Panel 1: Import & Make Editable -->
                <div id="panel-poster-import">
                    <p style="font-size: 0.84rem; color: var(--text-muted); margin-bottom: 1rem;">
                        <?= e(t('mode_import_desc')) ?>
                    </p>

                    <!-- Dropzone -->
                    <div id="poster-dropzone" class="poster-dropzone" style="border: 2px dashed rgba(0, 240, 255, 0.4); border-radius: var(--radius-lg); padding: 1.8rem 1rem; text-align: center; background: rgba(0, 240, 255, 0.03); cursor: pointer; transition: all 0.2s; margin-bottom: 1rem;">
                        <input type="file" id="poster-file-input" accept="image/jpeg,image/png,image/webp" style="display: none;">
                        <div class="dropzone-content">
                            <div style="font-size: 2.2rem; margin-bottom: 0.4rem;">📤</div>
                            <div style="font-weight: 600; color: var(--text); font-size: 0.95rem; margin-bottom: 0.2rem;"><?= e(t('poster_upload_label')) ?></div>
                            <div style="font-size: 0.78rem; color: var(--text-muted);"><?= e(t('poster_dropzone_hint')) ?></div>
                        </div>
                        <div id="poster-file-preview" style="display: none; align-items: center; justify-content: center; gap: 0.8rem;">
                            <img id="poster-preview-img" style="max-height: 120px; max-width: 160px; border-radius: 8px; border: 1px solid var(--border);" alt="Preview">
                            <div style="text-align: start;">
                                <div id="poster-file-name" style="font-weight: 600; font-size: 0.88rem; color: var(--text); margin-bottom: 0.3rem;"></div>
                                <button type="button" id="btn-remove-poster-file" class="btn-sm" style="color: var(--danger); font-size: 0.76rem;">✕ Remove</button>
                            </div>
                        </div>
                    </div>

                    <!-- Mode 1 Options -->
                    <div class="glass-panel" style="padding: 0.8rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.84rem; color: var(--text); cursor: pointer; margin-bottom: 0.5rem;">
                            <input type="radio" name="clean_plate_mode" value="clean" checked style="cursor: pointer;">
                            <span><?= e(t('clean_plate_option')) ?></span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.84rem; color: var(--text-muted); cursor: pointer;">
                            <input type="radio" name="clean_plate_mode" id="opt-keep-original" value="keep" style="cursor: pointer;">
                            <span><?= e(t('keep_original_option')) ?></span>
                        </label>
                    </div>

                    <!-- Progress tracker for Mode 1 -->
                    <div id="poster-import-progress" class="poster-steps-progress" style="display: none; margin-bottom: 1rem;">
                        <div class="step-item pending" data-step="1">
                            <span class="step-badge">1</span>
                            <span class="step-text"><?= e(t('step_import_1')) ?></span>
                            <span class="step-time"></span>
                        </div>
                        <div class="step-item pending" data-step="2">
                            <span class="step-badge">2</span>
                            <span class="step-text"><?= e(t('step_import_2')) ?></span>
                            <span class="step-time"></span>
                        </div>
                        <div class="step-item pending" data-step="3">
                            <span class="step-badge">3</span>
                            <span class="step-text"><?= e(t('step_import_3')) ?></span>
                            <span class="step-time"></span>
                        </div>
                    </div>

                    <button type="button" id="btn-run-poster-import" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 0.95rem; font-weight: 700; background: linear-gradient(135deg, #00f0ff, #0077ff); color: #020b14; border: none; border-radius: var(--radius-pill); cursor: pointer;">
                        ✨ <?= e(t('btn_import_poster')) ?>
                    </button>
                </div>

                <!-- Panel 2: Text to Poster -->
                <div id="panel-poster-gen" style="display: none;">
                    <p style="font-size: 0.84rem; color: var(--text-muted); margin-bottom: 1rem;">
                        <?= e(t('mode_text_poster_desc')) ?>
                    </p>

                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.84rem; font-weight: 600; color: var(--text); margin-bottom: 0.4rem;"><?= e(t('poster_prompt_label')) ?></label>
                        <textarea id="poster-prompt-input" rows="3" placeholder="<?= e(t('poster_prompt_placeholder')) ?>" style="width: 100%; padding: 0.7rem; border-radius: var(--radius-md); border: 1px solid var(--border); background: var(--glass-bg-subtle); color: var(--text); font-family: inherit; font-size: 0.88rem; box-sizing: border-box; resize: vertical;"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem;"><?= e(t('style_preset_label')) ?></label>
                            <select id="poster-style-preset" style="width: 100%; padding: 0.55rem; font-size: 0.84rem;">
                                <option value="cyber_tech"><?= e(t('style_cyber_tech')) ?></option>
                                <option value="luxury_gold"><?= e(t('style_luxury_gold')) ?></option>
                                <option value="modern_minimal"><?= e(t('style_modern_minimal')) ?></option>
                                <option value="event_promo"><?= e(t('style_event_promo')) ?></option>
                                <option value="calligraphy"><?= e(t('style_calligraphy')) ?></option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem;"><?= e(t('canvas_dimensions')) ?></label>
                            <select id="poster-dimensions-preset" style="width: 100%; padding: 0.55rem; font-size: 0.84rem;">
                                <option value="square">1080 × 1080 (Square)</option>
                                <option value="banner" selected>1024 × 558 (Reference Banner)</option>
                                <option value="story">1080 × 1920 (Story / Reel)</option>
                                <option value="landscape">1200 × 630 (Landscape)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Progress tracker for Mode 2 -->
                    <div id="poster-gen-progress" class="poster-steps-progress" style="display: none; margin-bottom: 1rem;">
                        <div class="step-item pending" data-step="1">
                            <span class="step-badge">1</span>
                            <span class="step-text"><?= e(t('step_gen_1')) ?></span>
                            <span class="step-time"></span>
                        </div>
                        <div class="step-item pending" data-step="2">
                            <span class="step-badge">2</span>
                            <span class="step-text"><?= e(t('step_gen_2')) ?></span>
                            <span class="step-time"></span>
                        </div>
                        <div class="step-item pending" data-step="3">
                            <span class="step-badge">3</span>
                            <span class="step-text"><?= e(t('step_gen_3')) ?></span>
                            <span class="step-time"></span>
                        </div>
                    </div>

                    <button type="button" id="btn-run-poster-generate" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 0.95rem; font-weight: 700; background: linear-gradient(135deg, #00f0ff, #0077ff); color: #020b14; border: none; border-radius: var(--radius-pill); cursor: pointer;">
                        🚀 <?= e(t('btn_generate_poster')) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ES6 Master Application Controller -->
    <script type="module" src="assets/js/app.js"></script>
</body>
</html>
