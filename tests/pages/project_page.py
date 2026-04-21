"""Project detail page object, PhaseCard component."""
import re

from playwright.sync_api import Locator, Page, expect

from .base_page import BasePage


class PhaseCard:
    """Wraps a single .phase-card locator."""

    TOGGLE          = ".phase-card__toggle-area"
    BADGE_UPCOMING  = ".badge-upcoming"
    DESCRIPTION     = ".phase-description"
    EDIT_BTN        = "button[title='Edit phase']"
    DELETE_BTN      = "button[title='Delete phase']"
    SECTION         = ".phase-section"
    ITEM_LIST       = ".item-list"

    def __init__(self, locator: Locator):
        self._loc = locator

    def is_collapsed(self) -> bool:
        return "is-collapsed" in (self._loc.get_attribute("class") or "")

    def expand(self, page: Page):
        if self.is_collapsed():
            self._loc.locator(self.TOGGLE).click()
            page.wait_for_timeout(300)

    def collapse(self, page: Page):
        if not self.is_collapsed():
            self._loc.locator(self.TOGGLE).click()
            page.wait_for_timeout(300)

    def toggle(self):
        self._loc.locator(self.TOGGLE).click()

    def milestones_section(self) -> Locator:
        return self._loc.locator(self.SECTION).first

    def events_section(self) -> Locator:
        return self._loc.locator(self.SECTION).last

    @property
    def edit_btn(self) -> Locator:
        return self._loc.locator(self.EDIT_BTN)

    @property
    def delete_btn(self) -> Locator:
        return self._loc.locator(self.DELETE_BTN)

    @property
    def badge_upcoming(self) -> Locator:
        return self._loc.locator(self.BADGE_UPCOMING)

    @property
    def description_el(self) -> Locator:
        return self._loc.locator(self.DESCRIPTION)


class ProjectPage(BasePage):
    # Header
    PROJECT_NAME = "#pName"
    TOPBAR_TITLE = "#topbarTitle"
    BACK_LINK    = "a.back-link"

    # Tab buttons
    PHASES_TAB       = ".tab-btn[data-tab='phases']"
    COLLABORATORS_TAB = ".tab-btn[data-tab='collaborators']"

    # Tab panels
    TAB_PHASES        = "#tab-phases"
    TAB_COLLABORATORS = "#tab-collaborators"
    TAB_TIMELINE      = "#tab-timeline"

    # Phase cards
    PHASE_CARD       = ".phase-card"
    ADD_PHASE_BTN    = "Add Phase"  # used with has_text
    ITEM_LIST        = ".item-list"

    # Generic modal (phases / milestones / events)
    GENERIC_MODAL = "#genericModal"
    MODAL_TITLE   = "#modalTitle"
    MODAL_NAME    = "#modal_input_name"
    MODAL_DESC    = "#modal_input_desc"
    MODAL_START   = "#modal_input_start"
    MODAL_END     = "#modal_input_end"
    MODAL_TARGET  = "#modal_input_target"
    MODAL_SUBMIT  = "#modalSubmitBtn"

    # Edit-project modal (uses the same generic modal)
    EDIT_PROJECT_BTN = "Edit"  # has_text, first button

    # Confirm modal
    CONFIRM_MODAL = "#confirmModal"
    CONFIRM_OK    = "#confirmOkBtn"

    # Success toast
    TOAST_SUCCESS = ".toast--success"

    # ICS subscription
    SUBSCRIBE_BTN   = "#subscribeBtn"
    SUBSCRIBE_MODAL = "#subscribeModal"
    ICS_URL         = "#icsUrl"

    # Edit / Delete buttons (used inside item rows)
    EDIT_MILESTONE_BTN   = "button[title='Edit milestone']"
    EDIT_EVENT_BTN       = "button[title='Edit event']"
    DELETE_MILESTONE_BTN = "button[title='Delete milestone']"
    DELETE_EVENT_BTN     = "button[title='Delete event']"

    # Project-level milestones and events
    ADD_MILESTONES_BTN = "+ Milestones"  # has_text
    ADD_EVENTS_BTN     = "+ Events"      # has_text
    ALL_DAY         = "#modal_input_all_day"
    TIME_FIELD      = ".event-time-field"
    START_TIME      = "#modal_input_start_time"
    END_TIME        = "#modal_input_end_time"
    ITEMS_BODY      = "#projectItemsBody"

    # Timeline / Gantt
    GANTT_BARS    = ".gantt .bar"
    DATE_LABELS   = ".gantt .gantt-date-label"
    GANTT_VIEW_BTNS = "#ganttViewBtns"
    TIMELINE_EMPTY  = "#tab-timeline .item-empty"
    GANTT_ERROR     = ".gantt-container .item-empty"
    BAR_WRAPPER     = ".bar-wrapper"

    # ── Navigation ─────────────────────────────────────────────────────────────

    def navigate_to(self, name: str):
        """Navigate to the dashboard and open the named project."""
        from .dashboard_page import DashboardPage
        DashboardPage(self.page).navigate_to_project(name)
        expect(self.page.locator(self.PROJECT_NAME)).to_contain_text(name)

    def navigate_by_id(self, project_id: int) -> None:
        """Navigate directly to a project by ID, bypassing the dashboard."""
        from .base_page import BASE_URL
        self.page.goto(BASE_URL + f"/project/{project_id}")
        self.page.wait_for_load_state("networkidle")

    # ── Tabs ───────────────────────────────────────────────────────────────────

    def switch_to_timeline(self):
        self.page.locator(".tab-btn", has_text="Timeline").click()
        expect(self.page.locator(self.TAB_TIMELINE)).to_be_visible()

    def switch_to_collaborators(self):
        self.page.locator(self.COLLABORATORS_TAB).click()

    def switch_to_phases(self):
        self.page.locator(self.PHASES_TAB).click()

    # ── Phase operations ───────────────────────────────────────────────────────

    def add_phase(
        self,
        name:  str,
        start: str = "2027-01-01",
        end:   str = "2027-06-30",
        desc:  str = "",
    ) -> str:
        self.page.locator("button", has_text=self.ADD_PHASE_BTN).click()
        expect(self.page.locator(self.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.MODAL_NAME).fill(name)
        if desc:
            self.page.locator(self.MODAL_DESC).fill(desc)
        self.page.locator(self.MODAL_START).fill(start)
        self.page.locator(self.MODAL_END).fill(end)
        self.page.locator(self.MODAL_SUBMIT).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")
        return name

    def get_phase_card(self, name: str) -> PhaseCard:
        return PhaseCard(self.page.locator(self.PHASE_CARD, has_text=name))

    def delete_phase(self, phase_name: str) -> None:
        self.get_phase_card(phase_name).delete_btn.click()
        expect(self.page.locator(self.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.CONFIRM_OK).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")

    # ── Milestone operations ───────────────────────────────────────────────────

    def add_milestone(
        self,
        phase_name: str,
        name:       str,
        target:     str = "2027-03-01",
    ) -> str:
        phase = self.get_phase_card(phase_name)
        phase.expand(self.page)
        phase.milestones_section().locator("button", has_text="Add").click()
        expect(self.page.locator(self.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.MODAL_NAME).fill(name)
        self.page.locator(self.MODAL_TARGET).fill(target)
        self.page.locator(self.MODAL_SUBMIT).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")
        return name

    def edit_milestone(self, phase_name: str, old_name: str, new_name: str, new_target: str | None = None) -> None:
        phase = self.get_phase_card(phase_name)
        phase.expand(self.page)
        phase.milestones_section() \
             .locator(".item-list li", has_text=old_name) \
             .locator(self.EDIT_MILESTONE_BTN).click()
        expect(self.page.locator(self.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.MODAL_NAME).fill(new_name)
        if new_target:
            self.page.locator(self.MODAL_TARGET).fill(new_target)
        self.page.locator(self.MODAL_SUBMIT).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")

    def delete_milestone(self, phase_name: str, name: str) -> None:
        phase = self.get_phase_card(phase_name)
        phase.expand(self.page)
        phase.milestones_section() \
             .locator(".item-list li", has_text=name) \
             .locator(self.DELETE_MILESTONE_BTN).click()
        expect(self.page.locator(self.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.CONFIRM_OK).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")

    # ── Phase-level event operations ───────────────────────────────────────────

    def add_phase_event(
        self,
        phase_name: str,
        name:       str,
        start:      str,
        end:        str,
        all_day:    bool        = True,
        start_time: str | None  = None,
        end_time:   str | None  = None,
    ) -> str:
        phase = self.get_phase_card(phase_name)
        phase.expand(self.page)
        phase.events_section().locator("button", has_text="Add").click()
        expect(self.page.locator(self.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.MODAL_NAME).fill(name)
        self.page.locator(self.MODAL_START).fill(start)
        self.page.locator(self.MODAL_END).fill(end)
        if not all_day:
            self.page.locator(self.ALL_DAY).uncheck()
            if start_time:
                self.page.locator(self.START_TIME).fill(start_time)
            if end_time:
                self.page.locator(self.END_TIME).fill(end_time)
        self.page.locator(self.MODAL_SUBMIT).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")
        return name

    def delete_phase_event(self, phase_name: str, name: str) -> None:
        phase = self.get_phase_card(phase_name)
        phase.expand(self.page)
        phase.events_section() \
             .locator(".item-list li", has_text=name) \
             .locator(self.DELETE_EVENT_BTN).click()
        expect(self.page.locator(self.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.CONFIRM_OK).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")

    # ── Project-level milestone operations ────────────────────────────────────

    def add_project_milestone(self, name: str, target: str) -> str:
        self.page.locator("button", has_text=self.ADD_MILESTONES_BTN).click()
        expect(self.page.locator(self.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.MODAL_NAME).fill(name)
        self.page.locator(self.MODAL_TARGET).fill(target)
        self.page.locator(self.MODAL_SUBMIT).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")
        return name

    # ── Project-level event operations ─────────────────────────────────────────

    def add_project_event(
        self,
        name:       str,
        start:      str,
        end:        str,
        all_day:    bool         = True,
        start_time: str | None   = None,
        end_time:   str | None   = None,
    ) -> str:
        self.page.locator("button", has_text=self.ADD_EVENTS_BTN).click()
        expect(self.page.locator(self.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.MODAL_NAME).fill(name)
        self.page.locator(self.MODAL_START).fill(start)
        self.page.locator(self.MODAL_END).fill(end)
        if not all_day:
            self.page.locator(self.ALL_DAY).uncheck()
            if start_time:
                self.page.locator(self.START_TIME).fill(start_time)
            if end_time:
                self.page.locator(self.END_TIME).fill(end_time)
        self.page.locator(self.MODAL_SUBMIT).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")
        return name

    def delete_project_event(self, name: str) -> None:
        self.page.locator(self.ITEMS_BODY + " li", has_text=name) \
                 .locator(self.DELETE_EVENT_BTN).click()
        expect(self.page.locator(self.CONFIRM_MODAL)).to_have_class(re.compile(r"is-open"))
        self.page.locator(self.CONFIRM_OK).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")

    # ── ICS / subscription ─────────────────────────────────────────────────────

    def open_subscribe_modal(self):
        self.page.locator(self.SUBSCRIBE_BTN).click()
        expect(self.page.locator(self.SUBSCRIBE_MODAL)).to_have_class(re.compile(r"is-open"))

    def get_ics_url(self) -> str:
        return self.page.locator(self.ICS_URL).input_value()

    def fetch_ics(self) -> str:
        """Open the subscribe modal, grab the ICS URL, close the modal, return feed body."""
        self.open_subscribe_modal()
        url = self.get_ics_url()
        self.page.keyboard.press("Escape")
        resp = self.page.request.get(url)
        assert resp.status == 200, f"ICS feed returned {resp.status}"
        return resp.text()

    # ── Timeline / Gantt ───────────────────────────────────────────────────────

    def wait_for_gantt_bars(self, timeout: int = 8000):
        self.page.wait_for_selector(self.GANTT_BARS, timeout=timeout)

    def switch_gantt_view(self, view: str):
        self.page.locator(self.GANTT_VIEW_BTNS + " button", has_text=view).click()
