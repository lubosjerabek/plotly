"""
ICS / calendar subscription tests.
"""
import re
from datetime import datetime, timedelta

import pytest
from conftest import rand_date_range, rand_event_name, rand_future_date, rand_milestone_name
from pages import BASE_URL, ProjectPage
from playwright.sync_api import Page, expect


def _vevent_block(body: str, summary_fragment: str) -> str:
    """Return the VEVENT block whose SUMMARY line contains summary_fragment."""
    for block in re.split(r'BEGIN:VEVENT', body)[1:]:
        if summary_fragment in block:
            return 'BEGIN:VEVENT' + block.split('END:VEVENT')[0] + 'END:VEVENT'
    return ''


class TestICSSubscription:
    """Modal, URL structure, and auth-gate tests."""

    @pytest.fixture(autouse=True)
    def _setup(self, page: Page, project):
        self.project = ProjectPage(page)
        self.project_name = project
        self.project_id = project.id

    def test_subscribe_button_opens_modal(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        self.project.open_subscribe_modal()
        expect(page.locator(ProjectPage.ICS_URL)).to_be_visible()

    def test_subscribe_modal_url_contains_token(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        self.project.open_subscribe_modal()
        ics_url = self.project.get_ics_url()
        assert "token=" in ics_url, f"ICS URL missing token parameter: {ics_url}"
        assert "/calendar.ics" in ics_url, f"ICS URL missing /calendar.ics: {ics_url}"

    def test_ics_feed_returns_vcalendar(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        body = self.project.fetch_ics()
        assert "BEGIN:VCALENDAR" in body, "ICS response missing BEGIN:VCALENDAR"
        assert "END:VCALENDAR" in body, "ICS response missing END:VCALENDAR"

    def test_ics_feed_rejected_without_token(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        resp = page.request.get(BASE_URL + f"/project/{self.project_id}/calendar.ics")
        assert resp.status in (401, 403), \
            f"Expected 401/403 for ICS without token, got {resp.status}"

    def test_ics_feed_rejected_with_wrong_token(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        resp = page.request.get(
            BASE_URL + f"/project/{self.project_id}/calendar.ics?token=invalid-token-value"
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
        target = rand_future_date()
        project = ProjectPage(page)
        project.add_milestone(phase_name, milestone_name, target)

        body = project.fetch_ics()
        assert milestone_name in body, \
            f"Milestone '{milestone_name}' not found in ICS feed"

    def test_milestone_removal_removes_from_ics(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        milestone_name = rand_milestone_name()
        target = rand_future_date()
        project = ProjectPage(page)
        project.add_milestone(phase_name, milestone_name, target)

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
        start, end = rand_date_range()
        project = ProjectPage(page)
        project.add_phase_event(phase_name, event_name, start, end)

        body = project.fetch_ics()
        assert event_name in body, \
            f"Phase event '{event_name}' not found in ICS feed"

    def test_phase_event_removal_removes_from_ics(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        event_name = rand_event_name()
        start, end = rand_date_range()
        project = ProjectPage(page)
        project.add_phase_event(phase_name, event_name, start, end)

        assert event_name in project.fetch_ics(), \
            f"Pre-condition failed: phase event '{event_name}' not in ICS before deletion"

        project.delete_phase_event(phase_name, event_name)

        assert event_name not in project.fetch_ics(), \
            f"Phase event '{event_name}' still present in ICS after deletion"

    # ── Project-level events ──────────────────────────────────────────────────

    def test_project_event_appears_in_ics_feed(self, page: Page, make_project):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(project_name.id)
        event_name = rand_event_name()
        start, end = rand_date_range()
        project.add_project_event(event_name, start, end)

        body = project.fetch_ics()
        assert event_name in body, \
            f"Project event '{event_name}' not found in ICS feed"

    def test_project_event_removal_removes_from_ics(self, page: Page, make_project):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(project_name.id)
        event_name = rand_event_name()
        start, end = rand_date_range()
        project.add_project_event(event_name, start, end)

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
        start, end = rand_date_range()
        make_phase(project_name, start=start, end=end)
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
        start, end = rand_date_range()
        make_phase(project_name, start=start, end=end)
        project = ProjectPage(page)
        body = project.fetch_ics()
        end_dt = datetime.strptime(end, "%Y-%m-%d").date()
        expected_dtend = (end_dt + timedelta(days=1)).strftime("%Y%m%d")
        for block in re.split(r'BEGIN:VEVENT', body)[1:]:
            if 'SUMMARY:📅' not in block:
                continue
            assert f'DTEND;VALUE=DATE:{expected_dtend}' in block, \
                f"Phase DTEND should be {expected_dtend} (exclusive day after {end}):\n{block}"

    # ── Phase milestones ──────────────────────────────────────────────────────

    def test_phase_milestone_summary_has_flag_emoji(
        self, page: Page, make_project, make_phase
    ):
        project_name = make_project()
        phase_name = make_phase(project_name)
        ms_name = rand_milestone_name()
        target = rand_future_date()
        project = ProjectPage(page)
        project.add_milestone(phase_name, ms_name, target)
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
        target = rand_future_date()
        project = ProjectPage(page)
        project.add_milestone(phase_name, ms_name, target)
        body = project.fetch_ics()
        assert '[Milestone]' not in body, \
            "Legacy '[Milestone]' text prefix must not appear anywhere in the ICS feed"

    def test_milestone_dtend_is_next_day(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        ms_name = rand_milestone_name()
        target = rand_future_date()
        target_dt = datetime.strptime(target, "%Y-%m-%d").date()
        expected_dtstart = target_dt.strftime("%Y%m%d")
        expected_dtend = (target_dt + timedelta(days=1)).strftime("%Y%m%d")
        project = ProjectPage(page)
        project.add_milestone(phase_name, ms_name, target)
        body = project.fetch_ics()
        block = _vevent_block(body, ms_name)
        assert f'DTSTART;VALUE=DATE:{expected_dtstart}' in block, \
            f"Milestone DTSTART should be {expected_dtstart}:\n{block}"
        assert f'DTEND;VALUE=DATE:{expected_dtend}' in block, \
            f"Milestone DTEND should be {expected_dtend} (exclusive next day):\n{block}"

    # ── Project-level milestones ──────────────────────────────────────────────

    def test_project_milestone_summary_has_flag_emoji(
        self, page: Page, make_project
    ):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(project_name.id)
        ms_name = rand_milestone_name()
        target = rand_future_date()
        project.add_project_milestone(ms_name, target)
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
        project.navigate_by_id(project_name.id)
        ms_name = rand_milestone_name()
        target = rand_future_date()
        project.add_project_milestone(ms_name, target)
        body = project.fetch_ics()
        assert '[Milestone]' not in body, \
            "Legacy '[Milestone]' text prefix must not appear in the ICS feed"

    # ── Events (phase and project) — no prefix ────────────────────────────────

    def test_phase_event_summary_is_bare_name(self, page: Page, make_project, make_phase):
        project_name = make_project()
        phase_name = make_phase(project_name)
        event_name = rand_event_name()
        start, end = rand_date_range()
        project = ProjectPage(page)
        project.add_phase_event(phase_name, event_name, start, end)
        body = project.fetch_ics()
        block = _vevent_block(body, event_name)
        assert block, f"VEVENT for phase event '{event_name}' not found"
        assert f"SUMMARY:{event_name}" in block, \
            f"Phase event summary should be the bare name with no prefix, got:\n{block}"

    def test_project_event_summary_is_bare_name(self, page: Page, make_project):
        project_name = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(project_name.id)
        event_name = rand_event_name()
        start, end = rand_date_range()
        project.add_project_event(event_name, start, end)
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
        project.navigate_by_id(project_name.id)
        event_name = rand_event_name()
        event_date = rand_future_date()
        project.add_project_event(
            event_name, event_date, event_date,
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
        project.navigate_by_id(project_name.id)
        event_name = rand_event_name()
        start, end = rand_date_range()
        project.add_project_event(event_name, start, end, all_day=True)

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
        event_date = rand_future_date()
        project.add_phase_event(
            phase_name, event_name, event_date, event_date,
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
        start, end = rand_date_range()
        project.add_phase_event(phase_name, event_name, start, end, all_day=True)

        body = project.fetch_ics()
        block = _vevent_block(body, event_name)
        assert block, f"VEVENT block for '{event_name}' not found in ICS feed"

        assert "DTSTART;VALUE=DATE:" in block, \
            "All-day phase event should use DTSTART;VALUE=DATE: format"
        assert "DTSTART;TZID=" not in block, \
            "All-day phase event must not use a TZID datetime DTSTART"
