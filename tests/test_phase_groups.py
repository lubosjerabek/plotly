"""
Phase Groups — Playwright integration tests.

Tests are grouped into four classes:
  TestPhaseGroupCRUD        — create, edit, delete a group via the UI
  TestPhaseGroupMembership  — assigning / removing phases from a group
  TestPhaseGroupGantt       — group summary bar in the Gantt / Timeline view
  TestPhaseGroupAPI         — raw REST API contract (status codes, payloads)
"""

import json
import re

from conftest import rand_date_range, rand_group_name, rand_phase_name
from pages import BASE_URL
from pages.project_page import ProjectPage
from playwright.sync_api import Page, expect

# ── Shared headers for raw API calls ─────────────────────────────────────────
H = {"Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest"}


# ─────────────────────────────────────────────────────────────────────────────
class TestPhaseGroupCRUD:
    """Create / read / update / delete a phase group through the browser UI."""

    def test_create_group_shows_group_card(self, page: Page, make_project):
        """Adding a group via the UI renders a .phase-group-card on the page."""
        ref = make_project()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        name = rand_group_name()
        project.add_group(name)
        card = project.get_group_card(name)
        expect(card._loc).to_be_visible()

    def test_group_card_displays_correct_name(self, page: Page, make_project, make_group):
        """The group header shows the exact name used when creating the group."""
        ref = make_project()
        _gid, name = make_group(ref)
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        card = project.get_group_card(name)
        expect(card._loc.locator(".phase-group-card__name")).to_have_text(name)

    def test_edit_group_updates_name(self, page: Page, make_project, make_group):
        """Editing the group name via the pencil button updates the card header."""
        ref = make_project()
        _gid, old_name = make_group(ref)
        new_name = rand_group_name()
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        card = project.get_group_card(old_name)
        card.edit_btn.click()
        expect(page.locator(ProjectPage.GENERIC_MODAL)).to_have_class(re.compile(r"is-open"))
        page.locator(ProjectPage.MODAL_NAME).fill(new_name)
        page.locator(ProjectPage.MODAL_SUBMIT).click()
        expect(page.locator(ProjectPage.TOAST_SUCCESS).last).to_be_visible()
        page.wait_for_load_state("networkidle")
        expect(project.get_group_card(new_name)._loc).to_be_visible()

    def test_delete_group_removes_card(self, page: Page, make_project, make_group):
        """Deleting a group removes the group card from the page."""
        ref = make_project()
        _gid, name = make_group(ref)
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        project.delete_group(name)
        expect(project.get_group_card(name)._loc).not_to_be_visible()

    def test_delete_group_preserves_member_phases_as_standalone(self, page: Page, make_project, make_group, make_phase):
        """After group deletion, phases that were members appear as standalone cards."""
        ref = make_project()
        _gid, group_name = make_group(ref)
        # Create a standalone phase then assign it to the group via API
        phase_name = make_phase(ref)
        # Use API to assign the phase to the group
        proj_data = page.request.get(BASE_URL + f"/api/projects/{ref.id}", headers=H).json()
        all_phases = proj_data.get("phases", []) + [p for g in proj_data.get("groups", []) for p in g.get("phases", [])]
        phase = next((p for p in all_phases if p["name"] == phase_name), None)
        assert phase is not None, "Phase not found in project"
        grp_id = next(g["id"] for g in proj_data.get("groups", []) if g["name"] == group_name)
        page.request.put(
            BASE_URL + f"/api/phases/{phase['id']}",
            data=json.dumps({"group_id": grp_id}),
            headers=H,
        )
        # Delete the group
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        project.delete_group(group_name)
        # Phase should still be visible as a standalone card
        expect(project.get_phase_card(phase_name)._loc).to_be_visible()


# ─────────────────────────────────────────────────────────────────────────────
class TestPhaseGroupMembership:
    """Assigning phases to groups and removing them."""

    def _assign_via_api(self, page: Page, phase_id: int, group_id: int | None) -> None:
        resp = page.request.put(
            BASE_URL + f"/api/phases/{phase_id}",
            data=json.dumps({"group_id": group_id}),
            headers=H,
        )
        assert resp.status == 200, f"Assign failed: {resp.text()}"

    def test_assign_phase_to_group_moves_it_inside_group_card(self, page: Page, make_project, make_group, make_phase):
        """After assigning a phase to a group, the phase card appears inside the group card."""
        ref = make_project()
        gid, group_name = make_group(ref)
        phase_name = make_phase(ref)
        proj_data = page.request.get(BASE_URL + f"/api/projects/{ref.id}", headers=H).json()
        phase = next(p for p in proj_data["phases"] if p["name"] == phase_name)
        self._assign_via_api(page, phase["id"], gid)

        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        card = project.get_group_card(group_name)
        card.expand(page)
        expect(card.child_phases().filter(has_text=phase_name)).to_be_visible()

    def test_remove_phase_from_group_makes_it_standalone(self, page: Page, make_project, make_group, make_phase):
        """Setting group to 'none' in Edit Phase returns the phase to the top-level list."""
        ref = make_project()
        gid, group_name = make_group(ref)
        phase_name = make_phase(ref)
        # Assign via API
        proj_data = page.request.get(BASE_URL + f"/api/projects/{ref.id}", headers=H).json()
        phase = next(p for p in proj_data["phases"] if p["name"] == phase_name)
        self._assign_via_api(page, phase["id"], gid)

        # Remove via UI (Edit Phase → Group = none)
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        project.assign_phase_to_group(phase_name, None)

        # Phase should appear as a standalone card
        expect(project.get_phase_card(phase_name)._loc).to_be_visible()
        # And NOT inside the group card
        group_card = project.get_group_card(group_name)
        expect(group_card.child_phases().filter(has_text=phase_name)).not_to_be_visible()

    def test_group_span_reflects_member_dates(self, page: Page, make_project, make_group):
        """Group date span = min(start) → max(end) across member phases."""
        ref = make_project()
        gid, group_name = make_group(ref)
        # Create two phases with known dates via API
        p1_start, p1_end = "2027-01-01", "2027-01-10"
        p2_start, p2_end = "2027-01-20", "2027-01-31"
        for start, end in [(p1_start, p1_end), (p2_start, p2_end)]:
            name = rand_phase_name()
            resp = page.request.post(
                BASE_URL + f"/api/phases?project_id={ref.id}",
                data=json.dumps({"name": name, "start_date": start, "end_date": end, "color": "#6366f1"}),
                headers=H,
            )
            assert resp.status == 201
            phase_id = resp.json()["id"]
            self._assign_via_api(page, phase_id, gid)

        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        card = project.get_group_card(group_name)
        dates_el = card._loc.locator(".phase-group-card__dates")
        # Should show the span from p1_start to p2_end
        expect(dates_el).to_contain_text("2027")

    def test_group_segment_count_badge_shown(self, page: Page, make_project, make_group):
        """The group card shows a segment count badge."""
        ref = make_project()
        gid, group_name = make_group(ref)
        # Add one phase and assign it
        name = rand_phase_name()
        resp = page.request.post(
            BASE_URL + f"/api/phases?project_id={ref.id}",
            data=json.dumps(
                {
                    "name": name,
                    **dict(zip(["start_date", "end_date"], rand_date_range(), strict=True)),
                    "color": "#6366f1",
                }
            ),
            headers=H,
        )
        assert resp.status == 201
        self._assign_via_api(page, resp.json()["id"], gid)

        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        card = project.get_group_card(group_name)
        expect(card._loc.locator(".badge-group")).to_be_visible()

    def test_phase_outside_group_unaffected_by_group_deletion(self, page: Page, make_project, make_group, make_phase):
        """A standalone phase is unaffected when a different group is deleted."""
        ref = make_project()
        _gid, group_name = make_group(ref)
        standalone_phase = make_phase(ref)
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        project.delete_group(group_name)
        expect(project.get_phase_card(standalone_phase)._loc).to_be_visible()


# ─────────────────────────────────────────────────────────────────────────────
class TestPhaseGroupGantt:
    """Group summary bars in the Timeline (Gantt) view."""

    def _assign_via_api(self, page: Page, phase_id: int, group_id: int) -> None:
        page.request.put(
            BASE_URL + f"/api/phases/{phase_id}",
            data=json.dumps({"group_id": group_id}),
            headers=H,
        )

    def test_group_summary_bar_appears_in_gantt(self, page: Page, make_project, make_group):
        """A group with member phases renders a .gantt-group-bar in the SVG."""
        ref = make_project()
        gid, _gname = make_group(ref)
        name = rand_phase_name()
        resp = page.request.post(
            BASE_URL + f"/api/phases?project_id={ref.id}",
            data=json.dumps(
                {
                    "name": name,
                    **dict(zip(["start_date", "end_date"], rand_date_range(), strict=True)),
                    "color": "#6366f1",
                }
            ),
            headers=H,
        )
        assert resp.status == 201
        self._assign_via_api(page, resp.json()["id"], gid)

        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        project.switch_to_timeline()
        project.wait_for_gantt_bars()
        expect(page.locator(".gantt .gantt-group-bar")).to_be_visible()

    def test_ungrouped_project_gantt_unchanged(self, page: Page, make_project, make_phase):
        """A project with no groups renders the Gantt with regular phase bars only."""
        ref = make_project()
        make_phase(ref)
        project = ProjectPage(page)
        project.navigate_by_id(ref.id)
        project.switch_to_timeline()
        project.wait_for_gantt_bars()
        # No group bars present
        expect(page.locator(".gantt .gantt-group-bar")).not_to_be_visible()
        # Regular phase bar still present
        assert page.locator(ProjectPage.GANTT_BARS).count() >= 1


# ─────────────────────────────────────────────────────────────────────────────
class TestPhaseGroupAPI:
    """Raw REST API contract — status codes and JSON payload shape."""

    def test_create_group_returns_201_with_id(self, page: Page, make_project):
        """POST /api/phase-groups returns 201 and a JSON body containing 'id'."""
        ref = make_project()
        resp = page.request.post(
            BASE_URL + f"/api/phase-groups?project_id={ref.id}",
            data=json.dumps({"name": rand_group_name(), "color": "#6366f1"}),
            headers=H,
        )
        assert resp.status == 201
        body = resp.json()
        assert "id" in body
        assert isinstance(body["id"], int)
        assert body["project_id"] == ref.id
        # Cleanup
        page.request.delete(BASE_URL + f"/api/phase-groups/{body['id']}", headers=H)

    def test_create_group_without_name_returns_422(self, page: Page, make_project):
        """POST /api/phase-groups with empty name returns 422."""
        ref = make_project()
        resp = page.request.post(
            BASE_URL + f"/api/phase-groups?project_id={ref.id}",
            data=json.dumps({"name": "", "color": "#6366f1"}),
            headers=H,
        )
        assert resp.status == 422

    def test_update_group_returns_200(self, page: Page, make_project, make_group):
        """PUT /api/phase-groups/{id} updates name and returns 200."""
        ref = make_project()
        gid, _name = make_group(ref)
        new_name = rand_group_name()
        resp = page.request.put(
            BASE_URL + f"/api/phase-groups/{gid}",
            data=json.dumps({"name": new_name}),
            headers=H,
        )
        assert resp.status == 200
        assert resp.json()["name"] == new_name

    def test_delete_group_returns_200_phases_survive(self, page: Page, make_project, make_group):
        """DELETE /api/phase-groups/{id} returns 200; member phases are NOT deleted."""
        ref = make_project()
        gid, _gname = make_group(ref)
        # Create and assign a phase
        phase_resp = page.request.post(
            BASE_URL + f"/api/phases?project_id={ref.id}",
            data=json.dumps(
                {
                    "name": rand_phase_name(),
                    **dict(zip(["start_date", "end_date"], rand_date_range(), strict=True)),
                    "color": "#6366f1",
                }
            ),
            headers=H,
        )
        assert phase_resp.status == 201
        phase_id = phase_resp.json()["id"]
        page.request.put(BASE_URL + f"/api/phases/{phase_id}", data=json.dumps({"group_id": gid}), headers=H)
        # Delete the group
        del_resp = page.request.delete(BASE_URL + f"/api/phase-groups/{gid}", headers=H)
        assert del_resp.status == 200
        # Phase should still exist in project data, now ungrouped
        proj = page.request.get(BASE_URL + f"/api/projects/{ref.id}", headers=H).json()
        all_phases = proj.get("phases", []) + [p for g in proj.get("groups", []) for p in g.get("phases", [])]
        assert any(p["id"] == phase_id for p in all_phases), "Phase was incorrectly deleted with the group"
        # Verify phase has no group_id
        phase_after = next(p for p in all_phases if p["id"] == phase_id)
        assert phase_after["group_id"] is None

    def test_assign_phase_to_group_via_put_phases(self, page: Page, make_project, make_group):
        """PUT /api/phases/{id} with group_id moves phase into group in API response."""
        ref = make_project()
        gid, _gname = make_group(ref)
        phase_resp = page.request.post(
            BASE_URL + f"/api/phases?project_id={ref.id}",
            data=json.dumps(
                {
                    "name": rand_phase_name(),
                    **dict(zip(["start_date", "end_date"], rand_date_range(), strict=True)),
                    "color": "#6366f1",
                }
            ),
            headers=H,
        )
        assert phase_resp.status == 201
        phase_id = phase_resp.json()["id"]
        upd = page.request.put(
            BASE_URL + f"/api/phases/{phase_id}",
            data=json.dumps({"group_id": gid}),
            headers=H,
        )
        assert upd.status == 200
        assert upd.json()["group_id"] == gid
        # Verify project endpoint reflects the assignment
        proj = page.request.get(BASE_URL + f"/api/projects/{ref.id}", headers=H).json()
        grp = next((g for g in proj.get("groups", []) if g["id"] == gid), None)
        assert grp is not None
        assert any(p["id"] == phase_id for p in grp.get("phases", []))

    def test_viewer_cannot_create_group(self, page: Page, second_user_page: Page, make_project):
        """A viewer collaborator gets 403 when calling POST /api/phase-groups."""
        ref = make_project()
        # Add second user as viewer collaborator
        vu = second_user_page.request.get(BASE_URL + "/api/profile", headers=H).json()
        page.request.post(
            BASE_URL + f"/api/projects/{ref.id}/collaborators",
            data=json.dumps({"email": vu["email"], "role": "viewer"}),
            headers=H,
        )
        resp = second_user_page.request.post(
            BASE_URL + f"/api/phase-groups?project_id={ref.id}",
            data=json.dumps({"name": rand_group_name()}),
            headers=H,
        )
        assert resp.status == 403
