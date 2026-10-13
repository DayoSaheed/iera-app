CREATE TABLE IF NOT EXISTS teams_outbox (
  id INT NOT NULL AUTO_INCREMENT,
  category VARCHAR(30) NOT NULL DEFAULT 'general',
  action VARCHAR(80) NULL,
  title VARCHAR(200) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_teams_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
