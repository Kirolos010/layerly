/**
 * AI Design Studio - Layers Panel Component
 * Displays objects in z-order, handles selection, visibility, locking, reordering, and deletion.
 */

export class LayersPanel {
    constructor(containerId, fabricCanvas, historyManager) {
        this.container = document.getElementById(containerId);
        this.canvas = fabricCanvas;
        this.history = historyManager;

        if (!this.container || !this.canvas) return;

        this.setupListeners();
        this.render();
    }

    setupListeners() {
        const updateLayers = () => this.render();

        this.canvas.on('object:added', updateLayers);
        this.canvas.on('object:removed', updateLayers);
        this.canvas.on('object:modified', updateLayers);
        this.canvas.on('selection:created', () => this.highlightSelected());
        this.canvas.on('selection:updated', () => this.highlightSelected());
        this.canvas.on('selection:cleared', () => this.highlightSelected());

        window.addEventListener('editor:design-loaded', updateLayers);
    }

    /**
     * Get human-friendly icon & label for an object
     */
    getObjectMeta(obj) {
        let icon = '📦';
        let label = obj.name || 'Object';

        if (obj.type === 'textbox' || obj.type === 'text' || obj.type === 'i-text') {
            icon = '🔤';
            if (!obj.name) {
                const text = (obj.text || '').trim();
                label = text ? `"${text.substring(0, 16)}${text.length > 16 ? '...' : ''}"` : 'Text';
            }
        } else if (obj.type === 'rect') {
            icon = '⬛';
            label = obj.name || 'Rectangle';
        } else if (obj.type === 'circle') {
            icon = '⚪';
            label = obj.name || 'Circle';
        } else if (obj.type === 'triangle') {
            icon = '🔺';
            label = obj.name || 'Triangle';
        } else if (obj.type === 'line') {
            icon = '➖';
            label = obj.name || 'Line';
        } else if (obj.type === 'polygon') {
            icon = '⭐';
            label = obj.name || 'Star / Polygon';
        } else if (obj.type === 'path') {
            icon = '📐';
            label = obj.name || 'Vector Path';
        } else if (obj.type === 'image') {
            icon = obj.isBackground ? '🎨' : '🖼️';
            label = obj.name || (obj.isBackground ? (window.i18n ? window.i18n('ai_background_layer', 'AI Background') : 'AI Background') : 'Image');
        } else if (obj.type === 'group') {
            icon = '📁';
            label = obj.name || 'Group';
        }

        return { icon, label };
    }

    render() {
        if (!this.container) return;

        const objects = this.canvas.getObjects().slice();
        // Topmost object should appear first in the layers list
        objects.reverse();

        if (objects.length === 0) {
            this.container.innerHTML = `
                <div class="empty-layers">
                    <span style="font-size: 1.5rem; opacity: 0.5;">📄</span>
                    <p style="margin: 6px 0 0; font-size: 0.85rem; color: var(--text-muted);">No elements yet</p>
                </div>
            `;
            return;
        }

        const activeObj = this.canvas.getActiveObject();
        this.container.innerHTML = '';

        const list = document.createElement('div');
        list.className = 'layers-list';

        objects.forEach((obj, reversedIndex) => {
            const actualIndex = objects.length - 1 - reversedIndex;
            const isSelected = activeObj === obj || (activeObj && activeObj.type === 'activeSelection' && activeObj.contains(obj));
            const isVisible = obj.visible !== false;
            const isLocked = Boolean(obj.lockMovementX);
            const { icon, label } = this.getObjectMeta(obj);

            const item = document.createElement('div');
            item.className = `layer-item ${isSelected ? 'active' : ''} ${!isVisible ? 'hidden-layer' : ''}`;
            item.dataset.index = actualIndex;

            item.innerHTML = `
                <div class="layer-info" title="${label}">
                    <span class="layer-icon">${icon}</span>
                    <span class="layer-label">${this.escapeHtml(label)}</span>
                </div>
                <div class="layer-actions">
                    <button type="button" class="layer-btn btn-up" title="Move Up" ${reversedIndex === 0 ? 'disabled' : ''}>▲</button>
                    <button type="button" class="layer-btn btn-down" title="Move Down" ${reversedIndex === objects.length - 1 ? 'disabled' : ''}>▼</button>
                    <button type="button" class="layer-btn btn-vis" title="${isVisible ? 'Hide' : 'Show'}">${isVisible ? '👁️' : '🚫'}</button>
                    <button type="button" class="layer-btn btn-lock" title="${isLocked ? 'Unlock' : 'Lock'}">${isLocked ? '🔒' : '🔓'}</button>
                    <button type="button" class="layer-btn btn-del" title="Delete">🗑️</button>
                </div>
            `;

            // Row click -> Select object
            item.querySelector('.layer-info').addEventListener('click', (e) => {
                e.stopPropagation();
                this.canvas.discardActiveObject();
                this.canvas.setActiveObject(obj);
                this.canvas.requestRenderAll();
                this.highlightSelected();
            });

            // Double click label -> rename
            const labelEl = item.querySelector('.layer-label');
            labelEl.addEventListener('dblclick', (e) => {
                e.stopPropagation();
                const newName = prompt('Rename layer:', obj.name || label);
                if (newName && newName.trim()) {
                    obj.name = newName.trim();
                    this.render();
                    if (this.history) this.history.saveState();
                }
            });

            // Move Up (towards front)
            item.querySelector('.btn-up').addEventListener('click', (e) => {
                e.stopPropagation();
                obj.bringForward();
                this.canvas.requestRenderAll();
                this.render();
                if (this.history) this.history.saveState();
            });

            // Move Down (towards back)
            item.querySelector('.btn-down').addEventListener('click', (e) => {
                e.stopPropagation();
                obj.sendBackwards();
                this.canvas.requestRenderAll();
                this.render();
                if (this.history) this.history.saveState();
            });

            // Toggle Visibility
            item.querySelector('.btn-vis').addEventListener('click', (e) => {
                e.stopPropagation();
                const newVis = !obj.visible;
                obj.set('visible', newVis);
                if (!newVis && this.canvas.getActiveObject() === obj) {
                    this.canvas.discardActiveObject();
                }
                this.canvas.requestRenderAll();
                this.render();
                if (this.history) this.history.saveState();
            });

            // Toggle Lock
            item.querySelector('.btn-lock').addEventListener('click', (e) => {
                e.stopPropagation();
                const lock = !obj.lockMovementX;
                obj.set({
                    lockMovementX: lock,
                    lockMovementY: lock,
                    lockRotation: lock,
                    lockScalingX: lock,
                    lockScalingY: lock,
                    hasControls: !lock
                });
                this.canvas.requestRenderAll();
                this.render();
                if (this.history) this.history.saveState();
            });

            // Delete
            item.querySelector('.btn-del').addEventListener('click', (e) => {
                e.stopPropagation();
                this.canvas.remove(obj);
                this.canvas.requestRenderAll();
                this.render();
                if (this.history) this.history.saveState();
            });

            list.appendChild(item);
        });

        this.container.appendChild(list);
    }

    highlightSelected() {
        if (!this.container) return;
        const activeObj = this.canvas.getActiveObject();
        const items = this.container.querySelectorAll('.layer-item');

        items.forEach(item => {
            const index = parseInt(item.dataset.index, 10);
            const obj = this.canvas.getObjects()[index];
            const isSelected = activeObj === obj || (activeObj && activeObj.type === 'activeSelection' && activeObj.contains(obj));
            item.classList.toggle('active', Boolean(isSelected));
        });
    }

    escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}

