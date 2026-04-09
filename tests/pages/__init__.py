"""Page Object Model for Plotly Playwright tests."""
from .base_page import BASE_URL, BasePage
from .login_page import LoginPage
from .dashboard_page import DashboardPage, ProjectCard
from .project_page import ProjectPage, PhaseCard
from .admin_page import AdminPage

__all__ = [
    "BASE_URL",
    "BasePage",
    "LoginPage",
    "DashboardPage",
    "ProjectCard",
    "ProjectPage",
    "PhaseCard",
    "AdminPage",
]
