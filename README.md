# Plotly 📌

> Track **Projects → Phases → Milestones & Events**. Stay in sync with Google Calendar via a zero-config ICS feed. Self-hosted, no cloud lock-in.

Built with pure PHP + MySQL + vanilla JS. Runs on a Raspberry Pi, a VPS, a Proxmox VM, or any shared PHP host (tested on Wedos NoLimit).

---

## ✨ Features

- 📁 **Hierarchical structure** — Projects → Phases → Milestones & Events, plus project-wide milestones & events that don't belong to any phase
- 🔗 **Phase dependencies** — shift one phase and all dependent phases cascade automatically
- 📝 **Phase descriptions** — rich context notes on each phase
- 🪗 **Expand / collapse phases** — active phases open by default; past & upcoming collapse to keep the screen tidy
- 🏷️ **Status badges** — Past / Active / Upcoming, auto-calculated from today's date
- 📅 **Google Calendar sync** — subscribe to the ICS feed; timed events export with full datetime, all-day events export as date-only; no API keys, no OAuth, no fuss
- 📊 **Gantt chart** — visual timeline with Day / Week / Month view modes and a "today" marker
- 👥 **Multi-user** — invite-based registration; collaborators can be added per project
- 🔒 **Password-protected** — bcrypt session auth; per-user ICS tokens
- 🚀 **Self-hosted** — pure PHP + MySQL, zero Composer dependencies, FTP-deployable

---

## 🚀 Quickstart

### Docker (recommended)

```bash
git clone https://github.com/yourusername/plotly.git
cd plotly
make build        # rebuild image and start (docker-compose up --build -d)
```

Open `http://localhost:8000` and log in with the credentials set in `docker-compose.yml` (`ADMIN_EMAIL` / `ADMIN_PASS`).

The database schema is applied automatically on first start via `docker-entrypoint-initdb.d`.

---

### Wedos / shared PHP hosting

1. FTP all files to your document root
2. Run `schema.sql` once via phpMyAdmin / hosting panel SQL console
3. Visit `https://yoursite.com/setup.php` → copy the generated hash → paste into `config.php` as `AUTH_PASS_HASH` → set a real `ICS_TOKEN` → fill in DB credentials → **delete `setup.php` via FTP**
4. Done 🎉

---

## 🗄️ Database

| Deployment | How the schema is applied |
|------------|--------------------------|
| Docker | Automatically on first `docker-compose up` via `docker-entrypoint-initdb.d` |
| Wedos / shared hosting | Run `schema.sql` once in phpMyAdmin |

`CREATE TABLE IF NOT EXISTS` keeps restarts idempotent — no migration scripts needed.

---

## 🔑 Authentication

**Docker** — configure the first admin account via environment variables in `docker-compose.yml`:

```yaml
ADMIN_EMAIL: "admin@example.com"
ADMIN_NAME:  "Admin"
ADMIN_PASS:  "your-password-here"
```

The entrypoint seeds the admin user on first start if the `users` table is empty. Additional users are added via the invite flow (`/admin/users`).

**Wedos / FTP** — edit `config.php` directly. Use `setup.php` to generate the bcrypt hash (then delete it), or run locally:

```bash
php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT) . PHP_EOL;"
```

`config.php` is blocked from direct HTTP access via `.htaccess` and is excluded from every automated FTP deploy — it lives on the server and is never overwritten by CI.

---

## 📅 Google Calendar Sync

No OAuth. No API keys. No token refresh drama. Just an ICS feed.

1. Open a project page → click **Subscribe** in the topbar
2. Copy the URL shown (it includes a secret `?token=...` parameter)
3. In Google Calendar → **Other calendars → From URL** → paste → **Add calendar**

Google polls the feed on its own schedule (typically every few hours). Phases and milestones appear as all-day events. Events with a specific time (created with "All-day event" unchecked) export as timed calendar entries. To revoke access, regenerate the token via account settings.

There's also a global feed at `/calendar.ics?token=...` that includes all projects.

---

## 🛠️ Local Development

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

## 🚢 Deploying Changes (Wedos / FTP)

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

## 🧪 Tests

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

## 🛠️ Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| Blank page / 500 | PHP error | Add `<?php ini_set('display_errors',1);` temporarily to `index.php` |
| All URLs → 404 | mod_rewrite not active or wrong doc root | Check `.htaccess` is in the document root |
| DB connection error | Wrong `DB_HOST` | Wedos may use `mysql.wedos.net` instead of `localhost` |
| Login loop | Wrong password | Check `ADMIN_PASS` in `docker-compose.yml` matches what you're typing |
| ICS returns 401/403 | Missing or wrong token | Ensure `?token=` matches the user's ICS token in the DB |
| Google Calendar not updating | GCal polls on its own schedule | Wait up to a few hours for first sync |
| `make deploy` fails | Container not running | Run `make up` or `make build` first |

---

## License

MIT
