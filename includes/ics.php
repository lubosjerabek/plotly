<?php

defined('APP_BOOT') or die;

// ── ICS helpers ───────────────────────────────────────────────────────────────

function ics_escape(string $s): string
{
    $s = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $s);
    $s = str_replace(["\r\n", "\n", "\r"], '\\n', $s);
    return $s;
}

function build_ics(array $items): string
{
    $now   = gmdate('Ymd\THis\Z');
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Plotly//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
    ];
    foreach ($items as $ev) {
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $ev['uid'];
        $lines[] = 'DTSTAMP:' . $now;

        $startTime = $ev['start_time'] ?? null;
        $endTime   = $ev['end_time']   ?? null;

        if ($startTime) {
            // Timed event: emit local datetime with TZID so calendar apps honour
            // the wall-clock time rather than converting from UTC.
            $tz = date_default_timezone_get() ?: 'UTC';
            $dtstart = str_replace('-', '', $ev['start']) . 'T' . str_replace(':', '', substr($startTime, 0, 5)) . '00';
            $dtend   = str_replace('-', '', $ev['end'])   . 'T' . str_replace(':', '', substr($endTime, 0, 5)) . '00';
            $lines[] = 'DTSTART;TZID=' . $tz . ':' . $dtstart;
            $lines[] = 'DTEND;TZID='   . $tz . ':' . $dtend;
        } else {
            // All-day event: DATE value, DTEND is the exclusive day after end.
            $dtend = date('Ymd', strtotime($ev['end'] . ' +1 day'));
            $lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $ev['start']);
            $lines[] = 'DTEND;VALUE=DATE:' . $dtend;
        }

        $lines[] = 'SUMMARY:' . ics_escape($ev['summary']);
        if (!empty($ev['description'])) {
            $lines[] = 'DESCRIPTION:' . ics_escape($ev['description']);
        }
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", $lines) . "\r\n";
}

function collect_project_ics_items(array $project): array
{
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
                'start_time'  => $ev['start_time'] ?? null,
                'end_time'    => $ev['end_time']   ?? null,
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
            'summary'     => '🏁 ' . $ms['name'],
            'description' => '',
        ];
    }
    foreach ($project['events'] ?? [] as $ev) {
        $items[] = [
            'uid'         => 'proj-ev-' . $ev['id'] . '@plotly',
            'start'       => $ev['start_date'],
            'end'         => $ev['end_date'],
            'start_time'  => $ev['start_time'] ?? null,
            'end_time'    => $ev['end_time']   ?? null,
            'summary'     => $ev['name'],
            'description' => '',
        ];
    }
    return $items;
}

// ── ICS feed handlers ─────────────────────────────────────────────────────────

function ics_all(): void
{
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

function ics_project(int $id): void
{
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
