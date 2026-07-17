# Plotly Coding Rules

These rules enforce development and testing standards for the Plotly codebase. Antigravity agents working on this repository must adhere to these guidelines at all times.

## Testing Guidelines

### 1. Avoid Hardcoded Test Data
- Do not use hardcoded strings for names of projects, phases, groups, milestones, or events inside test cases.
- Always use randomized generator functions imported from [conftest.py](file:///Users/lubosjerabek/Documents/git/plotly/tests/conftest.py), such as:
  - `rand_project_name()`
  - `rand_phase_name()`
  - `rand_group_name()`
  - `rand_milestone_name()`
  - `rand_event_name()`
  - `rand_future_date()` / `rand_date_range()`

### 2. Adhere to Page Object Model (POM) Best Practices
- Never use raw CSS or XPath selector strings directly in test cases (`tests/test_*.py`).
- Define all DOM selectors as class constants or attributes within the page object helper classes in [tests/pages/](file:///Users/lubosjerabek/Documents/git/plotly/tests/pages/).
- Reference these class attributes (e.g. `ProjectPage.VIEW_GROUPED_BTN`) within both page helper methods and assertions in test files.
- Ensure helper methods are defined on page object models for all common interactions (e.g. switching views, filling modals).
