#!/usr/bin/env python3
"""A controlling worker must preserve browser navigation and POST request semantics."""
import functools
import http.server
import threading
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]


class Handler(http.server.SimpleHTTPRequestHandler):
    records = []

    def log_message(self, *args):
        pass

    def do_GET(self):
        if self.path.startswith('/public/code/probe'):
            self.records.append(('GET', dict(self.headers)))
            body = b'<html><form method="post"><textarea>KEEP_DRAFT</textarea><button>Send</button></form></html>'
            self.send_response(200)
            self.send_header('Content-Type', 'text/html')
            self.send_header('Cache-Control', 'no-store')
            self.end_headers()
            self.wfile.write(body)
        else:
            super().do_GET()

    def do_POST(self):
        self.rfile.read(int(self.headers.get('Content-Length', '0')))
        self.records.append(('POST', dict(self.headers)))
        self.send_response(200)
        self.send_header('Content-Type', 'text/html')
        self.end_headers()
        self.wfile.write(b'<html>Submitted</html>')


server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(Handler, directory=str(ROOT)))
threading.Thread(target=server.serve_forever, daemon=True).start()
base = f'http://127.0.0.1:{server.server_port}'
try:
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        context = browser.new_context(locale='ru-RU')
        page = context.new_page()
        page.goto(base + '/public/code/probe')
        page.evaluate("async () => { await navigator.serviceWorker.register('/public/sw.js', {scope:'/public/'}); await navigator.serviceWorker.ready; }")
        page.wait_for_function('navigator.serviceWorker.controller !== null')
        assert page.locator('textarea').input_value() == 'KEEP_DRAFT'
        page.reload()
        page.locator('button').click()
        page.wait_for_function("document.body.textContent === 'Submitted'")
        assert [method for method, _ in Handler.records] == ['GET', 'GET', 'POST']
        for method, headers in Handler.records:
            assert headers.get('Sec-Fetch-Mode') == 'navigate', (method, headers)
            assert headers.get('Sec-Fetch-Dest') == 'document', (method, headers)
            assert not headers.get('Referer', '').endswith('/sw.js'), (method, headers)
        cached = page.evaluate("async () => {let urls=[];for(const name of await caches.keys())for(const req of await (await caches.open(name)).keys())urls.push(req.url);return urls;}")
        assert not any('/code/' in url for url in cached), cached
        browser.close()
    print('Service worker: controlled GET/POST preserve document headers; private pages are not cached')
finally:
    server.shutdown()
    server.server_close()
