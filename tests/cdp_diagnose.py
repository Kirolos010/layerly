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
            data = json.loads(resp.read().decode())
            if data:
                print("Chrome CDP ready.", flush=True)
                return proc
        except Exception:
            time.sleep(0.3)
    return proc

async def diagnose_page(url):
    print(f"\n==========================================", flush=True)
    print(f"DIAGNOSING URL: {url}", flush=True)
    print(f"==========================================", flush=True)
    
    try:
        req = urllib.request.Request("http://localhost:9222/json/new?" + urllib.request.quote(url), method="PUT")
        resp = urllib.request.urlopen(req)
        target = json.loads(resp.read().decode())
        ws_url = target['webSocketDebuggerUrl']
        target_id = target['id']
        print(f"Created tab: {target_id}", flush=True)
    except Exception as e:
        print(f"Fallback to existing tab: {e}", flush=True)
        resp = urllib.request.urlopen("http://localhost:9222/json")
        pages = json.loads(resp.read().decode())
        page = next((p for p in pages if p.get('type') == 'page'), pages[0])
        ws_url = page['webSocketDebuggerUrl']
        target_id = page['id']

    async with websockets.connect(ws_url) as ws:
        msg_id = 0
        async def send(method, params=None):
            nonlocal msg_id
            msg_id += 1
            payload = {"id": msg_id, "method": method, "params": params or {}}
            await ws.send(json.dumps(payload))
            return msg_id

        await send("Page.enable")
        await send("Runtime.enable")
        await send("Log.enable")
        await send("Network.enable")

        await send("Page.navigate", {"url": url})

        console_logs = []
        network_errors = []
        exceptions = []

        start_time = asyncio.get_event_loop().time()
        while asyncio.get_event_loop().time() - start_time < 3.5:
            try:
                msg = await asyncio.wait_for(ws.recv(), timeout=0.5)
                data = json.loads(msg)
                method = data.get("method", "")
                params = data.get("params", {})

                if method == "Runtime.consoleAPICalled":
                    log_type = params.get("type")
                    args = " ".join(str(a.get("value", a.get("description", ""))) for a in params.get("args", []))
                    console_logs.append(f"[{log_type.upper()}] {args}")
                    print(f"  CONSOLE [{log_type.upper()}]: {args}", flush=True)

                elif method == "Runtime.exceptionThrown":
                    details = params.get("exceptionDetails", {})
                    text = details.get("text", "")
                    exc = details.get("exception", {}).get("description", "")
                    url_err = details.get("url", "")
                    line = details.get("lineNumber", -1)
                    col = details.get("columnNumber", -1)
                    err_str = f"EXCEPTION: {text} - {exc} at {url_err}:{line}:{col}"
                    exceptions.append(err_str)
                    print(f"  >>> RUNTIME ERROR: {err_str}", flush=True)

                elif method == "Network.responseReceived":
                    resp = params.get("response", {})
                    status = resp.get("status", 200)
                    req_url = resp.get("url", "")
                    if status >= 400:
                        network_errors.append(f"HTTP {status} on {req_url}")
                        print(f"  >>> NETWORK ERROR: HTTP {status} on {req_url}", flush=True)

                elif method == "Network.loadingFailed":
                    req_url = params.get("url", "")
                    err_text = params.get("errorText", "")
                    network_errors.append(f"FAILED {req_url}: {err_text}")
                    print(f"  >>> NETWORK FAILED: {req_url}: {err_text}", flush=True)

            except asyncio.TimeoutError:
                pass

        eval_script = """
        (() => {
            const results = {};
            const cx = Math.floor(window.innerWidth / 2);
            const cy = Math.floor(window.innerHeight / 2);
            const elAtCenter = document.elementFromPoint(cx, cy);
            results.elementCenter = elAtCenter ? elAtCenter.tagName + '#' + elAtCenter.id + '.' + elAtCenter.className : 'none';

            const btnEnhance = document.getElementById('btn-enhance');
            results.btnEnhance = btnEnhance ? {
                disabled: btnEnhance.disabled,
                offsetParent: Boolean(btnEnhance.offsetParent),
                pointerEvents: window.getComputedStyle(btnEnhance).pointerEvents
            } : 'not found';

            const prompt = document.getElementById('prompt-input');
            results.hasPrompt = Boolean(prompt);
            if (prompt) {
                prompt.focus();
                results.promptFocused = (document.activeElement === prompt);
            }

            const modals = Array.from(document.querySelectorAll('.modal-overlay'));
            results.modals = modals.map(m => ({
                id: m.id,
                display: window.getComputedStyle(m).display,
                zIndex: window.getComputedStyle(m).zIndex,
                visibility: window.getComputedStyle(m).visibility,
                pointerEvents: window.getComputedStyle(m).pointerEvents
            }));

            const tabLayers = document.getElementById('tab-layers-btn');
            if (tabLayers) {
                tabLayers.click();
                const pane = document.getElementById('tab-layers-pane');
                results.tabLayersSwitched = pane && pane.classList.contains('active');
            }

            return results;
        })()
        """
        eval_id = await send("Runtime.evaluate", {"expression": eval_script, "returnByValue": True})
        
        start_time = asyncio.get_event_loop().time()
        while asyncio.get_event_loop().time() - start_time < 2.0:
            try:
                msg = await asyncio.wait_for(ws.recv(), timeout=0.5)
                data = json.loads(msg)
                if data.get("id") == eval_id:
                    res = data.get("result", {}).get("result", {}).get("value", {})
                    print("\nDOM INTERACTIVITY DIAGNOSTICS:", flush=True)
                    print(json.dumps(res, indent=2), flush=True)
                    break
            except Exception:
                pass

        print(f"\nSUMMARY FOR {url}:", flush=True)
        print(f"Exceptions: {len(exceptions)}", flush=True)
        print(f"Console logs: {len(console_logs)}", flush=True)
        print(f"Network errors: {len(network_errors)}", flush=True)

    try:
        req = urllib.request.Request(f"http://localhost:9222/json/close/{target_id}", method="PUT")
        urllib.request.urlopen(req)
    except Exception:
        pass

async def main():
    proc = launch_chrome()
    try:
        urls = [
            "http://localhost/layerly/index.php",
            "http://localhost/layerly/login.php",
            "http://localhost/layerly/register.php",
            "http://localhost/layerly/history.php",
            "http://localhost/layerly/profile.php"
        ]
        for u in urls:
            await diagnose_page(u)
    finally:
        proc.terminate()

asyncio.run(main())
