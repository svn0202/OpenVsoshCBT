#!/usr/bin/env python3
"""Offline browser tests for review confirmation, retry and serialized changes."""
import json
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright
from browser_answer_save_analysis import markup
from answer_navigation_regression import field
ROOT = Path(__file__).resolve().parents[1]


def fixture(question='1'):
    return markup(question).replace('</form>', '<div data-exam-toolbar><input type="checkbox" data-exam-review data-reviewed="0" data-review-save="tce_test_review.php"></div><input name="csrf_token" value="token"></form>')


def run(browser, case):
    context = browser.new_context()
    page = context.new_page()
    page.clock.install()
    calls, pending, errors = [], [], []
    page.on('pageerror', lambda error: errors.append(str(error)))

    def handler(route):
        if urlsplit(route.request.url).path.endswith('tce_test_review.php'):
            calls.append(route.request.post_data)
            if len(calls) == 1:
                if case in ('rapid', 'rapid_error', 'timeout', 'navigation'):
                    pending.append(route); return
                if case == 'lost':
                    route.abort(); return
                if case in ('502', '403'):
                    route.fulfill(status=int(case), body='error'); return
                if case == 'html':
                    route.fulfill(content_type='text/html', body='<p>Login</p>'); return
                if case == 'mismatch':
                    route.fulfill(content_type='application/json', body='{"status":"saved","reviewed":false}'); return
            route.fulfill(content_type='application/json', body=json.dumps({'status':'saved','reviewed':field(calls[-1],'reviewed')=='1'}))
        elif urlsplit(route.request.url).path.endswith('tce_test_answer_save.php'):
            route.fulfill(content_type='application/json', body='{"status":"saved","version":2}')
        elif urlsplit(route.request.url).path.endswith('tce_test_execute.php'):
            route.fulfill(content_type='text/html', body=fixture('2'))
        else:
            route.fulfill(content_type='application/json', body='{}')

    page.route('**/*', handler)
    page.goto('https://analysis.invalid/fixture')
    page.set_content(fixture())
    page.add_script_tag(path=str(ROOT/'shared/jscripts/mobile-exam.js'))
    review = page.locator('[data-exam-review]')
    status = page.locator('[data-review-status]')
    review.check()
    if case == 'navigation':
        page.locator('[name=nextquestion]').click()
        page.wait_for_function("document.getElementById('testlogid').value === '2'")
        pending.pop().fulfill(content_type='application/json', body='{"status":"saved","reviewed":true}')
        page.wait_for_timeout(100)
        assert not review.is_checked()
        assert field(calls[0], 'testlogid') == '1'
        assert len(calls) == 1 and not errors
        context.close()
        return
    if case in ('rapid','rapid_error'):
        review.uncheck()
        assert len(calls)==1, 'Concurrent request for the same review flag'
        if case == 'rapid_error':
            pending.pop().fulfill(status=502, body='error')
        else:
            pending.pop().fulfill(content_type='application/json', body='{"status":"saved","reviewed":true}')
    if case == 'timeout':
        page.wait_for_timeout(100)
        page.clock.fast_forward(16000)
    if case not in ('saved','rapid'):
        page.wait_for_function("document.querySelector('[data-review-status]').dataset.state === 'error'")
        assert review.is_checked() == (case != 'rapid_error')
        assert len(calls)==1
        assert page.evaluate("() => {const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;}")
        for route in pending: route.abort()
        pending.clear()
        page.locator('[data-review-retry]').click()
    page.wait_for_function("document.querySelector('[data-review-status]').dataset.state === 'saved'")
    assert review.is_checked() == (case not in ('rapid','rapid_error'))
    assert len(calls)==(1 if case=='saved' else 2)
    assert field(calls[-1],'testlogid')=='1'
    assert field(calls[-1],'reviewed') == ('0' if case in ('rapid','rapid_error') else '1')
    assert page.locator('[data-review-retry]').is_hidden()
    assert page.url.endswith('/fixture')
    assert not errors, errors
    context.close()


if __name__ == '__main__':
    with sync_playwright() as pw:
        browser = pw.chromium.launch()
        for case in ('saved','502','403','html','mismatch','lost','timeout','rapid','rapid_error','navigation'):
            run(browser, case)
        browser.close()
    print('Chromium: 10 review save scenarios passed')
