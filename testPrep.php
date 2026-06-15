<?php
// ─────────────────────────────────────────────────────
//  testPrep.php  — Test Preparation Hub
//  Replaces the static testPrep.html
// ─────────────────────────────────────────────────────
session_start();
require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$isLoggedIn = is_logged_in();
$userId     = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;
$userName   = $isLoggedIn ? htmlspecialchars($_SESSION['full_name'] ?? '') : '';

// ── STATIC EXAM DEFINITIONS ──────────────────────────
$exams = [
    'ielts' => [
        'label'    => 'Academic &amp; General',
        'icon'     => 'ri-global-line',
        'name'     => 'IELTS',
        'subtitle' => 'International English Language Testing System',
        'desc'     => 'Comprehensive listening, reading, writing, and speaking preparation with authentic band descriptors aligned to CEFR.',
        'duration' => '2 hrs 45m',
        'score'    => '0 – 9.0',
        'score_lbl'=> 'Band Score',
        'fee'      => '$245 – $340',
        'validity' => '2 years',
        'sections' => ['Listening','Reading','Writing','Speaking'],
        'modules'  => 12,
        'reg_url'  => 'https://www.ielts.org/book-a-test',
    ],
    'gre' => [
        'label'    => 'Graduate School Admission',
        'icon'     => 'ri-line-chart-line',
        'name'     => 'GRE',
        'subtitle' => 'Graduate Record Examinations — General Test',
        'desc'     => 'Verbal reasoning, quantitative analysis, and analytical writing with adaptive practice sets mirroring ETS format.',
        'duration' => '1 hr 58m',
        'score'    => '130 – 170',
        'score_lbl'=> 'Score Range',
        'fee'      => '$220',
        'validity' => '5 years',
        'sections' => ['Verbal','Quantitative','Analytical Writing'],
        'modules'  => 10,
        'reg_url'  => 'https://www.ets.org/gre/test-takers/general-test/register.html',
    ],
    'toefl' => [
        'label'    => 'Academic Proficiency',
        'icon'     => 'ri-book-open-line',
        'name'     => 'TOEFL',
        'subtitle' => 'Test of English as a Foreign Language — iBT',
        'desc'     => 'Academic English proficiency prep aligned to ETS scoring criteria and integrated task formats for universities worldwide.',
        'duration' => '~2 hrs',
        'score'    => '0 – 120',
        'score_lbl'=> 'Score Range',
        'fee'      => '$185 – $255',
        'validity' => '2 years',
        'sections' => ['Reading','Listening','Speaking','Writing'],
        'modules'  => 9,
        'reg_url'  => 'https://www.ets.org/toefl/test-takers/ibt/register.html',
    ],
];

// ── STATIC RESOURCE LIBRARY ──────────────────────────
$resources = [
    ['id'=>1,'icon'=>'ri-file-pdf-line',  'title'=>'GRE Quantitative Strategy Guide 2024', 'meta'=>'PDF Document • 4.2 MB • Advanced Level',   'file'=>'#'],
    ['id'=>2,'icon'=>'ri-video-line',      'title'=>'TOEFL Speaking: The 4-Template Method',  'meta'=>'Video Lecture • 14:20 • Instructional',       'file'=>'#'],
    ['id'=>3,'icon'=>'ri-article-line',    'title'=>'IELTS Writing Task 2: Band 9 Samples',  'meta'=>'Interactive Article • 8 min read',            'file'=>'#'],
    ['id'=>4,'icon'=>'ri-headphone-line',  'title'=>'IELTS Listening — Academic Section 3 Drill','meta'=>'Audio Practice • 22:45 • Intermediate Level','file'=>'#'],
];

// ── UPCOMING MOCK SESSIONS ───────────────────────────
$mocks = [
    ['month'=>'Aug','day'=>'8',  'title'=>'IELTS Full Mock Test',     'duration'=>'3 hrs','scope'=>'All sections',  'past'=>false],
    ['month'=>'Aug','day'=>'15', 'title'=>'GRE Diagnostic Session',   'duration'=>'2 hrs','scope'=>'Quant focus',   'past'=>false],
    ['month'=>'Sep','day'=>'3',  'title'=>'TOEFL Full Practice Test',  'duration'=>'2 hrs','scope'=>'All sections',  'past'=>false],
];

// ── FETCH USER PROGRESS (DB) ─────────────────────────
$progress = ['ielts'=>0,'gre'=>0,'toefl'=>0];
$bookmarks = [];
$lastExam  = null;

if ($isLoggedIn) {
    try {
        $pdo = getDB();

        // User progress per exam
        $stmt = $pdo->prepare(
            'SELECT exam_key, progress_pct, last_module FROM test_prep_progress WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $key = strtolower($row['exam_key']);
            if (isset($progress[$key])) {
                $progress[$key] = (int)$row['progress_pct'];
            }
            if ($row['last_module']) {
                $lastExam = ['exam' => strtoupper($key), 'module' => $row['last_module']];
            }
        }

        // Bookmarked resource IDs
        $stmt = $pdo->prepare(
            'SELECT resource_id FROM test_prep_bookmarks WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $bookmarks = array_column($stmt->fetchAll(), 'resource_id');

    } catch (PDOException $e) {
        error_log('testPrep DB error: ' . $e->getMessage());
    }
}

// ── PROGRESS LABELS ──────────────────────────────────
$progressRows = [
    ['key'=>'ielts', 'label'=>'IELTS Preparation',  'pct'=>$progress['ielts']],
    ['key'=>'gre',   'label'=>'GRE Quantitative',   'pct'=>$progress['gre']],
    ['key'=>'toefl', 'label'=>'TOEFL Speaking',      'pct'=>$progress['toefl']],
];

$cssPath = BASE_URL . '/src/output.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>The Editorial Scholar — Test Prep</title>

  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,600;0,700;0,800;1,300;1,400&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <!-- Primary: compiled Tailwind output -->
  <link rel="stylesheet" href="<?= $cssPath ?>">

  <!-- Fallback CSS tokens so the page never breaks if output.css is missing or classes haven't been compiled yet -->
  <style>
    /* ── Design tokens ─────────────────────────────── */
    :root {
      --color-navy:   #031632;
      --color-navy-2: #1A2B48;
      --color-gold:   #775A19;
      --color-gold-lt:#A16207;
      --color-gold-bg:#FED488;
      --color-slate:  #44474D;
      --color-muted:  #75777E;
      --color-border: #E2E8F0;
      --color-bg:     #F8F9FA;
      --font-newsreader: 'Newsreader', Georgia, serif;
      --font-manrope:    'Manrope', sans-serif;
    }

    /* ── Reset & base ──────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--color-bg);
      font-family: var(--font-manrope);
      color: var(--color-slate);
      -webkit-font-smoothing: antialiased;
    }
    a { text-decoration: none; }
    button { cursor: pointer; }

    /* ── Utility helpers (always present regardless of Tailwind compile) ── */
    .font-newsreader { font-family: var(--font-newsreader); }
    .font-manrope    { font-family: var(--font-manrope); }
    .text-navy       { color: var(--color-navy); }
    .text-gold       { color: var(--color-gold); }
    .text-gold-lt    { color: var(--color-gold-lt); }
    .text-muted      { color: var(--color-muted); }
    .text-slate      { color: var(--color-slate); }
    .bg-navy         { background: var(--color-navy); }
    .bg-gold         { background: var(--color-gold); }
    .bg-gold-bg      { background: var(--color-gold-bg); }
    .bg-bg           { background: var(--color-bg); }
    .border-border   { border-color: var(--color-border); }
    .text-navy-2     { color: var(--color-navy-2); }
    .no-underline    { text-decoration: none !important; }

    /* ── Navbar ────────────────────────────────────── */
    nav.site-nav {
      position: fixed; top: 0; left: 0; right: 0;
      z-index: 45;
      background: rgba(255,255,255,0.85);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-bottom: 1px solid #F1F5F9;
    }
    .nav-inner {
      max-width: 1280px; margin: 0 auto;
      padding: 0 2rem;
      height: 60px;
      display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    }
    .nav-logo {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.5rem;
      color: var(--color-navy);
      white-space: nowrap;
    }
    .nav-links { display: flex; align-items: center; gap: 2rem; }
    .nav-links a {
      font-family: var(--font-newsreader);
      font-weight: 600; font-size: 1rem;
      letter-spacing: -0.4px;
      color: #475569;
      transition: color 0.2s;
    }
    .nav-links a:hover { color: var(--color-navy); }
    .nav-links a.active {
      color: var(--color-gold-lt);
      border-bottom: 2px solid var(--color-gold-lt);
      padding-bottom: 2px;
    }
    .nav-right { display: flex; align-items: center; gap: 1.5rem; }
    .search-wrap { display: flex; align-items: center; gap: 0.5rem; }
    .search-wrap input {
      padding: 0.3rem 0.6rem; font-size: 0.85rem;
      border: 1px solid var(--color-border); border-radius: 6px;
      outline: none; transition: border-color 0.2s;
      width: 160px;
    }
    .search-wrap input:focus { border-color: var(--color-gold-lt); }
    .btn-signin {
      background: var(--color-navy); color: #fff;
      font-family: var(--font-manrope); font-weight: 500; font-size: 0.875rem;
      padding: 0.45rem 1.4rem; border-radius: 6px; border: none;
      transition: background 0.2s;
    }
    .btn-signin:hover { background: var(--color-navy-2); }

    /* ── User pill (when logged in) ────────────────── */
    .user-pill {
      display: flex; align-items: center; gap: 0.5rem;
      font-size: 0.85rem; font-weight: 600; color: var(--color-navy);
    }
    .user-pill i { font-size: 1.1rem; }

    /* ── Toast notifications ───────────────────────── */
    #toast {
      position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999;
      display: flex; flex-direction: column; gap: 0.5rem;
    }
    .toast-msg {
      background: var(--color-navy); color: #fff;
      padding: 0.7rem 1.2rem; border-radius: 6px;
      font-size: 0.82rem; font-weight: 600;
      box-shadow: 0 4px 14px rgba(0,0,0,0.18);
      animation: toast-in 0.3s ease;
    }
    .toast-msg.success { background: #166534; }
    .toast-msg.error   { background: #991b1b; }
    @keyframes toast-in {
      from { opacity: 0; transform: translateY(8px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Main layout ───────────────────────────────── */
    main {
      max-width: 1280px; margin: 0 auto;
      padding: 7.5rem 2rem 4rem;
      display: flex; flex-direction: column; gap: 5rem;
    }

    /* ── Hero ──────────────────────────────────────── */
    .hero { display: flex; align-items: flex-end; justify-content: space-between; gap: 2rem; }
    .hero-left { display: flex; flex-direction: column; gap: 1rem; max-width: 680px; }
    .eyebrow {
      font-family: var(--font-manrope);
      font-weight: 700; font-size: 0.65rem;
      letter-spacing: 2.4px; text-transform: uppercase;
      color: var(--color-gold-lt);
    }
    .hero h1 {
      font-family: var(--font-newsreader);
      font-weight: 800; font-size: 4rem;
      line-height: 1.05; letter-spacing: -1.5px;
      color: var(--color-navy);
    }
    .hero h1 em {
      font-weight: 300; font-size: 4.2rem;
      letter-spacing: -1.8px; font-style: italic;
    }
    .hero-desc {
      font-size: 1rem; line-height: 1.7;
      color: var(--color-muted); max-width: 500px; margin-top: 0.25rem;
    }
    .hero-stats {
      display: flex; gap: 2.5rem;
      margin-top: 0.5rem; padding-top: 1.5rem;
      border-top: 1px solid var(--color-border);
    }
    .hero-stat-num {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.8rem;
      color: var(--color-navy); letter-spacing: -0.5px;
    }
    .hero-stat-lbl {
      font-size: 0.7rem; font-weight: 700;
      letter-spacing: 1.4px; text-transform: uppercase;
      color: var(--color-muted); margin-top: 2px;
    }

    /* ── Section header row ────────────────────────── */
    .section-header {
      display: flex; align-items: baseline;
      justify-content: space-between; gap: 1rem;
    }
    .section-title {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.4rem;
      color: var(--color-navy); letter-spacing: -0.3px;
    }
    .section-link {
      font-size: 0.78rem; font-weight: 700;
      letter-spacing: 1.2px; text-transform: uppercase;
      color: var(--color-gold);
      display: flex; align-items: center; gap: 0.4rem;
      transition: color 0.2s;
    }
    .section-link:hover { color: var(--color-gold-lt); }

    /* ── Exam cards grid ───────────────────────────── */
    .exam-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
    }
    .exam-card {
      display: flex; flex-direction: column;
      background: #fff;
      border: 1px solid rgba(197,198,206,0.35);
      border-right: none;
      position: relative; overflow: hidden;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .exam-card:last-child { border-right: 1px solid rgba(197,198,206,0.35); }
    .exam-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 30px rgba(3,22,50,0.10);
      z-index: 2;
    }
    .exam-top-bar { height: 4px; width: 100%; background: var(--color-gold); }
    .exam-head {
      padding: 2rem 2rem 1.5rem;
      display: flex; flex-direction: column; gap: 0.75rem;
      border-bottom: 1px solid var(--color-border);
    }
    .exam-badge {
      display: inline-flex; align-items: center; gap: 0.4rem;
      font-size: 0.65rem; font-weight: 700;
      letter-spacing: 1.6px; text-transform: uppercase;
      color: var(--color-gold);
      padding: 0.3rem 0.7rem;
      border: 1px solid rgba(119,90,25,0.3);
      border-radius: 2px; width: fit-content;
    }
    .exam-name {
      font-family: var(--font-newsreader);
      font-weight: 800; font-size: 3rem;
      letter-spacing: -1.5px; color: var(--color-navy);
      line-height: 1;
    }
    .exam-subtitle {
      font-family: var(--font-newsreader);
      font-size: 0.9rem; font-style: italic;
      color: var(--color-muted);
    }
    .exam-desc { font-size: 0.82rem; line-height: 1.6; color: var(--color-slate); margin-top: 0.25rem; }
    .exam-stats {
      padding: 1.5rem 2rem;
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 1.25rem 1rem;
      border-bottom: 1px solid var(--color-border);
    }
    .exam-stat-val {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.1rem;
      color: var(--color-navy); letter-spacing: -0.3px;
    }
    .exam-stat-lbl {
      font-size: 0.65rem; font-weight: 700;
      letter-spacing: 1.2px; text-transform: uppercase;
      color: var(--color-muted); margin-top: 2px;
    }
    .exam-sections {
      padding: 1.25rem 2rem;
      display: flex; flex-wrap: wrap; gap: 0.5rem;
      border-bottom: 1px solid var(--color-border);
    }
    .exam-tag {
      font-size: 0.68rem; font-weight: 700;
      letter-spacing: 0.5px;
      color: var(--color-navy-2);
      background: #EEF2F8;
      padding: 0.25rem 0.65rem; border-radius: 2px;
    }
    .exam-footer {
      padding: 1.25rem 2rem;
      display: flex; align-items: center; justify-content: space-between;
    }
    .exam-module-count {
      font-size: 0.65rem; font-weight: 700;
      letter-spacing: 1.2px; text-transform: uppercase;
      color: var(--color-muted);
    }
    .cta-btn {
      display: inline-flex; align-items: center; gap: 0.5rem;
      background: var(--color-navy); color: #fff;
      font-size: 0.72rem; font-weight: 700;
      letter-spacing: 1px; text-transform: uppercase;
      padding: 0.6rem 1.2rem; border-radius: 3px;
      transition: background 0.2s;
    }
    .cta-btn:hover { background: var(--color-navy-2); }

    /* ── Bento grid ────────────────────────────────── */
    .bento { display: grid; grid-template-columns: 1fr 360px; gap: 3rem; }

    /* ── Progress panel ────────────────────────────── */
    .progress-panel {
      background: var(--color-navy); border-radius: 4px;
      padding: 2.5rem; display: flex; flex-direction: column; gap: 2rem;
    }
    .progress-header { display: flex; justify-content: space-between; align-items: flex-start; }
    .progress-title {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.35rem;
      color: #fff; letter-spacing: -0.3px;
    }
    .progress-sub { font-size: 0.8rem; color: #8293B5; margin-top: 3px; }
    .btn-resume {
      background: var(--color-gold); color: #fff;
      font-size: 0.75rem; font-weight: 700; letter-spacing: 0.5px;
      padding: 0.55rem 1.1rem; border-radius: 3px; border: none;
      white-space: nowrap; transition: background 0.2s;
    }
    .btn-resume:hover { background: var(--color-gold-lt); }
    .btn-resume:disabled { opacity: 0.5; cursor: default; }
    .progress-bars { display: flex; flex-direction: column; gap: 1.25rem; }
    .progress-row { display: flex; flex-direction: column; gap: 0.5rem; }
    .progress-label-row { display: flex; justify-content: space-between; }
    .progress-lbl {
      font-size: 0.7rem; font-weight: 700;
      letter-spacing: 1.2px; text-transform: uppercase; color: #fff;
    }
    .progress-track {
      width: 100%; height: 5px;
      background: rgba(255,255,255,0.1);
      border-radius: 9999px; overflow: hidden;
    }
    .progress-fill {
      height: 100%; background: var(--color-gold-bg);
      border-radius: 9999px;
      transition: width 0.6s ease;
    }
    .progress-login-msg {
      color: #8293B5; font-size: 0.82rem; text-align: center; padding: 1rem 0;
    }
    .progress-login-msg a { color: var(--color-gold-bg); }

    /* ── Resource library ──────────────────────────── */
    .resource-list { display: flex; flex-direction: column; gap: 1.25rem; }
    .resource-item {
      display: flex; align-items: center; gap: 1.25rem;
      padding: 1rem 1.25rem;
      background: #fff;
      border: 1px solid rgba(197,198,206,0.35);
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .resource-item:hover {
      border-color: var(--color-border);
      box-shadow: 0 2px 10px rgba(3,22,50,0.06);
    }
    .resource-icon {
      flex-shrink: 0;
      width: 3rem; height: 3rem;
      background: #EEF0F2; border-radius: 4px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.25rem; color: var(--color-gold);
    }
    .resource-info { flex: 1; min-width: 0; }
    .resource-title {
      font-weight: 700; font-size: 0.95rem;
      color: var(--color-navy);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .resource-meta { font-size: 0.75rem; color: var(--color-muted); margin-top: 3px; }
    .resource-actions { display: flex; align-items: center; gap: 0.75rem; flex-shrink: 0; }
    .resource-actions i {
      font-size: 1.1rem; color: var(--color-muted);
      cursor: pointer; transition: color 0.2s;
    }
    .resource-actions i:hover { color: var(--color-navy); }
    .resource-actions i.bookmarked { color: var(--color-gold-lt); }

    /* ── Sidebar ───────────────────────────────────── */
    .aside { display: flex; flex-direction: column; gap: 2rem; }

    /* ── Upcoming mocks ────────────────────────────── */
    .mocks-box {
      background: #F3F4F5;
      border: 1px solid rgba(197,198,206,0.35);
      border-radius: 4px;
      padding: 2rem;
      display: flex; flex-direction: column; gap: 1.5rem;
    }
    .mocks-title {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.15rem;
      color: var(--color-navy); letter-spacing: -0.2px;
    }
    .mock-list { display: flex; flex-direction: column; gap: 1.25rem; }
    .mock-item { display: flex; gap: 1rem; align-items: flex-start; }
    .mock-item.past { opacity: 0.45; }
    .mock-date-box {
      flex-shrink: 0;
      width: 3rem; height: 52px;
      background: #fff;
      border: 1px solid rgba(197,198,206,0.35);
      border-radius: 3px;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
    }
    .mock-month {
      font-size: 0.6rem; font-weight: 700;
      letter-spacing: 0.8px; text-transform: uppercase;
      color: var(--color-muted);
    }
    .mock-day {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.2rem;
      color: var(--color-navy); line-height: 1;
    }
    .mock-name { font-size: 0.85rem; font-weight: 700; color: var(--color-navy); }
    .mock-meta { font-size: 0.72rem; color: var(--color-muted); margin-top: 2px; }
    .btn-outline {
      width: 100%; height: 40px;
      border: 1px solid var(--color-navy);
      background: transparent;
      font-family: var(--font-manrope);
      font-size: 0.68rem; font-weight: 700;
      letter-spacing: 1.2px; text-transform: uppercase;
      color: var(--color-navy);
      transition: background 0.2s, color 0.2s;
    }
    .btn-outline:hover { background: var(--color-navy); color: #fff; }

    /* ── Scholars circle ───────────────────────────── */
    .scholars-circle {
      position: relative; height: 340px;
      border-radius: 4px; overflow: hidden;
    }
    .scholars-bg {
      position: absolute; inset: 0;
      background: url('https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800') center/cover;
      opacity: 0.45;
    }
    .scholars-grad {
      position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(3,22,50,0.95) 0%, rgba(3,22,50,0.1) 100%);
    }
    .scholars-content {
      position: absolute; inset: 0;
      display: flex; flex-direction: column; justify-content: flex-end;
      padding: 2rem;
    }
    .scholars-title {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.5rem;
      color: #fff; margin-bottom: 0.5rem;
    }
    .scholars-desc { font-size: 0.8rem; line-height: 1.6; color: #8293B5; margin-bottom: 1.25rem; }
    .scholars-link {
      display: inline-flex; align-items: center; gap: 0.5rem;
      font-size: 0.8rem; font-weight: 700;
      color: var(--color-gold-bg);
      transition: opacity 0.2s;
    }
    .scholars-link:hover { opacity: 0.75; }

    /* ── Admissions liaison ────────────────────────── */
    .liaison-box {
      background: #fff;
      border: 1px solid rgba(197,198,206,0.35);
      border-radius: 16px;
      padding: 1.75rem 2rem;
      display: flex; flex-direction: column; gap: 1rem;
      width: 60%; margin-left: auto; margin-right: auto;
    }
    .liaison-title {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.05rem;
      color: var(--color-navy);
    }
    .liaison-desc { font-size: 0.8rem; color: var(--color-slate); line-height: 1.6; }
    .liaison-advisor { display: flex; align-items: center; gap: 0.75rem; padding: 0.25rem 0; }
    .liaison-avatar {
      width: 2.5rem; height: 2.5rem;
      border-radius: 9999px; overflow: hidden; flex-shrink: 0;
    }
    .liaison-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .liaison-name { font-size: 0.8rem; font-weight: 700; color: var(--color-navy); }
    .liaison-role {
      font-size: 0.65rem; font-weight: 700;
      letter-spacing: 0.5px; text-transform: uppercase;
      color: var(--color-muted); margin-top: 2px;
    }
    .btn-consult {
      width: 100%; height: 42px;
      background: #E1E3E4; border: none;
      font-family: var(--font-manrope);
      font-size: 0.8rem; font-weight: 700;
      color: var(--color-navy);
      border-radius: 3px;
      transition: background 0.2s;
    }
    .btn-consult:hover { background: #d0d2d3; }

    /* ── Consultation modal ────────────────────────── */
    .modal-overlay {
      display: none;
      position: fixed; inset: 0; z-index: 100;
      background: rgba(3,22,50,0.5);
      align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal {
      background: #fff;
      border-radius: 12px;
      padding: 2rem;
      width: 100%; max-width: 440px;
      display: flex; flex-direction: column; gap: 1.25rem;
      box-shadow: 0 20px 50px rgba(3,22,50,0.2);
    }
    .modal-header {
      display: flex; align-items: center; justify-content: space-between;
    }
    .modal-title {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.2rem; color: var(--color-navy);
    }
    .modal-close {
      background: none; border: none;
      font-size: 1.4rem; color: var(--color-muted);
      transition: color 0.2s;
    }
    .modal-close:hover { color: var(--color-navy); }
    .modal label {
      display: block; font-size: 0.75rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.8px;
      color: var(--color-muted); margin-bottom: 0.35rem;
    }
    .modal input, .modal select, .modal textarea {
      width: 100%; padding: 0.6rem 0.8rem;
      border: 1px solid var(--color-border); border-radius: 6px;
      font-family: var(--font-manrope); font-size: 0.88rem;
      color: var(--color-navy); outline: none;
      transition: border-color 0.2s;
    }
    .modal input:focus, .modal select:focus, .modal textarea:focus {
      border-color: var(--color-gold-lt);
    }
    .modal textarea { resize: vertical; min-height: 80px; }
    .modal-submit {
      width: 100%; height: 44px;
      background: var(--color-navy); color: #fff;
      border: none; border-radius: 6px;
      font-family: var(--font-manrope); font-weight: 700; font-size: 0.88rem;
      transition: background 0.2s;
    }
    .modal-submit:hover { background: var(--color-navy-2); }
    .modal-login-note { font-size: 0.8rem; color: var(--color-muted); text-align: center; }
    .modal-login-note a { color: var(--color-gold-lt); font-weight: 600; }
    .form-row { display: flex; flex-direction: column; gap: 0.25rem; }
    .modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

    /* ── Footer ────────────────────────────────────── */
    footer {
      background: #F8FAFC;
      border-top: 1px solid var(--color-border);
      padding: 3rem 0;
    }
    .footer-inner {
      max-width: 1280px; margin: 0 auto; padding: 0 2rem;
      display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 2rem;
    }
    .footer-logo {
      font-family: var(--font-newsreader);
      font-weight: 700; font-size: 1.05rem; color: var(--color-navy);
      margin-bottom: 0.5rem;
    }
    .footer-copy { font-size: 0.8rem; line-height: 1.6; color: #64748B; }
    .footer-col-title {
      font-size: 0.7rem; font-weight: 700;
      letter-spacing: 1.4px; text-transform: uppercase;
      color: var(--color-navy); margin-bottom: 0.75rem;
    }
    .footer-link {
      display: block; font-size: 0.8rem; color: #64748B;
      margin-bottom: 0.6rem; transition: color 0.2s;
    }
    .footer-link:hover { color: var(--color-navy); }
    .social-links { display: flex; gap: 1rem; }
    .social-links a { font-size: 1.2rem; color: #64748B; transition: color 0.2s; }
    .social-links a:hover { color: var(--color-navy); }

    /* ── Bento left stack ──────────────────────────── */
    .bento-left { display: flex; flex-direction: column; gap: 3.5rem; }

    /* ── Auth-gated message ────────────────────────── */
    .auth-gate {
      background: rgba(255,255,255,0.08);
      border: 1px dashed rgba(255,255,255,0.2);
      border-radius: 6px; padding: 1rem;
      text-align: center; margin-top: 0.5rem;
    }

    /* ── Responsive tweaks ─────────────────────────── */
    @media (max-width: 900px) {
      .exam-grid { grid-template-columns: 1fr; }
      .exam-card { border-right: 1px solid rgba(197,198,206,0.35); border-bottom: none; }
      .exam-card:last-child { border-bottom: 1px solid rgba(197,198,206,0.35); }
      .bento { grid-template-columns: 1fr; }
      .liaison-box { width: 100%; margin: 0; }
      .nav-links { display: none; }
    }
  </style>
</head>
<body>

<!-- ═══════════════════════════════════════════════════
     NAV
═══════════════════════════════════════════════════ -->
<nav class="site-nav">
  <div class="nav-inner">
    <span class="nav-logo">The Editorial Scholar</span>

    <div class="nav-links">
      <a href="<?= BASE_URL ?>/index.html">Programs</a>
      <a href="<?= BASE_URL ?>/scholarship.php">Scholarships</a>
      <a href="<?= BASE_URL ?>/testPrep.php" class="active">Test Prep</a>
      <a href="<?= BASE_URL ?>/visa.html">Visa Guide</a>
      <a href="<?= BASE_URL ?>/research.php">Research</a>
    </div>

    <div class="nav-right">
      <div class="search-wrap">
        <i class="ri-search-line" style="font-size:1.1rem;color:#44474D;"></i>
        <input type="search" id="globalSearch" placeholder="Search resources…" />
      </div>

      <?php if ($isLoggedIn): ?>
        <div class="user-pill">
          <i class="ri-user-3-line"></i>
          <span><?= $userName ?></span>
        </div>
      <?php else: ?>
        <button class="btn-signin" onclick="window.location.href='<?= BASE_URL ?>/auth/signIn.php'">
          Sign in
        </button>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- ═══════════════════════════════════════════════════
     MAIN
═══════════════════════════════════════════════════ -->
<main>

  <!-- ── HERO ───────────────────────────────────────── -->
  <section class="hero">
    <div class="hero-left">
      <p class="eyebrow">Preparation Excellence</p>
      <h1>
        Mastering the<br/>
        <em>Standardized Path.</em>
      </h1>
      <p class="hero-desc">
        Structured programmes, diagnostic tools, and examiner-designed resources for IELTS, GRE, and TOEFL success.
      </p>
      <div class="hero-stats">
        <div>
          <div class="hero-stat-num">31</div>
          <div class="hero-stat-lbl">Modules Available</div>
        </div>
        <div>
          <div class="hero-stat-num">4,000+</div>
          <div class="hero-stat-lbl">Active Scholars</div>
        </div>
        <div>
          <div class="hero-stat-num">92%</div>
          <div class="hero-stat-lbl">Target Score Rate</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── EXAM CARDS ─────────────────────────────────── -->
  <section style="display:flex;flex-direction:column;gap:1.5rem;">
    <div class="section-header">
      <h2 class="section-title">Select Your Exam</h2>
      <a href="#" class="section-link">Compare all exams <i class="ri-arrow-right-line"></i></a>
    </div>

    <div class="exam-grid">
      <?php foreach ($exams as $key => $exam): ?>
      <a class="exam-card" href="<?= htmlspecialchars($exam['reg_url']) ?>" target="_blank" rel="noopener noreferrer">
        <div class="exam-top-bar"></div>
        <div class="exam-head">
          <span class="exam-badge">
            <i class="<?= $exam['icon'] ?>" style="font-size:0.75rem;"></i>
            <?= $exam['label'] ?>
          </span>
          <div class="exam-name"><?= $exam['name'] ?></div>
          <div class="exam-subtitle"><?= $exam['subtitle'] ?></div>
          <p class="exam-desc"><?= $exam['desc'] ?></p>
        </div>
        <div class="exam-stats">
          <div>
            <div class="exam-stat-val"><?= $exam['duration'] ?></div>
            <div class="exam-stat-lbl">Duration</div>
          </div>
          <div>
            <div class="exam-stat-val"><?= $exam['score'] ?></div>
            <div class="exam-stat-lbl"><?= $exam['score_lbl'] ?></div>
          </div>
          <div>
            <div class="exam-stat-val"><?= $exam['fee'] ?></div>
            <div class="exam-stat-lbl">Exam Fee</div>
          </div>
          <div>
            <div class="exam-stat-val"><?= $exam['validity'] ?></div>
            <div class="exam-stat-lbl">Validity</div>
          </div>
        </div>
        <div class="exam-sections">
          <?php foreach ($exam['sections'] as $section): ?>
          <span class="exam-tag"><?= htmlspecialchars($section) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="exam-footer">
          <span class="exam-module-count"><?= $exam['modules'] ?> Modules</span>
          <span class="cta-btn">Register Now <i class="ri-external-link-line" style="font-size:0.9rem;"></i></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── BENTO GRID ─────────────────────────────────── -->
  <section class="bento">

    <!-- LEFT COLUMN -->
    <div class="bento-left">

      <!-- Progress Panel -->
      <div class="progress-panel">
        <div class="progress-header">
          <div>
            <div class="progress-title">Your Progress</div>
            <div class="progress-sub">Track your preparation across all exams</div>
          </div>
          <?php if ($isLoggedIn): ?>
            <button class="btn-resume" id="btnResume"
              <?= $lastExam ? '' : 'disabled title="No practice sessions started yet"' ?>>
              <?= $lastExam ? 'Resume ' . $lastExam['exam'] : 'Start Practice' ?>
            </button>
          <?php else: ?>
            <button class="btn-resume" onclick="window.location.href='<?= BASE_URL ?>/auth/signIn.php'">
              Sign in to Track
            </button>
          <?php endif; ?>
        </div>

        <?php if ($isLoggedIn): ?>
          <div class="progress-bars">
            <?php foreach ($progressRows as $row): ?>
            <div class="progress-row">
              <div class="progress-label-row">
                <span class="progress-lbl"><?= htmlspecialchars($row['label']) ?></span>
                <span class="progress-lbl"><?= $row['pct'] ?>%</span>
              </div>
              <div class="progress-track">
                <div class="progress-fill" style="width:<?= $row['pct'] ?>%"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="auth-gate">
            <p class="progress-login-msg">
              <a href="<?= BASE_URL ?>/auth/signIn.php">Sign in</a> or
              <a href="<?= BASE_URL ?>/auth/signUp.php">create a free account</a>
              to track your progress across all exams.
            </p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Resource Library -->
      <div style="display:flex;flex-direction:column;gap:1.25rem;">
        <div class="section-header">
          <h2 class="section-title">Resource Library</h2>
          <a href="#" class="section-link">Browse all <i class="ri-arrow-right-line"></i></a>
        </div>

        <div class="resource-list" id="resourceList">
          <?php foreach ($resources as $r):
            $isBookmarked = in_array($r['id'], $bookmarks);
          ?>
          <div class="resource-item" data-resource-id="<?= $r['id'] ?>">
            <div class="resource-icon"><i class="<?= $r['icon'] ?>"></i></div>
            <div class="resource-info">
              <div class="resource-title"><?= htmlspecialchars($r['title']) ?></div>
              <div class="resource-meta"><?= htmlspecialchars($r['meta']) ?></div>
            </div>
            <div class="resource-actions">
              <i class="ri-bookmark-<?= $isBookmarked ? 'fill bookmarked' : 'line' ?>"
                 title="<?= $isBookmarked ? 'Remove bookmark' : 'Bookmark' ?>"
                 onclick="toggleBookmark(<?= $r['id'] ?>, this)"></i>
              <i class="ri-download-line" title="Download"
                 onclick="downloadResource(<?= $r['id'] ?>, '<?= addslashes($r['file']) ?>')"></i>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /bento-left -->

    <!-- ASIDE / RIGHT COLUMN -->
    <aside class="aside">

      <!-- Upcoming Mocks -->
      <div class="mocks-box">
        <h3 class="mocks-title">Upcoming Mocks</h3>
        <div class="mock-list">
          <?php foreach ($mocks as $mock): ?>
          <div class="mock-item <?= $mock['past'] ? 'past' : '' ?>">
            <div class="mock-date-box">
              <span class="mock-month"><?= $mock['month'] ?></span>
              <span class="mock-day"><?= $mock['day'] ?></span>
            </div>
            <div>
              <div class="mock-name"><?= htmlspecialchars($mock['title']) ?></div>
              <div class="mock-meta"><?= $mock['duration'] ?> &bull; <?= htmlspecialchars($mock['scope']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button class="btn-outline" onclick="viewAllSchedules()">View All Schedules</button>
      </div>

      <!-- Scholars Circle -->
      <div class="scholars-circle">
        <div class="scholars-bg"></div>
        <div class="scholars-grad"></div>
        <div class="scholars-content">
          <h3 class="scholars-title">Scholars Circle</h3>
          <p class="scholars-desc">
            Join 4,000+ applicants discussing prep strategies and score improvements.
          </p>
          <a href="#" class="scholars-link">
            Join the Community <i class="ri-arrow-right-line" style="font-size:0.9rem;"></i>
          </a>
        </div>
      </div>

    </aside>
  </section>

  <!-- ── ADMISSIONS LIAISON ─────────────────────────── -->
  <div class="liaison-box">
    <h3 class="liaison-title">Admissions Liaison</h3>
    <p class="liaison-desc">Need help choosing the right test for your target university?</p>
    <div class="liaison-advisor">
      <div class="liaison-avatar">
        <img src="https://www.shutterstock.com/image-vector/professional-businesswoman-icon-corporate-female-600w-2741202739.jpg"
             alt="Dr. Sarah Chen" />
      </div>
      <div>
        <div class="liaison-name">Dr. Sarah Chen</div>
        <div class="liaison-role">Admissions Specialist</div>
      </div>
    </div>
    <button class="btn-consult" onclick="openConsultModal()">Book a Consultation</button>
  </div>

</main>

<!-- ═══════════════════════════════════════════════════
     CONSULTATION MODAL
═══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="consultModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Book a Consultation</span>
      <button class="modal-close" onclick="closeConsultModal()"><i class="ri-close-line"></i></button>
    </div>

    <?php if ($isLoggedIn): ?>
      <p style="font-size:0.82rem;color:var(--color-muted);">
        Reserve a 30-minute session with Dr. Sarah Chen to discuss your exam strategy.
      </p>
      <div class="form-row">
        <label>Full Name</label>
        <input type="text" id="consultName" value="<?= $userName ?>" placeholder="Your full name" />
      </div>
      <div class="modal-grid">
        <div class="form-row">
          <label>Preferred Date</label>
          <input type="date" id="consultDate" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" />
        </div>
        <div class="form-row">
          <label>Time Slot</label>
          <select id="consultTime">
            <option value="">— select —</option>
            <option>10:00 AM</option>
            <option>11:00 AM</option>
            <option>2:00 PM</option>
            <option>3:00 PM</option>
            <option>4:00 PM</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <label>Target Exam</label>
        <select id="consultExam">
          <option value="">— select —</option>
          <option>IELTS</option>
          <option>GRE</option>
          <option>TOEFL</option>
          <option>Multiple / Unsure</option>
        </select>
      </div>
      <div class="form-row">
        <label>Notes (optional)</label>
        <textarea id="consultNotes" placeholder="Share any specific concerns or questions…"></textarea>
      </div>
      <button class="modal-submit" onclick="submitConsultation()">Confirm Booking</button>
    <?php else: ?>
      <p class="modal-login-note">
        Please <a href="<?= BASE_URL ?>/auth/signIn.php">sign in</a> or
        <a href="<?= BASE_URL ?>/auth/signUp.php">create a free account</a>
        to book a consultation.
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     TOAST
═══════════════════════════════════════════════════ -->
<div id="toast"></div>

<!-- ═══════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════ -->
<footer>
  <div class="footer-inner">
    <div>
      <div class="footer-logo">The Editorial Scholar</div>
      <div class="footer-copy">&copy; 2026 The Editorial Scholar.<br/>Curating Global Futures.</div>
    </div>
    <div>
      <div class="footer-col-title">Company</div>
      <a href="#" class="footer-link">About Us</a>
      <a href="#" class="footer-link">Contact Support</a>
    </div>
    <div>
      <div class="footer-col-title">Legal</div>
      <a href="#" class="footer-link">Terms of Service</a>
      <a href="#" class="footer-link">Privacy Policy</a>
      <a href="#" class="footer-link">Academic Integrity</a>
    </div>
    <div>
      <div class="footer-col-title">Social</div>
      <div class="social-links">
        <a href="#"><i class="ri-twitter-x-line"></i></a>
        <a href="#"><i class="ri-linkedin-line"></i></a>
        <a href="#"><i class="ri-instagram-line"></i></a>
      </div>
    </div>
  </div>
</footer>

<!-- ═══════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════ -->
<script>
const BASE      = '<?= BASE_URL ?>';
const LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
const API       = BASE + '/testprep_api.php';

// ── Toast helper ─────────────────────────────────────
function showToast(msg, type = '') {
  const toast = document.getElementById('toast');
  const el    = document.createElement('div');
  el.className = 'toast-msg' + (type ? ' ' + type : '');
  el.textContent = msg;
  toast.appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

// ── Bookmark toggle ───────────────────────────────────
async function toggleBookmark(resourceId, iconEl) {
  if (!LOGGED_IN) {
    showToast('Sign in to bookmark resources.', 'error');
    return;
  }
  const isBookmarked = iconEl.classList.contains('bookmarked');
  const action = isBookmarked ? 'remove' : 'add';

  try {
    const res  = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'bookmark', resource_id: resourceId, mode: action })
    });
    const data = await res.json();
    if (data.success) {
      if (action === 'add') {
        iconEl.classList.replace('ri-bookmark-line', 'ri-bookmark-fill');
        iconEl.classList.add('bookmarked');
        iconEl.title = 'Remove bookmark';
        showToast('Resource bookmarked.', 'success');
      } else {
        iconEl.classList.replace('ri-bookmark-fill', 'ri-bookmark-line');
        iconEl.classList.remove('bookmarked');
        iconEl.title = 'Bookmark';
        showToast('Bookmark removed.');
      }
    } else {
      showToast(data.message || 'Something went wrong.', 'error');
    }
  } catch {
    showToast('Network error. Please try again.', 'error');
  }
}

// ── Download resource ─────────────────────────────────
async function downloadResource(resourceId, fileUrl) {
  // Log the download server-side (best-effort)
  if (LOGGED_IN) {
    fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'log_download', resource_id: resourceId })
    }).catch(() => {});
  }
  if (fileUrl && fileUrl !== '#') {
    window.open(fileUrl, '_blank');
  } else {
    showToast('This resource is not yet available for download.');
  }
}

// ── Resume practice ───────────────────────────────────
<?php if ($isLoggedIn && $lastExam): ?>
document.getElementById('btnResume')?.addEventListener('click', () => {
  showToast('Resuming <?= addslashes($lastExam['exam']) ?> — <?= addslashes($lastExam['module']) ?>…');
  // TODO: navigate to the module player when it exists
  // window.location.href = BASE + '/modules/<?= strtolower($lastExam['exam']) ?>/<?= urlencode($lastExam['module']) ?>';
});
<?php endif; ?>

// ── View all schedules ────────────────────────────────
function viewAllSchedules() {
  showToast('Full schedule coming soon. Check back shortly!');
}

// ── Search filter ─────────────────────────────────────
document.getElementById('globalSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase().trim();
  document.querySelectorAll('.resource-item').forEach(item => {
    const text = item.querySelector('.resource-title').textContent.toLowerCase();
    item.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
});

// ── Consultation modal ────────────────────────────────
function openConsultModal() {
  document.getElementById('consultModal').classList.add('open');
}
function closeConsultModal() {
  document.getElementById('consultModal').classList.remove('open');
}
// Close on backdrop click
document.getElementById('consultModal').addEventListener('click', function (e) {
  if (e.target === this) closeConsultModal();
});

async function submitConsultation() {
  const name  = document.getElementById('consultName')?.value.trim();
  const date  = document.getElementById('consultDate')?.value;
  const time  = document.getElementById('consultTime')?.value;
  const exam  = document.getElementById('consultExam')?.value;
  const notes = document.getElementById('consultNotes')?.value.trim();

  if (!name || !date || !time || !exam) {
    showToast('Please fill in all required fields.', 'error');
    return;
  }

  try {
    const res  = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'book_consultation', name, date, time, exam, notes })
    });
    const data = await res.json();
    if (data.success) {
      closeConsultModal();
      showToast('Consultation booked! We\'ll confirm via email.', 'success');
    } else {
      showToast(data.message || 'Booking failed. Try again.', 'error');
    }
  } catch {
    showToast('Network error. Please try again.', 'error');
  }
}
</script>

</body>
</html>