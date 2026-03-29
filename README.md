# Plotly

Plotly is a fully self-hosted, Python-based project management utility tailored to synchronize beautifully with Google Calendar. Keep track of Projects, Phases, Milestones, and Events—all while ensuring your availability remains accurately reflected across your Google accounts.

## Features

- **Entity Organization**: Hierarchical tracking of Projects -> Phases -> Milestones/Events.
- **Phase Dependencies**: Need to shift a phase? Any dependent phases automatically shift in date, maintaining your project delta accurately.
- **Google Calendar Sync**: Native integration with Google Calendar API. It supports bidirectional or unidirectional pushing of milestones and events to dedicated calendars.
- **Modern UI**: Provided out-of-the-box leveraging Jinja2 templates and high-quality minimal CSS.
- **Self-Hosted First**: Runs delightfully on a Proxmox Virtual Environment (PVE), a VPS, or even a Raspberry Pi. No bloated containers needed. Built on FastAPI and SQLite.

## Quickstart

### Prerequisites
- Python 3.10+
- A Google Cloud API `credentials.json` (Required only for Google Calendar Sync)

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/yourusername/plotly.git
   cd plotly
   ```

2. **Setup your environment**:
   ```bash
   python3 -m venv venv
   source venv/bin/activate
   pip install -r requirements.txt
   ```
   *(Note: if `requirements.txt` is missing, run `pip install fastapi uvicorn sqlalchemy jinja2 python-multipart google-api-python-client google-auth-httplib2 google-auth-oauthlib pydantic`)*

3. **Configure Google API (Optional but recommended)**
   Place your generated `token.json` or `credentials.json` inside the root directory so the `CalendarSyncService` can initialize.

4. **Run the server (Local Python)**:
   ```bash
   uvicorn main:app --reload
   ```

### Docker / PVE Installation (Recommended)

To run Plotly easily mapped out on a Proxmox VM or basically any Docker-ready server:

1. **Clone the repository**:
   ```bash
   git clone https://github.com/yourusername/plotly.git
   cd plotly
   ```

2. **Start the container**:
   ```bash
   docker-compose up -d
   ```

5. Go to `http://YOUR_SERVER_IP:8000/` to test it!

## Database

By default, an SQLite database `pm_app.db` is built dynamically on the first startup. 

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

MIT
