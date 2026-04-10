<?php
defined('APP_BOOT') or die;

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
