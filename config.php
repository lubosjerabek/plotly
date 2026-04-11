<?php

// ── Database ──────────────────────────────────────────────────────────────────
// Values are read from environment variables first (Docker), then fall back to
// the hardcoded defaults (Wedos / direct FTP deployment).
define('DB_HOST', getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME', getenv('DB_NAME')    ?: 'your_db_name');
define('DB_USER', getenv('DB_USER')    ?: 'your_db_user');
define('DB_PASS', getenv('DB_PASS')    ?: 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// ── Auth ──────────────────────────────────────────────────────────────────────
// Authentication is now DB-based (users table).
// Run migrate.php once to create the first admin user from the legacy values
// below, then you can remove them.  They are only used by migrate.php.
define('LEGACY_AUTH_USER', getenv('AUTH_USER')      ?: 'admin');
define('LEGACY_AUTH_PASS_HASH', getenv('AUTH_PASS_HASH') ?: '$2y$12$CHANGE_ME_run_setup.php_first');

// ── ICS feed token ────────────────────────────────────────────────────────────
// Legacy fallback — only used by migrate.php to seed the first admin's ICS
// token from the old global token.  New per-user tokens are stored in users.ics_token.
define('ICS_TOKEN', getenv('ICS_TOKEN') ?: 'change-this-to-a-long-random-secret');

// ── Language ───────────────────────────────────────────────────────────────────
// Default UI language. Supported: 'en' | 'cs'.
// Per-user language is stored in the DB (users.lang) and loaded into the session
// on login.  This constant is the fallback for unauthenticated pages.
define('APP_LANG', in_array(getenv('APP_LANG'), ['en','cs']) ? getenv('APP_LANG') : 'en');
