<?php

defined('APP_BOOT') or die;

// ── Projects API ──────────────────────────────────────────────────────────────

/** GET /api/projects — return all projects visible to the current user */
function api_get_projects(): void
{
    require_auth();
    json_out(get_projects());
}

/**
 * POST /api/projects — create a new project owned by the current user.
 * Body: { name: string (required), description?: string }
 * Returns 201 with the new project object.
 */
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

/** GET /api/projects/{id} — return the fully hydrated project tree, annotated with can_edit for the current user */
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

/** PUT /api/projects/{id} — update the project name and description; write access required */
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

/** DELETE /api/projects/{id} — delete the project; only the project owner (or admin) may do this */
function api_delete_project(int $id): void
{
    require_auth();
    if (!is_project_owner($id)) json_out(['detail' => 'Forbidden'], 403);
    $del = pdo()->prepare('DELETE FROM projects WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

// ── Phase Groups API ──────────────────────────────────────────────────────────

/**
 * POST /api/phase-groups?project_id= — create a phase group for the given project.
 * Body: { name: string (required), color?: string, description?: string, sort_order?: int }
 * Returns 201 with the new group object (phases array is empty on creation).
 */
function api_create_phase_group(): void
{
    require_auth();
    $b          = body();
    $project_id = (int)($_GET['project_id'] ?? 0);
    if (!$project_id) json_out(['detail' => 'project_id required'], 422);
    assert_project_write($project_id);

    $name = trim($b['name'] ?? '');
    if ($name === '') json_out(['detail' => 'name required'], 422);

    $new_id = create_phase_group($project_id, [
        'name'        => $name,
        'color'       => $b['color']       ?? '#cccccc',
        'description' => $b['description'] ?? null,
        'sort_order'  => $b['sort_order']  ?? 0,
    ]);

    $row = pdo()->prepare('SELECT id, project_id, name, color, description, sort_order FROM phase_groups WHERE id = ?');
    $row->execute([$new_id]);
    $group = $row->fetch();
    $group['id']         = (int)$group['id'];
    $group['project_id'] = (int)$group['project_id'];
    $group['sort_order'] = (int)$group['sort_order'];
    $group['phases']     = [];
    json_out($group, 201);
}

/** PUT /api/phase-groups/{id} — update whichever fields are provided for the phase group */
function api_update_phase_group(int $id): void
{
    require_auth();
    $pid = project_id_for_group($id);
    if (!$pid) not_found();
    assert_project_write($pid);

    $b = body();
    update_phase_group($id, $b);

    $row = pdo()->prepare('SELECT id, project_id, name, color, description, sort_order FROM phase_groups WHERE id = ?');
    $row->execute([$id]);
    $group = $row->fetch();
    if (!$group) not_found();
    $group['id']         = (int)$group['id'];
    $group['project_id'] = (int)$group['project_id'];
    $group['sort_order'] = (int)$group['sort_order'];
    json_out($group);
}

/** DELETE /api/phase-groups/{id} — delete the group; member phases are ungrouped, not deleted */
function api_delete_phase_group(int $id): void
{
    require_auth();
    $pid = project_id_for_group($id);
    if (!$pid) not_found();
    assert_project_write($pid);

    delete_phase_group($id);
    json_out(['ok' => true]);
}

// ── Phases API ────────────────────────────────────────────────────────────────

/**
 * POST /api/phases?project_id= — create a new phase for the given project.
 * Body: { name, start_date, end_date, color?, description?, group_id?, depends_on_id? }
 * Validates that group_id, if provided, belongs to the same project.
 * Returns 201 with the new phase object (milestones and events arrays are empty).
 */
function api_create_phase(): void
{
    require_auth();
    $b          = body();
    $project_id = (int)($_GET['project_id'] ?? 0);
    if (!$project_id) json_out(['detail' => 'project_id required'], 422);
    assert_project_write($project_id);

    // Validate group_id belongs to this project (if provided)
    $group_id = null;
    if (!empty($b['group_id'])) {
        $group_id = (int)$b['group_id'];
        if (project_id_for_group($group_id) !== $project_id) {
            json_out(['detail' => 'Invalid group_id'], 422);
        }
    }

    $stmt = pdo()->prepare(
        'INSERT INTO phases (project_id, group_id, name, start_date, end_date, color, description, depends_on_id) VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $project_id,
        $group_id,
        $b['name']         ?? '',
        $b['start_date']   ?? '',
        $b['end_date']     ?? '',
        $b['color']        ?? '#cccccc',
        $b['description']  ?? null,
        !empty($b['depends_on_id']) ? (int)$b['depends_on_id'] : null,
    ]);
    $new_id = (int)pdo()->lastInsertId();

    $row = pdo()->prepare('SELECT * FROM phases WHERE id = ?');
    $row->execute([$new_id]);
    $phase = $row->fetch();
    $phase['id']            = (int)$phase['id'];
    $phase['project_id']    = (int)$phase['project_id'];
    $phase['group_id']      = $phase['group_id'] !== null ? (int)$phase['group_id'] : null;
    $phase['depends_on_id'] = $phase['depends_on_id'] !== null ? (int)$phase['depends_on_id'] : null;
    $phase['milestones']    = [];
    $phase['events']        = [];
    json_out($phase, 201);
}

/**
 * PUT /api/phases/{id} — update a phase and cascade any start_date delta to dependent phases.
 * Passing group_id: null ungrouped the phase; omitting group_id preserves the existing group.
 * Date shift is propagated recursively through the depends_on_id chain via shift_dependents().
 */
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

    // Resolve group_id: explicit null clears it; absent key keeps existing
    if (array_key_exists('group_id', $b)) {
        if ($b['group_id'] === null || $b['group_id'] === '' || $b['group_id'] === 0) {
            $group_id = null;
        } else {
            $group_id = (int)$b['group_id'];
            if (project_id_for_group($group_id) !== (int)$existing['project_id']) {
                json_out(['detail' => 'Invalid group_id'], 422);
            }
        }
    } else {
        $group_id = $existing['group_id'] !== null ? (int)$existing['group_id'] : null;
    }

    $upd = pdo()->prepare(
        'UPDATE phases SET name=?, start_date=?, end_date=?, color=?, description=?, group_id=?, depends_on_id=?, depends_on_milestone_id=? WHERE id=?'
    );
    $upd->execute([
        $b['name']          ?? $existing['name'],
        $new_start,
        $b['end_date']      ?? $existing['end_date'],
        $b['color']         ?? $existing['color'],
        array_key_exists('description', $b) ? $b['description'] : $existing['description'],
        $group_id,
        isset($b['depends_on_id']) && $b['depends_on_id'] ? (int)$b['depends_on_id'] : null,
        isset($b['depends_on_milestone_id']) && $b['depends_on_milestone_id'] ? (int)$b['depends_on_milestone_id'] : null,
        $id,
    ]);

    shift_dependents($id, $delta_days);

    $sel->execute([$id]);
    $phase = $sel->fetch();
    $phase['id']                      = (int)$phase['id'];
    $phase['project_id']              = (int)$phase['project_id'];
    $phase['group_id']                = $phase['group_id'] !== null ? (int)$phase['group_id'] : null;
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

/** DELETE /api/phases/{id} — delete the phase along with its milestones and events (via DB cascade) */
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

/** POST /api/projects/{id}/milestones — create a project-level milestone not tied to any phase */
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

/** POST /api/phases/{id}/milestones — create a milestone attached to a specific phase */
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

/**
 * PATCH /api/milestones/{id} — update a milestone's name and/or target_date.
 * When target_date changes, shifts all phases that depend on this milestone
 * via depends_on_milestone_id, then cascades further via shift_dependents().
 */
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
    $new_name  = isset($b['name']) ? trim($b['name']) : $existing['name'];
    if ($new_name === '') $new_name = $existing['name'];

    $upd = pdo()->prepare('UPDATE milestones SET name = ?, target_date = ? WHERE id = ?');
    $upd->execute([$new_name, $new_date, $id]);

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

/** DELETE /api/milestones/{id} — delete a milestone */
function api_delete_milestone(int $id): void
{
    require_auth();
    $pid = project_id_for_milestone($id);
    if ($pid) assert_project_write($pid);
    $del = pdo()->prepare('DELETE FROM milestones WHERE id = ?');
    $del->execute([$id]);
    json_out(['ok' => true]);
}

/** GET /api/milestones/upcoming — return up to 15 milestones due from 7 days ago onwards, scoped to the current user's visible projects */
function api_get_upcoming_milestones(): void
{
    require_auth();
    $user = current_user();

    $select = 'SELECT DISTINCT m.id, m.name, m.target_date,
                      DATEDIFF(m.target_date, CURDATE()) AS days_until,
                      COALESCE(m.project_id, ph.project_id) AS project_id,
                      p.name AS project_name
               FROM milestones m
               LEFT JOIN phases ph ON ph.id = m.phase_id
               JOIN projects p     ON p.id = COALESCE(m.project_id, ph.project_id)';
    $where  = 'WHERE m.target_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
    $order  = 'ORDER BY m.target_date ASC
               LIMIT 15';

    if ($user['role'] === 'admin') {
        $stmt = pdo()->prepare("$select $where $order");
        $stmt->execute();
    } else {
        $uid  = $user['id'];
        $stmt = pdo()->prepare(
            "$select LEFT JOIN project_collaborators pc
                        ON pc.project_id = p.id AND pc.user_id = ?
             $where AND (p.user_id = ? OR pc.user_id = ?)
             $order"
        );
        $stmt->execute([$uid, $uid, $uid]);
    }

    json_out(array_map(fn($r) => [
        'id'           => (int)$r['id'],
        'name'         => $r['name'],
        'target_date'  => $r['target_date'],
        'days_until'   => (int)$r['days_until'],
        'project_id'   => (int)$r['project_id'],
        'project_name' => $r['project_name'],
    ], $stmt->fetchAll()));
}

// ── Events API ────────────────────────────────────────────────────────────────

/** POST /api/projects/{id}/events — create a project-level event not tied to any phase; all_day=true clears start/end times */
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

/** POST /api/phases/{id}/events — create an event attached to a specific phase; all_day=true clears start/end times */
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

/**
 * PATCH /api/events/{id} — update an event's fields.
 * Passing all_day: true clears start_time/end_time regardless of other time fields.
 * Omitting all_day patches only the explicitly provided fields against the existing row.
 */
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

/** DELETE /api/events/{id} — delete an event */
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

/** GET /api/projects/{id}/collaborators — return the list of collaborators with their role; read access required */
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

/**
 * POST /api/projects/{id}/collaborators — add a user as a collaborator; owner-only.
 * Body: { email: string, role?: 'viewer'|'editor' } — defaults to 'viewer'.
 * Returns 422 if the target user is the project owner; upserts if already a collaborator.
 */
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

/** PATCH /api/projects/{id}/collaborators/{uid} — change a collaborator's role; owner-only */
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

/** DELETE /api/projects/{id}/collaborators/{uid} — remove a collaborator from the project; owner-only */
function api_remove_collaborator(int $project_id, int $user_id): void
{
    require_auth();
    if (!is_project_owner($project_id)) json_out(['detail' => 'Forbidden'], 403);
    $del = pdo()->prepare('DELETE FROM project_collaborators WHERE project_id = ? AND user_id = ?');
    $del->execute([$project_id, $user_id]);
    json_out(['ok' => true]);
}

// ── ICS / Settings API ────────────────────────────────────────────────────────

/** GET /api/ics-token — return the current user's ICS token and the full calendar feed URL */
function api_get_ics_token(): void
{
    require_auth();
    $token = current_user_ics_token();
    $base  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
           . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    json_out(['token' => $token, 'url' => $base . '/calendar.ics?token=' . urlencode($token)]);
}

/** POST /api/ics-token/rotate — generate a new ICS token for the current user, invalidating the previous one */
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

/** GET /api/admin/settings — return global settings (currently session_timeout in seconds); admin-only */
function api_get_admin_settings(): void
{
    require_admin();
    json_out(['session_timeout' => (int)setting_get('session_timeout', '0')]);
}

/**
 * PUT /api/admin/settings — update global settings; admin-only.
 * session_timeout must be one of: 0 (browser session), 3600, 14400, 28800, 86400, 604800 seconds.
 */
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

/** GET /api/profile — return the current user's id, name, email, and role */
function api_get_profile(): void
{
    require_auth();
    $u = current_user();
    json_out(['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']]);
}

/**
 * POST /api/profile/password — change the current user's password.
 * Body: { current_password: string, new_password: string (min 8 chars) }
 * Verifies the current password before updating; returns 422 if it is wrong or too short.
 */
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

/** GET /api/admin/users — return all users with id, email, name, role, is_active, and created_at; admin-only */
function api_get_users(): void
{
    require_admin();
    $rows = pdo()->query(
        'SELECT id, email, name, role, is_active, created_at FROM users ORDER BY id'
    )->fetchAll();
    json_out(array_map(fn($r) => array_merge($r, ['id' => (int)$r['id']]), $rows));
}

/**
 * POST /api/admin/invites — create a one-time invite link; admin-only.
 * Body: { label?: string, expires_days?: int (1–365, default 7) }
 * Returns 201 with the invite token and the full registration URL.
 */
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

/** GET /api/admin/invites — return all invites ordered by creation date; includes used_by_email for redeemed ones; admin-only */
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

/** DELETE /api/admin/invites/{id} — immediately expire an unused invite by setting expires_at to now; admin-only */
function api_revoke_invite(int $id): void
{
    require_admin();
    // Mark as expired immediately by setting expires_at to now
    pdo()->prepare('UPDATE invites SET expires_at = NOW() WHERE id = ? AND used_by IS NULL')
         ->execute([$id]);
    json_out(['ok' => true]);
}

/**
 * POST /api/admin/users/{id}/password-reset — generate a 24-hour password reset link for any user; admin-only.
 * Returns 201 with the reset URL and its expiry timestamp.
 */
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

/**
 * PATCH /api/admin/users/{id} — update a user's is_active flag and/or role; admin-only.
 * Returns 422 if the admin tries to modify their own account.
 */
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
