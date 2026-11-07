/**
 * AI Design Studio - AI Command Bar & Generation Controller
 * Handles prompt enhancement, AI generation, regeneration, AI edit commands,
 * and loading designs from history.
 */

import { renderDesignToFabric } from './ai-renderer.js';

export class AiBar {
    constructor(fabricCanvas, canvasManager, historyManager) {
        this.canvas = fabricCanvas;
        this.canvasManager = canvasManager;
        this.history = historyManager;

        this.originalPrompt = '';
        this.lastGenerationPayload = null;
        this.lastResultData = null;

        this.init();
    }

    init() {
        this.bindEnhancePrompt();
        this.bindGenerateDesign();
        this.bindRegenerate();
        this.bindEditWithAi();
        this.bindExamplePrompts();
        this.checkHistoryLoadParam();
    }

    getCSRF() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    setStatus(text, type = 'idle') {
        const pill = document.getElementById('status-pill');
        const alertBox = document.getElementById('status-alert');

        if (pill) {
            pill.textContent = text;
            pill.className = `stat-pill status-${type}`;
        }

        if (alertBox) {
            if (type === 'error') {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = text;
                alertBox.style.display = 'block';
            } else if (type === 'success') {
                alertBox.className = 'alert alert-success';
                alertBox.textContent = text;
                alertBox.style.display = 'block';
                setTimeout(() => { alertBox.style.display = 'none'; }, 4000);
            } else {
                alertBox.style.display = 'none';
            }
        }
    }

    updateStats(objectsCount = 0, timeMs = 0, provider = 'Gemini') {
        const objStat = document.getElementById('objects-stat');
        const timeStat = document.getElementById('time-stat');
        const provStat = document.getElementById('provider-stat');

        if (objStat) objStat.textContent = objectsCount;
        if (timeStat) timeStat.textContent = `${timeMs}ms`;
        if (provStat) provStat.textContent = provider;
    }

    /**
     * Serializes Fabric canvas objects into the structured AI design schema
     */
    canvasToDesignSchema() {
        const w = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
        const h = this.canvasManager ? this.canvasManager.virtualHeight : 1080;
        const bg = typeof this.canvas.backgroundColor === 'string' ? this.canvas.backgroundColor : '#ffffff';

        const objects = [];
        this.canvas.getObjects().forEach(obj => {
            if (obj.visible === false) return;

            const item = {
                type: 'rect',
                left: Math.round(obj.left),
                top: Math.round(obj.top),
                width: Math.round(obj.getScaledWidth()),
                height: Math.round(obj.getScaledHeight()),
                opacity: typeof obj.opacity === 'number' ? obj.opacity : 1.0,
                angle: Math.round(obj.angle || 0)
            };

            if (obj.type === 'textbox' || obj.type === 'text' || obj.type === 'i-text') {
                item.type = 'text';
                item.text = obj.text || '';
                item.fontFamily = obj.fontFamily || 'Poppins';
                item.fontSize = Math.round((obj.fontSize || 24) * (obj.scaleY || 1));
                item.fontWeight = obj.fontWeight || 'normal';
                item.textAlign = obj.textAlign || 'left';
                item.direction = obj.direction || 'ltr';
                item.fill = typeof obj.fill === 'string' ? obj.fill : '#0f172a';
            } else if (obj.type === 'rect') {
                item.type = 'rect';
                item.fill = typeof obj.fill === 'string' ? obj.fill : '#3b82f6';
                item.stroke = typeof obj.stroke === 'string' ? obj.stroke : null;
                item.strokeWidth = obj.strokeWidth || 0;
                item.rx = obj.rx || 0;
            } else if (obj.type === 'circle') {
                item.type = 'circle';
                item.fill = typeof obj.fill === 'string' ? obj.fill : '#10b981';
                item.stroke = typeof obj.stroke === 'string' ? obj.stroke : null;
                item.strokeWidth = obj.strokeWidth || 0;
            } else if (obj.type === 'triangle') {
                item.type = 'triangle';
                item.fill = typeof obj.fill === 'string' ? obj.fill : '#f59e0b';
            } else if (obj.type === 'line') {
                item.type = 'line';
                item.stroke = typeof obj.stroke === 'string' ? obj.stroke : '#64748b';
                item.strokeWidth = obj.strokeWidth || 2;
            } else if (obj.type === 'image') {
                item.type = 'image';
                item.imageQuery = obj.name || 'image';
            } else {
                item.type = 'rect';
                item.fill = typeof obj.fill === 'string' ? obj.fill : '#3b82f6';
            }

            objects.push(item);
        });

        return {
            canvas: {
                width: w,
                height: h,
                background: bg
            },
            objects: objects
        };
    }

    /**
     * Enhance prompt with Undo capability
     */
    bindEnhancePrompt() {
        const btnEnhance = document.getElementById('btn-enhance');
        const btnUndoEnhance = document.getElementById('btn-undo-enhance');
        const promptInput = document.getElementById('prompt-input');
        const langSelect = document.getElementById('text-language');

        if (!btnEnhance || !promptInput) return;

        btnEnhance.addEventListener('click', async () => {
            const prompt = promptInput.value.trim();
            if (!prompt) {
                this.setStatus('Please enter a prompt to enhance.', 'error');
                promptInput.focus();
                return;
            }

            this.originalPrompt = prompt;
            btnEnhance.disabled = true;
            btnEnhance.textContent = '⏳ Enhancing...';
            this.setStatus('Enhancing prompt with AI...', 'loading');

            try {
                const res = await fetch('api/enhance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.getCSRF()
                    },
                    body: JSON.stringify({
                        prompt: prompt,
                        language: langSelect ? langSelect.value : 'auto'
                    })
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Failed to enhance prompt.');
                }

                const enhancedPrompt = data.enhanced_prompt || data.data?.enhanced_prompt || '';
                promptInput.value = enhancedPrompt;
                this.setStatus('Prompt enhanced successfully!', 'success');

                if (btnUndoEnhance) {
                    btnUndoEnhance.style.display = 'inline-flex';
                }
            } catch (err) {
                this.setStatus(`Enhance failed: ${err.message}`, 'error');
            } finally {
                btnEnhance.disabled = false;
                btnEnhance.textContent = '✨ Enhance';
            }
        });

        if (btnUndoEnhance) {
            btnUndoEnhance.addEventListener('click', () => {
                if (this.originalPrompt) {
                    promptInput.value = this.originalPrompt;
                    btnUndoEnhance.style.display = 'none';
                    this.setStatus('Restored original prompt.', 'idle');
                }
            });
        }
    }

    /**
     * Generate design from scratch
     */
    bindGenerateDesign() {
        const btnGenerate = document.getElementById('btn-generate');
        const promptInput = document.getElementById('prompt-input');

        if (!btnGenerate || !promptInput) return;

        btnGenerate.addEventListener('click', async () => {
            const prompt = promptInput.value.trim();
            if (!prompt) {
                this.setStatus('Please enter a design prompt.', 'error');
                promptInput.focus();
                return;
            }

            const payload = {
                prompt: prompt,
                canvas_width: parseInt(document.getElementById('canvas-width')?.value, 10) || 1080,
                canvas_height: parseInt(document.getElementById('canvas-height')?.value, 10) || 1080,
                design_type: document.getElementById('design-type')?.value || 'social_post',
                style: document.getElementById('design-style')?.value || 'modern',
                color_mood: document.getElementById('color-mood')?.value || 'vibrant',
                text_language: document.getElementById('text-language')?.value || 'auto',
                generate_background: !!document.getElementById('generate-bg-toggle')?.checked
            };

            this.lastGenerationPayload = payload;
            await this.executeGeneration(payload, btnGenerate);
        });
    }

    /**
     * Regenerate current design with same parameters
     */
    bindRegenerate() {
        const btnRegen = document.getElementById('btn-regenerate');
        if (!btnRegen) return;

        btnRegen.addEventListener('click', async () => {
            if (!this.lastGenerationPayload) {
                const promptInput = document.getElementById('prompt-input');
                if (promptInput && promptInput.value.trim()) {
                    document.getElementById('btn-generate')?.click();
                } else {
                    this.setStatus('No previous design parameters to regenerate.', 'error');
                }
                return;
            }
            await this.executeGeneration(this.lastGenerationPayload, btnRegen);
        });
    }

    async executeGeneration(payload, triggerButton) {
        if (triggerButton) {
            triggerButton.disabled = true;
            triggerButton.dataset.originalText = triggerButton.textContent;
            triggerButton.textContent = '⏳ Designing...';
        }

        const editBtn = document.getElementById('btn-edit');
        if (editBtn) editBtn.disabled = true;

        this.setStatus('Generating design with AI...', 'loading');
        const startTime = Date.now();

        try {
            const res = await fetch('api/generate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.getCSRF()
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            const elapsed = Date.now() - startTime;

            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Failed to generate design.');
            }

            const designResult = data.data;
            this.lastResultData = designResult;

            // Render into Fabric.js Canvas
            await renderDesignToFabric(this.canvas, designResult, this.canvasManager);

            // Save history snapshot
            if (this.history) {
                this.history.saveState();
            }

            // Update stats
            const objCount = designResult.objects ? designResult.objects.length : 0;
            this.updateStats(objCount, elapsed, data.meta?.provider || 'AI');

            this.setStatus('Design generated successfully!', 'success');

            if (editBtn) editBtn.disabled = false;

            // Dispatch event for layers and other panels
            window.dispatchEvent(new CustomEvent('editor:design-loaded', { detail: designResult }));

        } catch (err) {
            this.setStatus(`Generation error: ${err.message}`, 'error');
        } finally {
            if (triggerButton) {
                triggerButton.disabled = false;
                triggerButton.textContent = triggerButton.dataset.originalText || '🎨 Generate';
            }
        }
    }

    /**
     * Edit current design with natural language AI command
     */
    bindEditWithAi() {
        const btnEdit = document.getElementById('btn-edit');
        const instructionInput = document.getElementById('instruction-input');

        if (!btnEdit || !instructionInput) return;

        btnEdit.addEventListener('click', async () => {
            const instruction = instructionInput.value.trim();
            if (!instruction) {
                this.setStatus('Please describe how to edit the design.', 'error');
                instructionInput.focus();
                return;
            }

            const currentSchema = this.canvasToDesignSchema();
            if (!currentSchema.objects || currentSchema.objects.length === 0) {
                this.setStatus('Cannot edit an empty canvas. Add elements or generate a design first.', 'error');
                return;
            }

            btnEdit.disabled = true;
            btnEdit.textContent = '⏳ Applying AI Edits...';
            this.setStatus('Updating design with AI instruction...', 'loading');
            const startTime = Date.now();

            try {
                const res = await fetch('api/edit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.getCSRF()
                    },
                    body: JSON.stringify({
                        current_json: currentSchema,
                        instruction: instruction
                    })
                });

                const data = await res.json();
                const elapsed = Date.now() - startTime;

                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Failed to edit design.');
                }

                const updatedDesign = data.data;
                await renderDesignToFabric(this.canvas, updatedDesign, this.canvasManager);

                if (this.history) {
                    this.history.saveState();
                }

                const objCount = updatedDesign.objects ? updatedDesign.objects.length : 0;
                this.updateStats(objCount, elapsed, data.meta?.provider || 'AI Edit');

                this.setStatus('AI edits applied successfully!', 'success');
                instructionInput.value = '';

                window.dispatchEvent(new CustomEvent('editor:design-loaded', { detail: updatedDesign }));

            } catch (err) {
                this.setStatus(`Edit error: ${err.message}`, 'error');
            } finally {
                btnEdit.disabled = false;
                btnEdit.textContent = '✨ Apply AI Edit';
            }
        });
    }

    /**
     * Example prompt chips in Arabic and English
     */
    bindExamplePrompts() {
        const chips = document.querySelectorAll('.prompt-chip');
        const promptInput = document.getElementById('prompt-input');

        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                const text = chip.getAttribute('data-prompt') || chip.textContent.trim();
                if (promptInput) {
                    promptInput.value = text;
                    promptInput.focus();
                }
            });
        });
    }

    /**
     * If URL has ?load_generation=ID, load it from history API
     */
    async checkHistoryLoadParam() {
        const loadId = document.body.dataset.loadId;
        if (!loadId || parseInt(loadId, 10) <= 0) return;

        this.setStatus('Loading saved design from history...', 'loading');
        try {
            const res = await fetch(`api/history.php?action=get&id=${loadId}`, {
                headers: {
                    'X-CSRF-Token': this.getCSRF()
                }
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Could not load generation history');
            }

            const rawJson = data.data.result_json;
            const parsed = typeof rawJson === 'string' ? JSON.parse(rawJson) : rawJson;

            await renderDesignToFabric(this.canvas, parsed, this.canvasManager);

            if (this.history) {
                this.history.saveState();
            }

            const promptInput = document.getElementById('prompt-input');
            if (promptInput && data.data.prompt) {
                promptInput.value = data.data.prompt;
            }

            const editBtn = document.getElementById('btn-edit');
            if (editBtn) editBtn.disabled = false;

            this.setStatus('Saved design loaded into editor!', 'success');
            window.dispatchEvent(new CustomEvent('editor:design-loaded', { detail: parsed }));

        } catch (err) {
            this.setStatus(`History load failed: ${err.message}`, 'error');
        }
    }
}

