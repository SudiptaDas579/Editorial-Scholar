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

-- ═════════════════════════════════════════════════════════════════════════════
--  The Editorial Scholar — Test Prep SQL Migration
--  Run AFTER the base database.sql schema has been applied.
--  Safe to re-run (all statements use IF NOT EXISTS / ON DUPLICATE KEY).
-- ═════════════════════════════════════════════════════════════════════════════

-- ── Mock Exam Registrations ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS test_prep_mock_registrations (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    mock_id    INT UNSIGNED NOT NULL,
    status     ENUM('registered','cancelled') NOT NULL DEFAULT 'registered',
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_mock (user_id, mock_id),
    INDEX idx_user   (user_id),
    INDEX idx_mock   (mock_id),
    INDEX idx_status (status),
    FOREIGN KEY fk_mr_user (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Per-Module Completion Log ─────────────────────────────────────────────────
--  Stores exactly which modules each user has completed.
--  Progress % in test_prep_progress is derived from this table by the API.
CREATE TABLE IF NOT EXISTS test_prep_module_completions (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    exam_key     VARCHAR(20)  NOT NULL,   -- 'ielts' | 'gre' | 'toefl'
    module_slug  VARCHAR(100) NOT NULL,   -- e.g. 'listening-section-1'
    completed_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_exam_module (user_id, exam_key, module_slug),
    INDEX idx_user     (user_id),
    INDEX idx_exam_key (exam_key),
    FOREIGN KEY fk_mc_user (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Materials / Module Library ────────────────────────────────────────────────
--  Each row is one piece of prep content: video, PDF, article, audio, quiz.
CREATE TABLE IF NOT EXISTS test_prep_materials (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    exam_key      VARCHAR(20)      NOT NULL,          -- 'ielts' | 'gre' | 'toefl'
    module_slug   VARCHAR(100)     NOT NULL,          -- used for completion tracking
    title         VARCHAR(255)     NOT NULL,
    material_type ENUM('video','pdf','article','audio','quiz','interactive')
                                   NOT NULL DEFAULT 'article',
    icon          VARCHAR(60)      NOT NULL DEFAULT 'ri-file-line',
    meta          VARCHAR(255)     NULL,              -- e.g. "PDF • 4.2 MB • Advanced"
    section_tag   VARCHAR(80)      NULL,              -- e.g. "Listening", "Quantitative"
    level         ENUM('Beginner','Intermediate','Advanced','All Levels')
                                   NOT NULL DEFAULT 'All Levels',
    duration_min  SMALLINT UNSIGNED NULL,             -- runtime/read-time in minutes
    file_url      VARCHAR(500)     NULL,              -- download / embed URL
    sort_order    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_free       TINYINT(1)       NOT NULL DEFAULT 1,
    created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_exam    (exam_key),
    INDEX idx_sort    (exam_key, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═════════════════════════════════════════════════════════════════════════════
--  SEED: 31 materials across IELTS (12), GRE (10), TOEFL (9)
--  NOTE: written as individual INSERT IGNORE statements (not one multi-row
--  VALUES list) on purpose. A single multi-row INSERT fails (and rolls back
--  the whole statement) if even one row has a problem — e.g. a stray
--  non-ASCII character (•, –) being mis-encoded by a client that isn't
--  sending utf8mb4, or a bad NULL literal. Splitting into one statement per
--  row means a bad row fails by itself and everything else still loads.
--  All separator characters below are plain ASCII (-, |) instead of bullets
--  or en-dashes for the same reason.
-- ═════════════════════════════════════════════════════════════════════════════

-- ── IELTS (12 modules) ────────────────────────────────────────────────────
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-intro', 'IELTS Overview and Exam Format', 'video', 'ri-play-circle-line', 'Video - 18 min - All Levels', 'Overview', 'All Levels', 18, '#', 1);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-listening-1', 'Listening Section 1 - Form Completion', 'audio', 'ri-headphone-line', 'Audio Practice - 22:45 - Beginner', 'Listening', 'Beginner', 23, '#', 2);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-listening-2', 'Listening Section 3 and 4 - Academic Drills', 'audio', 'ri-headphone-line', 'Audio Practice - 30 min - Advanced', 'Listening', 'Advanced', 30, '#', 3);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-reading-skills', 'Reading Skimming and Scanning Masterclass', 'video', 'ri-video-line', 'Video Lecture - 24 min - Intermediate', 'Reading', 'Intermediate', 24, '#', 4);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-reading-tfng', 'True / False / Not Given Strategy', 'article', 'ri-article-line', 'Interactive Article - 10 min read', 'Reading', 'Intermediate', 10, '#', 5);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-writing-task1', 'Writing Task 1 - Graph and Chart Templates', 'pdf', 'ri-file-pdf-line', 'PDF Document - 3.8 MB - Intermediate', 'Writing', 'Intermediate', NULL, '#', 6);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-writing-task2', 'Writing Task 2 - Band 9 Sample Essays', 'article', 'ri-article-line', 'Interactive Article - 8 min read', 'Writing', 'Advanced', 8, '#', 7);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-writing-coherence', 'Coherence and Cohesion Scoring Breakdown', 'video', 'ri-video-line', 'Video Lecture - 16 min - All Levels', 'Writing', 'All Levels', 16, '#', 8);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-speaking-parts', 'Speaking Parts 1-3 Full Walkthrough', 'video', 'ri-video-line', 'Video Lecture - 28 min - Intermediate', 'Speaking', 'Intermediate', 28, '#', 9);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-speaking-fluency', 'Fluency and Pronunciation: Band Descriptors', 'article', 'ri-article-line', 'Article - 6 min read - All Levels', 'Speaking', 'All Levels', 6, '#', 10);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-vocab-academic', 'Academic Vocabulary for IELTS Band 7+', 'pdf', 'ri-file-pdf-line', 'PDF Wordlist - 2.1 MB - Advanced', 'Vocabulary', 'Advanced', NULL, '#', 11);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('ielts', 'ielts-full-mock', 'Full IELTS Mock Test (Academic) with Answer Key', 'quiz', 'ri-questionnaire-line', 'Mock Exam - 2 hrs 45 min - All Levels', 'Mock Test', 'All Levels', 165, '#', 12);

-- ── GRE (10 modules) ──────────────────────────────────────────────────────
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-intro', 'GRE General Test Overview', 'video', 'ri-play-circle-line', 'Video - 14 min - All Levels', 'Overview', 'All Levels', 14, '#', 1);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-verbal-vocab', 'GRE Vocabulary: High-Frequency Words 500+', 'pdf', 'ri-file-pdf-line', 'PDF Flashcards - 5.1 MB - All Levels', 'Verbal', 'All Levels', NULL, '#', 2);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-verbal-reading', 'Critical Reading: Argument Structure', 'article', 'ri-article-line', 'Article - 12 min read - Intermediate', 'Verbal', 'Intermediate', 12, '#', 3);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-verbal-equivalence', 'Text Completion and Sentence Equivalence', 'interactive', 'ri-drag-move-2-line', 'Interactive Drill - 45 questions', 'Verbal', 'Intermediate', NULL, '#', 4);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-quant-strategy', 'GRE Quantitative Strategy Guide', 'pdf', 'ri-file-pdf-line', 'PDF Document - 4.2 MB - Advanced', 'Quantitative', 'Advanced', NULL, '#', 5);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-quant-algebra', 'Algebra and Number Properties Deep Dive', 'video', 'ri-video-line', 'Video Lecture - 35 min - Intermediate', 'Quantitative', 'Intermediate', 35, '#', 6);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-quant-geometry', 'Geometry and Data Analysis Crash Course', 'video', 'ri-video-line', 'Video Lecture - 29 min - Intermediate', 'Quantitative', 'Intermediate', 29, '#', 7);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-aw-issue', 'Analytical Writing: Issue Task Templates', 'article', 'ri-article-line', 'Article + Samples - 15 min - Advanced', 'Analytical Writing', 'Advanced', 15, '#', 8);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-aw-argument', 'Argument Task: Finding Logical Flaws', 'video', 'ri-video-line', 'Video Lecture - 22 min - Advanced', 'Analytical Writing', 'Advanced', 22, '#', 9);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('gre', 'gre-full-mock', 'GRE Full-Length Adaptive Mock Test', 'quiz', 'ri-questionnaire-line', 'Mock Exam - 1 hr 58 min - All Levels', 'Mock Test', 'All Levels', 118, '#', 10);

-- ── TOEFL (9 modules) ─────────────────────────────────────────────────────
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('toefl', 'toefl-intro', 'TOEFL iBT Format and Scoring Guide', 'video', 'ri-play-circle-line', 'Video - 12 min - All Levels', 'Overview', 'All Levels', 12, '#', 1);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('toefl', 'toefl-reading-strategy', 'Reading Passage Strategy: Inference and Detail', 'article', 'ri-article-line', 'Article - 9 min read - Intermediate', 'Reading', 'Intermediate', 9, '#', 2);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('toefl', 'toefl-listening-notes', 'Listening: Note-Taking Techniques', 'audio', 'ri-headphone-line', 'Audio + Notes - 25 min - All Levels', 'Listening', 'All Levels', 25, '#', 3);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('toefl', 'toefl-speaking-t1', 'Speaking Task 1: Independent Response Method', 'video', 'ri-video-line', 'TOEFL Speaking - 14:20 - Instructional', 'Speaking', 'Intermediate', 14, '#', 4);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('toefl', 'toefl-speaking-t2t4', 'Speaking Tasks 2-4: Integrated Strategies', 'video', 'ri-video-line', 'Video Lecture - 32 min - Advanced', 'Speaking', 'Advanced', 32, '#', 5);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('toefl', 'toefl-writing-integrated', 'Integrated Writing Task: Read + Listen + Write', 'article', 'ri-article-line', 'Interactive Article - 11 min - Advanced', 'Writing', 'Advanced', 11, '#', 6);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('toefl', 'toefl-writing-academic', 'Academic Discussion Task: Scoring Rubric', 'pdf', 'ri-file-pdf-line', 'PDF Rubric and Samples - 2.4 MB - Advanced', 'Writing', 'Advanced', NULL, '#', 7);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('toefl', 'toefl-vocab-academic', 'Academic Word List for TOEFL 100+', 'pdf', 'ri-file-pdf-line', 'PDF Wordlist - 1.9 MB - Intermediate', 'Vocabulary', 'Intermediate', NULL, '#', 8);
INSERT IGNORE INTO test_prep_materials (exam_key, module_slug, title, material_type, icon, meta, section_tag, level, duration_min, file_url, sort_order) VALUES ('toefl', 'toefl-full-mock', 'TOEFL iBT Full Practice Test', 'quiz', 'ri-questionnaire-line', 'Mock Exam - approx 2 hrs - All Levels', 'Mock Test', 'All Levels', 120, '#', 9);
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

    PRIMARY KEY (id),
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