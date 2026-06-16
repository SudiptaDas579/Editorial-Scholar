<?php
/**
 * tesrprep_api.php  — Test Prep JSON API
 * ─────────────────────────────────────────────────────────────────────────────
 * Handles all AJAX actions from testPrep.php:
 *   bookmark         — add / remove a resource bookmark
 *   log_download     — record a resource download
 *   register_mock    — register user for a mock exam session
 *   unregister_mock  — cancel a mock registration
 *   book_consultation— save a consultation slot request
 *   update_progress  — mark a module complete & recalculate exam progress %
 *   get_progress     — return all progress + bookmarks + mock registrations
 *   get_materials    — return paginated material list for an exam
 * ─────────────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth_helpers.php';

// ── Helpers ───────────────────────────────────────────────────────────────────

function ok(array $data = []): never
{
    echo json_encode(['success' => true] + $data);
    exit;
}

function fail(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function require_auth(): int
{
    if (!is_logged_in()) {
        fail('Authentication required.', 401);
    }
    return (int)$_SESSION['user_id'];
}

// ── Parse body ────────────────────────────────────────────────────────────────
$raw    = (string)file_get_contents('php://input');
$body   = json_decode($raw, true) ?? [];
$action = strtolower(trim((string)($body['action'] ?? $_GET['action'] ?? '')));

if ($action === '') {
    fail('Missing action.');
}

$pdo = null;
try {
    $pdo = getDB();
} catch (\Throwable $e) {
    fail('Database unavailable.', 503);
}

// ═════════════════════════════════════════════════════════════════════════════
//  ROUTER
// ═════════════════════════════════════════════════════════════════════════════

switch ($action) {

    // ── BOOKMARK ─────────────────────────────────────────────────────────────
    case 'bookmark':
        $userId     = require_auth();
        $resourceId = (int)($body['resource_id'] ?? 0);
        $mode       = strtolower(trim((string)($body['mode'] ?? 'add')));

        if ($resourceId <= 0) {
            fail('Invalid resource_id.');
        }

        if ($mode === 'add') {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO test_prep_bookmarks (user_id, resource_id) VALUES (?, ?)'
            );
            $stmt->execute([$userId, $resourceId]);
            ok(['action' => 'added']);
        } elseif ($mode === 'remove') {
            $stmt = $pdo->prepare(
                'DELETE FROM test_prep_bookmarks WHERE user_id = ? AND resource_id = ?'
            );
            $stmt->execute([$userId, $resourceId]);
            ok(['action' => 'removed']);
        }

        fail('Invalid mode. Use "add" or "remove".');

    // ── LOG DOWNLOAD ─────────────────────────────────────────────────────────
    case 'log_download':
        $userId     = require_auth();
        $resourceId = (int)($body['resource_id'] ?? 0);

        if ($resourceId <= 0) {
            fail('Invalid resource_id.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO test_prep_downloads (user_id, resource_id) VALUES (?, ?)'
        );
        $stmt->execute([$userId, $resourceId]);
        ok();

    // ── REGISTER MOCK ────────────────────────────────────────────────────────
    case 'register_mock':
        $userId = require_auth();
        $mockId = (int)($body['mock_id'] ?? 0);

        if ($mockId <= 0) {
            fail('Invalid mock_id.');
        }

        // Check the mock exists in our static list (1–3)
        if ($mockId < 1 || $mockId > 99) {
            fail('Mock session not found.');
        }

        // Upsert: if cancelled earlier, reactivate
        $stmt = $pdo->prepare(
            'INSERT INTO test_prep_mock_registrations (user_id, mock_id, status)
             VALUES (?, ?, "registered")
             ON DUPLICATE KEY UPDATE status = "registered", updated_at = NOW()'
        );
        $stmt->execute([$userId, $mockId]);
        ok(['mock_id' => $mockId, 'status' => 'registered']);

    // ── UNREGISTER MOCK ──────────────────────────────────────────────────────
    case 'unregister_mock':
        $userId = require_auth();
        $mockId = (int)($body['mock_id'] ?? 0);

        if ($mockId <= 0) {
            fail('Invalid mock_id.');
        }

        $stmt = $pdo->prepare(
            'UPDATE test_prep_mock_registrations
             SET status = "cancelled", updated_at = NOW()
             WHERE user_id = ? AND mock_id = ?'
        );
        $stmt->execute([$userId, $mockId]);
        ok(['mock_id' => $mockId, 'status' => 'cancelled']);

    // ── BOOK CONSULTATION ────────────────────────────────────────────────────
    case 'book_consultation':
        $userId = require_auth();

        $name  = trim((string)($body['name']  ?? ''));
        $date  = trim((string)($body['date']  ?? ''));
        $time  = trim((string)($body['time']  ?? ''));
        $exam  = trim((string)($body['exam']  ?? ''));
        $notes = trim((string)($body['notes'] ?? ''));

        if (!$name || !$date || !$time || !$exam) {
            fail('All required fields must be filled.');
        }

        // Validate date is in the future
        if (strtotime($date) < strtotime('today')) {
            fail('Consultation date must be in the future.');
        }

        // Allowed exam values
        $allowed = ['IELTS', 'GRE', 'TOEFL', 'Multiple / Unsure'];
        if (!in_array($exam, $allowed, true)) {
            fail('Invalid exam target.');
        }

        // Check for duplicate booking on same date+time (user)
        $check = $pdo->prepare(
            'SELECT id FROM test_prep_consultations
             WHERE user_id = ? AND consult_date = ? AND consult_time = ? AND status != "cancelled"'
        );
        $check->execute([$userId, $date, $time]);
        if ($check->fetch()) {
            fail('You already have a booking at this date and time.');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO test_prep_consultations
                (user_id, full_name, exam_target, consult_date, consult_time, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$userId, $name, $exam, $date, $time, $notes ?: null]);

        ok(['consultation_id' => (int)$pdo->lastInsertId()]);

    // ── UPDATE PROGRESS ──────────────────────────────────────────────────────
    case 'update_progress':
        $userId  = require_auth();
        $examKey = strtolower(trim((string)($body['exam_key'] ?? '')));
        $module  = trim((string)($body['module'] ?? ''));

        $validExams = ['ielts', 'gre', 'toefl'];
        if (!in_array($examKey, $validExams, true)) {
            fail('Invalid exam_key. Must be ielts, gre, or toefl.');
        }

        if ($module === '') {
            fail('module name is required.');
        }

        // Mark this specific module as completed
        // First ensure the module row exists
        $modStmt = $pdo->prepare(
            'INSERT INTO test_prep_module_completions (user_id, exam_key, module_slug)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE completed_at = NOW()'
        );
        $modStmt->execute([$userId, $examKey, $module]);

        // Recalculate progress: completed modules / total modules for that exam
        $totalModules = match ($examKey) {
            'ielts' => 12,
            'gre'   => 10,
            'toefl' => 9,
        };

        $doneStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM test_prep_module_completions
             WHERE user_id = ? AND exam_key = ?'
        );
        $doneStmt->execute([$userId, $examKey]);
        $done = (int)$doneStmt->fetchColumn();

        $pct = (int)min(100, round(($done / $totalModules) * 100));

        // Upsert into progress table
        $progStmt = $pdo->prepare(
            'INSERT INTO test_prep_progress (user_id, exam_key, progress_pct, last_module)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE progress_pct = ?, last_module = ?'
        );
        $progStmt->execute([$userId, $examKey, $pct, $module, $pct, $module]);

        ok([
            'exam_key'       => $examKey,
            'progress_pct'   => $pct,
            'modules_done'   => $done,
            'modules_total'  => $totalModules,
            'last_module'    => $module,
        ]);

    // ── GET PROGRESS ─────────────────────────────────────────────────────────
    case 'get_progress':
        $userId = require_auth();

        // Exam progress
        $progStmt = $pdo->prepare(
            'SELECT exam_key, progress_pct, last_module FROM test_prep_progress WHERE user_id = ?'
        );
        $progStmt->execute([$userId]);
        $progress = [];
        $lastExam = null;
        foreach ($progStmt->fetchAll() as $row) {
            $progress[$row['exam_key']] = [
                'pct'         => (int)$row['progress_pct'],
                'last_module' => $row['last_module'],
            ];
            if ($row['last_module']) {
                $lastExam = ['exam' => strtoupper($row['exam_key']), 'module' => $row['last_module']];
            }
        }

        // Bookmarks
        $bmStmt = $pdo->prepare('SELECT resource_id FROM test_prep_bookmarks WHERE user_id = ?');
        $bmStmt->execute([$userId]);
        $bookmarks = array_column($bmStmt->fetchAll(), 'resource_id');

        // Mock registrations
        $mrStmt = $pdo->prepare(
            'SELECT mock_id FROM test_prep_mock_registrations WHERE user_id = ? AND status != "cancelled"'
        );
        $mrStmt->execute([$userId]);
        $mockRegs = array_map('intval', array_column($mrStmt->fetchAll(), 'mock_id'));

        // Completed modules
        $cmStmt = $pdo->prepare(
            'SELECT exam_key, module_slug FROM test_prep_module_completions WHERE user_id = ?'
        );
        $cmStmt->execute([$userId]);
        $completedModules = [];
        foreach ($cmStmt->fetchAll() as $row) {
            $completedModules[$row['exam_key']][] = $row['module_slug'];
        }

        ok([
            'progress'          => $progress,
            'bookmarks'         => $bookmarks,
            'mock_registrations'=> $mockRegs,
            'completed_modules' => $completedModules,
            'last_exam'         => $lastExam,
        ]);

    // ── GET MATERIALS ────────────────────────────────────────────────────────
    case 'get_materials':
        $examKey = strtolower(trim((string)($body['exam_key'] ?? $_GET['exam_key'] ?? '')));
        $page    = max(1, (int)($body['page'] ?? $_GET['page'] ?? 1));
        $limit   = 6;
        $offset  = ($page - 1) * $limit;

        $validExams = ['ielts', 'gre', 'toefl'];
        if (!in_array($examKey, $validExams, true)) {
            fail('Invalid exam_key.');
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM test_prep_materials
             WHERE exam_key = ?
             ORDER BY sort_order ASC, id ASC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$examKey, $limit, $offset]);
        $materials = $stmt->fetchAll();

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM test_prep_materials WHERE exam_key = ?'
        );
        $countStmt->execute([$examKey]);
        $total = (int)$countStmt->fetchColumn();

        // If user is logged in, flag completed modules
        $completedSlugs = [];
        if (is_logged_in()) {
            $userId = (int)$_SESSION['user_id'];
            $csStmt = $pdo->prepare(
                'SELECT module_slug FROM test_prep_module_completions
                 WHERE user_id = ? AND exam_key = ?'
            );
            $csStmt->execute([$userId, $examKey]);
            $completedSlugs = array_column($csStmt->fetchAll(), 'module_slug');
        }

        // Annotate completion status
        $materials = array_map(function (array $m) use ($completedSlugs): array {
            $m['completed'] = in_array($m['module_slug'], $completedSlugs, true);
            return $m;
        }, $materials);

        ok([
            'materials'   => $materials,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => (int)ceil($total / $limit),
        ]);

    // ── FALLBACK ─────────────────────────────────────────────────────────────
    default:
        fail("Unknown action: $action");
}