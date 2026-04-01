"""
Test cleanup — deletes any projects created by the test suite.
Run last (pytest orders by filename; test_cleanup.py sorts after all others).
"""
import pytest
from playwright.sync_api import Page, expect

from helpers import BASE_URL, goto


class TestCleanup:

    def test_delete_playwright_test_project(self, page: Page):
        goto(page)
        card = page.locator(".project-card", has_text="Playwright Test Project")
        if card.count() == 0:
            pytest.skip("Playwright Test Project not found — already cleaned up")
        card.hover()
        card.locator("button[title='Delete project']").click()
        expect(page.locator("#confirmModal")).to_have_class(
            __import__("re").compile(r"is-open")
        )
        page.locator("#confirmOkBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(card).not_to_be_attached()
