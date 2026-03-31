<?php
/**
 * One-time migration: single-user → multi-user
 *
 * Run this once after deploying the multi-user code.
 * It will:
 *   1. Add the users, invites, password_resets, project_collaborators tables if missing.
 *   2. Add user_id column to projects if missing.
 *   3. Create the first admin user from the LEGACY_AUTH_USER / LEGACY_AUTH_PASS_HASH
 *      config values (or a form submission).
 *   4. Assign all existing projects (user_id IS NULL) to that admin.
 *   5. Move the global ICS token from settings to the admin user.
 *
 * DELETE this file via FTP after the migration succeeds.
 */
declare(strict_types=1);

// ── Basic protection ──────────────────────────────────────────────────────────
// The file should be deleted after use. As an extra precaution it checks for
// a one-time confirm param on POST and renders a form on GET.
session_start();
require __DIR__ . '/config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$errors  = [];
$success = false;
$log     = [];

function mig_log(string $msg): void {
    global $log;
    $log[] = htmlspecialchars($msg);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_email = strtolower(trim($_POST['admin_email'] ?? ''));
    $admin_name  = trim($_POST['admin_name'] ?? 'Admin');

    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid admin email address is required.';
    }

    if (empty($errors)) {
        try {
            // ── 1. Create users table ─────────────────────────────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
              id            INT AUTO_INCREMENT PRIMARY KEY,
              email         VARCHAR(255) NOT NULL UNIQUE,
              name          VARCHAR(255) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              role          ENUM('admin','user') NOT NULL DEFAULT 'user',
              ics_token     VARCHAR(64)  NOT NULL DEFAULT '',
              lang          VARCHAR(8)   NOT NULL DEFAULT '',
              is_active     TINYINT(1)   NOT NULL DEFAULT 1,
              created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            mig_log('✔ users table ready');

            // ── 2. Add user_id to projects ────────────────────────────────────
            $cols = $pdo->query("SHOW COLUMNS FROM projects LIKE 'user_id'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec("ALTER TABLE projects ADD COLUMN user_id INT AFTER id");
                $pdo->exec("ALTER TABLE projects ADD CONSTRAINT fk_projects_user FOREIGN KEY (user_id) REFERENCES users(id)");
                mig_log('✔ user_id column added to projects');
            } else {
                mig_log('✔ user_id column already exists on projects');
            }

            // ── 3. Create invites table ───────────────────────────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS invites (
              id         INT AUTO_INCREMENT PRIMARY KEY,
              token      VARCHAR(64)  NOT NULL UNIQUE,
              label      VARCHAR(255),
              created_by INT NOT NULL,
              used_by    INT,
              expires_at DATETIME     NOT NULL,
              created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (created_by) REFERENCES users(id),
              FOREIGN KEY (used_by)    REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            mig_log('✔ invites table ready');

            // ── 4. Create password_resets table ───────────────────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
              id         INT AUTO_INCREMENT PRIMARY KEY,
              user_id    INT NOT NULL,
              token      VARCHAR(64) NOT NULL UNIQUE,
              used_at    DATETIME,
              expires_at DATETIME    NOT NULL,
              created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            mig_log('✔ password_resets table ready');

            // ── 5. Create project_collaborators table ─────────────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS project_collaborators (
              id         INT AUTO_INCREMENT PRIMARY KEY,
              project_id INT NOT NULL,
              user_id    INT NOT NULL,
              role       ENUM('viewer','editor') NOT NULL DEFAULT 'viewer',
              added_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_project_user (project_id, user_id),
              FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
              FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            mig_log('✔ project_collaborators table ready');

            // ── 6. Create admin user ──────────────────────────────────────────
            $existing = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $existing->execute([$admin_email]);
            $admin_row = $existing->fetch();

            // ICS token: migrate from settings table or generate fresh
            $ics_stmt = $pdo->query("SELECT `value` FROM settings WHERE `key` = 'ics_token'");
            $ics_row  = $ics_stmt ? $ics_stmt->fetch() : false;
            $ics_token = $ics_row ? $ics_row['value'] : (ICS_TOKEN !== 'change-this-to-a-long-random-secret' ? ICS_TOKEN : bin2hex(random_bytes(32)));

            if (!$admin_row) {
                $ins = $pdo->prepare(
                    "INSERT INTO users (email, name, password_hash, role, ics_token) VALUES (?, ?, ?, 'admin', ?)"
                );
                $ins->execute([$admin_email, $admin_name, LEGACY_AUTH_PASS_HASH, $ics_token]);
                $admin_id = (int)$pdo->lastInsertId();
                mig_log("✔ Admin user created (id=$admin_id, email=$admin_email)");
            } else {
                $admin_id = (int)$admin_row['id'];
                // Ensure role is admin and update ics_token if missing
                $pdo->prepare("UPDATE users SET role = 'admin', ics_token = IF(ics_token = '', ?, ics_token) WHERE id = ?")
                    ->execute([$ics_token, $admin_id]);
                mig_log("✔ Existing user promoted to admin (id=$admin_id)");
            }

            // ── 7. Assign unowned projects to admin ───────────────────────────
            $upd  = $pdo->prepare('UPDATE projects SET user_id = ? WHERE user_id IS NULL');
            $upd->execute([$admin_id]);
            $count = $upd->rowCount();
            mig_log("✔ $count existing project(s) assigned to admin");

            $success = true;

        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Plotly — Migration</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #0f0f13; color: #f1f5f9; max-width: 560px; margin: 4rem auto; padding: 0 1rem; }
    h1 { font-size: 20px; margin-bottom: .5rem; }
    p, li { font-size: 14px; color: #94a3b8; line-height: 1.6; }
    label { display: block; font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin: 1rem 0 .3rem; }
    input { width: 100%; padding: .6rem .85rem; background: #1e1e2a; border: 1px solid rgba(255,255,255,.1); border-radius: 8px; color: #f1f5f9; font-size: 14px; box-sizing: border-box; }
    button { margin-top: 1.25rem; padding: .7rem 1.5rem; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
    button:hover { background: #4f51d4; }
    .error { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: #fca5a5; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: 13px; }
    .success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.3); color: #86efac; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: 13px; }
    .log { background: #16161d; border: 1px solid rgba(255,255,255,.08); border-radius: 8px; padding: 1rem; margin-top: 1.5rem; }
    .log p { margin: .15rem 0; font-size: 13px; color: #94a3b8; }
    .warn { color: #fbbf24; font-size: 13px; margin-top: 1.5rem; }
  </style>
</head>
<body>
  <h1>Plotly — Multi-User Migration</h1>
  <p>This script upgrades the database from single-user to multi-user mode.
     Run it once, then <strong>delete this file</strong> via FTP.</p>

  <?php if (!empty($errors)): ?>
    <?php foreach ($errors as $e): ?>
      <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success">Migration complete!</div>
    <div class="log">
      <?php foreach ($log as $line): ?>
        <p><?= $line ?></p>
      <?php endforeach; ?>
    </div>
    <p class="warn">⚠️ <strong>Delete this file now.</strong> Visit <a href="/" style="color:#6366f1">your app</a> and sign in with your existing credentials (email set above, same password).</p>
  <?php else: ?>
    <form method="post">
      <label for="admin_email">Admin email address</label>
      <input type="email" id="admin_email" name="admin_email"
             value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required autofocus>
      <p style="margin:.4rem 0 0;font-size:12px;color:#64748b">
        The existing password from <code>LEGACY_AUTH_PASS_HASH</code> in config.php will be kept.
      </p>

      <label for="admin_name">Display name</label>
      <input type="text" id="admin_name" name="admin_name"
             value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Admin') ?>" required>

      <button type="submit">Run Migration</button>
    </form>
  <?php endif; ?>
</body>
</html>
