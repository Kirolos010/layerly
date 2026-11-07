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

async def run_browser_phase_3():
    print("==================================================================", flush=True)
    print("RUNNING PHASE 3 IN-BROWSER CDP END-TO-END VERIFICATION", flush=True)
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

        async def evaluate(expr, await_promise=False, timeout_sec=25.0):
            req_id = await send("Runtime.evaluate", {
                "expression": expr,
                "returnByValue": True,
                "awaitPromise": await_promise
            })
            start = time.time()
            while time.time() - start < timeout_sec:
                try:
                    raw = await asyncio.wait_for(ws.recv(), timeout=1.0)
                    msg = json.loads(raw)
                    if msg.get("id") == req_id:
                        result = msg.get("result", {})
                        if "exceptionDetails" in result:
                            print(f"JS Exception: {result['exceptionDetails']}", flush=True)
                            return None
                        return result.get("result", {}).get("value")
                except asyncio.TimeoutError:
                    pass
            return None

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

        # -------------------------------------------------------------
        # FLOW 1: Studio Home, Project Name & Export Modal Interactivity
        # -------------------------------------------------------------
        print("\n--- [FLOW 1] Testing Export Modal & Project Controls on index.php ---", flush=True)
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/index.php"})
        await wait_nav()
        await asyncio.sleep(1.5)

        export_test = await evaluate("""
        (async () => {
            const btnOpen = document.getElementById('btn-open-export-modal');
            const modal = document.getElementById('export-modal');
            const btnClose = document.getElementById('close-export-modal');
            
            if (!btnOpen || !modal || !btnClose) return { error: 'Modal elements missing' };
            
            // Check modal initially hidden
            const initiallyHidden = window.getComputedStyle(modal).display === 'none';
            
            // Click open
            btnOpen.click();
            await new Promise(r => setTimeout(r, 200));
            const openedDisplay = window.getComputedStyle(modal).display;
            
            // Check dimension preview updates when scale changes
            const scaleSelect = document.getElementById('export-png-scale');
            if (scaleSelect) {
                scaleSelect.value = '2';
                scaleSelect.dispatchEvent(new Event('change'));
            }
            const pngDim = document.getElementById('png-dimensions-preview')?.textContent || '';
            
            // Close modal
            btnClose.click();
            await new Promise(r => setTimeout(r, 200));
            const closedDisplay = window.getComputedStyle(modal).display;
            
            return {
                initiallyHidden,
                opened: (openedDisplay === 'flex' || openedDisplay === 'block'),
                pngDim,
                closed: (closedDisplay === 'none')
            };
        })()
        """, await_promise=True)

        print(f"  [1.1] Export modal open, scale preview & close: {export_test}", flush=True)
        results["export_modal"] = (
            export_test is not None and
            export_test.get("initiallyHidden") and 
            export_test.get("opened") and 
            "2160" in export_test.get("pngDim", "") and 
            export_test.get("closed")
        )

        # -------------------------------------------------------------
        # FLOW 2: Manual Project Saving from Editor
        # -------------------------------------------------------------
        print("\n--- [FLOW 2] Testing Manual Project Save via MySQL ---", flush=True)
        save_test = await evaluate("""
        (async () => {
            const nameInput = document.getElementById('project-name-input');
            const btnSave = document.getElementById('btn-save-project');
            if (!nameInput || !btnSave) return { error: 'Save controls missing' };
            
            nameInput.value = 'CDP Test Project ' + Date.now();
            btnSave.click();
            
            // Wait up to 5s for save status
            for (let i = 0; i < 50; i++) {
                await new Promise(r => setTimeout(r, 100));
                const statusEl = document.getElementById('save-status-text');
                if (statusEl && statusEl.textContent.includes('Saved')) {
                    return { success: true, status: statusEl.textContent, url: window.location.href };
                }
            }
            return { success: false, status: document.getElementById('save-status-text')?.textContent };
        })()
        """, await_promise=True, timeout_sec=15.0)

        print(f"  [2.1] Project saved to MySQL: {save_test}", flush=True)
        results["project_save"] = (save_test is not None and save_test.get("success", False))

        # -------------------------------------------------------------
        # FLOW 3: Templates Gallery & Loading Template into Editor
        # -------------------------------------------------------------
        print("\n--- [FLOW 3] Testing Templates Gallery (templates.php) ---", flush=True)
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/templates.php"})
        await wait_nav()
        await asyncio.sleep(1.0)

        templates_test = await evaluate("""
        (() => {
            const cards = document.querySelectorAll('.template-card');
            const catPills = document.querySelectorAll('.filter-pill');
            const searchInput = document.getElementById('template-search-input');
            
            const totalCount = cards.length;
            
            // Test category filter click
            const storyPill = Array.from(catPills).find(p => p.dataset.category === 'story');
            if (storyPill) storyPill.click();
            const visibleStories = Array.from(cards).filter(c => c.style.display !== 'none').length;
            
            // Reset filter
            const allPill = Array.from(catPills).find(p => p.dataset.category === 'all');
            if (allPill) allPill.click();
            
            // Test search filter
            searchInput.value = 'Ramadan';
            searchInput.dispatchEvent(new Event('input'));
            const visibleSearch = Array.from(cards).filter(c => c.style.display !== 'none').length;
            
            return {
                totalCount,
                visibleStories,
                visibleSearch
            };
        })()
        """)
        print(f"  [3.1] Templates Gallery filtering: {templates_test}", flush=True)
        results["templates_gallery"] = (
            templates_test is not None and
            templates_test.get("totalCount", 0) >= 10 and 
            templates_test.get("visibleStories", 0) > 0 and 
            templates_test.get("visibleSearch", 0) >= 1
        )

        # Load Template into Editor
        print("\n--- [FLOW 4] Loading Template (ramadan-sale-post) into Studio Canvas ---", flush=True)
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/index.php?template=ramadan-sale-post"})
        await wait_nav()
        await asyncio.sleep(2.0)

        load_test = await evaluate("""
        (() => {
            const canvas = window.designStudioCanvas;
            if (!canvas) return { error: 'Canvas instance not found on window' };
            
            const objects = canvas.getObjects();
            const projName = document.getElementById('project-name-input')?.value || '';
            
            return {
                objectCount: objects.length,
                projectName: projName,
                canvasWidth: canvas.getWidth(),
                canvasHeight: canvas.getHeight()
            };
        })()
        """)
        print(f"  [4.1] Template loaded into Fabric.js: {load_test}", flush=True)
        results["template_loaded"] = (
            load_test is not None and
            load_test.get("objectCount", 0) >= 5 and 
            len(load_test.get("projectName", "")) > 0
        )

        # -------------------------------------------------------------
        # FLOW 5: "My Designs" Projects Page Interactivity
        # -------------------------------------------------------------
        print("\n--- [FLOW 5] Testing 'My Designs' Page (projects.php) ---", flush=True)
        await send("Page.navigate", {"url": "http://localhost/ai-design-studio/projects.php"})
        await wait_nav()
        await asyncio.sleep(1.0)

        projects_test = await evaluate("""
        (() => {
            const cards = document.querySelectorAll('.project-card');
            const searchInput = document.getElementById('project-search-input');
            const countEl = document.getElementById('showing-count');
            
            return {
                projectCount: cards.length,
                hasSearch: Boolean(searchInput),
                hasCountPill: Boolean(countEl)
            };
        })()
        """)
        print(f"  [5.1] 'My Designs' grid & components: {projects_test}", flush=True)
        results["projects_page"] = (
            projects_test is not None and
            projects_test.get("projectCount", 0) >= 1 and 
            projects_test.get("hasSearch", False)
        )

    print("\n==================================================================", flush=True)
    print("PHASE 3 BROWSER VERIFICATION SUMMARY:", flush=True)
    all_passed = all(results.values())
    for k, v in results.items():
        print(f"  {k}: {'PASS' if v else 'FAIL'}", flush=True)
    print(f"\nOVERALL RESULT: {'ALL PASS' if all_passed else 'SOME FAILED'}", flush=True)
    print("==================================================================", flush=True)

    if not all_passed:
        exit(1)

proc = launch_chrome()
try:
    asyncio.run(run_browser_phase_3())
finally:
    proc.terminate()
