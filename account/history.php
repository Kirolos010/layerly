<?php
/**
 * AI Design Studio - Generation History Page
 */

define('APP_INIT', true);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

send_security_headers();
init_secure_session();

$csrfToken = get_csrf_token();
$currentLang = get_current_lang();
$isArabic = ($currentLang === 'ar');
$dir = $isArabic ? 'rtl' : 'ltr';

$isLoggedIn = is_logged_in();
$currentUser = get_auth_user();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$type = isset($_GET['type']) ? trim((string)$_GET['type']) : 'all';

$historyData = get_history_list($page, 15, $type);
$items = $historyData['items'];
$pagination = $historyData['pagination'];
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>" dir="<?= e($dir) ?>" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= e(url('/')) ?>/">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e(t('history_title')) ?> - <?= e(t('app_name')) ?></title>

    <!-- Multilingual Alternate hreflang -->
    <link rel="alternate" hreflang="ar" href="<?= e(url('history?lang=ar')) ?>">
    <link rel="alternate" hreflang="en" href="<?= e(url('history?lang=en')) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= e(url('history')) ?>">

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
        <!-- Top Navigation Header -->
        <header class="app-header">
            <div>
                <a href="<?= e(url('/')) ?>" style="text-decoration: none;">
                    <h1 class="app-title"><?= e(t('app_name')) ?></h1>
                </a>
                <p class="app-subtitle"><?= e(t('history_subtitle')) ?></p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-sm theme-toggle-btn" id="theme-toggle" title="<?= e(t('theme_toggle')) ?>">
                    <span class="theme-icon">☀️</span> <span class="theme-text"><?= e(t('theme_light')) ?></span>
                </button>
                <a href="<?= e(url('history?type=' . urlencode($type) . '&page=' . $page . '&lang=' . ($isArabic ? 'en' : 'ar'))) ?>" class="btn-sm">🌐 <?= e(t('switch_language')) ?></a>
                <a href="<?= e(url('projects')) ?>" class="btn-sm">📁 <?= e(t('my_designs')) ?></a>
                <a href="<?= e(url('/')) ?>" class="btn-sm"><?= e(t('back_to_studio')) ?></a>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= e(url('profile')) ?>" class="user-badge">👤 <?= e($currentUser['name']) ?></a>
                    <a href="<?= e(url('logout')) ?>" class="btn-sm" style="color: var(--danger);"><?= e(t('logout')) ?></a>
                <?php else: ?>
                    <a href="<?= e(url('login')) ?>" class="btn-sm"><?= e(t('login')) ?></a>
                    <a href="<?= e(url('register')) ?>" class="btn-sm btn-primary" style="color:#fff;"><?= e(t('register')) ?></a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Guest Warning Banner -->
        <?php if (!$isLoggedIn): ?>
            <div class="guest-banner">
                <div class="guest-banner-text">
                    ⚠️ <strong><?= e(t('guest_mode')) ?>:</strong> <?= e(t('guest_mode_banner')) ?>
                    <span style="display: block; font-size: 0.82rem; margin-top: 2px; color: var(--text-muted);">
                        <?= e(t('guest_banner_action')) ?>
                    </span>
                </div>
                <div class="guest-banner-actions">
                    <a href="<?= e(url('register')) ?>" class="btn-sm btn-primary" style="color: #fff;"><?= e(t('register')) ?></a>
                    <a href="<?= e(url('login')) ?>" class="btn-sm"><?= e(t('login')) ?></a>
                </div>
            </div>
        <?php endif; ?>

        <!-- History Controls & Filters -->
        <div class="history-header">
            <div class="filter-pills">
                <a href="<?= e(url('history?type=all')) ?>" class="filter-pill <?= $type === 'all' ? 'active' : '' ?>"><?= e(t('filter_all')) ?></a>
                <a href="<?= e(url('history?type=generate')) ?>" class="filter-pill <?= $type === 'generate' ? 'active' : '' ?>"><?= e(t('filter_generate')) ?></a>
                <a href="<?= e(url('history?type=enhance')) ?>" class="filter-pill <?= $type === 'enhance' ? 'active' : '' ?>"><?= e(t('filter_enhance')) ?></a>
                <a href="<?= e(url('history?type=edit')) ?>" class="filter-pill <?= $type === 'edit' ? 'active' : '' ?>"><?= e(t('filter_edit')) ?></a>
            </div>

            <?php if (!empty($items)): ?>
                <button type="button" id="btn-clear-all" class="btn-sm" style="color: var(--danger); border-color: rgba(239, 68, 68, 0.3);">
                    🗑️ <?= e(t('clear_all')) ?>
                </button>
            <?php endif; ?>
        </div>

        <!-- History Grid -->
        <?php if (empty($items)): ?>
            <div class="empty-state">
                <h3><?= e(t('empty_history')) ?></h3>
                <p style="margin-top: 0.5rem;"><?= e(t('app_tagline')) ?></p>
                <a href="<?= e(url('/')) ?>" class="btn btn-primary" style="margin-top: 1.25rem;">
                    🚀 <?= e(t('btn_generate')) ?>
                </a>
            </div>
        <?php else: ?>
            <div class="history-grid">
                <?php foreach ($items as $item): 
                    $typeClass = 'type-' . $item['type'];
                    $typeLabel = t('filter_' . $item['type']);
                    $dateStr = date('M j, Y - H:i', strtotime($item['created_at']));
                ?>
                    <div class="history-card" id="card-<?= (int)$item['id'] ?>">
                        <div>
                            <div class="card-top">
                                <span class="type-tag <?= $typeClass ?>"><?= e($typeLabel) ?></span>
                                <span class="card-date"><?= e($dateStr) ?></span>
                            </div>
                            <div class="card-prompt" title="<?= e($item['prompt']) ?>">
                                <?= e($item['prompt']) ?>
                            </div>
                        </div>

                        <div class="card-actions">
                            <?php if ($item['type'] === 'generate' || $item['type'] === 'edit'): ?>
                                <a href="<?= e(url('?load_generation=' . (int)$item['id'])) ?>" class="btn-sm" style="background: var(--primary); color: #fff; border-color: var(--primary);">
                                    🎨 <?= e(t('action_open_editor')) ?>
                                </a>
                            <?php endif; ?>

                            <a href="<?= e(url('?reuse_prompt=' . urlencode($item['prompt']))) ?>" class="btn-sm">
                                🔄 <?= e(t('action_reuse_prompt')) ?>
                            </a>

                            <button type="button" class="btn-sm btn-view-json" data-id="<?= (int)$item['id'] ?>">
                                📄 <?= e(t('action_view_json')) ?>
                            </button>

                            <button type="button" class="btn-sm btn-delete-item" data-id="<?= (int)$item['id'] ?>" style="color: var(--danger); margin-inline-start: auto;">
                                ✕
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="<?= e(url('history?type=' . urlencode($type) . '&page=' . ($page - 1))) ?>" class="btn-sm"><?= t('page_prev') ?></a>
                    <?php endif; ?>

                    <span style="color: var(--text-muted); font-size: 0.9rem;">
                        <?= e(t('showing_page', null, ['page' => $pagination['current_page'], 'total' => $pagination['total_pages']])) ?>
                    </span>

                    <?php if ($pagination['has_next']): ?>
                        <a href="<?= e(url('history?type=' . urlencode($type) . '&page=' . ($page + 1))) ?>" class="btn-sm"><?= t('page_next') ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- JSON Inspection Modal -->
    <div id="json-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title" style="font-size: 1.1rem; color: #93c5fd;">JSON Details</h3>
                <button type="button" id="modal-close" class="btn-sm">✕</button>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: flex-end; margin-bottom: 0.5rem;">
                    <button type="button" id="btn-modal-copy" class="btn-sm">📋 <?= e(t('copy_json')) ?></button>
                </div>
                <pre id="modal-json" style="background: #090d16; padding: 1rem; border-radius: 8px; border: 1px solid var(--border); max-height: 450px; overflow: auto; color: #cbd5e1; direction: ltr; font-size: 0.82rem;"></pre>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Delete single item
        document.querySelectorAll('.btn-delete-item').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                if (!confirm("<?= e(t('confirm_delete_item')) ?>")) return;

                try {
                    const res = await fetch('api/history.php?action=delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ id: id, csrf_token: csrfToken })
                    });
                    const data = await res.json();
                    if (data.success) {
                        const card = document.getElementById(`card-${id}`);
                        if (card) card.remove();
                    } else {
                        alert(data.error || 'Failed to delete');
                    }
                } catch (e) {
                    alert('Network error: ' + e.message);
                }
            });
        });

        // Clear all history
        const btnClearAll = document.getElementById('btn-clear-all');
        if (btnClearAll) {
            btnClearAll.addEventListener('click', async () => {
                if (!confirm("<?= e(t('confirm_clear_all')) ?>")) return;

                try {
                    const res = await fetch('api/history.php?action=clear', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ csrf_token: csrfToken, type: '<?= e($type) ?>' })
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to clear');
                    }
                } catch (e) {
                    alert('Network error: ' + e.message);
                }
            });
        }

        // View JSON Modal
        const modal = document.getElementById('json-modal');
        const modalClose = document.getElementById('modal-close');
        const modalJson = document.getElementById('modal-json');
        const modalCopy = document.getElementById('btn-modal-copy');

        document.querySelectorAll('.btn-view-json').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                try {
                    const res = await fetch(`api/history.php?action=get&id=${id}`);
                    const data = await res.json();
                    if (data.success) {
                        modalJson.textContent = JSON.stringify(data.data.result, null, 2);
                        modal.style.display = 'flex';
                    } else {
                        alert(data.error || 'Failed to load details');
                    }
                } catch (e) {
                    alert('Error loading JSON: ' + e.message);
                }
            });
        });

        modalClose.addEventListener('click', () => { modal.style.display = 'none'; });
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.style.display = 'none';
        });

        modalCopy.addEventListener('click', async () => {
            await navigator.clipboard.writeText(modalJson.textContent);
            const orig = modalCopy.textContent;
            modalCopy.textContent = '✓ Copied!';
            setTimeout(() => { modalCopy.textContent = orig; }, 2000);
        });
    </script>

    <!-- Theme Manager Script -->
    <script type="module">
        import { ThemeManager } from './assets/js/editor/theme-manager.js';
        ThemeManager.init();
    </script>
</body>
</html>
