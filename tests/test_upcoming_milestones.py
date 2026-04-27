"""
Upcoming Milestones dashboard panel tests.

Each test uses project-level milestones (POST /api/projects/{id}/milestones) so
only a project ID is required — no phase setup needed.
"""
import json
from datetime import date, timedelta

from conftest import rand_milestone_name
from pages import BASE_URL, DashboardPage
from playwright.sync_api import Page, expect


# ── Helpers ───────────────────────────────────────────────────────────────────

def _today() -> str:
    return date.today().strftime("%Y-%m-%d")


def _days(n: int) -> str:
    return (date.today() + timedelta(days=n)).strftime("%Y-%m-%d")


def _post_milestone(page: Page, project_id: int, name: str, target: str) -> int:
    resp = page.request.post(
        BASE_URL + f"/api/projects/{project_id}/milestones",
        data=json.dumps({"name": name, "target_date": target}),
        headers={"Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest"},
    )
    assert resp.status == 201, f"Milestone creation failed: {resp.text()}"
    return resp.json()["id"]


# ── Tests ─────────────────────────────────────────────────────────────────────

class TestUpcomingMilestonesPanel:

    def test_future_milestone_appears_with_name_and_project(self, page: Page, make_project):
        ref = make_project()
        ms_name = rand_milestone_name()
        _post_milestone(page, ref.id, ms_name, _days(30))
        DashboardPage(page).goto()
        expect(page.locator(DashboardPage.UPCOMING_PANEL)).to_be_visible()
        expect(page.locator(DashboardPage.UPCOMING_LIST)).to_contain_text(ms_name)
        expect(page.locator(DashboardPage.UPCOMING_LIST)).to_contain_text(str(ref))

    def test_today_milestone_shows_green_dot_and_today_label(self, page: Page, make_project):
        ref = make_project()
        _post_milestone(page, ref.id, rand_milestone_name(), _today())
        DashboardPage(page).goto()
        expect(page.locator(DashboardPage.UPCOMING_DOT_TODAY).first).to_be_visible()
        expect(page.locator(DashboardPage.UPCOMING_LIST)).to_contain_text("Today")

    def test_overdue_milestone_has_red_dot_and_overdue_label(self, page: Page, make_project):
        ref = make_project()
        _post_milestone(page, ref.id, rand_milestone_name(), _days(-3))
        DashboardPage(page).goto()
        expect(page.locator(DashboardPage.UPCOMING_DOT_DANGER).first).to_be_visible()
        expect(page.locator(DashboardPage.UPCOMING_DATE_DANGER).first).to_contain_text("overdue")

    def test_milestone_older_than_7_days_not_shown(self, page: Page, make_project):
        ref = make_project()
        ms_name = rand_milestone_name()
        _post_milestone(page, ref.id, ms_name, _days(-8))
        DashboardPage(page).goto()
        expect(page.locator(DashboardPage.UPCOMING_LIST)).not_to_contain_text(ms_name)

    def test_non_collaborator_cannot_see_milestone(self, page: Page, second_user_page, make_project):
        ref = make_project()
        ms_name = rand_milestone_name()
        _post_milestone(page, ref.id, ms_name, _days(30))
        DashboardPage(second_user_page).goto()
        expect(second_user_page.locator(DashboardPage.UPCOMING_LIST)).not_to_contain_text(ms_name)
