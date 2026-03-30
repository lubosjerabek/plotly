<?php
// ── Database ──────────────────────────────────────────────────────────────────
// Values are read from environment variables first (Docker), then fall back to
// the hardcoded defaults (Wedos / direct FTP deployment).
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'your_db_name');
define('DB_USER',    getenv('DB_USER')    ?: 'your_db_user');
define('DB_PASS',    getenv('DB_PASS')    ?: 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// ── Auth ──────────────────────────────────────────────────────────────────────
// Generate AUTH_PASS_HASH by visiting setup.php once (or via Docker exec), then
// either delete setup.php (Wedos) or set the env var (Docker).
define('AUTH_USER',      getenv('AUTH_USER')      ?: 'admin');
define('AUTH_PASS_HASH', getenv('AUTH_PASS_HASH') ?: '$2y$12$CHANGE_ME_run_setup.php_first');

// ── ICS feed token ────────────────────────────────────────────────────────────
// Included in all ICS URLs as ?token=... so Google Calendar can reach the feed
// without a browser session. Set to any long random string.
define('ICS_TOKEN', getenv('ICS_TOKEN') ?: 'change-this-to-a-long-random-secret');
