/**
 * AI Design Studio - Keyboard Shortcuts Manager
 * Handles Ctrl+Z, Ctrl+Y, Delete, Ctrl+C/V/D, Arrow nudges, and Ctrl+A.
 */

export class ShortcutsManager {
    constructor(fabricCanvas, historyManager) {
        this.canvas = fabricCanvas;
        this.history = historyManager;
        this.clipboard = null;

        this.init();
    }

    init() {
        window.addEventListener('keydown', (e) => this.handleKeyDown(e));
    }

    isEditingText(e) {
        const activeEl = document.activeElement;
        const isDomInput = activeEl && (
            activeEl.tagName === 'INPUT' ||
            activeEl.tagName === 'TEXTAREA' ||
            activeEl.isContentEditable
        );

        const activeObj = this.canvas.getActiveObject();
        const isFabricTextEditing = activeObj && activeObj.isEditing;

        return isDomInput || isFabricTextEditing;
    }

    handleKeyDown(e) {
        const isCtrl = e.ctrlKey || e.metaKey;
        const key = e.key.toLowerCase();

        // 1. Undo: Ctrl+Z
        if (isCtrl && key === 'z' && !e.shiftKey) {
            if (this.isEditingText(e)) return;
            e.preventDefault();
            this.history?.undo();
            return;
        }

        // 2. Redo: Ctrl+Y or Ctrl+Shift+Z
        if ((isCtrl && key === 'y') || (isCtrl && key === 'z' && e.shiftKey)) {
            if (this.isEditingText(e)) return;
            e.preventDefault();
            this.history?.redo();
            return;
        }

        // 3. Select All: Ctrl+A (when not in DOM input)
        if (isCtrl && key === 'a' && !this.isEditingText(e)) {
            e.preventDefault();
            this.canvas.discardActiveObject();
            const allObjs = this.canvas.getObjects().filter(o => o.selectable !== false);
            if (allObjs.length > 0) {
                const sel = new fabric.ActiveSelection(allObjs, { canvas: this.canvas });
                this.canvas.setActiveObject(sel);
                this.canvas.requestRenderAll();
            }
            return;
        }

        // 4. Copy: Ctrl+C
        if (isCtrl && key === 'c' && !this.isEditingText(e)) {
            const activeObj = this.canvas.getActiveObject();
            if (activeObj) {
                activeObj.clone((cloned) => {
                    this.clipboard = cloned;
                });
            }
            return;
        }

        // 5. Paste: Ctrl+V
        if (isCtrl && key === 'v' && !this.isEditingText(e)) {
            if (!this.clipboard) return;
            e.preventDefault();

            this.clipboard.clone((clonedObj) => {
                this.canvas.discardActiveObject();
                clonedObj.set({
                    left: clonedObj.left + 24,
                    top: clonedObj.top + 24,
                    evented: true,
                });

                if (clonedObj.type === 'activeSelection') {
                    clonedObj.canvas = this.canvas;
                    clonedObj.forEachObject((obj) => {
                        this.canvas.add(obj);
                    });
                    clonedObj.setCoords();
                } else {
                    this.canvas.add(clonedObj);
                }

                this.clipboard.top += 24;
                this.clipboard.left += 24;
                this.canvas.setActiveObject(clonedObj);
                this.canvas.requestRenderAll();
                if (this.history) this.history.saveState();
            });
            return;
        }

        // 6. Duplicate: Ctrl+D
        if (isCtrl && key === 'd' && !this.isEditingText(e)) {
            e.preventDefault();
            const activeObj = this.canvas.getActiveObject();
            if (!activeObj) return;

            activeObj.clone((cloned) => {
                cloned.set({
                    left: activeObj.left + 24,
                    top: activeObj.top + 24,
                    name: `${activeObj.name || 'Object'} (Copy)`
                });
                this.canvas.add(cloned);
                this.canvas.setActiveObject(cloned);
                this.canvas.requestRenderAll();
                if (this.history) this.history.saveState();
            });
            return;
        }

        // 7. Delete / Backspace
        if ((e.key === 'Delete' || e.key === 'Backspace') && !this.isEditingText(e)) {
            const activeObj = this.canvas.getActiveObject();
            if (activeObj) {
                e.preventDefault();
                if (activeObj.type === 'activeSelection') {
                    activeObj.forEachObject((obj) => this.canvas.remove(obj));
                } else {
                    this.canvas.remove(activeObj);
                }
                this.canvas.discardActiveObject();
                this.canvas.requestRenderAll();
                if (this.history) this.history.saveState();
            }
            return;
        }

        // 8. Arrow Keys Nudge
        if (['arrowup', 'arrowdown', 'arrowleft', 'arrowright'].includes(key) && !this.isEditingText(e)) {
            const activeObj = this.canvas.getActiveObject();
            if (!activeObj) return;

            e.preventDefault();
            const step = e.shiftKey ? 10 : 1;

            if (key === 'arrowup') activeObj.set('top', activeObj.top - step);
            if (key === 'arrowdown') activeObj.set('top', activeObj.top + step);
            if (key === 'arrowleft') activeObj.set('left', activeObj.left - step);
            if (key === 'arrowright') activeObj.set('left', activeObj.left + step);

            activeObj.setCoords();
            this.canvas.requestRenderAll();
            if (this.history) this.history.saveState();
            return;
        }

        // 9. Help & Shortcuts: ? (Shift+/)
        if (e.key === '?' && !this.isEditingText(e)) {
            e.preventDefault();
            const shortcutsModal = document.getElementById('shortcuts-modal');
            if (shortcutsModal) {
                shortcutsModal.style.display = shortcutsModal.style.display === 'none' ? 'flex' : 'none';
            }
            return;
        }

        // 10. Escape: Close modals and discard selection
        if (key === 'escape') {
            document.querySelectorAll('.modal-overlay').forEach(m => {
                m.style.display = 'none';
            });
            this.canvas.discardActiveObject();
            this.canvas.requestRenderAll();
        }
    }
}

