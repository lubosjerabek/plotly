<?php

defined('APP_BOOT') or die;

// ── Project access helpers ────────────────────────────────────────────────────

/** True if the current user can read this project (owner, collaborator, or admin) */
function can_read_project(int $project_id): bool
{
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
function can_write_project(int $project_id): bool
{
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
function is_project_owner(int $project_id): bool
{
    $user = current_user();
    if (!$user) return false;
    if ($user['role'] === 'admin') return true;
    $stmt = pdo()->prepare('SELECT 1 FROM projects WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$project_id, $user['id']]);
    return (bool)$stmt->fetch();
}

/** Abort with 403 JSON if the current user cannot read this project */
function assert_project_read(int $project_id): void
{
    if (!can_read_project($project_id)) json_out(['detail' => 'Forbidden'], 403);
}

/** Abort with 403 JSON if the current user cannot write to this project */
function assert_project_write(int $project_id): void
{
    if (!can_write_project($project_id)) json_out(['detail' => 'Forbidden'], 403);
}

/** Get project_id for a phase (returns 0 if not found) */
function project_id_for_phase(int $phase_id): int
{
    $stmt = pdo()->prepare('SELECT project_id FROM phases WHERE id = ?');
    $stmt->execute([$phase_id]);
    $row = $stmt->fetch();
    return $row ? (int)$row['project_id'] : 0;
}

/** Get project_id for a phase group (returns 0 if not found) */
function project_id_for_group(int $group_id): int
{
    $stmt = pdo()->prepare('SELECT project_id FROM phase_groups WHERE id = ?');
    $stmt->execute([$group_id]);
    $row = $stmt->fetch();
    return $row ? (int)$row['project_id'] : 0;
}

/** Get project_id for a milestone */
function project_id_for_milestone(int $milestone_id): int
{
    $stmt = pdo()->prepare('SELECT project_id, phase_id FROM milestones WHERE id = ?');
    $stmt->execute([$milestone_id]);
    $row = $stmt->fetch();
    if (!$row) return 0;
    if ($row['project_id']) return (int)$row['project_id'];
    if ($row['phase_id'])   return project_id_for_phase((int)$row['phase_id']);
    return 0;
}

/** Get project_id for an event */
function project_id_for_event(int $event_id): int
{
    $stmt = pdo()->prepare('SELECT project_id, phase_id FROM events WHERE id = ?');
    $stmt->execute([$event_id]);
    $row = $stmt->fetch();
    if (!$row) return 0;
    if ($row['project_id']) return (int)$row['project_id'];
    if ($row['phase_id'])   return project_id_for_phase((int)$row['phase_id']);
    return 0;
}

// ── ICS token helpers ─────────────────────────────────────────────────────────

/** Find user by ICS token. Returns user row or null. */
function user_by_ics_token(string $token): ?array
{
    if ($token === '') return null;
    $stmt = pdo()->prepare('SELECT id, role FROM users WHERE ics_token = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch() ?: null;
    if ($row) $row['id'] = (int)$row['id'];
    return $row;
}

/** Get per-user ICS token for the currently authenticated user */
function current_user_ics_token(): string
{
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

/** Validate the ?token= query parameter as an ICS token; abort with 403 plain-text if invalid */
function require_ics_token(): ?array
{
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

/** Return all projects visible to the current user (admins see all; others see owned + collaborated) */
function get_projects(): array
{
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

/**
 * Return the fully hydrated project tree for $id, or null if not found.
 * Includes standalone phases and phase groups (each with their nested phases),
 * milestones and events at both phase and project level, and collaborators.
 */
function get_full_project(int $id): ?array
{
    $stmt = pdo()->prepare('SELECT id, user_id, name, description FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if (!$project) return null;

    $project['id']      = (int)$project['id'];
    $project['user_id'] = $project['user_id'] !== null ? (int)$project['user_id'] : null;

    // Phase groups
    $stmt = pdo()->prepare('SELECT id, project_id, name, color, description, sort_order FROM phase_groups WHERE project_id = ? ORDER BY sort_order, id');
    $stmt->execute([$id]);
    $groups_raw = $stmt->fetchAll();
    $groups_map = []; // keyed by group id
    foreach ($groups_raw as $g) {
        $g['id']         = (int)$g['id'];
        $g['project_id'] = (int)$g['project_id'];
        $g['sort_order'] = (int)$g['sort_order'];
        $g['phases']     = [];
        $groups_map[$g['id']] = $g;
    }

    // Phases (include group_id)
    $stmt = pdo()->prepare('SELECT id, project_id, group_id, name, start_date, end_date, color, description, google_event_id, depends_on_id, depends_on_milestone_id FROM phases WHERE project_id = ? ORDER BY start_date');
    $stmt->execute([$id]);
    $phases_raw = $stmt->fetchAll();

    $standalone_phases = [];

    foreach ($phases_raw as &$phase) {
        $phase['id']                      = (int)$phase['id'];
        $phase['project_id']              = (int)$phase['project_id'];
        $phase['group_id']                = $phase['group_id'] !== null ? (int)$phase['group_id'] : null;
        $phase['depends_on_id']           = $phase['depends_on_id'] !== null ? (int)$phase['depends_on_id'] : null;
        $phase['depends_on_milestone_id'] = $phase['depends_on_milestone_id'] !== null ? (int)$phase['depends_on_milestone_id'] : null;

        // Milestones
        $ms = pdo()->prepare('SELECT id, phase_id, name, target_date, google_event_id FROM milestones WHERE phase_id = ? ORDER BY target_date');
        $ms->execute([$phase['id']]);
        $phase['milestones'] = array_map(function ($m) {
            $m['id']       = (int)$m['id'];
            $m['phase_id'] = (int)$m['phase_id'];
            return $m;
        }, $ms->fetchAll());

        // Events
        $ev = pdo()->prepare('SELECT id, phase_id, name, start_date, end_date, start_time, end_time, google_event_id FROM events WHERE phase_id = ? ORDER BY start_date');
        $ev->execute([$phase['id']]);
        $phase['events'] = array_map(function ($e) {
            $e['id']       = (int)$e['id'];
            $e['phase_id'] = (int)$e['phase_id'];
            return $e;
        }, $ev->fetchAll());

        if ($phase['group_id'] !== null && isset($groups_map[$phase['group_id']])) {
            $groups_map[$phase['group_id']]['phases'][] = $phase;
        } else {
            $standalone_phases[] = $phase;
        }
    }
    unset($phase);

    $project['phases']  = $standalone_phases;
    $project['groups']  = array_values($groups_map);

    // Project-level milestones (not tied to any phase)
    $ms = pdo()->prepare('SELECT id, project_id, name, target_date, google_event_id FROM milestones WHERE project_id = ? AND phase_id IS NULL ORDER BY target_date');
    $ms->execute([$id]);
    $project['milestones'] = array_map(function ($m) {
        $m['id']         = (int)$m['id'];
        $m['project_id'] = (int)$m['project_id'];
        $m['phase_id']   = null;
        return $m;
    }, $ms->fetchAll());

    // Project-level events (not tied to any phase)
    $ev = pdo()->prepare('SELECT id, project_id, name, start_date, end_date, start_time, end_time, google_event_id FROM events WHERE project_id = ? AND phase_id IS NULL ORDER BY start_date');
    $ev->execute([$id]);
    $project['events'] = array_map(function ($e) {
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
    $project['collaborators'] = array_map(function ($c) {
        $c['id'] = (int)$c['id'];
        return $c;
    }, $co->fetchAll());

    return $project;
}

// ── Phase group CRUD ──────────────────────────────────────────────────────────

/** Insert a new phase group for $project_id and return the new row ID */
function create_phase_group(int $project_id, array $data): int
{
    $stmt = pdo()->prepare(
        'INSERT INTO phase_groups (project_id, name, color, description, sort_order)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $project_id,
        $data['name'],
        $data['color']       ?? '#cccccc',
        $data['description'] ?? null,
        $data['sort_order']  ?? 0,
    ]);
    return (int)pdo()->lastInsertId();
}

/** Update whichever fields are present in $data for phase group $id; returns false if nothing changed */
function update_phase_group(int $id, array $data): bool
{
    $fields = [];
    $params = [];

    if (array_key_exists('name', $data)) {
        $fields[] = 'name = ?';
        $params[] = $data['name'];
    }
    if (array_key_exists('color', $data)) {
        $fields[] = 'color = ?';
        $params[] = $data['color'];
    }
    if (array_key_exists('description', $data)) {
        $fields[] = 'description = ?';
        $params[] = $data['description'];
    }
    if (array_key_exists('sort_order', $data)) {
        $fields[] = 'sort_order = ?';
        $params[] = (int)$data['sort_order'];
    }
    if (empty($fields)) return false;

    $params[] = $id;
    $stmt = pdo()->prepare('UPDATE phase_groups SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

/** Delete a phase group; member phases are ungrouped (not deleted) via ON DELETE SET NULL on group_id */
function delete_phase_group(int $id): bool
{
    // ON DELETE SET NULL on phases.group_id handles ungrouping member phases
    $stmt = pdo()->prepare('DELETE FROM phase_groups WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

/**
 * Recursively shift all phases that depend on $phase_id by $delta_days.
 * Called after a phase date change to cascade the offset through the dependency chain.
 */
function shift_dependents(int $phase_id, int $delta_days): void
{
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
