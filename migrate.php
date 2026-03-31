<?php
/**
 * One-time migration: single-user → multi-user
 *
 * Two-step process for shared hosts (Wedos) that restrict CREATE/ALTER:
 *   Step 1 — Shows the SQL to run in phpMyAdmin (DDL: new tables + ALTER projects).
 *   Step 2 — Runs the DML: creates the first admin user and assigns existing projects.
 *
 * DELETE this file via FTP after the migration succeeds.
 */
declare(strict_types=1);
session_start();
require __DIR__ . '/config.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// ── Detect which tables / columns already exist ───────────────────────────────
function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetch();
}

function column_exists(PDO $pdo, string $table, string $col): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$col]);
    return (bool)$stmt->fetch();
}

$need_users            = !table_exists($pdo, 'users');
$need_invites          = !table_exists($pdo, 'invites');
$need_resets           = !table_exists($pdo, 'password_resets');
$need_collaborators    = !table_exists($pdo, 'project_collaborators');
$need_user_id_col      = !column_exists($pdo, 'projects', 'user_id');

$schema_needed = $need_users || $need_invites || $need_resets || $need_collaborators || $need_user_id_col;

// ── Build the SQL block the user needs to run in phpMyAdmin ──────────────────
$sql_parts = [];

if ($need_users) {
    $sql_parts[] = "CREATE TABLE `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `email`         VARCHAR(255) NOT NULL UNIQUE,
  `name`          VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','user') NOT NULL DEFAULT 'user',
  `ics_token`     VARCHAR(64)  NOT NULL DEFAULT '',
  `lang`          VARCHAR(8)   NOT NULL DEFAULT '',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
}

if ($need_user_id_col) {
    $sql_parts[] = "ALTER TABLE `projects`\n  ADD COLUMN `user_id` INT AFTER `id`,\n  ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`);";
}

if ($need_invites) {
    $sql_parts[] = "CREATE TABLE `invites` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `token`      VARCHAR(64)  NOT NULL UNIQUE,
  `label`      VARCHAR(255),
  `created_by` INT NOT NULL,
  `used_by`    INT,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`used_by`)    REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
}

if ($need_resets) {
    $sql_parts[] = "CREATE TABLE `password_resets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `token`      VARCHAR(64) NOT NULL UNIQUE,
  `used_at`    DATETIME,
  `expires_at` DATETIME    NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
}

if ($need_collaborators) {
    $sql_parts[] = "CREATE TABLE `project_collaborators` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `user_id`    INT NOT NULL,
  `role`       ENUM('viewer','editor') NOT NULL DEFAULT 'viewer',
  `added_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_project_user` (`project_id`, `user_id`),
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
}

$sql_block = implode("\n\n", $sql_parts);

// ── Handle step 2: DML only ───────────────────────────────────────────────────
$errors  = [];
$success = false;
$log     = [];

function mig_log(string $msg): void { global $log; $log[] = htmlspecialchars($msg); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_dml'])) {
    $admin_email = strtolower(trim($_POST['admin_email'] ?? ''));
    $admin_name  = trim($_POST['admin_name'] ?? 'Admin');

    if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid admin email address is required.';
    }

    // Re-check that schema is in place before running DML
    if (table_exists($pdo, 'users') && column_exists($pdo, 'projects', 'user_id')) {
        if (empty($errors)) {
            try {
                // ICS token: migrate from settings table if available, else use config or generate
                $ics_row   = $pdo->query("SELECT `value` FROM settings WHERE `key` = 'ics_token'")->fetch();
                $ics_token = $ics_row ? $ics_row['value']
                           : (ICS_TOKEN !== 'change-this-to-a-long-random-secret' ? ICS_TOKEN : bin2hex(random_bytes(32)));

                $existing = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $existing->execute([$admin_email]);
                $admin_row = $existing->fetch();

                if (!$admin_row) {
                    $ins = $pdo->prepare(
                        "INSERT INTO users (email, name, password_hash, role, ics_token) VALUES (?, ?, ?, 'admin', ?)"
                    );
                    $ins->execute([$admin_email, $admin_name, LEGACY_AUTH_PASS_HASH, $ics_token]);
                    $admin_id = (int)$pdo->lastInsertId();
                    mig_log("✔ Admin user created (id=$admin_id, email=$admin_email)");
                } else {
                    $admin_id = (int)$admin_row['id'];
                    $pdo->prepare("UPDATE users SET role = 'admin', ics_token = IF(ics_token = '', ?, ics_token) WHERE id = ?")
                        ->execute([$ics_token, $admin_id]);
                    mig_log("✔ Existing user promoted to admin (id=$admin_id)");
                }

                $upd   = $pdo->prepare('UPDATE projects SET user_id = ? WHERE user_id IS NULL');
                $upd->execute([$admin_id]);
                mig_log("✔ " . $upd->rowCount() . " existing project(s) assigned to admin");

                $success = true;
            } catch (Throwable $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    } else {
        $errors[] = 'The database schema is not yet in place. Please run the SQL in phpMyAdmin first (see Step 1 below).';
    }
}

// Re-check schema state for display
$schema_done = !table_exists($pdo, 'users') === false
    && !column_exists($pdo, 'projects', 'user_id') === false;
$schema_done = table_exists($pdo, 'users') && column_exists($pdo, 'projects', 'user_id')
           && table_exists($pdo, 'invites') && table_exists($pdo, 'password_resets')
           && table_exists($pdo, 'project_collaborators');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Plotly — Migration</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #0f0f13; color: #f1f5f9; max-width: 640px; margin: 4rem auto; padding: 0 1.25rem; }
    h1  { font-size: 20px; margin-bottom: .4rem; }
    h2  { font-size: 15px; font-weight: 600; margin: 2rem 0 .5rem; }
    p, li { font-size: 14px; color: #94a3b8; line-height: 1.6; margin: .4rem 0; }
    label { display: block; font-size: 12px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin: 1rem 0 .3rem; }
    input { width: 100%; padding: .6rem .85rem; background: #1e1e2a; border: 1px solid rgba(255,255,255,.1); border-radius: 8px; color: #f1f5f9; font-size: 14px; box-sizing: border-box; }
    button { margin-top: 1.25rem; padding: .7rem 1.5rem; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
    button:hover { background: #4f51d4; }
    .error   { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: #fca5a5; border-radius: 8px; padding: .75rem 1rem; margin: .75rem 0; font-size: 13px; }
    .success { background: rgba(34,197,94,.1);  border: 1px solid rgba(34,197,94,.3);  color: #86efac; border-radius: 8px; padding: .75rem 1rem; margin: .75rem 0; font-size: 13px; }
    .info    { background: rgba(99,102,241,.1); border: 1px solid rgba(99,102,241,.3); color: #a5b4fc; border-radius: 8px; padding: .75rem 1rem; margin: .75rem 0; font-size: 13px; }
    .warn    { color: #fbbf24; font-size: 13px; margin-top: 1.5rem; }
    .step    { background: #16161d; border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 1.25rem 1.5rem; margin: 1.5rem 0; }
    .step-num { display: inline-block; background: #6366f1; color: #fff; border-radius: 50%; width: 22px; height: 22px; font-size: 12px; font-weight: 700; line-height: 22px; text-align: center; margin-right: .5rem; }
    pre { background: #0f0f13; border: 1px solid rgba(255,255,255,.08); border-radius: 8px; padding: 1rem; font-size: 12px; color: #e2e8f0; overflow-x: auto; white-space: pre-wrap; word-break: break-all; margin: .75rem 0; }
    .copy-btn { margin-top: 0; padding: .35rem .75rem; font-size: 12px; }
    .log p { margin: .15rem 0; font-size: 13px; color: #94a3b8; }
  </style>
</head>
<body>
  <h1>Plotly — Multi-User Migration</h1>
  <p>Upgrades the database from single-user to multi-user mode. Two steps — then delete this file.</p>

  <?php foreach ($errors as $e): ?>
    <div class="error"><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <?php if ($success): ?>
    <div class="success">Migration complete!</div>
    <div class="step">
      <?php foreach ($log as $line): ?><p><?= $line ?></p><?php endforeach; ?>
    </div>
    <p class="warn">⚠️ <strong>Delete this file now</strong> via FTP, then <a href="/" style="color:#6366f1">sign in</a> with your email and existing password.</p>

  <?php else: ?>

    <!-- ── Step 1 ── -->
    <div class="step">
      <h2><span class="step-num">1</span> Run this SQL in phpMyAdmin</h2>
      <?php if ($schema_done): ?>
        <div class="success">All tables already exist — Step 1 is done.</div>
      <?php else: ?>
        <p>Your hosting account can't create tables directly from PHP. Copy the SQL below, open <strong>phpMyAdmin → your database → SQL tab</strong>, paste it, and click <strong>Go</strong>.</p>
        <pre id="sqlBlock"><?= htmlspecialchars($sql_block) ?></pre>
        <button class="copy-btn" type="button" onclick="
          navigator.clipboard.writeText(document.getElementById('sqlBlock').textContent)
            .then(() => { this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy SQL', 2000); });
        ">Copy SQL</button>
        <p style="margin-top:.75rem">After running the SQL, come back here and continue to Step 2.</p>
      <?php endif; ?>
    </div>

    <!-- ── Step 2 ── -->
    <div class="step">
      <h2><span class="step-num">2</span> Create admin user &amp; assign existing projects</h2>
      <?php if (!$schema_done): ?>
        <div class="info">Complete Step 1 first — the required tables are not yet present.</div>
      <?php else: ?>
        <p>Enter your email address for the admin account. Your existing password (from <code>LEGACY_AUTH_PASS_HASH</code> in config.php) will be kept.</p>
        <form method="post">
          <input type="hidden" name="run_dml" value="1">

          <label for="admin_email">Admin email address</label>
          <input type="email" id="admin_email" name="admin_email"
                 value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required autofocus>

          <label for="admin_name">Display name</label>
          <input type="text" id="admin_name" name="admin_name"
                 value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Admin') ?>" required>

          <button type="submit">Run Migration</button>
        </form>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</body>
</html>
