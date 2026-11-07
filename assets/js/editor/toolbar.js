/**
 * AI Design Studio - Editor Toolbar Component
 * Handles adding elements (Text, Shapes, Vector Icons, Uploads),
 * background customization (Color, Gradient, Pollinations.ai), and dimension presets.
 */

import { BUILTIN_ICONS } from './icons-data.js';
import { loadFonts, isArabic } from './font-manager.js';

let elemCounter = 0;

export class Toolbar {
    constructor(fabricCanvas, canvasManager, historyManager) {
        this.canvas = fabricCanvas;
        this.canvasManager = canvasManager;
        this.history = historyManager;

        this.init();
    }

    init() {
        this.bindAddText();
        this.bindAddShapes();
        this.bindIconsModal();
        this.bindImageUpload();
        this.bindBackgroundControls();
        this.bindDimensionPresets();
        this.bindZoomControls();
    }

    getCenterCoords(w = 200, h = 100) {
        const cw = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
        const ch = this.canvasManager ? this.canvasManager.virtualHeight : 1080;
        return {
            left: Math.max(0, (cw - w) / 2),
            top: Math.max(0, (ch - h) / 2)
        };
    }

    recordState() {
        if (this.history) this.history.saveState();
    }

    /**
     * Add Text Options
     */
    bindAddText() {
        const addHeading = document.getElementById('tool-add-heading');
        const addSubheading = document.getElementById('tool-add-subheading');
        const addBody = document.getElementById('tool-add-body');

        const insertText = (text, fontSize, fontWeight) => {
            const coords = this.getCenterCoords(300, 60);
            elemCounter++;

            const textbox = new fabric.Textbox(text, {
                left: coords.left,
                top: coords.top,
                width: 320,
                fontSize: fontSize,
                fontWeight: fontWeight,
                fontFamily: isArabic(text) ? 'Cairo' : 'Poppins',
                fill: '#0f172a',
                textAlign: 'center',
                name: `Text: ${text}`
            });

            this.canvas.add(textbox);
            this.canvas.setActiveObject(textbox);
            this.canvas.requestRenderAll();
            this.recordState();

            // Ensure fonts loaded in background
            loadFonts(['Poppins', 'Cairo']).then(() => {
                this.canvas.requestRenderAll();
            }).catch(() => {});
        };

        if (addHeading) addHeading.addEventListener('click', () => insertText('Add a Heading', 44, 'bold'));
        if (addSubheading) addSubheading.addEventListener('click', () => insertText('Add a Subheading', 28, '600'));
        if (addBody) addBody.addEventListener('click', () => insertText('Add body paragraph text here...', 18, 'normal'));
    }

    /**
     * Add Basic Shapes & Paths
     */
    bindAddShapes() {
        const addRect = document.getElementById('tool-add-rect');
        const addCircle = document.getElementById('tool-add-circle');
        const addTriangle = document.getElementById('tool-add-triangle');
        const addLine = document.getElementById('tool-add-line');
        const addStar = document.getElementById('tool-add-star');

        if (addRect) {
            addRect.addEventListener('click', () => {
                const coords = this.getCenterCoords(240, 160);
                const rect = new fabric.Rect({
                    left: coords.left,
                    top: coords.top,
                    width: 240,
                    height: 160,
                    fill: '#3b82f6',
                    rx: 8,
                    ry: 8,
                    name: 'Rectangle'
                });
                this.canvas.add(rect);
                this.canvas.setActiveObject(rect);
                this.canvas.requestRenderAll();
                this.recordState();
            });
        }

        if (addCircle) {
            addCircle.addEventListener('click', () => {
                const coords = this.getCenterCoords(180, 180);
                const circle = new fabric.Circle({
                    left: coords.left,
                    top: coords.top,
                    radius: 90,
                    fill: '#10b981',
                    name: 'Circle'
                });
                this.canvas.add(circle);
                this.canvas.setActiveObject(circle);
                this.canvas.requestRenderAll();
                this.recordState();
            });
        }

        if (addTriangle) {
            addTriangle.addEventListener('click', () => {
                const coords = this.getCenterCoords(180, 160);
                const tri = new fabric.Triangle({
                    left: coords.left,
                    top: coords.top,
                    width: 180,
                    height: 160,
                    fill: '#f59e0b',
                    name: 'Triangle'
                });
                this.canvas.add(tri);
                this.canvas.setActiveObject(tri);
                this.canvas.requestRenderAll();
                this.recordState();
            });
        }

        if (addLine) {
            addLine.addEventListener('click', () => {
                const coords = this.getCenterCoords(250, 4);
                const line = new fabric.Line([coords.left, coords.top, coords.left + 250, coords.top], {
                    stroke: '#64748b',
                    strokeWidth: 4,
                    name: 'Line'
                });
                this.canvas.add(line);
                this.canvas.setActiveObject(line);
                this.canvas.requestRenderAll();
                this.recordState();
            });
        }

        if (addStar) {
            addStar.addEventListener('click', () => {
                const coords = this.getCenterCoords(160, 160);
                // 5-point star polygon points
                const points = [
                    { x: 80, y: 0 },
                    { x: 104, y: 50 },
                    { x: 160, y: 58 },
                    { x: 120, y: 98 },
                    { x: 129, y: 154 },
                    { x: 80, y: 128 },
                    { x: 31, y: 154 },
                    { x: 40, y: 98 },
                    { x: 0, y: 58 },
                    { x: 56, y: 50 }
                ];
                const star = new fabric.Polygon(points, {
                    left: coords.left,
                    top: coords.top,
                    fill: '#eab308',
                    name: 'Star'
                });
                this.canvas.add(star);
                this.canvas.setActiveObject(star);
                this.canvas.requestRenderAll();
                this.recordState();
            });
        }
    }

    /**
     * Built-in Vector SVG Icons modal & insertion
     */
    bindIconsModal() {
        const openBtn = document.getElementById('tool-open-icons');
        const modal = document.getElementById('icons-modal');
        const grid = document.getElementById('icons-grid');
        const closeBtn = document.getElementById('close-icons-modal');

        if (!openBtn || !modal || !grid) return;

        // Render icons into modal grid once
        grid.innerHTML = '';
        BUILTIN_ICONS.forEach(iconDef => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'icon-grid-item';
            btn.title = iconDef.name;
            btn.innerHTML = `
                <div class="icon-preview-svg">${iconDef.svg}</div>
                <span class="icon-name">${iconDef.name}</span>
            `;

            btn.addEventListener('click', () => {
                fabric.loadSVGFromString(iconDef.svg, (objects, options) => {
                    const iconGroup = fabric.util.groupSVGElements(objects, options);
                    const coords = this.getCenterCoords(120, 120);

                    iconGroup.set({
                        left: coords.left,
                        top: coords.top,
                        scaleX: 120 / (iconGroup.width || 120),
                        scaleY: 120 / (iconGroup.height || 120),
                        name: `Icon: ${iconDef.name}`
                    });

                    this.canvas.add(iconGroup);
                    this.canvas.setActiveObject(iconGroup);
                    this.canvas.requestRenderAll();
                    this.recordState();
                    modal.style.display = 'none';
                });
            });

            grid.appendChild(btn);
        });

        openBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    /**
     * Image Upload via /api/upload.php
     */
    bindImageUpload() {
        const uploadBtn = document.getElementById('tool-upload-image');
        const fileInput = document.getElementById('tool-file-input');

        if (!uploadBtn || !fileInput) return;

        uploadBtn.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Reset input so same file can be selected again
            fileInput.value = '';

            // Check 5MB client-side limit
            if (file.size > 5 * 1024 * 1024) {
                alert('File size exceeds 5MB limit.');
                return;
            }

            const formData = new FormData();
            formData.append('image', file);

            uploadBtn.disabled = true;
            uploadBtn.textContent = '⏳ Uploading...';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const res = await fetch('api/upload.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': csrf
                    },
                    body: formData
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Failed to upload image');
                }

                const imageUrl = data.url;
                fabric.Image.fromURL(imageUrl, (img) => {
                    if (!img) {
                        alert('Could not render uploaded image.');
                        return;
                    }

                    const cw = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
                    const maxDim = cw * 0.5;

                    let scale = 1.0;
                    if (img.width > maxDim || img.height > maxDim) {
                        scale = Math.min(maxDim / img.width, maxDim / img.height);
                    }

                    img.set({
                        scaleX: scale,
                        scaleY: scale,
                        left: (cw - (img.width * scale)) / 2,
                        top: 100,
                        name: `Image: ${file.name.substring(0, 16)}`
                    });

                    this.canvas.add(img);
                    this.canvas.setActiveObject(img);
                    this.canvas.requestRenderAll();
                    this.recordState();
                }, { crossOrigin: 'anonymous' });

            } catch (err) {
                alert('Upload error: ' + err.message);
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.textContent = '🖼️ Upload Image';
            }
        });
    }

    /**
     * Background Controls (Solid, Gradient, Pollinations AI)
     */
    bindBackgroundControls() {
        const bgSolid = document.getElementById('bg-color-picker');
        const applyGradient = document.getElementById('bg-apply-gradient');
        const gradColor1 = document.getElementById('bg-grad-color1');
        const gradColor2 = document.getElementById('bg-grad-color2');
        const gradDir = document.getElementById('bg-grad-direction');

        const aiPromptInput = document.getElementById('bg-ai-prompt');
        const btnGenAiBg = document.getElementById('bg-btn-ai-gen');

        // Solid Color
        if (bgSolid) {
            bgSolid.addEventListener('input', (e) => {
                this.canvas.setBackgroundColor(e.target.value, () => {
                    this.canvas.requestRenderAll();
                });
            });
            bgSolid.addEventListener('change', () => this.recordState());
        }

        // Gradient
        if (applyGradient && gradColor1 && gradColor2 && gradDir) {
            applyGradient.addEventListener('click', () => {
                const c1 = gradColor1.value;
                const c2 = gradColor2.value;
                const dir = gradDir.value;
                const cw = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
                const ch = this.canvasManager ? this.canvasManager.virtualHeight : 1080;

                let coords = { x1: 0, y1: 0, x2: 0, y2: ch }; // vertical
                if (dir === 'horizontal') {
                    coords = { x1: 0, y1: 0, x2: cw, y2: 0 };
                } else if (dir === 'diagonal') {
                    coords = { x1: 0, y1: 0, x2: cw, y2: ch };
                }

                const gradient = new fabric.Gradient({
                    type: 'linear',
                    gradientUnits: 'pixels',
                    coords: coords,
                    colorStops: [
                        { offset: 0, color: c1 },
                        { offset: 1, color: c2 }
                    ]
                });

                this.canvas.setBackgroundColor(gradient, () => {
                    this.canvas.requestRenderAll();
                });
                this.recordState();
            });
        }

        // AI Background via Pollinations.ai proxied through /api/proxy-image.php
        if (btnGenAiBg && aiPromptInput) {
            btnGenAiBg.addEventListener('click', () => {
                const prompt = aiPromptInput.value.trim();
                if (!prompt) {
                    alert('Please enter a background prompt.');
                    return;
                }

                const cw = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
                const ch = this.canvasManager ? this.canvasManager.virtualHeight : 1080;

                const pollinationsUrl = `https://image.pollinations.ai/prompt/${encodeURIComponent(prompt)}?width=${cw}&height=${ch}&nologo=true`;
                const proxiedUrl = `api/proxy-image.php?url=${encodeURIComponent(pollinationsUrl)}`;

                btnGenAiBg.disabled = true;
                btnGenAiBg.textContent = '⏳ Generating...';

                fabric.Image.fromURL(proxiedUrl, (img) => {
                    btnGenAiBg.disabled = false;
                    btnGenAiBg.textContent = '✨ Generate AI Background';

                    if (!img) {
                        alert('Could not load generated background.');
                        return;
                    }

                    img.set({
                        scaleX: cw / img.width,
                        scaleY: ch / img.height,
                        originX: 'left',
                        originY: 'top'
                    });

                    this.canvas.setBackgroundImage(img, () => {
                        this.canvas.requestRenderAll();
                    });
                    this.recordState();
                }, { crossOrigin: 'anonymous' });
            });
        }
    }

    /**
     * Canvas Presets (Square, Story, YouTube, Facebook, A4, Custom)
     */
    bindDimensionPresets() {
        const presetSelect = document.getElementById('dimension-preset');
        const customW = document.getElementById('canvas-width');
        const customH = document.getElementById('canvas-height');
        const applyCustom = document.getElementById('btn-apply-dimensions');

        const presets = {
            square: { w: 1080, h: 1080 },
            story: { w: 1080, h: 1920 },
            youtube: { w: 1280, h: 720 },
            facebook: { w: 1200, h: 630 },
            a4: { w: 1240, h: 1754 }
        };

        const updateDim = (w, h) => {
            if (customW) customW.value = w;
            if (customH) customH.value = h;
            if (this.canvasManager) {
                this.canvasManager.setResolution(w, h);
            }
        };

        if (presetSelect) {
            presetSelect.addEventListener('change', () => {
                const val = presetSelect.value;
                if (presets[val]) {
                    updateDim(presets[val].w, presets[val].h);
                }
            });
        }

        if (applyCustom && customW && customH) {
            applyCustom.addEventListener('click', () => {
                const w = parseInt(customW.value, 10) || 1080;
                const h = parseInt(customH.value, 10) || 1080;
                updateDim(w, h);
            });
        }
    }

    /**
     * Zoom In / Out / Fit / Grid
     */
    bindZoomControls() {
        const btnIn = document.getElementById('btn-zoom-in');
        const btnOut = document.getElementById('btn-zoom-out');
        const btnFit = document.getElementById('btn-zoom-fit');
        const btnFitWidth = document.getElementById('btn-zoom-fit-width');
        const btnReset = document.getElementById('btn-zoom-reset');
        const btnGrid = document.getElementById('btn-toggle-grid');
        const btnFocus = document.getElementById('btn-focus-mode');
        const zoomPill = document.getElementById('zoom-percentage');

        // Viewport HUD buttons
        const hudIn = document.getElementById('hud-zoom-in');
        const hudOut = document.getElementById('hud-zoom-out');
        const hudFit = document.getElementById('hud-zoom-fit');
        const hudFitWidth = document.getElementById('hud-zoom-fit-width');
        const hudFocus = document.getElementById('hud-focus-mode');
        const hudVal = document.getElementById('hud-zoom-val');

        if (btnIn) btnIn.addEventListener('click', () => this.canvasManager?.zoomIn());
        if (btnOut) btnOut.addEventListener('click', () => this.canvasManager?.zoomOut());
        if (btnFit) btnFit.addEventListener('click', () => this.canvasManager?.fitToContainer());
        if (btnFitWidth) btnFitWidth.addEventListener('click', () => this.canvasManager?.fitToWidth());
        if (btnReset) btnReset.addEventListener('click', () => this.canvasManager?.zoomReset());
        if (btnFocus) btnFocus.addEventListener('click', () => this.canvasManager?.toggleFocusMode());

        if (hudIn) hudIn.addEventListener('click', () => this.canvasManager?.zoomIn());
        if (hudOut) hudOut.addEventListener('click', () => this.canvasManager?.zoomOut());
        if (hudFit) hudFit.addEventListener('click', () => this.canvasManager?.fitToContainer());
        if (hudFitWidth) hudFitWidth.addEventListener('click', () => this.canvasManager?.fitToWidth());
        if (hudFocus) hudFocus.addEventListener('click', () => this.canvasManager?.toggleFocusMode());
        if (hudVal) hudVal.addEventListener('click', () => this.canvasManager?.zoomReset());

        if (btnGrid) {
            btnGrid.addEventListener('click', () => {
                const active = this.canvasManager?.toggleGrid();
                btnGrid.classList.toggle('active', Boolean(active));
            });
        }

        window.addEventListener('canvas:zoom', (e) => {
            if (zoomPill) {
                zoomPill.textContent = `${e.detail.zoom}%`;
            }
            if (hudVal) {
                hudVal.textContent = `${e.detail.zoom}%`;
            }
        });
    }
}

