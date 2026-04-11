"""
Project detail tests: phases, milestones, events, modals, navigation.
Depends on the ``session_project`` fixture providing a pre-created project.
"""
import re
import pytest
from playwright.sync_api import Page, expect

from pages import BASE_URL, DashboardPage, ProjectPage


class TestProjectDetail:

    @pytest.fixture(autouse=True)
    def _setup(self, page: Page, session_project):
        self.project = ProjectPage(page)
        self.project_name = session_project

    def test_navigate_to_project(self, page: Page):
        self.project.navigate_to(self.project_name)
        expect(page).to_have_url(re.compile(r"/project/\d+"))

    def test_project_header_shows_name(self, page: Page):
        self.project.navigate_to(self.project_name)
        expect(page.locator(ProjectPage.PROJECT_NAME)).to_contain_text(self.project_name)

    def test_topbar_shows_project_name(self, page: Page):
        self.project.navigate_to(self.project_name)
        expect(page.locator(ProjectPage.TOPBAR_TITLE)).to_contain_text(self.project_name)

    def test_phases_tab_active_by_default(self, page: Page):
        self.project.navigate_to(self.project_name)
        expect(page.locator(ProjectPage.PHASES_TAB)).to_have_class(re.compile(r"active"))

    def test_timeline_tab_visible(self, page: Page):
        self.project.navigate_to(self.project_name)
        expect(page.locator(".tab-btn", has_text="Timeline")).to_be_visible()

    def test_collaborators_tab_visible(self, page: Page):
        self.project.navigate_to(self.project_name)
        expect(page.locator(ProjectPage.COLLABORATORS_TAB)).to_be_visible()

    def test_add_phase_opens_modal(self, page: Page):
        self.project.navigate_to(self.project_name)
        page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_TITLE)).to_contain_text("Add Phase")

    def test_add_phase_creates_card_with_upcoming_badge(self, page: Page, make_project):
        name = make_project()
        project = ProjectPage(page)
        project.navigate_to(name)
        project.add_phase("Alpha Phase", "2027-01-01", "2027-03-31")
        phase = project.get_phase_card("Alpha Phase")
        expect(phase._loc).to_be_visible()
        expect(phase.badge_upcoming).to_be_visible()

    def test_add_phase_with_description(self, page: Page, make_project):
        name = make_project()
        project = ProjectPage(page)
        project.navigate_to(name)
        project.add_phase(
            "Described Phase", "2027-07-01", "2027-09-30",
            desc="A detailed description for this phase.",
        )
        phase = project.get_phase_card("Described Phase")
        phase.expand(page)
        expect(phase.description_el).to_contain_text("A detailed description")

    def test_upcoming_phase_collapsed_by_default(self, page: Page, make_project, make_phase):
        name = make_project()
        phase_name = make_phase(name)
        # make_phase leaves the page on the project page
        phase = ProjectPage(page).get_phase_card(phase_name)
        assert phase.is_collapsed()

    def test_phase_expand_and_collapse(self, page: Page, make_project, make_phase):
        name = make_project()
        phase_name = make_phase(name)
        phase = ProjectPage(page).get_phase_card(phase_name)
        # Expand
        phase.toggle()
        page.wait_for_timeout(300)
        expect(phase._loc).not_to_have_class(re.compile(r"is-collapsed"))
        # Collapse
        phase.toggle()
        page.wait_for_timeout(300)
        expect(phase._loc).to_have_class(re.compile(r"is-collapsed"))

    def test_edit_phase_prepopulates_name(self, page: Page, make_project, make_phase):
        name = make_project()
        phase_name = make_phase(name)
        project = ProjectPage(page)
        project.get_phase_card(phase_name).edit_btn.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_NAME)).to_have_value(phase_name)
        page.keyboard.press("Escape")

    def test_esc_closes_generic_modal(self, page: Page):
        self.project.navigate_to(self.project_name)
        page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        page.keyboard.press("Escape")
        expect(page.locator(ProjectPage.GENERIC_MODAL)).not_to_have_class(re.compile(r"is-open"))

    def test_add_milestone_to_phase(self, page: Page, make_project, make_phase):
        name = make_project()
        phase_name = make_phase(name)
        project = ProjectPage(page)
        project.add_milestone(phase_name, "Kickoff Meeting", "2027-01-15")
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        expect(phase.milestones_section().locator(ProjectPage.ITEM_LIST)).to_contain_text("Kickoff Meeting")

    def test_add_event_to_phase(self, page: Page, make_project, make_phase):
        name = make_project()
        phase_name = make_phase(name)
        project = ProjectPage(page)
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        phase.events_section().locator("button", has_text="Add").click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_TITLE)).to_contain_text("Event")
        page.locator(ProjectPage.MODAL_NAME).fill("Sprint Demo")
        page.locator(ProjectPage.MODAL_START).fill("2027-01-20")
        page.locator(ProjectPage.MODAL_END).fill("2027-01-20")
        page.locator(ProjectPage.MODAL_SUBMIT).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        phase.expand(page)
        expect(phase.events_section().locator(ProjectPage.ITEM_LIST)).to_contain_text("Sprint Demo")

    def test_delete_milestone(self, page: Page, make_project, make_phase):
        name = make_project()
        phase_name = make_phase(name)
        project = ProjectPage(page)
        # Add a milestone first so there's something to delete
        project.add_milestone(phase_name, "Kickoff Meeting", "2027-01-15")
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        ms = phase.milestones_section().locator(".item-list li", has_text="Kickoff Meeting")
        ms.locator("button[title='Delete milestone']").click()
        expect(page.locator(ProjectPage.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        page.locator(ProjectPage.CONFIRM_OK).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        phase.expand(page)
        expect(phase.milestones_section().locator(ProjectPage.ITEM_LIST)).not_to_contain_text("Kickoff Meeting")

    def test_delete_event(self, page: Page, make_project, make_phase):
        name = make_project()
        phase_name = make_phase(name)
        project = ProjectPage(page)
        # Add an event first so there's something to delete
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        phase.events_section().locator("button", has_text="Add").click()
        page.locator(ProjectPage.MODAL_NAME).fill("Sprint Demo")
        page.locator(ProjectPage.MODAL_START).fill("2027-01-20")
        page.locator(ProjectPage.MODAL_END).fill("2027-01-20")
        page.locator(ProjectPage.MODAL_SUBMIT).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        # Now delete it
        phase.expand(page)
        ev = phase.events_section().locator(".item-list li", has_text="Sprint Demo")
        ev.locator("button[title='Delete event']").click()
        expect(page.locator(ProjectPage.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        page.locator(ProjectPage.CONFIRM_OK).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        phase.expand(page)
        expect(phase.events_section().locator(ProjectPage.ITEM_LIST)).not_to_contain_text("Sprint Demo")

    def test_delete_phase(self, page: Page, make_project, make_phase):
        name = make_project()
        phase_name = make_phase(name)
        project = ProjectPage(page)
        project.get_phase_card(phase_name).delete_btn.click()
        expect(page.locator(ProjectPage.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        page.locator(ProjectPage.CONFIRM_OK).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(project.get_phase_card(phase_name)._loc).not_to_be_attached()

    def test_back_link_returns_to_dashboard(self, page: Page):
        self.project.navigate_to(self.project_name)
        page.locator(ProjectPage.BACK_LINK).click()
        page.wait_for_load_state("networkidle")
        expect(page).to_have_url(BASE_URL + "/")
        expect(page.locator("h1")).to_contain_text("Projects")

    def test_edit_project_from_project_page(self, page: Page):
        self.project.navigate_to(self.project_name)
        page.locator("button", has_text=ProjectPage.EDIT_PROJECT_BTN).first.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_NAME)).to_have_value(self.project_name)
        page.locator(ProjectPage.MODAL_SUBMIT).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()

    def test_add_project_event_all_day(self, page: Page, make_project):
        name = make_project()
        project = ProjectPage(page)
        project.navigate_to(name)
        project.add_project_event("Launch Day", "2027-06-01", "2027-06-01", all_day=True)
        # all-day checkbox should be checked by default; time fields hidden
        expect(page.locator(ProjectPage.ITEMS_BODY)).to_contain_text("Launch Day")

    def test_add_project_event_with_time(self, page: Page, make_project):
        name = make_project()
        project = ProjectPage(page)
        project.navigate_to(name)
        project.add_project_event(
            "Team Meeting", "2027-07-15", "2027-07-15",
            all_day=False, start_time="10:00", end_time="11:30",
        )
        expect(page.locator(ProjectPage.ITEMS_BODY)).to_contain_text("Team Meeting")
        expect(page.locator(ProjectPage.ITEMS_BODY)).to_contain_text("10:00")

    def test_edit_phase_start_shifts_end_by_same_delta(self, page: Page, make_project, make_phase):
        """Changing start date in the edit-phase modal auto-shifts the end date."""
        name = make_project()
        # 10-day phase: 2027-01-10 → 2027-01-20
        phase_name = make_phase(name, start="2027-01-10", end="2027-01-20")
        project = ProjectPage(page)
        project.get_phase_card(phase_name).edit_btn.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        # Shift start +10 days; end should follow: 2027-01-20 → 2027-01-30
        page.locator(ProjectPage.MODAL_START).fill("2027-01-20")
        expect(page.locator(ProjectPage.MODAL_END)).to_have_value("2027-01-30")
        page.keyboard.press("Escape")

    def test_edit_event_start_shifts_end_by_same_delta(self, page: Page, make_project, make_phase):
        """Changing start date in the edit-event modal auto-shifts the end date."""
        name = make_project()
        phase_name = make_phase(name, start="2027-01-01", end="2027-06-30")
        project = ProjectPage(page)
        # 6-day event: 2027-02-01 → 2027-02-07
        project.add_phase_event(phase_name, "Shift Me Event", "2027-02-01", "2027-02-07")
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        phase.events_section().locator(".item-list li", has_text="Shift Me Event") \
             .locator(ProjectPage.EDIT_EVENT_BTN).click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        # Shift start +7 days; end should follow: 2027-02-07 → 2027-02-14
        page.locator(ProjectPage.MODAL_START).fill("2027-02-08")
        expect(page.locator(ProjectPage.MODAL_END)).to_have_value("2027-02-14")
        page.keyboard.press("Escape")

    def test_edit_project_event(self, page: Page, make_project):
        """Project-level events have an Edit button."""
        name = make_project()
        project = ProjectPage(page)
        project.navigate_to(name)
        project.add_project_event("Editable Event", "2027-08-01", "2027-08-02")
        ev_row = page.locator(ProjectPage.ITEMS_BODY + " li", has_text="Editable Event")
        ev_row.locator("button[title]").first.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_NAME)).to_have_value("Editable Event")
        page.locator(ProjectPage.MODAL_NAME).fill("Renamed Event")
        page.locator(ProjectPage.MODAL_SUBMIT).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(page.locator(ProjectPage.ITEMS_BODY)).to_contain_text("Renamed Event")
