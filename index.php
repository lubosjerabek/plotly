<?php
declare(strict_types=1);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => ($_SERVER['HTTPS'] ?? '') === 'on',
    'httponly'  => true,
    'samesite' => 'Lax',
]);
session_start();

// ── Security headers ─────────────────────────────────────────────────────────
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'");
if (($_SERVER['HTTPS'] ?? '') === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

require __DIR__ . '/config.php';

// ── i18n ─────────────────────────────────────────────────────────────────────

function load_lang(): array {
    static $strings = null;
    if ($strings === null) {
        $lang = $_SESSION['lang'] ?? APP_LANG;
        if (!in_array($lang, ['en', 'cs'], true)) $lang = 'en';
        $strings = require __DIR__ . '/lang/' . $lang . '.php';
    }
    return $strings;
}

/** Translate a key, optionally sprintf-formatting with $args */
function t(string $key, mixed ...$args): string {
    $str = load_lang()[$key] ?? $key;
    if (!is_string($str)) return $key;
    return $args ? sprintf($str, ...$args) : $str;
}

/** Return the full translation map as a JSON object for window.T injection */
function t_js(): string {
    $map = load_lang();
    return json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Current active language code */
function current_lang(): string {
    $lang = $_SESSION['lang'] ?? APP_LANG;
    return in_array($lang, ['en', 'cs'], true) ? $lang : 'en';
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function pdo(): PDO {
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

function json_out(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body(): array {
    static $parsed = null;
    if ($parsed === null) {
        $raw    = file_get_contents('php://input');
        $parsed = json_decode($raw ?: '{}', true) ?? [];
    }
    return $parsed;
}

// ── CSRF protection ──────────────────────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): void {
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

function not_found(): void {
    json_out(['detail' => 'Not found'], 404);
}

// ── Auth ───────────────────────────────────────────────────────────────────────

function require_auth(): void {
    if (empty($_SESSION['authed']) || empty($_SESSION['user_id'])) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_out(['detail' => 'Unauthorized'], 401);
        }
        header('Location: /login');
        exit;
    }
}

function require_admin(): void {
    require_auth();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_out(['detail' => 'Forbidden'], 403);
        }
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

/** Return the current authenticated user row (cached for the request) */
function current_user(): array {
    static $user = null;
    if ($user === null) {
        $stmt = pdo()->prepare('SELECT id, email, name, role, ics_token, lang, is_active FROM users WHERE id = ?');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: [];
        if ($user) $user['id'] = (int)$user['id'];
    }
    return $user;
}

function serve_template(string $name, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    require __DIR__ . '/templates/' . $name;
    exit;
}

// ── Project access helpers ─────────────────────────────────────────────────────

/** True if the current user can read this project (owner, collaborator, or admin) */
function can_read_project(int $project_id): bool {
    $user = current_user();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    $uid = $user['id'];
    $stmt = pdo()->prepare(
        'SELECT 1 FROM projects p
         LEFT JOIN project_collaborators pc ON pc.project_id = p.id AND pc.user_id = :uid
         WHERE p.id = :pid AND (p.user_id = :uid2 OR pc.user_id = :uid3)
         LIMIT 1'
    );
    $stmt->execute([':uid' => $uid, ':pid' => $project_id, ':uid2' => $uid, ':uid3' => $uid]);
    return (bool)$stmt->fetch();
}

/** True if the current user can write to this project (owner, editor-collaborator, or admin) */
function can_write_project(int $project_id): bool {
    $user = current_user();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    $uid = $user['id'];
    $stmt = pdo()->prepare(
        'SELECT 1 FROM projects p
         LEFT JOIN project_collaborators pc ON pc.project_id = p.id AND pc.user_id = :uid AND pc.role = \'editor\'
         WHERE p.id = :pid AND (p.user_id = :uid2 OR pc.user_id = :uid3)
         LIMIT 1'
    );
    $stmt->execute([':uid' => $uid, ':pid' => $project_id, ':uid2' => $uid, ':uid3' => $uid]);
    return (bool)$stmt->fetch();
}

/** True if the current user owns this project (or is admin) */
function is_project_owner(int $project_id): bool {
    $user = current_user();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    $stmt = pdo()->prepare('SELECT 1 FROM projects WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$project_id, $user['id']]);
    return (bool)$stmt->fetch();
}

function assert_project_read(int $project_id): void {
    if (!can_read_project($project_id)) json_out(['detail' => 'Forbidden'], 403);
}

function assert_project_write(int $project_id): void {
    if (!can_write_project($project_id)) json_out(['detail' => 'Forbidden'], 403);
}

/** Get project_id for a phase (returns 0 if not found) */
function project_id_for_phase(int $phase_id): int {
    $stmt = pdo()->prepare('SELECT project_id FROM phases WHERE id = ?');
    $stmt->execute([$phase_id]);
    $row = $stmt->fetch();
    return $row ? (int)$row['project_id'] : 0;
}

/** Get project_id for a milestone */
function project_id_for_milestone(int $milestone_id): int {
    $stmt = pdo()->prepare('SELECT project_id, phase_id FROM milestones WHERE id = ?');
    $stmt->execute([$milestone_id]);
    $row = $stmt->fetch();
    if (!$row) return 0;
    if ($row['project_id']) return (int)$row['project_id'];
    if ($row['phase_id'])   return project_id_for_phase((int)$row['phase_id']);
    return 0;
}

/** Get project_id for an event */
function project_id_for_event(int $event_id): int {
    $stmt = pdo()->prepare('SELECT project_id, phase_id FROM events WHERE id = ?');
    $stmt->execute([$event_id]);
    $row = $stmt->fetch();
    if (!$row) return 0;
    if ($row['project_id']) return (int)$row['project_id'];
    if ($row['phase_id'])   return project_id_for_phase((int)$row['phase_id']);
    return 0;
}

// ── ICS token helpers ──────────────────────────────────────────────────────────

/** Find user by ICS token. Returns user row or null. */
function user_by_ics_token(string $token): ?array {
    if ($token === '') return null;
    $stmt = pdo()->prepare('SELECT id, role FROM users WHERE ics_token = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch() ?: null;
    if ($row) $row['id'] = (int)$row['id'];
    return $row;
}

/** Get per-user ICS token for the currently authenticated user */
function current_user_ics_token(): string {
    $u = current_user();
    if (!$u || $u['ics_token'] === '') {
        // Generate on first use
        $token = bin2hex(random_bytes(32));
        $stmt  = pdo()->prepare('UPDATE users SET ics_token = ? WHERE id = ?');
        $stmt->execute([$token, $u['id']]);
        return $token;
    }
    return $u['ics_token'];
}

function require_ics_token(): ?array {
    $token = $_GET['token'] ?? '';
    $user  = user_by_ics_token($token);
    if (!$user) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Forbidden: invalid or missing token.';
        exit;
    }
    return $user;
}

// ── Database helpers ──────────────────────────────────────────────────────────

function get_projects(): array {
    $user = current_user();
    if ($user['role'] === 'admin') {
        $rows = pdo()->query('SELECT id, user_id, name, description FROM projects ORDER BY id')->fetchAll();
    } else {
        $uid  = $user['id'];
        $stmt = pdo()->prepare(
            'SELECT DISTINCT p.id, p.user_id, p.name, p.description
             FROM projects p
             LEFT JOIN project_collaborators pc ON pc.project_id = p.id AND pc.user_id = ?
             WHERE p.user_id = ? OR pc.user_id = ?
             ORDER BY p.id'
        );
        $stmt->execute([$uid, $uid, $uid]);
        $rows = $stmt->fetchAll();
    }
    return array_map(fn($r) => [
        'id'          => (int)$r['id'],
        'user_id'     => $r['user_id'] !== null ? (int)$r['user_id'] : null,
        'name'        => $r['name'],
        'description' => $r['description'],
        'phases'      => [],
    ], $rows);
}

function get_full_project(int $id): ?array {
    $stmt = pdo()->prepare('SELECT id, user_id, name, description FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if (!$project) return null;

    $project['id']      = (int)$project['id'];
    $project['user_id'] = $project['user_id'] !== null ? (int)$project['user_id'] : null;

    // Phases
    $stmt = pdo()->prepare('SELECT id, project_id, name, start_date, end_date, color, description, google_event_id, depends_on_id, depends_on_milestone_id FROM phases WHERE project_id = ? ORDER BY start_date');
    $stmt->execute([$id]);
    $phases = $stmt->fetchAll();

    foreach ($phases as &$phase) {
        $phase['id']                      = (int)$phase['id'];
        $phase['project_id']              = (int)$phase['project_id'];
        $phase['depends_on_id']           = $phase['depends_on_id'] !== null ? (int)$phase['depends_on_id'] : null;
        $phase['depends_on_milestone_id'] = $phase['depends_on_milestone_id'] !== null ? (int)$phase['depends_on_milestone_id'] : null;

        // Milestones
        $ms = pdo()->prepare('SELECT id, phase_id, name, target_date, google_event_id FROM milestones WHERE phase_id = ? ORDER BY target_date');
        $ms->execute([$phase['id']]);
        $phase['milestones'] = array_map(function($m) {
            $m['id']       = (int)$m['id'];
            $m['phase_id'] = (int)$m['phase_id'];
            return $m;
        }, $ms->fetchAll());

        // Events
        $ev = pdo()->prepare('SELECT id, phase_id, name, start_date, end_date, google_event_id FROM events WHERE phase_id = ? ORDER BY start_date');
        $ev->execute([$phase['id']]);
        $phase['events'] = array_map(function($e) {
            $e['id']       = (int)$e['id'];
            $e['phase_id'] = (int)$e['phase_id'];
            return $e;
        }, $ev->fetchAll());
    }
    unset($phase);

    $project['phases'] = $phases;

    // Project-level milestones (not tied to any phase)
    $ms = pdo()->prepare('SELECT id, project_id, name, target_date, google_event_id FROM milestones WHERE project_id = ? AND phase_id IS NULL ORDER BY target_date');
    $ms->execute([$id]);
    $project['milestones'] = array_map(function($m) {
        $m['id']         = (int)$m['id'];
        $m['project_id'] = (int)$m['project_id'];
        $m['phase_id']   = null;
        return $m;
    }, $ms->fetchAll());

    // Project-level events (not tied to any phase)
    $ev = pdo()->prepare('SELECT id, project_id, name, start_date, end_date, google_event_id FROM events WHERE project_id = ? AND phase_id IS NULL ORDER BY start_date');
    $ev->execute([$id]);
    $project['events'] = array_map(function($e) {
        $e['id']         = (int)$e['id'];
        $e['project_id'] = (int)$e['project_id'];
        $e['phase_id']   = null;
        return $e;
    }, $ev->fetchAll());

    // Collaborators
    $co = pdo()->prepare(
        'SELECT u.id, u.name, u.email, pc.role
         FROM project_collaborators pc
         JOIN users u ON u.id = pc.user_id
         WHERE pc.project_id = ?
         ORDER BY pc.added_at'
    );
    $co->execute([$id]);
    $project['collaborators'] = array_map(function($c) {
        $c['id'] = (int)$c['id'];
        return $c;
    }, $co->fetchAll());

    return $project;
}

function shift_dependents(int $phase_id, int $delta_days): void {
    if ($delta_days === 0) return;
    $stmt = pdo()->prepare('SELECT id, start_date, end_date FROM phases WHERE depends_on_id = ?');
    $stmt->execute([$phase_id]);
    $deps = $stmt->fetchAll();
    foreach ($deps as $dep) {
        $new_start = date('Y-m-d', strtotime($dep['start_date'] . ' ' . ($delta_days >= 0 ? "+$delta_days" : "$delta_days") . ' days'));
        $new_end   = date('Y-m-d', strtotime($dep['end_date']   . ' ' . ($delta_days >= 0 ? "+$delta_days" : "$delta_days") . ' days'));
        $upd = pdo()->prepare('UPDATE phases SET start_date = ?, end_date = ? WHERE id = ?');
        $upd->execute([$new_start, $new_end, (int)$dep['id']]);
        shift_dependents((int)$dep['id'], $delta_days);
    }
}

// ── ICS helpers ───────────────────────────────────────────────────────────────

function ics_escape(string $s): string {
    $s = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $s);
    $s = str_replace(["\r\n", "\n", "\r"], '\\n', $s);
    return $s;
}

function build_ics(array $items): string {
    $now   = gmdate('Ymd\THis\Z');
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Plotly//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
    ];
    foreach ($items as $ev) {
        $dtend = date('Ymd', strtotime($ev['end'] . ' +1 day'));
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $ev['uid'];
        $lines[] = 'DTSTAMP:' . $now;
        $lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $ev['start']);
        $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
        $lines[] = 'SUMMARY:' . ics_escape($ev['summary']);
        if (!empty($ev['description'])) {
            $lines[] = 'DESCRIPTION:' . ics_escape($ev['description']);
        }
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", $lines) . "\r\n";
}

function collect_project_ics_items(array $project): array {
    $items = [];
    foreach ($project['phases'] as $phase) {
        $items[] = [
            'uid'         => 'phase-' . $phase['id'] . '@plotly',
            'start'       => $phase['start_date'],
            'end'         => $phase['end_date'],
            'summary'     => '📅 ' . $phase['name'],
            'description' => $phase['description'] ?? '',
        ];
        foreach ($phase['milestones'] as $ms) {
            $items[] = [
                'uid'         => 'ms-' . $ms['id'] . '@plotly',
                'start'       => $ms['target_date'],
                'end'         => $ms['target_date'],
                'summary'     => '🏁 ' . $ms['name'],
                'description' => '',
            ];
        }
        foreach ($phase['events'] as $ev) {
            $items[] = [
                'uid'         => 'ev-' . $ev['id'] . '@plotly',
                'start'       => $ev['start_date'],
                'end'         => $ev['end_date'],
                'summary'     => $ev['name'],
                'description' => '',
            ];
        }
    }
    foreach ($project['milestones'] ?? [] as $ms) {
        $items[] = [
            'uid'         => 'proj-ms-' . $ms['id'] . '@plotly',
            'start'       => $ms['target_date'],
            'end'         => $ms['target_date'],
            'summary'     => '[Milestone] ' . $ms['name'],
            'description' => '',
        ];
    }
    foreach ($project['events'] ?? [] as $ev) {
        $items[] = [
            'uid'         => 'proj-ev-' . $ev['id'] . '@plotly',
            'start'       => $ev['start_date'],
            'end'         => $ev['end_date'],
            'summary'     => $ev['name'],
            'description' => '',
        ];
    }
    return $items;
}

// ── Auth pages ────────────────────────────────────────────────────────────────

function page_login(): void {
    $error = '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Rate limiting: max 10 attempts per IP in 15 minutes
        pdo()->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)')->execute();
        $stmt = pdo()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= 10) {
            $error = t('too_many_attempts');
            serve_template('login.php', ['error' => $error]);
        }

        pdo()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);

        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $stmt  = pdo()->prepare('SELECT id, password_hash, role, lang, is_active FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && $user['is_active'] && password_verify($pass, $user['password_hash'])) {
            // Clear attempts on successful login
            pdo()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            session_regenerate_id(true);
            $_SESSION['authed']    = true;
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_role'] = $user['role'];
            // Restore user's language preference
            if (!empty($user['lang'])) {
                $_SESSION['lang'] = $user['lang'];
            }
            header('Location: /');
            exit;
        }
        $error = t('invalid_credentials');
    }
    serve_template('login.php', ['error' => $error]);
}

function page_logout(): void {
    session_destroy();
    header('Location: /login');
    exit;
}

function page_register(string $token): void {
    // Validate token
    $stmt = pdo()->prepare(
        'SELECT id, label FROM invites WHERE token = ? AND used_by IS NULL AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$token]);
    $invite = $stmt->fetch();

    $error = '';
    if (!$invite) {
        serve_template('register.php', ['error' => t('invite_invalid'), 'invite' => null, 'token' => $token]);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name     = trim($_POST['name']             ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $pass     = $_POST['password']              ?? '';
        $pass2    = $_POST['password_confirm']       ?? '';

        if ($name === '' || $email === '') {
            $error = t('register_fields_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = t('email_invalid');
        } elseif (strlen($pass) < 8) {
            $error = t('password_too_short');
        } elseif ($pass !== $pass2) {
            $error = t('password_mismatch');
        } else {
            // Check email uniqueness
            $chk = pdo()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $error = t('email_taken');
            } else {
                // Create user
                $hash  = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $token_ics = bin2hex(random_bytes(32));
                $ins   = pdo()->prepare(
                    'INSERT INTO users (email, name, password_hash, role, ics_token) VALUES (?, ?, ?, \'user\', ?)'
                );
                $ins->execute([$email, $name, $hash, $token_ics]);
                $new_id = (int)pdo()->lastInsertId();

                // Mark invite used
                $upd = pdo()->prepare('UPDATE invites SET used_by = ? WHERE id = ?');
                $upd->execute([$new_id, (int)$invite['id']]);

                // Log in
                $_SESSION['authed']    = true;
                $_SESSION['user_id']   = $new_id;
                $_SESSION['user_role'] = 'user';
                header('Location: /');
                exit;
            }
        }
    }

    serve_template('register.php', ['error' => $error, 'invite' => $invite, 'token' => $token]);
}

function page_reset_password(string $token): void {
    $stmt = pdo()->prepare(
        'SELECT id, user_id FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    $error   = '';
    $success = false;

    if (!$reset) {
        serve_template('reset_password.php', ['error' => t('reset_token_invalid'), 'valid' => false, 'success' => false]);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pass  = $_POST['password']         ?? '';
        $pass2 = $_POST['password_confirm'] ?? '';

        if (strlen($pass) < 8) {
            $error = t('password_too_short');
        } elseif ($pass !== $pass2) {
            $error = t('password_mismatch');
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
            pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                 ->execute([$hash, (int)$reset['user_id']]);
            pdo()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
                 ->execute([(int)$reset['id']]);
            $success = true;
        }
    }

    serve_template('reset_password.php', ['error' => $error, 'valid' => true, 'success' => $success, 'token' => $token]);
}

// ── Page handlers ─────────────────────────────────────────────────────────────

function page_index(): void {
    require_auth();
    serve_template('index.php');
}

function page_project(int $project_id): void {
    require_auth();
    if (!can_read_project($project_id)) {
        header('Location: /');
        exit;
    }
    serve_template('project.php', ['project_id' => $project_id]);
}

function page_admin_users(): void {
    require_admin();
    serve_template('admin.php');
}

// ── Projects API ──────────────────────────────────────────────────────────────

function api_get_projects(): void {
    require_auth();
    json_out(get_projects());
}

function api_create_project(): void {
    require_auth();
    $b    = body();
    $name = trim($b['name'] ?? '');
    if ($name === '') json_out(['detail' => 'name required'], 422);
    $uid  = current_user()['id'];
    $stmt = pdo()->prepare('INSERT INTO projects (user_id, name, description) VALUES (?, ?, ?)');
    $stmt->execute([$uid, $name, $b['description'] ?? null]);
    $id = (int)pdo()->lastInsertId();
    json_out(['id' => $id, 'user_id' => $uid, 'name' => $name, 'description' => $b['description'] ?? null, 'phases' => []], 201);
}

function api_get_project(int $id): void {
    require_auth();
    assert_project_read($id);
    $project = get_full_project($id);
    if (!$project) not_found();
    // Annotate with current user's write capability
    $project['can_edit'] = can_write_project($id);
    json_out($project);
}

function api_update_project(int $id): void {
    require_auth();
    assert_project_write($id);
    $b = body();
    $stmt = pdo()->prepare('SELECT id FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) not_found();
    $upd = pdo()->prepare('UPDATE projects SET name = ?, description = ? WHERE id = ?');
    $upd->execute([trim($b['name'] ?? ''), $b['description'] ?? null, $id]);
    $project = get_full_project($id);
    $project['can_edit'] = true;
    json_out($project);
}

function api_delete_project(int $id): void {
    require_auth();
    if (!is_project_owner($id)) json_out(['detail' => 'Forbidden'], 403);
    $del = pdo()->prepare('DELETE FROM projects WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Phases API ────────────────────────────────────────────────────────────────

function api_create_phase(): void {
    require_auth();
    $b          = body();
    $project_id = (int)($_GET['project_id'] ?? 0);
    if (!$project_id) json_out(['detail' => 'project_id required'], 422);
    assert_project_write($project_id);

    $stmt = pdo()->prepare(
        'INSERT INTO phases (project_id, name, start_date, end_date, color, description, depends_on_id) VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $project_id,
        $b['name']         ?? '',
        $b['start_date']   ?? '',
        $b['end_date']     ?? '',
        $b['color']        ?? '#cccccc',
        $b['description']  ?? null,
        $b['depends_on_id'] ? (int)$b['depends_on_id'] : null,
    ]);
    $new_id = (int)pdo()->lastInsertId();

    $row = pdo()->prepare('SELECT * FROM phases WHERE id = ?');
    $row->execute([$new_id]);
    $phase = $row->fetch();
    $phase['id']            = (int)$phase['id'];
    $phase['project_id']    = (int)$phase['project_id'];
    $phase['depends_on_id'] = $phase['depends_on_id'] !== null ? (int)$phase['depends_on_id'] : null;
    $phase['milestones']    = [];
    $phase['events']        = [];
    json_out($phase, 201);
}

function api_update_phase(int $id): void {
    require_auth();
    $b = body();

    $sel = pdo()->prepare('SELECT * FROM phases WHERE id = ?');
    $sel->execute([$id]);
    $existing = $sel->fetch();
    if (!$existing) not_found();
    assert_project_write((int)$existing['project_id']);

    $old_start  = $existing['start_date'];
    $new_start  = $b['start_date'] ?? $old_start;
    $delta_days = (int)round((strtotime($new_start) - strtotime($old_start)) / 86400);

    $upd = pdo()->prepare(
        'UPDATE phases SET name=?, start_date=?, end_date=?, color=?, description=?, depends_on_id=?, depends_on_milestone_id=? WHERE id=?'
    );
    $upd->execute([
        $b['name']          ?? $existing['name'],
        $new_start,
        $b['end_date']      ?? $existing['end_date'],
        $b['color']         ?? $existing['color'],
        array_key_exists('description', $b) ? $b['description'] : $existing['description'],
        isset($b['depends_on_id']) && $b['depends_on_id'] ? (int)$b['depends_on_id'] : null,
        isset($b['depends_on_milestone_id']) && $b['depends_on_milestone_id'] ? (int)$b['depends_on_milestone_id'] : null,
        $id,
    ]);

    shift_dependents($id, $delta_days);

    $sel->execute([$id]);
    $phase = $sel->fetch();
    $phase['id']                      = (int)$phase['id'];
    $phase['project_id']              = (int)$phase['project_id'];
    $phase['depends_on_id']           = $phase['depends_on_id'] !== null ? (int)$phase['depends_on_id'] : null;
    $phase['depends_on_milestone_id'] = $phase['depends_on_milestone_id'] !== null ? (int)$phase['depends_on_milestone_id'] : null;

    $ms = pdo()->prepare('SELECT * FROM milestones WHERE phase_id = ? ORDER BY target_date');
    $ms->execute([$id]);
    $phase['milestones'] = array_map(fn($m) => array_merge($m, ['id' => (int)$m['id'], 'phase_id' => (int)$m['phase_id']]), $ms->fetchAll());

    $ev = pdo()->prepare('SELECT * FROM events WHERE phase_id = ? ORDER BY start_date');
    $ev->execute([$id]);
    $phase['events'] = array_map(fn($e) => array_merge($e, ['id' => (int)$e['id'], 'phase_id' => (int)$e['phase_id']]), $ev->fetchAll());

    json_out($phase);
}

function api_delete_phase(int $id): void {
    require_auth();
    $pid = project_id_for_phase($id);
    if ($pid) assert_project_write($pid);
    $del = pdo()->prepare('DELETE FROM phases WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Milestones API ────────────────────────────────────────────────────────────

function api_create_project_milestone(int $project_id): void {
    require_auth();
    assert_project_write($project_id);
    $b    = body();
    $stmt = pdo()->prepare('INSERT INTO milestones (project_id, phase_id, name, target_date) VALUES (?,NULL,?,?)');
    $stmt->execute([$project_id, $b['name'] ?? '', $b['target_date'] ?? '']);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'project_id' => $project_id, 'phase_id' => null, 'name' => $b['name'] ?? '', 'target_date' => $b['target_date'] ?? '', 'google_event_id' => null], 201);
}

function api_create_milestone(int $phase_id): void {
    require_auth();
    $pid = project_id_for_phase($phase_id);
    if ($pid) assert_project_write($pid);
    $b    = body();
    $stmt = pdo()->prepare('INSERT INTO milestones (phase_id, name, target_date) VALUES (?,?,?)');
    $stmt->execute([$phase_id, $b['name'] ?? '', $b['target_date'] ?? '']);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'phase_id' => $phase_id, 'name' => $b['name'] ?? '', 'target_date' => $b['target_date'] ?? '', 'google_event_id' => null], 201);
}

function api_update_milestone(int $id): void {
    require_auth();
    $b = body();

    $sel = pdo()->prepare('SELECT * FROM milestones WHERE id = ?');
    $sel->execute([$id]);
    $existing = $sel->fetch();
    if (!$existing) not_found();

    $pid = project_id_for_milestone($id);
    if ($pid) assert_project_write($pid);

    $old_date  = $existing['target_date'];
    $new_date  = $b['target_date'] ?? $old_date;

    $upd = pdo()->prepare('UPDATE milestones SET target_date = ? WHERE id = ?');
    $upd->execute([$new_date, $id]);

    $delta_days = (int)round((strtotime($new_date) - strtotime($old_date)) / 86400);
    if ($delta_days !== 0) {
        $deps = pdo()->prepare('SELECT id, start_date, end_date FROM phases WHERE depends_on_milestone_id = ?');
        $deps->execute([$id]);
        foreach ($deps->fetchAll() as $dep) {
            $sign      = $delta_days >= 0 ? "+$delta_days" : "$delta_days";
            $new_start = date('Y-m-d', strtotime($dep['start_date'] . " $sign days"));
            $new_end   = date('Y-m-d', strtotime($dep['end_date']   . " $sign days"));
            $upd2 = pdo()->prepare('UPDATE phases SET start_date = ?, end_date = ? WHERE id = ?');
            $upd2->execute([$new_start, $new_end, (int)$dep['id']]);
            shift_dependents((int)$dep['id'], $delta_days);
        }
    }

    $sel->execute([$id]);
    $ms = $sel->fetch();
    $ms['id'] = (int)$ms['id'];
    if (isset($ms['phase_id']))   $ms['phase_id']   = $ms['phase_id']   !== null ? (int)$ms['phase_id']   : null;
    if (isset($ms['project_id'])) $ms['project_id'] = $ms['project_id'] !== null ? (int)$ms['project_id'] : null;
    json_out($ms);
}

function api_delete_milestone(int $id): void {
    require_auth();
    $pid = project_id_for_milestone($id);
    if ($pid) assert_project_write($pid);
    $del = pdo()->prepare('DELETE FROM milestones WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Events API ────────────────────────────────────────────────────────────────

function api_create_project_event(int $project_id): void {
    require_auth();
    assert_project_write($project_id);
    $b    = body();
    $stmt = pdo()->prepare('INSERT INTO events (project_id, phase_id, name, start_date, end_date) VALUES (?,NULL,?,?,?)');
    $stmt->execute([$project_id, $b['name'] ?? '', $b['start_date'] ?? '', $b['end_date'] ?? '']);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'project_id' => $project_id, 'phase_id' => null, 'name' => $b['name'] ?? '', 'start_date' => $b['start_date'] ?? '', 'end_date' => $b['end_date'] ?? '', 'google_event_id' => null], 201);
}

function api_create_event(int $phase_id): void {
    require_auth();
    $pid = project_id_for_phase($phase_id);
    if ($pid) assert_project_write($pid);
    $b    = body();
    $stmt = pdo()->prepare('INSERT INTO events (phase_id, name, start_date, end_date) VALUES (?,?,?,?)');
    $stmt->execute([$phase_id, $b['name'] ?? '', $b['start_date'] ?? '', $b['end_date'] ?? '']);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'phase_id' => $phase_id, 'name' => $b['name'] ?? '', 'start_date' => $b['start_date'] ?? '', 'end_date' => $b['end_date'] ?? '', 'google_event_id' => null], 201);
}

function api_update_event(int $id): void {
    require_auth();
    $b = body();
    $sel = pdo()->prepare('SELECT * FROM events WHERE id = ?');
    $sel->execute([$id]);
    $existing = $sel->fetch();
    if (!$existing) not_found();

    $pid = project_id_for_event($id);
    if ($pid) assert_project_write($pid);

    $upd = pdo()->prepare('UPDATE events SET name=?, start_date=?, end_date=? WHERE id=?');
    $upd->execute([
        $b['name']       ?? $existing['name'],
        $b['start_date'] ?? $existing['start_date'],
        $b['end_date']   ?? $existing['end_date'],
        $id,
    ]);
    $sel->execute([$id]);
    $ev = $sel->fetch();
    $ev['id'] = (int)$ev['id'];
    json_out($ev);
}

function api_delete_event(int $id): void {
    require_auth();
    $pid = project_id_for_event($id);
    if ($pid) assert_project_write($pid);
    $del = pdo()->prepare('DELETE FROM events WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Collaborators API ─────────────────────────────────────────────────────────

function api_get_collaborators(int $project_id): void {
    require_auth();
    assert_project_read($project_id);
    $stmt = pdo()->prepare(
        'SELECT u.id, u.name, u.email, pc.role
         FROM project_collaborators pc
         JOIN users u ON u.id = pc.user_id
         WHERE pc.project_id = ?
         ORDER BY pc.added_at'
    );
    $stmt->execute([$project_id]);
    $rows = array_map(fn($r) => array_merge($r, ['id' => (int)$r['id']]), $stmt->fetchAll());
    json_out($rows);
}

function api_add_collaborator(int $project_id): void {
    require_auth();
    if (!is_project_owner($project_id)) json_out(['detail' => 'Forbidden'], 403);
    $b     = body();
    $email = strtolower(trim($b['email'] ?? ''));
    $role  = in_array($b['role'] ?? '', ['viewer', 'editor']) ? $b['role'] : 'viewer';

    $uq = pdo()->prepare('SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $uq->execute([$email]);
    $target = $uq->fetch();
    if (!$target) json_out(['detail' => t('collaborator_not_found')], 404);

    $target_id = (int)$target['id'];
    // Don't add the project owner as a collaborator
    $proj = pdo()->prepare('SELECT user_id FROM projects WHERE id = ?');
    $proj->execute([$project_id]);
    $p = $proj->fetch();
    if ($p && (int)$p['user_id'] === $target_id) {
        json_out(['detail' => 'User is already the project owner'], 422);
    }

    $ins = pdo()->prepare(
        'INSERT INTO project_collaborators (project_id, user_id, role) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE role = VALUES(role)'
    );
    $ins->execute([$project_id, $target_id, $role]);

    $stmt = pdo()->prepare('SELECT u.id, u.name, u.email, pc.role FROM project_collaborators pc JOIN users u ON u.id = pc.user_id WHERE pc.project_id = ? AND pc.user_id = ?');
    $stmt->execute([$project_id, $target_id]);
    $row = $stmt->fetch();
    $row['id'] = (int)$row['id'];
    json_out($row, 201);
}

function api_update_collaborator(int $project_id, int $user_id): void {
    require_auth();
    if (!is_project_owner($project_id)) json_out(['detail' => 'Forbidden'], 403);
    $b    = body();
    $role = in_array($b['role'] ?? '', ['viewer', 'editor']) ? $b['role'] : 'viewer';
    $upd  = pdo()->prepare('UPDATE project_collaborators SET role = ? WHERE project_id = ? AND user_id = ?');
    $upd->execute([$role, $project_id, $user_id]);
    json_out(['ok' => true]);
}

function api_remove_collaborator(int $project_id, int $user_id): void {
    require_auth();
    if (!is_project_owner($project_id)) json_out(['detail' => 'Forbidden'], 403);
    $del = pdo()->prepare('DELETE FROM project_collaborators WHERE project_id = ? AND user_id = ?');
    $del->execute([$project_id, $user_id]);
    json_out(['ok' => true]);
}

// ── ICS / Settings API ────────────────────────────────────────────────────────

function api_get_ics_token(): void {
    require_auth();
    $token = current_user_ics_token();
    $base  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
           . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    json_out(['token' => $token, 'url' => $base . '/calendar.ics?token=' . urlencode($token)]);
}

function api_rotate_ics_token(): void {
    require_auth();
    $token = bin2hex(random_bytes(32));
    $stmt  = pdo()->prepare('UPDATE users SET ics_token = ? WHERE id = ?');
    $stmt->execute([$token, current_user()['id']]);
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    json_out(['token' => $token, 'url' => $base . '/calendar.ics?token=' . urlencode($token)]);
}

// ── Profile API ───────────────────────────────────────────────────────────────

function api_get_profile(): void {
    require_auth();
    $u = current_user();
    json_out(['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']]);
}

function api_change_password(): void {
    require_auth();
    $b    = body();
    $curr = $b['current_password'] ?? '';
    $new  = $b['new_password']     ?? '';
    if (strlen($new) < 8) json_out(['detail' => t('password_too_short')], 422);

    $u    = current_user();
    $stmt = pdo()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$u['id']]);
    $row  = $stmt->fetch();
    if (!$row || !password_verify($curr, $row['password_hash'])) {
        json_out(['detail' => t('wrong_current_password')], 422);
    }

    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
    pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $u['id']]);
    json_out(['ok' => true]);
}

// ── Admin API ─────────────────────────────────────────────────────────────────

function api_get_users(): void {
    require_admin();
    $rows = pdo()->query(
        'SELECT id, email, name, role, is_active, created_at FROM users ORDER BY id'
    )->fetchAll();
    json_out(array_map(fn($r) => array_merge($r, ['id' => (int)$r['id']]), $rows));
}

function api_create_invite(): void {
    require_admin();
    $b       = body();
    $label   = trim($b['label']   ?? '');
    $days    = max(1, min(365, (int)($b['expires_days'] ?? 7)));
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    $uid     = current_user()['id'];

    $stmt = pdo()->prepare(
        'INSERT INTO invites (token, label, created_by, expires_at) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$token, $label ?: null, $uid, $expires]);
    $id = (int)pdo()->lastInsertId();

    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    json_out([
        'id'         => $id,
        'token'      => $token,
        'label'      => $label,
        'expires_at' => $expires,
        'url'        => $base . '/register/' . $token,
    ], 201);
}

function api_get_invites(): void {
    require_admin();
    $rows = pdo()->query(
        'SELECT i.id, i.token, i.label, i.expires_at, i.created_at,
                u.email AS used_by_email
         FROM invites i
         LEFT JOIN users u ON u.id = i.used_by
         ORDER BY i.created_at DESC'
    )->fetchAll();
    json_out(array_map(fn($r) => array_merge($r, ['id' => (int)$r['id']]), $rows));
}

function api_revoke_invite(int $id): void {
    require_admin();
    // Mark as expired immediately by setting expires_at to now
    pdo()->prepare('UPDATE invites SET expires_at = NOW() WHERE id = ? AND used_by IS NULL')
         ->execute([$id]);
    json_out(['ok' => true]);
}

function api_create_password_reset(int $user_id): void {
    require_admin();
    $stmt = pdo()->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) not_found();

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    pdo()->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
         ->execute([$user_id, $token, $expires]);

    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    json_out(['url' => $base . '/reset-password/' . $token, 'expires_at' => $expires], 201);
}

function api_update_user(int $user_id): void {
    require_admin();
    $b         = body();
    $is_active = isset($b['is_active']) ? ($b['is_active'] ? 1 : 0) : null;
    $role      = isset($b['role']) && in_array($b['role'], ['admin', 'user']) ? $b['role'] : null;

    // Don't let an admin deactivate or demote themselves
    if ($user_id === current_user()['id']) {
        json_out(['detail' => 'Cannot modify your own account'], 422);
    }

    if ($is_active !== null) {
        pdo()->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$is_active, $user_id]);
    }
    if ($role !== null) {
        pdo()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $user_id]);
    }
    json_out(['ok' => true]);
}

// ── ICS feed handlers ─────────────────────────────────────────────────────────

function ics_all(): void {
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: private, no-store');
    $ics_user = require_ics_token();
    if ($ics_user['role'] === 'admin') {
        // Admin token: all projects
        $rows = pdo()->query('SELECT id FROM projects ORDER BY id')->fetchAll();
    } else {
        $uid  = $ics_user['id'];
        $stmt = pdo()->prepare(
            'SELECT DISTINCT p.id FROM projects p
             LEFT JOIN project_collaborators pc ON pc.project_id = p.id AND pc.user_id = ?
             WHERE p.user_id = ? OR pc.user_id = ?
             ORDER BY p.id'
        );
        $stmt->execute([$uid, $uid, $uid]);
        $rows = $stmt->fetchAll();
    }
    $items = [];
    foreach ($rows as $p) {
        $full = get_full_project((int)$p['id']);
        if ($full) $items = array_merge($items, collect_project_ics_items($full));
    }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="plotly-all.ics"');
    echo build_ics($items);
    exit;
}

function ics_project(int $id): void {
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: private, no-store');
    $ics_user = require_ics_token();
    // Check access
    if ($ics_user['role'] !== 'admin') {
        $uid  = $ics_user['id'];
        $stmt = pdo()->prepare(
            'SELECT 1 FROM projects p
             LEFT JOIN project_collaborators pc ON pc.project_id = p.id AND pc.user_id = :uid
             WHERE p.id = :pid AND (p.user_id = :uid2 OR pc.user_id = :uid3) LIMIT 1'
        );
        $stmt->execute([':uid' => $uid, ':pid' => $id, ':uid2' => $uid, ':uid3' => $uid]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            header('Content-Type: text/plain');
            echo 'Forbidden.';
            exit;
        }
    }
    $project = get_full_project($id);
    if (!$project) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Project not found.';
        exit;
    }
    $items = collect_project_ics_items($project);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="project-' . $id . '.ics"');
    echo build_ics($items);
    exit;
}

// ── Router ────────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

// CSRF protection for all state-changing requests
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    verify_csrf();
}

// Login / logout / register / reset (no auth required)
if ($method === 'GET'  && $path === '/login')  { page_login();  }
if ($method === 'POST' && $path === '/login')  { page_login();  }
if ($method === 'GET'  && $path === '/logout') { page_logout(); }
if ($method === 'GET'  && preg_match('#^/register/([a-f0-9]{64})$#', $path, $m))      { page_register($m[1]); }
if ($method === 'POST' && preg_match('#^/register/([a-f0-9]{64})$#', $path, $m))      { page_register($m[1]); }
if ($method === 'GET'  && preg_match('#^/reset-password/([a-f0-9]{64})$#', $path, $m)) { page_reset_password($m[1]); }
if ($method === 'POST' && preg_match('#^/reset-password/([a-f0-9]{64})$#', $path, $m)) { page_reset_password($m[1]); }

// Language switcher (no auth required)
if ($method === 'POST' && $path === '/set-lang') {
    $lang = $_POST['lang'] ?? '';
    if (in_array($lang, ['en', 'cs'], true)) {
        $_SESSION['lang'] = $lang;
        // Persist for authenticated users
        if (!empty($_SESSION['user_id'])) {
            pdo()->prepare('UPDATE users SET lang = ? WHERE id = ?')
                 ->execute([$lang, (int)$_SESSION['user_id']]);
        }
    }
    $back   = $_SERVER['HTTP_REFERER'] ?? '/';
    $parsed = parse_url($back);
    $redirect = isset($parsed['path']) ? $parsed['path'] : '/';
    if (!empty($parsed['query'])) $redirect .= '?' . $parsed['query'];
    header('Location: ' . $redirect);
    exit;
}

// ICS feeds (token-protected, no session required)
if ($method === 'GET' && $path === '/calendar.ics') { ics_all(); }
if ($method === 'GET' && preg_match('#^/project/(\d+)/calendar\.ics$#', $path, $m)) { ics_project((int)$m[1]); }

// Pages
if ($method === 'GET' && $path === '/')                                           { page_index(); }
if ($method === 'GET' && $path === '/admin/users')                                { page_admin_users(); }
if ($method === 'GET' && preg_match('#^/project/(\d+)$#', $path, $m))            { page_project((int)$m[1]); }

// Projects API
if ($method === 'GET'    && $path === '/api/projects')                            { api_get_projects(); }
if ($method === 'POST'   && $path === '/api/projects')                            { api_create_project(); }
if ($method === 'GET'    && preg_match('#^/api/projects/(\d+)$#', $path, $m))    { api_get_project((int)$m[1]); }
if ($method === 'PUT'    && preg_match('#^/api/projects/(\d+)$#', $path, $m))    { api_update_project((int)$m[1]); }
if ($method === 'DELETE' && preg_match('#^/api/projects/(\d+)$#', $path, $m))    { api_delete_project((int)$m[1]); }

// Collaborators API
if ($method === 'GET'    && preg_match('#^/api/projects/(\d+)/collaborators$#', $path, $m))                  { api_get_collaborators((int)$m[1]); }
if ($method === 'POST'   && preg_match('#^/api/projects/(\d+)/collaborators$#', $path, $m))                  { api_add_collaborator((int)$m[1]); }
if ($method === 'PATCH'  && preg_match('#^/api/projects/(\d+)/collaborators/(\d+)$#', $path, $m))            { api_update_collaborator((int)$m[1], (int)$m[2]); }
if ($method === 'DELETE' && preg_match('#^/api/projects/(\d+)/collaborators/(\d+)$#', $path, $m))            { api_remove_collaborator((int)$m[1], (int)$m[2]); }

// Phases API
if ($method === 'POST'   && $path === '/api/phases')                              { api_create_phase(); }
if ($method === 'PUT'    && preg_match('#^/api/phases/(\d+)$#', $path, $m))      { api_update_phase((int)$m[1]); }
if ($method === 'DELETE' && preg_match('#^/api/phases/(\d+)$#', $path, $m))      { api_delete_phase((int)$m[1]); }

// Milestones API
if ($method === 'POST'   && preg_match('#^/api/projects/(\d+)/milestones$#', $path, $m)) { api_create_project_milestone((int)$m[1]); }
if ($method === 'POST'   && preg_match('#^/api/phases/(\d+)/milestones$#', $path, $m))   { api_create_milestone((int)$m[1]); }
if ($method === 'PATCH'  && preg_match('#^/api/milestones/(\d+)$#', $path, $m))          { api_update_milestone((int)$m[1]); }
if ($method === 'DELETE' && preg_match('#^/api/milestones/(\d+)$#', $path, $m))          { api_delete_milestone((int)$m[1]); }

// Events API
if ($method === 'POST'   && preg_match('#^/api/projects/(\d+)/events$#', $path, $m)) { api_create_project_event((int)$m[1]); }
if ($method === 'POST'   && preg_match('#^/api/phases/(\d+)/events$#', $path, $m))   { api_create_event((int)$m[1]); }
if ($method === 'PATCH'  && preg_match('#^/api/events/(\d+)$#', $path, $m))          { api_update_event((int)$m[1]); }
if ($method === 'DELETE' && preg_match('#^/api/events/(\d+)$#', $path, $m))          { api_delete_event((int)$m[1]); }

// ICS / Settings API
if ($method === 'GET'  && $path === '/api/settings/ics-token')          { api_get_ics_token(); }
if ($method === 'POST' && $path === '/api/settings/ics-token/rotate')   { api_rotate_ics_token(); }

// Profile API
if ($method === 'GET'  && $path === '/api/profile')          { api_get_profile(); }
if ($method === 'POST' && $path === '/api/profile/password') { api_change_password(); }

// Admin API
if ($method === 'GET'    && $path === '/api/admin/users')                             { api_get_users(); }
if ($method === 'POST'   && $path === '/api/admin/invites')                           { api_create_invite(); }
if ($method === 'GET'    && $path === '/api/admin/invites')                           { api_get_invites(); }
if ($method === 'DELETE' && preg_match('#^/api/admin/invites/(\d+)$#', $path, $m))   { api_revoke_invite((int)$m[1]); }
if ($method === 'POST'   && preg_match('#^/api/admin/users/(\d+)/reset-password$#', $path, $m)) { api_create_password_reset((int)$m[1]); }
if ($method === 'PATCH'  && preg_match('#^/api/admin/users/(\d+)$#', $path, $m))     { api_update_user((int)$m[1]); }

// Nothing matched
not_found();
