-- iERA Connect core: extra roles per user and in-app notifications
CREATE TABLE IF NOT EXISTS user_roles (
  user_id CHAR(36) NOT NULL,
  role VARCHAR(40) NOT NULL,
  granted_by CHAR(36) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, role),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'general',
  type VARCHAR(20) NOT NULL DEFAULT 'info',
  icon VARCHAR(40) NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT NULL,
  link VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  source VARCHAR(60) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_notif_user (user_id, is_read, created_at),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
  user_id CHAR(36) NOT NULL,
  category VARCHAR(40) NOT NULL,
  in_app TINYINT(1) NOT NULL DEFAULT 1,
  email TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, category),
  CONSTRAINT fk_np_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
