-- Run this once via your hosting panel's SQL / phpMyAdmin console.

CREATE TABLE IF NOT EXISTS projects (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255) NOT NULL,
  description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS phases (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  project_id      INT NOT NULL,
  name            VARCHAR(255) NOT NULL,
  start_date      DATE NOT NULL,
  end_date        DATE NOT NULL,
  color           VARCHAR(20) DEFAULT '#cccccc',
  description     TEXT,
  google_event_id VARCHAR(255),
  depends_on_id   INT,
  FOREIGN KEY (project_id)    REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (depends_on_id) REFERENCES phases(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS milestones (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  phase_id        INT NOT NULL,
  name            VARCHAR(255) NOT NULL,
  target_date     DATE NOT NULL,
  google_event_id VARCHAR(255),
  FOREIGN KEY (phase_id) REFERENCES phases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  phase_id        INT NOT NULL,
  name            VARCHAR(255) NOT NULL,
  start_date      DATE NOT NULL,
  end_date        DATE NOT NULL,
  google_event_id VARCHAR(255),
  FOREIGN KEY (phase_id) REFERENCES phases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
