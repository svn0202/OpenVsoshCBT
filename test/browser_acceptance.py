#!/usr/bin/env python3
"""Run the mobile RTL/listening fixture in Chromium, Firefox and WebKit."""

from __future__ import annotations

import argparse
import json
import os
import threading
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any

from playwright.sync_api import BrowserType, sync_playwright


ROOT = Path(__file__).resolve().parent.parent
FIXTURE_PATH = "/test/fixtures/browser-acceptance.html"
EXPECTED_INITIAL = "مرات التشغيل المتبقية: 2"
EXPECTED_AFTER_FIRST = "مرات التشغيل المتبقية: 1"
EXPECTED_EXHAUSTED = "تم استنفاد حد تشغيل الصوت"


class QuietHandler(SimpleHTTPRequestHandler):
    """Serve the repository without writing request logs to stderr."""

    def log_message(self, format: str, *args: object) -> None:
        return


def assert_equal(actual: Any, expected: Any, message: str) -> None:
    if actual != expected:
        raise AssertionError(f"{message}: expected {expected!r}, got {actual!r}")


def check_content_protection(page: Any, name: str) -> None:
    answer = page.locator('[name="answertext"]')
    answer.fill('My own answer')
    answer.press('ControlOrMeta+a')
    answer.press('Backspace')
    answer.press_sequentially('Edited answer')
    assert_equal(answer.input_value(), 'Edited answer', f'{name} answer editing')
    assert_equal(page.locator('#arabic-sample').evaluate(
        "e => getComputedStyle(e).userSelect || getComputedStyle(e).webkitUserSelect"),
                 'none', f'{name} question selection')
    assert_equal(answer.evaluate(
        "e => getComputedStyle(e).userSelect || getComputedStyle(e).webkitUserSelect"),
                 'text', f'{name} answer selection')

    results = page.evaluate("""() => {
        const answer = document.querySelector('[name="answertext"]');
        const sample = document.querySelector('#arabic-sample');
        const cancel = (node, event) => !node.dispatchEvent(event);
        const event = type => new Event(type, {bubbles: true, cancelable: true});
        const checks = {};
        for (const type of ['contextmenu', 'dragstart', 'selectstart']) {
            checks[type] = cancel(sample, event(type));
        }
        for (const type of ['paste', 'drop']) {
            checks[type] = cancel(answer, event(type));
        }
        for (const inputType of ['insertFromPaste', 'insertFromDrop']) {
            checks[inputType] = cancel(answer, new InputEvent('beforeinput', {
                bubbles: true, cancelable: true, inputType, data: 'External answer'
            }));
        }
        for (const options of [
            {key: 'v', ctrlKey: true}, {key: 'v', metaKey: true},
            {key: 'м', code: 'KeyV', ctrlKey: true}, {key: 'Insert', shiftKey: true}
        ]) {
            checks[JSON.stringify(options)] = cancel(answer,
                new KeyboardEvent('keydown', {bubbles: true, cancelable: true, ...options}));
        }
        const copy = type => {
            const clipboardEvent = new ClipboardEvent(type, {
                bubbles: true, cancelable: true, clipboardData: new DataTransfer()
            });
            // Firefox creates its own DataTransfer for synthetic clipboard events.
            const data = clipboardEvent.clipboardData;
            data.setData('text/html', '<b>Question must not escape</b>');
            const blocked = cancel(answer, clipboardEvent);
            return {blocked, text: data.getData('text/plain'), html: data.getData('text/html')};
        };
        const fallback = copy('copy');
        const context = document.createElement('span');
        context.className = 'exam-machine-context';
        context.textContent = 'First question AI text';
        document.querySelector('#testform').append(context);
        const first = copy('copy');
        context.remove();
        const next = document.createElement('span');
        next.className = 'exam-machine-context';
        next.textContent = 'Next question AI text';
        document.querySelector('#testform').append(next);
        const second = copy('cut');
        next.remove();
        return {checks, fallback, first, second, answer: answer.value};
    }""")
    for check, blocked in results['checks'].items():
        assert_equal(blocked, True, f'{name} blocks {check}')
    for key in ['fallback', 'first', 'second']:
        assert_equal(results[key]['blocked'], True, f'{name} cancels {key} export')
        assert_equal(results[key]['html'], results[key]['text'], f'{name} replaces {key} HTML')
    assert_equal(results['first']['text'], 'First question AI text', f'{name} copy decoy')
    assert_equal(results['second']['text'], 'Next question AI text', f'{name} updated decoy')
    assert_equal('самостоятельно' in results['fallback']['text'], True, f'{name} fallback')
    assert_equal(results['answer'], 'Edited answer', f'{name} cut preserves answer')

    if name == 'chromium':
        page.context.grant_permissions(['clipboard-read', 'clipboard-write'])
        for shortcut in ['ControlOrMeta+c', 'ControlOrMeta+x']:
            answer.select_text()
            answer.press(shortcut)
            copied = page.evaluate('navigator.clipboard.readText()')
            assert_equal(copied, results['fallback']['text'], f'{name} real {shortcut}')
            assert_equal(answer.input_value(), 'Edited answer', f'{name} real cut preserves answer')

    # These validate page handlers, not interception of actual OS screenshots.
    for shortcut in ['PrintScreen', 'Meta+Shift+s', 'Meta+Shift+3',
                     'Meta+Shift+4', 'Meta+Shift+5', 'Control+p', 'Meta+p']:
        page.keyboard.press(shortcut)
        assert_equal(page.locator('#testform').evaluate(
            "e => getComputedStyle(e).visibility"), 'hidden', f'{name} hides {shortcut}')
        page.evaluate("document.body.classList.remove('exam-capture-obscured')")
    page.keyboard.press('PrintScreen')
    page.wait_for_function("!document.body.classList.contains('exam-capture-obscured')")
    assert_equal(answer.input_value(), 'Edited answer', f'{name} capture preserves answer')
    page.emulate_media(media='print')
    assert_equal(page.locator('main').evaluate("e => getComputedStyle(e).display"),
                 'none', f'{name} print hides exam')
    page.emulate_media(media='screen')


def run_engine(browser_type: BrowserType, name: str, base_url: str) -> dict[str, Any]:
    browser = browser_type.launch(headless=True)
    context = browser.new_context(viewport={"width": 390, "height": 844})
    page = context.new_page()
    font_statuses: list[int] = []
    page.on(
        "response",
        lambda response: font_statuses.append(response.status)
        if response.url.endswith("noto-sans-arabic.woff2")
        else None,
    )

    try:
        page.goto(base_url + FIXTURE_PATH, wait_until="networkidle")
        page.evaluate("localStorage.clear()")
        page.reload(wait_until="networkidle")
        page.evaluate("document.fonts.ready")

        status = page.locator(".audio-play-status")
        assert_equal(status.inner_text(), EXPECTED_INITIAL, f"{name} initial audio status")

        page.locator("#simulate-audio-start").click()
        assert_equal(status.inner_text(), EXPECTED_AFTER_FIRST, f"{name} first audio start")

        page.reload(wait_until="networkidle")
        status = page.locator(".audio-play-status")
        assert_equal(status.inner_text(), EXPECTED_AFTER_FIRST, f"{name} reload counter")

        page.locator("#simulate-audio-start").click()
        assert_equal(status.inner_text(), EXPECTED_EXHAUSTED, f"{name} exhausted status")
        audio = page.locator("#listening-sample")
        assert_equal(audio.get_attribute("aria-disabled"), "true", f"{name} audio lock")

        page.locator("#simulate-audio-start").click()
        assert_equal(status.inner_text(), EXPECTED_EXHAUSTED, f"{name} third audio start")

        metrics = page.evaluate(
            """() => ({
                direction: getComputedStyle(document.documentElement).direction,
                fontFamily: getComputedStyle(
                    document.getElementById('arabic-sample')
                ).fontFamily,
                fontsStatus: document.fonts.status,
                viewportWidth: document.documentElement.clientWidth,
                scrollWidth: document.documentElement.scrollWidth
            })"""
        )
        assert_equal(metrics["direction"], "rtl", f"{name} direction")
        assert_equal(metrics["fontsStatus"], "loaded", f"{name} font state")
        if "Noto Sans Arabic" not in metrics["fontFamily"]:
            raise AssertionError(f"{name} did not select the bundled Arabic font")
        assert_equal(metrics["scrollWidth"], metrics["viewportWidth"], f"{name} overflow")
        if not font_statuses or any(status_code != 200 for status_code in font_statuses):
            raise AssertionError(f"{name} font responses were not all HTTP 200: {font_statuses}")

        check_content_protection(page, name)
        page.route('**/ordinary-page.html', lambda route: route.fulfill(
            content_type='text/html', body='''<!doctype html><html><head>
            <link rel="stylesheet" href="/public/styles/tmf-reference.css"></head>
            <body><p>Ordinary page</p>
            <script src="/shared/jscripts/mobile-exam.js"></script></body></html>'''))
        page.goto(base_url + '/ordinary-page.html', wait_until='networkidle')
        assert_equal(page.evaluate("""() => {
            const copy = new Event('copy', {bubbles: true, cancelable: true});
            return document.body.dispatchEvent(copy)
                && !document.body.classList.contains('exam-content-protected');
        }"""), True, f'{name} ordinary page is unrestricted')

        return {
            "engine": name,
            "contentProtection": "passed",
            **metrics,
            "fontHttpStatuses": font_statuses,
            "reloadCounter": EXPECTED_AFTER_FIRST,
            "finalStatus": EXPECTED_EXHAUSTED,
            "ariaDisabled": "true",
        }
    finally:
        context.close()
        browser.close()


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--browser",
        action="append",
        choices=("chromium", "firefox", "webkit"),
        dest="browsers",
        help="engine to test; may be repeated (default: all three)",
    )
    args = parser.parse_args()
    browsers = args.browsers or ["chromium", "firefox", "webkit"]

    previous_directory = Path.cwd()
    os.chdir(ROOT)
    server = ThreadingHTTPServer(("127.0.0.1", 0), QuietHandler)
    server_thread = threading.Thread(target=server.serve_forever, daemon=True)
    server_thread.start()

    try:
        base_url = f"http://127.0.0.1:{server.server_port}"
        with sync_playwright() as playwright:
            results = [
                run_engine(getattr(playwright, browser_name), browser_name, base_url)
                for browser_name in browsers
            ]
        print(json.dumps(results, ensure_ascii=False, indent=2))
    finally:
        server.shutdown()
        server.server_close()
        server_thread.join()
        os.chdir(previous_directory)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
