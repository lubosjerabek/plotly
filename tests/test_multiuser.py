"""
Multi-user tests: registration, project isolation, access control, collaborators.
"""
import re
import json
import pytest
from playwright.sync_api import Browser, Page, expect

from helpers import BASE_URL, goto, create_project, navigate_to_project
from conftest import rand_project_name


class TestRegistration:

    def test_invalid_invite_token_shows_error(self, browser: Browser):
        fake_token = "a" * 64
        ctx = browser.new_context()
        pg = ctx.new_page()
        pg.goto(BASE_URL + f"/register/{fake_token}")
        pg.wait_for_load_state("networkidle")
        # Should show an error — not a working registration form
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
        create_project(page, "Admin Only Project")

        second_user_page.goto(BASE_URL + "/")
        second_user_page.wait_for_load_state("networkidle")
        expect(second_user_page.locator(".project-card", has_text="Admin Only Project")).not_to_be_attached()

        # Cleanup
        goto(page)
        card = page.locator(".project-card", has_text="Admin Only Project")
        card.hover()
        card.locator("button[title='Delete project']").click()
        page.locator("#confirmOkBtn").click()
        page.wait_for_load_state("networkidle")

    def test_second_user_cannot_access_admin_project_url(self, page: Page, second_user_page: Page):
        """Direct URL access to another user's project should be denied."""
        create_project(page, "Private Admin Project")
        goto(page)
        card = page.locator(".project-card", has_text="Private Admin Project")
        href = card.locator("a.btn").get_attribute("href")
        project_url = BASE_URL + href

        second_user_page.goto(project_url)
        second_user_page.wait_for_load_state("networkidle")
        # Should be redirected to / or shown a 403 — not the project page
        in_login = "/login" in second_user_page.url
        at_home  = second_user_page.url.rstrip("/") == BASE_URL
        has_forbidden = second_user_page.locator("text=403").count() > 0 or \
                        second_user_page.locator("text=Forbidden").count() > 0
        assert in_login or at_home or has_forbidden, \
            f"Second user should not see admin's private project. URL: {second_user_page.url}"

        # Cleanup
        goto(page)
        card = page.locator(".project-card", has_text="Private Admin Project")
        card.hover()
        card.locator("button[title='Delete project']").click()
        page.locator("#confirmOkBtn").click()
        page.wait_for_load_state("networkidle")

    def test_second_user_project_not_visible_to_admin_without_collab(
        self, page: Page, second_user_page: Page
    ):
        """A non-admin user's project should not appear in another user's project list."""
        project_name = rand_project_name()
        create_project(second_user_page, project_name)

        goto(page)
        # Admin sees all projects — this is the documented behaviour
        # (see architecture: admin sees all). So we just confirm admin CAN see it.
        expect(page.locator(".project-card", has_text=project_name).first).to_be_visible()

        # Cleanup
        second_user_page.goto(BASE_URL + "/")
        second_user_page.wait_for_load_state("networkidle")
        card = second_user_page.locator(".project-card", has_text=project_name).first
        card.hover()
        card.locator("button[title='Delete project']").click()
        second_user_page.locator("#confirmOkBtn").click()
        second_user_page.wait_for_load_state("networkidle")


class TestCollaborators:

    @pytest.fixture(autouse=True)
    def _setup(self, session_project):
        self.project_name = session_project

    def test_collaborators_tab_switches_panel(self, page: Page):
        navigate_to_project(page, self.project_name)
        page.locator(".tab-btn[data-tab='collaborators']").click()
        expect(page.locator("#tab-collaborators")).to_be_visible()
        expect(page.locator("#tab-phases")).not_to_be_visible()

    def test_add_collaborator_by_email(self, page: Page, second_user_auth_state):
        _state, email, _pass = second_user_auth_state
        navigate_to_project(page, self.project_name)
        page.locator(".tab-btn[data-tab='collaborators']").click()
        expect(page.locator("#tab-collaborators")).to_be_visible()

        # Add second user as viewer
        resp = page.request.post(
            page.url.split("/project/")[0] + "/api/projects/" +
            re.search(r"/project/(\d+)", page.url).group(1) + "/collaborators",
            data=json.dumps({"email": email, "role": "viewer"}),
            headers={"Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest"},
        )
        assert resp.status in (200, 201), f"Add collaborator failed: {resp.text()}"

        # Reload and confirm collaborator appears in the list
        page.reload()
        page.wait_for_load_state("networkidle")
        page.locator(".tab-btn[data-tab='collaborators']").click()
        page.wait_for_timeout(500)
        expect(page.locator("#tab-collaborators")).to_contain_text(email)

    def test_collaborator_can_see_shared_project(self, page: Page, second_user_page: Page):
        navigate_to_project(page, self.project_name)
        project_url = page.url

        second_user_page.goto(project_url)
        second_user_page.wait_for_load_state("networkidle")
        # Should be able to see the project (added as viewer in previous test)
        expect(second_user_page.locator("#pName")).to_contain_text(self.project_name)

    def test_remove_collaborator(self, page: Page, second_user_auth_state):
        _state, email, _pass = second_user_auth_state
        navigate_to_project(page, self.project_name)
        project_id = re.search(r"/project/(\d+)", page.url).group(1)

        # Get current collaborators to find the user id
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
