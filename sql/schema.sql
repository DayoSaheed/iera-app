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

CREATE TABLE IF NOT EXISTS volunteer_applications (
  id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  email VARCHAR(190) NOT NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  full_name VARCHAR(200) NULL,
  gender VARCHAR(10) NULL,
  phone VARCHAR(40) NULL,
  whatsapp VARCHAR(40) NULL,
  volunteer_category VARCHAR(10) NOT NULL DEFAULT 'Global',
  country VARCHAR(100) NULL,
  city VARCHAR(100) NULL,
  town VARCHAR(100) NULL,
  postal_code VARCHAR(20) NULL,
  date_of_birth DATE NULL,
  is_18_plus TINYINT(1) NULL,
  university VARCHAR(150) NULL,
  islamic_society_member TINYINT(1) NULL,
  islamic_society_name VARCHAR(150) NULL,
  involved_in_dawah TINYINT(1) NULL,
  dawah_involvement_description TEXT NULL,
  dawah_experience_length VARCHAR(40) NOT NULL DEFAULT 'No previous experience',
  skills_json TEXT NULL,
  custom_skill VARCHAR(255) NULL,
  previous_training TINYINT(1) NULL,
  previous_training_details TEXT NULL,
  hours_per_week VARCHAR(20) NULL,
  weekly_commitment TINYINT(1) NULL,
  preferred_days_json TEXT NULL,
  motivation TEXT NULL,
  role_interests_json TEXT NULL,
  languages_json TEXT NULL,
  given_shahadah_before TINYINT(1) NULL,
  shahadah_count INT NULL,
  social_media_json TEXT NULL,
  no_social_media TINYINT(1) NOT NULL DEFAULT 0,
  agreed_to_terms TINYINT(1) NOT NULL DEFAULT 0,
  agreed_data_protection TINYINT(1) NOT NULL DEFAULT 0,
  agreed_conflict_of_interest TINYINT(1) NOT NULL DEFAULT 0,
  agreement_signed_at DATETIME NULL,
  agreement_version VARCHAR(10) NULL,
  onboarding_stage TINYINT NOT NULL DEFAULT 1,
  submitted_at DATETIME NULL,
  onboarded_date DATE NULL,
  onboarded_approved TINYINT(1) NOT NULL DEFAULT 0,
  onboarded_approved_at DATETIME NULL,
  onboarded_approved_by VARCHAR(190) NULL,
  onboarding_approved_roles_json TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'New',
  reviewer VARCHAR(190) NULL,
  tags_json TEXT NULL,
  id_doc_1_id CHAR(36) NULL,
  id_doc_2_id CHAR(36) NULL,
  id_verification_status VARCHAR(10) NULL,
  id_rejection_reason TEXT NULL,
  id_approved_by VARCHAR(190) NULL,
  id_approved_at DATETIME NULL,
  id_doc_1_status VARCHAR(10) NOT NULL DEFAULT 'pending',
  id_doc_1_rejection_reason TEXT NULL,
  id_doc_2_status VARCHAR(10) NOT NULL DEFAULT 'pending',
  id_doc_2_rejection_reason TEXT NULL,
  cv_doc_id CHAR(36) NULL,
  call_scheduled TINYINT(1) NOT NULL DEFAULT 0,
  interview_status VARCHAR(20) NOT NULL DEFAULT 'not_scheduled',
  interview_date DATE NULL,
  interviewer_name VARCHAR(150) NULL,
  interview_outcome VARCHAR(10) NOT NULL DEFAULT 'pending',
  interview_notes TEXT NULL,
  certs_json TEXT NULL,
  mentor_agreement_agreed TINYINT(1) NOT NULL DEFAULT 0,
  nda_agreed TINYINT(1) NOT NULL DEFAULT 0,
  street_dawah_team VARCHAR(150) NULL,
  street_dawah_amir VARCHAR(150) NULL,
  street_dawah_amir_mobile VARCHAR(40) NULL,
  street_dawah_location VARCHAR(150) NULL,
  communication_preferences_json TEXT NULL,
  reliability_score DECIMAL(5,2) NULL,
  tour_restricted_seen TINYINT(1) NOT NULL DEFAULT 0,
  tour_full_seen TINYINT(1) NOT NULL DEFAULT 0,
  online_dawah_interest TINYINT(1) NOT NULL DEFAULT 0,
  online_dawah_interest_at DATETIME NULL,
  gorap_training_completion VARCHAR(20) NOT NULL DEFAULT 'not_started',
  gorap_training_declared_at DATETIME NULL,
  risk_assessment_status VARCHAR(20) NOT NULL DEFAULT 'Not Started',
  risk_assessment_stage VARCHAR(20) NOT NULL DEFAULT 'Requested',
  risk_assessment_outcome VARCHAR(10) NOT NULL DEFAULT 'pending',
  risk_assessor_name VARCHAR(150) NULL,
  risk_assessment_date DATE NULL,
  risk_assessment_notes TEXT NULL,
  risk_assessment_by VARCHAR(190) NULL,
  risk_assessment_notified_at DATETIME NULL,
  risk_assessment_reminder_sent_at DATETIME NULL,
  detected_country VARCHAR(100) NULL,
  is_inactive TINYINT(1) NOT NULL DEFAULT 0,
  inactive_reason TEXT NULL,
  inactive_at DATETIME NULL,
  last_activity_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_va_user (user_id),
  KEY ix_va_email (email),
  KEY ix_va_status (status, volunteer_category),
  CONSTRAINT fk_va_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agreement_signatures (
  id CHAR(36) NOT NULL,
  application_id CHAR(36) NOT NULL,
  volunteer_first_name VARCHAR(100) NULL,
  volunteer_last_name VARCHAR(100) NULL,
  volunteer_email VARCHAR(190) NOT NULL,
  typed_signature VARCHAR(200) NOT NULL,
  agreement_version VARCHAR(10) NOT NULL DEFAULT '1.0',
  agreement_text_snapshot LONGTEXT NOT NULL,
  signed_at DATETIME NOT NULL,
  ip_address VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_as_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS interview_slots (
  id CHAR(36) NOT NULL,
  slot_date DATE NOT NULL,
  slot_time VARCHAR(5) NOT NULL,
  duration_minutes INT NOT NULL DEFAULT 30,
  status VARCHAR(12) NOT NULL DEFAULT 'available',
  booked_application_id CHAR(36) NULL,
  booked_by_email VARCHAR(190) NULL,
  booked_by_name VARCHAR(200) NULL,
  booked_at DATETIME NULL,
  configured_by_email VARCHAR(190) NULL,
  notes TEXT NULL,
  reminder_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_is_date (slot_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_signup_requests (
  id CHAR(36) NOT NULL,
  user_id CHAR(36) NULL,
  full_name VARCHAR(200) NOT NULL,
  email VARCHAR(190) NOT NULL,
  requested_role VARCHAR(40) NOT NULL DEFAULT 'admin',
  reason TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  reviewed_by VARCHAR(190) NULL,
  reviewed_at DATETIME NULL,
  rejection_reason TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_asr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_notes (
  id INT NOT NULL AUTO_INCREMENT,
  application_id CHAR(36) NOT NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'note',
  author_email VARCHAR(190) NULL,
  author_name VARCHAR(200) NULL,
  content TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_an_app (application_id, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_onboarding_requirements (
  id CHAR(36) NOT NULL,
  application_id CHAR(36) NOT NULL,
  volunteer_email VARCHAR(190) NOT NULL,
  volunteer_name VARCHAR(200) NULL,
  requirement_key VARCHAR(40) NOT NULL,
  requirement_type VARCHAR(20) NOT NULL,
  role_source_json TEXT NULL,
  pathway VARCHAR(10) NULL,
  previously_completed TINYINT(1) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'declared',
  institution VARCHAR(200) NULL,
  institution_choice VARCHAR(30) NULL,
  completion_mode VARCHAR(20) NULL,
  certificate_received TINYINT(1) NULL,
  certificate_availability VARCHAR(10) NULL,
  evidence_doc_id CHAR(36) NULL,
  notes TEXT NULL,
  declared_at DATETIME NULL,
  verified_at DATETIME NULL,
  verified_by VARCHAR(190) NULL,
  rejection_reason TEXT NULL,
  review_note TEXT NULL,
  verification_history_json TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_oor (application_id, requirement_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_facebook_responses (
  application_id CHAR(36) NOT NULL,
  volunteer_email VARCHAR(190) NOT NULL,
  volunteer_name VARCHAR(200) NULL,
  facebook_response VARCHAR(120) NOT NULL,
  submitted_at DATETIME NOT NULL,
  PRIMARY KEY (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_volunteer_forwards (
  id CHAR(36) NOT NULL,
  application_id CHAR(36) NOT NULL,
  volunteer_name VARCHAR(200) NULL,
  volunteer_email VARCHAR(190) NOT NULL,
  assigned_team VARCHAR(150) NULL,
  forwarded_at DATETIME NULL,
  forwarded_by VARCHAR(190) NULL,
  activation_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ovf_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_applicant_dispositions (
  id CHAR(36) NOT NULL,
  application_id CHAR(36) NOT NULL,
  volunteer_name VARCHAR(200) NULL,
  volunteer_email VARCHAR(190) NOT NULL,
  disposition VARCHAR(12) NOT NULL,
  reason_type VARCHAR(40) NULL,
  reason_notes TEXT NULL,
  recorded_by_email VARCHAR(190) NULL,
  recorded_by_name VARCHAR(200) NULL,
  last_edited_by_email VARCHAR(190) NULL,
  last_edited_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_oad_app (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_modules (
  id CHAR(36) NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  video_url VARCHAR(500) NULL,
  category VARCHAR(60) NULL,
  duration_minutes INT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_completions (
  id INT NOT NULL AUTO_INCREMENT,
  module_id CHAR(36) NULL,
  user_id CHAR(36) NOT NULL,
  course_name VARCHAR(200) NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'app',
  completed_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_tc_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_awards (
  id INT NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  course_name VARCHAR(200) NOT NULL,
  badge VARCHAR(100) NULL,
  xp_awarded INT NOT NULL DEFAULT 0,
  awarded_by VARCHAR(20) NOT NULL DEFAULT 'admin_manual',
  wp_user_id VARCHAR(40) NULL,
  notified TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ta_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS volunteer_reports (
  id INT NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  report_date DATE NOT NULL,
  hours DECIMAL(5,2) NOT NULL DEFAULT 0,
  activity_type VARCHAR(40) NOT NULL,
  summary TEXT NOT NULL,
  reflection TEXT NULL,
  photo_doc_id CHAR(36) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_vr_user (user_id, report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_posts (
  id INT NOT NULL AUTO_INCREMENT,
  user_id CHAR(36) NOT NULL,
  author_name VARCHAR(200) NOT NULL,
  content TEXT NULL,
  images_json TEXT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'update',
  group_slug VARCHAR(40) NULL,
  likes_json TEXT NULL,
  hidden TINYINT(1) NOT NULL DEFAULT 0,
  pinned TINYINT(1) NOT NULL DEFAULT 0,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_cp_created (hidden, pinned, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_comments (
  id INT NOT NULL AUTO_INCREMENT,
  post_id INT NOT NULL,
  user_id CHAR(36) NOT NULL,
  author_name VARCHAR(200) NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_pc_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_memberships (
  user_id CHAR(36) NOT NULL,
  group_slug VARCHAR(40) NOT NULL,
  group_name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, group_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS direct_messages (
  id INT NOT NULL AUTO_INCREMENT,
  from_user_id CHAR(36) NOT NULL,
  from_name VARCHAR(200) NOT NULL,
  to_user_id CHAR(36) NOT NULL,
  content TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_dm_to (to_user_id, is_read),
  KEY ix_dm_from (from_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_events (
  id INT NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  event_type VARCHAR(20) NOT NULL DEFAULT 'dawah',
  event_date DATE NOT NULL,
  end_date DATE NULL,
  event_time VARCHAR(40) NULL,
  location VARCHAR(255) NULL,
  cover_doc_id CHAR(36) NULL,
  organizer VARCHAR(150) NULL,
  group_slug VARCHAR(40) NULL,
  rsvp_json TEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'upcoming',
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ce_date (status, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stories (
  id INT NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  excerpt TEXT NULL,
  content LONGTEXT NULL,
  image_url VARCHAR(500) NULL,
  image_doc_id CHAR(36) NULL,
  author VARCHAR(150) NULL,
  published_date DATE NULL,
  category VARCHAR(40) NULL,
  tags_json TEXT NULL,
  country VARCHAR(100) NULL,
  continent VARCHAR(20) NULL,
  source VARCHAR(30) NOT NULL DEFAULT 'volunteer_submission',
  source_url VARCHAR(500) NULL,
  wp_post_id VARCHAR(20) NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'pending',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  submitted_by CHAR(36) NULL,
  activity_type VARCHAR(40) NULL,
  permission_granted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_st_status (status, published_date),
  KEY ix_st_wp (wp_post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback_reports (
  id INT NOT NULL AUTO_INCREMENT,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  page_affected VARCHAR(255) NULL,
  issue_type VARCHAR(30) NOT NULL DEFAULT 'Bug',
  priority VARCHAR(10) NOT NULL DEFAULT 'Medium',
  status VARCHAR(20) NOT NULL DEFAULT 'New',
  device_type VARCHAR(10) NULL,
  browser VARCHAR(30) NULL,
  screen_size VARCHAR(20) NULL,
  page_url VARCHAR(500) NULL,
  screenshot_doc_id CHAR(36) NULL,
  submitted_by CHAR(36) NULL,
  submitted_by_name VARCHAR(200) NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  assigned_to VARCHAR(190) NULL,
  admin_notes TEXT NULL,
  xp_awarded TINYINT(1) NOT NULL DEFAULT 0,
  votes_json TEXT NULL,
  comments_json TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_fr_status (status, issue_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(40) NOT NULL,
  applied_at DATETIME NOT NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version, applied_at) VALUES ('0001_initial', UTC_TIMESTAMP()), ('0002_email_log_driver', UTC_TIMESTAMP()), ('0003_connect_core', UTC_TIMESTAMP()), ('0004_connect_applications', UTC_TIMESTAMP()), ('0005_connect_volunteer_area', UTC_TIMESTAMP()), ('0006_connect_admin', UTC_TIMESTAMP());

SET FOREIGN_KEY_CHECKS = 1;
