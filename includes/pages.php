<?php
defined('APP_BOOT') or die;

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
