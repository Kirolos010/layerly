<?php
/**
 * AI Design Studio - My Designs Page (Phase 4 Liquid Glass)
 * 
 * Grid of saved projects for authenticated users and anonymous guests.
 * Actions: Open in editor, duplicate, rename, delete.
 */

define('APP_INIT', true);

require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

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

$ownerToken = get_or_create_guest_token();

// Fetch projects
$db = get_db();
if ($isLoggedIn && $currentUser) {
    $stmt = $db->prepare("
        SELECT id, name, thumbnail, created_at, updated_at 
        FROM projects 
        WHERE user_id = ? OR owner_token = ?
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$currentUser['id'], $ownerToken]);
} else {
    $stmt = $db->prepare("
        SELECT id, name, thumbnail, created_at, updated_at 
        FROM projects 
        WHERE owner_token = ? AND user_id IS NULL
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$ownerToken]);
}

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$projectCount = count($projects);
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    
    <!-- SEO & Metadata -->
    <title><?= e(t('my_designs')) ?> - <?= e(t('app_name')) ?></title>
    <meta name="description" content="<?= e(t('meta_desc')) ?>">
    
    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e(t('my_designs')) ?> - <?= e(t('app_name')) ?>">
    <meta property="og:description" content="<?= e(t('meta_desc')) ?>">
    <meta property="og:locale" content="<?= $isArabic ? 'ar_AR' : 'en_US' ?>">
    <meta property="og:site_name" content="AI Design Studio">

    <!-- Multilingual Alternate hreflang -->
    <link rel="alternate" hreflang="ar" href="projects.php?lang=ar">
    <link rel="alternate" hreflang="en" href="projects.php?lang=en">
    <link rel="alternate" hreflang="x-default" href="projects.php">

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
                <div class="app-logo-badge">📁</div>
                <div>
                    <a href="index.php" style="text-decoration: none;">
                        <h1 class="app-title"><?= e(t('app_name')) ?></h1>
                    </a>
                    <p class="app-subtitle"><?= e(t('my_designs')) ?> (<?= $projectCount ?>)</p>
                </div>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-sm theme-toggle-btn" id="theme-toggle" title="<?= e(t('theme_toggle')) ?>">
                    <span class="theme-icon">☀️</span> <span class="theme-text"><?= e(t('theme_light')) ?></span>
                </button>
                <a href="<?= e($switchLangUrl) ?>" class="btn-sm" title="<?= e(t('switch_language')) ?>">🌐 <?= e(t('switch_language')) ?></a>
                <a href="templates.php" class="btn-sm">🎨 <?= e(t('templates')) ?></a>
                <a href="history.php" class="btn-sm">📜 <?= e(t('history')) ?></a>
                <a href="about.php" class="btn-sm">ℹ️ <?= e(t('about')) ?></a>
                
                <?php if ($isLoggedIn): ?>
                    <a href="profile.php" class="user-badge">👤 <?= e($currentUser['name']) ?></a>
                    <a href="logout.php" class="btn-sm" style="color: var(--danger);"><?= e(t('logout')) ?></a>
                <?php else: ?>
                    <a href="login.php" class="btn-sm"><?= e(t('login')) ?></a>
                    <a href="register.php" class="btn-sm btn-primary"><?= e(t('register')) ?></a>
                <?php endif; ?>

                <a href="index.php" class="btn btn-sm btn-primary">+ <?= e(t('new_design')) ?></a>
            </div>
        </header>

        <!-- Guest Notice Banner -->
        <?php if (!$isLoggedIn && $projectCount > 0): ?>
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

        <!-- Search Bar -->
        <div class="templates-filter-bar">
            <input type="text" id="project-search-input" placeholder="<?= e(t('search_designs_placeholder')) ?>" style="max-width: 380px; padding: 0.6rem 0.95rem; font-size: 0.88rem;">
            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                <span id="showing-count" style="font-weight: 700; color: var(--text);"><?= $projectCount ?></span> <?= e(t('designs_found')) ?>
            </div>
        </div>

        <!-- Projects Grid -->
        <?php if ($projectCount === 0): ?>
            <div class="glass-panel" style="text-align: center; padding: 4rem 2rem;">
                <div style="font-size: 3.5rem; margin-bottom: 0.75rem;">📁</div>
                <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem; font-weight: 700;"><?= e(t('no_designs_yet')) ?></h3>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem;"><?= e(t('no_designs_desc')) ?></p>
                <a href="index.php" class="btn btn-primary" style="display: inline-flex; width: auto;">+ <?= e(t('create_first_design')) ?></a>
            </div>
        <?php else: ?>
            <div class="projects-grid" id="projects-grid">
                <?php foreach ($projects as $proj): ?>
                    <div class="project-card" data-id="<?= (int)$proj['id'] ?>" data-name="<?= e(strtolower($proj['name'])) ?>">
                        <div class="project-card-thumb">
                            <?php if (!empty($proj['thumbnail'])): ?>
                                <img src="<?= e($proj['thumbnail']) ?>" alt="<?= e($proj['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="project-thumb-placeholder">🎨</div>
                            <?php endif; ?>
                            <div class="project-thumb-overlay">
                                <a href="index.php?project_id=<?= (int)$proj['id'] ?>" class="btn btn-sm btn-primary">
                                    <?= e(t('open_editor')) ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="project-card-details">
                            <h4 class="project-name" title="<?= e($proj['name']) ?>"><?= e($proj['name']) ?></h4>
                            <div class="project-date" style="font-size: 0.78rem; color: var(--text-muted);">
                                <?= date('M j, Y - g:i A', strtotime($proj['updated_at'])) ?>
                            </div>
                        </div>

                        <div class="project-card-actions">
                            <button type="button" class="btn-sm btn-duplicate" data-id="<?= (int)$proj['id'] ?>" title="<?= e(t('duplicate')) ?>">
                                📋 <?= e(t('duplicate')) ?>
                            </button>
                            <button type="button" class="btn-sm btn-rename" data-id="<?= (int)$proj['id'] ?>" data-current="<?= e($proj['name']) ?>" title="<?= e(t('rename')) ?>">
                                ✏️ <?= e(t('rename')) ?>
                            </button>
                            <button type="button" class="btn-sm btn-delete" data-id="<?= (int)$proj['id'] ?>" style="color: var(--danger);" title="<?= e(t('action_delete')) ?>">
                                🗑️
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Theme Manager & Client Actions Script -->
    <script type="module">
        import { ThemeManager } from './assets/js/editor/theme-manager.js';
        import { ToastManager } from './assets/js/editor/toast-manager.js';

        new ThemeManager();
        const toast = new ToastManager();

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Search Filter
        const searchInput = document.getElementById('project-search-input');
        const cards = document.querySelectorAll('.project-card');
        const countSpan = document.getElementById('showing-count');

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const q = searchInput.value.toLowerCase().trim();
                let visible = 0;
                cards.forEach(card => {
                    const name = card.dataset.name || '';
                    if (!q || name.includes(q)) {
                        card.style.display = 'flex';
                        visible++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                if (countSpan) countSpan.textContent = visible;
            });
        }

        // Duplicate Action
        document.querySelectorAll('.btn-duplicate').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                btn.disabled = true;

                try {
                    const res = await fetch('api/projects.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ action: 'duplicate', id: parseInt(id, 10) })
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.error || 'Failed to duplicate project.');
                    toast.show('Project duplicated!', 'success');
                    setTimeout(() => window.location.reload(), 300);
                } catch (err) {
                    toast.show(err.message, 'error');
                    btn.disabled = false;
                }
            });
        });

        // Rename Action
        document.querySelectorAll('.btn-rename').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                const currentName = btn.dataset.current;
                const newName = prompt('Enter new project name:', currentName);

                if (!newName || !newName.trim() || newName.trim() === currentName) return;

                btn.disabled = true;
                try {
                    const res = await fetch('api/projects.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ action: 'rename', id: parseInt(id, 10), name: newName.trim() })
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.error || 'Failed to rename project.');
                    toast.show('Project renamed!', 'success');
                    setTimeout(() => window.location.reload(), 300);
                } catch (err) {
                    toast.show(err.message, 'error');
                    btn.disabled = false;
                }
            });
        });

        // Delete Action
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                if (!confirm('Are you sure you want to delete this design? This action cannot be undone.')) return;

                btn.disabled = true;
                try {
                    const res = await fetch('api/projects.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ action: 'delete', id: parseInt(id, 10) })
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.error || 'Failed to delete project.');
                    
                    const card = btn.closest('.project-card');
                    if (card) card.remove();
                    
                    const remaining = document.querySelectorAll('.project-card').length;
                    if (countSpan) countSpan.textContent = remaining;
                    toast.show('Project deleted.', 'info');
                    if (remaining === 0) window.location.reload();
                } catch (err) {
                    toast.show(err.message, 'error');
                    btn.disabled = false;
                }
            });
        });
    </script>
</body>
</html>
