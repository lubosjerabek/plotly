# Plotly

A self-hosted project management tool for tracking **Projects → Phases → Milestones / Events**, with automatic sync to Google Calendar. Built on FastAPI + SQLite + Jinja2.

---

## Features

- **Hierarchical structure** — Projects contain Phases; each Phase holds Milestones and Events.
- **Phase dependencies** — Shifting one phase cascades date changes to all dependent phases automatically.
- **Phase descriptions** — Add rich context notes to each phase.
- **Expand/collapse phases** — Active phases open by default; past and upcoming phases collapse to save screen space.
- **Status badges** — Phases are automatically marked Past / Active / Upcoming based on today's date.
- **Google Calendar sync** — Every phase, milestone, and event is pushed to your primary Google Calendar on create/update/delete.
- **Gantt chart** — Visual project timeline with Day / Week / Month view modes.
- **Self-hosted** — Runs on a Raspberry Pi, VPS, or Proxmox VM. No cloud lock-in.

---

## Quickstart

### Prerequisites

- Python 3.10+
- A Google Cloud project with the Calendar API enabled *(required only for Google Calendar sync — the app works in mock mode without it)*

### Installation

```bash
git clone https://github.com/yourusername/plotly.git
cd plotly

python3 -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate
pip install -r requirements.txt

uvicorn main:app --reload
```

Open `http://localhost:8000` in your browser.

### Docker / Proxmox

```bash
git clone https://github.com/yourusername/plotly.git
cd plotly
docker-compose up -d
```

Open `http://YOUR_SERVER_IP:8000`.

---

## Google Calendar Setup

The app syncs phases, milestones, and events to your **primary** Google Calendar. Without credentials the app runs fine — API calls are mocked and logged to the console instead.

### How it works

On startup, `sync.py` looks for a `token.json` file in the project root. If found, it connects to the Google Calendar API. If not, it falls back to mock mode (prints `Mock Sync / Mock Delete` to the console).

There are two credential files involved:

| File | What it is | How you get it |
|------|-----------|----------------|
| `credentials.json` | Your OAuth2 client ID & secret from Google Cloud | Downloaded from Google Cloud Console |
| `token.json` | Your personal OAuth2 access + refresh token | Generated once by running a script |

Both files are excluded from git via `.gitignore`.

---

### Step 1 — Create a Google Cloud project

1. Go to [console.cloud.google.com](https://console.cloud.google.com/).
2. Click the project dropdown at the top → **New Project**.
3. Give it a name (e.g. `plotly-sync`) and click **Create**.

---

### Step 2 — Enable the Google Calendar API

1. In your new project, go to **APIs & Services → Library**.
2. Search for **Google Calendar API** and click it.
3. Click **Enable**.

---

### Step 3 — Configure the OAuth consent screen

1. Go to **APIs & Services → OAuth consent screen**.
2. Select **External** (or **Internal** if you're on a Google Workspace organisation).
3. Fill in:
   - **App name** — anything, e.g. `Plotly`
   - **User support email** — your email
   - **Developer contact information** — your email
4. Click **Save and Continue**.
5. On the **Scopes** step, click **Add or Remove Scopes**, search for `calendar.events`, and add:
   ```
   https://www.googleapis.com/auth/calendar.events
   ```
6. Click **Save and Continue**.
7. On the **Test users** step, click **Add Users** and add your own Google account.
   *(This is required while the app is in "Testing" status — skip if you chose Internal.)*
8. Click **Save and Continue** then **Back to Dashboard**.

---

### Step 4 — Create OAuth2 credentials

1. Go to **APIs & Services → Credentials**.
2. Click **Create Credentials → OAuth client ID**.
3. Application type: **Desktop app**.
4. Name it anything (e.g. `Plotly Desktop`).
5. Click **Create**.
6. In the dialog that appears, click **Download JSON**.
7. Rename the downloaded file to `credentials.json` and place it in the **project root** (next to `main.py`).

---

### Step 5 — Generate token.json (one-time)

This step opens a browser window, asks you to log in with your Google account, and writes a `token.json` file to the project root. You only do this once.

With your virtualenv active and `credentials.json` in the project root:

```bash
python - <<'EOF'
from google_auth_oauthlib.flow import InstalledAppFlow
SCOPES = ['https://www.googleapis.com/auth/calendar.events']
flow = InstalledAppFlow.from_client_secrets_file('credentials.json', SCOPES)
creds = flow.run_local_server(port=0)
with open('token.json', 'w') as f:
    f.write(creds.to_json())
print("token.json created — you can now start the server.")
EOF
```

A browser window will open. Log in with the Google account whose calendar you want to sync to, and click **Allow** when prompted.

---

### Step 6 — Start (or restart) the server

```bash
uvicorn main:app --reload
```

You should now see the **Auto-synced** indicator in the project header turn meaningful — phases, milestones, and events will appear in your Google Calendar the moment they are created.

---

### Token refresh and expiry

The `token.json` file includes a **refresh token**, so it stays valid indefinitely as long as you don't revoke access. If it ever stops working:

1. Delete `token.json`.
2. Re-run the Step 5 script.

To revoke access entirely, visit [myaccount.google.com/permissions](https://myaccount.google.com/permissions) and remove the Plotly app.

---

### Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| Console shows `Mock Sync: …` | `token.json` not found | Complete steps 4–5 |
| `FileNotFoundError: credentials.json` | Script can't find the file | Make sure you're in the project root when running the script |
| `Access blocked: app has not been verified` | Consent screen is in Testing mode but your account isn't a test user | Add your account in Step 3 → Test users |
| `Token has been expired or revoked` | Token was manually revoked or has been inactive for 6 months | Delete `token.json` and re-run Step 5 |
| Events sync but go to wrong calendar | App always syncs to the **primary** calendar | There is currently no calendar selector; to change this you would modify `calendarId='primary'` in `sync.py` |

---

## Running Tests

The test suite uses Playwright and requires the server to be running on port 8000.

```bash
# Install test dependencies (once)
pip install pytest playwright pytest-playwright
playwright install chromium

# Run all tests (server auto-starts if not already running)
pytest tests/test_ui.py -v
```

---

## Database

SQLite (`pm_app.db`) is created automatically on first start. No setup required. Schema migrations (e.g. new columns) are applied automatically at startup.

To reset the database entirely:

```bash
rm pm_app.db
uvicorn main:app --reload   # re-creates the schema from scratch
```

---

## License

MIT
