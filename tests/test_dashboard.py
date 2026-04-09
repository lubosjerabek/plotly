"""
Dashboard tests: project list, create, edit, delete, modals.
"""
import re
import pytest
from playwright.sync_api import Page, expect

from helpers import BASE_URL, goto, open_new_project_modal, create_project


class TestDashboard:

    def test_page_loads_with_correct_title(self, page: Page):
        goto(page)
        expect(page).to_have_title(re.compile(r"Plotly"))
        expect(page.locator("h1")).to_contain_text("Projects")

    def test_topbar_brand_visible(self, page: Page):
        goto(page)
        expect(page.locator(".topbar__brand")).to_contain_text("Plotly")

    def test_new_project_button_opens_modal(self, page: Page):
        goto(page)
        page.locator("#btnNewProject").click()
        expect(page.locator("#projectModal")).to_have_class(re.compile(r"is-open"))

    def test_esc_closes_new_project_modal(self, page: Page):
        goto(page)
        open_new_project_modal(page)
        page.keyboard.press("Escape")
        expect(page.locator("#projectModal")).not_to_have_class(re.compile(r"is-open"))

    def test_create_project_shows_toast_and_card(self, page: Page, make_project):
        name = make_project()
        expect(page.locator(".project-card", has_text=name)).to_be_visible()

    def test_project_card_shows_description(self, page: Page, make_project):
        name = make_project(desc="Automated test")
        card = page.locator(".project-card", has_text=name).last
        expect(card.locator(".project-card__desc")).to_contain_text("Automated test")

    def test_project_card_has_open_link(self, page: Page, make_project):
        name = make_project()
        card = page.locator(".project-card", has_text=name).last
        card.hover()
        link = card.locator("a.btn")
        expect(link).to_contain_text("Open")
        href = link.get_attribute("href")
        assert href and re.match(r"/project/\d+", href), f"Unexpected href: {href}"

    def test_edit_project_updates_name(self, page: Page, make_project):
        name = make_project()
        card = page.locator(".project-card", has_text=name).last
        card.hover()
        card.locator("button[title='Edit project']").click()
        expect(page.locator("#projectModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#pm_name")).to_have_value(name)

        page.locator("#pm_name").fill(name + " (edited)")
        page.locator("#projectModalSubmit").click()
        expect(page.locator(".toast--success")).to_be_visible()
        expect(page.locator(".project-card", has_text=name + " (edited)")).to_be_visible()

    def test_delete_project_via_confirmation_modal(self, page: Page):
        # Create a throwaway project then delete it
        create_project(page, "Delete Me Project")
        goto(page)
        card = page.locator(".project-card", has_text="Delete Me Project")
        card.hover()
        card.locator("button[title='Delete project']").click()
        expect(page.locator("#confirmModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#confirmOkBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(page.locator(".project-card", has_text="Delete Me Project")).not_to_be_attached()
