/**
 * AI Design Studio - AI Design to Fabric.js Renderer
 * Translates structured JSON schema into interactive Fabric.js canvas objects.
 */

import { loadFonts, isArabic } from './font-manager.js';

let objectCounter = 0;

export async function renderDesignToFabric(fabricCanvas, designData, canvasManager) {
    if (!fabricCanvas || !designData || !designData.canvas) return;

    // 1. Update Canvas Dimensions
    const width = designData.canvas.width || 1080;
    const height = designData.canvas.height || 1080;
    if (canvasManager) {
        canvasManager.setResolution(width, height);
    }

    // 2. Clear Existing Canvas
    fabricCanvas.clear();

    // 3. Set Canvas Background
    const bg = designData.canvas.background || '#ffffff';
    fabricCanvas.setBackgroundColor(bg, () => {
        fabricCanvas.requestRenderAll();
    });

    // 4. Preload all fonts used in the design
    const fontsToLoad = [];
    const objects = designData.objects || [];
    objects.forEach(obj => {
        if (obj.type === 'text' && obj.fontFamily) {
            fontsToLoad.push(obj.fontFamily);
        }
    });

    if (fontsToLoad.length > 0) {
        await loadFonts(fontsToLoad);
    }

    // 5. Render elements in schema order
    for (const obj of objects) {
        objectCounter++;
        const objId = `elem_${Date.now()}_${objectCounter}`;

        const left = typeof obj.left === 'number' ? obj.left : 0;
        const top = typeof obj.top === 'number' ? obj.top : 0;
        const width = typeof obj.width === 'number' ? Math.max(5, obj.width) : 100;
        const height = typeof obj.height === 'number' ? Math.max(5, obj.height) : 100;
        const opacity = typeof obj.opacity === 'number' ? obj.opacity : 1.0;
        const angle = typeof obj.angle === 'number' ? obj.angle : 0;

        let fabricObj = null;

        switch (obj.type) {
            case 'text': {
                const textStr = obj.text || 'Text Element';
                const arabic = isArabic(textStr);
                const fontFamily = obj.fontFamily || (arabic ? 'Cairo' : 'Poppins');
                const direction = obj.direction || (arabic ? 'rtl' : 'ltr');
                const textAlign = obj.textAlign || (direction === 'rtl' ? 'right' : 'left');

                fabricObj = new fabric.Textbox(textStr, {
                    left: left,
                    top: top,
                    width: width,
                    fontSize: Math.max(10, obj.fontSize || 28),
                    fontFamily: fontFamily,
                    fontWeight: obj.fontWeight || 'normal',
                    fontStyle: obj.fontStyle || 'normal',
                    fill: obj.fill || (arabic ? '#1e293b' : '#0f172a'),
                    textAlign: textAlign,
                    direction: direction,
                    stroke: (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : null,
                    strokeWidth: obj.strokeWidth || 0,
                    opacity: opacity,
                    angle: angle,
                    splitByGrapheme: true,
                    editable: true
                });
                fabricObj.name = `Text: ${textStr.substring(0, 15)}...`;
                fabricObj.customType = 'text';
                break;
            }

            case 'rect': {
                fabricObj = new fabric.Rect({
                    left: left,
                    top: top,
                    width: width,
                    height: height,
                    fill: obj.fill || '#3b82f6',
                    stroke: (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : null,
                    strokeWidth: obj.strokeWidth || 0,
                    rx: obj.rx || 0,
                    ry: obj.rx || 0,
                    opacity: opacity,
                    angle: angle
                });
                fabricObj.name = `Rectangle`;
                fabricObj.customType = 'rect';
                break;
            }

            case 'circle': {
                const radius = Math.min(width, height) / 2;
                fabricObj = new fabric.Circle({
                    left: left,
                    top: top,
                    radius: radius,
                    fill: obj.fill || '#3b82f6',
                    stroke: (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : null,
                    strokeWidth: obj.strokeWidth || 0,
                    opacity: opacity,
                    angle: angle
                });
                fabricObj.name = `Circle`;
                fabricObj.customType = 'circle';
                break;
            }

            case 'triangle': {
                fabricObj = new fabric.Triangle({
                    left: left,
                    top: top,
                    width: width,
                    height: height,
                    fill: obj.fill || '#3b82f6',
                    stroke: (obj.stroke && obj.stroke !== 'transparent') ? obj.stroke : null,
                    strokeWidth: obj.strokeWidth || 0,
                    opacity: opacity,
                    angle: angle
                });
                fabricObj.name = `Triangle`;
                fabricObj.customType = 'triangle';
                break;
            }

            case 'line': {
                fabricObj = new fabric.Line([0, 0, width, 0], {
                    left: left,
                    top: top,
                    stroke: obj.stroke || obj.fill || '#3b82f6',
                    strokeWidth: Math.max(1, obj.strokeWidth || 3),
                    opacity: opacity,
                    angle: angle
                });
                fabricObj.name = `Line`;
                fabricObj.customType = 'line';
                break;
            }

            case 'image': {
                let imgUrl = obj.src;
                if (!imgUrl) {
                    const query = obj.imageQuery ? encodeURIComponent(obj.imageQuery) : 'modern abstract graphic illustration minimal';
                    const targetW = Math.max(200, Math.round(width));
                    const targetH = Math.max(200, Math.round(height));
                    const candidateUrl = `https://image.pollinations.ai/prompt/${query}?width=${targetW}&height=${targetH}&nologo=true`;
                    imgUrl = `api/proxy-image.php?url=${encodeURIComponent(candidateUrl)}`;
                }

                try {
                    fabricObj = await loadImagePromise(imgUrl, {
                        left: left,
                        top: top,
                        targetWidth: width,
                        targetHeight: height,
                        opacity: opacity,
                        angle: angle,
                        rx: obj.rx || 0
                    });
                    fabricObj.name = obj.name || (obj.isBackground ? (window.i18n ? window.i18n('ai_background_layer', 'AI Background') : 'AI Background') : `Image: ${obj.imageQuery || 'Photo'}`);
                } catch (e) {
                    // Fallback to placeholder rect if network blocked
                    fabricObj = new fabric.Rect({
                        left: left,
                        top: top,
                        width: width,
                        height: height,
                        fill: obj.fill || '#cbd5e1',
                        stroke: '#94a3b8',
                        strokeWidth: 1,
                        rx: obj.rx || 8,
                        ry: obj.rx || 8,
                        opacity: opacity,
                        angle: angle
                    });
                    fabricObj.name = obj.name || `Image: ${obj.imageQuery || 'Placeholder'}`;
                }

                if (obj.locked) {
                    fabricObj.set({
                        lockMovementX: true,
                        lockMovementY: true,
                        lockRotation: true,
                        lockScalingX: true,
                        lockScalingY: true
                    });
                    fabricObj.locked = true;
                }

                if (obj.isBackground) {
                    fabricObj.isBackground = true;
                }

                fabricObj.customType = 'image';
                break;
            }
        }

        if (fabricObj) {
            fabricObj.id = objId;
            fabricCanvas.add(fabricObj);
        }
    }

    fabricCanvas.requestRenderAll();
    window.dispatchEvent(new CustomEvent('canvas:objects-updated'));
}

/**
 * Helper to load Fabric.js image safely with proxy
 */
function loadImagePromise(url, opts) {
    return new Promise((resolve, reject) => {
        fabric.Image.fromURL(url, (img) => {
            if (!img || !img.width) {
                return reject(new Error('Image failed to load'));
            }
            img.set({
                left: opts.left,
                top: opts.top,
                opacity: opts.opacity,
                angle: opts.angle
            });
            // Scale to target dimensions
            img.scaleToWidth(opts.targetWidth);
            if (opts.targetHeight) {
                // If aspect ratio differs, adjust
                img.set({ scaleY: opts.targetHeight / img.height });
            }
            resolve(img);
        }, { crossOrigin: 'anonymous' });
    });
}

