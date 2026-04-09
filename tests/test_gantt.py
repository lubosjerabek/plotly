"""
Gantt / Timeline tab tests: bar rendering, view mode switches, date labels.

Each test creates its own project+phase via fixtures so tests are fully
self-contained and independent of execution order.
"""
import pytest
from playwright.sync_api import Page, expect

from pages import ProjectPage


class TestGantt:

    def test_timeline_tab_renders_svg_bars(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        project = ProjectPage(page)
        project.switch_to_timeline()
        project.wait_for_gantt_bars()
        assert page.locator(ProjectPage.GANTT_BARS).count() >= 1

    def test_empty_gantt_shows_placeholder(self, page: Page, make_project):
        """A project with no phases/events shows the empty-state message."""
        name = make_project()
        project = ProjectPage(page)
        project.navigate_to(name)
        project.switch_to_timeline()
        expect(page.locator(ProjectPage.TIMELINE_EMPTY)).to_be_visible()

    def test_gantt_view_buttons_present(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        project = ProjectPage(page)
        project.switch_to_timeline()
        expect(page.locator(ProjectPage.GANTT_VIEW_BTNS + " button", has_text="Day")).to_be_visible()
        expect(page.locator(ProjectPage.GANTT_VIEW_BTNS + " button", has_text="Week")).to_be_visible()
        expect(page.locator(ProjectPage.GANTT_VIEW_BTNS + " button", has_text="Month")).to_be_visible()

    def test_switch_to_week_view(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        project = ProjectPage(page)
        project.switch_to_timeline()
        project.switch_gantt_view("Week")
        expect(page.locator(ProjectPage.GANTT_VIEW_BTNS + " button", has_text="Week")).to_have_class(
            __import__("re").compile(r"active")
        )

    def test_switch_to_day_view(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        project = ProjectPage(page)
        project.switch_to_timeline()
        project.switch_gantt_view("Day")
        expect(page.locator(ProjectPage.GANTT_VIEW_BTNS + " button", has_text="Day")).to_have_class(
            __import__("re").compile(r"active")
        )

    def test_date_labels_appear_on_gantt_bars(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        project = ProjectPage(page)
        project.switch_to_timeline()
        project.wait_for_gantt_bars()
        page.wait_for_timeout(300)  # allow requestAnimationFrame to inject labels
        assert page.locator(ProjectPage.DATE_LABELS).count() >= 1, \
            "Expected at least one .gantt-date-label text element"

    def test_date_labels_survive_view_switch(self, page: Page, make_project, make_phase):
        name = make_project()
        make_phase(name)
        project = ProjectPage(page)
        project.switch_to_timeline()
        project.wait_for_gantt_bars()
        page.wait_for_timeout(300)

        for view in ("Week", "Month"):
            project.switch_gantt_view(view)
            page.wait_for_timeout(300)
            assert page.locator(ProjectPage.DATE_LABELS).count() >= 1, \
                f"Date labels missing after switching to {view} view"

    def test_date_labels_not_overlapping_bar_labels(self, page: Page, make_project, make_phase):
        """Date label x must not overlap the bar-label text."""
        name = make_project()
        make_phase(name)
        project = ProjectPage(page)
        project.switch_to_timeline()
        project.wait_for_gantt_bars()
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

    def test_gantt_no_error_fallback(self, page: Page, make_project, make_phase):
        """Verify the Gantt error-fallback div is NOT shown when data is valid."""
        name = make_project()
        make_phase(name)
        project = ProjectPage(page)
        project.switch_to_timeline()
        project.wait_for_gantt_bars()
        assert page.locator(ProjectPage.GANTT_ERROR).count() == 0, \
            "Gantt rendered an error fallback — check browser console for the exception"

    def test_timeline_renders_after_direct_navigation(self, page: Page, make_project, make_phase):
        """Timeline must render on a fresh direct page load, not only after SPA navigation."""
        name = make_project()
        make_phase(name)
        project = ProjectPage(page)
        project.navigate_to(name)
        project.switch_to_timeline()
        project.wait_for_gantt_bars()
        assert page.locator(ProjectPage.GANTT_BARS).count() >= 1, \
            "Timeline bars missing after fresh navigation to project page"
