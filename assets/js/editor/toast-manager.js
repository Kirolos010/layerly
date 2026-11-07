/**
 * AI Design Studio - Liquid Glass Toast Notification System
 * Displays floating translucent glass pills with spring animations and auto-dismiss.
 */

export class ToastManager {
    constructor() {
        this.container = this.getOrCreateContainer();
        this.init();
    }

    getOrCreateContainer() {
        let el = document.getElementById('glass-toast-container');
        if (!el) {
            el = document.createElement('div');
            el.id = 'glass-toast-container';
            el.className = 'glass-toast-container';
            document.body.appendChild(el);
        }
        return el;
    }

    init() {
        window.addEventListener('toast:show', (e) => {
            if (e.detail) {
                this.show(e.detail.message, e.detail.type || 'info', e.detail.duration || 3500);
            }
        });
    }

    show(message, type = 'info', duration = 3500) {
        if (!message) return;

        const toast = document.createElement('div');
        toast.className = `glass-toast toast-${type}`;

        const icon = type === 'success' ? '✓' : (type === 'error' ? '⚠️' : 'ℹ️');
        toast.innerHTML = `
            <span style="font-weight: 700; color: ${type === 'success' ? 'var(--success)' : (type === 'error' ? 'var(--danger)' : 'var(--primary)')};">${icon}</span>
            <span>${this.escapeHTML(message)}</span>
        `;

        toast.addEventListener('click', () => {
            this.dismiss(toast);
        });

        this.container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                this.dismiss(toast);
            }, duration);
        }

        return toast;
    }

    dismiss(toast) {
        if (!toast || !toast.parentNode) return;
        toast.classList.add('hide');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 250);
    }

    escapeHTML(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

