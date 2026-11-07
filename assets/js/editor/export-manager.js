/**
 * AI Design Studio - Advanced Export & Import Manager
 * Handles JPG (with quality slider & scale), PNG (with transparent background option),
 * SVG, JSON export, and JSON project import at full virtual resolution.
 */

import { renderDesignToFabric } from './ai-renderer.js';

export class ExportManager {
    constructor(fabricCanvas, canvasManager, historyManager) {
        this.canvas = fabricCanvas;
        this.canvasManager = canvasManager;
        this.history = historyManager;

        this.init();
    }

    init() {
        this.bindModalTriggers();
        this.bindExportActions();
        this.bindImportAction();
    }

    getProjectName() {
        const input = document.getElementById('project-name-input');
        const raw = input ? input.value.trim() : '';
        const safe = (raw || 'design').replace(/[^a-zA-Z0-9_\u0600-\u06FF-]/g, '_');
        return safe.substring(0, 50);
    }

    getMultiplier(scale = 1.0) {
        const zoom = this.canvasManager ? this.canvasManager.zoom : 1.0;
        return (scale / (zoom > 0 ? zoom : 1.0));
    }

    downloadURI(uri, filename) {
        const link = document.createElement('a');
        link.download = filename;
        link.href = uri;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    bindModalTriggers() {
        const btnOpenModal = document.getElementById('btn-open-export-modal');
        const modal = document.getElementById('export-modal');
        const btnClose = document.getElementById('close-export-modal');

        if (btnOpenModal && modal) {
            btnOpenModal.addEventListener('click', () => {
                this.updateDimensionsPreview();
                modal.style.display = 'flex';
            });
        }

        if (btnClose && modal) {
            btnClose.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }

        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.style.display = 'none';
            });
        }

        // Quality slider display
        const qualitySlider = document.getElementById('export-jpg-quality');
        const qualityVal = document.getElementById('export-quality-val');
        if (qualitySlider && qualityVal) {
            qualitySlider.addEventListener('input', () => {
                qualityVal.textContent = `${Math.round(qualitySlider.value * 100)}%`;
            });
        }

        // Scale multiplier listeners
        ['export-png-scale', 'export-jpg-scale'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', () => this.updateDimensionsPreview());
        });
    }

    updateDimensionsPreview() {
        const cw = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
        const ch = this.canvasManager ? this.canvasManager.virtualHeight : 1080;

        const pngScale = parseFloat(document.getElementById('export-png-scale')?.value || '1');
        const jpgScale = parseFloat(document.getElementById('export-jpg-scale')?.value || '1');

        const pngDim = document.getElementById('png-dimensions-preview');
        const jpgDim = document.getElementById('jpg-dimensions-preview');

        if (pngDim) pngDim.textContent = `${Math.round(cw * pngScale)} × ${Math.round(ch * pngScale)} px`;
        if (jpgDim) jpgDim.textContent = `${Math.round(cw * jpgScale)} × ${Math.round(ch * jpgScale)} px`;
    }

    bindExportActions() {
        const pngBtns = [document.getElementById('btn-export-png'), document.getElementById('btn-do-export-png')].filter(Boolean);
        const jpgBtns = [document.getElementById('btn-export-jpg'), document.getElementById('btn-do-export-jpg')].filter(Boolean);
        const svgBtns = [document.getElementById('btn-export-svg'), document.getElementById('btn-do-export-svg')].filter(Boolean);
        const jsonBtns = [document.getElementById('btn-export-json'), document.getElementById('btn-do-export-json')].filter(Boolean);

        pngBtns.forEach(b => b.addEventListener('click', () => this.exportPNG()));
        jpgBtns.forEach(b => b.addEventListener('click', () => this.exportJPEG()));
        svgBtns.forEach(b => b.addEventListener('click', () => this.exportSVG()));
        jsonBtns.forEach(b => b.addEventListener('click', () => this.exportJSON()));
    }

    async ensureFontsReady() {
        if ('fonts' in document) {
            await document.fonts.ready;
        }
    }

    async exportPNG() {
        const btn = document.getElementById('btn-export-png') || document.getElementById('btn-do-export-png');
        if (btn) { btn.disabled = true; btn.textContent = '⏳ Exporting...'; }

        await this.ensureFontsReady();

        const isTransparent = Boolean(document.getElementById('export-png-transparent')?.checked);
        const scale = parseFloat(document.getElementById('export-png-scale')?.value || '1');

        // Deselect objects to prevent rendering selection bounding box
        const active = this.canvas.getActiveObject();
        this.canvas.discardActiveObject();

        const origBg = this.canvas.backgroundColor;
        if (isTransparent) {
            this.canvas.setBackgroundColor(null, () => {});
        }
        this.canvas.requestRenderAll();

        const multiplier = this.getMultiplier(scale);
        const dataUrl = this.canvas.toDataURL({
            format: 'png',
            multiplier: multiplier
        });

        // Restore background & selection
        if (isTransparent) {
            this.canvas.setBackgroundColor(origBg, () => {});
        }
        if (active) {
            this.canvas.setActiveObject(active);
        }
        this.canvas.requestRenderAll();

        const projName = this.getProjectName();
        const suffix = scale > 1 ? `@${scale}x` : '';
        this.downloadURI(dataUrl, `${projName}${suffix}.png`);

        if (btn) { btn.disabled = false; btn.textContent = 'Download PNG'; }
    }

    async exportJPEG() {
        const btn = document.getElementById('btn-export-jpg') || document.getElementById('btn-do-export-jpg');
        if (btn) { btn.disabled = true; btn.textContent = '⏳ Exporting...'; }

        await this.ensureFontsReady();

        const quality = parseFloat(document.getElementById('export-jpg-quality')?.value || '0.92');
        const scale = parseFloat(document.getElementById('export-jpg-scale')?.value || '1');

        const active = this.canvas.getActiveObject();
        this.canvas.discardActiveObject();

        // JPEG has no transparency: ensure background is solid
        const origBg = this.canvas.backgroundColor;
        if (!origBg || origBg === 'transparent') {
            this.canvas.setBackgroundColor('#ffffff', () => {});
        }
        this.canvas.requestRenderAll();

        const multiplier = this.getMultiplier(scale);
        const dataUrl = this.canvas.toDataURL({
            format: 'jpeg',
            quality: Math.max(0.1, Math.min(1.0, quality)),
            multiplier: multiplier
        });

        if (!origBg || origBg === 'transparent') {
            this.canvas.setBackgroundColor(origBg, () => {});
        }
        if (active) {
            this.canvas.setActiveObject(active);
        }
        this.canvas.requestRenderAll();

        const projName = this.getProjectName();
        const suffix = scale > 1 ? `@${scale}x` : '';
        this.downloadURI(dataUrl, `${projName}${suffix}.jpg`);

        if (btn) { btn.disabled = false; btn.textContent = 'Download JPG'; }
    }

    async exportSVG() {
        await this.ensureFontsReady();

        const cw = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
        const ch = this.canvasManager ? this.canvasManager.virtualHeight : 1080;

        const svgContent = this.canvas.toSVG({
            width: cw,
            height: ch,
            viewBox: { x: 0, y: 0, width: cw, height: ch }
        });

        const blob = new Blob([svgContent], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const projName = this.getProjectName();

        this.downloadURI(url, `${projName}.svg`);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    exportJSON() {
        const json = this.canvas.toJSON([
            'id', 'name', 'customType', 'lockMovementX', 'lockMovementY',
            'selectable', 'evented', 'direction', 'rx', 'ry'
        ]);

        const projectPayload = {
            app: 'AI Design Studio',
            version: '2.0',
            exported_at: new Date().toISOString(),
            canvas: {
                width: this.canvasManager ? this.canvasManager.virtualWidth : 1080,
                height: this.canvasManager ? this.canvasManager.virtualHeight : 1080,
                background: this.canvas.backgroundColor || '#ffffff'
            },
            fabric: json
        };

        const str = JSON.stringify(projectPayload, null, 2);
        const blob = new Blob([str], { type: 'application/json;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const projName = this.getProjectName();

        this.downloadURI(url, `${projName}.project.json`);
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    /**
     * Import JSON project file into canvas
     */
    bindImportAction() {
        const fileInput = document.getElementById('input-import-json');
        const triggerBtn = document.getElementById('btn-import-json-trigger');

        if (triggerBtn && fileInput) {
            triggerBtn.addEventListener('click', () => fileInput.click());
        }

        if (fileInput) {
            fileInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;

                fileInput.value = '';

                try {
                    const text = await file.text();
                    const parsed = JSON.parse(text);

                    // Handle full project export or raw design schema
                    if (parsed.canvas && (parsed.objects || parsed.fabric)) {
                        const width = parsed.canvas.width || 1080;
                        const height = parsed.canvas.height || 1080;

                        if (this.canvasManager) {
                            this.canvasManager.setResolution(width, height);
                        }

                        if (parsed.objects) {
                            // AI schema format
                            await renderDesignToFabric(this.canvas, parsed, this.canvasManager);
                        } else if (parsed.fabric) {
                            // Fabric format
                            await new Promise((resolve) => {
                                this.canvas.loadFromJSON(parsed.fabric, () => {
                                    this.canvas.requestRenderAll();
                                    resolve();
                                });
                            });
                        }
                    } else if (parsed.objects) {
                        await renderDesignToFabric(this.canvas, parsed, this.canvasManager);
                    } else {
                        throw new Error('Unrecognized project JSON format.');
                    }

                    if (this.history) {
                        this.history.saveState();
                    }

                    // Set project name from file name
                    const nameInput = document.getElementById('project-name-input');
                    if (nameInput) {
                        nameInput.value = file.name.replace(/\.project\.json$|\.json$/i, '');
                    }

                    const modal = document.getElementById('export-modal');
                    if (modal) modal.style.display = 'none';

                    alert('Project imported successfully!');
                } catch (err) {
                    alert('Error importing project JSON: ' + err.message);
                }
            });
        }
    }
}
