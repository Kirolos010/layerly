/**
 * AI Design Studio - Theme Manager (Liquid Glass Light & Dark Modes)
 * Supports persistent storage (localStorage + cookie) and system preference auto-detection.
 */

export class ThemeManager {
    constructor() {
        this.STORAGE_KEY = 'design_studio_theme';
        this.currentTheme = this.resolveInitialTheme();
        this.init();
    }

    resolveInitialTheme() {
        // 1. Check documentElement attribute already set by pre-render script
        const existing = document.documentElement.getAttribute('data-theme');
        if (existing === 'dark' || existing === 'light') {
            return existing;
        }

        // 2. Check localStorage
        const stored = localStorage.getItem(this.STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') {
            return stored;
        }

        // 3. Check Cookie
        const match = document.cookie.match(/(?:^|; )design_studio_theme=([^;]*)/);
        if (match && (match[1] === 'dark' || match[1] === 'light')) {
            return match[1];
        }

        // 4. Check System Preference (Default: dark)
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            return 'light';
        }

        return 'dark';
    }

    init() {
        this.applyTheme(this.currentTheme, false);
        this.bindToggleButton();
        this.listenSystemChanges();
    }

    applyTheme(theme, save = true) {
        this.currentTheme = theme;
        document.documentElement.setAttribute('data-theme', theme);

        if (save) {
            try {
                localStorage.setItem(this.STORAGE_KEY, theme);
                document.cookie = `design_studio_theme=${theme}; path=/; max-age=31536000; SameSite=Lax`;
            } catch (e) {
                // Ignore storage errors in restrictive environments
            }
        }

        this.updateToggleUi();
    }

    toggle() {
        const next = this.currentTheme === 'dark' ? 'light' : 'dark';
        this.applyTheme(next, true);
        return next;
    }

    bindToggleButton() {
        const btns = document.querySelectorAll('#theme-toggle, .theme-toggle-btn');
        btns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggle();
            });
        });
        this.updateToggleUi();
    }

    updateToggleUi() {
        const btns = document.querySelectorAll('#theme-toggle, .theme-toggle-btn');
        const isDark = this.currentTheme === 'dark';
        const icon = isDark ? '☀️' : '🌙';
        const docLang = document.documentElement.lang || 'ar';
        const label = isDark 
            ? (docLang === 'ar' ? 'الوضع الفاتح' : 'Light Mode')
            : (docLang === 'ar' ? 'الوضع الداكن' : 'Dark Mode');

        btns.forEach(btn => {
            btn.setAttribute('title', label);
            btn.setAttribute('aria-label', label);
            const iconSpan = btn.querySelector('.theme-icon') || btn;
            if (iconSpan === btn) {
                btn.textContent = `${icon} ${label}`;
            } else {
                iconSpan.textContent = icon;
                const textSpan = btn.querySelector('.theme-text');
                if (textSpan) textSpan.textContent = label;
            }
        });
    }

    listenSystemChanges() {
        if (!window.matchMedia) return;
        try {
            const mq = window.matchMedia('(prefers-color-scheme: dark)');
            mq.addEventListener('change', (e) => {
                // Only adapt if user hasn't explicitly chosen a theme
                if (!localStorage.getItem(this.STORAGE_KEY)) {
                    this.applyTheme(e.matches ? 'dark' : 'light', false);
                }
            });
        } catch (e) {
            // Older browser fallback
        }
    }
}

