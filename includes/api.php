<?php

defined('APP_BOOT') or die;

// ── Projects API ──────────────────────────────────────────────────────────────

function api_get_projects(): void
{
    require_auth();
    json_out(get_projects());
}

function api_create_project(): void
{
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

function api_get_project(int $id): void
{
    require_auth();
    assert_project_read($id);
    $project = get_full_project($id);
    if (!$project) not_found();
    // Annotate with current user's write capability
    $project['can_edit'] = can_write_project($id);
    json_out($project);
}

function api_update_project(int $id): void
{
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

function api_delete_project(int $id): void
{
    require_auth();
    if (!is_project_owner($id)) json_out(['detail' => 'Forbidden'], 403);
    $del = pdo()->prepare('DELETE FROM projects WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Phases API ────────────────────────────────────────────────────────────────

function api_create_phase(): void
{
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

function api_update_phase(int $id): void
{
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

function api_delete_phase(int $id): void
{
    require_auth();
    $pid = project_id_for_phase($id);
    if ($pid) assert_project_write($pid);
    $del = pdo()->prepare('DELETE FROM phases WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Milestones API ────────────────────────────────────────────────────────────

function api_create_project_milestone(int $project_id): void
{
    require_auth();
    assert_project_write($project_id);
    $b    = body();
    $stmt = pdo()->prepare('INSERT INTO milestones (project_id, phase_id, name, target_date) VALUES (?,NULL,?,?)');
    $stmt->execute([$project_id, $b['name'] ?? '', $b['target_date'] ?? '']);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'project_id' => $project_id, 'phase_id' => null, 'name' => $b['name'] ?? '', 'target_date' => $b['target_date'] ?? '', 'google_event_id' => null], 201);
}

function api_create_milestone(int $phase_id): void
{
    require_auth();
    $pid = project_id_for_phase($phase_id);
    if ($pid) assert_project_write($pid);
    $b    = body();
    $stmt = pdo()->prepare('INSERT INTO milestones (phase_id, name, target_date) VALUES (?,?,?)');
    $stmt->execute([$phase_id, $b['name'] ?? '', $b['target_date'] ?? '']);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'phase_id' => $phase_id, 'name' => $b['name'] ?? '', 'target_date' => $b['target_date'] ?? '', 'google_event_id' => null], 201);
}

function api_update_milestone(int $id): void
{
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

function api_delete_milestone(int $id): void
{
    require_auth();
    $pid = project_id_for_milestone($id);
    if ($pid) assert_project_write($pid);
    $del = pdo()->prepare('DELETE FROM milestones WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Events API ────────────────────────────────────────────────────────────────

function api_create_project_event(int $project_id): void
{
    require_auth();
    assert_project_write($project_id);
    $b          = body();
    $start_time = (!empty($b['all_day']) ? null : ($b['start_time'] ?? null)) ?: null;
    $end_time   = (!empty($b['all_day']) ? null : ($b['end_time']   ?? null)) ?: null;
    $stmt = pdo()->prepare('INSERT INTO events (project_id, phase_id, name, start_date, end_date, start_time, end_time) VALUES (?,NULL,?,?,?,?,?)');
    $stmt->execute([$project_id, $b['name'] ?? '', $b['start_date'] ?? '', $b['end_date'] ?? '', $start_time, $end_time]);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'project_id' => $project_id, 'phase_id' => null,
              'name' => $b['name'] ?? '', 'start_date' => $b['start_date'] ?? '', 'end_date' => $b['end_date'] ?? '',
              'start_time' => $start_time, 'end_time' => $end_time, 'google_event_id' => null], 201);
}

function api_create_event(int $phase_id): void
{
    require_auth();
    $pid = project_id_for_phase($phase_id);
    if ($pid) assert_project_write($pid);
    $b          = body();
    $start_time = (!empty($b['all_day']) ? null : ($b['start_time'] ?? null)) ?: null;
    $end_time   = (!empty($b['all_day']) ? null : ($b['end_time']   ?? null)) ?: null;
    $stmt = pdo()->prepare('INSERT INTO events (phase_id, name, start_date, end_date, start_time, end_time) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$phase_id, $b['name'] ?? '', $b['start_date'] ?? '', $b['end_date'] ?? '', $start_time, $end_time]);
    $new_id = (int)pdo()->lastInsertId();
    json_out(['id' => $new_id, 'phase_id' => $phase_id, 'name' => $b['name'] ?? '',
              'start_date' => $b['start_date'] ?? '', 'end_date' => $b['end_date'] ?? '',
              'start_time' => $start_time, 'end_time' => $end_time, 'google_event_id' => null], 201);
}

function api_update_event(int $id): void
{
    require_auth();
    $b = body();
    $sel = pdo()->prepare('SELECT * FROM events WHERE id = ?');
    $sel->execute([$id]);
    $existing = $sel->fetch();
    if (!$existing) not_found();

    $pid = project_id_for_event($id);
    if ($pid) assert_project_write($pid);

    // If all_day is explicitly passed, clear times; otherwise use provided/existing values
    if (array_key_exists('all_day', $b)) {
        $start_time = $b['all_day'] ? null : (($b['start_time'] ?? null) ?: null);
        $end_time   = $b['all_day'] ? null : (($b['end_time']   ?? null) ?: null);
    } else {
        $start_time = array_key_exists('start_time', $b) ? ($b['start_time'] ?: null) : $existing['start_time'];
        $end_time   = array_key_exists('end_time', $b) ? ($b['end_time']   ?: null) : $existing['end_time'];
    }

    $upd = pdo()->prepare('UPDATE events SET name=?, start_date=?, end_date=?, start_time=?, end_time=? WHERE id=?');
    $upd->execute([
        $b['name']       ?? $existing['name'],
        $b['start_date'] ?? $existing['start_date'],
        $b['end_date']   ?? $existing['end_date'],
        $start_time,
        $end_time,
        $id,
    ]);
    $sel->execute([$id]);
    $ev = $sel->fetch();
    $ev['id'] = (int)$ev['id'];
    json_out($ev);
}

function api_delete_event(int $id): void
{
    require_auth();
    $pid = project_id_for_event($id);
    if ($pid) assert_project_write($pid);
    $del = pdo()->prepare('DELETE FROM events WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Collaborators API ─────────────────────────────────────────────────────────

function api_get_collaborators(int $project_id): void
{
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

function api_add_collaborator(int $project_id): void
{
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

function api_update_collaborator(int $project_id, int $user_id): void
{
    require_auth();
    if (!is_project_owner($project_id)) json_out(['detail' => 'Forbidden'], 403);
    $b    = body();
    $role = in_array($b['role'] ?? '', ['viewer', 'editor']) ? $b['role'] : 'viewer';
    $upd  = pdo()->prepare('UPDATE project_collaborators SET role = ? WHERE project_id = ? AND user_id = ?');
    $upd->execute([$role, $project_id, $user_id]);
    json_out(['ok' => true]);
}

function api_remove_collaborator(int $project_id, int $user_id): void
{
    require_auth();
    if (!is_project_owner($project_id)) json_out(['detail' => 'Forbidden'], 403);
    $del = pdo()->prepare('DELETE FROM project_collaborators WHERE project_id = ? AND user_id = ?');
    $del->execute([$project_id, $user_id]);
    json_out(['ok' => true]);
}

// ── ICS / Settings API ────────────────────────────────────────────────────────

function api_get_ics_token(): void
{
    require_auth();
    $token = current_user_ics_token();
    $base  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
           . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    json_out(['token' => $token, 'url' => $base . '/calendar.ics?token=' . urlencode($token)]);
}

function api_rotate_ics_token(): void
{
    require_auth();
    $token = bin2hex(random_bytes(32));
    $stmt  = pdo()->prepare('UPDATE users SET ics_token = ? WHERE id = ?');
    $stmt->execute([$token, current_user()['id']]);
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    json_out(['token' => $token, 'url' => $base . '/calendar.ics?token=' . urlencode($token)]);
}

function api_get_admin_settings(): void
{
    require_admin();
    json_out(['session_timeout' => (int)setting_get('session_timeout', '0')]);
}

function api_update_admin_settings(): void
{
    require_admin();
    $b       = body();
    $timeout = (int)($b['session_timeout'] ?? 0);
    $allowed = [0, 3600, 14400, 28800, 86400, 604800];
    if (!in_array($timeout, $allowed, true)) json_out(['detail' => 'invalid value'], 422);
    setting_set('session_timeout', (string)$timeout);
    json_out(['session_timeout' => $timeout]);
}

// ── Profile API ───────────────────────────────────────────────────────────────

function api_get_profile(): void
{
    require_auth();
    $u = current_user();
    json_out(['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']]);
}

function api_change_password(): void
{
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

function api_get_users(): void
{
    require_admin();
    $rows = pdo()->query(
        'SELECT id, email, name, role, is_active, created_at FROM users ORDER BY id'
    )->fetchAll();
    json_out(array_map(fn($r) => array_merge($r, ['id' => (int)$r['id']]), $rows));
}

function api_create_invite(): void
{
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

function api_get_invites(): void
{
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

function api_revoke_invite(int $id): void
{
    require_admin();
    // Mark as expired immediately by setting expires_at to now
    pdo()->prepare('UPDATE invites SET expires_at = NOW() WHERE id = ? AND used_by IS NULL')
         ->execute([$id]);
    json_out(['ok' => true]);
}

function api_create_password_reset(int $user_id): void
{
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

function api_update_user(int $user_id): void
{
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
