#!/usr/bin/env python3
"""Offline browser regressions: navigation must preserve edits made in flight."""
import re
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright
from browser_answer_save_analysis import markup

ROOT = Path(__file__).resolve().parents[1]


def field(body, name):
    return re.search(r'name="' + name + r'"\r\n\r\n([^\r]*)', body).group(1)


def run(browser, control, phase):
    context = browser.new_context()
    page = context.new_page()
    saves, gets, errors = [], [], []
    page.on('pageerror', lambda error: errors.append(str(error)))
    selector = '[name=nextquestion]' if control == 'next' else '.exam-question-list li'

    def handler(route):
        path = urlsplit(route.request.url).path
        if path.endswith('tce_test_answer_save.php'):
            saves.append(route.request.post_data)
            if len(saves) == 1 and phase == 'save':
                page.locator('textarea').fill('LATEST')
            route.fulfill(content_type='application/json', body='{"status":"saved","version":%d}' % (len(saves) + 1))
        elif path.endswith('tce_test_execute.php'):
            assert route.request.method == 'GET', 'Unexpected native POST navigation'
            gets.append(route.request.url)
            if len(gets) == 1 and phase in ('load', 'load_error'):
                page.locator('textarea').fill('LATEST')
                # A second navigation while the GET is pending must not start another save.
                page.locator(selector).click()
            route.fulfill(status=500 if len(gets) == 1 and phase == 'load_error' else 200,
                          content_type='text/html', body=markup('2'))
        else:
            route.fulfill(content_type='application/json', body='{}')

    page.route('**/*', handler)
    page.goto('https://analysis.invalid/fixture')
    page.set_content(markup().replace('</form>', '<ol class="exam-question-list"><li data-testlog-id="2"><span>Question 2</span><input type="submit" name="jumpquestion_2" value="2"></li></ol></form>'))
    page.add_script_tag(path=str(ROOT / 'shared/jscripts/mobile-exam.js'))
    page.locator('textarea').fill('ORIGINAL')
    page.locator(selector).click()
    if phase == 'none':
        page.wait_for_function("document.getElementById('testlogid').value === '2'")
        assert len(saves) == len(gets) == 1
    else:
        page.wait_for_function("document.getElementById('testform').getAttribute('aria-busy') === 'false'")
        assert page.locator('#testlogid').input_value() == '1'
        assert page.locator('textarea').input_value() == 'LATEST'
        assert page.locator('#answer-save-status').inner_text() == 'dirty'
        assert len(saves) == 1
        assert len(gets) == (0 if phase == 'save' else 1)
        assert page.evaluate("() => {const e = new Event('beforeunload', {cancelable:true}); window.dispatchEvent(e); return e.defaultPrevented;}")
        page.locator(selector).click()
        page.wait_for_function("document.getElementById('testlogid').value === '2'")
        assert len(saves) == 2
        assert field(saves[0], 'answertext') == 'ORIGINAL'
        assert field(saves[1], 'answertext') == 'LATEST'
        assert field(saves[1], 'answer_version') == '2'
        assert field(saves[0], 'answer_operation') != field(saves[1], 'answer_operation')
    assert not errors, errors
    context.close()


if __name__ == '__main__':
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        for control in ('next', 'menu'):
            for phase in ('none', 'save', 'load', 'load_error'):
                run(browser, control, phase)
        browser.close()
    print('Chromium: 8 answer navigation scenarios passed')
