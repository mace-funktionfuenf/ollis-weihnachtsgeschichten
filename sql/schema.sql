-- Run once against the target database. Works as-is against SQLite
-- (local dev) and MySQL/MariaDB (production) — both accept this syntax.

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'editor',
  active INTEGER NOT NULL DEFAULT 1,
  created_at VARCHAR(30) NOT NULL
);

-- Bootstrapping the first account (no self-registration exists):
--
--   php -r "echo password_hash('CHANGE-ME', PASSWORD_DEFAULT), \"\n\";"
--
-- then, in phpMyAdmin (production) or via the sqlite3 CLI (local dev):
--
--   INSERT INTO users (username, password_hash, role, active, created_at)
--   VALUES ('olaf', '<paste hash here>', 'admin', 1, '2026-08-20');
