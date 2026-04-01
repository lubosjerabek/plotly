"""
Project detail tests: phases, milestones, events, modals, navigation.
Depends on "Playwright Test Project" existing (created by test_dashboard.py).
"""
import re
import pytest
from playwright.sync_api import Page, expect

from helpers import BASE_URL, goto, navigate_to_project, expand_phase, create_project


class TestProjectDetail:

    def test_navigate_to_project(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expect(page).to_have_url(re.compile(r"/project/\d+"))

    def test_project_header_shows_name(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expect(page.locator("#pName")).to_contain_text("Playwright Test Project")

    def test_topbar_shows_project_name(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expect(page.locator("#topbarTitle")).to_contain_text("Playwright Test Project")

    def test_phases_tab_active_by_default(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expect(page.locator(".tab-btn[data-tab='phases']")).to_have_class(re.compile(r"active"))

    def test_timeline_tab_visible(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expect(page.locator(".tab-btn", has_text="Timeline")).to_be_visible()

    def test_collaborators_tab_visible(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expect(page.locator(".tab-btn[data-tab='collaborators']")).to_be_visible()

    def test_add_phase_opens_modal(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        page.locator("button", has_text="Add Phase").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modalTitle")).to_contain_text("Add Phase")

    def test_add_phase_creates_card_with_upcoming_badge(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        page.locator("button", has_text="Add Phase").click()
        page.locator("#modal_input_name").fill("Alpha Phase")
        page.locator("#modal_input_start").fill("2027-01-01")
        page.locator("#modal_input_end").fill("2027-03-31")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        expect(phase_card).to_be_visible()
        expect(phase_card.locator(".badge-upcoming")).to_be_visible()

    def test_add_phase_with_description(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        page.locator("button", has_text="Add Phase").click()
        page.locator("#modal_input_name").fill("Described Phase")
        page.locator("#modal_input_desc").fill("A detailed description for this phase.")
        page.locator("#modal_input_start").fill("2027-07-01")
        page.locator("#modal_input_end").fill("2027-09-30")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expand_phase(page, "Described Phase")
        expect(page.locator(".phase-card", has_text="Described Phase").locator(".phase-description")).to_contain_text("A detailed description")

    def test_upcoming_phase_collapsed_by_default(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expect(page.locator(".phase-card", has_text="Alpha Phase")).to_have_class(re.compile(r"is-collapsed"))

    def test_phase_expand_and_collapse(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        card = page.locator(".phase-card", has_text="Alpha Phase")
        # Expand
        card.locator(".phase-card__toggle-area").click()
        page.wait_for_timeout(300)
        expect(card).not_to_have_class(re.compile(r"is-collapsed"))
        # Collapse
        card.locator(".phase-card__toggle-area").click()
        page.wait_for_timeout(300)
        expect(card).to_have_class(re.compile(r"is-collapsed"))

    def test_edit_phase_prepopulates_name(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        page.locator(".phase-card", has_text="Alpha Phase").locator("button[title='Edit phase']").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modal_input_name")).to_have_value("Alpha Phase")
        page.keyboard.press("Escape")

    def test_esc_closes_generic_modal(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        page.locator("button", has_text="Add Phase").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        page.keyboard.press("Escape")
        expect(page.locator("#genericModal")).not_to_have_class(re.compile(r"is-open"))

    def test_add_milestone_to_phase(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expand_phase(page, "Alpha Phase")
        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        phase_card.locator(".phase-section").first.locator("button", has_text="Add").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modalTitle")).to_contain_text("Milestone")
        page.locator("#modal_input_name").fill("Kickoff Meeting")
        page.locator("#modal_input_target").fill("2027-01-15")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expand_phase(page, "Alpha Phase")
        expect(
            page.locator(".phase-card", has_text="Alpha Phase")
                .locator(".phase-section").first
                .locator(".item-list")
        ).to_contain_text("Kickoff Meeting")

    def test_add_event_to_phase(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expand_phase(page, "Alpha Phase")
        phase_card = page.locator(".phase-card", has_text="Alpha Phase")
        phase_card.locator(".phase-section").last.locator("button", has_text="Add").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modalTitle")).to_contain_text("Event")
        page.locator("#modal_input_name").fill("Sprint Demo")
        page.locator("#modal_input_start").fill("2027-01-20")
        page.locator("#modal_input_end").fill("2027-01-20")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expand_phase(page, "Alpha Phase")
        expect(
            page.locator(".phase-card", has_text="Alpha Phase")
                .locator(".phase-section").last
                .locator(".item-list")
        ).to_contain_text("Sprint Demo")

    def test_delete_milestone(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expand_phase(page, "Alpha Phase")
        ms = page.locator(".phase-card", has_text="Alpha Phase") \
                 .locator(".phase-section").first \
                 .locator(".item-list li", has_text="Kickoff Meeting")
        ms.locator("button[title='Delete milestone']").click()
        expect(page.locator("#confirmModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#confirmOkBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expand_phase(page, "Alpha Phase")
        expect(
            page.locator(".phase-card", has_text="Alpha Phase")
                .locator(".phase-section").first
                .locator(".item-list")
        ).not_to_contain_text("Kickoff Meeting")

    def test_delete_event(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        expand_phase(page, "Alpha Phase")
        ev = page.locator(".phase-card", has_text="Alpha Phase") \
                 .locator(".phase-section").last \
                 .locator(".item-list li", has_text="Sprint Demo")
        ev.locator("button[title='Delete event']").click()
        expect(page.locator("#confirmModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#confirmOkBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expand_phase(page, "Alpha Phase")
        expect(
            page.locator(".phase-card", has_text="Alpha Phase")
                .locator(".phase-section").last
                .locator(".item-list")
        ).not_to_contain_text("Sprint Demo")

    def test_delete_phase(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        page.locator(".phase-card", has_text="Described Phase").locator("button[title='Delete phase']").click()
        expect(page.locator("#confirmModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#confirmOkBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(page.locator(".phase-card", has_text="Described Phase")).not_to_be_attached()

    def test_back_link_returns_to_dashboard(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        page.locator("a.back-link").click()
        page.wait_for_load_state("networkidle")
        expect(page).to_have_url(BASE_URL + "/")
        expect(page.locator("h1")).to_contain_text("Projects")

    def test_edit_project_from_project_page(self, page: Page):
        navigate_to_project(page, "Playwright Test Project")
        page.locator("button", has_text="Edit").first.click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        expect(page.locator("#modal_input_name")).to_have_value("Playwright Test Project")
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
