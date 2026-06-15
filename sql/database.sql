-- ═══════════════════════════════════════════════════════════════
--  The Editorial Scholar — Core Database Schema
-- ═══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS editorial_scholar
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE editorial_scholar;

-- ──────────────────────────────────────────────
--  USERS  (role: user | admin | advisor)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(150)     NOT NULL,
  email         VARCHAR(255)     NOT NULL UNIQUE,
  password_hash VARCHAR(255)     NOT NULL,
  role          ENUM('user','admin','advisor') NOT NULL DEFAULT 'user',
  status        ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  email_verified TINYINT(1)      NOT NULL DEFAULT 0,
  created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login    TIMESTAMP        NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX idx_email  (email),
  INDEX idx_role   (role),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
--  ADVISOR APPLICATIONS
--  Pending until admin approves → advisor can log in
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS advisor_applications (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED  NOT NULL,          -- FK to users.id
  specialization  VARCHAR(200)  NOT NULL,
  qualifications  TEXT          NOT NULL,
  experience_yrs  TINYINT       NOT NULL DEFAULT 0,
  bio             TEXT          NOT NULL,
  linkedin_url    VARCHAR(500)  NULL,
  cv_filename     VARCHAR(300)  NULL,              -- optional CV upload
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note      TEXT          NULL,              -- rejection/approval note
  reviewed_by     INT UNSIGNED  NULL,              -- FK to users.id (admin)
  reviewed_at     TIMESTAMP     NULL DEFAULT NULL,
  submitted_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_adv_user  (user_id)      REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY fk_adv_admin (reviewed_by)  REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_adv_status (status),
  INDEX idx_adv_user   (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
--  ADVISOR PROFILES  (created after approval)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS advisor_profiles (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED  NOT NULL UNIQUE,
  specialization  VARCHAR(200)  NOT NULL,
  bio             TEXT          NULL,
  experience_yrs  TINYINT       NOT NULL DEFAULT 0,
  linkedin_url    VARCHAR(500)  NULL,
  rating          DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
  total_reviews   INT UNSIGNED  NOT NULL DEFAULT 0,
  available       TINYINT(1)    NOT NULL DEFAULT 1,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_prof_user (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
--  USER SESSIONS  (server-side session tracking)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_sessions (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED  NOT NULL,
  token       CHAR(64)      NOT NULL UNIQUE,
  ip_address  VARCHAR(45)   NULL,
  user_agent  TEXT          NULL,
  expires_at  TIMESTAMP     NOT NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_sess_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token   (token),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
--  PASSWORD RESET TOKENS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED  NOT NULL,
  token      CHAR(64)      NOT NULL UNIQUE,
  expires_at TIMESTAMP     NOT NULL,
  used       TINYINT(1)    NOT NULL DEFAULT 0,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY fk_pr_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pr_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
--  DEFAULT ADMIN ACCOUNT
--  Password: Admin@2026  (bcrypt)  — CHANGE ON FIRST LOGIN
-- ──────────────────────────────────────────────
INSERT INTO users (full_name, email, password_hash, role, status, email_verified)
VALUES (
  'Site Administrator',
  'admin@editorialscholar.com',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.ucrIuRssi', -- Admin@2026
  'admin',
  'active',
  1
) ON DUPLICATE KEY UPDATE id = id;

-- ──────────────────────────────────────────────
--  RESEARCH INSTITUTIONS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS research_institutions (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name        VARCHAR(255)  NOT NULL,
  qs_rank     INT UNSIGNED  NOT NULL,
  country     VARCHAR(100)  NOT NULL,
  description TEXT          NOT NULL,
  image_url   VARCHAR(500)  NOT NULL,
  PRIMARY KEY (id),
  INDEX idx_country (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
--  RESEARCH PROGRAMS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS research_programs (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  institution_id   INT UNSIGNED  NOT NULL,
  title            VARCHAR(255)  NOT NULL,
  major            VARCHAR(100)  NOT NULL,
  degree_level     ENUM('Undergraduate', 'Postgraduate', 'Doctorate') NOT NULL,
  tuition_fee      INT UNSIGNED  NOT NULL, -- annual budget/fee in GBP/EUR/USD equivalent
  scholarship_type VARCHAR(150)  NOT NULL, -- e.g. "Full Scholarship", "75% Tuition Waiver"
  intake_info      VARCHAR(100)  NOT NULL, -- e.g. "Intake: Sept 2025"
  prospectus_url   VARCHAR(500)  NOT NULL,
  action_label     VARCHAR(100)  NOT NULL DEFAULT 'Match Eligibility',
  PRIMARY KEY (id),
  FOREIGN KEY fk_prog_inst (institution_id) REFERENCES research_institutions(id) ON DELETE CASCADE,
  INDEX idx_major (major),
  INDEX idx_degree (degree_level),
  INDEX idx_fee (tuition_fee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
--  SEED RESEARCH DATA
-- ──────────────────────────────────────────────
INSERT INTO research_institutions (id, name, qs_rank, country, description, image_url) VALUES
(1, 'London School of Political Science', 4, 'United Kingdom', 'Renowned for its focus on social sciences, providing a rigorous intellectual environment for future global leaders.', 'https://picsum.photos/300/200'),
(2, 'Zurich Federal Institute', 12, 'Switzerland', 'A hub for innovation and scientific excellence offering world-class facilities.', 'https://picsum.photos/300/201'),
(3, 'National University of Arts', 21, 'Singapore', 'Bridging Eastern and Western artistic traditions fostering creativity.', 'https://picsum.photos/300/202'),
(4, 'Imperial College of Technology', 6, 'United Kingdom', 'Specializing in science, engineering, medicine, and business to solve global challenges.', 'https://picsum.photos/300/203'),
(5, 'National University of Science', 8, 'Singapore', 'A leading global university centered in Asia, offering a global approach to education and research.', 'https://picsum.photos/300/204');

INSERT INTO research_programs (institution_id, title, major, degree_level, tuition_fee, scholarship_type, intake_info, prospectus_url, action_label) VALUES
(1, 'Economics & Philosophy BSc', 'Economics & Philosophy', 'Undergraduate', 28000, 'Full Scholarship', 'Intake: Sept 2025', '#', 'Match Eligibility'),
(2, 'Advanced Computing MSc', 'Computer Science', 'Postgraduate', 1500, 'Editorial Choice', 'Intake: Oct 2025', '#', 'Check Application'),
(3, 'Fine Arts PhD', 'Fine Arts', 'Doctorate', 35000, '75% Tuition Waiver', 'Intake: Aug 2025', '#', 'Join Webinar'),
(4, 'Biomedical Engineering MSc', 'Biomedical Science', 'Postgraduate', 32000, 'Full Scholarship', 'Intake: Sept 2025', '#', 'Match Eligibility'),
(5, 'Quantitative Economics PhD', 'Economics & Philosophy', 'Doctorate', 42000, 'Full Scholarship', 'Intake: Jan 2026', '#', 'Match Eligibility');

