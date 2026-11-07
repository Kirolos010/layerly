/**
 * AI Design Studio - Project Manager & Autosave Engine
 * Handles saving to MySQL, thumbnails, localStorage autosaving, session restoration,
 * and loading projects or templates from URL parameters.
 */

import { renderDesignToFabric } from './ai-renderer.js';

export class ProjectManager {
    constructor(fabricCanvas, canvasManager, historyManager) {
        this.canvas = fabricCanvas;
        this.canvasManager = canvasManager;
        this.history = historyManager;

        this.currentProjectId = null;
        this.hasUnsavedChanges = false;
        this.autosaveTimer = null;
        this.AUTOSAVE_KEY = 'ai_design_studio_autosave';

        this.init();
    }

    init() {
        this.bindSaveButton();
        this.bindUnsavedChangeDetection();
        this.setupBeforeUnloadWarning();
        this.checkUrlParams();
    }

    getCSRF() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    getProjectName() {
        const input = document.getElementById('project-name-input');
        const val = input ? input.value.trim() : '';
        return val || 'Untitled Design';
    }

    setProjectName(name) {
        const input = document.getElementById('project-name-input');
        if (input && name) {
            input.value = name;
        }
    }

    showSaveStatus(msg, isError = false) {
        const statusEl = document.getElementById('save-status-text');
        if (statusEl) {
            statusEl.textContent = msg;
            statusEl.style.color = isError ? 'var(--danger)' : 'var(--success)';
            statusEl.style.display = 'inline';
            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 3500);
        }
    }

    /**
     * Generate lightweight thumbnail for project grid
     */
    generateThumbnail() {
        if (!this.canvas) return null;

        const cw = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
        const targetW = 320;
        const multiplier = (targetW / cw) * (1 / (this.canvasManager ? this.canvasManager.zoom : 1));

        try {
            return this.canvas.toDataURL({
                format: 'jpeg',
                quality: 0.75,
                multiplier: multiplier
            });
        } catch (e) {
            console.warn('Could not generate canvas thumbnail:', e);
            return null;
        }
    }

    /**
     * Manual save to MySQL via /api/projects.php
     */
    async saveProject() {
        const btnSave = document.getElementById('btn-save-project');
        if (btnSave) {
            btnSave.disabled = true;
            btnSave.textContent = '⏳ Saving...';
        }

        const name = this.getProjectName();
        const json = this.canvas.toJSON([
            'id', 'name', 'customType', 'lockMovementX', 'lockMovementY',
            'selectable', 'evented', 'direction', 'rx', 'ry'
        ]);
        const jsonString = JSON.stringify(json);
        const thumbnail = this.generateThumbnail();

        const isUpdate = Boolean(this.currentProjectId);
        const payload = {
            action: isUpdate ? 'update' : 'create',
            name: name,
            json_data: jsonString,
            thumbnail: thumbnail
        };

        if (isUpdate) {
            payload.id = this.currentProjectId;
        }

        try {
            const res = await fetch('api/projects.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRF()
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Failed to save project.');
            }

            this.currentProjectId = data.project_id;
            this.hasUnsavedChanges = false;
            this.showSaveStatus('✓ Saved!');

            // Update URL without reload
            const url = new URL(window.location.href);
            url.searchParams.set('project_id', this.currentProjectId);
            url.searchParams.delete('template');
            url.searchParams.delete('load_generation');
            window.history.replaceState({}, '', url.toString());

            // Clear autosave once saved to cloud
            localStorage.removeItem(this.AUTOSAVE_KEY);

        } catch (err) {
            this.showSaveStatus('Save failed: ' + err.message, true);
        } finally {
            if (btnSave) {
                btnSave.disabled = false;
                btnSave.textContent = '💾 Save';
            }
        }
    }

    bindSaveButton() {
        const btnSave = document.getElementById('btn-save-project');
        if (btnSave) {
            btnSave.addEventListener('click', () => this.saveProject());
        }

        // Trigger save on Enter inside Project Name input
        const nameInput = document.getElementById('project-name-input');
        if (nameInput) {
            nameInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    nameInput.blur();
                    this.saveProject();
                }
            });
            nameInput.addEventListener('change', () => {
                this.hasUnsavedChanges = true;
                this.queueAutosave();
            });
        }
    }

    /**
     * Detect modifications to trigger autosave & warning
     */
    bindUnsavedChangeDetection() {
        const markChanged = () => {
            this.hasUnsavedChanges = true;
            this.queueAutosave();
        };

        this.canvas.on('object:added', (e) => {
            if (e.target && e.target.type !== 'activeSelection') markChanged();
        });
        this.canvas.on('object:modified', markChanged);
        this.canvas.on('object:removed', markChanged);
    }

    queueAutosave() {
        clearTimeout(this.autosaveTimer);
        this.autosaveTimer = setTimeout(() => {
            this.executeAutosave();
        }, 4000); // 4-second debounce
    }

    executeAutosave() {
        if (!this.hasUnsavedChanges || !this.canvas) return;

        try {
            const json = this.canvas.toJSON([
                'id', 'name', 'customType', 'lockMovementX', 'lockMovementY',
                'selectable', 'evented', 'direction', 'rx', 'ry'
            ]);

            const record = {
                projectId: this.currentProjectId,
                name: this.getProjectName(),
                timestamp: Date.now(),
                canvas: {
                    width: this.canvasManager ? this.canvasManager.virtualWidth : 1080,
                    height: this.canvasManager ? this.canvasManager.virtualHeight : 1080,
                    background: this.canvas.backgroundColor || '#ffffff'
                },
                json: json
            };

            localStorage.setItem(this.AUTOSAVE_KEY, JSON.stringify(record));
            const statusEl = document.getElementById('save-status-text');
            if (statusEl) {
                statusEl.textContent = 'Autosaved locally';
                statusEl.style.color = 'var(--text-muted)';
                statusEl.style.display = 'inline';
                setTimeout(() => { statusEl.style.display = 'none'; }, 2000);
            }
        } catch (e) {
            // Ignore quota exceeded errors
        }
    }

    setupBeforeUnloadWarning() {
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes that will be lost.';
                return e.returnValue;
            }
        });
    }

    /**
     * Check URL query parameters for ?project_id=X or ?template=X
     */
    async checkUrlParams() {
        const params = new URLSearchParams(window.location.search);
        const projectId = params.get('project_id');
        const templateId = params.get('template');

        if (projectId && parseInt(projectId, 10) > 0) {
            await this.loadProjectById(parseInt(projectId, 10));
            return;
        }

        if (templateId) {
            await this.loadTemplateById(templateId);
            return;
        }

        // Check for autosaved session if user just opened blank studio
        this.checkAutosaveRestore();
    }

    async loadProjectById(id) {
        try {
            const res = await fetch(`api/projects.php?action=get&id=${id}`, {
                headers: { 'X-CSRF-Token': this.getCSRF() }
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Could not load project.');
            }

            const proj = data.project;
            this.currentProjectId = proj.id;
            this.setProjectName(proj.name);

            const parsed = typeof proj.json_data === 'string' ? JSON.parse(proj.json_data) : proj.json_data;

            // Load into fabric
            if (parsed.objects && parsed.canvas) {
                await renderDesignToFabric(this.canvas, parsed, this.canvasManager);
            } else {
                // Raw Fabric JSON format
                await new Promise((resolve) => {
                    this.canvas.loadFromJSON(parsed, () => {
                        this.canvas.requestRenderAll();
                        resolve();
                    });
                });
            }

            if (this.history) {
                this.history.reset();
            }

            this.hasUnsavedChanges = false;
            window.dispatchEvent(new CustomEvent('editor:design-loaded', { detail: parsed }));

        } catch (err) {
            console.error('Error loading project:', err);
        }
    }

    async loadTemplateById(id) {
        try {
            const res = await fetch(`api/templates.php?action=get&id=${encodeURIComponent(id)}`);
            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Could not load template.');
            }

            const tpl = data.template;
            const docLang = document.documentElement.lang || 'en';
            const templateTitle = (docLang === 'ar' && tpl.name_ar) ? tpl.name_ar : (tpl.name_en || tpl.name_ar);

            this.setProjectName(templateTitle);
            this.currentProjectId = null; // Save will create new project

            await renderDesignToFabric(this.canvas, tpl.design, this.canvasManager);

            if (this.history) {
                this.history.reset();
            }

            this.hasUnsavedChanges = true;
            window.dispatchEvent(new CustomEvent('editor:design-loaded', { detail: tpl.design }));

        } catch (err) {
            console.error('Error loading template:', err);
        }
    }

    checkAutosaveRestore() {
        const raw = localStorage.getItem(this.AUTOSAVE_KEY);
        if (!raw) return;

        try {
            const data = JSON.parse(raw);
            const ageMs = Date.now() - (data.timestamp || 0);

            // Ignore autosaves older than 7 days
            if (ageMs > 7 * 86400 * 1000) {
                localStorage.removeItem(this.AUTOSAVE_KEY);
                return;
            }

            const banner = document.getElementById('autosave-restore-banner');
            if (banner) {
                banner.style.display = 'flex';

                const btnRestore = document.getElementById('btn-restore-autosave');
                const btnDiscard = document.getElementById('btn-discard-autosave');

                if (btnRestore) {
                    btnRestore.onclick = async () => {
                        banner.style.display = 'none';
                        this.setProjectName(data.name || 'Restored Design');
                        this.currentProjectId = data.projectId || null;

                        if (data.json) {
                            await new Promise((resolve) => {
                                this.canvas.loadFromJSON(data.json, () => {
                                    this.canvas.requestRenderAll();
                                    resolve();
                                });
                            });
                        }
                        if (this.history) this.history.saveState();
                        this.hasUnsavedChanges = true;
                    };
                }

                if (btnDiscard) {
                    btnDiscard.onclick = () => {
                        banner.style.display = 'none';
                        localStorage.removeItem(this.AUTOSAVE_KEY);
                    };
                }
            }
        } catch (e) {
            localStorage.removeItem(this.AUTOSAVE_KEY);
        }
    }
}

