<?php

defined('APP_BOOT') or die;

// ── Auth ──────────────────────────────────────────────────────────────────────

function require_auth(): void
{
    if (empty($_SESSION['authed']) || empty($_SESSION['user_id'])) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_out(['detail' => 'Unauthorized'], 401);
        }
        header('Location: /login');
        exit;
    }
}

function require_admin(): void
{
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
function current_user(): array
{
    static $user = null;
    if ($user === null) {
        $stmt = pdo()->prepare('SELECT id, email, name, role, ics_token, lang, is_active FROM users WHERE id = ?');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: [];
        if ($user) $user['id'] = (int)$user['id'];
    }
    return $user;
}
