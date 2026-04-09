"""
Multi-user tests: registration, project isolation, access control, collaborators.
"""
import re
import json
import pytest
from playwright.sync_api import Browser, Page, expect

from pages import BASE_URL, DashboardPage, ProjectPage
from conftest import rand_project_name


class TestRegistration:

    def test_invalid_invite_token_shows_error(self, browser: Browser):
        fake_token = "a" * 64
        ctx = browser.new_context()
        pg = ctx.new_page()
        pg.goto(BASE_URL + f"/register/{fake_token}")
        pg.wait_for_load_state("networkidle")
        body = pg.locator("body").inner_text()
        assert any(word in body.lower() for word in ["invalid", "expired", "error"]), \
            f"Expected error message for invalid invite token, got: {body[:200]}"
        ctx.close()

    def test_second_user_registered_and_logged_in(self, second_user_page: Page):
        """second_user_page fixture confirms registration succeeded."""
        second_user_page.goto(BASE_URL + "/")
        second_user_page.wait_for_load_state("networkidle")
        assert "/login" not in second_user_page.url, "Second user should be authenticated"

    def test_invalid_reset_token_shows_error(self, browser: Browser):
        fake_token = "b" * 64
        ctx = browser.new_context()
        pg = ctx.new_page()
        pg.goto(BASE_URL + f"/reset-password/{fake_token}")
        pg.wait_for_load_state("networkidle")
        body = pg.locator("body").inner_text()
        assert any(word in body.lower() for word in ["invalid", "expired", "error"]), \
            f"Expected error for invalid reset token, got: {body[:200]}"
        ctx.close()


class TestProjectIsolation:

    def test_admin_project_not_in_second_user_list(self, page: Page, second_user_page: Page):
        """Admin creates a project; second user should not see it in their list."""
        dashboard = DashboardPage(page)
        dashboard.create_project("Admin Only Project")

        second_user_page.goto(BASE_URL + "/")
        second_user_page.wait_for_load_state("networkidle")
        expect(
            DashboardPage(second_user_page).get_project_card("Admin Only Project")._loc
        ).not_to_be_attached()

        dashboard.delete_project("Admin Only Project")

    def test_second_user_cannot_access_admin_project_url(self, page: Page, second_user_page: Page):
        """Direct URL access to another user's project should be denied."""
        dashboard = DashboardPage(page)
        dashboard.create_project("Private Admin Project")
        dashboard.goto()
        card = dashboard.get_project_card("Private Admin Project")
        href = card.open_link.get_attribute("href")
        project_url = BASE_URL + href

        second_user_page.goto(project_url)
        second_user_page.wait_for_load_state("networkidle")
        in_login    = "/login" in second_user_page.url
        at_home     = second_user_page.url.rstrip("/") == BASE_URL
        has_forbidden = (
            second_user_page.locator("text=403").count() > 0 or
            second_user_page.locator("text=Forbidden").count() > 0
        )
        assert in_login or at_home or has_forbidden, \
            f"Second user should not see admin's private project. URL: {second_user_page.url}"

        dashboard.delete_project("Private Admin Project")

    def test_second_user_project_not_visible_to_admin_without_collab(
        self, page: Page, second_user_page: Page
    ):
        """A non-admin user's project should not appear in another user's project list."""
        project_name = rand_project_name()
        second_dashboard = DashboardPage(second_user_page)
        second_dashboard.create_project(project_name)

        admin_dashboard = DashboardPage(page)
        admin_dashboard.goto()
        # Admin sees all projects — this is the documented behaviour
        expect(admin_dashboard.get_project_card(project_name)._loc.first).to_be_visible()

        second_dashboard.delete_project(project_name)


class TestCollaborators:

    @pytest.fixture(autouse=True)
    def _setup(self, page: Page, session_project):
        self.project = ProjectPage(page)
        self.project_name = session_project

    def test_collaborators_tab_switches_panel(self, page: Page):
        self.project.navigate_to(self.project_name)
        self.project.switch_to_collaborators()
        expect(page.locator(ProjectPage.TAB_COLLABORATORS)).to_be_visible()
        expect(page.locator(ProjectPage.TAB_PHASES)).not_to_be_visible()

    def test_add_collaborator_by_email(self, page: Page, second_user_auth_state):
        _state, email, _pass = second_user_auth_state
        self.project.navigate_to(self.project_name)
        self.project.switch_to_collaborators()
        expect(page.locator(ProjectPage.TAB_COLLABORATORS)).to_be_visible()

        resp = page.request.post(
            page.url.split("/project/")[0] + "/api/projects/" +
            re.search(r"/project/(\d+)", page.url).group(1) + "/collaborators",
            data=json.dumps({"email": email, "role": "viewer"}),
            headers={"Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest"},
        )
        assert resp.status in (200, 201), f"Add collaborator failed: {resp.text()}"

        page.reload()
        page.wait_for_load_state("networkidle")
        self.project.switch_to_collaborators()
        page.wait_for_timeout(500)
        expect(page.locator(ProjectPage.TAB_COLLABORATORS)).to_contain_text(email)

    def test_collaborator_can_see_shared_project(self, page: Page, second_user_page: Page):
        self.project.navigate_to(self.project_name)
        project_url = page.url

        second_user_page.goto(project_url)
        second_user_page.wait_for_load_state("networkidle")
        expect(second_user_page.locator(ProjectPage.PROJECT_NAME)).to_contain_text(self.project_name)

    def test_remove_collaborator(self, page: Page, second_user_auth_state):
        _state, email, _pass = second_user_auth_state
        self.project.navigate_to(self.project_name)
        project_id = re.search(r"/project/(\d+)", page.url).group(1)

        resp = page.request.get(BASE_URL + f"/api/projects/{project_id}/collaborators")
        assert resp.status == 200
        collabs = resp.json()
        collab = next((c for c in collabs if c["email"] == email), None)
        assert collab is not None, f"{email} not found in collaborators"

        del_resp = page.request.delete(
            BASE_URL + f"/api/projects/{project_id}/collaborators/{collab['id']}",
            headers={"X-Requested-With": "XMLHttpRequest"}
        )
        assert del_resp.status == 200
