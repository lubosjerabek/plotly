"""
Gantt / Timeline tab tests: bar rendering, view mode switches, date labels.

Each test creates its own project+phase via fixtures so tests are fully
self-contained and independent of execution order.
"""
import re
import pytest
from playwright.sync_api import Page, expect

from helpers import BASE_URL, navigate_to_project


def _open_timeline(page: Page, project_name: str | None = None):
    """Switch to the Timeline tab. Navigates to the project first if a name is given."""
    if project_name:
        navigate_to_project(page, project_name)
    page.locator(".tab-btn", has_text="Timeline").click()
    expect(page.locator("#tab-timeline")).to_be_visible()


def _wait_for_bars(page: Page, timeout: int = 8000):
    page.wait_for_selector(".gantt .bar", timeout=timeout)


class TestGantt:

    def test_timeline_tab_renders_svg_bars(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)        # navigates to project, adds phase
        _open_timeline(page)    # already on project page — just switch tab
        _wait_for_bars(page)
        assert page.locator(".gantt .bar").count() >= 1

    def test_empty_gantt_shows_placeholder(self, page: Page, make_project):
        """A project with no phases/events shows the empty-state message."""
        name = make_project()
        navigate_to_project(page, name)
        page.locator(".tab-btn", has_text="Timeline").click()
        expect(page.locator("#tab-timeline .item-empty")).to_be_visible()

    def test_gantt_view_buttons_present(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        _open_timeline(page)
        expect(page.locator("#ganttViewBtns button", has_text="Day")).to_be_visible()
        expect(page.locator("#ganttViewBtns button", has_text="Week")).to_be_visible()
        expect(page.locator("#ganttViewBtns button", has_text="Month")).to_be_visible()

    def test_switch_to_week_view(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        _open_timeline(page)
        page.locator("#ganttViewBtns button", has_text="Week").click()
        expect(page.locator("#ganttViewBtns button", has_text="Week")).to_have_class(re.compile(r"active"))

    def test_switch_to_day_view(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        _open_timeline(page)
        page.locator("#ganttViewBtns button", has_text="Day").click()
        expect(page.locator("#ganttViewBtns button", has_text="Day")).to_have_class(re.compile(r"active"))

    def test_date_labels_appear_on_gantt_bars(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        _open_timeline(page)
        _wait_for_bars(page)
        page.wait_for_timeout(300)  # allow requestAnimationFrame to inject labels
        assert page.locator(".gantt .gantt-date-label").count() >= 1, \
            "Expected at least one .gantt-date-label text element"

    def test_date_labels_survive_view_switch(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        _open_timeline(page)
        _wait_for_bars(page)
        page.wait_for_timeout(300)

        for view in ("Week", "Month"):
            page.locator("#ganttViewBtns button", has_text=view).click()
            page.wait_for_timeout(300)
            assert page.locator(".gantt .gantt-date-label").count() >= 1, \
                f"Date labels missing after switching to {view} view"

    def test_date_labels_not_overlapping_bar_labels(self, page: Page, make_project, make_phase):
        """Date label x must not overlap the bar-label text."""
        name = make_project()
        make_phase(name)
        _open_timeline(page)
        _wait_for_bars(page)
        page.wait_for_timeout(400)

        overlaps = page.evaluate("""() => {
            const issues = [];
            document.querySelectorAll('.bar-wrapper').forEach(wrapper => {
                const bar       = wrapper.querySelector('.bar');
                const dateLabel = wrapper.querySelector('.gantt-date-label');
                const barLabel  = wrapper.querySelector('.bar-label');
                if (!bar || !dateLabel) return;

                const barRight   = parseFloat(bar.getAttribute('x') || 0)
                                 + parseFloat(bar.getAttribute('width') || 0);
                const dateLabelX = parseFloat(dateLabel.getAttribute('x') || 0);
                const barLabelX  = barLabel ? parseFloat(barLabel.getAttribute('x') || 0) : 0;

                if (barLabel && barLabelX > barRight) {
                    const barLabelRight = barLabelX + (barLabel.getComputedTextLength() || 0);
                    if (dateLabelX < barLabelRight - 2) {
                        issues.push({id: wrapper.getAttribute('data-id'), dateLabelX, barLabelRight});
                    }
                } else if (dateLabelX < barRight) {
                    issues.push({id: wrapper.getAttribute('data-id'), dateLabelX, barRight});
                }
            });
            return issues;
        }""")
        assert overlaps == [], f"Date labels overlap bar labels on: {overlaps}"
