<?php
/**
 * AI Design Studio - Templates Gallery Page (Phase 4 Liquid Glass)
 * 
 * Browse built-in starter templates across categories and languages.
 * Selecting any template loads it directly into the Fabric.js editor.
 */

define('APP_INIT', true);

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

$isLoggedIn = is_logged_in();
$currentUser = get_auth_user();

// Load templates from disk
$templatesDir = __DIR__ . '/templates';
$files = glob($templatesDir . '/*.json') ?: [];
$templates = [];

foreach ($files as $file) {
    $content = @file_get_contents($file);
    if (!$content) continue;

    $data = json_decode($content, true);
    if (!$data || empty($data['canvas'])) continue;

    $templates[] = [
        'id'          => $data['id'] ?? basename($file, '.json'),
        'name_ar'     => $data['name_ar'] ?? 'قالب تصميم',
        'name_en'     => $data['name_en'] ?? 'Design Template',
        'category'    => $data['category'] ?? 'social',
        'language'    => $data['language'] ?? 'en',
        'tags'        => $data['tags'] ?? [],
        'width'       => (int)($data['canvas']['width'] ?? 1080),
        'height'      => (int)($data['canvas']['height'] ?? 1080),
        'background'  => (string)($data['canvas']['background'] ?? '#ffffff'),
        'objects'     => $data['objects'] ?? []
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO & Metadata -->
    <title><?= e(t('templates')) ?> - <?= e(t('app_name')) ?></title>
    <meta name="description" content="<?= e(t('templates_gallery_subtitle')) ?>">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e(t('templates')) ?> - <?= e(t('app_name')) ?>">
    <meta property="og:description" content="<?= e(t('templates_gallery_subtitle')) ?>">
    <meta property="og:locale" content="<?= $isArabic ? 'ar_AR' : 'en_US' ?>">
    <meta property="og:site_name" content="AI Design Studio">

    <!-- Multilingual Alternate hreflang -->
    <link rel="alternate" hreflang="ar" href="templates.php?lang=ar">
    <link rel="alternate" hreflang="en" href="templates.php?lang=en">
    <link rel="alternate" hreflang="x-default" href="templates.php">

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

    <div class="container" style="max-width: 1400px;">
        <!-- Header -->
        <header class="app-header">
            <div class="app-brand-group">
                <div class="app-logo-badge">🎨</div>
                <div>
                    <a href="index.php" style="text-decoration: none;">
                        <h1 class="app-title"><?= e(t('app_name')) ?></h1>
                    </a>
                    <p class="app-subtitle"><?= e(t('templates_gallery_subtitle')) ?> (<?= count($templates) ?>)</p>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-sm theme-toggle-btn" id="theme-toggle" title="<?= e(t('theme_toggle')) ?>">
                    <span class="theme-icon">☀️</span> <span class="theme-text"><?= e(t('theme_light')) ?></span>
                </button>
                <a href="<?= e($switchLangUrl) ?>" class="btn-sm" title="<?= e(t('switch_language')) ?>">🌐 <?= e(t('switch_language')) ?></a>
                <a href="projects.php" class="btn-sm">📁 <?= e(t('my_designs')) ?></a>
                <a href="history.php" class="btn-sm">📜 <?= e(t('history')) ?></a>
                <a href="about.php" class="btn-sm">ℹ️ <?= e(t('about')) ?></a>
                
                <?php if ($isLoggedIn): ?>
                    <a href="profile.php" class="user-badge">👤 <?= e($currentUser['name']) ?></a>
                    <a href="logout.php" class="btn-sm" style="color: var(--danger);"><?= e(t('logout')) ?></a>
                <?php else: ?>
                    <a href="login.php" class="btn-sm"><?= e(t('login')) ?></a>
                    <a href="register.php" class="btn-sm btn-primary"><?= e(t('register')) ?></a>
                <?php endif; ?>

                <a href="index.php" class="btn btn-sm btn-primary">+ <?= e(t('blank_canvas')) ?></a>
            </div>
        </header>

        <!-- Filters & Search Bar -->
        <div class="templates-filter-bar">
            <!-- Category Filter Pills -->
            <div class="filter-pills-group" id="category-filters">
                <button type="button" class="filter-pill active" data-category="all"><?= e(t('all_categories')) ?></button>
                <button type="button" class="filter-pill" data-category="social">Instagram / Social</button>
                <button type="button" class="filter-pill" data-category="story">Stories & Reels</button>
                <button type="button" class="filter-pill" data-category="banner">Banners & YouTube</button>
                <button type="button" class="filter-pill" data-category="invitation">Invitations & Cards</button>
                <button type="button" class="filter-pill" data-category="flyer">Flyers & Posters</button>
            </div>

            <!-- Language & Search -->
            <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <select id="template-lang-filter" style="padding: 0.5rem 0.75rem; font-size: 0.85rem; width: auto; border-radius: var(--radius-pill);">
                    <option value="all"><?= e(t('all_languages')) ?></option>
                    <option value="ar">العربية (Arabic)</option>
                    <option value="en">English</option>
                </select>

                <input type="text" id="template-search-input" placeholder="<?= e(t('search_templates')) ?>" style="max-width: 260px; padding: 0.5rem 0.85rem; font-size: 0.85rem; border-radius: var(--radius-pill);">
            </div>
        </div>

        <!-- Templates Grid -->
        <div class="templates-grid" id="templates-grid">
            <?php foreach ($templates as $tpl): ?>
                <?php 
                    $title = ($currentLang === 'ar') ? $tpl['name_ar'] : $tpl['name_en'];
                    $subTitle = ($currentLang === 'ar') ? $tpl['name_en'] : $tpl['name_ar'];
                ?>
                <div class="template-card" 
                     data-category="<?= e($tpl['category']) ?>" 
                     data-language="<?= e($tpl['language']) ?>"
                     data-title="<?= e(strtolower($title . ' ' . $subTitle)) ?>"
                     data-tags="<?= e(implode(' ', $tpl['tags'])) ?>">
                    
                    <div class="template-preview-wrapper" style="background: <?= e($tpl['background']) ?>;">
                        <!-- Mini Preview Layout Representation -->
                        <div class="template-mini-canvas">
                            <?php foreach (array_slice($tpl['objects'], 0, 5) as $obj): ?>
                                <?php if (($obj['type'] ?? '') === 'text'): ?>
                                    <div class="mini-text-line" style="color: <?= e($obj['fill'] ?? '#fff') ?>; font-size: 0.7rem; font-weight: <?= ($obj['fontWeight'] ?? '') === 'bold' ? '700' : '400' ?>; text-align: <?= e($obj['textAlign'] ?? 'center') ?>;">
                                        <?= e(mb_substr($obj['text'] ?? '', 0, 30)) ?>
                                    </div>
                                <?php elseif (($obj['type'] ?? '') === 'rect'): ?>
                                    <div class="mini-shape-box" style="background: <?= e($obj['fill'] !== 'transparent' ? $obj['fill'] : 'rgba(255,255,255,0.15)') ?>; border-color: <?= e($obj['stroke'] ?? 'transparent') ?>;"></div>
                                <?php elseif (($obj['type'] ?? '') === 'circle'): ?>
                                    <div class="mini-circle-box" style="background: <?= e($obj['fill'] ?? '#fbbf24') ?>;"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <div class="template-card-overlay">
                            <a href="index.php?template=<?= urlencode($tpl['id']) ?>" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                                ✨ <?= e(t('use_template')) ?>
                            </a>
                        </div>
                    </div>

                    <div class="template-info">
                        <div class="template-meta-row">
                            <span class="template-badge"><?= strtoupper(e($tpl['language'])) ?></span>
                            <span class="template-dim"><?= $tpl['width'] ?> × <?= $tpl['height'] ?></span>
                        </div>
                        <h4 class="template-title"><?= e($title) ?></h4>
                        <p class="template-subtitle"><?= e($subTitle) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Theme Manager & Filtering Script -->
    <script type="module">
        import { ThemeManager } from './assets/js/editor/theme-manager.js';
        new ThemeManager();

        const catPills = document.querySelectorAll('#category-filters .filter-pill');
        const langFilter = document.getElementById('template-lang-filter');
        const searchInput = document.getElementById('template-search-input');
        const cards = document.querySelectorAll('.template-card');

        let currentCat = 'all';
        let currentLang = 'all';
        let searchQuery = '';

        function filterTemplates() {
            cards.forEach(card => {
                const cat = card.dataset.category;
                const lang = card.dataset.language;
                const title = card.dataset.title;
                const tags = card.dataset.tags;

                const matchCat = (currentCat === 'all' || cat === currentCat);
                const matchLang = (currentLang === 'all' || lang === currentLang);
                const matchSearch = (!searchQuery || title.includes(searchQuery) || tags.includes(searchQuery));

                if (matchCat && matchLang && matchSearch) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        catPills.forEach(pill => {
            pill.addEventListener('click', () => {
                catPills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                currentCat = pill.dataset.category;
                filterTemplates();
            });
        });

        if (langFilter) {
            langFilter.addEventListener('change', () => {
                currentLang = langFilter.value;
                filterTemplates();
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                searchQuery = searchInput.value.toLowerCase().trim();
                filterTemplates();
            });
        }
    </script>
</body>
</html>
