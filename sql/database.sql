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
