#!/usr/bin/env python3
"""Offline conflict resolution: no implicit overwrite or navigation."""
import json
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright
from browser_answer_save_analysis import markup
from answer_navigation_regression import ROOT, field


def run(browser, case):
    context = browser.new_context()
    page = context.new_page()
    calls, navigation, errors = [], [], []
    page.on('pageerror', lambda error: errors.append(str(error)))

    def handler(route):
        path = urlsplit(route.request.url).path
        if path.endswith('tce_test_answer_save.php'):
            calls.append(route.request.post_data)
            conflicting = len(calls) == 1 or (case == 'race' and len(calls) == 2)
            version = None if case == 'invalid' else len(calls) + 1
            if conflicting:
                route.fulfill(status=409, content_type='application/json', body=json.dumps({'status': 'conflict', 'version': version}))
            else:
                if case == 'edit':
                    page.locator('textarea').fill('NEWER_EDIT')
                route.fulfill(content_type='application/json', body=json.dumps({'status': 'saved', 'version': version}))
        elif path.endswith('tce_test_execute.php'):
            navigation.append(route.request.method)
            route.fulfill(content_type='text/html', body=markup('2'))
        else:
            route.fulfill(content_type='application/json', body='{}')

    page.route('**/*', handler)
    page.goto('https://analysis.invalid/fixture')
    page.set_content(markup())
    page.add_script_tag(path=str(ROOT / 'shared/jscripts/mobile-exam.js'))
    page.locator('textarea').fill('LOCAL')
    page.locator('[name=nextquestion]').click()
    actions = page.locator('#answer-conflict-actions')
    actions.wait_for()
    assert page.locator('#answer_version').input_value() == '1'
    assert page.locator('textarea').input_value() == 'LOCAL'
    assert actions.locator('a').get_attribute('target') == '_blank'
    assert 'testlogid=1' in actions.locator('a').get_attribute('href')
    # Neither a normal save nor navigation may silently adopt the server version.
    page.locator('[data-answer-save]').click()
    page.locator('[name=nextquestion]').click()
    page.wait_for_timeout(100)
    assert len(calls) == 1 and not navigation
    assert page.evaluate("() => {const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;}")
    if case == 'invalid':
        assert actions.locator('button').count() == 0
    else:
        page.once('dialog', lambda dialog: dialog.dismiss())
        actions.locator('button').click()
        assert len(calls) == 1
        page.locator('textarea').fill('CHOSEN')
        page.once('dialog', lambda dialog: dialog.accept())
        actions.locator('button').click()
        page.wait_for_function("!document.querySelector('[data-answer-save]').disabled")
        assert len(calls) == 2
        assert field(calls[1], 'answer_version') == '2'
        assert field(calls[1], 'answertext') == 'CHOSEN'
        assert field(calls[0], 'answer_operation') != field(calls[1], 'answer_operation')
        assert not navigation
        if case == 'race':
            assert page.locator('#answer_version').input_value() == '1'
            page.locator('[data-answer-save]').click()
            assert len(calls) == 2
            page.once('dialog', lambda dialog: dialog.accept())
            actions.locator('button').click()
            page.wait_for_function("!document.querySelector('[data-answer-save]').disabled")
            assert field(calls[2], 'answer_version') == '3'
        assert actions.count() == 0
        if case == 'edit':
            assert page.locator('textarea').input_value() == 'NEWER_EDIT'
            assert page.locator('#answer-save-status').inner_text() == 'dirty'
        else:
            assert page.locator('#answer-save-status').inner_text() == 'saved'
    assert not errors, errors
    context.close()


if __name__ == '__main__':
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        for case in ('resolve', 'race', 'edit', 'invalid'):
            run(browser, case)
        browser.close()
    print('Chromium: 4 conflict resolution scenarios passed')
