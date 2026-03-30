<?php
declare(strict_types=1);
session_start();

require __DIR__ . '/config.php';

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

function not_found(): void {
    json_out(['detail' => 'Not found'], 404);
}

function require_auth(): void {
    if (empty($_SESSION['authed'])) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_out(['detail' => 'Unauthorized'], 401);
        }
        header('Location: /login');
        exit;
    }
}

function require_ics_token(): void {
    if (($_GET['token'] ?? '') !== ICS_TOKEN) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Forbidden: invalid or missing token.';
        exit;
    }
}

function serve_template(string $name, array $vars = []): void {
    extract($vars);
    require __DIR__ . '/templates/' . $name;
    exit;
}

// ── Database helpers ──────────────────────────────────────────────────────────

function get_projects(): array {
    $rows = pdo()->query('SELECT id, name, description FROM projects ORDER BY id')->fetchAll();
    return array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'description' => $r['description'], 'phases' => []], $rows);
}

function get_full_project(int $id): ?array {
    $stmt = pdo()->prepare('SELECT id, name, description FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if (!$project) return null;

    $project['id'] = (int)$project['id'];

    // Phases
    $stmt = pdo()->prepare('SELECT id, project_id, name, start_date, end_date, color, description, google_event_id, depends_on_id, depends_on_milestone_id FROM phases WHERE project_id = ? ORDER BY start_date');
    $stmt->execute([$id]);
    $phases = $stmt->fetchAll();

    foreach ($phases as &$phase) {
        $phase['id']                     = (int)$phase['id'];
        $phase['project_id']             = (int)$phase['project_id'];
        $phase['depends_on_id']          = $phase['depends_on_id'] !== null ? (int)$phase['depends_on_id'] : null;
        $phase['depends_on_milestone_id']= $phase['depends_on_milestone_id'] !== null ? (int)$phase['depends_on_milestone_id'] : null;

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
        // DTEND is exclusive in ICS for all-day events
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
    // Project-level milestones
    foreach ($project['milestones'] ?? [] as $ms) {
        $items[] = [
            'uid'         => 'proj-ms-' . $ms['id'] . '@plotly',
            'start'       => $ms['target_date'],
            'end'         => $ms['target_date'],
            'summary'     => '[Milestone] ' . $ms['name'],
            'description' => '',
        ];
    }
    // Project-level events
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';
        if ($user === AUTH_USER && password_verify($pass, AUTH_PASS_HASH)) {
            $_SESSION['authed'] = true;
            header('Location: /');
            exit;
        }
        $error = 'Invalid username or password.';
    }
    serve_template('login.php', ['error' => $error]);
}

function page_logout(): void {
    session_destroy();
    header('Location: /login');
    exit;
}

// ── Page handlers ─────────────────────────────────────────────────────────────

function page_index(): void {
    require_auth();
    serve_template('index.php');
}

function page_project(int $project_id): void {
    require_auth();
    serve_template('project.php', ['project_id' => $project_id]);
}

// ── Projects API ──────────────────────────────────────────────────────────────

function api_get_projects(): void {
    require_auth();
    json_out(get_projects());
}

function api_create_project(): void {
    require_auth();
    $b = body();
    $name = trim($b['name'] ?? '');
    if ($name === '') json_out(['detail' => 'name required'], 422);
    $stmt = pdo()->prepare('INSERT INTO projects (name, description) VALUES (?, ?)');
    $stmt->execute([$name, $b['description'] ?? null]);
    $id = (int)pdo()->lastInsertId();
    json_out(['id' => $id, 'name' => $name, 'description' => $b['description'] ?? null, 'phases' => []], 201);
}

function api_get_project(int $id): void {
    require_auth();
    $project = get_full_project($id);
    if (!$project) not_found();
    json_out($project);
}

function api_update_project(int $id): void {
    require_auth();
    $b = body();
    $stmt = pdo()->prepare('SELECT id FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) not_found();
    $upd = pdo()->prepare('UPDATE projects SET name = ?, description = ? WHERE id = ?');
    $upd->execute([trim($b['name'] ?? ''), $b['description'] ?? null, $id]);
    json_out(get_full_project($id));
}

function api_delete_project(int $id): void {
    require_auth();
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
    $phase['id']           = (int)$phase['id'];
    $phase['project_id']   = (int)$phase['project_id'];
    $phase['depends_on_id']= $phase['depends_on_id'] !== null ? (int)$phase['depends_on_id'] : null;
    $phase['milestones']   = [];
    $phase['events']       = [];
    json_out($phase, 201);
}

function api_update_phase(int $id): void {
    require_auth();
    $b = body();

    $sel = pdo()->prepare('SELECT * FROM phases WHERE id = ?');
    $sel->execute([$id]);
    $existing = $sel->fetch();
    if (!$existing) not_found();

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
    $phase['id']                     = (int)$phase['id'];
    $phase['project_id']             = (int)$phase['project_id'];
    $phase['depends_on_id']          = $phase['depends_on_id'] !== null ? (int)$phase['depends_on_id'] : null;
    $phase['depends_on_milestone_id']= $phase['depends_on_milestone_id'] !== null ? (int)$phase['depends_on_milestone_id'] : null;

    // Attach milestones + events
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
    $del = pdo()->prepare('DELETE FROM phases WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Milestones API ────────────────────────────────────────────────────────────

function api_create_project_milestone(int $project_id): void {
    require_auth();
    $b    = body();
    $stmt = pdo()->prepare('INSERT INTO milestones (project_id, phase_id, name, target_date) VALUES (?,NULL,?,?)');
    $stmt->execute([$project_id, $b['name'] ?? '', $b['target_date'] ?? '']);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'project_id' => $project_id, 'phase_id' => null, 'name' => $b['name'] ?? '', 'target_date' => $b['target_date'] ?? '', 'google_event_id' => null], 201);
}

function api_create_milestone(int $phase_id): void {
    require_auth();
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

    $old_date  = $existing['target_date'];
    $new_date  = $b['target_date'] ?? $old_date;

    $upd = pdo()->prepare('UPDATE milestones SET target_date = ? WHERE id = ?');
    $upd->execute([$new_date, $id]);

    // Cascade: shift all phases that depend on this milestone
    $delta_days = (int)round((strtotime($new_date) - strtotime($old_date)) / 86400);
    if ($delta_days !== 0) {
        $deps = pdo()->prepare('SELECT id, start_date, end_date FROM phases WHERE depends_on_milestone_id = ?');
        $deps->execute([$id]);
        foreach ($deps->fetchAll() as $dep) {
            $sign       = $delta_days >= 0 ? "+$delta_days" : "$delta_days";
            $new_start  = date('Y-m-d', strtotime($dep['start_date'] . " $sign days"));
            $new_end    = date('Y-m-d', strtotime($dep['end_date']   . " $sign days"));
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
    $del = pdo()->prepare('DELETE FROM milestones WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Events API ────────────────────────────────────────────────────────────────

function api_create_project_event(int $project_id): void {
    require_auth();
    $b    = body();
    $stmt = pdo()->prepare('INSERT INTO events (project_id, phase_id, name, start_date, end_date) VALUES (?,NULL,?,?,?)');
    $stmt->execute([$project_id, $b['name'] ?? '', $b['start_date'] ?? '', $b['end_date'] ?? '']);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'project_id' => $project_id, 'phase_id' => null, 'name' => $b['name'] ?? '', 'start_date' => $b['start_date'] ?? '', 'end_date' => $b['end_date'] ?? '', 'google_event_id' => null], 201);
}

function api_create_event(int $phase_id): void {
    require_auth();
    $b    = body();
    $stmt = pdo()->prepare('INSERT INTO events (phase_id, name, start_date, end_date) VALUES (?,?,?,?)');
    $stmt->execute([$phase_id, $b['name'] ?? '', $b['start_date'] ?? '', $b['end_date'] ?? '']);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'phase_id' => $phase_id, 'name' => $b['name'] ?? '', 'start_date' => $b['start_date'] ?? '', 'end_date' => $b['end_date'] ?? '', 'google_event_id' => null], 201);
}

function api_delete_event(int $id): void {
    require_auth();
    $del = pdo()->prepare('DELETE FROM events WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── ICS feed handlers ─────────────────────────────────────────────────────────

function ics_all(): void {
    require_ics_token();
    $projects = get_projects();
    $items = [];
    foreach ($projects as $p) {
        $full = get_full_project((int)$p['id']);
        if ($full) $items = array_merge($items, collect_project_ics_items($full));
    }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="plotly-all.ics"');
    echo build_ics($items);
    exit;
}

function ics_project(int $id): void {
    require_ics_token();
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

// Login / logout (no auth required)
if ($method === 'GET'  && $path === '/login')  { page_login();  }
if ($method === 'POST' && $path === '/login')  { page_login();  }
if ($method === 'GET'  && $path === '/logout') { page_logout(); }

// ICS feeds (token-protected, no session required)
if ($method === 'GET' && $path === '/calendar.ics') { ics_all(); }
if ($method === 'GET' && preg_match('#^/project/(\d+)/calendar\.ics$#', $path, $m)) { ics_project((int)$m[1]); }

// Pages
if ($method === 'GET' && $path === '/')                                           { page_index(); }
if ($method === 'GET' && preg_match('#^/project/(\d+)$#', $path, $m))            { page_project((int)$m[1]); }

// Projects API
if ($method === 'GET'    && $path === '/api/projects')                            { api_get_projects(); }
if ($method === 'POST'   && $path === '/api/projects')                            { api_create_project(); }
if ($method === 'GET'    && preg_match('#^/api/projects/(\d+)$#', $path, $m))    { api_get_project((int)$m[1]); }
if ($method === 'PUT'    && preg_match('#^/api/projects/(\d+)$#', $path, $m))    { api_update_project((int)$m[1]); }
if ($method === 'DELETE' && preg_match('#^/api/projects/(\d+)$#', $path, $m))    { api_delete_project((int)$m[1]); }

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
if ($method === 'DELETE' && preg_match('#^/api/events/(\d+)$#', $path, $m))          { api_delete_event((int)$m[1]); }

// Nothing matched
not_found();
