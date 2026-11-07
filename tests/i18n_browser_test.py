import asyncio
import json
import os
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

async def run_phase_4_browser_tests():
    print("==================================================================", flush=True)
    print("STARTING PHASE 4 BROWSER & UI VERIFICATION (CHROME CDP)", flush=True)
    print("==================================================================", flush=True)

    resp = urllib.request.urlopen("http://localhost:9222/json")
    pages = json.loads(resp.read().decode())
    page = next((p for p in pages if p.get('type') == 'page'), pages[0])
    ws_url = page['webSocketDebuggerUrl']

    results = {}

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

        # -------------------------------------------------------------
        # TEST 1: Arabic Language Switcher & RTL Layout
        # -------------------------------------------------------------
        print("\n--- [TEST 1] Testing Arabic Switcher & Native RTL Layout ---", flush=True)
        nav_id = await send("Page.navigate", {"url": f"{BASE_URL}/index.php?lang=ar"})
        await recv_response(nav_id)
        await asyncio.sleep(2.0)

        rtl_check = await evaluate("""
            (() => {
                const docDir = document.documentElement.getAttribute('dir');
                const docLang = document.documentElement.getAttribute('lang');
                const title = document.title;
                const url = window.location.href;
                const hasMesh = !!document.querySelector('.glass-mesh-backdrop');
                const floatingBar = document.querySelector('#floating-ai-bar');
                const bodyClasses = document.body ? document.body.className : '';
                return {
                    url,
                    docDir,
                    docLang,
                    title,
                    hasMesh,
                    hasFloatingBar: !!floatingBar
                };
            })()
        """)
        print(f"  [1.1] RTL attributes & elements: {rtl_check}", flush=True)
        assert rtl_check['docDir'] == 'rtl', f"Expected dir=rtl, got {rtl_check['docDir']}"
        assert rtl_check['docLang'] == 'ar', f"Expected lang=ar, got {rtl_check['docLang']}"
        assert rtl_check['hasMesh'] is True, f"Missing .glass-mesh-backdrop on {rtl_check['url']}"
        assert rtl_check['hasFloatingBar'] is True, f"Missing #floating-ai-bar on {rtl_check['url']}"
        results['arabic_rtl'] = "PASS"

        # -------------------------------------------------------------
        # TEST 2: Light / Dark Theme Switching & Persistence
        # -------------------------------------------------------------
        print("\n--- [TEST 2] Testing Light/Dark Theme Switching & Persistence ---", flush=True)
        theme_toggle_res = await evaluate("""
            (() => {
                const btn = document.querySelector('#theme-toggle');
                if (!btn) return { error: 'No theme toggle button found' };
                
                const initTheme = document.documentElement.getAttribute('data-theme');
                btn.click();
                const afterFirstClick = document.documentElement.getAttribute('data-theme');
                const storedFirst = localStorage.getItem('design_studio_theme');
                
                btn.click();
                const afterSecondClick = document.documentElement.getAttribute('data-theme');
                const storedSecond = localStorage.getItem('design_studio_theme');
                
                return {
                    initTheme,
                    afterFirstClick,
                    storedFirst,
                    afterSecondClick,
                    storedSecond
                };
            })()
        """)
        print(f"  [2.1] Theme Toggle cycle: {theme_toggle_res}", flush=True)
        assert theme_toggle_res['initTheme'] != theme_toggle_res['afterFirstClick'], "Theme did not toggle on click"
        assert theme_toggle_res['afterFirstClick'] == theme_toggle_res['storedFirst'], "Theme not persisted to localStorage"
        assert theme_toggle_res['afterSecondClick'] == theme_toggle_res['initTheme'], "Theme did not toggle back"
        results['theme_toggle'] = "PASS"

        # -------------------------------------------------------------
        # TEST 3: Keyboard Shortcuts Modal ('?' hotkey & Esc)
        # -------------------------------------------------------------
        print("\n--- [TEST 3] Testing Shortcuts Modal Hotkey & Dismiss ---", flush=True)
        shortcuts_res = await evaluate("""
            (() => {
                const modal = document.querySelector('#shortcuts-modal');
                if (!modal) return { error: 'Shortcuts modal element missing' };

                const initDisplay = window.getComputedStyle(modal).display;
                
                // Dispatch '?' keydown
                window.dispatchEvent(new KeyboardEvent('keydown', { key: '?', code: 'Slash', shiftKey: true, bubbles: true }));
                const afterQuestion = window.getComputedStyle(modal).display;
                
                // Dispatch 'Escape' keydown
                window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', bubbles: true }));
                const afterEsc = window.getComputedStyle(modal).display;

                return {
                    initDisplay,
                    afterQuestion,
                    afterEsc
                };
            })()
        """)
        print(f"  [3.1] Shortcuts modal toggles: {shortcuts_res}", flush=True)
        assert shortcuts_res['afterQuestion'] != 'none', "Shortcuts modal did not open on '?'"
        assert shortcuts_res['afterEsc'] == 'none', "Shortcuts modal did not close on 'Escape'"
        results['shortcuts_modal'] = "PASS"

        # -------------------------------------------------------------
        # TEST 4: About & Help Page Verification
        # -------------------------------------------------------------
        print("\n--- [TEST 4] Testing about.php Navigation & Features Grid ---", flush=True)
        nav_about = await send("Page.navigate", {"url": f"{BASE_URL}/about.php"})
        await recv_response(nav_about)
        await asyncio.sleep(1.5)

        about_res = await evaluate("""
            (() => {
                const title = document.title;
                const cards = document.querySelectorAll('.glass-card');
                const panels = document.querySelectorAll('.glass-panel');
                const hasThemeToggle = !!document.querySelector('#theme-toggle');
                return {
                    title,
                    cardCount: cards.length,
                    panelCount: panels.length,
                    hasThemeToggle
                };
            })()
        """)
        print(f"  [4.1] about.php contents: {about_res}", flush=True)
        assert about_res['cardCount'] >= 4, f"Expected at least 4 feature cards, got {about_res['cardCount']}"
        assert about_res['hasThemeToggle'] is True
        results['about_page'] = "PASS"

        # -------------------------------------------------------------
        # TEST 5: 404 Error Page Verification
        # -------------------------------------------------------------
        print("\n--- [TEST 5] Testing 404.php Presentation & Return Button ---", flush=True)
        nav_404 = await send("Page.navigate", {"url": f"{BASE_URL}/404.php"})
        await recv_response(nav_404)
        await asyncio.sleep(1.0)

        notfound_res = await evaluate("""
            (() => {
                const title = document.title;
                const panel = document.querySelector('.glass-panel');
                const returnBtn = document.querySelector('a[href=\"index.php\"]');
                return {
                    title,
                    hasPanel: !!panel,
                    hasReturnBtn: !!returnBtn,
                    btnText: returnBtn ? returnBtn.innerText : ''
                };
            })()
        """)
        print(f"  [5.1] 404.php contents: {notfound_res}", flush=True)
        assert notfound_res['hasPanel'] is True
        assert notfound_res['hasReturnBtn'] is True
        results['404_page'] = "PASS"

    print("\n==================================================================", flush=True)
    print("PHASE 4 BROWSER TEST RESULTS:", flush=True)
    for test, res in results.items():
        print(f"  {test}: {res}", flush=True)
    print("OVERALL RESULT: ALL PHASE 4 BROWSER TESTS PASSED! 🎉", flush=True)
    print("==================================================================", flush=True)

if __name__ == "__main__":
    proc = launch_chrome()
    try:
        asyncio.run(run_phase_4_browser_tests())
    finally:
        try:
            proc.terminate()
        except Exception:
            pass
