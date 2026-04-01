"""
Auth tests: login page, bad credentials, logout, unauthenticated redirects.
"""
import re
import pytest
from playwright.sync_api import Browser, Page, expect

from helpers import BASE_URL, goto


class TestLogin:

    def test_login_page_renders(self, browser: Browser):
        ctx = browser.new_context()
        pg = ctx.new_page()
        pg.goto(BASE_URL + "/login")
        pg.wait_for_load_state("networkidle")
        expect(pg.locator("input[name='email']")).to_be_visible()
        expect(pg.locator("input[name='password']")).to_be_visible()
        expect(pg.get_by_role("button", name="Sign In")).to_be_visible()
        ctx.close()

    def test_wrong_password_stays_on_login(self, browser: Browser):
        ctx = browser.new_context()
        pg = ctx.new_page()
        pg.goto(BASE_URL + "/login")
        pg.fill("input[name='email']",    "admin@example.com")
        pg.fill("input[name='password']", "definitely-wrong-password")
        pg.get_by_role("button", name="Sign In").click()
        pg.wait_for_load_state("networkidle")
        assert "/login" in pg.url, "Expected to remain on /login after bad credentials"
        ctx.close()

    def test_wrong_email_stays_on_login(self, browser: Browser):
        ctx = browser.new_context()
        pg = ctx.new_page()
        pg.goto(BASE_URL + "/login")
        pg.fill("input[name='email']",    "nobody@nowhere.example")
        pg.fill("input[name='password']", "anything")
        pg.get_by_role("button", name="Sign In").click()
        pg.wait_for_load_state("networkidle")
        assert "/login" in pg.url
        ctx.close()

    def test_unauthenticated_dashboard_redirects_to_login(self, browser: Browser):
        ctx = browser.new_context()
        pg = ctx.new_page()
        pg.goto(BASE_URL + "/")
        pg.wait_for_load_state("networkidle")
        assert "/login" in pg.url, "Expected redirect to /login for unauthenticated request"
        ctx.close()

    def test_unauthenticated_project_url_redirects_to_login(self, browser: Browser):
        ctx = browser.new_context()
        pg = ctx.new_page()
        pg.goto(BASE_URL + "/project/1")
        pg.wait_for_load_state("networkidle")
        assert "/login" in pg.url
        ctx.close()


class TestLogout:
    """
    Logout tests must use their own fresh login session — never the shared
    auth_state fixture — because calling /logout kills the server-side session
    and would poison auth_state for every subsequent test.
    """

    def _login_fresh(self, browser: Browser):
        """Return a new page logged in as admin (independent session)."""
        from conftest import TEST_AUTH_EMAIL, TEST_AUTH_PASS
        ctx = browser.new_context()
        pg = ctx.new_page()
        pg.goto(BASE_URL + "/login")
        pg.fill("input[name='email']",    TEST_AUTH_EMAIL)
        pg.fill("input[name='password']", TEST_AUTH_PASS)
        pg.get_by_role("button", name="Sign In").click()
        pg.wait_for_load_state("networkidle")
        return ctx, pg

    def test_logout_redirects_to_login(self, browser: Browser):
        ctx, pg = self._login_fresh(browser)
        pg.goto(BASE_URL + "/logout")
        pg.wait_for_load_state("networkidle")
        assert "/login" in pg.url
        ctx.close()

    def test_after_logout_dashboard_requires_login(self, browser: Browser):
        ctx, pg = self._login_fresh(browser)
        pg.goto(BASE_URL + "/logout")
        pg.wait_for_load_state("networkidle")
        pg.goto(BASE_URL + "/")
        pg.wait_for_load_state("networkidle")
        assert "/login" in pg.url
        ctx.close()
