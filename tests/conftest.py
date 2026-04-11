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
      TEST_AUTH_EMAIL=admin@example.com TEST_AUTH_PASS=plotly_admin_pass pytest tests/ -v
"""
import json
import os
import random
import socket
import subprocess
import time
from datetime import date, timedelta
from typing import Generator

import pytest
from faker import Faker
from pages import BASE_URL, ProjectPage
from pages.login_page import LoginPage
from playwright.sync_api import Browser, Page

TEST_AUTH_EMAIL = os.getenv("TEST_AUTH_EMAIL", os.getenv("TEST_AUTH_USER", "admin@example.com"))
TEST_AUTH_PASS  = os.getenv("TEST_AUTH_PASS", "plotly_admin_pass")

_started_stack = False


# ── Faker instance ────────────────────────────────────────────────────────────

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


def rand_future_date(min_days: int = 60, max_days: int = 365) -> str:
    """Return a random future date string in YYYY-MM-DD format."""
    return (date.today() + timedelta(days=random.randint(min_days, max_days))).strftime("%Y-%m-%d")


def rand_date_range(
    min_start: int = 30,
    max_start: int = 180,
    min_dur: int = 30,
    max_dur: int = 180,
) -> tuple[str, str]:
    """Return a (start, end) pair of future date strings."""
    s = date.today() + timedelta(days=random.randint(min_start, max_start))
    e = s + timedelta(days=random.randint(min_dur, max_dur))
    return s.strftime("%Y-%m-%d"), e.strftime("%Y-%m-%d")


# ── ProjectRef ────────────────────────────────────────────────────────────────

class ProjectRef(str):
    """
    A string subclass that also carries the project's database id.

    Behaves exactly like a plain string (has_text=ref, f"{ref}", comparisons)
    so existing test code that treats it as a name requires no changes.
    Use ref.id for direct URL navigation or API calls.
    """
    def __new__(cls, name: str, pid: int):
        obj = super().__new__(cls, name)
        obj.id = pid
        return obj


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
    login = LoginPage(pg)
    login.goto()
    login.login(TEST_AUTH_EMAIL, TEST_AUTH_PASS)
    if login.is_on_login_page():
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
        headers={"Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest"},
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
def project(page: Page) -> Generator[ProjectRef, None, None]:
    """
    Function-scoped: creates a project via the REST API, yields a ProjectRef
    (behaves as the project name string but also has a .id attribute for direct
    URL navigation), and deletes the project via API on teardown.

    Each test that requests this fixture gets its own isolated project.
    """
    name = rand_project_name()
    resp = page.request.post(
        BASE_URL + "/api/projects",
        data=json.dumps({"name": name}),
        headers={"Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest"},
    )
    assert resp.status == 201, f"Project creation failed: {resp.text()}"
    pid = resp.json()["id"]
    yield ProjectRef(name, pid)
    page.request.delete(
        BASE_URL + f"/api/projects/{pid}",
        headers={"X-Requested-With": "XMLHttpRequest"},
    )


@pytest.fixture
def make_project(page: Page):
    """
    Factory fixture — call ``ref = make_project()`` (or ``make_project(name=…)``)
    to create a project via the REST API.  Every project created this way is
    automatically deleted after the test via API, regardless of pass/fail.

    Returns a ProjectRef (string subclass with .id attribute).
    Names default to ``rand_project_name()`` so each run exercises different
    input data (pesticide paradox countermeasure).
    """
    created: list[int] = []

    def _make(name: str | None = None, desc: str = "") -> ProjectRef:
        n = name or rand_project_name()
        resp = page.request.post(
            BASE_URL + "/api/projects",
            data=json.dumps({"name": n, "description": desc}),
            headers={"Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest"},
        )
        assert resp.status == 201, f"Project creation failed: {resp.text()}"
        pid = resp.json()["id"]
        created.append(pid)
        return ProjectRef(n, pid)

    yield _make

    for pid in created:
        try:
            page.request.delete(
                BASE_URL + f"/api/projects/{pid}",
                headers={"X-Requested-With": "XMLHttpRequest"},
            )
        except Exception:
            pass  # best-effort cleanup


@pytest.fixture
def make_phase(page: Page):
    """
    Factory fixture.
    Call ``phase_name = make_phase(project_ref)`` to navigate to the project
    and add a phase. Optionally pass ``name``, ``start``, ``end``, ``desc``.

    Names default to ``rand_phase_name()``.
    Dates default to a random future range via ``rand_date_range()``.
    """
    project = ProjectPage(page)

    def _make(
        project_ref: "ProjectRef | str",
        name:  str | None = None,
        start: str | None = None,
        end:   str | None = None,
        desc:  str        = "",
    ) -> str:
        if start is None or end is None:
            _start, _end = rand_date_range()
            start = start or _start
            end = end or _end
        if hasattr(project_ref, "id"):
            project.navigate_by_id(project_ref.id)
        else:
            project.navigate_to(project_ref)
        return project.add_phase(name or rand_phase_name(), start, end, desc)

    return _make


@pytest.fixture
def make_milestone(page: Page):
    """
    Factory fixture.
    Call ``ms_name = make_milestone(project_ref, phase_name)`` to navigate to the
    project and add a milestone under the given phase.

    Expands the target phase card automatically.
    Names default to ``rand_milestone_name()``.
    Target date defaults to ``rand_future_date()``.
    """
    project = ProjectPage(page)

    def _make(
        project_ref:  "ProjectRef | str",
        phase_name:   str,
        name:         str | None = None,
        target:       str | None = None,
    ) -> str:
        if target is None:
            target = rand_future_date()
        if hasattr(project_ref, "id"):
            project.navigate_by_id(project_ref.id)
        else:
            project.navigate_to(project_ref)
        return project.add_milestone(phase_name, name or rand_milestone_name(), target)

    return _make
