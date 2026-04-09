"""Admin page object."""
from playwright.sync_api import Locator

from .base_page import BasePage


class AdminPage(BasePage):
    ADMIN_NAV   = "a[href='/admin/users']"
    INVITES_TAB = "Invites"  # used with has_text on .tab-btn

    def goto(self):
        super().goto("/admin/users")
        return self

    @property
    def admin_nav_link(self) -> Locator:
        return self.page.locator(self.ADMIN_NAV)

    @property
    def invites_tab(self) -> Locator:
        return self.page.locator(".tab-btn", has_text=self.INVITES_TAB)
