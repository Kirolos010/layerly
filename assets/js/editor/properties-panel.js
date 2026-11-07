/**
 * AI Design Studio - Context-Aware Properties Panel
 * Controls typography, colors, borders, opacity, shadows, transforms, and alignment.
 */

import { ARABIC_FONTS, ENGLISH_FONTS, loadFonts } from './font-manager.js';

export class PropertiesPanel {
    constructor(containerId, fabricCanvas, canvasManager, historyManager) {
        this.container = document.getElementById(containerId);
        this.canvas = fabricCanvas;
        this.canvasManager = canvasManager;
        this.history = historyManager;

        if (!this.container || !this.canvas) return;

        this.setupListeners();
        this.render();
    }

    setupListeners() {
        const updatePanel = () => this.render();

        this.canvas.on('selection:created', updatePanel);
        this.canvas.on('selection:updated', updatePanel);
        this.canvas.on('selection:cleared', updatePanel);
        this.canvas.on('object:modified', updatePanel);
    }

    render() {
        if (!this.container) return;

        const activeObj = this.canvas.getActiveObject();

        if (!activeObj) {
            this.renderCanvasProperties();
            return;
        }

        if (activeObj.type === 'activeSelection') {
            this.renderMultiSelectionProperties(activeObj);
            return;
        }

        const isText = activeObj.type === 'textbox' || activeObj.type === 'text' || activeObj.type === 'i-text';
        const isRect = activeObj.type === 'rect';
        const isShape = isRect || activeObj.type === 'circle' || activeObj.type === 'triangle' || activeObj.type === 'polygon' || activeObj.type === 'path';
        const isLine = activeObj.type === 'line';
        const isImage = activeObj.type === 'image';

        let html = `
            <div class="props-header">
                <span class="props-type-badge">${this.escapeHtml(activeObj.name || activeObj.type.toUpperCase())}</span>
                <div class="props-header-actions">
                    <button type="button" class="btn-icon" id="prop-duplicate" title="Duplicate">📋</button>
                    <button type="button" class="btn-icon" id="prop-delete" title="Delete" style="color: var(--danger);">🗑️</button>
                </div>
            </div>

            <!-- Position & Size -->
            <div class="props-section">
                <div class="props-row-4">
                    <div class="prop-field">
                        <label>X</label>
                        <input type="number" id="prop-x" value="${Math.round(activeObj.left || 0)}">
                    </div>
                    <div class="prop-field">
                        <label>Y</label>
                        <input type="number" id="prop-y" value="${Math.round(activeObj.top || 0)}">
                    </div>
                    <div class="prop-field">
                        <label>W</label>
                        <input type="number" id="prop-w" value="${Math.round(activeObj.getScaledWidth())}" min="1">
                    </div>
                    <div class="prop-field">
                        <label>H</label>
                        <input type="number" id="prop-h" value="${Math.round(activeObj.getScaledHeight())}" min="1">
                    </div>
                </div>
                <div class="props-row-2" style="margin-top: 6px;">
                    <div class="prop-field">
                        <label>Angle (°)</label>
                        <input type="number" id="prop-angle" value="${Math.round(activeObj.angle || 0)}" min="-360" max="360">
                    </div>
                    <div class="prop-field">
                        <label>Opacity (%)</label>
                        <input type="number" id="prop-opacity" value="${Math.round((activeObj.opacity ?? 1) * 100)}" min="0" max="100">
                    </div>
                </div>
            </div>
        `;

        // Text specific controls
        if (isText) {
            const currentFont = activeObj.fontFamily || 'Poppins';
            const isBold = activeObj.fontWeight === 'bold' || activeObj.fontWeight === 700 || activeObj.fontWeight === '700';
            const isItalic = activeObj.fontStyle === 'italic';
            const isUnderline = Boolean(activeObj.underline);
            const isRTL = activeObj.direction === 'rtl';

            html += `
                <div class="props-section">
                    <div class="props-section-title">🔤 Typography</div>
                    
                    <div class="prop-field">
                        <label>Text Content</label>
                        <textarea id="prop-text-content" rows="2" style="resize: vertical;">${this.escapeHtml(activeObj.text || '')}</textarea>
                    </div>

                    <div class="props-row-2">
                        <div class="prop-field">
                            <label>Font Family</label>
                            <select id="prop-font-family">
                                <optgroup label="Arabic Fonts">
                                    ${ARABIC_FONTS.map(f => `<option value="${f}" ${f.toLowerCase() === currentFont.toLowerCase() ? 'selected' : ''}>${f}</option>`).join('')}
                                </optgroup>
                                <optgroup label="Latin / English Fonts">
                                    ${ENGLISH_FONTS.map(f => `<option value="${f}" ${f.toLowerCase() === currentFont.toLowerCase() ? 'selected' : ''}>${f}</option>`).join('')}
                                </optgroup>
                            </select>
                        </div>
                        <div class="prop-field">
                            <label>Font Size (px)</label>
                            <input type="number" id="prop-font-size" value="${Math.round(activeObj.fontSize || 24)}" min="8" max="300">
                        </div>
                    </div>

                    <div class="props-button-group" style="margin-top: 6px;">
                        <button type="button" class="btn-toggle ${isBold ? 'active' : ''}" id="prop-btn-bold" title="Bold"><strong>B</strong></button>
                        <button type="button" class="btn-toggle ${isItalic ? 'active' : ''}" id="prop-btn-italic" title="Italic"><em>I</em></button>
                        <button type="button" class="btn-toggle ${isUnderline ? 'active' : ''}" id="prop-btn-underline" title="Underline"><u>U</u></button>
                        <button type="button" class="btn-toggle ${isRTL ? 'active' : ''}" id="prop-btn-rtl" title="RTL Direction">↔ RTL</button>
                    </div>

                    <div class="props-button-group" style="margin-top: 6px;">
                        <button type="button" class="btn-toggle ${activeObj.textAlign === 'left' ? 'active' : ''}" id="prop-align-left" title="Align Left">⯬</button>
                        <button type="button" class="btn-toggle ${activeObj.textAlign === 'center' ? 'active' : ''}" id="prop-align-center" title="Align Center">☰</button>
                        <button type="button" class="btn-toggle ${activeObj.textAlign === 'right' ? 'active' : ''}" id="prop-align-right" title="Align Right">⯮</button>
                        <button type="button" class="btn-toggle ${activeObj.textAlign === 'justify' ? 'active' : ''}" id="prop-align-justify" title="Justify">𝄘</button>
                    </div>

                    <div class="props-row-2" style="margin-top: 8px;">
                        <div class="prop-field">
                            <label>Text Color</label>
                            <input type="color" id="prop-fill-color" value="${this.sanitizeColor(activeObj.fill)}">
                        </div>
                        <div class="prop-field">
                            <label>Line Height</label>
                            <input type="number" id="prop-line-height" value="${activeObj.lineHeight || 1.16}" step="0.1" min="0.5" max="3">
                        </div>
                    </div>
                </div>
            `;
        }

        // Shape / Color controls
        if (isShape && !isText) {
            html += `
                <div class="props-section">
                    <div class="props-section-title">🎨 Appearance</div>
                    <div class="props-row-2">
                        <div class="prop-field">
                            <label>Fill Color</label>
                            <input type="color" id="prop-fill-color" value="${this.sanitizeColor(activeObj.fill)}">
                        </div>
                        <div class="prop-field">
                            <label>Stroke Color</label>
                            <input type="color" id="prop-stroke-color" value="${this.sanitizeColor(activeObj.stroke || '#000000')}">
                        </div>
                    </div>
                    <div class="props-row-2" style="margin-top: 6px;">
                        <div class="prop-field">
                            <label>Stroke Width</label>
                            <input type="number" id="prop-stroke-width" value="${activeObj.strokeWidth || 0}" min="0" max="50">
                        </div>
                        ${isRect ? `
                        <div class="prop-field">
                            <label>Corner Radius</label>
                            <input type="number" id="prop-corner-radius" value="${activeObj.rx || 0}" min="0" max="200">
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // Line controls
        if (isLine) {
            html += `
                <div class="props-section">
                    <div class="props-section-title">➖ Line Styling</div>
                    <div class="props-row-2">
                        <div class="prop-field">
                            <label>Line Color</label>
                            <input type="color" id="prop-stroke-color" value="${this.sanitizeColor(activeObj.stroke || '#000000')}">
                        </div>
                        <div class="prop-field">
                            <label>Line Thickness</label>
                            <input type="number" id="prop-stroke-width" value="${activeObj.strokeWidth || 2}" min="1" max="50">
                        </div>
                    </div>
                </div>
            `;
        }

        // Image specific controls
        if (isImage) {
            html += `
                <div class="props-section">
                    <div class="props-section-title">🖼️ ${window.i18n ? window.i18n('image_tools', 'Image & AI Tools') : 'Image & AI Tools'}</div>
                    <div class="props-button-group" style="margin-bottom: 0.6rem;">
                        <button type="button" class="btn-toggle ${activeObj.flipX ? 'active' : ''}" id="prop-flip-x">${window.i18n ? window.i18n('flip_h', 'Flip H ⮂') : 'Flip H ⮂'}</button>
                        <button type="button" class="btn-toggle ${activeObj.flipY ? 'active' : ''}" id="prop-flip-y">${window.i18n ? window.i18n('flip_v', 'Flip V ⮃') : 'Flip V ⮃'}</button>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.45rem; padding-top: 0.5rem; border-top: 1px solid var(--border-subtle);">
                        <button type="button" class="btn btn-sm btn-primary" id="btn-separate-subject" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.55rem;">
                            <span>✨</span> <span>${window.i18n ? window.i18n('separate_main_subject', 'Separate Main Subject') : 'Separate Main Subject'}</span>
                        </button>

                        <button type="button" class="btn btn-sm btn-secondary" id="btn-extract-text" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.55rem;">
                            <span>📝</span> <span>${window.i18n ? window.i18n('extract_text_from_image', 'Extract Text from Image') : 'Extract Text from Image'}</span>
                        </button>

                        <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-muted); cursor: pointer; margin-top: 2px;">
                            <input type="checkbox" id="check-mask-text" checked style="width: auto; cursor: pointer;">
                            <span>${window.i18n ? window.i18n('mask_original_text', 'Mask original text areas') : 'Mask original text areas'}</span>
                        </label>
                    </div>
                </div>
            `;
        }

        // Canvas alignment controls (Center, Top, Bottom, etc.)
        html += `
            <div class="props-section">
                <div class="props-section-title">📐 Canvas Align</div>
                <div class="props-button-group">
                    <button type="button" class="btn-sm" id="prop-align-canvas-left" title="Align to Left Edge">Left</button>
                    <button type="button" class="btn-sm" id="prop-align-canvas-center-h" title="Center Horizontally">Center H</button>
                    <button type="button" class="btn-sm" id="prop-align-canvas-right" title="Align to Right Edge">Right</button>
                </div>
                <div class="props-button-group" style="margin-top: 4px;">
                    <button type="button" class="btn-sm" id="prop-align-canvas-top" title="Align to Top Edge">Top</button>
                    <button type="button" class="btn-sm" id="prop-align-canvas-center-v" title="Center Vertically">Center V</button>
                    <button type="button" class="btn-sm" id="prop-align-canvas-bottom" title="Align to Bottom Edge">Bottom</button>
                </div>
            </div>

            <!-- Layer Order Actions -->
            <div class="props-section">
                <div class="props-section-title">📑 Layer Order</div>
                <div class="props-button-group">
                    <button type="button" class="btn-sm" id="prop-layer-front" title="Bring to Front">To Front</button>
                    <button type="button" class="btn-sm" id="prop-layer-forward" title="Bring Forward">Forward</button>
                    <button type="button" class="btn-sm" id="prop-layer-backward" title="Send Backward">Backward</button>
                    <button type="button" class="btn-sm" id="prop-layer-back" title="Send to Back">To Back</button>
                </div>
            </div>
        `;

        this.container.innerHTML = html;
        this.bindEvents(activeObj);
    }

    renderCanvasProperties() {
        const bg = this.canvas.backgroundColor || '#ffffff';
        const w = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
        const h = this.canvasManager ? this.canvasManager.virtualHeight : 1080;

        this.container.innerHTML = `
            <div class="props-header">
                <span class="props-type-badge">Canvas Background</span>
            </div>
            <div class="props-section">
                <div class="prop-field">
                    <label>Canvas Resolution</label>
                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-heading); margin-top: 4px;">
                        ${w} × ${h} px
                    </div>
                </div>

                <div class="prop-field" style="margin-top: 12px;">
                    <label>Background Color</label>
                    <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                        <input type="color" id="prop-canvas-bg" value="${this.sanitizeColor(bg)}">
                        <span style="font-size: 0.85rem; font-mono; color: var(--text-muted);">${typeof bg === 'string' ? bg : 'Gradient/Pattern'}</span>
                    </div>
                </div>
            </div>

            <div class="props-section">
                <div class="props-section-title">💡 Tip</div>
                <p style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.4; margin: 0;">
                    Click any element on the canvas to edit its typography, colors, position, borders, and layers.
                </p>
            </div>
        `;

        const bgInput = this.container.querySelector('#prop-canvas-bg');
        if (bgInput) {
            bgInput.addEventListener('input', (e) => {
                this.canvas.setBackgroundColor(e.target.value, () => {
                    this.canvas.requestRenderAll();
                });
            });
            bgInput.addEventListener('change', () => {
                if (this.history) this.history.saveState();
            });
        }
    }

    renderMultiSelectionProperties(activeSelection) {
        this.container.innerHTML = `
            <div class="props-header">
                <span class="props-type-badge">Multiple Selected (${activeSelection.size()})</span>
                <div class="props-header-actions">
                    <button type="button" class="btn-icon" id="prop-delete" title="Delete All" style="color: var(--danger);">🗑️</button>
                </div>
            </div>
            <div class="props-section">
                <div class="props-button-group">
                    <button type="button" class="btn btn-sm btn-primary" id="prop-group-btn" style="flex: 1;">📁 Group Elements</button>
                </div>
                <div class="props-button-group" style="margin-top: 8px;">
                    <button type="button" class="btn-sm" id="prop-align-canvas-center-h" style="flex: 1;">Center H</button>
                    <button type="button" class="btn-sm" id="prop-align-canvas-center-v" style="flex: 1;">Center V</button>
                </div>
            </div>
        `;

        const groupBtn = this.container.querySelector('#prop-group-btn');
        if (groupBtn) {
            groupBtn.addEventListener('click', () => {
                if (!this.canvas.getActiveObject() || this.canvas.getActiveObject().type !== 'activeSelection') return;
                const group = this.canvas.getActiveObject().toGroup();
                group.name = 'Group';
                this.canvas.requestRenderAll();
                if (this.history) this.history.saveState();
                this.render();
            });
        }

        const delBtn = this.container.querySelector('#prop-delete');
        if (delBtn) {
            delBtn.addEventListener('click', () => {
                const objs = activeSelection.getObjects();
                this.canvas.discardActiveObject();
                objs.forEach(o => this.canvas.remove(o));
                this.canvas.requestRenderAll();
                if (this.history) this.history.saveState();
                this.render();
            });
        }
    }

    bindEvents(obj) {
        const c = this.container;
        const onChange = () => {
            this.canvas.requestRenderAll();
            if (this.history) this.history.saveState();
        };

        // Position inputs
        const xInput = c.querySelector('#prop-x');
        const yInput = c.querySelector('#prop-y');
        const wInput = c.querySelector('#prop-w');
        const hInput = c.querySelector('#prop-h');
        const angleInput = c.querySelector('#prop-angle');
        const opacityInput = c.querySelector('#prop-opacity');

        if (xInput) xInput.addEventListener('change', () => { obj.set('left', parseFloat(xInput.value) || 0); onChange(); });
        if (yInput) yInput.addEventListener('change', () => { obj.set('top', parseFloat(yInput.value) || 0); onChange(); });
        if (wInput) wInput.addEventListener('change', () => {
            const w = Math.max(1, parseFloat(wInput.value) || 10);
            if (obj.type === 'textbox') {
                obj.set('width', w);
            } else {
                obj.scaleToWidth(w);
            }
            onChange();
        });
        if (hInput) hInput.addEventListener('change', () => {
            const h = Math.max(1, parseFloat(hInput.value) || 10);
            if (obj.type !== 'textbox') {
                obj.scaleToHeight(h);
            }
            onChange();
        });
        if (angleInput) angleInput.addEventListener('change', () => { obj.set('angle', parseFloat(angleInput.value) || 0); onChange(); });
        if (opacityInput) opacityInput.addEventListener('input', () => {
            const val = Math.max(0, Math.min(100, parseInt(opacityInput.value, 10) || 100));
            obj.set('opacity', val / 100);
            this.canvas.requestRenderAll();
        });
        if (opacityInput) opacityInput.addEventListener('change', onChange);

        // Duplicate
        const dupBtn = c.querySelector('#prop-duplicate');
        if (dupBtn) dupBtn.addEventListener('click', () => {
            obj.clone((cloned) => {
                cloned.set({
                    left: obj.left + 20,
                    top: obj.top + 20,
                    name: `${obj.name || 'Object'} (Copy)`
                });
                this.canvas.add(cloned);
                this.canvas.setActiveObject(cloned);
                onChange();
            });
        });

        // Delete
        const delBtn = c.querySelector('#prop-delete');
        if (delBtn) delBtn.addEventListener('click', () => {
            this.canvas.remove(obj);
            this.canvas.discardActiveObject();
            onChange();
        });

        // Typography events
        const textContent = c.querySelector('#prop-text-content');
        if (textContent) {
            textContent.addEventListener('input', () => {
                obj.set('text', textContent.value);
                this.canvas.requestRenderAll();
            });
            textContent.addEventListener('change', onChange);
        }

        const fontSelect = c.querySelector('#prop-font-family');
        if (fontSelect) {
            fontSelect.addEventListener('change', async () => {
                const font = fontSelect.value;
                await loadFonts([font]);
                obj.set('fontFamily', font);
                onChange();
            });
        }

        const fontSize = c.querySelector('#prop-font-size');
        if (fontSize) {
            fontSize.addEventListener('input', () => {
                obj.set('fontSize', Math.max(8, parseInt(fontSize.value, 10) || 24));
                this.canvas.requestRenderAll();
            });
            fontSize.addEventListener('change', onChange);
        }

        const boldBtn = c.querySelector('#prop-btn-bold');
        if (boldBtn) boldBtn.addEventListener('click', () => {
            const isBold = obj.fontWeight === 'bold' || obj.fontWeight === 700;
            obj.set('fontWeight', isBold ? 'normal' : 'bold');
            boldBtn.classList.toggle('active', !isBold);
            onChange();
        });

        const italicBtn = c.querySelector('#prop-btn-italic');
        if (italicBtn) italicBtn.addEventListener('click', () => {
            const isItalic = obj.fontStyle === 'italic';
            obj.set('fontStyle', isItalic ? 'normal' : 'italic');
            italicBtn.classList.toggle('active', !isItalic);
            onChange();
        });

        const underlineBtn = c.querySelector('#prop-btn-underline');
        if (underlineBtn) underlineBtn.addEventListener('click', () => {
            const isUnderline = Boolean(obj.underline);
            obj.set('underline', !isUnderline);
            underlineBtn.classList.toggle('active', !isUnderline);
            onChange();
        });

        const rtlBtn = c.querySelector('#prop-btn-rtl');
        if (rtlBtn) rtlBtn.addEventListener('click', () => {
            const isRTL = obj.direction === 'rtl';
            obj.set({
                direction: isRTL ? 'ltr' : 'rtl',
                textAlign: isRTL ? 'left' : 'right'
            });
            rtlBtn.classList.toggle('active', !isRTL);
            onChange();
            this.render();
        });

        ['left', 'center', 'right', 'justify'].forEach(align => {
            const btn = c.querySelector(`#prop-align-${align}`);
            if (btn) btn.addEventListener('click', () => {
                obj.set('textAlign', align);
                onChange();
                this.render();
            });
        });

        const lineHeight = c.querySelector('#prop-line-height');
        if (lineHeight) {
            lineHeight.addEventListener('input', () => {
                obj.set('lineHeight', parseFloat(lineHeight.value) || 1.16);
                this.canvas.requestRenderAll();
            });
            lineHeight.addEventListener('change', onChange);
        }

        // Colors
        const fillInput = c.querySelector('#prop-fill-color');
        if (fillInput) {
            fillInput.addEventListener('input', () => {
                obj.set('fill', fillInput.value);
                this.canvas.requestRenderAll();
            });
            fillInput.addEventListener('change', onChange);
        }

        const strokeInput = c.querySelector('#prop-stroke-color');
        if (strokeInput) {
            strokeInput.addEventListener('input', () => {
                obj.set('stroke', strokeInput.value);
                if (!obj.strokeWidth) obj.set('strokeWidth', 2);
                this.canvas.requestRenderAll();
            });
            strokeInput.addEventListener('change', onChange);
        }

        const strokeWidth = c.querySelector('#prop-stroke-width');
        if (strokeWidth) {
            strokeWidth.addEventListener('input', () => {
                obj.set('strokeWidth', parseInt(strokeWidth.value, 10) || 0);
                this.canvas.requestRenderAll();
            });
            strokeWidth.addEventListener('change', onChange);
        }

        const cornerRadius = c.querySelector('#prop-corner-radius');
        if (cornerRadius) {
            cornerRadius.addEventListener('input', () => {
                const rx = parseInt(cornerRadius.value, 10) || 0;
                obj.set({ rx, ry: rx });
                this.canvas.requestRenderAll();
            });
            cornerRadius.addEventListener('change', onChange);
        }

        // Flip image
        const flipX = c.querySelector('#prop-flip-x');
        if (flipX) flipX.addEventListener('click', () => {
            obj.set('flipX', !obj.flipX);
            flipX.classList.toggle('active', obj.flipX);
            onChange();
        });

        const flipY = c.querySelector('#prop-flip-y');
        if (flipY) flipY.addEventListener('click', () => {
            obj.set('flipY', !obj.flipY);
            flipY.classList.toggle('active', obj.flipY);
            onChange();
        });

        // Separate Main Subject (Hugging Face RMBG)
        const btnSeparate = c.querySelector('#btn-separate-subject');
        if (btnSeparate) {
            btnSeparate.addEventListener('click', async () => {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.content : '';

                const origHtml = btnSeparate.innerHTML;
                btnSeparate.disabled = true;
                btnSeparate.innerHTML = `<span>⏳</span> <span>${window.i18n ? window.i18n('separating_subject', 'Separating subject...') : 'Separating subject...'}</span>`;

                window.dispatchEvent(new CustomEvent('toast:show', {
                    detail: {
                        message: window.i18n ? window.i18n('separating_subject', 'Separating main subject with AI...') : 'Separating main subject with AI...',
                        type: 'info'
                    }
                }));

                try {
                    const dataUrl = obj.toDataURL({ format: 'png' });

                    const resp = await fetch('api/remove-bg.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ image_data: dataUrl })
                    });

                    const res = await resp.json();

                    if (!res.success) {
                        throw new Error(res.error || (window.i18n ? window.i18n('rmbg_failed') : 'Background removal failed'));
                    }

                    const cutoutSrc = res.data_url || res.image_url;
                    fabric.Image.fromURL(cutoutSrc, (cutout) => {
                        if (!cutout || !cutout.width) {
                            window.dispatchEvent(new CustomEvent('toast:show', {
                                detail: { message: window.i18n ? window.i18n('rmbg_failed') : 'Failed to load cutout', type: 'error' }
                            }));
                            return;
                        }

                        cutout.set({
                            left: obj.left + 24,
                            top: obj.top + 24,
                            scaleX: obj.scaleX,
                            scaleY: obj.scaleY,
                            angle: obj.angle,
                            opacity: obj.opacity
                        });
                        cutout.name = `${obj.name || 'Image'} (Subject)`;
                        cutout.customType = 'image';

                        this.canvas.add(cutout);
                        this.canvas.setActiveObject(cutout);
                        this.canvas.requestRenderAll();

                        if (this.history) {
                            this.history.recordChange();
                        }
                        window.dispatchEvent(new CustomEvent('canvas:objects-updated'));

                        window.dispatchEvent(new CustomEvent('toast:show', {
                            detail: {
                                message: res.message || (window.i18n ? window.i18n('subject_separated_success') : 'Subject separated!'),
                                type: 'success'
                            }
                        }));
                    }, { crossOrigin: 'anonymous' });

                } catch (err) {
                    window.dispatchEvent(new CustomEvent('toast:show', {
                        detail: { message: err.message, type: 'error' }
                    }));
                } finally {
                    btnSeparate.disabled = false;
                    btnSeparate.innerHTML = origHtml;
                }
            });
        }

        // Extract Text from Image (Gemini Vision OCR)
        const btnExtract = c.querySelector('#btn-extract-text');
        if (btnExtract) {
            btnExtract.addEventListener('click', async () => {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.content : '';
                const maskCheckbox = c.querySelector('#check-mask-text');
                const shouldMask = maskCheckbox ? maskCheckbox.checked : false;

                const origHtml = btnExtract.innerHTML;
                btnExtract.disabled = true;
                btnExtract.innerHTML = `<span>⏳</span> <span>${window.i18n ? window.i18n('extracting_text', 'Extracting text...') : 'Extracting text...'}</span>`;

                window.dispatchEvent(new CustomEvent('toast:show', {
                    detail: {
                        message: window.i18n ? window.i18n('extracting_text', 'Extracting editable text with AI Vision...') : 'Extracting editable text with AI Vision...',
                        type: 'info'
                    }
                }));

                try {
                    const dataUrl = obj.toDataURL({ format: 'jpeg', quality: 0.85 });

                    const resp = await fetch('api/extract-text.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            image_data: dataUrl,
                            width: Math.round(obj.getScaledWidth()),
                            height: Math.round(obj.getScaledHeight())
                        })
                    });

                    const res = await resp.json();

                    if (!res.success) {
                        throw new Error(res.error || (window.i18n ? window.i18n('no_text_detected') : 'No text detected'));
                    }

                    const blocks = res.text_blocks || [];
                    if (blocks.length === 0) {
                        window.dispatchEvent(new CustomEvent('toast:show', {
                            detail: { message: window.i18n ? window.i18n('no_text_detected') : 'No text detected', type: 'info' }
                        }));
                        return;
                    }

                    const fontsToPreload = blocks.map(b => b.fontFamily).filter(Boolean);
                    if (fontsToPreload.length > 0) {
                        await loadFonts(fontsToPreload);
                    }

                    const scaledW = obj.getScaledWidth();
                    const scaledH = obj.getScaledHeight();

                    blocks.forEach((block, idx) => {
                        const blockLeft = obj.left + (block.left_percent / 100) * scaledW;
                        const blockTop = obj.top + (block.top_percent / 100) * scaledH;
                        const blockWidth = Math.max(50, (block.width_percent / 100) * scaledW);
                        const blockHeight = Math.max(20, (block.height_percent / 100) * scaledH);

                        if (shouldMask) {
                            const maskRect = new fabric.Rect({
                                left: blockLeft - 4,
                                top: blockTop - 2,
                                width: blockWidth + 8,
                                height: blockHeight + 6,
                                fill: '#ffffff',
                                opacity: 0.85,
                                rx: 4,
                                ry: 4
                            });
                            maskRect.name = `Mask: ${block.text.substring(0, 10)}`;
                            maskRect.customType = 'rect';
                            this.canvas.add(maskRect);
                        }

                        const isAr = block.language === 'ar';
                        const textObj = new fabric.Textbox(block.text, {
                            left: blockLeft,
                            top: blockTop,
                            width: blockWidth,
                            fontSize: block.fontSize || 28,
                            fontFamily: block.fontFamily || (isAr ? 'Cairo' : 'Poppins'),
                            fontWeight: block.fontWeight || 'normal',
                            fill: block.fill || (isAr ? '#1e293b' : '#0f172a'),
                            direction: isAr ? 'rtl' : 'ltr',
                            textAlign: isAr ? 'right' : 'left',
                            splitByGrapheme: true,
                            editable: true
                        });
                        textObj.name = `Text: ${block.text.substring(0, 15)}...`;
                        textObj.customType = 'text';

                        this.canvas.add(textObj);
                        if (idx === 0) {
                            this.canvas.setActiveObject(textObj);
                        }
                    });

                    this.canvas.requestRenderAll();
                    if (this.history) {
                        this.history.recordChange();
                    }
                    window.dispatchEvent(new CustomEvent('canvas:objects-updated'));

                    window.dispatchEvent(new CustomEvent('toast:show', {
                        detail: {
                            message: res.message || (window.i18n ? window.i18n('text_extracted_success') : 'Text extracted!'),
                            type: 'success'
                        }
                    }));

                } catch (err) {
                    window.dispatchEvent(new CustomEvent('toast:show', {
                        detail: { message: err.message, type: 'error' }
                    }));
                } finally {
                    btnExtract.disabled = false;
                    btnExtract.innerHTML = origHtml;
                }
            });
        }

        // Canvas Align
        const cw = this.canvasManager ? this.canvasManager.virtualWidth : 1080;
        const ch = this.canvasManager ? this.canvasManager.virtualHeight : 1080;

        const alignL = c.querySelector('#prop-align-canvas-left');
        if (alignL) alignL.addEventListener('click', () => { obj.set('left', 0); onChange(); });

        const alignCH = c.querySelector('#prop-align-canvas-center-h');
        if (alignCH) alignCH.addEventListener('click', () => {
            obj.set('left', (cw - obj.getScaledWidth()) / 2);
            onChange();
        });

        const alignR = c.querySelector('#prop-align-canvas-right');
        if (alignR) alignR.addEventListener('click', () => {
            obj.set('left', cw - obj.getScaledWidth());
            onChange();
        });

        const alignT = c.querySelector('#prop-align-canvas-top');
        if (alignT) alignT.addEventListener('click', () => { obj.set('top', 0); onChange(); });

        const alignCV = c.querySelector('#prop-align-canvas-center-v');
        if (alignCV) alignCV.addEventListener('click', () => {
            obj.set('top', (ch - obj.getScaledHeight()) / 2);
            onChange();
        });

        const alignB = c.querySelector('#prop-align-canvas-bottom');
        if (alignB) alignB.addEventListener('click', () => {
            obj.set('top', ch - obj.getScaledHeight());
            onChange();
        });

        // Layer Ordering
        const lFront = c.querySelector('#prop-layer-front');
        if (lFront) lFront.addEventListener('click', () => { obj.bringToFront(); onChange(); });

        const lFwd = c.querySelector('#prop-layer-forward');
        if (lFwd) lFwd.addEventListener('click', () => { obj.bringForward(); onChange(); });

        const lBack = c.querySelector('#prop-layer-backward');
        if (lBack) lBack.addEventListener('click', () => { obj.sendBackwards(); onChange(); });

        const lToBack = c.querySelector('#prop-layer-back');
        if (lToBack) lToBack.addEventListener('click', () => { obj.sendToBack(); onChange(); });
    }

    sanitizeColor(color) {
        if (!color || typeof color !== 'string') return '#000000';
        if (color.startsWith('#') && (color.length === 7 || color.length === 4)) {
            return color.length === 4 ? `#${color[1]}${color[1]}${color[2]}${color[2]}${color[3]}${color[3]}` : color;
        }
        return '#3b82f6';
    }

    escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}

