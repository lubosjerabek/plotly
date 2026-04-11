"""
Form validation tests.

Covers:
- Required-name enforcement on project / phase / milestone / phase-event modals
- End-date-before-start-date error on phase and event modals
- End-date auto-advance when start is set to a date after the current end
"""
import re
from datetime import date, timedelta

import pytest
from playwright.sync_api import Page, expect

from pages import DashboardPage, ProjectPage
from conftest import rand_phase_name, rand_event_name, rand_future_date


# ── Selectors for validation feedback ──────────────────────────────────────────
FIELD_ERROR      = ".field-error"
GENERIC_MODAL    = ProjectPage.GENERIC_MODAL
PROJECT_MODAL    = DashboardPage.PROJECT_MODAL
MODAL_START      = ProjectPage.MODAL_START
MODAL_END        = ProjectPage.MODAL_END
MODAL_NAME       = ProjectPage.MODAL_NAME
MODAL_SUBMIT     = ProjectPage.MODAL_SUBMIT


# ── Helpers ────────────────────────────────────────────────────────────────────

def _fill_start_trigger_change(page: Page, value: str):
    """Fill the start-date field and dispatch a change event explicitly."""
    page.locator(MODAL_START).fill(value)
    page.locator(MODAL_START).dispatch_event("change")


# ══════════════════════════════════════════════════════════════════════════════
# Dashboard – project creation modal
# ══════════════════════════════════════════════════════════════════════════════

class TestDashboardValidation:

    def test_create_project_requires_name(self, page: Page):
        """Submitting the project modal with an empty name shows an error."""
        dashboard = DashboardPage(page)
        dashboard.goto()
        dashboard.open_new_project_modal()

        # Submit without filling the name
        page.locator(DashboardPage.PM_SUBMIT).click()

        expect(page.locator(PROJECT_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(f"{PROJECT_MODAL} {FIELD_ERROR}")).to_be_visible()

    def test_create_project_error_clears_on_valid_submit(self, page: Page):
        """After a failed submission, a valid name clears the error and saves."""
        from pages import BASE_URL
        from conftest import rand_project_name
        dashboard = DashboardPage(page)
        dashboard.goto()
        dashboard.open_new_project_modal()

        # Trigger error first
        page.locator(DashboardPage.PM_SUBMIT).click()
        expect(page.locator(f"{PROJECT_MODAL} {FIELD_ERROR}")).to_be_visible()

        # Submit empty again (error remains), then fill and submit
        page.locator(DashboardPage.PM_SUBMIT).click()
        name = rand_project_name()
        page.locator(DashboardPage.PM_NAME).fill(name)
        page.locator(DashboardPage.PM_SUBMIT).click()
        expect(page.locator(DashboardPage.TOAST_SUCCESS).last).to_be_visible()

        # Cleanup: find the newly created project and delete via API
        resp = page.request.get(
            BASE_URL + "/api/projects",
            headers={"X-Requested-With": "XMLHttpRequest"},
        )
        if resp.status == 200:
            for p in resp.json():
                if p["name"] == name:
                    page.request.delete(
                        BASE_URL + f"/api/projects/{p['id']}",
                        headers={"X-Requested-With": "XMLHttpRequest"},
                    )
                    break


# ══════════════════════════════════════════════════════════════════════════════
# Project page modals – required name
# ══════════════════════════════════════════════════════════════════════════════

class TestProjectModalValidation:

    @pytest.fixture(autouse=True)
    def _setup(self, page: Page, project):
        self.project = ProjectPage(page)
        self.project_name = project
        self.project_id = project.id
        self.page = page

    # ── Phase ──────────────────────────────────────────────────────────────────

    def test_add_phase_requires_name(self):
        self.project.navigate_by_id(self.project_id)
        self.page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Leave name empty, fill valid dates, submit
        start = rand_future_date(min_days=30, max_days=90)
        end = rand_future_date(min_days=120, max_days=365)
        self.page.locator(MODAL_START).fill(start)
        self.page.locator(MODAL_END).fill(end)
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")).to_be_visible()

    # ── Milestone ──────────────────────────────────────────────────────────────

    def test_add_milestone_requires_name(self, make_phase):
        phase_name = make_phase(self.project_name)
        phase = self.project.get_phase_card(phase_name)
        phase.expand(self.page)
        phase.milestones_section().locator("button", has_text="Add").click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Leave name empty, fill valid date, submit
        self.page.locator(ProjectPage.MODAL_TARGET).fill(rand_future_date())
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")).to_be_visible()

    # ── Phase event ────────────────────────────────────────────────────────────

    def test_add_phase_event_requires_name(self, make_phase):
        phase_name = make_phase(self.project_name)
        phase = self.project.get_phase_card(phase_name)
        phase.expand(self.page)
        phase.events_section().locator("button", has_text="Add").click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Leave name empty, fill valid dates, submit
        start = rand_future_date(min_days=60, max_days=120)
        end = rand_future_date(min_days=150, max_days=200)
        self.page.locator(MODAL_START).fill(start)
        self.page.locator(MODAL_END).fill(end)
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")).to_be_visible()

    # ── Project event ──────────────────────────────────────────────────────────

    def test_add_project_event_requires_name(self):
        self.project.navigate_by_id(self.project_id)
        self.page.locator("button", has_text=ProjectPage.ADD_EVENTS_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Leave name empty, fill valid dates, submit
        start = rand_future_date(min_days=60, max_days=120)
        end = rand_future_date(min_days=150, max_days=200)
        self.page.locator(MODAL_START).fill(start)
        self.page.locator(MODAL_END).fill(end)
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")).to_be_visible()


# ══════════════════════════════════════════════════════════════════════════════
# Date-range validation: end before start
# ══════════════════════════════════════════════════════════════════════════════

class TestDateValidation:

    @pytest.fixture(autouse=True)
    def _setup(self, page: Page, project):
        self.project = ProjectPage(page)
        self.project_name = project
        self.project_id = project.id
        self.page = page

    def test_phase_end_before_start_shows_error(self):
        self.project.navigate_by_id(self.project_id)
        self.page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Set end before start to trigger validation error
        start = rand_future_date(min_days=90, max_days=180)
        start_dt = date.fromisoformat(start)
        end = (start_dt - timedelta(days=30)).strftime("%Y-%m-%d")

        phase_name = rand_phase_name()
        self.page.locator(MODAL_NAME).fill(phase_name)
        self.page.locator(MODAL_START).fill(start)
        self.page.locator(MODAL_END).fill(end)
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        error = self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")
        expect(error).to_be_visible()

    def test_event_end_before_start_shows_error(self):
        self.project.navigate_by_id(self.project_id)
        self.page.locator("button", has_text=ProjectPage.ADD_EVENTS_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        start = rand_future_date(min_days=90, max_days=180)
        start_dt = date.fromisoformat(start)
        end = (start_dt - timedelta(days=30)).strftime("%Y-%m-%d")

        event_name = rand_event_name()
        self.page.locator(MODAL_NAME).fill(event_name)
        self.page.locator(MODAL_START).fill(start)
        self.page.locator(MODAL_END).fill(end)
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")).to_be_visible()

    def test_phase_end_advances_when_start_set_later(self):
        """Setting start to a date after current end auto-advances end to match."""
        self.project.navigate_by_id(self.project_id)
        self.page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Set start to a far-future date (guaranteed to be after the default end)
        future_start = rand_future_date(min_days=300, max_days=500)
        _fill_start_trigger_change(self.page, future_start)

        end_value = self.page.locator(MODAL_END).input_value()
        assert end_value >= future_start, (
            f"End date should have advanced to at least {future_start}, got '{end_value}'"
        )

    def test_event_end_advances_when_start_set_later(self):
        """Same auto-advance behaviour on the event modal."""
        self.project.navigate_by_id(self.project_id)
        self.page.locator("button", has_text=ProjectPage.ADD_EVENTS_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        future_start = rand_future_date(min_days=300, max_days=500)
        _fill_start_trigger_change(self.page, future_start)

        end_value = self.page.locator(MODAL_END).input_value()
        assert end_value >= future_start, (
            f"End date should have advanced to at least {future_start}, got '{end_value}'"
        )

    def test_end_not_changed_when_start_is_before_end(self):
        """If start is still before current end, end must not be touched."""
        self.project.navigate_by_id(self.project_id)
        self.page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Set end to a far-future date first, then set start to an earlier date
        far_end = rand_future_date(min_days=400, max_days=500)
        near_start = rand_future_date(min_days=60, max_days=100)
        self.page.locator(MODAL_END).fill(far_end)
        _fill_start_trigger_change(self.page, near_start)

        end_value = self.page.locator(MODAL_END).input_value()
        assert end_value == far_end, (
            f"End date should be unchanged at {far_end}, got '{end_value}'"
        )
