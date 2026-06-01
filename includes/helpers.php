<?php

defined('APP_BOOT') or die;

// ── i18n ─────────────────────────────────────────────────────────────────────

/** Load and cache the translation strings for the current session language */
function load_lang(): array
{
    static $strings = null;
    if ($strings === null) {
        $lang = $_SESSION['lang'] ?? APP_LANG;
        if (!in_array($lang, ['en', 'cs', 'uk'], true)) $lang = 'en';
        $strings = require __DIR__ . '/../lang/' . $lang . '.php';
    }
    return $strings;
}

/** Translate a key, optionally sprintf-formatting with $args */
function t(string $key, mixed ...$args): string
{
    $str = load_lang()[$key] ?? $key;
    if (!is_string($str)) return $key;
    return $args ? sprintf($str, ...$args) : $str;
}

/** Return the full translation map as a JSON object for window.T injection */
function t_js(): string
{
    $map = load_lang();
    return json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Current active language code */
function current_lang(): string
{
    $lang = $_SESSION['lang'] ?? APP_LANG;
    return in_array($lang, ['en', 'cs', 'uk'], true) ? $lang : 'en';
}

// ── Core helpers ──────────────────────────────────────────────────────────────

/** Return the shared PDO connection, creating it on first call */
function pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Send a JSON response with the given HTTP status code and halt execution */
function json_out(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Parse and cache the JSON request body; returns an empty array for non-JSON or empty bodies */
function body(): array
{
    static $parsed = null;
    if ($parsed === null) {
        $raw    = file_get_contents('php://input');
        $parsed = json_decode($raw ?: '{}', true) ?? [];
    }
    return $parsed;
}

/** Send a 404 JSON response and halt execution */
function not_found(): void
{
    json_out(['detail' => 'Not found'], 404);
}

/** Read a value from the settings table; returns $default if the key is absent or the table is missing */
function setting_get(string $key, string $default = ''): string
{
    try {
        $stmt = pdo()->prepare("SELECT value FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (string)$v : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/** Upsert a key/value pair in the settings table */
function setting_set(string $key, string $value): void
{
    pdo()->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?")
         ->execute([$key, $value, $value]);
}

/** Render a template file from the templates/ directory with the given variables and halt execution */
function serve_template(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require __DIR__ . '/../templates/' . $name;
    exit;
}

// ── CSRF protection ───────────────────────────────────────────────────────────

/** Return the session CSRF token, generating one if it does not yet exist */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden `<input>` carrying the CSRF token for inclusion in HTML forms */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verify the CSRF token for the current request and abort with 403 on failure.
 * XHR requests (X-Requested-With: XMLHttpRequest) are trusted without a token
 * because browsers enforce same-origin for custom headers via CORS preflight.
 * Form POST requests must include the synchronizer token in _csrf or X-CSRF-Token.
 */
function verify_csrf(): void
{
    // XHR requests: a custom header is sufficient (browsers block
    // cross-origin custom headers without a CORS preflight)
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        return;
    }
    // Form POSTs: check the synchronizer token
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        echo 'CSRF validation failed.';
        exit;
    }
}
