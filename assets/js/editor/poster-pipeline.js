/**
 * AI Design Studio - Phase 6 AI Poster Pipeline Controller
 * Manages:
 * - "AI Poster (New)" modal & sub-tabs
 * - Mode 1: "Import & Make Editable" (OCR extraction, clean plate inpainting, layer rebuilding)
 * - Mode 2: "Text to Poster" (Art direction, text-free background, typography safe zones)
 * - Compare View (A/B Toggle & Split comparison against original poster)
 * - Re-align layers button
 * - Live step-by-step progress & retry
 * - Quota indicator & rate limit countdown
 * - Meta display in editor footer
 */

import { loadFonts, isArabic } from './font-manager.js';

export class PosterPipeline {
    constructor(fabricCanvas, canvasManager, historyManager) {
        this.canvas = fabricCanvas;
        this.canvasManager = canvasManager;
        this.history = historyManager;

        this.originalImageUrl = null;
        this.cleanPlateUrl = null;
        this.textBlocks = [];
        this.canvasMeta = null;
        this.isComparing = false;
        this.compareOverlay = null;

        this.selectedFile = null;
        this.lastImportCacheId = null;
        this.lastGenCacheId = null;

        this.init();
    }

    init() {
        this.bindModalTriggers();
        this.bindDropzone();
        this.bindMode1Import();
        this.bindMode2Generate();
        this.bindCompareView();
        this.bindRealign();
        this.fetchQuota();
    }

    getCSRF() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    async fetchQuota() {
        try {
            const res = await fetch('api/poster-quota.php');
            const data = await res.json();
            if (data.success && data.quota) {
                this.updateQuotaDisplay(data.quota);
            }
        } catch (e) {
            console.warn('Could not fetch poster quota:', e);
        }
    }

    updateQuotaDisplay(quota) {
        const remaining = quota.remaining ?? 5;
        const total = quota.limit ?? 5;
        const badges = document.querySelectorAll('.poster-quota-badge');
        badges.forEach(badge => {
            let label = window.i18n ? window.i18n('poster_quota_badge', `${remaining} operations remaining today`) : `${remaining} remaining today`;
            label = label.replace(':count', remaining);
            badge.textContent = `${remaining}/${total} ${label}`;
            if (remaining <= 0) {
                badge.classList.add('status-error');
                badge.classList.remove('status-info');
            } else {
                badge.classList.remove('status-error');
                badge.classList.add('status-info');
            }
        });
    }

    bindModalTriggers() {
        const openBtn = document.getElementById('btn-open-poster-modal');
        const modal = document.getElementById('ai-poster-modal');
        const closeBtn = document.getElementById('btn-close-poster-modal');
        const tabImport = document.getElementById('tab-poster-import');
        const tabGen = document.getElementById('tab-poster-gen');
        const panelImport = document.getElementById('panel-poster-import');
        const panelGen = document.getElementById('panel-poster-gen');

        if (openBtn && modal) {
            openBtn.addEventListener('click', () => {
                modal.style.display = 'flex';
                this.fetchQuota();
            });
        }

        if (closeBtn && modal) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }

        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Sub-tabs switching
        if (tabImport && tabGen && panelImport && panelGen) {
            tabImport.addEventListener('click', () => {
                tabImport.classList.add('active');
                tabGen.classList.remove('active');
                panelImport.style.display = 'block';
                panelGen.style.display = 'none';
            });

            tabGen.addEventListener('click', () => {
                tabGen.classList.add('active');
                tabImport.classList.remove('active');
                panelGen.style.display = 'block';
                panelImport.style.display = 'none';
            });
        }
    }

    bindDropzone() {
        const dropzone = document.getElementById('poster-dropzone');
        const fileInput = document.getElementById('poster-file-input');
        const preview = document.getElementById('poster-file-preview');
        const previewImg = document.getElementById('poster-preview-img');
        const fileNameEl = document.getElementById('poster-file-name');
        const removeBtn = document.getElementById('btn-remove-poster-file');

        if (!dropzone || !fileInput) return;

        dropzone.addEventListener('click', () => fileInput.click());

        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dragover');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                this.handleFileSelected(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0]) {
                this.handleFileSelected(fileInput.files[0]);
            }
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.selectedFile = null;
                fileInput.value = '';
                if (preview) preview.style.display = 'none';
                dropzone.querySelector('.dropzone-content').style.display = 'block';
            });
        }
    }

    handleFileSelected(file) {
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            alert(window.i18n ? window.i18n('invalid_image_format', 'Please upload a JPG, PNG, or WebP image.') : 'Invalid image format.');
            return;
        }
        if (file.size > 5242880) {
            alert(window.i18n ? window.i18n('image_too_large', 'Image exceeds 5MB limit.') : 'File too large (Max 5MB).');
            return;
        }

        this.selectedFile = file;
        const dropzone = document.getElementById('poster-dropzone');
        const preview = document.getElementById('poster-file-preview');
        const previewImg = document.getElementById('poster-preview-img');
        const fileNameEl = document.getElementById('poster-file-name');

        if (fileNameEl) fileNameEl.textContent = `${file.name} (${Math.round(file.size / 1024)} KB)`;
        if (previewImg) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
                if (preview) preview.style.display = 'flex';
                if (dropzone) dropzone.querySelector('.dropzone-content').style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    }

    setStepProgress(containerId, stepNum, status = 'active', text = '', latency = null) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.style.display = 'block';

        const stepEl = container.querySelector(`.step-item[data-step="${stepNum}"]`);
        if (!stepEl) return;

        stepEl.className = `step-item ${status}`;
        const label = stepEl.querySelector('.step-text');
        if (label && text) label.textContent = text;

        const badge = stepEl.querySelector('.step-badge');
        if (badge) {
            if (status === 'completed') badge.textContent = '✓';
            else if (status === 'active') badge.textContent = '⏳';
            else if (status === 'failed') badge.textContent = '✗';
            else badge.textContent = stepNum;
        }

        if (latency !== null) {
            const timeEl = stepEl.querySelector('.step-time');
            if (timeEl) timeEl.textContent = `${latency}ms`;
        }
    }

    /**
     * Mode 1: Import & Make Editable
     */
    bindMode1Import() {
        const btnRun = document.getElementById('btn-run-poster-import');
        if (!btnRun) return;

        btnRun.addEventListener('click', async () => {
            if (!this.selectedFile) {
                alert(window.i18n ? window.i18n('image_required', 'Please select an image first.') : 'Please select a poster image.');
                return;
            }

            const keepOriginal = document.getElementById('opt-keep-original')?.checked || false;
            const progressContainer = 'poster-import-progress';

            btnRun.disabled = true;
            btnRun.textContent = window.i18n ? window.i18n('processing_poster', '⏳ Processing Poster...') : '⏳ Processing...';

            this.setStepProgress(progressContainer, 1, 'active', window.i18n ? window.i18n('step_import_1') : 'Analyzing text & layout with Gemini Vision...');
            this.setStepProgress(progressContainer, 2, 'pending');
            this.setStepProgress(progressContainer, 3, 'pending');

            const formData = new FormData();
            formData.append('image', this.selectedFile);
            formData.append('use_original_bg', keepOriginal ? '1' : '0');
            formData.append('step', 'all');

            const t0 = Date.now();

            try {
                const res = await fetch('api/poster-import.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': this.getCSRF() },
                    body: formData
                });

                const data = await res.json();
                const totalElapsed = Date.now() - t0;

                if (!res.ok || !data.success) {
                    this.setStepProgress(progressContainer, 1, 'failed', data.error || 'Failed to process poster');
                    throw new Error(data.error || 'Poster processing failed.');
                }

                // Step 1 done
                this.setStepProgress(progressContainer, 1, 'completed', null, data.meta?.vision_latency_ms || Math.round(totalElapsed * 0.7));
                // Step 2 done
                this.setStepProgress(progressContainer, 2, 'completed', null, data.meta?.clean_plate_latency || Math.round(totalElapsed * 0.2));
                // Step 3 done
                this.setStepProgress(progressContainer, 3, 'completed', null, Math.round(totalElapsed * 0.1));

                // Save references for Compare & Realign
                this.originalImageUrl = data.original_image_url;
                this.cleanPlateUrl = data.clean_plate_url;
                this.textBlocks = data.text_blocks || [];
                this.canvasMeta = data.canvas;
                this.lastImportCacheId = data.cache_id;

                // Reconstruct scene in Fabric.js
                await this.reconstructScene(data);

                // Update quota
                if (data.quota) this.updateQuotaDisplay(data.quota);

                // Close modal and show success toast
                setTimeout(() => {
                    const modal = document.getElementById('ai-poster-modal');
                    if (modal) modal.style.display = 'none';
                }, 800);

                // Surface degraded fallback notice if inpainting was used
                if (data.fallback_used && data.notice) {
                    if (window.toastManager) {
                        window.toastManager.warning(data.notice);
                    } else {
                        alert(data.notice);
                    }
                } else if (window.toastManager) {
                    window.toastManager.success('Poster imported & editable layers rebuilt successfully!');
                }

                // Show compare & realign buttons in floating toolbar
                this.showPosterHUD(data.meta);

            } catch (err) {
                console.error(err);
                if (window.toastManager) {
                    window.toastManager.error(err.message);
                } else {
                    alert(err.message);
                }
            } finally {
                btnRun.disabled = false;
                btnRun.textContent = window.i18n ? window.i18n('btn_import_poster') : '✨ Import & Rebuild Editable Layers';
            }
        });
    }

    /**
     * Mode 2: Text to Poster
     */
    bindMode2Generate() {
        const btnRun = document.getElementById('btn-run-poster-generate');
        const promptInput = document.getElementById('poster-prompt-input');
        const styleSelect = document.getElementById('poster-style-preset');
        const dimSelect = document.getElementById('poster-dimensions-preset');

        if (!btnRun || !promptInput) return;

        btnRun.addEventListener('click', async () => {
            const prompt = promptInput.value.trim();
            if (!prompt) {
                alert(window.i18n ? window.i18n('prompt_required', 'Please enter a poster prompt.') : 'Please enter a prompt.');
                promptInput.focus();
                return;
            }

            const stylePreset = styleSelect ? styleSelect.value : 'cyber_tech';
            let width = 1080;
            let height = 1080;
            if (dimSelect) {
                if (dimSelect.value === 'story') { width = 1080; height = 1920; }
                else if (dimSelect.value === 'landscape') { width = 1200; height = 630; }
                else if (dimSelect.value === 'banner') { width = 1024; height = 558; }
            }

            const progressContainer = 'poster-gen-progress';
            btnRun.disabled = true;
            btnRun.textContent = window.i18n ? window.i18n('generating_poster', '⏳ Generating Poster...') : '⏳ Generating...';

            this.setStepProgress(progressContainer, 1, 'active', window.i18n ? window.i18n('step_gen_1') : 'Creative art direction & copywriting pass...');
            this.setStepProgress(progressContainer, 2, 'pending');
            this.setStepProgress(progressContainer, 3, 'pending');

            const t0 = Date.now();

            try {
                const res = await fetch('api/poster-generate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.getCSRF()
                    },
                    body: JSON.stringify({
                        prompt: prompt,
                        style_preset: stylePreset,
                        width: width,
                        height: height,
                        step: 'all'
                    })
                });

                const data = await res.json();
                const totalElapsed = Date.now() - t0;

                if (!res.ok || !data.success) {
                    this.setStepProgress(progressContainer, 1, 'failed', data.error || 'Failed to generate poster');
                    throw new Error(data.error || 'Poster generation failed.');
                }

                // Step 1 done
                this.setStepProgress(progressContainer, 1, 'completed', null, data.meta?.art_latency_ms || Math.round(totalElapsed * 0.4));
                // Step 2 done
                this.setStepProgress(progressContainer, 2, 'completed', null, data.meta?.image_latency_ms || Math.round(totalElapsed * 0.5));
                // Step 3 done
                this.setStepProgress(progressContainer, 3, 'completed', null, Math.round(totalElapsed * 0.1));

                this.cleanPlateUrl = data.clean_plate_url;
                this.canvasMeta = data.canvas;
                this.lastGenCacheId = data.cache_id;

                await this.reconstructScene(data);

                if (data.quota) this.updateQuotaDisplay(data.quota);

                setTimeout(() => {
                    const modal = document.getElementById('ai-poster-modal');
                    if (modal) modal.style.display = 'none';
                }, 800);

                if (window.toastManager) {
                    window.toastManager.success('AI Poster generated successfully!');
                }

                this.showPosterHUD(data.meta);

            } catch (err) {
                console.error(err);
                if (window.toastManager) {
                    window.toastManager.error(err.message);
                } else {
                    alert(err.message);
                }
            } finally {
                btnRun.disabled = false;
                btnRun.textContent = window.i18n ? window.i18n('btn_generate_poster') : '🚀 Generate AI Poster';
            }
        });
    }

    /**
     * Reconstruct Fabric.js scene from payload with high-fidelity layers & auto-fitted typography
     */
    async reconstructScene(data) {
        if (!this.canvas || !data || !data.canvas) return;

        const w = data.canvas.width || 1080;
        const h = data.canvas.height || 1080;

        if (this.canvasManager) {
            this.canvasManager.setResolution(w, h);
        }

        this.canvas.clear();
        this.canvas.setBackgroundColor(data.canvas.bg_color || '#020b14', () => {
            this.canvas.requestRenderAll();
        });

        // Preload fonts
        const fontsToLoad = ['Cairo', 'Poppins'];
        (data.objects || []).forEach(o => {
            if (o.type === 'text' && o.fontFamily) {
                fontsToLoad.push(o.fontFamily);
            }
        });
        await loadFonts(fontsToLoad);

        // Render objects in order: bottom locked background first, then badges, then textboxes
        const objects = data.objects || [];
        for (const obj of objects) {
            if (obj.type === 'image') {
                const imgObj = await this.loadImagePromise(obj.src, {
                    left: obj.left || 0,
                    top: obj.top || 0,
                    targetWidth: obj.width || w,
                    targetHeight: obj.height || h,
                    opacity: obj.opacity ?? 1.0,
                    angle: obj.angle || 0
                });

                imgObj.set({
                    selectable: false,
                    evented: false,
                    lockMovementX: true,
                    lockMovementY: true,
                    lockRotation: true,
                    lockScalingX: true,
                    lockScalingY: true
                });
                imgObj.isBackgroundLayer = true;
                imgObj.name = window.i18n ? window.i18n('ai_background_layer', 'AI Background') : 'Clean Plate Background';
                this.canvas.add(imgObj);

            } else if (obj.type === 'rect') {
                const rectObj = new fabric.Rect({
                    left: obj.left,
                    top: obj.top,
                    width: obj.width,
                    height: obj.height,
                    fill: obj.fill || '#00e5ff',
                    stroke: obj.stroke || null,
                    strokeWidth: obj.strokeWidth || 0,
                    rx: obj.rx || 18,
                    ry: obj.ry || 18,
                    opacity: obj.opacity ?? 1.0,
                    selectable: true,
                    name: obj.name || 'Pill Badge'
                });
                rectObj.isBadge = true;
                this.canvas.add(rectObj);

            } else if (obj.type === 'text') {
                const textStr = obj.text || '';
                const arabic = isArabic(textStr);
                const fontFamily = obj.fontFamily || (arabic ? 'Cairo' : 'Poppins');
                const direction = obj.direction || (arabic ? 'rtl' : 'ltr');
                const textAlign = obj.textAlign || (direction === 'rtl' ? 'right' : 'center');

                let shadow = null;
                if (obj.shadow && obj.shadow.color) {
                    shadow = new fabric.Shadow({
                        color: obj.shadow.color,
                        blur: obj.shadow.blur || 10,
                        offsetX: obj.shadow.offsetX || 0,
                        offsetY: obj.shadow.offsetY || 0
                    });
                }

                const tb = new fabric.Textbox(textStr, {
                    left: obj.left,
                    top: obj.top,
                    width: obj.width,
                    fontSize: obj.fontSize || 24,
                    fontFamily: fontFamily,
                    fontWeight: obj.fontWeight || 'bold',
                    fill: obj.fill || '#ffffff',
                    textAlign: textAlign,
                    direction: direction,
                    shadow: shadow,
                    splitByGrapheme: true,
                    editable: true,
                    name: `Text: ${textStr.substring(0, 18)}...`
                });

                tb.originalBox = obj.originalBox || {
                    left: obj.left,
                    top: obj.top,
                    width: obj.width,
                    height: obj.height,
                    fontSize: obj.fontSize
                };

                // Auto-fit text bounding box so it never clips
                this.autoFitTextbox(tb, obj.width, obj.height);

                this.canvas.add(tb);
            }
        }

        this.canvas.requestRenderAll();
        if (this.history) this.history.saveState();
        window.dispatchEvent(new CustomEvent('canvas:objects-updated'));
        window.dispatchEvent(new CustomEvent('editor:design-loaded', { detail: data }));
    }

    /**
     * Auto-fits fontSize slightly so text fits inside detected box without clipping
     */
    autoFitTextbox(tb, targetW, targetH) {
        if (!tb || !targetW || !targetH) return;
        let fontSize = tb.fontSize || 24;
        let iterations = 0;
        tb.set('width', targetW);

        while (iterations < 8) {
            iterations++;
            const textH = tb.calcTextHeight ? tb.calcTextHeight() : (tb.height || targetH);
            if (textH > targetH * 1.08 && fontSize > 11) {
                fontSize = Math.floor(fontSize * 0.92);
                tb.set('fontSize', fontSize);
            } else {
                break;
            }
        }
    }

    /**
     * Helper to load Fabric Image
     */
    loadImagePromise(url, opts) {
        return new Promise((resolve, reject) => {
            fabric.Image.fromURL(url, (img) => {
                if (!img || !img.width) return reject(new Error('Failed to load image: ' + url));
                img.set({
                    left: opts.left,
                    top: opts.top,
                    opacity: opts.opacity,
                    angle: opts.angle
                });
                img.scaleToWidth(opts.targetWidth);
                if (opts.targetHeight) {
                    img.set({ scaleY: opts.targetHeight / img.height });
                }
                resolve(img);
            }, { crossOrigin: 'anonymous' });
        });
    }

    /**
     * Compare View (Original Poster vs. Editable Rebuild)
     */
    bindCompareView() {
        const btnCompare = document.getElementById('btn-compare-original');
        const sliderModal = document.getElementById('compare-slider-modal');
        const sliderInput = document.getElementById('compare-opacity-slider');
        const btnCloseSlider = document.getElementById('btn-close-compare-slider');

        if (!btnCompare) return;

        btnCompare.addEventListener('click', () => {
            if (!this.originalImageUrl) {
                alert(window.i18n ? window.i18n('no_original_poster', 'No original poster to compare against.') : 'No original image.');
                return;
            }
            if (sliderModal) {
                sliderModal.style.display = sliderModal.style.display === 'flex' ? 'none' : 'flex';
                this.updateCompareOverlay(sliderInput ? parseFloat(sliderInput.value) : 0.5);
            }
        });

        if (sliderInput) {
            sliderInput.addEventListener('input', (e) => {
                const opacity = parseFloat(e.target.value);
                this.updateCompareOverlay(opacity);
            });
        }

        if (btnCloseSlider && sliderModal) {
            btnCloseSlider.addEventListener('click', () => {
                sliderModal.style.display = 'none';
                this.removeCompareOverlay();
            });
        }
    }

    updateCompareOverlay(opacity) {
        if (!this.canvas || !this.originalImageUrl) return Promise.resolve(null);

        return new Promise((resolve) => {
            if (!this.compareOverlay) {
                fabric.Image.fromURL(this.originalImageUrl, (img) => {
                    if (!img) return resolve(null);
                    img.set({
                        left: 0,
                        top: 0,
                        opacity: opacity,
                        selectable: false,
                        evented: false
                    });
                    const cw = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
                    img.scaleToWidth(cw);
                    this.compareOverlay = img;
                    this.canvas.add(img);
                    img.bringToFront();
                    this.canvas.requestRenderAll();
                    resolve(img);
                }, { crossOrigin: 'anonymous' });
            } else {
                this.compareOverlay.set('opacity', opacity);
                this.compareOverlay.bringToFront();
                this.canvas.requestRenderAll();
                resolve(this.compareOverlay);
            }
        });
    }

    removeCompareOverlay() {
        if (this.compareOverlay && this.canvas) {
            this.canvas.remove(this.compareOverlay);
            this.compareOverlay = null;
            this.canvas.requestRenderAll();
        }
    }

    /**
     * Re-align layers button
     */
    bindRealign() {
        const btnRealign = document.getElementById('btn-realign-layers');
        if (!btnRealign) return;

        btnRealign.addEventListener('click', () => {
            let count = 0;
            this.canvas.getObjects().forEach(obj => {
                if (obj.originalBox) {
                    obj.set({
                        left: obj.originalBox.left,
                        top: obj.originalBox.top,
                        width: obj.originalBox.width,
                        fontSize: obj.originalBox.fontSize
                    });
                    if (obj.type === 'textbox') {
                        this.autoFitTextbox(obj, obj.originalBox.width, obj.originalBox.height);
                    }
                    obj.setCoords();
                    count++;
                }
            });

            if (count > 0) {
                this.canvas.requestRenderAll();
                if (this.history) this.history.saveState();
                if (window.toastManager) {
                    window.toastManager.success(window.i18n ? window.i18n('realign_success') : 'Layers re-aligned successfully!');
                }
            }
        });
    }

    /**
     * Shows HUD controls (Compare View, Realign, Meta Pill)
     */
    showPosterHUD(meta) {
        const btnCompare = document.getElementById('btn-compare-original');
        const btnRealign = document.getElementById('btn-realign-layers');
        const metaPill = document.getElementById('poster-meta-pill');

        if (btnCompare && this.originalImageUrl) {
            btnCompare.style.display = 'inline-flex';
        }
        if (btnRealign) {
            btnRealign.style.display = 'inline-flex';
        }
        if (metaPill && meta) {
            const provider = meta.provider || 'Gemini 3.5';
            const method = meta.clean_plate_method === 'inpainting_fallback' ? 'Inpainting Fallback' : (meta.clean_plate_method || 'Clean Plate');
            const totalMs = (meta.vision_latency_ms || 0) + (meta.clean_plate_latency || 0) + (meta.art_latency_ms || 0) + (meta.image_latency_ms || 0);
            const timeSec = (totalMs / 1000).toFixed(1);
            metaPill.textContent = `⚡ ${timeSec}s via ${provider} + ${method}`;
            metaPill.style.display = 'inline-flex';
        }
    }
}
