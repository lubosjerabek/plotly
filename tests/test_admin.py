"""
Admin panel tests: access control, invite generation, user listing, password reset links.
"""
import re
import pytest
from playwright.sync_api import Page, expect

from helpers import BASE_URL, goto


class TestAdminAccess:

    def test_admin_nav_link_visible_for_admin(self, page: Page):
        goto(page)
        expect(page.locator("a[href='/admin/users']")).to_be_visible()

    def test_admin_panel_loads_for_admin(self, page: Page):
        page.goto(BASE_URL + "/admin/users")
        page.wait_for_load_state("networkidle")
        expect(page).to_have_url(re.compile(r"/admin/users"))

    def test_admin_panel_blocked_for_non_admin(self, second_user_page: Page):
        second_user_page.goto(BASE_URL + "/admin/users")
        second_user_page.wait_for_load_state("networkidle")
        # Should get a 403 or be redirected away from admin/users
        assert "/admin/users" not in second_user_page.url or \
               second_user_page.locator("text=403").count() > 0 or \
               second_user_page.locator("text=Forbidden").count() > 0, \
               "Non-admin user should not access /admin/users"

    def test_admin_nav_link_not_visible_for_non_admin(self, second_user_page: Page):
        second_user_page.goto(BASE_URL + "/")
        second_user_page.wait_for_load_state("networkidle")
        expect(second_user_page.locator("a[href='/admin/users']")).not_to_be_visible()


class TestAdminInvites:

    def test_invites_tab_visible(self, page: Page):
        page.goto(BASE_URL + "/admin/users")
        page.wait_for_load_state("networkidle")
        expect(page.locator(".tab-btn", has_text="Invites")).to_be_visible()

    def test_generate_invite_via_api_returns_url(self, page: Page):
        resp = page.request.post(
            BASE_URL + "/api/admin/invites",
            data='{"label": "Test Invite", "expires_days": 7}',
            headers={"Content-Type": "application/json"},
        )
        assert resp.status == 201
        data = resp.json()
        assert "token" in data
        assert "url" in data
        assert "/register/" in data["url"]
        assert len(data["token"]) == 64

    def test_revoke_invite_via_api(self, page: Page):
        # Create then immediately revoke
        resp = page.request.post(
            BASE_URL + "/api/admin/invites",
            data='{"label": "To Revoke", "expires_days": 1}',
            headers={"Content-Type": "application/json"},
        )
        invite_id = resp.json()["id"]
        revoke = page.request.delete(BASE_URL + f"/api/admin/invites/{invite_id}")
        assert revoke.status == 200


class TestAdminPasswordReset:

    def test_generate_reset_link_returns_url(self, page: Page, second_user_auth_state):
        _state, email, _pass = second_user_auth_state
        # Find the second user's ID via the users API
        resp = page.request.get(BASE_URL + "/api/admin/users")
        assert resp.status == 200
        users = resp.json()
        user = next((u for u in users if u["email"] == email), None)
        assert user is not None, f"Second user {email} not found in admin user list"

        reset = page.request.post(
            BASE_URL + f"/api/admin/users/{user['id']}/reset-password",
            data="{}",
            headers={"Content-Type": "application/json"},
        )
        assert reset.status == 201
        data = reset.json()
        assert "url" in data
        assert "/reset-password/" in data["url"]
