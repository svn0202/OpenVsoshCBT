#!/usr/bin/env python3
"""Check legacy asset URLs and service-worker update without navigating an exam."""
import functools, http.server, json, pathlib, threading
from urllib.parse import urljoin, urlsplit
from playwright.sync_api import sync_playwright
ROOT = pathlib.Path(__file__).resolve().parents[1]

class Handler(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *args): pass
    def do_GET(self):
        if self.path in ('/legacy-fixture', '/public/fixture', '/public/code/probe'):
            body = b'<html><body><textarea>KEEP_CURRENT_ANSWER</textarea></body></html>'
            self.send_response(200); self.send_header('Content-Type', 'text/html')
            self.end_headers(); self.wfile.write(body); return
        super().do_GET()

server = http.server.ThreadingHTTPServer(('127.0.0.1',0), functools.partial(Handler,directory=str(ROOT)))
threading.Thread(target=server.serve_forever,daemon=True).start()
base = 'http://127.0.0.1:%d' % server.server_port
try:
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        context = browser.new_context(); page = context.new_page()
        for path in ('/apple-touch-icon.png','/apple-touch-icon-precomposed.png', '/styles/picoman.css','/styles/tmf-reference.css','/sw.js'):
            response = context.request.get(base+path); assert response.ok, path
        for path in ('/a2hs/site.webmanifest','/public/manifest.webmanifest'):
            response = context.request.get(base+path); assert response.ok
            manifest = response.json()
            for icon in manifest['icons']:
                assert context.request.get(urljoin(base+path,icon['src'])).ok
            assert urlsplit(urljoin(base+path,manifest['scope'])).path=='/public/'
        page.goto(base+'/legacy-fixture')
        page.evaluate("navigator.serviceWorker.register('/sw.js')")
        page.wait_for_function("async () => (await navigator.serviceWorker.getRegistrations()).length === 0")
        assert page.locator('textarea').input_value()=='KEEP_CURRENT_ANSWER'
        assert page.url.endswith('/legacy-fixture')
        page.goto(base+'/public/fixture')
        page.evaluate("async () => {await navigator.serviceWorker.register('/public/sw.js',{scope:'/public/',updateViaCache:'none'}); await navigator.serviceWorker.ready;}")
        page.wait_for_function('navigator.serviceWorker.controller !== null')
        page.evaluate("fetch('/public/code/probe').then(r=>r.text())")
        cached = page.evaluate("async () => {let urls=[];for(const name of await caches.keys()){for(const r of await (await caches.open(name)).keys())urls.push(r.url);}return urls;}")
        assert all('/code/' not in url and '/fixture' not in url for url in cached), cached
        assert page.locator('textarea').input_value()=='KEEP_CURRENT_ANSWER'
        browser.close()
    print('Release assets: compatibility URLs, manifest icons, worker retirement and private-cache exclusion passed')
finally:
    server.shutdown(); server.server_close()
