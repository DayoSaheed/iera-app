-- iERA Connect: impact reports, community, messages, events, stories, feedback
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
