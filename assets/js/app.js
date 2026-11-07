/**
 * AI Design Studio - Master Application Entry Point (ES6 Module)
 * Orchestrates Fabric.js Canvas, Managers, Toolbars, and AI integrations.
 */

import { CanvasManager } from './editor/canvas-manager.js';
import { HistoryManager } from './editor/history-manager.js';
import { LayersPanel } from './editor/layers-panel.js';
import { PropertiesPanel } from './editor/properties-panel.js';
import { Toolbar } from './editor/toolbar.js';
import { ShortcutsManager } from './editor/shortcuts.js';
import { ExportManager } from './editor/export-manager.js';
import { ProjectManager } from './editor/project-manager.js';
import { AiBar } from './editor/ai-bar.js';
import { PosterPipeline } from './editor/poster-pipeline.js';
import { ThemeManager } from './editor/theme-manager.js';
import { ToastManager } from './editor/toast-manager.js';

let isAppInitialized = false;

function initApp() {
    if (isAppInitialized) return;
    isAppInitialized = true;

    // 0. Initialize Liquid Glass Theme & Toast Managers
    let themeManager = null;
    let toastManager = null;
    try {
        themeManager = new ThemeManager();
        window.themeManager = themeManager;
    } catch (err) {
        console.error('Error initializing ThemeManager:', err);
    }

    try {
        toastManager = new ToastManager();
        window.toastManager = toastManager;
    } catch (err) {
        console.error('Error initializing ToastManager:', err);
    }

    // 1. Verify Fabric.js is loaded
    if (typeof fabric === 'undefined') {
        console.error('Fabric.js library not detected. Ensure fabric.js is included in <head>.');
        return;
    }

    let canvasManager = null;
    let fabricCanvas = null;
    let historyManager = null;

    // 2. Initialize Canvas & Viewport
    try {
        canvasManager = new CanvasManager('design-canvas', 'canvas-viewport');
        fabricCanvas = canvasManager.fabricCanvas;

        // Set initial 1080x1080 resolution and default white background
        canvasManager.setResolution(1080, 1080);
        fabricCanvas.setBackgroundColor('#ffffff', () => fabricCanvas.requestRenderAll());

        // Expose instances for testing & debug
        window.designStudioCanvas = fabricCanvas;
        window.designStudioCanvasManager = canvasManager;
    } catch (err) {
        console.error('Error initializing CanvasManager:', err);
    }

    if (!fabricCanvas) {
        console.error('Fabric canvas could not be initialized. Stopping sub-modules init.');
        return;
    }

    // 3. Initialize History Manager (50 undo/redo steps)
    try {
        historyManager = new HistoryManager(fabricCanvas, 50);
        window.designStudioHistoryManager = historyManager;
        setTimeout(() => {
            historyManager.saveState();
        }, 100);
    } catch (err) {
        console.error('Error initializing HistoryManager:', err);
    }

    // 4. Initialize Components Defensively
    let layersPanel = null;
    try {
        layersPanel = new LayersPanel('layers-container', fabricCanvas, historyManager);
    } catch (err) {
        console.error('Error initializing LayersPanel:', err);
    }

    try {
        new PropertiesPanel('properties-container', fabricCanvas, canvasManager, historyManager);
    } catch (err) {
        console.error('Error initializing PropertiesPanel:', err);
    }

    try {
        new Toolbar(fabricCanvas, canvasManager, historyManager);
    } catch (err) {
        console.error('Error initializing Toolbar:', err);
    }

    try {
        new ShortcutsManager(fabricCanvas, historyManager);
    } catch (err) {
        console.error('Error initializing ShortcutsManager:', err);
    }

    try {
        new ExportManager(fabricCanvas, canvasManager, historyManager);
    } catch (err) {
        console.error('Error initializing ExportManager:', err);
    }

    try {
        new ProjectManager(fabricCanvas, canvasManager, historyManager);
    } catch (err) {
        console.error('Error initializing ProjectManager:', err);
    }

    try {
        new AiBar(fabricCanvas, canvasManager, historyManager);
    } catch (err) {
        console.error('Error initializing AiBar:', err);
    }

    try {
        const posterPipeline = new PosterPipeline(fabricCanvas, canvasManager, historyManager);
        window.designStudioPosterPipeline = posterPipeline;
    } catch (err) {
        console.error('Error initializing PosterPipeline:', err);
    }

    // 5. Connect Top-bar Undo / Redo buttons
    try {
        const btnUndo = document.getElementById('btn-undo');
        const btnRedo = document.getElementById('btn-redo');

        if (btnUndo && historyManager) {
            btnUndo.addEventListener('click', () => historyManager.undo());
        }
        if (btnRedo && historyManager) {
            btnRedo.addEventListener('click', () => historyManager.redo());
        }

        const updateUndoRedoButtons = (detail) => {
            if (!detail) return;
            if (btnUndo) btnUndo.disabled = !detail.canUndo;
            if (btnRedo) btnRedo.disabled = !detail.canRedo;
        };

        window.addEventListener('canvas:history', (e) => updateUndoRedoButtons(e.detail));
        window.addEventListener('history:change', (e) => updateUndoRedoButtons(e.detail));
    } catch (err) {
        console.error('Error setting up Undo/Redo buttons:', err);
    }

    // 6. Inspector Tab Switcher (Properties vs Layers)
    try {
        const tabPropsBtn = document.getElementById('tab-properties-btn');
        const tabLayersBtn = document.getElementById('tab-layers-btn');
        const tabPropsPane = document.getElementById('tab-properties-pane');
        const tabLayersPane = document.getElementById('tab-layers-pane');

        if (tabPropsBtn && tabLayersBtn && tabPropsPane && tabLayersPane) {
            tabPropsBtn.addEventListener('click', () => {
                tabPropsBtn.classList.add('active');
                tabLayersBtn.classList.remove('active');
                tabPropsPane.classList.add('active');
                tabLayersPane.classList.remove('active');
            });

            tabLayersBtn.addEventListener('click', () => {
                tabLayersBtn.classList.add('active');
                tabPropsBtn.classList.remove('active');
                tabLayersPane.classList.add('active');
                tabPropsPane.classList.remove('active');
                if (layersPanel) layersPanel.render();
            });

            // Automatically switch to Properties tab when an object is selected on canvas
            fabricCanvas.on('selection:created', () => {
                tabPropsBtn.click();
            });
        }
    } catch (err) {
        console.error('Error setting up Inspector tabs:', err);
    }

    // 7. Auto-fit canvas to container on window resize
    try {
        window.addEventListener('resize', () => {
            if (canvasManager) canvasManager.fitToContainer();
        });
    } catch (err) {
        console.error('Error setting up resize listener:', err);
    }

    // 8. 60 FPS Canvas Drag Performance Optimization
    try {
        const setInteracting = () => document.body.classList.add('canvas-interacting');
        const clearInteracting = () => document.body.classList.remove('canvas-interacting');

        fabricCanvas.on('object:moving', setInteracting);
        fabricCanvas.on('object:scaling', setInteracting);
        fabricCanvas.on('object:rotating', setInteracting);
        fabricCanvas.on('mouse:up', clearInteracting);
    } catch (err) {
        console.error('Error setting up drag performance listener:', err);
    }

    // 9. Keyboard Shortcuts Modal Triggers
    try {
        const btnOpenShortcuts = document.getElementById('btn-open-shortcuts');
        const modalShortcuts = document.getElementById('shortcuts-modal');
        const btnCloseShortcuts = document.getElementById('close-shortcuts-modal');

        if (btnOpenShortcuts && modalShortcuts) {
            btnOpenShortcuts.addEventListener('click', () => {
                modalShortcuts.style.display = 'flex';
            });
        }
        if (btnCloseShortcuts && modalShortcuts) {
            btnCloseShortcuts.addEventListener('click', () => {
                modalShortcuts.style.display = 'none';
            });
        }
        if (modalShortcuts) {
            modalShortcuts.addEventListener('click', (e) => {
                if (e.target === modalShortcuts) modalShortcuts.style.display = 'none';
            });
        }
    } catch (err) {
        console.error('Error setting up Shortcuts modal:', err);
    }

    // 10. Floating Panels & Prompt Bar Collapse/Expand Toggles
    try {
        const workspace = document.querySelector('.editor-workspace');
        const btnCollapseTools = document.getElementById('btn-collapse-tools');
        const toolsSidebar = document.querySelector('.tools-sidebar');
        if (btnCollapseTools && toolsSidebar) {
            btnCollapseTools.addEventListener('click', () => {
                toolsSidebar.classList.toggle('collapsed');
                if (workspace) workspace.classList.toggle('tools-collapsed', toolsSidebar.classList.contains('collapsed'));
                setTimeout(() => canvasManager?.fitToContainer(), 250);
            });
        }

        const btnCollapseInspector = document.getElementById('btn-collapse-inspector');
        const inspectorSidebar = document.querySelector('.inspector-sidebar');
        if (btnCollapseInspector && inspectorSidebar) {
            btnCollapseInspector.addEventListener('click', () => {
                inspectorSidebar.classList.toggle('collapsed');
                if (workspace) workspace.classList.toggle('inspector-collapsed', inspectorSidebar.classList.contains('collapsed'));
                setTimeout(() => canvasManager?.fitToContainer(), 250);
            });
        }

        const btnTogglePromptBar = document.getElementById('btn-toggle-prompt-bar');
        const promptBar = document.getElementById('ai-prompt-bar');
        const promptToggleIcon = document.getElementById('prompt-bar-toggle-icon');
        const promptToggleText = document.getElementById('prompt-bar-toggle-text');

        if (btnTogglePromptBar && promptBar) {
            btnTogglePromptBar.addEventListener('click', () => {
                const isCollapsed = promptBar.classList.toggle('collapsed');
                if (workspace) workspace.classList.toggle('expanded-height', isCollapsed);
                if (promptToggleIcon) promptToggleIcon.textContent = isCollapsed ? '▼' : '▲';
                if (promptToggleText) {
                    promptToggleText.textContent = isCollapsed 
                        ? (window.i18n ? window.i18n('expand_generator', 'Expand') : 'Expand')
                        : (window.i18n ? window.i18n('collapse_generator', 'Minimize') : 'Minimize');
                }
                setTimeout(() => canvasManager?.fitToContainer(), 250);
            });
        }

        // Auto-minimize prompt bar after generating to reveal maximum canvas
        const autoMinimize = () => {
            if (promptBar && !promptBar.classList.contains('collapsed')) {
                promptBar.classList.add('collapsed');
                if (workspace) workspace.classList.add('expanded-height');
                if (promptToggleIcon) promptToggleIcon.textContent = '▼';
                if (promptToggleText) {
                    promptToggleText.textContent = window.i18n ? window.i18n('expand_generator', 'Expand') : 'Expand';
                }
                setTimeout(() => canvasManager?.fitToContainer(), 300);
            }
        };
        window.addEventListener('ai:generated', autoMinimize);
        window.addEventListener('editor:design-loaded', autoMinimize);

        // 'F' Hotkey for Focus Mode (Full Canvas View)
        window.addEventListener('keydown', (e) => {
            if ((e.key === 'f' || e.key === 'F') && !e.ctrlKey && !e.metaKey && !e.altKey) {
                const tag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
                if (tag !== 'input' && tag !== 'textarea' && !document.activeElement.isContentEditable) {
                    canvasManager?.toggleFocusMode();
                }
            }
        });
    } catch (err) {
        console.error('Error setting up panel collapse toggles:', err);
    }

    // 11. Log ready status
    console.log('AI Design Studio Liquid Glass Phase 4 ready.');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}
