-- iERA App - full MySQL schema for new installs
-- Import with phpMyAdmin or: mysql -u user -p dbname < sql/schema.sql
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'volunteer',
  status VARCHAR(30) NOT NULL DEFAULT 'new',
  is_sysadmin TINYINT(1) NOT NULL DEFAULT 0,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  gender VARCHAR(10) NULL,
  mobile VARCHAR(30) NULL,
  phone VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  postal_code VARCHAR(10) NULL,
  city VARCHAR(100) NULL,
  province VARCHAR(100) NULL,
  country VARCHAR(100) NULL,
  birth_year SMALLINT NULL,
  pin VARCHAR(20) NULL,
  position VARCHAR(50) NULL,
  enrollment_method VARCHAR(30) NULL,
  previous_religion VARCHAR(100) NULL,
  shahada_location VARCHAR(255) NULL,
  referrer_name VARCHAR(150) NULL,
  referrer_email VARCHAR(190) NULL,
  training_type VARCHAR(30) NULL,
  training_date DATETIME NULL,
  stripe_verification_id VARCHAR(100) NULL,
  stripe_status VARCHAR(30) NULL,
  comments TEXT NULL,
  paused_until DATE NULL,
  profile_json LONGTEXT NULL,
  created_by CHAR(36) NULL,
  last_login_at DATETIME NULL,
  totp_secret VARCHAR(64) NULL,
  totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_type_status (type, status),
  KEY ix_users_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_groups (
  user_id CHAR(36) NOT NULL,
  grp VARCHAR(20) NOT NULL,
  PRIMARY KEY (user_id, grp),
  CONSTRAINT fk_ug_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sub_groups (
  user_id CHAR(36) NOT NULL,
  sub_group VARCHAR(20) NOT NULL,
  PRIMARY KEY (user_id, sub_group),
  CONSTRAINT fk_usg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
  id VARCHAR(80) NOT NULL,
  name VARCHAR(150) NOT NULL,
  parent_team VARCHAR(80) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'approved',
  description TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_teams_parent (parent_team)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
  team_id VARCHAR(80) NOT NULL,
  user_id CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (team_id, user_id),
  KEY ix_tm_user (user_id),
  CONSTRAINT fk_tm_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_tm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_admins (
  team_id VARCHAR(80) NOT NULL,
  user_id CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (team_id, user_id),
  KEY ix_ta_user (user_id),
  CONSTRAINT fk_ta_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_ta_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mentor_assignments (
  id INT NOT NULL AUTO_INCREMENT,
  mentor_id CHAR(36) NOT NULL,
  mentee_id CHAR(36) NOT NULL,
  assigned_by CHAR(36) NULL,
  accepted_at DATETIME NULL,
  rejected_at DATETIME NULL,
  reject_reason TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ma (mentor_id, mentee_id),
  KEY ix_ma_mentee (mentee_id),
  CONSTRAINT fk_ma_mentor FOREIGN KEY (mentor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ma_mentee FOREIGN KEY (mentee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id CHAR(36) NOT NULL,
  team_id VARCHAR(80) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  city VARCHAR(150) NULL,
  min_participants INT NOT NULL DEFAULT 0,
  event_type VARCHAR(20) NOT NULL DEFAULT 'recurring',
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  active_events INT NOT NULL DEFAULT 4,
  start_at DATETIME NOT NULL,
  event_length DECIMAL(4,1) NOT NULL DEFAULT 2.0,
  recurrence VARCHAR(10) NOT NULL DEFAULT 'weekly',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  author_id CHAR(36) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_events_team (team_id, status),
  CONSTRAINT fk_events_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sub_events (
  id CHAR(36) NOT NULL,
  event_id CHAR(36) NULL,
  team_id VARCHAR(80) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  city VARCHAR(150) NULL,
  min_participants INT NOT NULL DEFAULT 0,
  start_at DATETIME NOT NULL,
  event_length DECIMAL(4,1) NOT NULL DEFAULT 2.0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  report_generated_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_se_team_start (team_id, status, start_at),
  KEY ix_se_event (event_id),
  KEY ix_se_start (start_at),
  CONSTRAINT fk_se_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sub_event_attendance (
  id INT NOT NULL AUTO_INCREMENT,
  sub_event_id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  times_json TEXT NULL,
  added_by CHAR(36) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sea (sub_event_id, user_id),
  KEY ix_sea_user (user_id),
  CONSTRAINT fk_sea_sub FOREIGN KEY (sub_event_id) REFERENCES sub_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_sea_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  id CHAR(36) NOT NULL,
  type VARCHAR(20) NOT NULL,
  subject_id CHAR(36) NOT NULL,
  assignee_id CHAR(36) NOT NULL,
  period_key VARCHAR(20) NOT NULL DEFAULT 'event',
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  submit_json LONGTEXT NULL,
  submitted_by CHAR(36) NULL,
  submitted_at DATETIME NULL,
  sharepoint_entry_id VARCHAR(50) NULL,
  sharepoint_error TEXT NULL,
  sharepoint_attempts INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reports (type, subject_id, assignee_id, period_key),
  KEY ix_reports_assignee (assignee_id, status),
  KEY ix_reports_type_status (type, status),
  KEY ix_reports_subject (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_notifications (
  id INT NOT NULL AUTO_INCREMENT,
  report_id CHAR(36) NOT NULL,
  kind VARCHAR(40) NOT NULL,
  sent_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rn (report_id, kind),
  CONSTRAINT fk_rn_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_templates (
  id VARCHAR(40) NOT NULL,
  label VARCHAR(100) NOT NULL,
  fields_json LONGTEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vacation_requests (
  id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  reason TEXT NULL,
  from_date DATE NULL,
  to_date DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'requested',
  decided_by CHAR(36) NULL,
  decided_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_vr_user (user_id),
  CONSTRAINT fk_vr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_comments (
  id INT NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  author_id CHAR(36) NULL,
  comment TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ac_user (user_id),
  CONSTRAINT fk_ac_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_documents (
  id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  kind VARCHAR(30) NOT NULL DEFAULT 'id',
  filename VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size INT NOT NULL DEFAULT 0,
  path VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ud_user (user_id),
  CONSTRAINT fk_ud_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS otp_codes (
  id INT NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  purpose VARCHAR(20) NOT NULL DEFAULT 'login',
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  used_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_otp_user (user_id, purpose),
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT NOT NULL AUTO_INCREMENT,
  identifier VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_la_identifier (identifier, created_at),
  KEY ix_la_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
  selector VARCHAR(40) NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  user_id CHAR(36) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (selector),
  KEY ix_rt_user (user_id),
  CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_pr_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
  tpl_key VARCHAR(60) NOT NULL,
  name VARCHAR(120) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_html LONGTEXT NOT NULL,
  sendgrid_template_id VARCHAR(80) NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  description TEXT NULL,
  variables VARCHAR(500) NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (tpl_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_log (
  id INT NOT NULL AUTO_INCREMENT,
  to_email VARCHAR(500) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  template_key VARCHAR(60) NULL,
  status VARCHAR(20) NOT NULL,
  driver VARCHAR(20) NULL,
  error TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_el_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT NOT NULL AUTO_INCREMENT,
  actor_id CHAR(36) NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id VARCHAR(80) NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_al_created (created_at),
  KEY ix_al_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(80) NOT NULL,
  value TEXT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_runs (
  id INT NOT NULL AUTO_INCREMENT,
  job VARCHAR(60) NOT NULL,
  period_key VARCHAR(40) NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'running',
  message TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_job_period (job, period_key),
  KEY ix_job_started (job, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_cache (
  cache_key VARCHAR(60) NOT NULL,
  value LONGTEXT NULL,
  expires_at DATETIME NULL,
  PRIMARY KEY (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(40) NOT NULL,
  applied_at DATETIME NOT NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version, applied_at) VALUES ('0001_initial', UTC_TIMESTAMP()), ('0002_email_log_driver', UTC_TIMESTAMP()), ('0003_connect_core', UTC_TIMESTAMP());

SET FOREIGN_KEY_CHECKS = 1;
