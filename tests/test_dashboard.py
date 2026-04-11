"""
Dashboard tests: project list, create, edit, delete, modals.
"""
import re
import pytest
from playwright.sync_api import Page, expect

from pages import DashboardPage
from conftest import fake


class TestDashboard:

    def test_page_loads_with_correct_title(self, page: Page):
        dashboard = DashboardPage(page)
        dashboard.goto()
        expect(page).to_have_title(re.compile(r"Plotly"))
        expect(page.locator(DashboardPage.HEADING)).to_contain_text("Projects")

    def test_topbar_brand_visible(self, page: Page):
        dashboard = DashboardPage(page)
        dashboard.goto()
        expect(page.locator(DashboardPage.BRAND)).to_contain_text("Plotly")

    def test_new_project_button_opens_modal(self, page: Page):
        dashboard = DashboardPage(page)
        dashboard.goto()
        dashboard.open_new_project_modal()
        expect(page.locator(DashboardPage.PROJECT_MODAL)).to_have_class(re.compile(r"is-open"))

    def test_esc_closes_new_project_modal(self, page: Page):
        dashboard = DashboardPage(page)
        dashboard.goto()
        dashboard.open_new_project_modal()
        page.keyboard.press("Escape")
        expect(page.locator(DashboardPage.PROJECT_MODAL)).not_to_have_class(re.compile(r"is-open"))

    def test_create_project_shows_toast_and_card(self, page: Page):
        from pages import BASE_URL
        from conftest import rand_project_name
        name = rand_project_name()
        dashboard = DashboardPage(page)
        dashboard.create_project(name)
        expect(page.locator(DashboardPage.TOAST_SUCCESS).last).to_be_visible()
        expect(dashboard.get_project_card(name)._loc).to_be_visible()
        # Cleanup via API
        resp = page.request.get(BASE_URL + "/api/projects", headers={"X-Requested-With": "XMLHttpRequest"})
        if resp.status == 200:
            for p in resp.json():
                if p["name"] == name:
                    page.request.delete(BASE_URL + f"/api/projects/{p['id']}", headers={"X-Requested-With": "XMLHttpRequest"})
                    break

    def test_project_card_shows_description(self, page: Page, make_project):
        desc = fake.sentence(nb_words=4)
        ref = make_project(desc=desc)
        dashboard = DashboardPage(page)
        dashboard.goto()
        card = dashboard.get_project_card(ref)
        expect(card.description.last).to_contain_text(desc)

    def test_project_card_has_open_link(self, page: Page, make_project):
        ref = make_project()
        dashboard = DashboardPage(page)
        dashboard.goto()
        card = dashboard.get_project_card(ref)
        card.hover()
        expect(card.open_link).to_contain_text("Open")
        href = card.open_link.get_attribute("href")
        assert href and re.match(r"/project/\d+", href), f"Unexpected href: {href}"

    def test_edit_project_updates_name(self, page: Page, make_project):
        ref = make_project()
        dashboard = DashboardPage(page)
        dashboard.goto()
        card = dashboard.get_project_card(ref)
        card.edit()
        expect(page.locator(DashboardPage.PROJECT_MODAL)).to_have_class(re.compile(r"is-open"))
        expect(page.locator(DashboardPage.PM_NAME)).to_have_value(ref)

        edited_name = str(ref) + " (edited)"
        page.locator(DashboardPage.PM_NAME).fill(edited_name)
        page.locator(DashboardPage.PM_SUBMIT).click()
        expect(page.locator(DashboardPage.TOAST_SUCCESS).last).to_be_visible()
        expect(dashboard.get_project_card(edited_name)._loc).to_be_visible()

    def test_delete_project_via_confirmation_modal(self, page: Page, make_project):
        ref = make_project()
        dashboard = DashboardPage(page)
        dashboard.goto()
        card = dashboard.get_project_card(ref)
        card.delete(page.locator(DashboardPage.CONFIRM_OK))
        expect(page.locator(DashboardPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(dashboard.get_project_card(ref)._loc).not_to_be_attached()
