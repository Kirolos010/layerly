import asyncio
import json
import subprocess
import time
import urllib.request
import websockets

CHROME_PATH = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
BASE_URL = "http://localhost/layerly"

def launch_chrome():
    cmd = [
        CHROME_PATH,
        "--headless=new",
        "--remote-debugging-port=9222",
        "--window-size=1600,1000",
        "--no-first-run",
        "--no-default-browser-check",
        "--disable-gpu",
        "about:blank"
    ]
    proc = subprocess.Popen(cmd)
    for _ in range(25):
        try:
            resp = urllib.request.urlopen("http://localhost:9222/json")
            if resp.getcode() == 200:
                return proc
        except Exception:
            time.sleep(0.3)
    return proc

async def run_flexible_canvas_tests():
    print("==================================================================", flush=True)
    print("TESTING FLEXIBLE, LARGE & SHARP CANVAS VIEWPORT (CHROME CDP)", flush=True)
    print("==================================================================", flush=True)

    resp = urllib.request.urlopen("http://localhost:9222/json")
    pages = json.loads(resp.read().decode())
    page = next((p for p in pages if p.get('type') == 'page'), pages[0])
    ws_url = page['webSocketDebuggerUrl']

    async with websockets.connect(ws_url) as ws:
        msg_id = 0
        async def send(method, params=None):
            nonlocal msg_id
            msg_id += 1
            payload = {"id": msg_id, "method": method, "params": params or {}}
            await ws.send(json.dumps(payload))
            return msg_id

        async def recv_response(expected_id):
            while True:
                raw = await ws.recv()
                msg = json.loads(raw)
                if msg.get("id") == expected_id:
                    return msg

        async def evaluate(expr):
            req_id = await send("Runtime.evaluate", {"expression": expr, "returnByValue": True, "awaitPromise": True})
            resp = await recv_response(req_id)
            val = resp.get("result", {}).get("result", {}).get("value")
            return val

        await send("Page.enable")
        await send("DOM.enable")
        await send("Runtime.enable")
        await send("Network.enable")
        await send("Network.setCacheDisabled", {"cacheDisabled": True})

        print("\n--- [STEP 1] Navigate to Editor & Check Initial Dimensions ---", flush=True)
        nav_id = await send("Page.navigate", {"url": f"{BASE_URL}/index.php"})
        await recv_response(nav_id)
        await asyncio.sleep(2.0)

        initial_dims = await evaluate("""
            new Promise(async (resolve) => {
                for (let i = 0; i < 40; i++) {
                    if (window.designStudioCanvasManager) break;
                    await new Promise(r => setTimeout(r, 100));
                }
                const viewport = document.getElementById('canvas-viewport');
                const canvas = window.designStudioCanvas;
                const cm = window.designStudioCanvasManager;
                resolve({
                    viewportWidth: viewport ? viewport.clientWidth : 0,
                    viewportHeight: viewport ? viewport.clientHeight : 0,
                    canvasDisplayWidth: canvas ? canvas.getWidth() : 0,
                    canvasDisplayHeight: canvas ? canvas.getHeight() : 0,
                    zoom: cm ? cm.zoom : 0,
                    hasPromptBar: !!document.getElementById('ai-prompt-bar'),
                    hasTogglePromptBtn: !!document.getElementById('btn-toggle-prompt-bar'),
                    hasFocusBtn: !!document.getElementById('btn-focus-mode'),
                    hasFitWidthBtn: !!document.getElementById('btn-zoom-fit-width'),
                    hasHud: !!document.getElementById('canvas-viewport-hud')
                });
            })
        """)
        print(f"  [1.1] Initial Viewport & Canvas: {initial_dims}", flush=True)
        assert initial_dims['viewportWidth'] > 400, "Viewport width too small"
        assert initial_dims['hasTogglePromptBtn'] is True, "Missing #btn-toggle-prompt-bar"
        assert initial_dims['hasFocusBtn'] is True, "Missing #btn-focus-mode"
        assert initial_dims['hasFitWidthBtn'] is True, "Missing #btn-zoom-fit-width"
        assert initial_dims['hasHud'] is True, "Missing #canvas-viewport-hud"

        print("\n--- [STEP 2] Toggle Minimize AI Prompt Bar ---", flush=True)
        minimize_res = await evaluate("""
            new Promise(async (resolve) => {
                const btnToggle = document.getElementById('btn-toggle-prompt-bar');
                const promptBar = document.getElementById('ai-prompt-bar');
                const vBefore = document.getElementById('canvas-viewport').clientHeight;

                btnToggle.click();
                await new Promise(r => setTimeout(r, 350));

                const vAfter = document.getElementById('canvas-viewport').clientHeight;
                const isCollapsed = promptBar.classList.contains('collapsed');

                resolve({
                    isCollapsed,
                    vBefore,
                    vAfter,
                    heightGained: vAfter - vBefore
                });
            })
        """)
        print(f"  [2.1] Prompt Bar Minimize: {minimize_res}", flush=True)
        assert minimize_res['isCollapsed'] is True, "Prompt bar did not collapse"

        print("\n--- [STEP 3] Toggle Focus Mode (Fullscreen Canvas) ---", flush=True)
        focus_res = await evaluate("""
            new Promise(async (resolve) => {
                const btnFocus = document.getElementById('btn-focus-mode');
                const viewport = document.getElementById('canvas-viewport');
                const wBefore = viewport.clientWidth;

                btnFocus.click();
                await new Promise(r => setTimeout(r, 400));

                const wAfter = viewport.clientWidth;
                const isFocus = document.querySelector('.editor-workspace').classList.contains('focus-mode');
                const cm = window.designStudioCanvasManager;

                resolve({
                    isFocus,
                    wBefore,
                    wAfter,
                    widthGained: wAfter - wBefore,
                    canvasWidth: window.designStudioCanvas.getWidth(),
                    canvasHeight: window.designStudioCanvas.getHeight(),
                    zoom: cm ? cm.zoom : 0
                });
            })
        """)
        print(f"  [3.1] Focus Mode (Wide Canvas): {focus_res}", flush=True)
        assert focus_res['isFocus'] is True, "Focus mode not activated"
        assert focus_res['widthGained'] > 200, "Canvas viewport should gain significant width in focus mode"

        print("\n--- [STEP 4] Test Zoom Controls & Fit to Width ---", flush=True)
        zoom_res = await evaluate("""
            new Promise(async (resolve) => {
                const cm = window.designStudioCanvasManager;
                const hudZoomIn = document.getElementById('hud-zoom-in');
                const hudZoomVal = document.getElementById('hud-zoom-val');
                const btnFitWidth = document.getElementById('btn-zoom-fit-width');

                const initialZoom = cm.zoom;
                hudZoomIn.click();
                await new Promise(r => setTimeout(r, 50));
                hudZoomIn.click();
                await new Promise(r => setTimeout(r, 50));

                const zoomedIn = cm.zoom;
                const hudPillText = hudZoomVal ? hudZoomVal.textContent : '';

                // Now test fit to width
                btnFitWidth.click();
                await new Promise(r => setTimeout(r, 50));
                const fitWidthZoom = cm.zoom;

                resolve({
                    initialZoom,
                    zoomedIn,
                    hudPillText,
                    fitWidthZoom,
                    isZoomedIn: zoomedIn > initialZoom
                });
            })
        """)
        print(f"  [4.1] Zoom Actions & HUD update: {zoom_res}", flush=True)
        assert zoom_res['isZoomedIn'] is True, "Zoom In did not increase zoom"

        print("\n--- [STEP 5] Spacebar Pan Mode Verification ---", flush=True)
        pan_res = await evaluate("""
            (() => {
                const viewport = document.getElementById('canvas-viewport');
                const isOverflowAuto = window.getComputedStyle(viewport).overflow === 'auto';
                window.dispatchEvent(new KeyboardEvent('keydown', { code: 'Space' }));
                const hasPanClass = viewport.classList.contains('panning-mode');
                window.dispatchEvent(new KeyboardEvent('keyup', { code: 'Space' }));
                const clearedPanClass = !viewport.classList.contains('panning-mode');

                return {
                    isOverflowAuto,
                    hasPanClass,
                    clearedPanClass
                };
            })()
        """)
        print(f"  [5.1] Spacebar Hand Tool (Pan Mode): {pan_res}", flush=True)
        assert pan_res['isOverflowAuto'] is True, "Viewport must have overflow: auto for panning"
        assert pan_res['hasPanClass'] is True, "Spacebar must activate panning-mode"
        assert pan_res['clearedPanClass'] is True, "Releasing Space must clear panning-mode"

        print("\n==================================================================", flush=True)
        print("ALL FLEXIBLE CANVAS & HIGH-CLARITY TESTS PASSED SUCCESSFULLY! 🎉", flush=True)
        print("==================================================================", flush=True)

if __name__ == "__main__":
    proc = launch_chrome()
    try:
        asyncio.run(run_flexible_canvas_tests())
    finally:
        try:
            proc.terminate()
        except Exception:
            pass
