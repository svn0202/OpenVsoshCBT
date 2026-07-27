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

        return {
            "engine": name,
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
