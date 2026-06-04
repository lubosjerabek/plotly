"""
Phase Dependency modal — Playwright integration tests.

Tests are grouped into two classes:
  TestPhaseDependencyUI   — setting / clearing dependencies via the modal
  TestDependencyOptgroups — <optgroup> grouping in the dependency select
"""

from conftest import rand_date_range, rand_group_name, rand_phase_name
from pages.project_page import ProjectPage
from playwright.sync_api import Page, expect


# ─────────────────────────────────────────────────────────────────────────────
class TestPhaseDependencyUI:
    """Set and clear phase dependencies through the modal."""

    def test_set_standalone_phase_dependency(self, page: Page, make_project):
        """Picking a phase in the dependency modal saves without error."""
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)

        s, e = rand_date_range()
        predecessor = project.add_phase(rand_phase_name(), s, e)
        s2, e2 = rand_date_range()
        dependent = project.add_phase(rand_phase_name(), s2, e2)

        project.set_dependency(dependent, predecessor)

        expect(page.locator(".toast--success").last).to_be_visible()

    def test_clear_phase_dependency(self, page: Page, make_project):
        """Clearing a previously set dependency saves without error."""
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)

        s, e = rand_date_range()
        predecessor = project.add_phase(rand_phase_name(), s, e)
        s2, e2 = rand_date_range()
        dependent = project.add_phase(rand_phase_name(), s2, e2)

        project.set_dependency(dependent, predecessor)
        project.set_dependency(dependent, None)

        expect(page.locator(".toast--success").last).to_be_visible()

    def test_set_dependency_on_grouped_phase(self, page: Page, make_project):
        """A phase inside a group can have its dependency set via the modal."""
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)

        group_name = rand_group_name()
        project.add_group(group_name)

        s, e = rand_date_range()
        predecessor = project.add_phase(rand_phase_name(), s, e)
        s2, e2 = rand_date_range()
        dependent = project.add_phase(rand_phase_name(), s2, e2, group=group_name)

        project.set_dependency(dependent, predecessor)

        expect(page.locator(".toast--success").last).to_be_visible()


# ─────────────────────────────────────────────────────────────────────────────
class TestDependencyOptgroups:
    """<optgroup> grouping in the dependency select."""

    def test_standalone_phases_appear_under_standalone_optgroup(self, page: Page, make_project):
        """Standalone phases are listed inside an <optgroup> labelled 'Standalone'."""
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)

        s, e = rand_date_range()
        predecessor_name = rand_phase_name()
        project.add_phase(predecessor_name, s, e)
        s2, e2 = rand_date_range()
        dependent_name = rand_phase_name()
        project.add_phase(dependent_name, s2, e2)

        # Open the dependency modal for the dependent phase
        project.get_phase_card(dependent_name)._loc.locator("button[title='Set dependency']").click()
        expect(page.locator("#genericModal")).to_be_visible()

        optgroup = page.locator("#modal_input_target optgroup[label='Standalone']")
        expect(optgroup).to_be_attached()
        expect(optgroup.locator(f"option:has-text('{predecessor_name}')")).to_be_attached()

        page.keyboard.press("Escape")

    def test_grouped_phases_appear_under_group_optgroup(self, page: Page, make_project):
        """Phases inside a group are listed under an <optgroup> with the group's name."""
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)

        group_name = rand_group_name()
        project.add_group(group_name)

        s, e = rand_date_range()
        grouped_phase = rand_phase_name()
        project.add_phase(grouped_phase, s, e, group=group_name)

        s2, e2 = rand_date_range()
        dependent_name = rand_phase_name()
        project.add_phase(dependent_name, s2, e2)

        project.get_phase_card(dependent_name)._loc.locator("button[title='Set dependency']").click()
        expect(page.locator("#genericModal")).to_be_visible()

        optgroup = page.locator(f"#modal_input_target optgroup[label='{group_name}']")
        expect(optgroup).to_be_attached()
        expect(optgroup.locator(f"option:has-text('{grouped_phase}')")).to_be_attached()

        page.keyboard.press("Escape")

    def test_phases_from_different_groups_are_in_separate_optgroups(self, page: Page, make_project):
        """Phases from two different groups each appear in their own <optgroup>."""
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)

        group_a = rand_group_name()
        group_b = rand_group_name()
        project.add_group(group_a)
        project.add_group(group_b)

        s, e = rand_date_range()
        phase_a = rand_phase_name()
        project.add_phase(phase_a, s, e, group=group_a)

        s2, e2 = rand_date_range()
        phase_b = rand_phase_name()
        project.add_phase(phase_b, s2, e2, group=group_b)

        s3, e3 = rand_date_range()
        dependent_name = rand_phase_name()
        project.add_phase(dependent_name, s3, e3)

        project.get_phase_card(dependent_name)._loc.locator("button[title='Set dependency']").click()
        expect(page.locator("#genericModal")).to_be_visible()

        expect(page.locator(f"#modal_input_target optgroup[label='{group_a}']")).to_be_attached()
        expect(page.locator(f"#modal_input_target optgroup[label='{group_b}']")).to_be_attached()

        page.keyboard.press("Escape")
