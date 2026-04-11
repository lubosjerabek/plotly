"""Page Object Model for Plotly Playwright tests."""
from .admin_page import AdminPage
from .base_page import BASE_URL, BasePage
from .dashboard_page import DashboardPage, ProjectCard
from .login_page import LoginPage
from .project_page import PhaseCard, ProjectPage

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
