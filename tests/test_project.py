"""
Project detail tests: phases, milestones, events, modals, navigation.
Depends on the ``project`` fixture providing a pre-created, isolated project.
"""
import re
from datetime import datetime, timedelta

import pytest
from playwright.sync_api import Page, expect

from pages import BASE_URL, DashboardPage, ProjectPage
from conftest import rand_phase_name, rand_milestone_name, rand_event_name, rand_date_range, rand_future_date


class TestProjectDetail:

    @pytest.fixture(autouse=True)
    def _setup(self, page: Page, project):
        self.project = ProjectPage(page)
        self.project_name = project
        self.project_id = project.id

    def test_navigate_to_project(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        expect(page).to_have_url(re.compile(r"/project/\d+"))

    def test_project_header_shows_name(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        expect(page.locator(ProjectPage.PROJECT_NAME)).to_contain_text(self.project_name)

    def test_topbar_shows_project_name(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        expect(page.locator(ProjectPage.TOPBAR_TITLE)).to_contain_text(self.project_name)

    def test_phases_tab_active_by_default(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        expect(page.locator(ProjectPage.PHASES_TAB)).to_have_class(re.compile(r"active"))

    def test_timeline_tab_visible(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        expect(page.locator(".tab-btn", has_text="Timeline")).to_be_visible()

    def test_collaborators_tab_visible(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        expect(page.locator(ProjectPage.COLLABORATORS_TAB)).to_be_visible()

    def test_add_phase_opens_modal(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_TITLE)).to_contain_text("Add Phase")

    def test_add_phase_creates_card_with_upcoming_badge(self, page: Page, make_project):
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        start, end = rand_date_range()
        phase_name = rand_phase_name()
        project.add_phase(phase_name, start, end)
        phase = project.get_phase_card(phase_name)
        expect(phase._loc).to_be_visible()
        expect(phase.badge_upcoming).to_be_visible()

    def test_add_phase_with_description(self, page: Page, make_project):
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        start, end = rand_date_range()
        phase_name = rand_phase_name()
        desc_text = "A detailed description for this phase."
        project.add_phase(phase_name, start, end, desc=desc_text)
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        expect(phase.description_el).to_contain_text("A detailed description")

    def test_upcoming_phase_collapsed_by_default(self, page: Page, make_project, make_phase):
        ref = make_project()
        phase_name = make_phase(ref)
        phase = ProjectPage(page).get_phase_card(phase_name)
        assert phase.is_collapsed()

    def test_phase_expand_and_collapse(self, page: Page, make_project, make_phase):
        ref = make_project()
        phase_name = make_phase(ref)
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
        ref = make_project()
        phase_name = make_phase(ref)
        project = ProjectPage(page)
        project.get_phase_card(phase_name).edit_btn.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_NAME)).to_have_value(phase_name)
        page.keyboard.press("Escape")

    def test_esc_closes_generic_modal(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        page.locator("button", has_text=ProjectPage.ADD_PHASE_BTN).click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        page.keyboard.press("Escape")
        expect(page.locator(ProjectPage.GENERIC_MODAL)).not_to_have_class(re.compile(r"is-open"))

    def test_add_milestone_to_phase(self, page: Page, make_project, make_phase):
        ref = make_project()
        phase_name = make_phase(ref)
        ms_name = rand_milestone_name()
        target = rand_future_date()
        project = ProjectPage(page)
        project.add_milestone(phase_name, ms_name, target)
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        expect(phase.milestones_section().locator(ProjectPage.ITEM_LIST)).to_contain_text(ms_name)

    def test_add_event_to_phase(self, page: Page, make_project, make_phase):
        ref = make_project()
        phase_name = make_phase(ref)
        event_name = rand_event_name()
        start, _ = rand_date_range(min_dur=0, max_dur=0)
        project = ProjectPage(page)
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        phase.events_section().locator("button", has_text="Add").click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_TITLE)).to_contain_text("Event")
        page.locator(ProjectPage.MODAL_NAME).fill(event_name)
        page.locator(ProjectPage.MODAL_START).fill(start)
        page.locator(ProjectPage.MODAL_END).fill(start)
        page.locator(ProjectPage.MODAL_SUBMIT).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        phase.expand(page)
        expect(phase.events_section().locator(ProjectPage.ITEM_LIST)).to_contain_text(event_name)

    def test_delete_milestone(self, page: Page, make_project, make_phase):
        ref = make_project()
        phase_name = make_phase(ref)
        ms_name = rand_milestone_name()
        target = rand_future_date()
        project = ProjectPage(page)
        project.add_milestone(phase_name, ms_name, target)
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        ms = phase.milestones_section().locator(".item-list li", has_text=ms_name)
        ms.locator("button[title='Delete milestone']").click()
        expect(page.locator(ProjectPage.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        page.locator(ProjectPage.CONFIRM_OK).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        phase.expand(page)
        expect(phase.milestones_section().locator(ProjectPage.ITEM_LIST)).not_to_contain_text(ms_name)

    def test_delete_event(self, page: Page, make_project, make_phase):
        ref = make_project()
        phase_name = make_phase(ref)
        event_name = rand_event_name()
        start, _ = rand_date_range(min_dur=0, max_dur=0)
        project = ProjectPage(page)
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        phase.events_section().locator("button", has_text="Add").click()
        page.locator(ProjectPage.MODAL_NAME).fill(event_name)
        page.locator(ProjectPage.MODAL_START).fill(start)
        page.locator(ProjectPage.MODAL_END).fill(start)
        page.locator(ProjectPage.MODAL_SUBMIT).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        # Now delete it
        phase.expand(page)
        ev = phase.events_section().locator(".item-list li", has_text=event_name)
        ev.locator("button[title='Delete event']").click()
        expect(page.locator(ProjectPage.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        page.locator(ProjectPage.CONFIRM_OK).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        phase.expand(page)
        expect(phase.events_section().locator(ProjectPage.ITEM_LIST)).not_to_contain_text(event_name)

    def test_delete_phase(self, page: Page, make_project, make_phase):
        ref = make_project()
        phase_name = make_phase(ref)
        project = ProjectPage(page)
        project.get_phase_card(phase_name).delete_btn.click()
        expect(page.locator(ProjectPage.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        page.locator(ProjectPage.CONFIRM_OK).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(project.get_phase_card(phase_name)._loc).not_to_be_attached()

    def test_back_link_returns_to_dashboard(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        page.locator(ProjectPage.BACK_LINK).click()
        page.wait_for_load_state("networkidle")
        expect(page).to_have_url(BASE_URL + "/")
        expect(page.locator("h1")).to_contain_text("Projects")

    def test_edit_project_from_project_page(self, page: Page):
        self.project.navigate_by_id(self.project_id)
        page.locator("button", has_text=ProjectPage.EDIT_PROJECT_BTN).first.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_NAME)).to_have_value(self.project_name)
        page.locator(ProjectPage.MODAL_SUBMIT).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()

    def test_add_project_event_all_day(self, page: Page, make_project):
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        event_name = rand_event_name()
        event_date = rand_future_date()
        project.add_project_event(event_name, event_date, event_date, all_day=True)
        expect(page.locator(ProjectPage.ITEMS_BODY)).to_contain_text(event_name)

    def test_add_project_event_with_time(self, page: Page, make_project):
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        event_name = rand_event_name()
        event_date = rand_future_date()
        project.add_project_event(
            event_name, event_date, event_date,
            all_day=False, start_time="10:00", end_time="11:30",
        )
        expect(page.locator(ProjectPage.ITEMS_BODY)).to_contain_text(event_name)
        expect(page.locator(ProjectPage.ITEMS_BODY)).to_contain_text("10:00")

    def test_edit_phase_start_shifts_end_by_same_delta(self, page: Page, make_project, make_phase):
        """Changing start date via programmatic input auto-shifts the end date (input event path)."""
        ref = make_project()
        # 10-day phase: random start → start+10
        start, _ = rand_date_range(min_start=60, max_start=180, min_dur=10, max_dur=10)
        start_dt = datetime.strptime(start, "%Y-%m-%d")
        end_dt = start_dt + timedelta(days=10)
        end = end_dt.strftime("%Y-%m-%d")
        phase_name = make_phase(ref, start=start, end=end)
        project = ProjectPage(page)
        project.get_phase_card(phase_name).edit_btn.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        # Shift start +10 days; end should follow
        new_start_dt = start_dt + timedelta(days=10)
        new_start = new_start_dt.strftime("%Y-%m-%d")
        new_end = (end_dt + timedelta(days=10)).strftime("%Y-%m-%d")
        page.locator(ProjectPage.MODAL_START).fill(new_start)
        expect(page.locator(ProjectPage.MODAL_END)).to_have_value(new_end)
        page.keyboard.press("Escape")

    def test_edit_phase_start_shifts_end_via_change_event(self, page: Page, make_project, make_phase):
        """Changing start date via native picker/blur auto-shifts the end date (change event path)."""
        ref = make_project()
        # 10-day phase
        start, _ = rand_date_range(min_start=60, max_start=180, min_dur=10, max_dur=10)
        start_dt = datetime.strptime(start, "%Y-%m-%d")
        end_dt = start_dt + timedelta(days=10)
        end = end_dt.strftime("%Y-%m-%d")
        phase_name = make_phase(ref, start=start, end=end)
        project = ProjectPage(page)
        project.get_phase_card(phase_name).edit_btn.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        new_start = (start_dt + timedelta(days=10)).strftime("%Y-%m-%d")
        new_end = (end_dt + timedelta(days=10)).strftime("%Y-%m-%d")
        page.locator(ProjectPage.MODAL_START).evaluate(
            f"el => {{ el.value = '{new_start}'; el.dispatchEvent(new Event('change', {{ bubbles: true }})); }}"
        )
        expect(page.locator(ProjectPage.MODAL_END)).to_have_value(new_end)
        page.keyboard.press("Escape")

    def test_edit_event_start_shifts_end_by_same_delta(self, page: Page, make_project, make_phase):
        """Changing start date via programmatic input auto-shifts the end date (input event path)."""
        ref = make_project()
        phase_start, phase_end = rand_date_range(min_start=60, max_start=120, min_dur=90, max_dur=180)
        phase_name = make_phase(ref, start=phase_start, end=phase_end)
        # 6-day event within the phase
        phase_start_dt = datetime.strptime(phase_start, "%Y-%m-%d")
        ev_start_dt = phase_start_dt + timedelta(days=30)
        ev_end_dt = ev_start_dt + timedelta(days=6)
        ev_start = ev_start_dt.strftime("%Y-%m-%d")
        ev_end = ev_end_dt.strftime("%Y-%m-%d")
        event_name = rand_event_name()
        project = ProjectPage(page)
        project.add_phase_event(phase_name, event_name, ev_start, ev_end)
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        phase.events_section().locator(".item-list li", has_text=event_name) \
             .locator(ProjectPage.EDIT_EVENT_BTN).click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        # Shift start +7 days; end should follow
        new_ev_start = (ev_start_dt + timedelta(days=7)).strftime("%Y-%m-%d")
        new_ev_end = (ev_end_dt + timedelta(days=7)).strftime("%Y-%m-%d")
        page.locator(ProjectPage.MODAL_START).fill(new_ev_start)
        expect(page.locator(ProjectPage.MODAL_END)).to_have_value(new_ev_end)
        page.keyboard.press("Escape")

    def test_edit_event_start_shifts_end_via_change_event(self, page: Page, make_project, make_phase):
        """Changing start date via native picker/blur auto-shifts the end date (change event path)."""
        ref = make_project()
        phase_start, phase_end = rand_date_range(min_start=60, max_start=120, min_dur=90, max_dur=180)
        phase_name = make_phase(ref, start=phase_start, end=phase_end)
        phase_start_dt = datetime.strptime(phase_start, "%Y-%m-%d")
        ev_start_dt = phase_start_dt + timedelta(days=30)
        ev_end_dt = ev_start_dt + timedelta(days=6)
        ev_start = ev_start_dt.strftime("%Y-%m-%d")
        ev_end = ev_end_dt.strftime("%Y-%m-%d")
        event_name = rand_event_name()
        project = ProjectPage(page)
        project.add_phase_event(phase_name, event_name, ev_start, ev_end)
        phase = project.get_phase_card(phase_name)
        phase.expand(page)
        phase.events_section().locator(".item-list li", has_text=event_name) \
             .locator(ProjectPage.EDIT_EVENT_BTN).click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        new_ev_start = (ev_start_dt + timedelta(days=7)).strftime("%Y-%m-%d")
        new_ev_end = (ev_end_dt + timedelta(days=7)).strftime("%Y-%m-%d")
        page.locator(ProjectPage.MODAL_START).evaluate(
            f"el => {{ el.value = '{new_ev_start}'; el.dispatchEvent(new Event('change', {{ bubbles: true }})); }}"
        )
        expect(page.locator(ProjectPage.MODAL_END)).to_have_value(new_ev_end)
        page.keyboard.press("Escape")

    def test_edit_project_event(self, page: Page, make_project):
        """Project-level events have an Edit button."""
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        original_name = rand_event_name()
        renamed_name = rand_event_name()
        event_date = rand_future_date()
        project.add_project_event(original_name, event_date, event_date)
        ev_row = page.locator(ProjectPage.ITEMS_BODY + " li", has_text=original_name)
        ev_row.locator("button[title]").first.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(ProjectPage.MODAL_NAME)).to_have_value(original_name)
        page.locator(ProjectPage.MODAL_NAME).fill(renamed_name)
        page.locator(ProjectPage.MODAL_SUBMIT).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(page.locator(ProjectPage.ITEMS_BODY)).to_contain_text(renamed_name)
