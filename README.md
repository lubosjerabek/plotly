# Plotly

> Track **Projects → Phases → Milestones & Events**. Stay in sync with Google Calendar via a zero-config ICS feed. Self-hosted, no cloud lock-in.

Built with pure PHP + MySQL + vanilla JS. Runs on a Raspberry Pi, a VPS, a Proxmox VM, or any shared PHP host (tested on Wedos NoLimit).

---

## Why I built this

I was managing a house renovation. Tradespeople, suppliers, an architect — a small army of people who all needed to know what was happening and when. Every time a phase shifted, I was texting updates, forwarding PDFs, and fielding calls from a confused builder who showed up on the wrong day.

What I really wanted was simple: one place to define the plan, and a way for everyone involved to see it in the calendar app they already use — without accounts, without apps, without me having to push updates manually.

Plotly is that tool. You define your project structure once, invite collaborators if you want them to edit, and share an ICS feed URL with anyone who needs read-only visibility. Their calendar updates automatically. When a phase slips, you move it, and everyone's calendar catches up on its own.

It turned out to be useful for more than house renovations — but that's where it started.

---

## Features

- **Hierarchical structure** — Projects → Phases → Milestones & Events, plus project-wide milestones & events that don't belong to any phase
- **Phase dependencies** — shift one phase and all dependent phases cascade automatically
- **Phase descriptions** — rich context notes on each phase
- **Expand / collapse phases** — active phases open by default; past & upcoming collapse to keep the screen tidy
- **Status badges** — Past / Active / Upcoming, auto-calculated from today's date
- **Google Calendar sync** — subscribe to the ICS feed; timed events export with full datetime, all-day events export as date-only; no API keys, no OAuth, no fuss
- **Gantt chart** — visual timeline with Day / Week / Month view modes and a "today" marker
- **Multi-user** — invite-based registration; collaborators can be added per project with viewer or editor roles
- **Password-protected** — bcrypt session auth; per-user ICS tokens
- **Self-hosted** — pure PHP + MySQL, zero Composer dependencies, FTP-deployable

---

## Quickstart

### Docker (recommended)

```bash
git clone https://github.com/yourusername/plotly.git
cd plotly
make build        # rebuild image and start (docker-compose up --build -d)
```

Open `http://localhost:8000` and log in with the credentials set in `docker-compose.yml`:

```yaml
ADMIN_EMAIL: "admin@example.com"
ADMIN_NAME:  "Admin"
ADMIN_PASS:  "your-password-here"
```

The database schema is applied automatically on first start. The entrypoint seeds the admin user on first start if the `users` table is empty. Additional users are added via the invite flow (`/admin/users`).

---

### Wedos / shared PHP hosting

**Fresh install:**

1. FTP all files to your document root
2. Run `schema.sql` once via phpMyAdmin / hosting panel SQL console
3. Open `setup.php` in your browser → enter a password → copy the generated hash
4. Edit `config.php`: paste the hash as `LEGACY_AUTH_PASS_HASH`, fill in DB credentials
5. Visit `https://yoursite.com/migrate.php` — this creates your first admin user from the legacy values and upgrades the schema
6. **Delete `setup.php` and `migrate.php` via FTP**
7. Done

**Upgrading from a single-admin install:**

Run `migrate.php` once. It will create the `users` table and convert the existing `LEGACY_AUTH_PASS_HASH` credentials into the first admin user entry. Then delete `migrate.php`.

---

## Database

| Deployment | How the schema is applied |
|------------|--------------------------|
| Docker | Automatically on first `docker-compose up` via `docker-entrypoint-initdb.d` |
| Wedos / shared hosting | Run `schema.sql` once in phpMyAdmin |

`CREATE TABLE IF NOT EXISTS` keeps restarts idempotent — no migration scripts needed.

---

## Google Calendar Sync

No OAuth. No API keys. No token refresh drama. Just an ICS feed.

1. Open a project page → click **Subscribe** in the topbar
2. Copy the URL shown (it includes a secret `?token=...` parameter)
3. In Google Calendar → **Other calendars → From URL** → paste → **Add calendar**

Google polls the feed on its own schedule (typically every few hours). Phases and milestones appear as all-day events. Events with a specific time export as timed calendar entries. To revoke access, regenerate the token via account settings.

There's also a global feed at `/calendar.ics?token=...` that includes all projects.

---

## Local Development

Run `make` with no arguments to see all available targets:

```
  build         Rebuild the Docker image and start the stack
  up            Start the stack without rebuilding the image
  down          Stop the stack
  reset         Stop the stack and delete all data volumes (clean slate)
  deploy        Copy PHP source into the running container (fast, no rebuild)
  test          Run the full test suite (stack must be running)
  test-file     Run one test file: make test-file FILE=tests/test_ics.py
  check         Deploy current source then run the full test suite
  logs          Tail the app container logs
  shell         Open a shell inside the app container
```

### Typical workflow

**First time / after Dockerfile changes:**

```bash
make build        # full image rebuild + start
```

**Iterating on PHP / templates / lang files** (no rebuild needed):

```bash
# edit index.php, templates/project.php, lang/en.php, …
make deploy       # docker cp changed files into the running container (~1 s)
```

**Deploy and verify in one step:**

```bash
make check        # deploy + run full test suite
```

**Run just the tests you care about:**

```bash
make test-file FILE=tests/test_ics.py
```

### VS Code tasks

All of the above are also available via **Cmd+Shift+P → Tasks: Run Task**:

| Task | Shortcut |
|------|---------|
| Build & Start | Cmd+Shift+B (default build task) |
| Test: Run all | Cmd+Shift+P → Tasks: Run Test Task |
| Test: Run current file | Open any `test_*.py`, then run this task |
| Deploy (fast) | Tasks menu |
| Logs | Tasks menu (opens a dedicated terminal panel) |

---

## Deploying Changes (Wedos / FTP)

Push to `main` — GitHub Actions handles the rest.

```bash
git push origin main
# → .github/workflows/deploy.yml triggers
# → FTP-Deploy-Action uploads changed files to your server
# → config.php is NEVER overwritten (it's in .ftp-deploy-ignore)
```

**Required GitHub Secrets:**

| Secret | Value |
|--------|-------|
| `FTP_SERVER` | Your hosting FTP hostname |
| `FTP_USERNAME` | FTP username |
| `FTP_PASSWORD` | FTP password |
| `FTP_SERVER_DIR` | Document root on the server, e.g. `/web/` |

---

## Tests

The test suite uses Playwright (Python) and runs against the live Docker stack.

```bash
# Install test dependencies once
pip install -r tests/requirements.txt
playwright install chromium

# Start the stack if it isn't already running
make up

# Run all tests
make test

# Run a specific file
make test-file FILE=tests/test_validation.py
```

`conftest.py` spins up `docker-compose` automatically if port 8000 isn't reachable, so you can also just run `pytest tests/ -v` directly and it will start the stack for you.

The session logs in once and reuses the cookie across all tests. Every test that creates data cleans up after itself.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| Blank page / 500 | PHP error | Add `<?php ini_set('display_errors',1);` temporarily to `index.php` |
| All URLs → 404 | mod_rewrite not active or wrong doc root | Check `.htaccess` is in the document root |
| DB connection error | Wrong `DB_HOST` | Wedos may use `mysql.wedos.net` instead of `localhost` |
| Login loop | Wrong password | Re-run `setup.php` to regenerate hash and update `config.php` |
| ICS returns 401/403 | Missing or wrong token | Token is per-user — regenerate via account settings |
| Google Calendar not updating | GCal polls on its own schedule | Wait up to a few hours for first sync |
| `make deploy` fails | Container not running | Run `make up` or `make build` first |

---

## License

MIT
