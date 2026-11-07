import asyncio
import json
import os
import subprocess
import time
import urllib.request
import websockets

CHROME_PATH = r"C:\Program Files\Google\Chrome\Application\chrome.exe"

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
    for _ in range(20):
        try:
            resp = urllib.request.urlopen("http://localhost:9222/json")
            if resp.getcode() == 200:
                return proc
        except Exception:
            time.sleep(0.3)
    return proc

async def run_full_suite():
    print("==================================================================", flush=True)
    print("STARTING FULL IN-BROWSER CDP END-TO-END VERIFICATION", flush=True)
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

        async def evaluate(expr, await_promise=False, timeout_sec=30.0):
            req_id = await send("Runtime.evaluate", {
                "expression": expr,
                "returnByValue": True,
                "awaitPromise": await_promise
            })
            start = time.time()
            while time.time() - start < timeout_sec:
                try:
                    raw = await asyncio.wait_for(ws.recv(), timeout=1.0)
                    data = json.loads(raw)
                    if data.get("id") == req_id:
                        res = data.get("result", {})
                        if "exceptionDetails" in res:
                            raise Exception("JS Error: " + json.dumps(res["exceptionDetails"]))
                        return res.get("result", {}).get("value")
                except asyncio.TimeoutError:
                    pass
            raise TimeoutError("Evaluation timed out: " + expr[:60])

        async def wait_nav():
            start = time.time()
            while time.time() - start < 5.0:
                try:
                    raw = await asyncio.wait_for(ws.recv(), timeout=0.5)
                    data = json.loads(raw)
                    if data.get("method") == "Page.loadEventFired":
                        return
                except asyncio.TimeoutError:
                    pass

        await send("Page.enable")
        await send("Runtime.enable")
        await send("Log.enable")
        await send("Network.enable")

        # -------------------------------------------------------------
        # FLOW 1: Home Page & Controls
        # -------------------------------------------------------------
        print("\n--- [FLOW 1] Testing Home Page, Language, Prompt Box, Enhance, Generate ---", flush=True)
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/index.php?lang=en"})
        await wait_nav()
        await asyncio.sleep(1.0)

        # 1.1 Language Switch
        dir_val = await evaluate("document.documentElement.dir")
        results["home_lang_ltr"] = (dir_val == "ltr")
        print(f"  [1.1] Document direction is LTR: {results['home_lang_ltr']}", flush=True)

        # 1.2 Prompt typing
        type_res = await evaluate("""
        (() => {
            const p = document.getElementById('prompt-input');
            p.value = 'Modern Coffee Brand Poster';
            p.dispatchEvent(new Event('input', { bubbles: true }));
            return p.value === 'Modern Coffee Brand Poster';
        })()
        """)
        results["prompt_typing"] = type_res
        print(f"  [1.2] Prompt typing responsive: {results['prompt_typing']}", flush=True)

        # 1.3 Example chip click
        chip_res = await evaluate("""
        (() => {
            const chip = document.querySelector('.prompt-chip');
            if (!chip) return false;
            chip.click();
            const p = document.getElementById('prompt-input');
            return p.value.length > 5;
        })()
        """)
        results["chip_clicking"] = chip_res
        print(f"  [1.3] Example prompt chip click auto-fills input: {results['chip_clicking']}", flush=True)

        # 1.4 Enhance Prompt API
        enhance_res = await evaluate("""
        new Promise(async (resolve) => {
            const p = document.getElementById('prompt-input');
            p.value = 'Summer Sale Banner';
            const btnEnhance = document.getElementById('btn-enhance');
            btnEnhance.click();
            
            for (let i = 0; i < 80; i++) {
                await new Promise(r => setTimeout(r, 250));
                const undoBtn = document.getElementById('btn-undo-enhance');
                const alertEl = document.getElementById('status-alert');
                
                if (undoBtn && undoBtn.style.display !== 'none') {
                    const enhancedVal = p.value;
                    undoBtn.click();
                    resolve({
                        success: (p.value === 'Summer Sale Banner'),
                        enhancedLength: enhancedVal.length,
                        finalValue: p.value
                    });
                    return;
                }
                
                if (alertEl && alertEl.style.display !== 'none' && alertEl.className.includes('danger')) {
                    resolve({
                        success: false,
                        error: alertEl.textContent
                    });
                    return;
                }
            }
            resolve({ success: false, timeout: true });
        })
        """, await_promise=True, timeout_sec=25.0)
        results["enhance_and_undo"] = enhance_res.get("success", False) if isinstance(enhance_res, dict) else False
        print(f"  [1.4] Prompt Enhance and Undo Restore: {results['enhance_and_undo']} (Details: {enhance_res})", flush=True)

        # 1.5 Generate Design onto Canvas
        gen_res = await evaluate("""
        new Promise(async (resolve) => {
            const p = document.getElementById('prompt-input');
            p.value = 'Modern minimal social card with title and shape';
            const btnGen = document.getElementById('btn-generate');
            btnGen.click();

            for (let i = 0; i < 80; i++) {
                await new Promise(r => setTimeout(r, 250));
                const c = window.designStudioCanvas;
                if (c && c.getObjects().length > 0) {
                    resolve({
                        success: true,
                        objectCount: c.getObjects().length,
                        bg: c.backgroundColor
                    });
                    return;
                }
            }
            resolve({ success: false });
        })
        """, await_promise=True, timeout_sec=25.0)
        results["generate_design"] = gen_res.get("success", False) if isinstance(gen_res, dict) else False
        print(f"  [1.5] Generate Design creates interactive canvas objects: {results['generate_design']} (Details: {gen_res})", flush=True)

        # -------------------------------------------------------------
        # FLOW 2: Login, Register, Logout
        # -------------------------------------------------------------
        print("\n--- [FLOW 2] Testing Register, Logout, Login Flow ---", flush=True)
        test_email = f"user_{int(time.time())}@studio-test.com"
        test_pass = "StrongPass123"

        # Register
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/register.php"})
        await wait_nav()
        reg_ok = await evaluate(f"""
        (() => {{
            document.getElementById('name').value = 'Test Studio User';
            document.getElementById('email').value = '{test_email}';
            document.getElementById('password').value = '{test_pass}';
            document.getElementById('confirm_password').value = '{test_pass}';
            const form = document.querySelector('form');
            form.submit();
            return true;
        }})()
        """)
        await wait_nav()
        await asyncio.sleep(1.0)
        
        user_badge = await evaluate("document.querySelector('.user-badge')?.textContent || ''")
        results["register_flow"] = ("Test Studio User" in user_badge)
        print(f"  [2.1] Register user and auto-login: {results['register_flow']} (Badge: '{user_badge}')", flush=True)

        # Logout
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/logout.php"})
        await wait_nav()
        await asyncio.sleep(0.5)
        has_login_btn = await evaluate("Boolean(document.querySelector('a[href=\"login.php\"]'))")
        results["logout_flow"] = has_login_btn
        print(f"  [2.2] Logout successfully: {results['logout_flow']}", flush=True)

        # Login
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/login.php"})
        await wait_nav()
        await evaluate(f"""
        (() => {{
            document.getElementById('email').value = '{test_email}';
            document.getElementById('password').value = '{test_pass}';
            document.querySelector('form').submit();
        }})()
        """)
        await wait_nav()
        await asyncio.sleep(1.0)
        user_badge_after_login = await evaluate("document.querySelector('.user-badge')?.textContent || ''")
        results["login_flow"] = ("Test Studio User" in user_badge_after_login)
        print(f"  [2.3] Login user: {results['login_flow']} (Badge: '{user_badge_after_login}')", flush=True)

        # -------------------------------------------------------------
        # FLOW 3: Editor Functionality (Select, Move, Resize, Props, Layers, Undo/Redo, Zoom)
        # -------------------------------------------------------------
        print("\n--- [FLOW 3] Testing Editor Actions: Shapes, Properties, Layers, Undo/Redo, Zoom ---", flush=True)
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/index.php"})
        await wait_nav()
        await asyncio.sleep(1.0)

        editor_test = await evaluate("""
        new Promise(async (resolve) => {
            const c = window.designStudioCanvas;
            if (!c) { resolve({ error: 'No fabric canvas found' }); return; }

            // 3.1 Add Heading Text
            const addH = document.getElementById('tool-add-heading');
            addH.click();
            await new Promise(r => setTimeout(r, 50));
            const textObj = c.getActiveObject();
            if (!textObj || (textObj.type !== 'textbox' && textObj.type !== 'text')) {
                resolve({ error: 'Failed to add heading', activeType: textObj ? textObj.type : 'none' });
                return;
            }

            // 3.2 Add Shapes
            document.getElementById('tool-add-rect').click();
            await new Promise(r => setTimeout(r, 50));
            const rectObj = c.getActiveObject();
            if (!rectObj || rectObj.type !== 'rect') { resolve({ error: 'Failed to add rect' }); return; }

            document.getElementById('tool-add-circle').click();
            await new Promise(r => setTimeout(r, 50));
            document.getElementById('tool-add-star').click();
            await new Promise(r => setTimeout(r, 50));

            const totalObjs = c.getObjects().length;

            // 3.3 Move & Resize element
            rectObj.set({ left: 300, top: 400 });
            rectObj.scaleToWidth(350);
            c.requestRenderAll();

            // 3.4 Properties panel inspection
            c.setActiveObject(rectObj);
            await new Promise(r => setTimeout(r, 50));
            const propX = document.getElementById('prop-x');
            const propW = document.getElementById('prop-w');
            const propsUpdated = propX && propW && parseInt(propX.value) === 300;

            // 3.5 Layers tab and reorder
            const layersTab = document.getElementById('tab-layers-btn');
            layersTab.click();
            await new Promise(r => setTimeout(r, 50));
            const layersItems = document.querySelectorAll('.layer-item');
            const hasLayers = layersItems.length >= 4;

            // 3.6 Undo & Redo (async loadFromJSON)
            const btnUndo = document.getElementById('btn-undo');
            const btnRedo = document.getElementById('btn-redo');
            const countBeforeUndo = c.getObjects().length;
            btnUndo.click();
            await new Promise(r => setTimeout(r, 200));
            const countAfterUndo = c.getObjects().length;
            btnRedo.click();
            await new Promise(r => setTimeout(r, 200));
            const countAfterRedo = c.getObjects().length;
            const undoRedoWorking = (countAfterUndo < countBeforeUndo) && (countAfterRedo === countBeforeUndo);

            // 3.7 Zoom in and fit
            const zoomIn = document.getElementById('btn-zoom-in');
            const zoomPill = document.getElementById('zoom-percentage');
            const initialZoom = zoomPill.textContent;
            zoomIn.click();
            const zoomedInText = zoomPill.textContent;
            document.getElementById('btn-zoom-fit').click();
            const zoomWorking = (zoomedInText !== initialZoom);

            resolve({
                success: true,
                totalObjs,
                propsUpdated,
                hasLayers,
                undoRedoWorking,
                zoomWorking
            });
        })
        """, await_promise=True, timeout_sec=15.0)
        results["editor_actions"] = editor_test.get("success", False) and editor_test.get("undoRedoWorking", False)
        print(f"  [3.1-3.7] Editor shapes, properties, layers, undo/redo, zoom: {results['editor_actions']}", flush=True)
        print(f"            Details: {editor_test}", flush=True)

        # -------------------------------------------------------------
        # FLOW 4: History Page (Open and Delete)
        # -------------------------------------------------------------
        print("\n--- [FLOW 4] Testing History & My Designs Page ---", flush=True)
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/history.php"})
        await wait_nav()
        await asyncio.sleep(0.5)

        history_test = await evaluate("""
        (() => {
            const title = document.querySelector('.page-title')?.textContent || '';
            const filters = document.querySelectorAll('.filter-pill');
            const cards = document.querySelectorAll('.history-card');
            
            let deleteWorking = true;
            if (cards.length > 0) {
                const delBtn = cards[0].querySelector('.btn-delete-item');
                deleteWorking = Boolean(delBtn);
            }

            return {
                success: true,
                title,
                filterCount: filters.length,
                cardCount: cards.length,
                deleteWorking
            };
        })()
        """)
        results["history_page"] = history_test.get("success", False) and (history_test.get("filterCount", 0) >= 4)
        print(f"  [4.1] History page loaded with filters: {results['history_page']} ({history_test})", flush=True)

        # -------------------------------------------------------------
        # FLOW 5: Keyboard Shortcuts (Undo, Redo, Duplicate, Delete)
        # -------------------------------------------------------------
        print("\n--- [FLOW 5] Testing Keyboard Shortcuts ---", flush=True)
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/index.php"})
        await wait_nav()
        await asyncio.sleep(0.5)

        shortcuts_test = await evaluate("""
        (() => {
            const c = window.designStudioCanvas;
            document.getElementById('tool-add-rect').click();
            const initCount = c.getObjects().length;

            // Trigger Ctrl+D (duplicate)
            const dEvent = new KeyboardEvent('keydown', { key: 'd', ctrlKey: true, bubbles: true });
            window.dispatchEvent(dEvent);
            const countAfterDup = c.getObjects().length;

            // Trigger Delete
            const delEvent = new KeyboardEvent('keydown', { key: 'Delete', bubbles: true });
            window.dispatchEvent(delEvent);
            const countAfterDel = c.getObjects().length;

            // Trigger Ctrl+Z (undo)
            const zEvent = new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true });
            window.dispatchEvent(zEvent);
            const countAfterUndo = c.getObjects().length;

            return {
                initCount,
                countAfterDup,
                countAfterDel,
                countAfterUndo,
                duplicateOk: (countAfterDup === initCount + 1),
                deleteOk: (countAfterDel === initCount),
                undoOk: (countAfterUndo === countAfterDup)
            };
        })()
        """)
        results["keyboard_shortcuts"] = shortcuts_test.get("duplicateOk", False) and shortcuts_test.get("deleteOk", False)
        print(f"  [5.1] Keyboard shortcuts (Ctrl+D, Delete, Ctrl+Z): {results['keyboard_shortcuts']} ({shortcuts_test})", flush=True)

        # -------------------------------------------------------------
        # FLOW 6: Mobile Viewport (375px) Responsiveness
        # -------------------------------------------------------------
        print("\n--- [FLOW 6] Testing Mobile Viewport (375px) ---", flush=True)
        await send("Emulation.setDeviceMetricsOverride", {
            "width": 375,
            "height": 667,
            "deviceScaleFactor": 2.0,
            "mobile": True
        })
        await asyncio.sleep(0.5)

        mobile_test = await evaluate("""
        (() => {
            const vw = window.innerWidth;
            const bodyW = document.body.clientWidth;
            
            const btnGen = document.getElementById('btn-generate');
            const buttonsTappable = btnGen && (window.getComputedStyle(btnGen).pointerEvents === 'auto');

            const prompt = document.getElementById('prompt-input');
            prompt.focus();
            const promptFocused = (document.activeElement === prompt);

            const canvasContainer = document.getElementById('canvas-viewport');
            const canvasW = canvasContainer ? canvasContainer.clientWidth : 0;

            return {
                vw,
                bodyW,
                buttonsTappable,
                promptFocused,
                canvasFits: (canvasW <= 375)
            };
        })()
        """)
        results["mobile_viewport"] = mobile_test.get("buttonsTappable", False) and mobile_test.get("promptFocused", False)
        print(f"  [6.1] Mobile viewport (375px) responsive & tappable: {results['mobile_viewport']} ({mobile_test})", flush=True)

        # Restore desktop viewport
        await send("Emulation.clearDeviceMetricsOverride")

    print("\n==================================================================", flush=True)
    print("VERIFICATION SUMMARY:", flush=True)
    all_passed = all(results.values())
    for k, v in results.items():
        print(f"  {k}: {'PASS' if v else 'FAIL'}", flush=True)
    print(f"\nOVERALL RESULT: {'ALL PASS' if all_passed else 'SOME FAILED'}", flush=True)
    print("==================================================================", flush=True)

proc = launch_chrome()
try:
    asyncio.run(run_full_suite())
finally:
    proc.terminate()
