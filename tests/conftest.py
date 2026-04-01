"""
Test session bootstrap for Plotly (PHP + Docker stack).

Start behaviour:
  - If localhost:8000 is already reachable → use it as-is (works for an already-
    running docker-compose stack or any other server on that port).
  - Otherwise → run `docker-compose up -d` from the project root and wait up to
    60 s for port 8000 to become reachable.

Authentication:
  Each test gets a pre-authenticated `page` fixture. The session logs in once
  (using TEST_AUTH_EMAIL / TEST_AUTH_PASS env vars) and reuses the cookie for
  every test function.

  TEST_AUTH_EMAIL must match a user in the `users` table with role='admin'.
  TEST_AUTH_PASS must match that user's password_hash.

  Example:
      TEST_AUTH_EMAIL=admin@example.com TEST_AUTH_PASS=plotly pytest tests/ -v
"""
import json
import os
import re
import socket
import subprocess
import time

import pytest
from faker import Faker
from playwright.sync_api import Browser, Page, expect

BASE_URL = "http://localhost:8000"
TEST_AUTH_EMAIL = os.getenv("TEST_AUTH_EMAIL", os.getenv("TEST_AUTH_USER", "admin@example.com"))
TEST_AUTH_PASS  = os.getenv("TEST_AUTH_PASS", "plotly")

_started_stack = False


# ── Faker instance (shared across helpers) ────────────────────────────────────

fake = Faker()


def rand_project_name() -> str:
    return f"{fake.bs().title()} {fake.word().title()}"


def rand_phase_name() -> str:
    return f"{fake.word().title()} Phase"


def rand_milestone_name() -> str:
    return fake.catch_phrase()


def rand_event_name() -> str:
    return f"{fake.word().title()} {fake.word().title()} Event"


def rand_email() -> str:
    return fake.unique.email()


# ── Docker / server bootstrap ─────────────────────────────────────────────────

def wait_for_port(host: str, port: int, timeout: float = 60.0) -> bool:
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            with socket.create_connection((host, port), timeout=1):
                return True
        except OSError:
            time.sleep(0.5)
    return False


def pytest_configure(config):
    global _started_stack
    try:
        with socket.create_connection(("localhost", 8000), timeout=1):
            return
    except OSError:
        pass

    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    result = subprocess.run(
        ["docker-compose", "up", "-d"],
        cwd=project_root,
        capture_output=True,
        text=True,
    )
    if result.returncode != 0:
        raise RuntimeError(f"docker-compose up failed:\n{result.stderr}")

    _started_stack = True

    if not wait_for_port("localhost", 8000, timeout=60):
        subprocess.run(["docker-compose", "down"], cwd=project_root)
        raise RuntimeError("Docker stack did not become reachable on port 8000 within 60 s")


def pytest_unconfigure(config):
    if _started_stack:
        project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        subprocess.run(["docker-compose", "down"], cwd=project_root, check=False)


# ── Auth fixtures ─────────────────────────────────────────────────────────────

@pytest.fixture(scope="session")
def base_url():
    return BASE_URL


@pytest.fixture(scope="session")
def auth_state(browser: Browser):
    """Log in once per session as admin; return Playwright storage state."""
    context = browser.new_context()
    pg = context.new_page()
    pg.goto(BASE_URL + "/login")
    pg.fill("input[name='email']",    TEST_AUTH_EMAIL)
    pg.fill("input[name='password']", TEST_AUTH_PASS)
    pg.click("button[type='submit']")
    pg.wait_for_load_state("networkidle")
    if "/login" in pg.url:
        raise RuntimeError(
            f"Login failed for '{TEST_AUTH_EMAIL}'. "
            "Check TEST_AUTH_PASS matches the DB user's password_hash."
        )
    state = context.storage_state()
    context.close()
    return state


@pytest.fixture
def page(browser: Browser, auth_state):
    """Function-scoped page pre-loaded with the admin session cookie."""
    context = browser.new_context(storage_state=auth_state)
    pg = context.new_page()
    yield pg
    context.close()


@pytest.fixture(scope="session")
def second_user_auth_state(browser: Browser, auth_state):
    """
    Create a non-admin test user via the invite flow (once per session).
    Yields (storage_state, email, password).
    """
    email    = rand_email()
    password = fake.password(length=16, special_chars=True, digits=True, upper_case=True)
    name     = fake.name()

    ctx = browser.new_context(storage_state=auth_state)
    pg  = ctx.new_page()
    resp = pg.request.post(
        BASE_URL + "/api/admin/invites",
        data=json.dumps({"label": "Playwright CI", "expires_days": 1}),
        headers={"Content-Type": "application/json"},
    )
    assert resp.status == 201, f"Invite creation failed: {resp.text()}"
    token = resp.json()["token"]
    ctx.close()

    ctx2 = browser.new_context()
    pg2  = ctx2.new_page()
    pg2.goto(BASE_URL + f"/register/{token}")
    pg2.wait_for_load_state("networkidle")
    pg2.fill("input[name='name']",             name)
    pg2.fill("input[name='email']",            email)
    pg2.fill("input[name='password']",         password)
    pg2.fill("input[name='password_confirm']", password)
    pg2.click("button[type='submit']")
    pg2.wait_for_load_state("networkidle")
    if "/login" in pg2.url or "/register/" in pg2.url:
        raise RuntimeError(f"Second user registration failed. URL: {pg2.url}")
    state = ctx2.storage_state()
    ctx2.close()
    return state, email, password


@pytest.fixture
def second_user_page(browser: Browser, second_user_auth_state):
    """Function-scoped page authenticated as the non-admin second user."""
    state, _email, _pass = second_user_auth_state
    context = browser.new_context(storage_state=state)
    pg = context.new_page()
    yield pg
    context.close()


# ── Resource factory fixtures ─────────────────────────────────────────────────

@pytest.fixture
def make_project(page: Page):
    """
    Factory fixture — call ``name = make_project()`` (or ``make_project(name=…)``)
    to create a project via the UI.  Every project created this way is
    automatically deleted after the test, regardless of pass/fail.

    Names default to ``rand_project_name()`` so each run exercises different
    input data (pesticide paradox countermeasure).
    """
    created: list[str] = []

    def _make(name: str | None = None, desc: str = "") -> str:
        name = name or rand_project_name()
        page.goto(BASE_URL + "/")
        page.wait_for_load_state("networkidle")
        page.locator("#btnNewProject").click()
        expect(page.locator("#projectModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#pm_name").fill(name)
        if desc:
            page.locator("#pm_desc").fill(desc)
        page.locator("#projectModalSubmit").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        created.append(name)
        return name

    yield _make

    for name in created:
        try:
            page.goto(BASE_URL + "/")
            page.wait_for_load_state("networkidle")
            card = page.locator(".project-card", has_text=name)
            if card.count() == 0:
                continue
            card.hover()
            card.locator("button[title='Delete project']").click()
            page.locator("#confirmOkBtn").click()
            page.wait_for_load_state("networkidle")
        except Exception:
            pass  # best-effort cleanup


@pytest.fixture
def make_phase(page: Page):
    """
    Factory fixture.
    Call ``phase_name = make_phase(project_name)`` to navigate to the project
    and add a phase. Optionally pass ``name``, ``start``, ``end``, ``desc``.

    Names default to ``rand_phase_name()``.
    """
    def _make(
        project_name: str,
        name:  str | None = None,
        start: str        = "2027-01-01",
        end:   str        = "2027-06-30",
        desc:  str        = "",
    ) -> str:
        from helpers import navigate_to_project
        navigate_to_project(page, project_name)
        name = name or rand_phase_name()
        page.locator("button", has_text="Add Phase").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#modal_input_name").fill(name)
        if desc:
            page.locator("#modal_input_desc").fill(desc)
        page.locator("#modal_input_start").fill(start)
        page.locator("#modal_input_end").fill(end)
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        return name

    return _make


@pytest.fixture
def make_milestone(page: Page):
    """
    Factory fixture.
    Call ``ms_name = make_milestone(project_name, phase_name)`` to navigate to the
    project and add a milestone under the given phase.

    Expands the target phase card automatically.
    Names default to ``rand_milestone_name()``.
    """
    def _make(
        project_name: str,
        phase_name:   str,
        name:         str | None = None,
        target:       str        = "2027-03-01",
    ) -> str:
        from helpers import navigate_to_project, expand_phase
        navigate_to_project(page, project_name)
        name = name or rand_milestone_name()
        expand_phase(page, phase_name)
        phase_card = page.locator(".phase-card", has_text=phase_name)
        phase_card.locator(".phase-section").first.locator("button", has_text="Add").click()
        expect(page.locator("#genericModal")).to_have_class(re.compile(r"is-open"))
        page.locator("#modal_input_name").fill(name)
        page.locator("#modal_input_target").fill(target)
        page.locator("#modalSubmitBtn").click()
        expect(page.locator(".toast--success")).to_be_visible()
        page.wait_for_load_state("networkidle")
        return name

    return _make
