"""
ICS / calendar subscription tests.
"""
import re
import pytest
from playwright.sync_api import Page, expect

from helpers import BASE_URL, navigate_to_project


class TestICSSubscription:

    @pytest.fixture(autouse=True)
    def _setup(self, session_project):
        self.project_name = session_project

    def test_subscribe_button_opens_modal(self, page: Page):
        navigate_to_project(page, self.project_name)
        page.locator("#subscribeBtn").click()
        expect(page.locator("#subscribeModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#icsUrl")).to_be_visible()

    def test_subscribe_modal_url_contains_token(self, page: Page):
        navigate_to_project(page, self.project_name)
        page.locator("#subscribeBtn").click()
        ics_url = page.locator("#icsUrl").input_value()
        assert "token=" in ics_url, f"ICS URL missing token parameter: {ics_url}"
        assert "/calendar.ics" in ics_url, f"ICS URL missing /calendar.ics: {ics_url}"

    def test_ics_feed_returns_vcalendar(self, page: Page):
        navigate_to_project(page, self.project_name)
        page.locator("#subscribeBtn").click()
        ics_url = page.locator("#icsUrl").input_value()

        resp = page.request.get(ics_url)
        assert resp.status == 200, f"ICS feed returned {resp.status}"
        body = resp.text()
        assert "BEGIN:VCALENDAR" in body, "ICS response missing BEGIN:VCALENDAR"
        assert "END:VCALENDAR" in body, "ICS response missing END:VCALENDAR"

    def test_ics_feed_rejected_without_token(self, page: Page):
        navigate_to_project(page, self.project_name)
        project_id = re.search(r"/project/(\d+)", page.url).group(1)
        resp = page.request.get(BASE_URL + f"/project/{project_id}/calendar.ics")
        # No token → should be 401 or 403, not a valid calendar
        assert resp.status in (401, 403), \
            f"Expected 401/403 for ICS without token, got {resp.status}"

    def test_ics_feed_rejected_with_wrong_token(self, page: Page):
        navigate_to_project(page, self.project_name)
        project_id = re.search(r"/project/(\d+)", page.url).group(1)
        resp = page.request.get(
            BASE_URL + f"/project/{project_id}/calendar.ics?token=invalid-token-value"
        )
        assert resp.status in (401, 403), \
            f"Expected 401/403 for ICS with wrong token, got {resp.status}"
