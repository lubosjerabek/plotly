"""
ICS / calendar subscription tests.
"""
import re
import pytest
from playwright.sync_api import Page, expect

from pages import BASE_URL, ProjectPage
from conftest import rand_milestone_name, rand_event_name


def _vevent_block(body: str, summary_fragment: str) -> str:
    """Return the VEVENT block whose SUMMARY line contains summary_fragment."""
    for block in re.split(r'BEGIN:VEVENT', body)[1:]:
        if summary_fragment in block:
            return 'BEGIN:VEVENT' + block.split('END:VEVENT')[0] + 'END:VEVENT'
    return ''


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


class TestICSFormat:
    """SUMMARY prefix and DTSTART/DTEND formatting for every item type."""

    # ── Phases ────────────────────────────────────────────────────────────────

    def test_phase_summary_has_calendar_emoji(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        project = ProjectPage(page)
        body = project.fetch_ics()
        block = _vevent_block(body, phase_name)
        assert block, f"VEVENT for phase '{phase_name}' not found"
        assert f"SUMMARY:📅 {phase_name}" in block, \
            f"Phase summary should start with 📅, got block:\n{block}"

    def test_phase_uses_date_dtstart(self, page: Page, make_project, make_phase):
        project_name = make_project()
        make_phase(project_name, start="2027-03-01", end="2027-05-31")
        project = ProjectPage(page)
        body = project.fetch_ics()
        # All phase VEVENTs must use VALUE=DATE (phases are always all-day spans)
        for block in re.split(r'BEGIN:VEVENT', body)[1:]:
            if 'SUMMARY:📅' not in block:
                continue
            assert 'DTSTART;VALUE=DATE:' in block, \
                f"Phase VEVENT should use VALUE=DATE:\n{block}"
            assert 'DTSTART;TZID=' not in block, \
                f"Phase VEVENT must not have a TZID datetime:\n{block}"

    def test_phase_dtend_is_day_after_end_date(self, page: Page, make_project, make_phase):
        project_name = make_project()
        make_phase(project_name, start="2027-04-01", end="2027-04-30")
        project = ProjectPage(page)
        body = project.fetch_ics()
        for block in re.split(r'BEGIN:VEVENT', body)[1:]:
            if 'SUMMARY:📅' not in block:
                continue
            # Per RFC 5545 DTEND for a DATE value is exclusive — one day after
            assert 'DTEND;VALUE=DATE:20270501' in block, \
                f"Phase DTEND should be 20270501 (exclusive day after 2027-04-30):\n{block}"

    # ── Phase milestones ──────────────────────────────────────────────────────

    def test_phase_milestone_summary_has_flag_emoji(
        self, page: Page, make_project, make_phase
    ):
        project_name = make_project()
        phase_name = make_phase(project_name)
        ms_name = rand_milestone_name()
        project = ProjectPage(page)
        project.add_milestone(phase_name, ms_name, "2027-06-15")
        body = project.fetch_ics()
        block = _vevent_block(body, ms_name)
        assert block, f"VEVENT for milestone '{ms_name}' not found"
        assert f"SUMMARY:🏁 {ms_name}" in block, \
            f"Phase milestone summary should start with 🏁, got:\n{block}"

    def test_phase_milestone_no_legacy_bracket_prefix(
        self, page: Page, make_project, make_phase
    ):
        project_name = make_project()
        phase_name = make_phase(project_name)
        ms_name = rand_milestone_name()
        project = ProjectPage(page)
        project.add_milestone(phase_name, ms_name, "2027-06-15")
        body = project.fetch_ics()
        assert '[Milestone]' not in body, \
            "Legacy '[Milestone]' text prefix must not appear anywhere in the ICS feed"

    def test_milestone_dtend_is_next_day(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        ms_name = rand_milestone_name()
        project = ProjectPage(page)
        project.add_milestone(phase_name, ms_name, "2027-07-10")
        body = project.fetch_ics()
        block = _vevent_block(body, ms_name)
        assert 'DTSTART;VALUE=DATE:20270710' in block, \
            f"Milestone DTSTART should be 20270710:\n{block}"
        assert 'DTEND;VALUE=DATE:20270711' in block, \
            f"Milestone DTEND should be 20270711 (exclusive next day):\n{block}"

    # ── Project-level milestones ──────────────────────────────────────────────

    def test_project_milestone_summary_has_flag_emoji(
        self, page: Page, make_project
    ):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_to(project_name)
        ms_name = rand_milestone_name()
        # Add via the project-wide milestone button
        project.add_project_milestone(ms_name, "2027-08-01")
        body = project.fetch_ics()
        block = _vevent_block(body, ms_name)
        assert block, f"VEVENT for project milestone '{ms_name}' not found"
        assert f"SUMMARY:🏁 {ms_name}" in block, \
            f"Project milestone summary should start with 🏁 (not '[Milestone]'), got:\n{block}"

    def test_project_milestone_no_legacy_bracket_prefix(
        self, page: Page, make_project
    ):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_to(project_name)
        ms_name = rand_milestone_name()
        project.add_project_milestone(ms_name, "2027-08-01")
        body = project.fetch_ics()
        assert '[Milestone]' not in body, \
            "Legacy '[Milestone]' text prefix must not appear in the ICS feed"

    # ── Events (phase and project) — no prefix ────────────────────────────────

    def test_phase_event_summary_is_bare_name(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        event_name = rand_event_name()
        project = ProjectPage(page)
        project.add_phase_event(phase_name, event_name, "2027-09-01", "2027-09-02")
        body = project.fetch_ics()
        block = _vevent_block(body, event_name)
        assert block, f"VEVENT for phase event '{event_name}' not found"
        assert f"SUMMARY:{event_name}" in block, \
            f"Phase event summary should be the bare name with no prefix, got:\n{block}"

    def test_project_event_summary_is_bare_name(self, page: Page, make_project):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_to(project_name)
        event_name = rand_event_name()
        project.add_project_event(event_name, "2027-10-05", "2027-10-06")
        body = project.fetch_ics()
        block = _vevent_block(body, event_name)
        assert block, f"VEVENT for project event '{event_name}' not found"
        assert f"SUMMARY:{event_name}" in block, \
            f"Project event summary should be the bare name with no prefix, got:\n{block}"


class TestICSEventTiming:
    """Timed vs all-day event export: DTSTART datetime vs VALUE=DATE."""

    # ── Project-level events ──────────────────────────────────────────────────

    def test_timed_project_event_uses_datetime_dtstart(self, page: Page, make_project):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_to(project_name)
        event_name = rand_event_name()
        project.add_project_event(
            event_name, "2027-07-15", "2027-07-15",
            all_day=False, start_time="09:15", end_time="10:45",
        )

        body = project.fetch_ics()
        block = _vevent_block(body, event_name)
        assert block, f"VEVENT block for '{event_name}' not found in ICS feed"

        assert "DTSTART;TZID=" in block, \
            "Timed event should use DTSTART;TZID=… not DTSTART;VALUE=DATE"
        assert "DTSTART;VALUE=DATE" not in block, \
            "Timed event must not use all-day VALUE=DATE format"
        assert "T091500" in block, \
            f"Start time 09:15 should appear as T091500 in VEVENT, got:\n{block}"
        assert "T104500" in block, \
            f"End time 10:45 should appear as T104500 in VEVENT, got:\n{block}"

    def test_all_day_project_event_uses_date_dtstart(self, page: Page, make_project):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_to(project_name)
        event_name = rand_event_name()
        project.add_project_event(event_name, "2027-08-01", "2027-08-02", all_day=True)

        body = project.fetch_ics()
        block = _vevent_block(body, event_name)
        assert block, f"VEVENT block for '{event_name}' not found in ICS feed"

        assert "DTSTART;VALUE=DATE:" in block, \
            "All-day event should use DTSTART;VALUE=DATE: format"
        assert "DTSTART;TZID=" not in block, \
            "All-day event must not use a TZID datetime DTSTART"

    # ── Phase-level events ────────────────────────────────────────────────────

    def test_timed_phase_event_uses_datetime_dtstart(
        self, page: Page, make_project, make_phase
    ):
        project_name = make_project()
        phase_name = make_phase(project_name)
        project = ProjectPage(page)
        event_name = rand_event_name()
        project.add_phase_event(
            phase_name, event_name, "2027-09-10", "2027-09-10",
            all_day=False, start_time="14:00", end_time="15:30",
        )

        body = project.fetch_ics()
        block = _vevent_block(body, event_name)
        assert block, f"VEVENT block for '{event_name}' not found in ICS feed"

        assert "DTSTART;TZID=" in block, \
            "Timed phase event should use DTSTART;TZID=… not DTSTART;VALUE=DATE"
        assert "DTSTART;VALUE=DATE" not in block, \
            "Timed phase event must not use all-day VALUE=DATE format"
        assert "T140000" in block, \
            f"Start time 14:00 should appear as T140000 in VEVENT, got:\n{block}"
        assert "T153000" in block, \
            f"End time 15:30 should appear as T153000 in VEVENT, got:\n{block}"

    def test_all_day_phase_event_uses_date_dtstart(
        self, page: Page, make_project, make_phase
    ):
        project_name = make_project()
        phase_name = make_phase(project_name)
        project = ProjectPage(page)
        event_name = rand_event_name()
        project.add_phase_event(
            phase_name, event_name, "2027-10-01", "2027-10-03", all_day=True,
        )

        body = project.fetch_ics()
        block = _vevent_block(body, event_name)
        assert block, f"VEVENT block for '{event_name}' not found in ICS feed"

        assert "DTSTART;VALUE=DATE:" in block, \
            "All-day phase event should use DTSTART;VALUE=DATE: format"
        assert "DTSTART;TZID=" not in block, \
            "All-day phase event must not use a TZID datetime DTSTART"
