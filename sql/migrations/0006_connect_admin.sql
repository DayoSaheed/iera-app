-- iERA Connect: admin control centre tables
CREATE TABLE IF NOT EXISTS regions (
  id CHAR(36) NOT NULL,
  name VARCHAR(100) NOT NULL,
  parent_id CHAR(36) NULL,
  branch VARCHAR(10) NOT NULL DEFAULT 'Global',
  emoji VARCHAR(10) NULL,
  countries_json TEXT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_reg_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manager_assignments (
  id CHAR(36) NOT NULL,
  user_email VARCHAR(190) NOT NULL,
  user_name VARCHAR(200) NULL,
  management_role VARCHAR(60) NOT NULL,
  volunteer_category VARCHAR(10) NOT NULL DEFAULT 'All',
  region_id CHAR(36) NULL,
  region_name VARCHAR(100) NULL,
  country VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  language VARCHAR(60) NULL,
  platform VARCHAR(80) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  assigned_by_email VARCHAR(190) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_volunteer_records (
  id CHAR(36) NOT NULL,
  application_id CHAR(36) NOT NULL,
  volunteer_email VARCHAR(190) NULL,
  volunteer_name VARCHAR(200) NULL,
  task_pref TEXT NULL,
  projects TEXT NULL,
  task_history TEXT NULL,
  date_added_to_page DATE NULL,
  skills TEXT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_avr_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS participation_logs (
  id INT NOT NULL AUTO_INCREMENT,
  application_id CHAR(36) NULL,
  volunteer_name VARCHAR(200) NOT NULL,
  volunteer_email VARCHAR(190) NOT NULL,
  task VARCHAR(150) NOT NULL,
  task_date DATE NOT NULL,
  notes TEXT NULL,
  recorded_by_email VARCHAR(190) NULL,
  recorded_by_name VARCHAR(200) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_pl_app (application_id),
  KEY ix_pl_date (task_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_events (
  id CHAR(36) NOT NULL,
  event_name VARCHAR(200) NOT NULL,
  event_date DATE NULL,
  event_location VARCHAR(200) NULL,
  source_event_id VARCHAR(40) NULL,
  travel_filter TINYINT(1) NOT NULL DEFAULT 0,
  is_hidden TINYINT(1) NOT NULL DEFAULT 0,
  created_by_email VARCHAR(190) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_logs (
  id INT NOT NULL AUTO_INCREMENT,
  recruitment_event_id CHAR(36) NOT NULL,
  application_id CHAR(36) NOT NULL,
  volunteer_email VARCHAR(190) NULL,
  volunteer_name VARCHAR(200) NULL,
  contacted_date DATE NULL,
  method VARCHAR(10) NULL,
  responded VARCHAR(10) NULL,
  agreed VARCHAR(10) NULL,
  note TEXT NULL,
  attended VARCHAR(12) NULL,
  notified_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rl (recruitment_event_id, application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_templates (
  id INT NOT NULL AUTO_INCREMENT,
  internal_name VARCHAR(200) NOT NULL,
  category VARCHAR(30) NOT NULL DEFAULT 'general',
  subject_line VARCHAR(255) NOT NULL,
  body_html LONGTEXT NOT NULL,
  plain_text_summary TEXT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'draft',
  created_by_email VARCHAR(190) NULL,
  created_by_name VARCHAR(200) NULL,
  last_edited_by_email VARCHAR(190) NULL,
  last_edited_by_name VARCHAR(200) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ct_cat (category, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_sends (
  id INT NOT NULL AUTO_INCREMENT,
  template_id INT NOT NULL,
  template_name VARCHAR(200) NULL,
  category VARCHAR(30) NULL,
  filters_json TEXT NULL,
  recipient_count INT NOT NULL DEFAULT 0,
  recipient_emails_json TEXT NULL,
  event_data_json TEXT NULL,
  scheduled_at DATETIME NULL,
  sent_at DATETIME NULL,
  send_type VARCHAR(12) NOT NULL DEFAULT 'manual',
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  created_by_email VARCHAR(190) NULL,
  created_by_name VARCHAR(200) NULL,
  cancellation_reason TEXT NULL,
  result_json TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_cs_status (status, scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_logs (
  id INT NOT NULL AUTO_INCREMENT,
  send_id INT NOT NULL,
  template_id INT NULL,
  template_name VARCHAR(200) NULL,
  category VARCHAR(30) NULL,
  user_id CHAR(36) NULL,
  volunteer_email VARCHAR(190) NOT NULL,
  volunteer_name VARCHAR(200) NULL,
  sent_at DATETIME NOT NULL,
  trigger_type VARCHAR(12) NOT NULL DEFAULT 'manual',
  delivery_status VARCHAR(10) NOT NULL DEFAULT 'sent',
  notification_id INT NULL,
  in_app_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  subject_line_sent VARCHAR(255) NULL,
  body_html_sent LONGTEXT NULL,
  PRIMARY KEY (id),
  KEY ix_cl_email (volunteer_email),
  KEY ix_cl_send (send_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_rules (
  id INT NOT NULL AUTO_INCREMENT,
  rule_name VARCHAR(150) NOT NULL,
  trigger_type VARCHAR(40) NOT NULL,
  trigger_condition_field VARCHAR(60) NULL,
  trigger_condition_value VARCHAR(100) NULL,
  recipient_name VARCHAR(150) NULL,
  recipient_email VARCHAR(190) NOT NULL,
  recipient_whatsapp VARCHAR(40) NULL,
  channels_json TEXT NULL,
  email_subject_template VARCHAR(255) NULL,
  email_body_template LONGTEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  priority VARCHAR(10) NOT NULL DEFAULT 'normal',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
  id INT NOT NULL AUTO_INCREMENT,
  rule_id INT NULL,
  rule_name VARCHAR(150) NULL,
  trigger_type VARCHAR(40) NOT NULL,
  channel VARCHAR(20) NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  recipient_name VARCHAR(150) NULL,
  volunteer_email VARCHAR(190) NULL,
  volunteer_name VARCHAR(200) NULL,
  volunteer_category VARCHAR(10) NULL,
  subject VARCHAR(255) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_nl_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_time_logs (
  id INT NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  log_date DATE NOT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  seconds INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_atl_user (user_id, log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
