/**
 * AI Design Studio - Fabric.js Canvas Manager
 * Handles responsive canvas scaling, dynamic zoom, pan/hand tool, grid, and alignment guides.
 */

export class CanvasManager {
    constructor(canvasElementId, containerElementId) {
        this.canvasEl = document.getElementById(canvasElementId);
        this.containerEl = document.getElementById(containerElementId);

        // Virtual project resolution
        this.virtualWidth = 1080;
        this.virtualHeight = 1080;

        // Current zoom level
        this.zoom = 1.0;
        this.showGrid = false;
        this.isFocusMode = false;

        // Configure Fabric high-DPI retina sharpness & image smoothing
        fabric.Image.prototype.imageSmoothing = true;

        // Initialize Fabric Canvas
        this.fabricCanvas = new fabric.Canvas(canvasElementId, {
            preserveObjectStacking: true,
            selection: true,
            selectionColor: 'rgba(59, 130, 246, 0.15)',
            selectionBorderColor: '#3b82f6',
            selectionLineWidth: 1.5,
            stopContextMenu: true,
            fireRightClick: true,
            enableRetinaScaling: true,
            imageSmoothingEnabled: true
        });

        // Configure modern handle controls
        fabric.Object.prototype.transparentCorners = false;
        fabric.Object.prototype.cornerColor = '#3b82f6';
        fabric.Object.prototype.cornerStrokeColor = '#ffffff';
        fabric.Object.prototype.borderColor = '#3b82f6';
        fabric.Object.prototype.cornerSize = 10;
        fabric.Object.prototype.cornerStyle = 'circle';
        fabric.Object.prototype.padding = 4;

        this.setupSnappingGuides();
        this.setupResizeObserver();
        this.setupPanAndZoom();
    }

    /**
     * Set the true export resolution of the project
     */
    setResolution(width, height) {
        this.virtualWidth = Math.max(100, parseInt(width, 10) || 1080);
        this.virtualHeight = Math.max(100, parseInt(height, 10) || 1080);
        this.fitToContainer();
    }

    /**
     * Compute scale factor and fit canvas comfortably in container
     */
    fitToContainer() {
        if (!this.containerEl) return;

        const padding = 28;
        const availableW = Math.max(200, this.containerEl.clientWidth - padding);
        const availableH = Math.max(200, this.containerEl.clientHeight - padding);

        const scaleW = availableW / this.virtualWidth;
        const scaleH = availableH / this.virtualHeight;
        const fitScale = Math.min(scaleW, scaleH);

        this.setZoomLevel(Math.max(0.15, fitScale));
        this.centerViewport();
    }

    /**
     * Fit canvas to the full available width of container
     */
    fitToWidth() {
        if (!this.containerEl) return;
        const padding = 28;
        const availableW = Math.max(200, this.containerEl.clientWidth - padding);
        const scaleW = availableW / this.virtualWidth;
        this.setZoomLevel(Math.max(0.15, scaleW));
        this.centerViewport();
    }

    /**
     * Center scroll position in viewport
     */
    centerViewport() {
        if (!this.containerEl) return;
        requestAnimationFrame(() => {
            const scrollX = Math.max(0, (this.containerEl.scrollWidth - this.containerEl.clientWidth) / 2);
            const scrollY = Math.max(0, (this.containerEl.scrollHeight - this.containerEl.clientHeight) / 2);
            this.containerEl.scrollLeft = scrollX;
            this.containerEl.scrollTop = scrollY;
        });
    }

    /**
     * Apply explicit zoom level with high-DPI scaling
     */
    setZoomLevel(scale) {
        this.zoom = Math.max(0.15, Math.min(4.0, scale));

        const displayW = Math.round(this.virtualWidth * this.zoom);
        const displayH = Math.round(this.virtualHeight * this.zoom);

        this.fabricCanvas.setDimensions({
            width: displayW,
            height: displayH
        });

        this.fabricCanvas.setZoom(this.zoom);
        this.fabricCanvas.requestRenderAll();

        if (this.showGrid) {
            const gridCanvas = document.getElementById('grid-overlay');
            if (gridCanvas) this.drawGrid(gridCanvas);
        }

        // Dispatch zoom event for UI badge
        const rounded = Math.round(this.zoom * 100);
        const event = new CustomEvent('canvas:zoom', { detail: { zoom: rounded } });
        window.dispatchEvent(event);

        // Also update any floating HUD zoom pills
        const hudPill = document.getElementById('hud-zoom-val');
        if (hudPill) hudPill.textContent = `${rounded}%`;
    }

    zoomIn() {
        this.setZoomLevel(Math.round((this.zoom + 0.1) * 10) / 10);
    }

    zoomOut() {
        this.setZoomLevel(Math.round((this.zoom - 0.1) * 10) / 10);
    }

    zoomReset() {
        this.setZoomLevel(1.0);
        this.centerViewport();
    }

    /**
     * Setup Pan & Zoom interactions (Figma/Canva style)
     */
    setupPanAndZoom() {
        if (!this.containerEl) return;

        // 1. Mouse wheel zoom with Ctrl (or trackpad pinch)
        this.containerEl.addEventListener('wheel', (e) => {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                const delta = e.deltaY;
                const factor = delta > 0 ? 0.9 : 1.1;
                this.setZoomLevel(this.zoom * factor);
            }
        }, { passive: false });

        // 2. Spacebar + Drag or Middle Mouse Button to Pan
        let isPanning = false;
        let startX = 0;
        let startY = 0;
        let scrollStartX = 0;
        let scrollStartY = 0;
        let spacePressed = false;

        window.addEventListener('keydown', (e) => {
            if (e.code === 'Space' && !spacePressed) {
                const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
                if (activeTag === 'input' || activeTag === 'textarea' || document.activeElement.isContentEditable) {
                    return;
                }
                spacePressed = true;
                this.containerEl.classList.add('panning-mode');
                this.fabricCanvas.defaultCursor = 'grab';
                this.fabricCanvas.selection = false;
            }
        });

        window.addEventListener('keyup', (e) => {
            if (e.code === 'Space') {
                spacePressed = false;
                isPanning = false;
                this.containerEl.classList.remove('panning-mode', 'is-panning');
                this.fabricCanvas.defaultCursor = 'default';
                this.fabricCanvas.selection = true;
            }
        });

        const startPan = (e) => {
            isPanning = true;
            startX = e.clientX;
            startY = e.clientY;
            scrollStartX = this.containerEl.scrollLeft;
            scrollStartY = this.containerEl.scrollTop;
            this.containerEl.classList.add('is-panning');
            if (spacePressed) {
                this.fabricCanvas.defaultCursor = 'grabbing';
            }
        };

        const doPan = (e) => {
            if (!isPanning) return;
            e.preventDefault();
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            this.containerEl.scrollLeft = scrollStartX - dx;
            this.containerEl.scrollTop = scrollStartY - dy;
        };

        const endPan = () => {
            if (!isPanning) return;
            isPanning = false;
            this.containerEl.classList.remove('is-panning');
            if (spacePressed) {
                this.fabricCanvas.defaultCursor = 'grab';
            } else {
                this.fabricCanvas.defaultCursor = 'default';
            }
        };

        this.containerEl.addEventListener('mousedown', (e) => {
            if (e.button === 1 || (e.button === 0 && spacePressed)) {
                e.preventDefault();
                startPan(e);
            }
        });

        window.addEventListener('mousemove', doPan);
        window.addEventListener('mouseup', endPan);
    }

    /**
     * Toggle Focus Mode (Maximized workspace canvas)
     */
    toggleFocusMode() {
        this.isFocusMode = !this.isFocusMode;
        const workspace = document.querySelector('.editor-workspace');
        const toolsSidebar = document.querySelector('.tools-sidebar');
        const inspectorSidebar = document.querySelector('.inspector-sidebar');
        const promptBar = document.getElementById('ai-prompt-bar');
        const btnFocus = document.getElementById('btn-focus-mode');
        const hudFocus = document.getElementById('hud-focus-mode');

        if (this.isFocusMode) {
            if (toolsSidebar) toolsSidebar.classList.add('collapsed');
            if (inspectorSidebar) inspectorSidebar.classList.add('collapsed');
            if (workspace) workspace.classList.add('focus-mode', 'tools-collapsed', 'inspector-collapsed');
            if (promptBar) promptBar.classList.add('collapsed');
            if (btnFocus) btnFocus.classList.add('active');
            if (hudFocus) hudFocus.classList.add('active');
        } else {
            if (toolsSidebar) toolsSidebar.classList.remove('collapsed');
            if (inspectorSidebar) inspectorSidebar.classList.remove('collapsed');
            if (workspace) workspace.classList.remove('focus-mode', 'tools-collapsed', 'inspector-collapsed');
            if (btnFocus) btnFocus.classList.remove('active');
            if (hudFocus) hudFocus.classList.remove('active');
        }

        // Re-fit canvas to newly expanded dimensions smoothly
        setTimeout(() => {
            this.fitToContainer();
        }, 300);

        return this.isFocusMode;
    }

    /**
     * Resize observer for responsive editor wrapper
     */
    setupResizeObserver() {
        if (!window.ResizeObserver || !this.containerEl) return;
        const ro = new ResizeObserver(() => {
            this.fitToContainer();
        });
        ro.observe(this.containerEl);
    }

    /**
     * Alignment guides / Snapping lines
     */
    setupSnappingGuides() {
        const SNAP_MARGIN = 8;

        this.fabricCanvas.on('object:moving', (e) => {
            const obj = e.target;
            if (!obj) return;

            const w = this.virtualWidth;
            const h = this.virtualHeight;
            const objW = obj.getScaledWidth();
            const objH = obj.getScaledHeight();

            // Snap to Canvas Center Horizontal
            if (Math.abs(obj.left + objW / 2 - w / 2) < SNAP_MARGIN) {
                obj.set({ left: w / 2 - objW / 2 });
            }
            // Snap to Canvas Center Vertical
            if (Math.abs(obj.top + objH / 2 - h / 2) < SNAP_MARGIN) {
                obj.set({ top: h / 2 - objH / 2 });
            }
            // Snap to Canvas Left
            if (Math.abs(obj.left) < SNAP_MARGIN) {
                obj.set({ left: 0 });
            }
            // Snap to Canvas Right
            if (Math.abs(obj.left + objW - w) < SNAP_MARGIN) {
                obj.set({ left: w - objW });
            }
            // Snap to Canvas Top
            if (Math.abs(obj.top) < SNAP_MARGIN) {
                obj.set({ top: 0 });
            }
            // Snap to Canvas Bottom
            if (Math.abs(obj.top + objH - h) < SNAP_MARGIN) {
                obj.set({ top: h - objH });
            }
        });
    }

    /**
     * Toggle grid lines on/off
     */
    toggleGrid() {
        this.showGrid = !this.showGrid;
        const gridCanvas = document.getElementById('grid-overlay');
        if (!gridCanvas) return this.showGrid;

        if (this.showGrid) {
            gridCanvas.style.display = 'block';
            this.drawGrid(gridCanvas);
        } else {
            gridCanvas.style.display = 'none';
        }
        return this.showGrid;
    }

    drawGrid(canvas) {
        const ctx = canvas.getContext('2d');
        const w = canvas.width = this.fabricCanvas.getWidth();
        const h = canvas.height = this.fabricCanvas.getHeight();
        ctx.clearRect(0, 0, w, h);

        const gridSize = 40 * this.zoom;
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.07)';
        ctx.lineWidth = 1;

        ctx.beginPath();
        for (let x = 0; x < w; x += gridSize) {
            ctx.moveTo(x, 0);
            ctx.lineTo(x, h);
        }
        for (let y = 0; y < h; y += gridSize) {
            ctx.moveTo(0, y);
            ctx.lineTo(w, y);
        }
        ctx.stroke();
    }
}
