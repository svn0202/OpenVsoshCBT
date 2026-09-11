#!/usr/bin/env python3
"""Check matching answers with long choices at desktop/mobile breakpoints.

Run with python3 test/browser_matching_regression.py (Playwright required).
Uses repository assets and synthetic answer text, without a database.
"""

import functools
import json
import threading
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

from playwright.sync_api import sync_playwright


ROOT = Path(__file__).resolve().parent.parent
SIZES = [(390, 844), (575, 900), (576, 900), (768, 1024), (1024, 768), (1366, 768), (1745, 1000), (1920, 1080)]
CHOICE = 'Длинное объяснение ситуации, которое должно оставаться доступным участнику. ' * 3
HTML = f'''<!doctype html><html lang="ru"><head><meta charset="utf-8">
<link rel="stylesheet" href="/public/styles/picoman.css">
<link rel="stylesheet" href="/public/styles/tmf-reference.css"></head>
<body class="app-page exam-page"><main class="body"><div class="container">
<form id="testform"><div class="tcecontentbox"><fieldset class="answergroup">
<ol class="answer"><li class="exam-matching-answer">
<select id="matching" class="matching-position" name="answpos[1]">
<option value="0"></option><option value="1">{CHOICE}</option>
<option value="2">Другое объяснение.</option></select>
<label for="matching">Описание ситуации: участник должен прочитать весь текст
и выбрать соответствующее объяснение, не прокручивая страницу по горизонтали.</label>
</li></ol></fieldset></div></form></div></main>
<script src="/shared/jscripts/mobile-exam.js"></script></body></html>'''


class QuietHandler(SimpleHTTPRequestHandler):
    def log_message(self, *args):
        pass


def main():
    server = ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(QuietHandler, directory=str(ROOT)))
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    results = []
    try:
        with sync_playwright() as playwright:
            for name in ('chromium', 'firefox', 'webkit'):
                browser = getattr(playwright, name).launch()
                try:
                    page = browser.new_page()
                    page.route('**/matching-fixture', lambda route: route.fulfill(content_type='text/html', body=HTML))
                    page.goto(f'http://127.0.0.1:{server.server_port}/matching-fixture')
                    for width, height in SIZES:
                        page.set_viewport_size({'width': width, 'height': height})
                        page.locator('#matching').select_option('1')
                        metrics = page.evaluate('''() => ({
                            width: document.documentElement.scrollWidth,
                            label: document.querySelector('label').getBoundingClientRect().width,
                            select: document.querySelector('select').getBoundingClientRect().width,
                            row: document.querySelector('li').getBoundingClientRect().width
                        })''')
                        assert metrics['width'] <= width, (name, width, metrics)
                        assert metrics['label'] >= 180, (name, width, metrics)
                        assert metrics['select'] <= metrics['row'], (name, width, metrics)
                        preview = page.locator('.exam-matching-selected-text')
                        assert preview.count() == 1
                        assert preview.text_content() == CHOICE
                        assert preview.is_visible()
                        page.locator('#matching').select_option('2')
                        assert preview.text_content() == 'Другое объяснение.'
                        page.locator('#matching').select_option('0')
                        assert preview.is_hidden()
                        assert preview.text_content() == ''
                    results.append({'engine': name, 'sizes': len(SIZES), 'status': 'passed'})
                finally:
                    browser.close()
        print(json.dumps(results, ensure_ascii=False, indent=2))
    finally:
        server.shutdown()
        server.server_close()
        thread.join()


if __name__ == '__main__':
    main()
