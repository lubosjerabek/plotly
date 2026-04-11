"""Login page object."""

from .base_page import BasePage


class LoginPage(BasePage):
    EMAIL    = "input[name='email']"
    PASSWORD = "input[name='password']"
    _SUBMIT  = "Sign In"  # used with get_by_role

    def goto(self):
        super().goto("/login")
        return self

    def login(self, email: str, password: str):
        self.page.fill(self.EMAIL,    email)
        self.page.fill(self.PASSWORD, password)
        self.page.get_by_role("button", name=self._SUBMIT).click()
        self.page.wait_for_load_state("networkidle")

    def is_on_login_page(self) -> bool:
        return "/login" in self.page.url
