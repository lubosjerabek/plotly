#!/bin/sh
# Custom entrypoint: wait for DB, seed first admin user if the users table is
# empty, then hand off to the default Apache entrypoint.

set -e

ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_NAME="${ADMIN_NAME:-Admin}"
ADMIN_PASS="${ADMIN_PASS:-plotly}"

# Wait for MySQL to be ready (up to 60 s)
echo "[entrypoint] Waiting for MySQL on ${DB_HOST}:3306 …"
i=0
until php -r "
  try {
    new PDO('mysql:host=${DB_HOST};dbname=${DB_NAME};charset=utf8mb4', '${DB_USER}', '${DB_PASS}', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    exit(0);
  } catch (Exception \$e) { exit(1); }
" 2>/dev/null; do
  i=$((i+1))
  if [ $i -ge 60 ]; then echo "[entrypoint] DB not reachable after 60 s. Aborting."; exit 1; fi
  sleep 1
done
echo "[entrypoint] DB is ready."

# Seed first admin user if users table is empty
php -r "
  require '/var/www/html/config.php';
  \$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  // Users table must exist (schema.sql should have run via Docker initdb)
  \$count = (int)\$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
  if (\$count === 0) {
    \$hash  = password_hash('${ADMIN_PASS}', PASSWORD_BCRYPT, ['cost' => 12]);
    \$token = bin2hex(random_bytes(32));
    \$pdo->prepare(
      \"INSERT INTO users (email, name, password_hash, role, ics_token) VALUES (?, ?, ?, 'admin', ?)\"
    )->execute(['${ADMIN_EMAIL}', '${ADMIN_NAME}', \$hash, \$token]);

    // Assign any unowned projects to this admin
    \$uid = (int)\$pdo->lastInsertId();
    \$pdo->prepare('UPDATE projects SET user_id = ? WHERE user_id IS NULL')->execute([\$uid]);
    echo '[entrypoint] Admin user created: ${ADMIN_EMAIL}' . PHP_EOL;
  } else {
    echo '[entrypoint] Users already seeded, skipping.' . PHP_EOL;
  }
"

# Hand off to the default php:apache entrypoint / CMD
exec docker-php-entrypoint apache2-foreground
