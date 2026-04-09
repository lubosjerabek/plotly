"""
Form validation tests.

Covers:
- Required-name enforcement on project / phase / milestone / phase-event modals
- End-date-before-start-date error on phase and event modals
- End-date auto-advance when start is set to a date after the current end
"""
import re
import pytest
from playwright.sync_api import Page, expect

from pages import DashboardPage, ProjectPage


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

    def test_create_project_error_clears_on_valid_submit(self, page: Page, make_project):
        """After a failed submission, a valid name clears the error and saves."""
        dashboard = DashboardPage(page)
        dashboard.goto()
        dashboard.open_new_project_modal()

        # Trigger error first
        page.locator(DashboardPage.PM_SUBMIT).click()
        expect(page.locator(f"{PROJECT_MODAL} {FIELD_ERROR}")).to_be_visible()

        # Now fill a valid name and submit via make_project's create path
        page.locator(DashboardPage.PM_SUBMIT).click()   # still empty → still error
        page.locator(DashboardPage.PM_NAME).fill("Temp Validation Project")
        page.locator(DashboardPage.PM_SUBMIT).click()
        expect(page.locator(DashboardPage.TOAST_SUCCESS).last).to_be_visible()
        # cleanup
        DashboardPage(page).delete_project("Temp Validation Project")


# ══════════════════════════════════════════════════════════════════════════════
# Project page modals – required name
# ══════════════════════════════════════════════════════════════════════════════

class TestProjectModalValidation:

    @pytest.fixture(autouse=True)
    def _setup(self, page: Page, session_project):
        self.project = ProjectPage(page)
        self.project_name = session_project
        self.page = page

    # ── Phase ──────────────────────────────────────────────────────────────────

    def test_add_phase_requires_name(self):
        self.project.navigate_to(self.project_name)
        self.page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Leave name empty, fill valid dates, submit
        self.page.locator(MODAL_START).fill("2027-01-01")
        self.page.locator(MODAL_END).fill("2027-06-30")
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")).to_be_visible()

    # ── Milestone ──────────────────────────────────────────────────────────────

    def test_add_milestone_requires_name(self, make_phase):
        project_name = self.project_name
        phase_name = make_phase(project_name)
        phase = self.project.get_phase_card(phase_name)
        phase.expand(self.page)
        phase.milestones_section().locator("button", has_text="Add").click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Leave name empty, fill valid date, submit
        self.page.locator(ProjectPage.MODAL_TARGET).fill("2027-03-01")
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
        self.page.locator(MODAL_START).fill("2027-02-01")
        self.page.locator(MODAL_END).fill("2027-02-05")
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")).to_be_visible()

    # ── Project event ──────────────────────────────────────────────────────────

    def test_add_project_event_requires_name(self):
        self.project.navigate_to(self.project_name)
        self.page.locator("button", has_text=ProjectPage.ADD_EVENTS_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Leave name empty, fill valid dates, submit
        self.page.locator(MODAL_START).fill("2027-05-01")
        self.page.locator(MODAL_END).fill("2027-05-02")
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")).to_be_visible()


# ══════════════════════════════════════════════════════════════════════════════
# Date-range validation: end before start
# ══════════════════════════════════════════════════════════════════════════════

class TestDateValidation:

    @pytest.fixture(autouse=True)
    def _setup(self, page: Page, session_project):
        self.project = ProjectPage(page)
        self.project_name = session_project
        self.page = page

    def test_phase_end_before_start_shows_error(self):
        self.project.navigate_to(self.project_name)
        self.page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        self.page.locator(MODAL_NAME).fill("Backward Phase")
        self.page.locator(MODAL_START).fill("2027-06-01")
        self.page.locator(MODAL_END).fill("2027-03-01")   # earlier than start
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        error = self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")
        expect(error).to_be_visible()

    def test_event_end_before_start_shows_error(self):
        self.project.navigate_to(self.project_name)
        self.page.locator("button", has_text=ProjectPage.ADD_EVENTS_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        self.page.locator(MODAL_NAME).fill("Backward Event")
        self.page.locator(MODAL_START).fill("2027-09-15")
        self.page.locator(MODAL_END).fill("2027-08-01")   # earlier than start
        self.page.locator(MODAL_SUBMIT).click()

        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(self.page.locator(f"{GENERIC_MODAL} {FIELD_ERROR}")).to_be_visible()

    def test_phase_end_advances_when_start_set_later(self):
        """Setting start to a date after current end auto-advances end to match."""
        self.project.navigate_to(self.project_name)
        self.page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Both fields default to today; set start to a future date
        _fill_start_trigger_change(self.page, "2027-05-01")

        end_value = self.page.locator(MODAL_END).input_value()
        assert end_value >= "2027-05-01", (
            f"End date should have advanced to at least 2027-05-01, got '{end_value}'"
        )

    def test_event_end_advances_when_start_set_later(self):
        """Same auto-advance behaviour on the event modal."""
        self.project.navigate_to(self.project_name)
        self.page.locator("button", has_text=ProjectPage.ADD_EVENTS_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        _fill_start_trigger_change(self.page, "2027-07-10")

        end_value = self.page.locator(MODAL_END).input_value()
        assert end_value >= "2027-07-10", (
            f"End date should have advanced to at least 2027-07-10, got '{end_value}'"
        )

    def test_end_not_changed_when_start_is_before_end(self):
        """If start is still before current end, end must not be touched."""
        self.project.navigate_to(self.project_name)
        self.page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(self.page.locator(GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))

        # Set end to a far-future date first, then set start to an earlier date
        self.page.locator(MODAL_END).fill("2027-12-31")
        _fill_start_trigger_change(self.page, "2027-01-01")

        end_value = self.page.locator(MODAL_END).input_value()
        assert end_value == "2027-12-31", (
            f"End date should be unchanged at 2027-12-31, got '{end_value}'"
        )
