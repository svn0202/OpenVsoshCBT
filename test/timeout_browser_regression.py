#!/usr/bin/env python3
"""Synthetic deadline saves: preserve input, report confirmation, never redirect."""
import json
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright
from browser_answer_save_analysis import markup
ROOT = Path(__file__).resolve().parents[1]
with sync_playwright() as pw:
    browser = pw.chromium.launch()
    for last in (False, True):
        for outcome in ('saved', 'conflict', 'forbidden', 'lost', 'html', 'timeout'):
            context = browser.new_context()
            page = context.new_page()
            page.clock.install()
            calls, errors, pending = [], [], []
            page.on('pageerror', lambda error: errors.append(str(error)))
            def handler(route):
                if urlsplit(route.request.url).path.endswith(('tce_test_answer_save.php', 'tce_test_execute.php')):
                    calls.append(route.request)
                    if outcome == 'timeout':
                        pending.append(route)
                        return
                    if outcome == 'lost':
                        route.abort(); return
                    if outcome == 'html':
                        route.fulfill(content_type='text/html', body='<p>Login</p>'); return
                    route.fulfill(status=409 if outcome=='conflict' else 403 if outcome=='forbidden' else 200,
                                  content_type='application/json', body=json.dumps({'status': 'session_required' if outcome=='forbidden' else outcome, 'version': 2}))
                else:
                    route.fulfill(content_type='application/json', body='{}')
            page.route('**/*', handler)
            page.goto('https://analysis.invalid/fixture')
            extra = '<input name="finish" id="finish" value="0" type="hidden">'
            if last:
                extra += '<input name="forceterminate" value="lasttimedquestion" type="hidden">'
            page.set_content(markup().replace('</form>', extra+'</form>'))
            page.add_script_tag(path=str(ROOT/'shared/jscripts/mobile-exam.js'))
            page.locator('textarea').fill('KEEP_THIS')
            if last:
                page.evaluate("document.getElementById('testform').submit()")
            else:
                page.add_script_tag(path=str(ROOT/'shared/jscripts/timer.js'))
                page.evaluate("FJ_start_timer(true, -1, 'Time expired', false)")
            if outcome == 'timeout':
                page.wait_for_timeout(100)
                page.clock.fast_forward(16000)
            page.wait_for_function("document.querySelector('#answer-final-save-status + a') !== null")
            assert len(calls) == 1
            assert 'KEEP_THIS' in calls[0].post_data
            assert calls[0].url.endswith('tce_test_execute.php')
            assert page.locator('textarea').input_value() == 'KEEP_THIS'
            assert page.locator('textarea').is_disabled()
            assert page.url.endswith('/fixture')
            message = page.locator('#answer-final-save-status').inner_text()
            assert ('Последний ответ сохранён' in message) == (outcome=='saved'), message
            if outcome in ('lost','html','timeout'): assert 'неизвестен' in message
            if outcome=='conflict': assert 'конфликт' in message
            assert page.evaluate("() => {let e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;}") == (outcome!='saved')
            page.evaluate("document.getElementById('testform').submit()")
            assert len(calls)==1
            assert not errors, errors
            for route in pending:
                route.abort()
            context.close()
    browser.close()
print('Chromium: 12 deadline save scenarios passed')
