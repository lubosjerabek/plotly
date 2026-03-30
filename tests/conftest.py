"""
Test session bootstrap for Plotly (PHP + Docker stack).

Start behaviour:
  - If localhost:8000 is already reachable → use it as-is (works for an already-
    running docker-compose stack or any other server on that port).
  - Otherwise → run `docker-compose up -d` from the project root and wait up to
    60 s for port 8000 to become reachable.

Authentication:
  Each test gets a pre-authenticated `page` fixture. The session logs in once
  (using TEST_AUTH_USER / TEST_AUTH_PASS env vars, defaulting to admin/plotly)
  and reuses the cookie for every test function.

  Make sure the AUTH_PASS_HASH in docker-compose.yml matches TEST_AUTH_PASS.
  Generate a fresh hash with:
      docker-compose exec app php -r "echo password_hash('plotly', PASSWORD_DEFAULT) . PHP_EOL;"
  Then set AUTH_PASS_HASH in docker-compose.yml to that value.
"""
import os
import socket
import subprocess
import time

import pytest
from playwright.sync_api import Browser

BASE_URL = "http://localhost:8000"
TEST_AUTH_USER = os.getenv("TEST_AUTH_USER", "admin")
TEST_AUTH_PASS = os.getenv("TEST_AUTH_PASS", "plotly")

_started_stack = False


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

    # Already up — nothing to do
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


@pytest.fixture(scope="session")
def base_url():
    return BASE_URL


@pytest.fixture(scope="session")
def auth_state(browser: Browser):
    """Log in once per test session; return the Playwright storage state."""
    context = browser.new_context()
    pg = context.new_page()
    pg.goto(BASE_URL + "/login")
    pg.fill("input[name='username']", TEST_AUTH_USER)
    pg.fill("input[name='password']", TEST_AUTH_PASS)
    pg.click("button[type='submit']")
    pg.wait_for_load_state("networkidle")
    # Verify we landed on the dashboard, not back on /login
    if "/login" in pg.url:
        raise RuntimeError(
            f"Login failed for user '{TEST_AUTH_USER}'. "
            "Check TEST_AUTH_PASS matches AUTH_PASS_HASH in docker-compose.yml."
        )
    state = context.storage_state()
    context.close()
    return state


@pytest.fixture
def page(browser: Browser, auth_state):
    """Function-scoped page pre-loaded with the session auth cookie."""
    context = browser.new_context(storage_state=auth_state)
    pg = context.new_page()
    yield pg
    context.close()
