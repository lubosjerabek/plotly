"""Shared helpers for Plotly Playwright tests."""
import re
from playwright.sync_api import Page, expect

BASE_URL = "http://localhost:8000"


def goto(page: Page, path: str = "/"):
    page.goto(BASE_URL + path)
    page.wait_for_load_state("networkidle")


def open_new_project_modal(page: Page):
    page.locator("#btnNewProject").click()
    expect(page.locator("#projectModal")).to_have_class(re.compile(r"is-open"))


def create_project(page: Page, name: str, desc: str = "") -> None:
    goto(page)
    open_new_project_modal(page)
    page.locator("#pm_name").fill(name)
    if desc:
        page.locator("#pm_desc").fill(desc)
    page.locator("#projectModalSubmit").click()
    expect(page.locator(".toast--success")).to_be_visible()
    page.wait_for_load_state("networkidle")


def navigate_to_project(page: Page, name: str):
    goto(page)
    card = page.locator(".project-card", has_text=name)
    expect(card).to_be_visible()
    card.locator("a.btn").click()
    page.wait_for_load_state("networkidle")
    expect(page.locator("#pName")).to_contain_text(name)


def expand_phase(page: Page, phase_name: str):
    """Expand a phase card if it is currently collapsed."""
    card = page.locator(".phase-card", has_text=phase_name)
    if "is-collapsed" in (card.get_attribute("class") or ""):
        card.locator(".phase-card__toggle-area").click()
        page.wait_for_timeout(300)
