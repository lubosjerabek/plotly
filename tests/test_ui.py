"""
Playwright UI tests for Plotly — full CRUD + UX feature coverage.
Run:  pytest tests/test_ui.py -v
      (server auto-starts via conftest.py if not already running)
"""
import re
import pytest
from playwright.sync_api import Page, expect

BASE_URL = "http://localhost:8000"

# ── Helpers ────────────────────────────────────────────────────

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


def navigate_to_test_project(page: Page, name: str):
    goto(page)
    card = page.locator(".project-card", has_text=name)
    expect(card).to_be_visible()
    card.locator("a.btn").click()
    page.wait_for_load_state("networkidle")
    expect(page.locator("#pName")).to_contain_text(name)


# ── Test: Dashboard ────────────────────────────────────────────

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

    def test_create_project_shows_toast_and_card(self, page: Page):
        create_project(page, "Playwright Test Project", "Automated test")
        expect(page.locator(".project-card", has_text="Playwright Test Project")).to_be_visible()

    def test_project_card_shows_description(self, page: Page):
        goto(page)
        card = page.locator(".project-card", has_text="Playwright Test Project")
        expect(card.locator(".project-card__desc")).to_contain_text("Automated test")

    def test_project_card_has_open_link(self, page: Page):
        goto(page)
        card = page.locator(".project-card", has_text="Playwright Test Project")
        link = card.locator("a.btn")
        expect(link).to_contain_text("Open")
        href = link.get_attribute("href")
        assert href and re.match(r"/project/\d+", href), f"Unexpected href: {href}"

    def test_edit_project_updates_name(self, page: Page):
        goto(page)
        card = page.locator(".project-card", has_text="Playwright Test Project")
        # hover to reveal action buttons
        card.hover()
        card.locator("button[title='Edit project']").click()
        expect(page.locator("#projectModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#pm_name")).to_have_value("Playwright Test Project")

        page.locator("#pm_name").fill("Playwright Test Project (edited)")
        page.locator("#projectModalSubmit").click()
        expect(page.locator(".toast--success")).to_be_visible()
        expect(page.locator(".project-card", has_text="Playwright Test Project (edited)")).to_be_visible()

        # Restore original name for later tests
        goto(page)
        page.locator(".project-card", has_text="Playwright Test Project (edited)").hover()
        page.locator(".project-card", has_text="Playwright Test Project (edited)").locator("button[title='Edit project']").click()
        page.locator("#pm_name").fill("Playwright Test Project")
        page.locator("#projectModalSubmit").click()
        expect(page.locator(".toast--success")).to_be_visible()


# ── Test: Project Detail ───────────────────────────────────────

class TestProjectDetail:

    def test_navigate_to_project(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        expect(page).to_have_url(re.compile(r"/project/\d+"))

    def test_project_header_shows_name(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        expect(page.locator("#pName")).to_contain_text("Playwright Test Project")

    def test_topbar_shows_project_name(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        expect(page.locator("#topbarTitle")).to_contain_text("Playwright Test Project")

    def test_tabs_are_visible(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        expect(page.locator(".tab-btn", has_text="Phases")).to_be_visible()
        expect(page.locator(".tab-btn", has_text="Timeline")).to_be_visible()
        # Phases tab active by default
        expect(page.locator(".tab-btn[data-tab='phases']")).to_have_class(re.compile(r"active"))

    def test_add_phase_opens_modal(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        page.locator("button", has_text="Add Phase").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modalTitle")).to_contain_text("Add Phase")

    def test_add_phase_creates_card_with_status_badge(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        page.locator("button", has_text="Add Phase").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))

        page.locator("#modal_input_name").fill("Alpha Phase")
        # Future date → Upcoming badge
        page.locator("#modal_input_start").fill("2027-01-01")
        page.locator("#modal_input_end").fill("2027-03-31")
        page.locator("#modalSubmitBtn").click()

        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")

        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        expect(phase_card).to_be_visible()
        expect(phase_card.locator(".badge-upcoming")).to_be_visible()

    def test_add_phase_with_custom_color(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        page.locator("button", has_text="Add Phase").click()
        page.locator("#modal_input_name").fill("Colored Phase")
        page.locator("#modal_input_start").fill("2027-04-01")
        page.locator("#modal_input_end").fill("2027-06-30")
        # Set a custom color
        page.locator("#modal_input_color").evaluate("el => el.value = '#22c55e'")
        page.locator("#modal_input_color").dispatch_event("input")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(page.locator(".phase-card", has_text="Colored Phase")).to_be_visible()

    def test_esc_closes_generic_modal(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        page.locator("button", has_text="Add Phase").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        page.keyboard.press("Escape")
        expect(page.locator("#genericModal")).not_to_have_class(re.compile(r"is-open"))

    def test_edit_phase_prepopulates_modal(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        phase_card.locator("button[title='Edit phase']").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modal_input_name")).to_have_value("Alpha Phase")

        page.locator("#modal_input_name").fill("Alpha Phase — Updated")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(page.locator(".phase-card", has_text="Alpha Phase — Updated")).to_be_visible()

    def test_add_milestone_to_phase(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        # First "+ Add" button = milestones
        phase_card.locator(".phase-section").first.locator("button", has_text="+ Add").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modalTitle")).to_contain_text("Milestone")

        page.locator("#modal_input_name").fill("Kickoff Meeting")
        page.locator("#modal_input_target").fill("2027-01-15")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")

        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        ms_list = phase_card.locator(".phase-section").first.locator(".item-list")
        expect(ms_list).to_contain_text("Kickoff Meeting")

    def test_add_event_to_phase(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        # Second ".phase-section" = events
        phase_card.locator(".phase-section").last.locator("button", has_text="+ Add").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modalTitle")).to_contain_text("Event")

        page.locator("#modal_input_name").fill("Sprint Demo")
        page.locator("#modal_input_start").fill("2027-01-20")
        page.locator("#modal_input_end").fill("2027-01-20")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")

        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        ev_list = phase_card.locator(".phase-section").last.locator(".item-list")
        expect(ev_list).to_contain_text("Sprint Demo")

    def test_timeline_tab_renders_gantt(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        page.locator(".tab-btn", has_text="Timeline").click()
        expect(page.locator("#tab-timeline")).to_be_visible()
        expect(page.locator("#tab-phases")).not_to_be_visible()
        # Gantt should have rendered SVG bars
        page.wait_for_selector(".gantt .bar", timeout=5000)
        bars = page.locator(".gantt .bar")
        expect(bars).to_have_count(bars.count())
        assert bars.count() >= 1, "Expected at least one Gantt bar"

    def test_gantt_view_mode_switch(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        page.locator(".tab-btn", has_text="Timeline").click()
        page.locator("#ganttViewBtns button", has_text="Week").click()
        expect(page.locator("#ganttViewBtns button", has_text="Week")).to_have_class(re.compile(r"active"))

    def test_gcal_button_shows_info_toast(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        page.locator("#gcalBtn").click()
        expect(page.locator(".toast--info")).to_be_visible()

    def test_delete_milestone_via_confirmation_modal(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        ms_item = phase_card.locator(".phase-section").first.locator(".item-list li", has_text="Kickoff Meeting")
        ms_item.locator("button[title='Delete milestone']").click()

        # Confirmation modal (NOT browser confirm)
        expect(page.locator("#confirmModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#confirmOkBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")

        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        expect(phase_card.locator(".phase-section").first.locator(".item-list")).not_to_contain_text("Kickoff Meeting")

    def test_delete_event_via_confirmation_modal(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        ev_item = phase_card.locator(".phase-section").last.locator(".item-list li", has_text="Sprint Demo")
        ev_item.locator("button[title='Delete event']").click()

        expect(page.locator("#confirmModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#confirmOkBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")

        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        expect(phase_card.locator(".phase-section").last.locator(".item-list")).not_to_contain_text("Sprint Demo")

    def test_delete_phase_via_confirmation_modal(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        # Delete Colored Phase first (keep Alpha for nav test)
        phase_card = page.locator(".phase-card", has_text="Colored Phase")
        phase_card.locator("button[title='Delete phase']").click()

        expect(page.locator("#confirmModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#confirmOkBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(page.locator(".phase-card", has_text="Colored Phase")).not_to_be_attached()

    def test_back_link_navigates_to_dashboard(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        page.locator("a.back-link").click()
        page.wait_for_load_state("networkidle")
        expect(page).to_have_url(BASE_URL + "/")
        expect(page.locator("h1")).to_contain_text("Projects")

    def test_edit_project_from_project_page(self, page: Page):
        navigate_to_test_project(page, "Playwright Test Project")
        page.locator("button", has_text="Edit").first.click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modal_input_name")).to_have_value("Playwright Test Project")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()


# ── Cleanup ─────────────────────────────────────────────────────

class TestCleanup:
    """Delete the test project created by these tests."""

    def test_delete_test_project(self, page: Page):
        goto(page)
        card = page.locator(".project-card", has_text="Playwright Test Project")
        if card.count() == 0:
            pytest.skip("Test project not found — already cleaned up")
        card.hover()
        card.locator("button[title='Delete project']").click()
        expect(page.locator("#confirmModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#confirmOkBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(page.locator(".project-card", has_text="Playwright Test Project")).not_to_be_attached()
