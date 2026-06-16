<?php
// ─────────────────────────────────────────────────────
//  tesrprep_api.php  — Test Prep AJAX API
//
//  Endpoints (POST JSON):
//    action: "bookmark"            — add/remove a resource bookmark
//    action: "log_download"        — log a resource download
//    action: "book_consultation"   — book an advisor consultation
//    action: "cancel_consultation" — cancel a pending/confirmed consultation
//    action: "update_progress"     — update exam progress % (called from module player)
//    action: "get_progress"        — fetch all exam progress for the logged-in user
//    action: "get_bookmarks"       — fetch all bookmarked resource IDs for the logged-in user
//    action: "register_mock"       — register for an upcoming mock session
//    action: "unregister_mock"     — cancel a mock session registration
//
//  All endpoints require an active session (user must be logged in),
//  except book_consultation / register_mock which return a friendly
//  error if not logged in.
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
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       INT UNSIGNED NOT NULL,
            resource_id   INT UNSIGNED NOT NULL,
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

    // Mock session registrations
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS test_prep_mock_registrations (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     INT UNSIGNED NOT NULL,
            mock_id     INT UNSIGNED NOT NULL,
            status      ENUM('registered','cancelled') NOT NULL DEFAULT 'registered',
            registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_mock (user_id, mock_id),
            INDEX idx_user (user_id),
            INDEX idx_mock (mock_id)
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

        // ── GET BOOKMARKS ─────────────────────────────
        // Returns all bookmarked resource IDs for the current user.
        // Useful for restoring bookmark state after page navigation
        // without a full PHP re-render.
        case 'get_bookmarks':
            if (!$isLoggedIn) {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
                exit;
            }

            $stmt = $pdo->prepare(
                'SELECT resource_id FROM test_prep_bookmarks WHERE user_id = ? ORDER BY created_at DESC'
            );
            $stmt->execute([$userId]);
            $ids = array_map('intval', array_column($stmt->fetchAll(), 'resource_id'));

            echo json_encode(['success' => true, 'bookmarks' => $ids]);
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

            echo json_encode([
                'success'        => true,
                'message'        => 'Consultation booked successfully.',
                'consultation_id' => (int)$pdo->lastInsertId(),
            ]);
            break;

        // ── CANCEL CONSULTATION ───────────────────────
        // Lets a user cancel one of their own pending/confirmed consultations.
        // Requires: consultation_id (int)
        case 'cancel_consultation':
            if (!$isLoggedIn) {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
                exit;
            }

            $consultId = (int)($body['consultation_id'] ?? 0);
            if ($consultId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid consultation ID.']);
                exit;
            }

            // Confirm it belongs to this user and is cancellable
            $check = $pdo->prepare(
                'SELECT id, status FROM test_prep_consultations WHERE id = ? AND user_id = ?'
            );
            $check->execute([$consultId, $userId]);
            $row = $check->fetch();

            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Consultation not found.']);
                exit;
            }
            if ($row['status'] === 'cancelled') {
                echo json_encode(['success' => false, 'message' => 'This consultation is already cancelled.']);
                exit;
            }

            $stmt = $pdo->prepare(
                'UPDATE test_prep_consultations SET status = "cancelled" WHERE id = ?'
            );
            $stmt->execute([$consultId]);

            echo json_encode(['success' => true, 'message' => 'Consultation cancelled.']);
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

        // ── GET PROGRESS ──────────────────────────────
        // Returns all exam progress rows for the logged-in user.
        // Useful for refreshing the progress panel via AJAX after a
        // module is completed, without reloading the whole page.
        case 'get_progress':
            if (!$isLoggedIn) {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
                exit;
            }

            $stmt = $pdo->prepare(
                'SELECT exam_key, progress_pct, last_module, updated_at
                 FROM test_prep_progress
                 WHERE user_id = ?
                 ORDER BY updated_at DESC'
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Normalise into a keyed map for convenience
            $progress = ['ielts' => 0, 'gre' => 0, 'toefl' => 0];
            $lastExam = null;
            foreach ($rows as $row) {
                $key = strtolower($row['exam_key']);
                if (isset($progress[$key])) {
                    $progress[$key] = (int)$row['progress_pct'];
                }
                if ($row['last_module'] && $lastExam === null) {
                    $lastExam = [
                        'exam'       => strtoupper($key),
                        'module'     => $row['last_module'],
                        'updated_at' => $row['updated_at'],
                    ];
                }
            }

            echo json_encode([
                'success'  => true,
                'progress' => $progress,
                'last_exam' => $lastExam,
            ]);
            break;

        // ── REGISTER MOCK ─────────────────────────────
        // Registers the logged-in user for an upcoming mock session.
        // Requires: mock_id (int)
        // Uses INSERT ... ON DUPLICATE KEY to handle re-registrations
        // (e.g. re-registering after a cancellation).
        case 'register_mock':
            if (!$isLoggedIn) {
                echo json_encode(['success' => false, 'message' => 'Please sign in to register for mock sessions.']);
                exit;
            }

            $mockId = (int)($body['mock_id'] ?? 0);
            if ($mockId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid mock session.']);
                exit;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO test_prep_mock_registrations (user_id, mock_id, status)
                 VALUES (?, ?, "registered")
                 ON DUPLICATE KEY UPDATE status = "registered", updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$userId, $mockId]);

            echo json_encode(['success' => true, 'message' => 'Registered for mock session.']);
            break;

        // ── UNREGISTER MOCK ───────────────────────────
        // Cancels the logged-in user's registration for a mock session.
        // Requires: mock_id (int)
        case 'unregister_mock':
            if (!$isLoggedIn) {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
                exit;
            }

            $mockId = (int)($body['mock_id'] ?? 0);
            if ($mockId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid mock session.']);
                exit;
            }

            $stmt = $pdo->prepare(
                'UPDATE test_prep_mock_registrations
                 SET status = "cancelled", updated_at = CURRENT_TIMESTAMP
                 WHERE user_id = ? AND mock_id = ?'
            );
            $stmt->execute([$userId, $mockId]);

            echo json_encode(['success' => true, 'message' => 'Mock registration cancelled.']);
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