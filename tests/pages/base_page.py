"""Base page object shared by all page objects."""
from playwright.sync_api import Page

BASE_URL = "http://localhost:8000"


class BasePage:
    def __init__(self, page: Page):
        self.page = page

    def goto(self, path: str = "/"):
        self.page.goto(BASE_URL + path)
        self.page.wait_for_load_state("networkidle")
