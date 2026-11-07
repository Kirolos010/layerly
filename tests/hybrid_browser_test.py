import asyncio
import json
import os
import subprocess
import time
import urllib.request
import websockets

CHROME_PATH = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
BASE_URL = "http://localhost/layerly"

# 100x100 PNG test image (red/blue square with white text area)
TEST_IMAGE_DATA_URL = (
    "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAYAAABw4pVUAAAAAXNSR0IArs4c6QAAAARnQU1BAACx"
    "jwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAC0SURBVHhe7dAxEQAgEMDAwZ8qFvA7mK2Vnbm7v33vC5E0kiZpJE3SSJqk"
    "kTSpJE3SSJqkkTRJI2mSRtIkkjRJkzaSJmkkTdJImqSRNEkjSZNJ0kTSJEmTJkkjaZJG0iSNpEkaSZNI0iRN0kiapJE0SSNp"
    "kqaTNEkaSZIkaZIkSZJGkiRJkiRJIkmSJEmSJEmSJEmSJEmaTNIkaSRN0kiapJE0SSNpkkbSJI2kSRpJk7Tufc3kQvX+30MA"
    "AAAASUVORK5CYII="
)

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

async def run_phase_5_browser_tests():
    print("==================================================================", flush=True)
    print("STARTING PHASE 5 HYBRID & VISION BROWSER VERIFICATION (CHROME CDP)", flush=True)
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
        # TEST 1: Generator Bar "Generate AI background" Toggle
        # -------------------------------------------------------------
        print("\n--- [TEST 1] Testing #generate-bg-toggle in English ---", flush=True)
        nav_id = await send("Page.navigate", {"url": f"{BASE_URL}/index.php?lang=en"})
        await recv_response(nav_id)
        await asyncio.sleep(2.0)

        t1_check = await evaluate("""
            (() => {
                const toggle = document.querySelector('#generate-bg-toggle');
                const label = toggle ? toggle.closest('label') : null;
                const promptInput = document.querySelector('#prompt-input');
                return {
                    hasToggle: !!toggle,
                    toggleChecked: toggle ? toggle.checked : false,
                    labelHtml: label ? label.innerText.trim() : '',
                    hasPrompt: !!promptInput
                };
            })()
        """)
        print(f"  [1.1] Toggle check: {t1_check}", flush=True)
        assert t1_check['hasToggle'] is True, "Missing #generate-bg-toggle"
        assert "Background" in t1_check['labelHtml'] or "AI" in t1_check['labelHtml']

        # Toggle state test
        toggle_toggled = await evaluate("""
            (() => {
                const toggle = document.querySelector('#generate-bg-toggle');
                toggle.checked = true;
                toggle.dispatchEvent(new Event('change', { bubbles: true }));
                return toggle.checked;
            })()
        """)
        assert toggle_toggled is True
        print(f"  [1.2] Toggle successfully switched to checked: {toggle_toggled}", flush=True)
        results['generator_bg_toggle'] = "PASS"

        # -------------------------------------------------------------
        # TEST 2: Add Image to Canvas & Verify Properties Panel Controls
        # -------------------------------------------------------------
        print("\n--- [TEST 2] Adding Image Layer & Verifying Phase 5 Controls ---", flush=True)

        img_add_res = await evaluate(f"""
            new Promise(async (resolve) => {{
                let canvas = window.designStudioCanvas;
                for (let i = 0; i < 40; i++) {{
                    if (window.designStudioCanvas) {{
                        canvas = window.designStudioCanvas;
                        break;
                    }}
                    await new Promise(r => setTimeout(r, 100));
                }}
                if (!canvas) return resolve({{ error: 'Canvas not found' }});

                fabric.Image.fromURL('{TEST_IMAGE_DATA_URL}', (img) => {{
                    img.set({{
                        left: 200,
                        top: 200,
                        name: 'Test Poster Image'
                    }});
                    img.customType = 'image';
                    canvas.add(img);
                    canvas.setActiveObject(img);
                    canvas.requestRenderAll();

                    // Switch to properties tab
                    const tabProps = document.querySelector('#tab-properties-btn');
                    if (tabProps) tabProps.click();

                    setTimeout(() => {{
                        const btnSep = document.querySelector('#btn-separate-subject');
                        const btnExt = document.querySelector('#btn-extract-text');
                        const chkMask = document.querySelector('#check-mask-text');
                        resolve({{
                            objectCount: canvas.getObjects().length,
                            activeType: canvas.getActiveObject() ? canvas.getActiveObject().type : null,
                            hasBtnSeparate: !!btnSep,
                            btnSeparateText: btnSep ? btnSep.innerText.trim() : '',
                            hasBtnExtract: !!btnExt,
                            btnExtractText: btnExt ? btnExt.innerText.trim() : '',
                            hasChkMask: !!chkMask,
                            chkMaskChecked: chkMask ? chkMask.checked : false
                        }});
                    }}, 200);
                }});
            }})
        """)
        print(f"  [2.1] Image added & properties panel inspection: {img_add_res}", flush=True)
        assert img_add_res['objectCount'] >= 1
        assert img_add_res['hasBtnSeparate'] is True, "Missing #btn-separate-subject in properties panel"
        assert img_add_res['hasBtnExtract'] is True, "Missing #btn-extract-text in properties panel"
        assert img_add_res['hasChkMask'] is True, "Missing #check-mask-text in properties panel"
        assert img_add_res['chkMaskChecked'] is True, "#check-mask-text should default to checked"
        results['image_properties_controls'] = "PASS"

        # -------------------------------------------------------------
        # TEST 3: Click "Separate Main Subject" (Background Removal)
        # -------------------------------------------------------------
        print("\n--- [TEST 3] Testing 'Separate Main Subject' Action ---", flush=True)

        sep_action_res = await evaluate("""
            new Promise((resolve) => {
                const btnSep = document.querySelector('#btn-separate-subject');
                if (!btnSep) return resolve({ error: 'btnSep missing' });

                btnSep.click();

                // Poll for new cutout object on canvas
                let checks = 0;
                const interval = setInterval(() => {
                    checks++;
                    const canvas = window.designStudioCanvas;
                    const objects = canvas.getObjects();
                    const hasCutout = objects.some(o => (o.name || '').includes('(Subject)'));

                    if (hasCutout || checks >= 30) {
                        clearInterval(interval);
                        resolve({
                            hasCutout,
                            totalObjects: objects.length,
                            objectNames: objects.map(o => o.name)
                        });
                    }
                }, 300);
            })
        """)
        print(f"  [3.1] Separate subject response: {sep_action_res}", flush=True)
        assert sep_action_res['hasCutout'] is True, "Cutout layer was not added to the canvas"
        assert sep_action_res['totalObjects'] >= 2, "Original image and cutout should both exist"
        results['separate_main_subject'] = "PASS"

        # -------------------------------------------------------------
        # TEST 4: Click "Extract Text from Image" (Vision OCR)
        # -------------------------------------------------------------
        print("\n--- [TEST 4] Testing 'Extract Text from Image' Action ---", flush=True)

        extract_action_res = await evaluate("""
            new Promise((resolve) => {
                const canvas = window.designStudioCanvas;
                const objects = canvas.getObjects();
                // Select original image again
                const origImg = objects.find(o => o.name === 'Test Poster Image');
                if (!origImg) return resolve({ error: 'Original image not found' });
                canvas.setActiveObject(origImg);
                canvas.requestRenderAll();

                setTimeout(() => {
                    const btnExt = document.querySelector('#btn-extract-text');
                    const chkMask = document.querySelector('#check-mask-text');
                    if (chkMask) chkMask.checked = true; // Ensure masking is active

                    if (!btnExt) return resolve({ error: 'btnExt missing' });

                    btnExt.click();

                    let checks = 0;
                    const interval = setInterval(() => {
                        checks++;
                        const currentObjects = canvas.getObjects();
                        const textboxes = currentObjects.filter(o => o.type === 'textbox');
                        const masks = currentObjects.filter(o => (o.name || '').startsWith('Mask:'));

                        if (textboxes.length > 0 || checks >= 30) {
                            clearInterval(interval);
                            resolve({
                                textboxesCount: textboxes.length,
                                masksCount: masks.length,
                                totalObjects: currentObjects.length,
                                textSnippets: textboxes.map(t => t.text)
                            });
                        }
                    }, 300);
                }, 200);
            })
        """)
        print(f"  [4.1] Extract text response: {extract_action_res}", flush=True)
        assert extract_action_res['textboxesCount'] >= 1, "Extracted textboxes were not added to canvas"
        assert extract_action_res['masksCount'] >= 1, "Masking rectangles were not added to canvas"
        print(f"  [4.2] Extracted texts: {extract_action_res['textSnippets']}", flush=True)
        results['extract_text_ocr'] = "PASS"

        # -------------------------------------------------------------
        # TEST 5: Verify Layers Panel Reflects Objects
        # -------------------------------------------------------------
        print("\n--- [TEST 5] Testing Layers Panel Synchronization ---", flush=True)

        layers_res = await evaluate("""
            (() => {
                const tabLayers = document.querySelector('#tab-layers-btn');
                if (tabLayers) tabLayers.click();

                const container = document.querySelector('#layers-container');
                const items = container ? container.querySelectorAll('.layer-item') : [];
                const itemTexts = Array.from(items).map(i => i.innerText.trim());

                return {
                    layerCount: items.length,
                    itemTexts
                };
            })()
        """)
        print(f"  [5.1] Layers inspection: {layers_res}", flush=True)
        assert layers_res['layerCount'] >= 3, "Layers panel should list image, cutout, mask, text"
        results['layers_panel_sync'] = "PASS"

        # -------------------------------------------------------------
        # TEST 6: Arabic & Native RTL Verification
        # -------------------------------------------------------------
        print("\n--- [TEST 6] Testing Phase 5 Features in Arabic (RTL) ---", flush=True)
        nav_ar = await send("Page.navigate", {"url": f"{BASE_URL}/index.php?lang=ar"})
        await recv_response(nav_ar)
        await asyncio.sleep(2.0)

        ar_test_res = await evaluate(f"""
            new Promise(async (resolve) => {{
                const toggle = document.querySelector('#generate-bg-toggle');
                const toggleLabel = toggle ? toggle.closest('label').innerText.trim() : '';

                let canvas = window.designStudioCanvas;
                for (let i = 0; i < 40; i++) {{
                    if (window.designStudioCanvas) {{
                        canvas = window.designStudioCanvas;
                        break;
                    }}
                    await new Promise(r => setTimeout(r, 100));
                }}
                if (!canvas) return resolve({{ error: 'Canvas not found' }});

                fabric.Image.fromURL('{TEST_IMAGE_DATA_URL}', (img) => {{
                    img.set({{ left: 150, top: 150, name: 'Arabic Test Image' }});
                    img.customType = 'image';
                    canvas.add(img);
                    canvas.setActiveObject(img);
                    canvas.requestRenderAll();

                    const tabProps = document.querySelector('#tab-properties-btn');
                    if (tabProps) tabProps.click();

                    setTimeout(() => {{
                        const btnSep = document.querySelector('#btn-separate-subject');
                        const btnExt = document.querySelector('#btn-extract-text');
                        const chkMask = document.querySelector('#check-mask-text');
                        const maskLabel = chkMask ? chkMask.closest('label').innerText.trim() : '';

                        resolve({{
                            dir: document.documentElement.getAttribute('dir'),
                            toggleLabel,
                            btnSepText: btnSep ? btnSep.innerText.trim() : '',
                            btnExtText: btnExt ? btnExt.innerText.trim() : '',
                            maskLabel
                        }});
                    }}, 200);
                }});
            }})
        """)
        print(f"  [6.1] Arabic UI inspection: {ar_test_res}", flush=True)
        assert ar_test_res['dir'] == 'rtl'
        assert "خلفية" in ar_test_res['toggleLabel']
        assert "فصل العنصر" in ar_test_res['btnSepText']
        assert "استخراج النصوص" in ar_test_res['btnExtText']
        results['arabic_rtl_phase5'] = "PASS"

    print("\n==================================================================", flush=True)
    print("PHASE 5 BROWSER TEST RESULTS:", flush=True)
    for test, res in results.items():
        print(f"  {test}: {res}", flush=True)
    print("OVERALL RESULT: ALL PHASE 5 BROWSER TESTS PASSED! 🎉", flush=True)
    print("==================================================================", flush=True)

if __name__ == "__main__":
    proc = launch_chrome()
    try:
        asyncio.run(run_phase_5_browser_tests())
    finally:
        try:
            proc.terminate()
        except Exception:
            pass
