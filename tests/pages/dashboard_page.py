"""Dashboard page object and ProjectCard component."""
import re

from playwright.sync_api import Locator, expect

from .base_page import BasePage


class ProjectCard:
    """Wraps a single .project-card locator."""

    OPEN_LINK  = "a.btn"
    EDIT_BTN   = "button[title='Edit project']"
    DELETE_BTN = "button[title='Delete project']"
    DESC       = ".project-card__desc"

    def __init__(self, locator: Locator):
        self._loc = locator

    def hover(self):
        self._loc.hover()

    @property
    def open_link(self) -> Locator:
        return self._loc.locator(self.OPEN_LINK)

    @property
    def edit_btn(self) -> Locator:
        return self._loc.locator(self.EDIT_BTN)

    @property
    def delete_btn(self) -> Locator:
        return self._loc.locator(self.DELETE_BTN)

    @property
    def description(self) -> Locator:
        return self._loc.locator(self.DESC)

    def open(self):
        self.hover()
        self.open_link.click()

    def edit(self):
        self.hover()
        self.edit_btn.click()

    def delete(self, confirm_btn: Locator):
        self.hover()
        self.delete_btn.click()
        confirm_btn.click()


class DashboardPage(BasePage):
    # Project modal (create / edit from dashboard)
    NEW_PROJECT_BTN = "#btnNewProject"
    PROJECT_MODAL   = "#projectModal"
    PM_NAME         = "#pm_name"
    PM_DESC         = "#pm_desc"
    PM_SUBMIT       = "#projectModalSubmit"

    # Project cards
    PROJECT_CARD = ".project-card"

    # Shared UI
    TOAST_SUCCESS = ".toast--success"
    CONFIRM_MODAL = "#confirmModal"
    CONFIRM_OK    = "#confirmOkBtn"

    # Page chrome
    BRAND   = ".topbar__brand"
    HEADING = "h1"

    # Admin nav link (inside user menu dropdown)
    ADMIN_NAV        = "a[href='/admin/users']"
    USER_MENU_TRIGGER = ".user-menu__trigger"

    def goto(self):
        super().goto("/")
        # Wait for the new-project button to be interactive, not just networkidle.
        # Under server load the JS-rendered button can still be absent when
        # networkidle fires, causing click() timeouts in long test runs.
        expect(self.page.locator(self.NEW_PROJECT_BTN)).to_be_visible()
        return self

    # ── User menu ──────────────────────────────────────────────────────────────

    def open_user_menu(self):
        self.page.locator(self.USER_MENU_TRIGGER).click()

    # ── Project modal ──────────────────────────────────────────────────────────

    def open_new_project_modal(self):
        self.page.locator(self.NEW_PROJECT_BTN).click()
        expect(self.page.locator(self.PROJECT_MODAL)).to_have_class(re.compile(r"is-open"))

    def create_project(self, name: str, desc: str = "") -> None:
        self.goto()
        self.open_new_project_modal()
        self.page.locator(self.PM_NAME).fill(name)
        if desc:
            self.page.locator(self.PM_DESC).fill(desc)
        self.page.locator(self.PM_SUBMIT).click()
        expect(self.page.locator(self.TOAST_SUCCESS).last).to_be_visible()
        self.page.wait_for_load_state("networkidle")

    # ── Project cards ──────────────────────────────────────────────────────────

    def get_project_card(self, name: str) -> ProjectCard:
        return ProjectCard(self.page.locator(self.PROJECT_CARD, has_text=name))

    def navigate_to_project(self, name: str):
        """Go to the dashboard, find the project card, and open the project."""
        self.goto()
        card = self.get_project_card(name)
        expect(card._loc.last).to_be_visible()
        card._loc.last.locator(ProjectCard.OPEN_LINK).click()
        self.page.wait_for_load_state("networkidle")

    def delete_project(self, name: str):
        """Go to the dashboard, find the project card, and delete it."""
        self.goto()
        card = self.get_project_card(name)
        if card._loc.count() == 0:
            return
        card.delete(self.page.locator(self.CONFIRM_OK))
        self.page.wait_for_load_state("networkidle")
