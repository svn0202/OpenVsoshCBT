#!/usr/bin/env python3
"""Read-only diagnostic of answer-save UX with synthetic HTTP responses; no live requests."""
import argparse
import json
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright


def markup(question='1'):
    return '''<!doctype html><html><body><form id="testform" method="post" action="tce_test_execute.php">
<input id="testid" name="testid" value="1" type="hidden">
<input id="testlogid" name="testlogid" value="%s" type="hidden">
<input id="testuser_id" value="1" type="hidden"><input id="nextquestionid" value="2" type="hidden">
<input id="answer_version" name="answer_version" value="1" type="hidden">
<textarea name="answertext"></textarea><span id="answer-save-status"></span>
<button type="button" data-answer-save="tce_test_answer_save.php" data-answer-saving="saving" data-answer-saved="saved"
data-answer-error="error" data-answer-conflict="conflict" data-answer-unsaved="dirty"
data-answer-retrying="retry {attempt}/{maximum}/{seconds}">Save</button>
<button name="nextquestion" value="next">Next</button></form></body></html>''' % question


def run(browser, source, scenario):
    context = browser.new_context()
    page = context.new_page()
    calls, errors = [], []
    page.on('pageerror', lambda e: errors.append(str(e)))
    def route(r):
        req = r.request
        path = urlsplit(req.url).path
        if path.endswith('tce_test_answer_save.php'):
            calls.append({'type': 'save', 'body': req.post_data})
            if scenario == 'lost_response' and len([x for x in calls if x['type'] == 'save']) == 1:
                r.abort('failed'); return
            if scenario in ('edit_during_navigation', 'edit_during_save'):
                page.locator('textarea').fill('NEW_UNSAVED_VALUE')
            if scenario == 'double_submit':
                page.evaluate("document.querySelector('[name=nextquestion]').click()")
            status = 409 if scenario == 'conflict' else 403 if scenario == 'forbidden' else 200
            payload = {'status': {409:'conflict',403:'csrf_failed',200:'saved'}[status], 'version':2}
            r.fulfill(status=status, content_type='application/json', body=json.dumps(payload)); return
        if path.endswith('tce_test_execute.php'):
            calls.append({'type':'navigation', 'method':req.method, 'body':req.post_data})
            r.fulfill(content_type='text/html',body=markup('2')); return
        # Block external communication, including heartbeat/focus requests.
        r.fulfill(content_type='application/json',body='{}')
    page.route('**/*', route)
    page.goto('https://analysis.invalid/fixture')
    page.set_content(markup())
    page.add_script_tag(content=source)
    page.locator('textarea').fill('ORIGINAL_UNSAVED_VALUE')
    page.locator('[data-answer-save]' if scenario in ('edit_during_save','lost_response') else '[name=nextquestion]').click()
    page.wait_for_timeout(1600 if scenario == 'lost_response' else 300)
    result = {'scenario':scenario,'question':page.locator('#testlogid').input_value(),
              'answer':page.locator('textarea').input_value(),'status':page.locator('#answer-save-status').inner_text(),
              'calls':calls,'page_errors':errors}
    assert any(x["type"] == "save" for x in calls), "Fixture did not exercise saving"
    assert not errors, errors
    context.close()
    return result


def main():
    p=argparse.ArgumentParser();p.add_argument('--script',type=Path,default=Path(__file__).resolve().parents[1]/'shared/jscripts/mobile-exam.js')
    p.add_argument('--output',type=Path,required=True);a=p.parse_args()
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True)
        results=[run(browser,a.script.read_text(),s) for s in ['conflict','forbidden','edit_during_navigation','edit_during_save','double_submit','lost_response']]
        browser.close()
    a.output.parent.mkdir(parents=True,exist_ok=True);a.output.write_text(json.dumps(results,indent=2))
    for x in results:
        print(json.dumps({k:v for k,v in x.items() if k!='calls'}), 'requests',[(c['type'],c.get('method')) for c in x['calls']])

if __name__=='__main__':main()
