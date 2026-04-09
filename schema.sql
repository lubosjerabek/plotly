-- Run this once via your hosting panel's SQL / phpMyAdmin console.
-- Note: the ALTER TABLE near the bottom adds the milestone-dependency FK and
-- must be run on existing installations (Wedos). For fresh installs via Docker
-- the entrypoint runs this file in full, so the ALTER also applies.
--
-- UPGRADING an existing single-user install?
-- Run migrate.php once after deploying the new code.  It will ALTER the tables
-- and create the first admin user from your old AUTH_USER / AUTH_PASS_HASH.

-- ── Users ──────────────────────────────────────────────────────────────────────
-- Must be created before projects (projects.user_id references users.id)
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(255) NOT NULL UNIQUE,
  name          VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','user') NOT NULL DEFAULT 'user',
  ics_token     VARCHAR(64)  NOT NULL DEFAULT '',
  lang          VARCHAR(8)   NOT NULL DEFAULT '',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Projects ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT,
  name        VARCHAR(255) NOT NULL,
  description TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS phases (
  id                      INT AUTO_INCREMENT PRIMARY KEY,
  project_id              INT NOT NULL,
  name                    VARCHAR(255) NOT NULL,
  start_date              DATE NOT NULL,
  end_date                DATE NOT NULL,
  color                   VARCHAR(20) DEFAULT '#cccccc',
  description             TEXT,
  google_event_id         VARCHAR(255),
  depends_on_id           INT,
  depends_on_milestone_id INT NULL,
  FOREIGN KEY (project_id)    REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (depends_on_id) REFERENCES phases(id)   ON DELETE SET NULL
  -- depends_on_milestone_id FK is added via ALTER TABLE below (circular reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- phase_id is nullable so milestones can belong to a project directly
-- (project_id set, phase_id NULL) or to a phase (phase_id set, project_id NULL).
CREATE TABLE IF NOT EXISTS milestones (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  phase_id        INT NULL,
  project_id      INT NULL,
  name            VARCHAR(255) NOT NULL,
  target_date     DATE NOT NULL,
  google_event_id VARCHAR(255),
  FOREIGN KEY (phase_id)   REFERENCES phases(id)   ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Same dual-ownership pattern for events.
CREATE TABLE IF NOT EXISTS events (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  phase_id        INT NULL,
  project_id      INT NULL,
  name            VARCHAR(255) NOT NULL,
  start_date      DATE NOT NULL,
  end_date        DATE NOT NULL,
  google_event_id VARCHAR(255),
  FOREIGN KEY (phase_id)   REFERENCES phases(id)   ON DELETE CASCADE,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add the milestone-dependency FK on phases now that milestones table exists
ALTER TABLE phases
  ADD FOREIGN KEY (depends_on_milestone_id) REFERENCES milestones(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(64) PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Invites ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invites (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  token      VARCHAR(64)  NOT NULL UNIQUE,
  label      VARCHAR(255),
  created_by INT NOT NULL,
  used_by    INT,
  expires_at DATETIME     NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (used_by)    REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Password resets ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  token      VARCHAR(64) NOT NULL UNIQUE,
  used_at    DATETIME,
  expires_at DATETIME    NOT NULL,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Project collaborators ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS project_collaborators (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  user_id    INT NOT NULL,
  role       ENUM('viewer','editor') NOT NULL DEFAULT 'viewer',
  added_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_project_user (project_id, user_id),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Login rate limiting ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
  ip           VARCHAR(45) NOT NULL,
  attempted_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Live-server migration (run once if upgrading from an earlier schema) ──────
-- If you are starting fresh, these ALTER statements are harmless no-ops because
-- the tables above already have the correct structure.
--
-- ALTER TABLE milestones
--   MODIFY phase_id INT NULL,
--   ADD COLUMN project_id INT NULL,
--   ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE;
--
-- ALTER TABLE events
--   MODIFY phase_id INT NULL,
--   ADD COLUMN project_id INT NULL,
--   ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE;
--
-- ALTER TABLE phases
--   ADD COLUMN depends_on_milestone_id INT NULL,
--   ADD FOREIGN KEY (depends_on_milestone_id) REFERENCES milestones(id) ON DELETE SET NULL;
--
-- Multi-user upgrade (run migrate.php instead of these):
-- ALTER TABLE projects ADD COLUMN user_id INT AFTER id,
--   ADD FOREIGN KEY (user_id) REFERENCES users(id);
