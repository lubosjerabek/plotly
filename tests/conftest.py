import subprocess
import time
import socket
import pytest
import os
import sys

BASE_URL = "http://localhost:8000"
_server_proc = None


def wait_for_port(host: str, port: int, timeout: float = 15.0) -> bool:
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            with socket.create_connection((host, port), timeout=1):
                return True
        except OSError:
            time.sleep(0.2)
    return False


def pytest_configure(config):
    """Start the FastAPI server before the test session if not already running."""
    global _server_proc

    # Check if the server is already up
    try:
        with socket.create_connection(("localhost", 8000), timeout=1):
            return  # Already running, nothing to do
    except OSError:
        pass

    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    venv_python = os.path.join(project_root, "venv", "bin", "python")
    python = venv_python if os.path.exists(venv_python) else sys.executable

    _server_proc = subprocess.Popen(
        [python, "-m", "uvicorn", "main:app", "--port", "8000"],
        cwd=project_root,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    if not wait_for_port("localhost", 8000, timeout=20):
        _server_proc.terminate()
        raise RuntimeError("FastAPI server did not start in time")


def pytest_unconfigure(config):
    """Shut down the server we started (if we started it)."""
    if _server_proc is not None:
        _server_proc.terminate()
        _server_proc.wait(timeout=5)


@pytest.fixture(scope="session")
def base_url():
    return BASE_URL
