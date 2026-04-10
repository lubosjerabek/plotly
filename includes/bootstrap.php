<?php
declare(strict_types=1);
defined('APP_BOOT') or die;

$_session_lifetime = (int)setting_get('session_timeout', '0');
if ($_session_lifetime > 0) {
    ini_set('session.gc_maxlifetime', (string)$_session_lifetime);
}
session_set_cookie_params([
    'lifetime' => $_session_lifetime,
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
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'");
if (($_SERVER['HTTPS'] ?? '') === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

