# Plotly

> Track **Projects → Phases → Milestones & Events**. Stay in sync with Google Calendar via a zero-config ICS feed. Self-hosted, no cloud lock-in.

Built with pure PHP + MySQL + vanilla JS. Runs on a Raspberry Pi, a VPS, a Proxmox VM, or any shared PHP host (tested on Wedos NoLimit).

---

## Why I built this

I was managing a house renovation and needed one place to define the plan with a way for everyone involved to see it in their existing calendar app — without accounts, without apps, without me pushing updates manually. Plotly is that tool: define the structure once, invite collaborators to edit, share an ICS feed URL with anyone who needs read-only visibility. When a phase shifts, everyone's calendar catches up automatically.

---

## Features

- **Hierarchical structure** — Projects → Phase Groups → Phases → Milestones & Events, plus project-wide milestones & events
- **Phase dependencies** — shift one phase and all dependent phases cascade automatically
- **Phase groups** — organise related phases into collapsible groups
- **Upcoming milestones** — dashboard panel showing the next milestones across all projects
- **Expand / collapse phases** — active phases open by default; past & upcoming collapse to keep the screen tidy
- **Status badges** — Past / Active / Upcoming, auto-calculated from today's date
- **Google Calendar sync** — subscribe to the ICS feed; no API keys, no OAuth, no fuss
- **Gantt chart** — visual timeline with Day / Week / Month view modes and a "today" marker
- **Multi-user** — invite-based registration; collaborators added per project with viewer or editor roles
- **Localisation** — English and Ukrainian UI
- **Password-protected** — bcrypt session auth; per-user ICS tokens
- **Self-hosted** — pure PHP + MySQL, zero Composer dependencies, FTP-deployable

---

## Quickstart

### Docker (recommended)

```bash
git clone https://github.com/lubosjerabek/plotly.git
cd plotly
make build        # rebuild image and start (docker-compose up --build -d)
```

Open `http://localhost:8000` and log in with the credentials set in `docker-compose.yml`:

```yaml
ADMIN_EMAIL: "admin@example.com"
ADMIN_NAME:  "Admin"
ADMIN_PASS:  "your-password-here"
```

The database schema is applied automatically on first start. Additional users are added via the invite flow (`/admin/users`).

---

### Wedos / shared PHP hosting

**Fresh install:**

1. FTP all files to your document root
2. Run `schema.sql` once via phpMyAdmin / hosting panel SQL console
3. Open `setup.php` in your browser → enter a password → copy the generated hash
4. Edit `config.php`: paste the hash as `LEGACY_AUTH_PASS_HASH`, fill in DB credentials
5. Visit `https://yoursite.com/migrate.php` — creates your first admin user and upgrades the schema
6. **Delete `setup.php` and `migrate.php` via FTP**

**Upgrading from a single-admin install:**

Run `migrate.php` once — it creates the `users` table and converts the existing `LEGACY_AUTH_PASS_HASH` credentials into the first admin user. Then delete `migrate.php`.

---

## Google Calendar Sync

No OAuth. No API keys. Just an ICS feed.

1. Open a project page → click **Subscribe** in the topbar
2. Copy the URL (it includes a secret `?token=...` parameter)
3. In Google Calendar → **Other calendars → From URL** → paste → **Add calendar**

Google polls the feed on its own schedule (typically every few hours). To revoke access, regenerate the token via account settings. There's also a global feed at `/calendar.ics?token=...` that includes all projects.

---

## Local Development

Run `make` with no arguments to see all available targets:

```
  build         Rebuild the Docker image and start the stack
  up            Start the stack without rebuilding the image
  down          Stop the stack
  reset         Stop the stack and delete all data volumes (clean slate)
  deploy        Copy PHP source into the running container (fast, no rebuild)
  check         Deploy current source then run the full test suite
  test          Run the full test suite (stack must be running)
  test-file     Run one test file: make test-file FILE=tests/test_ics.py
  lint          Run all linters (Python + PHP)
  fmt           Lint and auto-format Python test files
  hooks         Install the pre-commit hook (run once after cloning)
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
make deploy       # docker cp changed files into the running container (~1 s)
```

**Deploy and verify in one step:**

```bash
make check        # deploy + run full test suite
```

### Tests

The suite uses Playwright (Python) and runs against the live Docker stack.

```bash
# Set up once after cloning
python3 -m venv venv
pip install -r tests/requirements.txt
playwright install chromium
make hooks        # install pre-commit hook

# Run tests
make test
make test-file FILE=tests/test_validation.py
```

`conftest.py` starts the stack automatically if port 8000 isn't reachable, so you can also run `pytest tests/ -v` directly.

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
