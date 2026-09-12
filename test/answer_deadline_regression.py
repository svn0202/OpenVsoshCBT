#!/usr/bin/env python3
"""Deadline and native form submissions must not race a pending answer save."""
import json
from urllib.parse import urlsplit, parse_qs
from playwright.sync_api import sync_playwright
from browser_answer_save_analysis import markup
from answer_navigation_regression import ROOT, field


def run(browser, case):
    context = browser.new_context()
    page = context.new_page()
    pending, final, native, errors = [], [], [], []
    page.on('pageerror', lambda e: errors.append(str(e)))

    def handler(route):
        path = urlsplit(route.request.url).path
        if path.endswith('tce_test_answer_save.php'):
            pending.append(route)
        elif path.endswith('tce_test_execute.php'):
            if case == 'native':
                native.append(route.request.post_data)
                route.fulfill(content_type='text/html', body='<html>Finished</html>')
            else:
                final.append(route.request.post_data)
                route.fulfill(content_type='application/json', body='{"status":"saved","version":3}')
        else:
            route.fulfill(content_type='text/html', body='')

    page.route('**/*', handler)
    page.goto('https://analysis.invalid/public/code/fixture')
    extra = '<input type="hidden" name="finish" value="0"><button name="terminationform">Finish</button>'
    page.set_content(markup().replace('</form>', extra + '</form>'))
    page.add_script_tag(path=str(ROOT / 'shared/jscripts/mobile-exam.js'))
    page.locator('textarea').fill('FIRST')
    page.locator('[data-answer-save]').click()
    page.wait_for_function("document.querySelector('[data-answer-save]').disabled")
    assert len(pending) == 1
    if case == 'native':
        page.locator('[name=terminationform]').click()
        assert not native
    else:
        if case == 'edited':
            page.locator('textarea').fill('LATEST_AT_DEADLINE')
        page.evaluate("document.querySelector('[name=finish]').value='1'; document.querySelector('#testform').submit()")
        assert not final, 'Final request raced the pending save'
    status = 409 if case == 'conflict' else 403 if case == 'forbidden' else 200
    payload = {'status': {200: 'saved', 409: 'conflict', 403: 'session_required'}[status], 'version': 2}
    pending[0].fulfill(status=status, content_type='application/json', body=json.dumps(payload))
    if case == 'native':
        page.wait_for_function("!document.querySelector('[data-answer-save]').disabled")
        page.locator('[name=terminationform]').click()
        page.wait_for_function("document.body.textContent === 'Finished'")
        assert len(native) == 1 and parse_qs(native[0])['answer_version'] == ['2']
    else:
        page.wait_for_function("document.querySelector('#answer-final-save-status + a') !== null")
        if case in ('conflict', 'forbidden'):
            assert not final, 'A rejected save must not be replayed at the deadline'
            assert 'Последний ответ сохранён.' not in page.locator('#answer-final-save-status').inner_text()
        else:
            assert len(final) == 1
            assert field(final[0], 'answer_version') == '2'
            assert field(final[0], 'answertext') == ('LATEST_AT_DEADLINE' if case == 'edited' else 'FIRST')
        assert page.locator('textarea').input_value() == ('LATEST_AT_DEADLINE' if case == 'edited' else 'FIRST')
    assert not errors, errors
    context.close()


with sync_playwright() as pw:
    browser = pw.chromium.launch()
    for case in ('saved', 'edited', 'conflict', 'forbidden', 'native'):
        run(browser, case)
    browser.close()
print('Chromium: 5 deadline/native save serialization scenarios passed')
