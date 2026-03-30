-- Run this once via your hosting panel's SQL / phpMyAdmin console.
-- Note: the ALTER TABLE near the bottom adds the milestone-dependency FK and
-- must be run on existing installations (Wedos). For fresh installs via Docker
-- the entrypoint runs this file in full, so the ALTER also applies.

CREATE TABLE IF NOT EXISTS projects (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255) NOT NULL,
  description TEXT
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
