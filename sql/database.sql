-- ═══════════════════════════════════════════════════════════════
--  The Editorial Scholar — Core Database Schema
-- ═══════════════════════════════════════════════════════════════



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

UPDATE users 
SET password_hash = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.ucrIuRssi'
WHERE email = 'admin@editorialscholar.com';


-- ─────────────────────────────────────────────────────
--  Test Prep Tables Migration
--  Add to your database.sql or run separately.
-- ─────────────────────────────────────────────────────

-- Resource Bookmarks
CREATE TABLE IF NOT EXISTS test_prep_bookmarks (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    resource_id INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_resource (user_id, resource_id),
    INDEX idx_user (user_id),
    FOREIGN KEY fk_bm_user (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Download Log
CREATE TABLE IF NOT EXISTS test_prep_downloads (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    resource_id   INT UNSIGNED NOT NULL,
    downloaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user (user_id),
    INDEX idx_resource (resource_id),
    FOREIGN KEY fk_dl_user (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exam Progress Tracker
CREATE TABLE IF NOT EXISTS test_prep_progress (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    exam_key     VARCHAR(20)  NOT NULL,   -- 'ielts' | 'gre' | 'toefl'
    progress_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 0–100
    last_module  VARCHAR(200) NULL,       -- name of last completed module
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_exam (user_id, exam_key),
    INDEX idx_user (user_id),
    FOREIGN KEY fk_prog_user (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advisor Consultation Bookings
CREATE TABLE IF NOT EXISTS test_prep_consultations (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    full_name    VARCHAR(150) NOT NULL,
    exam_target  VARCHAR(50)  NOT NULL,   -- 'IELTS' | 'GRE' | 'TOEFL' | 'Multiple / Unsure'
    consult_date DATE         NOT NULL,
    consult_time VARCHAR(20)  NOT NULL,   -- e.g. '10:00 AM'
    notes        TEXT         NULL,
    status       ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user (user_id),
    INDEX idx_date (consult_date),
    INDEX idx_status (status),
    FOREIGN KEY fk_con_user (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- =====================================================
--  VISA MODULE — Full Schema
--  Drop order respects FK dependencies
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS visa_audit_requests;
DROP TABLE IF EXISTS visa_status_history;
DROP TABLE IF EXISTS visa_documents;
DROP TABLE IF EXISTS visa_faq;
DROP TABLE IF EXISTS visa_applications;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
--  1. VISA APPLICATIONS
-- =====================================================

CREATE TABLE visa_applications (
    id                  INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    application_code    VARCHAR(30)         NOT NULL,
    user_id             INT UNSIGNED        NULL,

    full_name           VARCHAR(150)        NOT NULL,
    email               VARCHAR(255)        NOT NULL,
    phone               VARCHAR(50)         NOT NULL,

    dob                 DATE                NULL,
    gender              ENUM('Male','Female','Other') NULL,

    nationality         VARCHAR(100)        NOT NULL,
    passport_number     VARCHAR(100)        NOT NULL,
    passport_expiry     DATE                NOT NULL,

    degree              VARCHAR(150)        NULL,
    institution         VARCHAR(255)        NULL,
    cgpa                VARCHAR(20)         NULL,
    passing_year        YEAR                NULL,

    destination_country VARCHAR(100)        NOT NULL,
    university_name     VARCHAR(255)        NOT NULL,
    course_name         VARCHAR(255)        NOT NULL,
    intake              VARCHAR(100)        NOT NULL,

    sponsor_name        VARCHAR(150)        NULL,
    sponsor_relation    VARCHAR(100)        NULL,
    bank_balance        DECIMAL(15,2)       NOT NULL DEFAULT 0.00,
    ielts_score         DECIMAL(4,2)        NOT NULL DEFAULT 0.00,

    visa_status         ENUM(
                            'submitted',
                            'documents_pending',
                            'under_review',
                            'interview',
                            'processing',
                            'approved',
                            'rejected'
                        )                   NOT NULL DEFAULT 'submitted',

    admin_notes         TEXT                NULL,

    created_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_application_code (application_code),
    INDEX idx_email      (email),
    INDEX idx_status     (visa_status),
    INDEX idx_user       (user_id),

    CONSTRAINT fk_visa_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  2. VISA DOCUMENTS
-- =====================================================

CREATE TABLE visa_documents (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    application_id  INT UNSIGNED    NOT NULL,

    document_type   ENUM(
                        'passport',
                        'photo',
                        'transcript',
                        'certificate',
                        'bank_statement',
                        'offer_letter',
                        'sop',
                        'ielts'
                    )               NOT NULL,

    original_name   VARCHAR(255)    NOT NULL,
    stored_name     VARCHAR(255)    NOT NULL,
    file_path       VARCHAR(500)    NOT NULL,

    uploaded_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_doc_application (application_id),

    CONSTRAINT fk_doc_application
        FOREIGN KEY (application_id)
        REFERENCES visa_applications(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  3. VISA STATUS HISTORY
-- =====================================================

CREATE TABLE visa_status_history (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    application_id  INT UNSIGNED    NOT NULL,
    status_name     VARCHAR(100)    NOT NULL,
    remarks         TEXT            NULL,
    changed_by      INT UNSIGNED    NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_hist_application (application_id),

    CONSTRAINT fk_hist_application
        FOREIGN KEY (application_id)
        REFERENCES visa_applications(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_hist_changed_by
        FOREIGN KEY (changed_by)
        REFERENCES users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  4. VISA AUDIT REQUESTS
-- =====================================================

CREATE TABLE visa_audit_requests (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    application_id  INT UNSIGNED    NOT NULL,

    issue_type      VARCHAR(150)    NOT NULL,
    description     TEXT            NOT NULL,

    priority        ENUM('low','medium','high')              NOT NULL DEFAULT 'medium',
    status          ENUM('open','reviewing','resolved','closed') NOT NULL DEFAULT 'open',

    admin_reply     TEXT            NULL,

    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_audit_application (application_id),
    INDEX idx_audit_status      (status),

    CONSTRAINT fk_audit_application
        FOREIGN KEY (application_id)
        REFERENCES visa_applications(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  5. VISA FAQ
-- =====================================================

CREATE TABLE visa_faq (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    question        VARCHAR(500)    NOT NULL,
    answer          TEXT            NOT NULL,
    display_order   INT             NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),sa
    INDEX idx_faq_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
--  DEFAULT FAQ ROWS
-- =====================================================

INSERT INTO visa_faq (question, answer, display_order) VALUES
('How long does visa processing take?',   'Usually between 2 and 12 weeks depending on the destination country.',    1),
('Can I upload documents later?',         'Yes, documents can be uploaded after your application has been submitted.', 2),
('Can I track my application?',           'Yes, every application receives a unique tracking code upon submission.',   3),
('Can I request a document audit?',       'Yes, audit requests can be submitted directly from the visa portal.',       4);