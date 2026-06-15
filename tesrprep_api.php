<?php
// ─────────────────────────────────────────────────────
//  testprep_api.php  — Test Prep AJAX API
//
//  Endpoints (POST JSON):
//    action: "bookmark"          — add/remove a resource bookmark
//    action: "log_download"      — log a resource download
//    action: "book_consultation" — book an advisor consultation
//    action: "update_progress"   — update exam progress % (called from module player)
//
//  All endpoints require an active session (user must be logged in),
//  except book_consultation which returns a friendly error if not logged in.
// ─────────────────────────────────────────────────────
session_start();
require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

header('Content-Type: application/json');

// ── Only accept POST ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Parse JSON body ───────────────────────────────────
$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true);
$action = $body['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No action specified.']);
    exit;
}

$isLoggedIn = is_logged_in();
$userId     = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;

// ─────────────────────────────────────────────────────
//  Helper: ensure test prep tables exist
// ─────────────────────────────────────────────────────
function ensureTables(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    // Bookmarks
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS test_prep_bookmarks (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     INT UNSIGNED NOT NULL,
            resource_id INT UNSIGNED NOT NULL,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_resource (user_id, resource_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Download log
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS test_prep_downloads (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     INT UNSIGNED NOT NULL,
            resource_id INT UNSIGNED NOT NULL,
            downloaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user (user_id),
            INDEX idx_resource (resource_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Progress
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS test_prep_progress (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id      INT UNSIGNED NOT NULL,
            exam_key     VARCHAR(20) NOT NULL,
            progress_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_module  VARCHAR(200) NULL,
            updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_exam (user_id, exam_key),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Consultations
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS test_prep_consultations (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id      INT UNSIGNED NOT NULL,
            full_name    VARCHAR(150) NOT NULL,
            exam_target  VARCHAR(50) NOT NULL,
            consult_date DATE NOT NULL,
            consult_time VARCHAR(20) NOT NULL,
            notes        TEXT NULL,
            status       ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user (user_id),
            INDEX idx_date (consult_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $done = true;
}

// ─────────────────────────────────────────────────────
//  ROUTE
// ─────────────────────────────────────────────────────
try {
    $pdo = getDB();
    ensureTables($pdo);

    switch ($action) {

        // ── BOOKMARK ──────────────────────────────────
        case 'bookmark':
            if (!$isLoggedIn) {
                echo json_encode(['success' => false, 'message' => 'Please sign in to bookmark resources.']);
                exit;
            }

            $resourceId = (int)($body['resource_id'] ?? 0);
            $mode       = $body['mode'] ?? 'add'; // 'add' | 'remove'

            if ($resourceId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid resource.']);
                exit;
            }

            if ($mode === 'add') {
                $stmt = $pdo->prepare(
                    'INSERT IGNORE INTO test_prep_bookmarks (user_id, resource_id) VALUES (?, ?)'
                );
                $stmt->execute([$userId, $resourceId]);
                echo json_encode(['success' => true, 'mode' => 'added']);
            } else {
                $stmt = $pdo->prepare(
                    'DELETE FROM test_prep_bookmarks WHERE user_id = ? AND resource_id = ?'
                );
                $stmt->execute([$userId, $resourceId]);
                echo json_encode(['success' => true, 'mode' => 'removed']);
            }
            break;

        // ── LOG DOWNLOAD ──────────────────────────────
        case 'log_download':
            if (!$isLoggedIn) {
                // Not logged in — silently succeed (no tracking)
                echo json_encode(['success' => true]);
                exit;
            }

            $resourceId = (int)($body['resource_id'] ?? 0);
            if ($resourceId > 0) {
                $stmt = $pdo->prepare(
                    'INSERT INTO test_prep_downloads (user_id, resource_id) VALUES (?, ?)'
                );
                $stmt->execute([$userId, $resourceId]);
            }
            echo json_encode(['success' => true]);
            break;

        // ── BOOK CONSULTATION ─────────────────────────
        case 'book_consultation':
            if (!$isLoggedIn) {
                echo json_encode(['success' => false, 'message' => 'Please sign in to book a consultation.']);
                exit;
            }

            $name  = trim($body['name']  ?? '');
            $date  = trim($body['date']  ?? '');
            $time  = trim($body['time']  ?? '');
            $exam  = trim($body['exam']  ?? '');
            $notes = trim($body['notes'] ?? '');

            // Validate
            if (!$name || !$date || !$time || !$exam) {
                echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
                exit;
            }
            // Basic date validation — must be today or future
            if (strtotime($date) < strtotime('today')) {
                echo json_encode(['success' => false, 'message' => 'Please select a future date.']);
                exit;
            }
            // Allowed exam values
            $allowedExams = ['IELTS','GRE','TOEFL','Multiple / Unsure'];
            if (!in_array($exam, $allowedExams, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid exam selection.']);
                exit;
            }
            // Check for duplicate booking on same date+time
            $check = $pdo->prepare(
                'SELECT id FROM test_prep_consultations
                 WHERE user_id = ? AND consult_date = ? AND consult_time = ?
                   AND status != "cancelled"'
            );
            $check->execute([$userId, $date, $time]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'You already have a booking at this date and time.']);
                exit;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO test_prep_consultations
                 (user_id, full_name, exam_target, consult_date, consult_time, notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $name, $exam, $date, $time, $notes ?: null]);

            echo json_encode(['success' => true, 'message' => 'Consultation booked successfully.']);
            break;

        // ── UPDATE PROGRESS ───────────────────────────
        // Called by the module player (future feature).
        case 'update_progress':
            if (!$isLoggedIn) {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
                exit;
            }

            $examKey    = strtolower(trim($body['exam_key']    ?? ''));
            $pct        = (int)($body['progress_pct']         ?? 0);
            $lastModule = trim($body['last_module']            ?? '');

            $allowedKeys = ['ielts','gre','toefl'];
            if (!in_array($examKey, $allowedKeys, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid exam key.']);
                exit;
            }
            $pct = max(0, min(100, $pct));

            $stmt = $pdo->prepare(
                'INSERT INTO test_prep_progress (user_id, exam_key, progress_pct, last_module)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   progress_pct = VALUES(progress_pct),
                   last_module  = VALUES(last_module)'
            );
            $stmt->execute([$userId, $examKey, $pct, $lastModule ?: null]);

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action: ' . htmlspecialchars($action)]);
            break;
    }

} catch (PDOException $e) {
    error_log('testprep_api PDO error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
} catch (Throwable $e) {
    error_log('testprep_api error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}