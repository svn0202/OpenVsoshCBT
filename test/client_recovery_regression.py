#!/usr/bin/env python3
"""Exercise stale clients and tab-local drafts without production data or Docker."""
import json
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright
from browser_answer_save_analysis import markup

ROOT = Path(__file__).resolve().parents[1]
SOURCE = (ROOT / 'shared/jscripts/mobile-exam.js').read_text()


def run(browser):
    context = browser.new_context(accept_downloads=True)
    page = context.new_page()
    state = {'authorized': False, 'version': 1, 'attempt': '1'}
    requests = []
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))

    def route(r):
        path = urlsplit(r.request.url).path
        if path.endswith('tce_test_answer_save.php'):
            requests.append((r.request.method, r.request.post_data))
            if not state['authorized']:
                r.fulfill(status=403, json={'status': 'session_required'})
            elif r.request.method == 'GET':
                r.fulfill(json={'status': 'csrf_refreshed', 'csrf_token': 'fresh'})
            elif state['version'] == 2:
                r.fulfill(status=409, json={'status': 'conflict', 'version': 2})
            else:
                assert 'fresh' in r.request.post_data
                r.fulfill(json={'status': 'saved', 'version': 2})
            return
        if path.endswith('/fixture'):
            html = markup().replace('</form>', '<li><select class="matching-position" name="answpos[3]"><option value="0">Choose</option><option value="1">LONG_MATCHING_ANSWER</option></select></li></form>').replace('<textarea', '<input name="csrf_token" value="old"><textarea')
            html = html.replace('id="testuser_id" value="1"', 'id="testuser_id" value="%s"' % state['attempt'])
            r.fulfill(content_type='text/html', body=html)
            return
        r.fulfill(json={})

    page.route('**/*', route)
    page.goto('https://recovery.invalid/fixture')
    page.add_script_tag(content=SOURCE)
    page.locator('textarea').fill('MY_ANSWER')
    page.locator('select.matching-position').select_option('1')
    page.locator('[data-answer-save]').click()
    page.wait_for_selector('#answer-login-link')
    assert len(requests) == 1 and page.locator('textarea').input_value() == 'MY_ANSWER'
    stored = page.evaluate('JSON.stringify(sessionStorage)')
    assert 'MY_ANSWER' in stored and 'csrf_token' not in stored and '"old"' not in stored
    with page.expect_download() as download:
        page.locator('#answer-draft-export').click()
    assert json.loads(Path(download.value.path()).read_text()) == [['answertext', 'MY_ANSWER'], ['answpos[3]', '1']]
    state['authorized'] = True
    page.locator('[data-answer-save]').click()
    page.wait_for_function("document.querySelector('#answer-save-status').dataset.state === 'saved'")
    assert [r[0] for r in requests] == ['POST', 'GET', 'POST']
    assert 'MY_ANSWER' in requests[-1][1]
    assert 'answer-draft:' not in page.evaluate('JSON.stringify(sessionStorage)')

    # Reload offers a draft explicitly; its original base version remains in use.
    page.locator('textarea').fill('UNSAVED_AFTER_LOGIN')
    state['version'] = 2
    page.reload()
    page.add_script_tag(content=SOURCE)
    assert page.locator('textarea').input_value() == ''
    page.locator('#answer-draft-restore button').first.click()
    assert page.locator('textarea').input_value() == 'UNSAVED_AFTER_LOGIN'
    assert page.locator('select.matching-position').input_value() == '1'
    assert page.locator('.exam-matching-selected-text').inner_text() == 'LONG_MATCHING_ANSWER'
    page.locator('[data-answer-save]').click()
    page.wait_for_selector('#answer-conflict-actions')
    assert page.locator('textarea').input_value() == 'UNSAVED_AFTER_LOGIN'
    assert len(requests) == 4  # Never automatically overwrite after a conflict.

    state['attempt'] = '99'
    page.reload()
    page.add_script_tag(content=SOURCE)
    assert page.locator('#answer-draft-restore').count() == 0
    assert page.locator('textarea').input_value() == ''
    page.evaluate("() => { Storage.prototype.setItem = function () { throw new Error('blocked'); }; }")
    state['authorized'] = False
    page.locator('textarea').fill('STORAGE_BLOCKED_DRAFT')
    page.locator('[data-answer-save]').click()
    page.wait_for_selector('#answer-draft-export')
    with page.expect_download() as download:
        page.locator('#answer-draft-export').click()
    assert 'STORAGE_BLOCKED_DRAFT' in Path(download.value.path()).read_text()
    assert page.locator('textarea').input_value() == 'STORAGE_BLOCKED_DRAFT'
    assert not errors, errors
    context.close()

    context = browser.new_context()
    page = context.new_page()
    page.route('**/*', lambda r: r.fulfill(content_type='text/html', body=markup()))
    page.goto('https://legacy.invalid/public/code/tce_test_execute.php?testid=1')
    page.locator('textarea').fill('KEEP_LEGACY_ANSWER')
    for _ in range(3):
        page.add_script_tag(path=str(ROOT / 'shared/jscripts/legacy-client-recovery.js'))
    assert page.locator('#legacy-client-recovery').count() == 1
    assert page.locator('[name=answertext]').input_value() == 'KEEP_LEGACY_ANSWER'
    page.locator('#legacy-client-recovery button').click()
    assert 'KEEP_LEGACY_ANSWER' in page.locator('#legacy-client-recovery textarea').input_value()
    assert page.url.endswith('testid=1')
    context.close()


if __name__ == '__main__':
    with sync_playwright() as pw:
        for name in ['chromium', 'firefox', 'webkit']:
            browser = getattr(pw, name).launch()
            run(browser)
            browser.close()
            print(name + ': reauthentication, draft export/reload/isolation, conflict and legacy recovery passed')
