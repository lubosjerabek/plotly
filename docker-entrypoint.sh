#!/bin/sh
# Custom entrypoint: wait for DB, seed first admin user if the users table is
# empty, then hand off to the default Apache entrypoint.

set -e

ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_NAME="${ADMIN_NAME:-Admin}"
ADMIN_PASS="${ADMIN_PASS:-plotly}"

if [ ${#ADMIN_PASS} -lt 8 ]; then
  echo "[entrypoint] WARNING: ADMIN_PASS is shorter than 8 characters. Set a stronger password via the ADMIN_PASS env variable."
fi

# Wait for MySQL to be ready (up to 60 s)
echo "[entrypoint] Waiting for MySQL on ${DB_HOST}:3306 …"
i=0
until php -- "$DB_HOST" "$DB_NAME" "$DB_USER" "$DB_PASS" <<'WAITPHP'
<?php
  try {
    new PDO(
      'mysql:host=' . $argv[1] . ';dbname=' . $argv[2] . ';charset=utf8mb4',
      $argv[3], $argv[4],
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    exit(0);
  } catch (Exception $e) { exit(1); }
WAITPHP
do
  i=$((i+1))
  if [ $i -ge 60 ]; then echo "[entrypoint] DB not reachable after 60 s. Aborting."; exit 1; fi
  sleep 1
done
echo "[entrypoint] DB is ready."

# Seed first admin user if users table is empty
php -- "$ADMIN_EMAIL" "$ADMIN_NAME" "$ADMIN_PASS" <<'SEEDPHP'
<?php
  require '/var/www/html/config.php';
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  // Users table must exist (schema.sql should have run via Docker initdb)
  $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
  if ($count === 0) {
    $email = $argv[1];
    $name  = $argv[2];
    $pass  = $argv[3];
    $hash  = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
      "INSERT INTO users (email, name, password_hash, role, ics_token) VALUES (?, ?, ?, 'admin', ?)"
    )->execute([$email, $name, $hash, $token]);

    // Assign any unowned projects to this admin
    $uid = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE projects SET user_id = ? WHERE user_id IS NULL')->execute([$uid]);
    echo "[entrypoint] Admin user created: $email" . PHP_EOL;
  } else {
    echo '[entrypoint] Users already seeded, skipping.' . PHP_EOL;
  }
SEEDPHP

# Hand off to the default php:apache entrypoint / CMD
exec docker-php-entrypoint apache2-foreground
