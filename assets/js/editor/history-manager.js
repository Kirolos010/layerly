/**
 * AI Design Studio - 50-Step Undo / Redo Manager
 */

export class HistoryManager {
    constructor(fabricCanvas, maxSteps = 50) {
        this.canvas = fabricCanvas;
        this.maxSteps = maxSteps;

        this.undoStack = [];
        this.redoStack = [];

        this.isLocked = false; // Prevents recording during undo/redo
        this.customProperties = ['id', 'name', 'customType', 'lockMovementX', 'lockMovementY', 'selectable', 'evented', 'direction'];

        this.setupListeners();
    }

    setupListeners() {
        const recordChange = () => {
            if (!this.isLocked) {
                this.saveState();
            }
        };

        this.canvas.on('object:modified', recordChange);
        this.canvas.on('object:added', (e) => {
            // Don't record internal selection or grid objects
            if (!e.target || e.target.type === 'activeSelection') return;
            recordChange();
        });
        this.canvas.on('object:removed', recordChange);
    }

    /**
     * Save current canvas state to undo stack
     */
    saveState() {
        if (this.isLocked) return;

        const json = this.canvas.toJSON(this.customProperties);
        const stateString = JSON.stringify(json);

        // Don't record identical consecutive states
        if (this.undoStack.length > 0 && this.undoStack[this.undoStack.length - 1] === stateString) {
            return;
        }

        this.undoStack.push(stateString);
        if (this.undoStack.length > this.maxSteps) {
            this.undoStack.shift();
        }

        // Clear redo stack on new user action
        this.redoStack = [];
        this.dispatchHistoryEvent();
    }

    canUndo() {
        return this.undoStack.length > 1;
    }

    canRedo() {
        return this.redoStack.length > 0;
    }

    async undo() {
        if (!this.canUndo()) return;

        this.isLocked = true;
        const currentState = this.undoStack.pop();
        this.redoStack.push(currentState);

        const prevStateString = this.undoStack[this.undoStack.length - 1];
        await this.loadState(prevStateString);
        this.isLocked = false;
        this.dispatchHistoryEvent();
    }

    async redo() {
        if (!this.canRedo()) return;

        this.isLocked = true;
        const nextStateString = this.redoStack.pop();
        this.undoStack.push(nextStateString);

        await this.loadState(nextStateString);
        this.isLocked = false;
        this.dispatchHistoryEvent();
    }

    loadState(stateString) {
        return new Promise((resolve) => {
            const json = JSON.parse(stateString);
            this.canvas.loadFromJSON(json, () => {
                this.canvas.requestRenderAll();
                resolve();
            });
        });
    }

    dispatchHistoryEvent() {
        const detail = {
            canUndo: this.canUndo(),
            canRedo: this.canRedo(),
            undoCount: this.undoStack.length,
            redoCount: this.redoStack.length
        };
        window.dispatchEvent(new CustomEvent('canvas:history', { detail }));
        window.dispatchEvent(new CustomEvent('history:change', { detail }));
    }

    reset() {
        this.undoStack = [];
        this.redoStack = [];
        this.saveState();
    }
}

