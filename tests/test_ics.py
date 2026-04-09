"""
ICS / calendar subscription tests.
"""
import re
import pytest
from playwright.sync_api import Page, expect

from pages import BASE_URL, ProjectPage
from conftest import rand_milestone_name, rand_event_name


class TestICSSubscription:
    """Modal, URL structure, and auth-gate tests."""

    @pytest.fixture(autouse=True)
    def _setup(self, page: Page, session_project):
        self.project = ProjectPage(page)
        self.project_name = session_project

    def test_subscribe_button_opens_modal(self, page: Page):
        self.project.navigate_to(self.project_name)
        self.project.open_subscribe_modal()
        expect(page.locator(ProjectPage.ICS_URL)).to_be_visible()

    def test_subscribe_modal_url_contains_token(self, page: Page):
        self.project.navigate_to(self.project_name)
        self.project.open_subscribe_modal()
        ics_url = self.project.get_ics_url()
        assert "token=" in ics_url, f"ICS URL missing token parameter: {ics_url}"
        assert "/calendar.ics" in ics_url, f"ICS URL missing /calendar.ics: {ics_url}"

    def test_ics_feed_returns_vcalendar(self, page: Page):
        self.project.navigate_to(self.project_name)
        body = self.project.fetch_ics()
        assert "BEGIN:VCALENDAR" in body, "ICS response missing BEGIN:VCALENDAR"
        assert "END:VCALENDAR" in body, "ICS response missing END:VCALENDAR"

    def test_ics_feed_rejected_without_token(self, page: Page):
        self.project.navigate_to(self.project_name)
        project_id = re.search(r"/project/(\d+)", page.url).group(1)
        resp = page.request.get(BASE_URL + f"/project/{project_id}/calendar.ics")
        assert resp.status in (401, 403), \
            f"Expected 401/403 for ICS without token, got {resp.status}"

    def test_ics_feed_rejected_with_wrong_token(self, page: Page):
        self.project.navigate_to(self.project_name)
        project_id = re.search(r"/project/(\d+)", page.url).group(1)
        resp = page.request.get(
            BASE_URL + f"/project/{project_id}/calendar.ics?token=invalid-token-value"
        )
        assert resp.status in (401, 403), \
            f"Expected 401/403 for ICS with wrong token, got {resp.status}"


class TestICSContent:
    """Feed content tests: each item type appears after creation and disappears after deletion."""

    # ── Phases ────────────────────────────────────────────────────────────────

    def test_phase_appears_in_ics_feed(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        project = ProjectPage(page)
        body = project.fetch_ics()
        assert phase_name in body, \
            f"Phase '{phase_name}' not found in ICS feed"

    def test_phase_removal_removes_from_ics(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        project = ProjectPage(page)

        assert phase_name in project.fetch_ics(), \
            f"Pre-condition failed: phase '{phase_name}' not in ICS before deletion"

        project.delete_phase(phase_name)

        assert phase_name not in project.fetch_ics(), \
            f"Phase '{phase_name}' still present in ICS after deletion"

    # ── Milestones ────────────────────────────────────────────────────────────

    def test_milestone_appears_in_ics_feed(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        milestone_name = rand_milestone_name()
        project = ProjectPage(page)
        project.add_milestone(phase_name, milestone_name, "2027-03-15")

        body = project.fetch_ics()
        assert milestone_name in body, \
            f"Milestone '{milestone_name}' not found in ICS feed"

    def test_milestone_removal_removes_from_ics(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        milestone_name = rand_milestone_name()
        project = ProjectPage(page)
        project.add_milestone(phase_name, milestone_name, "2027-03-15")

        assert milestone_name in project.fetch_ics(), \
            f"Pre-condition failed: milestone '{milestone_name}' not in ICS before deletion"

        project.delete_milestone(phase_name, milestone_name)

        assert milestone_name not in project.fetch_ics(), \
            f"Milestone '{milestone_name}' still present in ICS after deletion"

    # ── Phase events ──────────────────────────────────────────────────────────

    def test_phase_event_appears_in_ics_feed(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        event_name = rand_event_name()
        project = ProjectPage(page)
        project.add_phase_event(phase_name, event_name, "2027-02-01", "2027-02-03")

        body = project.fetch_ics()
        assert event_name in body, \
            f"Phase event '{event_name}' not found in ICS feed"

    def test_phase_event_removal_removes_from_ics(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        event_name = rand_event_name()
        project = ProjectPage(page)
        project.add_phase_event(phase_name, event_name, "2027-02-01", "2027-02-03")

        assert event_name in project.fetch_ics(), \
            f"Pre-condition failed: phase event '{event_name}' not in ICS before deletion"

        project.delete_phase_event(phase_name, event_name)

        assert event_name not in project.fetch_ics(), \
            f"Phase event '{event_name}' still present in ICS after deletion"

    # ── Project-level events ──────────────────────────────────────────────────

    def test_project_event_appears_in_ics_feed(self, page: Page, make_project):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_to(project_name)
        event_name = rand_event_name()
        project.add_project_event(event_name, "2027-05-01", "2027-05-02")

        body = project.fetch_ics()
        assert event_name in body, \
            f"Project event '{event_name}' not found in ICS feed"

    def test_project_event_removal_removes_from_ics(self, page: Page, make_project):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_to(project_name)
        event_name = rand_event_name()
        project.add_project_event(event_name, "2027-05-01", "2027-05-02")

        assert event_name in project.fetch_ics(), \
            f"Pre-condition failed: project event '{event_name}' not in ICS before deletion"

        project.delete_project_event(event_name)

        assert event_name not in project.fetch_ics(), \
            f"Project event '{event_name}' still present in ICS after deletion"
