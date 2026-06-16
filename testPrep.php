<?php
// ─────────────────────────────────────────────────────────────────────────────
//  testPrep.php  — Test Preparation Hub  (complete rebuild)
// ─────────────────────────────────────────────────────────────────────────────
session_start();
require_once __DIR__ . '/includes/auth_helpers.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';

$isLoggedIn = is_logged_in();
$userId     = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;
$userName   = $isLoggedIn ? htmlspecialchars($_SESSION['full_name'] ?? '') : '';

// ── STATIC EXAM DEFINITIONS ──────────────────────────────────────────────────
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
        'color'    => '#2563EB',
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
        'color'    => '#7C3AED',
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
        'color'    => '#059669',
        'reg_url'  => 'https://www.ets.org/toefl/test-takers/ibt/register.html',
    ],
];

// ── STATIC RESOURCE LIBRARY ──────────────────────────────────────────────────
$resources = [
    ['id'=>1,'icon'=>'ri-file-pdf-line',  'title'=>'GRE Quantitative Strategy Guide 2024', 'meta'=>'PDF Document • 4.2 MB • Advanced Level',       'file'=>'#', 'exam'=>'gre'],
    ['id'=>2,'icon'=>'ri-video-line',      'title'=>'TOEFL Speaking: The 4-Template Method',  'meta'=>'Video Lecture • 14:20 • Instructional',           'file'=>'#', 'exam'=>'toefl'],
    ['id'=>3,'icon'=>'ri-article-line',    'title'=>'IELTS Writing Task 2: Band 9 Samples',  'meta'=>'Interactive Article • 8 min read',                'file'=>'#', 'exam'=>'ielts'],
    ['id'=>4,'icon'=>'ri-headphone-line',  'title'=>'IELTS Listening — Section 3 Drill',     'meta'=>'Audio Practice • 22:45 • Intermediate Level',     'file'=>'#', 'exam'=>'ielts'],
];

// ── UPCOMING MOCK SESSIONS ────────────────────────────────────────────────────
$mocks = [
    ['id'=>1,'month'=>'Aug','day'=>'8',  'title'=>'IELTS Full Mock Test',      'duration'=>'3 hrs',  'scope'=>'All sections',  'exam'=>'ielts', 'past'=>false],
    ['id'=>2,'month'=>'Aug','day'=>'15', 'title'=>'GRE Diagnostic Session',    'duration'=>'2 hrs',  'scope'=>'Quant focus',   'exam'=>'gre',   'past'=>false],
    ['id'=>3,'month'=>'Sep','day'=>'3',  'title'=>'TOEFL Full Practice Test',  'duration'=>'2 hrs',  'scope'=>'All sections',  'exam'=>'toefl', 'past'=>false],
];

// ── FETCH USER PROGRESS & STATE (DB) ─────────────────────────────────────────
$progress         = ['ielts'=>0,'gre'=>0,'toefl'=>0];
$bookmarks        = [];
$lastExam         = null;
$mockRegs         = [];
$completedModules = [];

if ($isLoggedIn) {
    try {
        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT exam_key, progress_pct, last_module FROM test_prep_progress WHERE user_id = ?');
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

        $stmt = $pdo->prepare('SELECT resource_id FROM test_prep_bookmarks WHERE user_id = ?');
        $stmt->execute([$userId]);
        $bookmarks = array_column($stmt->fetchAll(), 'resource_id');

        $stmt = $pdo->prepare('SELECT mock_id FROM test_prep_mock_registrations WHERE user_id = ? AND status != "cancelled"');
        $stmt->execute([$userId]);
        $mockRegs = array_map('intval', array_column($stmt->fetchAll(), 'mock_id'));

        $stmt = $pdo->prepare('SELECT exam_key, module_slug FROM test_prep_module_completions WHERE user_id = ?');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $completedModules[$row['exam_key']][] = $row['module_slug'];
        }

    } catch (PDOException $e) {
        error_log('testPrep DB error: ' . $e->getMessage());
    }
}

$progressRows = [
    ['key'=>'ielts', 'label'=>'IELTS Preparation', 'pct'=>$progress['ielts'], 'modules'=>12],
    ['key'=>'gre',   'label'=>'GRE Preparation',   'pct'=>$progress['gre'],   'modules'=>10],
    ['key'=>'toefl', 'label'=>'TOEFL Preparation', 'pct'=>$progress['toefl'], 'modules'=>9],
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
  <link rel="stylesheet" href="<?= $cssPath ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/testPrep.css">

</head>
<body>

<!-- ═══════════════════════════════════════════════════════
     NAV
════════════════════════════════════════════════════════ -->
<nav class="site-nav">
  <div class="nav-inner">
    <span class="nav-logo">The Editorial Scholar</span>
    <div class="nav-links">
      <a href="<?= BASE_URL ?>/index.php">Programs</a>
      <a href="<?= BASE_URL ?>/scholarship.php">Scholarships</a>
      <a href="<?= BASE_URL ?>/testPrep.php" class="active">Test Prep</a>
      <a href="<?= BASE_URL ?>/visa.php">Visa Guide</a>
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
        <button class="btn-signin" onclick="window.location.href='<?= BASE_URL ?>/auth/signIn.php'">Sign in</button>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- ═══════════════════════════════════════════════════════
     MAIN
════════════════════════════════════════════════════════ -->
<main>

  <!-- ── HERO ───────────────────────────────────────────── -->
  <section class="hero">
    <div class="hero-left">
      <p class="eyebrow">Preparation Excellence</p>
      <h1>Mastering the<br/><em>Standardized Path.</em></h1>
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

  <!-- ── EXAM CARDS ─────────────────────────────────────── -->
  <section style="display:flex;flex-direction:column;gap:1.5rem;">
    <div class="section-header">
      <h2 class="section-title">Select Your Exam</h2>
      <a href="#" class="section-link">Compare all exams <i class="ri-arrow-right-line"></i></a>
    </div>

    <div class="exam-grid">
      <?php foreach ($exams as $key => $exam): ?>
      <div class="exam-card" id="exam-card-<?= $key ?>" data-exam="<?= $key ?>">
        <div class="exam-top-bar" style="background:<?= $exam['color'] ?>;"></div>
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
          <div style="display:flex;gap:0.5rem;align-items:center;">
            <button class="cta-btn" onclick="openMaterials('<?= $key ?>')">
              <i class="ri-book-3-line" style="font-size:0.9rem;"></i> Study Now
            </button>
            <a href="<?= htmlspecialchars($exam['reg_url']) ?>" target="_blank" rel="noopener"
               class="cta-btn" style="background:transparent;border:1px solid var(--color-navy);color:var(--color-navy);">
              Register <i class="ri-external-link-line" style="font-size:0.85rem;"></i>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── BENTO GRID ─────────────────────────────────────── -->
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
          <div class="progress-bars" id="progressBars">
            <?php foreach ($progressRows as $row): ?>
            <div class="progress-row" data-exam="<?= $row['key'] ?>">
              <div class="progress-label-row">
                <span class="progress-lbl"><?= htmlspecialchars($row['label']) ?></span>
                <span class="progress-lbl" id="prog-pct-<?= $row['key'] ?>"><?= $row['pct'] ?>%</span>
              </div>
              <div class="progress-track">
                <div class="progress-fill" id="prog-bar-<?= $row['key'] ?>" style="width:<?= $row['pct'] ?>%"></div>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
                <span style="font-size:0.62rem;color:rgba(255,255,255,0.4);">
                  <span id="prog-done-<?= $row['key'] ?>"><?= $row['pct'] > 0 ? (int)round($row['pct'] * $row['modules'] / 100) : 0 ?></span> /
                  <?= $row['modules'] ?> modules
                </span>
                <button onclick="openMaterials('<?= $row['key'] ?>')"
                  style="font-size:0.62rem;font-weight:700;letter-spacing:0.5px;color:var(--color-gold-bg);
                         background:none;border:none;cursor:pointer;text-transform:uppercase;letter-spacing:0.8px;">
                  Continue →
                </button>
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
          <?php foreach ($mocks as $mock):
            $registered = in_array($mock['id'], $mockRegs);
          ?>
          <div class="mock-item <?= $mock['past'] ? 'past' : '' ?>">
            <div class="mock-date-box">
              <span class="mock-month"><?= $mock['month'] ?></span>
              <span class="mock-day"><?= $mock['day'] ?></span>
            </div>
            <div style="flex:1;">
              <div class="mock-name"><?= htmlspecialchars($mock['title']) ?></div>
              <div class="mock-meta"><?= $mock['duration'] ?> &bull; <?= htmlspecialchars($mock['scope']) ?></div>
              <?php if (!$mock['past']): ?>
                <?php if ($isLoggedIn): ?>
                  <button
                    class="mock-register-btn <?= $registered ? 'registered' : '' ?>"
                    style="margin-top:6px;font-size:0.68rem;font-weight:700;letter-spacing:0.5px;
                           padding:4px 12px;border-radius:3px;border:1px solid;cursor:pointer;
                           background:<?= $registered ? '#166534' : 'transparent' ?>;
                           color:<?= $registered ? '#fff' : 'var(--color-navy)' ?>;
                           border-color:<?= $registered ? '#166534' : 'var(--color-navy)' ?>;"
                    onclick="toggleMockReg(<?= $mock['id'] ?>, this)"
                    data-registered="<?= $registered ? '1' : '0' ?>">
                    <?= $registered ? '✓ Registered' : 'Register' ?>
                  </button>
                <?php else: ?>
                  <button
                    style="margin-top:6px;font-size:0.68rem;font-weight:700;letter-spacing:0.5px;
                           padding:4px 12px;border-radius:3px;border:1px solid var(--color-navy);
                           background:transparent;color:var(--color-navy);cursor:pointer;"
                    onclick="window.location.href='<?= BASE_URL ?>/auth/signIn.php'">
                    Sign in to Register
                  </button>
                <?php endif; ?>
              <?php endif; ?>
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
          <p class="scholars-desc">Join 4,000+ applicants discussing prep strategies and score improvements.</p>
          <a href="#" class="scholars-link">Join the Community <i class="ri-arrow-right-line" style="font-size:0.9rem;"></i></a>
        </div>
      </div>

    </aside>
  </section>

  <!-- ── ADMISSIONS LIAISON ─────────────────────────────── -->
  <div class="liaison-box">
    <h3 class="liaison-title">Admissions Liaison</h3>
    <p class="liaison-desc">Need help choosing the right test for your target university?</p>
    <div class="liaison-advisor">
      <div class="liaison-avatar">
        <img src="https://www.shutterstock.com/image-vector/professional-businesswoman-icon-corporate-female-600w-2741202739.jpg" alt="Dr. Sarah Chen" />
      </div>
      <div>
        <div class="liaison-name">Dr. Sarah Chen</div>
        <div class="liaison-role">Admissions Specialist</div>
      </div>
    </div>
    <button class="btn-consult" onclick="openConsultModal()">Book a Consultation</button>
  </div>

</main>

<!-- ═══════════════════════════════════════════════════════
     MATERIALS DRAWER
════════════════════════════════════════════════════════ -->
<div class="materials-overlay" id="materialsOverlay" onclick="handleOverlayClick(event)">
  <div class="materials-drawer" id="materialsDrawer">

    <div class="drawer-head">
      <div style="flex:1;min-width:0;">
        <span class="drawer-exam-badge" id="drawerBadge">IELTS</span>
        <div class="drawer-title" id="drawerTitle">Study Materials</div>
        <div class="drawer-progress-bar">
          <div class="drawer-progress-fill" id="drawerProgressFill" style="width:0%"></div>
        </div>
        <div class="drawer-progress-label">
          <span id="drawerProgressLabel">0% complete</span>
          <span id="drawerModuleCount">0 / 0 modules</span>
        </div>
      </div>
      <button class="drawer-close" onclick="closeMaterials()"><i class="ri-close-line"></i></button>
    </div>

    <!-- Tabs -->
    <div class="drawer-tabs">
      <button class="drawer-tab active" onclick="switchTab('modules', this)">Modules</button>
      <button class="drawer-tab" onclick="switchTab('overview', this)">Overview</button>
      <button class="drawer-tab" onclick="switchTab('schedule', this)">Mock Tests</button>
    </div>

    <!-- Tab: Modules -->
    <div class="drawer-tab-panel active" id="tab-modules">
      <div class="drawer-filters" id="sectionFilters"></div>
      <div id="materialsContainer" style="display:flex;flex-direction:column;gap:0.75rem;padding:0 0 0.5rem;">
        <div class="materials-loading">
          <i class="ri-loader-4-line"></i>Loading modules…
        </div>
      </div>
      <div class="drawer-pagination" id="drawerPagination"></div>
    </div>

    <!-- Tab: Overview -->
    <div class="drawer-tab-panel" id="tab-overview">
      <div id="overviewContent"></div>
    </div>

    <!-- Tab: Mock Tests -->
    <div class="drawer-tab-panel" id="tab-schedule">
      <div id="scheduleContent"></div>
    </div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     CONSULTATION MODAL
════════════════════════════════════════════════════════ -->
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
            <option>10:00 AM</option><option>11:00 AM</option>
            <option>2:00 PM</option><option>3:00 PM</option><option>4:00 PM</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <label>Target Exam</label>
        <select id="consultExam">
          <option value="">— select —</option>
          <option>IELTS</option><option>GRE</option><option>TOEFL</option><option>Multiple / Unsure</option>
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
        <a href="<?= BASE_URL ?>/auth/signUp.php">create a free account</a> to book a consultation.
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- TOAST -->
<div id="toast"></div>

<!-- ═══════════════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════════════ -->
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

<!-- ═══════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════ -->
<script>
const BASE      = '<?= BASE_URL ?>';
const LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
const API       = BASE + '/testprep_api.php';

// PHP-side data injected for immediate use
const INIT_PROGRESS  = <?= json_encode($progress) ?>;
const INIT_BOOKMARKS = <?= json_encode($bookmarks) ?>;
const INIT_MOCKREGS  = <?= json_encode($mockRegs) ?>;
const INIT_COMPLETED = <?= json_encode($completedModules) ?>;
const MODULE_TOTALS  = { ielts: 12, gre: 10, toefl: 9 };
const EXAM_NAMES     = { ielts: 'IELTS', gre: 'GRE', toefl: 'TOEFL' };

// ── Toast ────────────────────────────────────────────────
function showToast(msg, type = '') {
  const toast = document.getElementById('toast');
  const el    = document.createElement('div');
  el.className = 'toast-msg' + (type ? ' ' + type : '');
  el.textContent = msg;
  toast.appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

// ── Progress UI update ───────────────────────────────────
function updateProgressUI(examKey, pct, done) {
  const bar  = document.getElementById('prog-bar-' + examKey);
  const pctEl= document.getElementById('prog-pct-' + examKey);
  const doneEl=document.getElementById('prog-done-' + examKey);
  if (bar)   bar.style.width = pct + '%';
  if (pctEl) pctEl.textContent = pct + '%';
  if (doneEl)doneEl.textContent = done;

  // Update drawer header if open on this exam
  if (_drawerExam === examKey) {
    const total = MODULE_TOTALS[examKey];
    document.getElementById('drawerProgressFill').style.width = pct + '%';
    document.getElementById('drawerProgressLabel').textContent = pct + '% complete';
    document.getElementById('drawerModuleCount').textContent = done + ' / ' + total + ' modules';
  }
}

// ── Bookmark toggle ──────────────────────────────────────
async function toggleBookmark(resourceId, iconEl) {
  if (!LOGGED_IN) { showToast('Sign in to bookmark resources.', 'error'); return; }
  const isBookmarked = iconEl.classList.contains('bookmarked');
  const action = isBookmarked ? 'remove' : 'add';

  try {
    const res  = await fetch(API, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
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
  } catch { showToast('Network error. Please try again.', 'error'); }
}

// ── Download resource ────────────────────────────────────
async function downloadResource(resourceId, fileUrl) {
  if (LOGGED_IN) {
    fetch(API, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'log_download', resource_id: resourceId })
    }).catch(() => {});
  }
  if (fileUrl && fileUrl !== '#') {
    window.open(fileUrl, '_blank');
  } else {
    showToast('This resource is not yet available for download.');
  }
}

// ── Mock register ────────────────────────────────────────
async function toggleMockReg(mockId, btn) {
  const alreadyRegistered = btn.dataset.registered === '1';
  const action = alreadyRegistered ? 'unregister_mock' : 'register_mock';
  btn.disabled = true;
  try {
    const res  = await fetch(API, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, mock_id: mockId })
    });
    const data = await res.json();
    if (data.success) {
      if (!alreadyRegistered) {
        btn.textContent = '✓ Registered';
        btn.dataset.registered = '1';
        btn.style.background = '#166534';
        btn.style.color = '#fff';
        btn.style.borderColor = '#166534';
        showToast('You\'re registered for this mock session.', 'success');
      } else {
        btn.textContent = 'Register';
        btn.dataset.registered = '0';
        btn.style.background = 'transparent';
        btn.style.color = 'var(--color-navy)';
        btn.style.borderColor = 'var(--color-navy)';
        showToast('Registration cancelled.');
      }
    } else {
      showToast(data.message || 'Something went wrong.', 'error');
    }
  } catch { showToast('Network error. Please try again.', 'error'); }
  finally { btn.disabled = false; }
}

// ── Mark module complete ─────────────────────────────────
async function markModuleComplete(examKey, moduleSlug, btn) {
  if (!LOGGED_IN) { showToast('Sign in to track your progress.', 'error'); return; }

  const isDone = btn.classList.contains('done');
  if (isDone) { showToast('Module already marked as complete.'); return; }

  btn.disabled = true;
  btn.innerHTML = '<i class="ri-loader-4-line"></i> Saving…';

  try {
    const res  = await fetch(API, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_progress', exam_key: examKey, module: moduleSlug })
    });
    const data = await res.json();
    if (data.success) {
      // Update button state
      btn.classList.replace('not-done', 'done');
      btn.innerHTML = '<i class="ri-checkbox-circle-fill"></i> Completed';
      btn.disabled  = false;

      // Update parent row
      const row = btn.closest('.material-row');
      if (row) row.classList.add('completed');

      // Update progress bars
      updateProgressUI(examKey, data.progress_pct, data.modules_done);

      // Update resume button
      const resumeBtn = document.getElementById('btnResume');
      if (resumeBtn) {
        resumeBtn.disabled   = false;
        resumeBtn.textContent= 'Resume ' + EXAM_NAMES[examKey];
      }

      showToast('Module complete! Progress saved.', 'success');
    } else {
      btn.innerHTML = 'Mark Complete';
      btn.disabled  = false;
      showToast(data.message || 'Could not save progress.', 'error');
    }
  } catch {
    btn.innerHTML = 'Mark Complete';
    btn.disabled  = false;
    showToast('Network error. Please try again.', 'error');
  }
}

// ── Materials Drawer ─────────────────────────────────────
let _drawerExam     = null;
let _currentPage    = 1;
let _activeFilter   = 'all';
const _examData     = <?= json_encode(array_map(fn($e) => [
    'name'     => $e['name'],
    'subtitle' => $e['subtitle'],
    'sections' => $e['sections'],
    'modules'  => $e['modules'],
    'score'    => $e['score'],
    'score_lbl'=> $e['score_lbl'],
    'fee'      => $e['fee'],
    'duration' => $e['duration'],
    'validity' => $e['validity'],
    'reg_url'  => $e['reg_url'],
], $exams)) ?>;

function openMaterials(examKey) {
  _drawerExam   = examKey;
  _currentPage  = 1;
  _activeFilter = 'all';

  const exam = _examData[examKey];

  // Update drawer header
  document.getElementById('drawerBadge').textContent = exam.name;
  document.getElementById('drawerTitle').textContent = exam.name + ' Preparation';

  const pct   = INIT_PROGRESS[examKey] || 0;
  const total = MODULE_TOTALS[examKey];
  const done  = Math.round(pct * total / 100);
  document.getElementById('drawerProgressFill').style.width = pct + '%';
  document.getElementById('drawerProgressLabel').textContent = pct + '% complete';
  document.getElementById('drawerModuleCount').textContent   = done + ' / ' + total + ' modules';

  // Highlight exam card
  document.querySelectorAll('.exam-card').forEach(c => c.classList.remove('panel-open'));
  const card = document.getElementById('exam-card-' + examKey);
  if (card) card.classList.add('panel-open');

  // Build overview tab
  buildOverviewTab(examKey);
  buildScheduleTab(examKey);

  // Show drawer
  document.getElementById('materialsOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';

  // Switch to modules tab & load
  switchTab('modules', document.querySelector('.drawer-tab'));
  loadMaterials(1);
}

function closeMaterials() {
  document.getElementById('materialsOverlay').classList.remove('open');
  document.body.style.overflow = '';
  document.querySelectorAll('.exam-card').forEach(c => c.classList.remove('panel-open'));
}

function handleOverlayClick(e) {
  if (e.target === document.getElementById('materialsOverlay')) closeMaterials();
}

// ── Load materials via API ────────────────────────────────
async function loadMaterials(page = 1) {
  _currentPage = page;
  const container = document.getElementById('materialsContainer');
  container.innerHTML = '<div class="materials-loading"><i class="ri-loader-4-line"></i>Loading modules…</div>';
  document.getElementById('drawerPagination').innerHTML = '';

  try {
    const res  = await fetch(API, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'get_materials', exam_key: _drawerExam, page })
    });
    const data = await res.json();

    if (!data.success) { throw new Error(data.message || 'Load failed'); }

    // Build section filters
    const allSections = [...new Set(data.materials.map(m => m.section_tag).filter(Boolean))];
    buildSectionFilters(allSections);

    // Render
    renderMaterials(data.materials);
    renderPagination(data.page, data.total_pages);

  } catch (err) {
    container.innerHTML = `
      <div style="padding:2rem;text-align:center;color:#991b1b;background:#fef2f2;border-radius:6px;font-size:0.83rem;">
        <i class="ri-error-warning-line" style="display:block;font-size:1.5rem;margin-bottom:0.5rem;"></i>
        Could not load modules. ${err.message}
      </div>`;
  }
}

// Fallback: render from static data if API not wired yet
function loadMaterialsFallback() {
  const staticData = {
    ielts:  [
      {module_slug:'ielts-intro',completed:false,icon:'ri-play-circle-line',title:'IELTS Overview & Exam Format',meta:'Video • 18 min • All Levels',section_tag:'Overview',material_type:'video'},
      {module_slug:'ielts-listening-1',completed:false,icon:'ri-headphone-line',title:'Listening Section 1 – Form Completion',meta:'Audio Practice • 22:45 • Beginner',section_tag:'Listening',material_type:'audio'},
      {module_slug:'ielts-reading-skills',completed:false,icon:'ri-video-line',title:'Reading Skimming & Scanning Masterclass',meta:'Video Lecture • 24 min • Intermediate',section_tag:'Reading',material_type:'video'},
      {module_slug:'ielts-writing-task2',completed:false,icon:'ri-article-line',title:'Writing Task 2 – Band 9 Sample Essays',meta:'Interactive Article • 8 min read',section_tag:'Writing',material_type:'article'},
      {module_slug:'ielts-speaking-parts',completed:false,icon:'ri-video-line',title:'Speaking Parts 1–3 Full Walkthrough',meta:'Video Lecture • 28 min • Intermediate',section_tag:'Speaking',material_type:'video'},
      {module_slug:'ielts-full-mock',completed:false,icon:'ri-questionnaire-line',title:'Full IELTS Mock Test with Answer Key',meta:'Mock Exam • 2 hrs 45 min • All Levels',section_tag:'Mock Test',material_type:'quiz'},
    ],
    gre:   [
      {module_slug:'gre-intro',completed:false,icon:'ri-play-circle-line',title:'GRE General Test Overview',meta:'Video • 14 min • All Levels',section_tag:'Overview',material_type:'video'},
      {module_slug:'gre-verbal-vocab',completed:false,icon:'ri-file-pdf-line',title:'GRE Vocabulary: High-Frequency Words 500+',meta:'PDF Flashcards • 5.1 MB • All Levels',section_tag:'Verbal',material_type:'pdf'},
      {module_slug:'gre-quant-strategy',completed:false,icon:'ri-file-pdf-line',title:'GRE Quantitative Strategy Guide',meta:'PDF Document • 4.2 MB • Advanced',section_tag:'Quantitative',material_type:'pdf'},
      {module_slug:'gre-aw-issue',completed:false,icon:'ri-article-line',title:'Analytical Writing: Issue Task Templates',meta:'Article + Samples • 15 min • Advanced',section_tag:'Analytical Writing',material_type:'article'},
      {module_slug:'gre-full-mock',completed:false,icon:'ri-questionnaire-line',title:'GRE Full-Length Adaptive Mock Test',meta:'Mock Exam • 1 hr 58 min • All Levels',section_tag:'Mock Test',material_type:'quiz'},
    ],
    toefl: [
      {module_slug:'toefl-intro',completed:false,icon:'ri-play-circle-line',title:'TOEFL iBT Format & Scoring Guide',meta:'Video • 12 min • All Levels',section_tag:'Overview',material_type:'video'},
      {module_slug:'toefl-listening-notes',completed:false,icon:'ri-headphone-line',title:'Listening: Note-Taking Techniques',meta:'Audio + Notes • 25 min • All Levels',section_tag:'Listening',material_type:'audio'},
      {module_slug:'toefl-speaking-t1',completed:false,icon:'ri-video-line',title:'Speaking Task 1: Independent Response Method',meta:'TOEFL Speaking • 14:20 • Instructional',section_tag:'Speaking',material_type:'video'},
      {module_slug:'toefl-writing-integrated',completed:false,icon:'ri-article-line',title:'Integrated Writing Task: Read + Listen + Write',meta:'Interactive Article • 11 min • Advanced',section_tag:'Writing',material_type:'article'},
      {module_slug:'toefl-full-mock',completed:false,icon:'ri-questionnaire-line',title:'TOEFL iBT Full Practice Test',meta:'Mock Exam • ~2 hrs • All Levels',section_tag:'Mock Test',material_type:'quiz'},
    ],
  };

  const completed = INIT_COMPLETED[_drawerExam] || [];
  const items = (staticData[_drawerExam] || []).map(m => ({
    ...m,
    completed: completed.includes(m.module_slug),
  }));

  const sections = [...new Set(items.map(m => m.section_tag).filter(Boolean))];
  buildSectionFilters(sections);
  renderMaterials(items);
  document.getElementById('drawerPagination').innerHTML = '';
}

function buildSectionFilters(sections) {
  const wrap = document.getElementById('sectionFilters');
  const all  = ['all', ...sections];
  wrap.innerHTML = all.map(s => `
    <button class="filter-chip ${s === _activeFilter ? 'active' : ''}"
      onclick="setFilter('${s}', this)">${s === 'all' ? 'All' : escHtml(s)}</button>
  `).join('');
}

function setFilter(val, chip) {
  _activeFilter = val;
  document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  filterMaterials();
}

function filterMaterials() {
  document.querySelectorAll('.material-row').forEach(row => {
    const tag = row.dataset.section || '';
    row.style.display = (_activeFilter === 'all' || tag === _activeFilter) ? '' : 'none';
  });
}

function renderMaterials(materials) {
  const container = document.getElementById('materialsContainer');
  const completed = INIT_COMPLETED[_drawerExam] || [];

  if (!materials.length) {
    container.innerHTML = '<div class="materials-loading"><i class="ri-inbox-line"></i>No modules found.</div>';
    return;
  }

  container.innerHTML = materials.map(m => {
    const isDone = m.completed || completed.includes(m.module_slug);
    return `
    <div class="material-row ${isDone ? 'completed' : ''}" data-section="${escHtml(m.section_tag || '')}">
      <div class="material-icon-wrap"><i class="${escHtml(m.icon || 'ri-file-line')}"></i></div>
      <div class="material-info">
        <div class="material-title">${escHtml(m.title)}</div>
        <div class="material-meta">${escHtml(m.meta || '')}</div>
        ${m.section_tag ? `<span class="material-section-tag">${escHtml(m.section_tag)}</span>` : ''}
      </div>
      <div class="material-actions">
        ${LOGGED_IN
          ? `<button class="btn-complete ${isDone ? 'done' : 'not-done'}"
                onclick="markModuleComplete('${escHtml(_drawerExam)}', '${escHtml(m.module_slug)}', this)">
              ${isDone
                ? '<i class="ri-checkbox-circle-fill"></i> Completed'
                : 'Mark Complete'}
            </button>`
          : `<button class="btn-complete not-done"
                onclick="window.location.href='${BASE}/auth/signIn.php'"
                title="Sign in to track progress">
              Sign in
            </button>`
        }
        <button class="material-download-btn" title="Open resource"
          onclick="downloadResource(0, '#')">
          <i class="ri-arrow-right-s-line"></i>
        </button>
      </div>
    </div>`;
  }).join('');

  filterMaterials();
}

function renderPagination(current, total) {
  const wrap = document.getElementById('drawerPagination');
  if (total <= 1) { wrap.innerHTML = ''; return; }
  let html = '';
  if (current > 1) html += `<button class="page-btn" onclick="loadMaterials(${current - 1})"><i class="ri-arrow-left-s-line"></i></button>`;
  for (let i = 1; i <= total; i++) {
    html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="loadMaterials(${i})">${i}</button>`;
  }
  if (current < total) html += `<button class="page-btn" onclick="loadMaterials(${current + 1})"><i class="ri-arrow-right-s-line"></i></button>`;
  wrap.innerHTML = html;
}

function buildOverviewTab(examKey) {
  const exam = _examData[examKey];
  const pct  = INIT_PROGRESS[examKey] || 0;
  const total= MODULE_TOTALS[examKey];
  const done = Math.round(pct * total / 100);

  document.getElementById('overviewContent').innerHTML = `
    <div class="exam-score-grid">
      <div class="exam-score-card">
        <div class="exam-score-val">${exam.score}</div>
        <div class="exam-score-lbl">${exam.score_lbl}</div>
      </div>
      <div class="exam-score-card">
        <div class="exam-score-val">${exam.duration}</div>
        <div class="exam-score-lbl">Duration</div>
      </div>
      <div class="exam-score-card">
        <div class="exam-score-val">${exam.fee}</div>
        <div class="exam-score-lbl">Exam Fee</div>
      </div>
      <div class="exam-score-card">
        <div class="exam-score-val">${exam.validity}</div>
        <div class="exam-score-lbl">Score Validity</div>
      </div>
    </div>

    <p style="font-size:0.82rem;line-height:1.65;color:var(--color-slate);margin-bottom:1rem;">${exam.subtitle}</p>

    <div style="margin-bottom:1rem;">
      <p style="font-size:0.7rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--color-muted);margin-bottom:0.5rem;">Sections Covered</p>
      <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
        ${exam.sections.map(s => `<span class="exam-tag">${escHtml(s)}</span>`).join('')}
      </div>
    </div>

    ${LOGGED_IN ? `
    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:1rem;margin-bottom:1rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
        <span style="font-size:0.75rem;font-weight:700;color:var(--color-navy);">Your Progress</span>
        <span style="font-size:0.75rem;color:var(--color-muted);">${done}/${total} modules</span>
      </div>
      <div style="background:#E2E8F0;border-radius:9999px;height:6px;overflow:hidden;">
        <div style="width:${pct}%;height:100%;background:linear-gradient(90deg,#775A19,#A16207);border-radius:9999px;transition:width 0.5s;"></div>
      </div>
      <p style="font-size:0.7rem;color:var(--color-muted);margin-top:0.35rem;">${pct}% complete</p>
    </div>` : `
    <div class="auth-gate-inline" style="margin-bottom:1rem;">
      <i class="ri-lock-line" style="font-size:1.5rem;display:block;margin:0 auto 0.5rem;color:#94A3B8;"></i>
      <a href="${BASE}/auth/signIn.php">Sign in</a> to track your progress across ${exam.name} modules.
    </div>`}

    <a href="${exam.reg_url}" target="_blank" rel="noopener"
       style="display:flex;align-items:center;justify-content:center;gap:0.5rem;
              height:42px;background:var(--color-navy);color:#fff;border-radius:4px;
              font-size:0.78rem;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;
              text-decoration:none;transition:background 0.2s;"
       onmouseover="this.style.background='#1A2B48'"
       onmouseout="this.style.background='var(--color-navy)'">
      Register for ${exam.name} <i class="ri-external-link-line"></i>
    </a>`;
}

function buildScheduleTab(examKey) {
  const mocks = [
    {id:1,month:'Aug',day:'8', title:'IELTS Full Mock Test',    duration:'3 hrs',scope:'All sections',  exam:'ielts'},
    {id:2,month:'Aug',day:'15',title:'GRE Diagnostic Session',  duration:'2 hrs',scope:'Quant focus',   exam:'gre'},
    {id:3,month:'Sep',day:'3', title:'TOEFL Full Practice Test',duration:'2 hrs',scope:'All sections',  exam:'toefl'},
  ];
  const relevant = mocks.filter(m => m.exam === examKey);
  const all      = mocks.filter(m => m.exam !== examKey);

  document.getElementById('scheduleContent').innerHTML = `
    <p style="font-size:0.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--color-muted);margin-bottom:0.75rem;">
      ${EXAM_NAMES[examKey]} Sessions
    </p>
    ${relevant.length ? relevant.map(m => mockCard(m)).join('') : '<p style="font-size:0.82rem;color:var(--color-muted);">No sessions scheduled yet.</p>'}
    ${all.length ? `
    <p style="font-size:0.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--color-muted);margin:1.25rem 0 0.75rem;">Other Sessions</p>
    ${all.map(m => mockCard(m)).join('')}` : ''}`;
}

function mockCard(m) {
  const registered = INIT_MOCKREGS.includes(m.id);
  return `
  <div class="mock-item" style="margin-bottom:0.75rem;">
    <div class="mock-date-box"><span class="mock-month">${m.month}</span><span class="mock-day">${m.day}</span></div>
    <div style="flex:1;">
      <div class="mock-name">${escHtml(m.title)}</div>
      <div class="mock-meta">${m.duration} &bull; ${escHtml(m.scope)}</div>
      ${LOGGED_IN
        ? `<button
            class="mock-register-btn ${registered ? 'registered' : ''}"
            style="margin-top:6px;font-size:0.68rem;font-weight:700;letter-spacing:0.5px;
                   padding:4px 12px;border-radius:3px;border:1px solid;cursor:pointer;
                   background:${registered ? '#166534' : 'transparent'};
                   color:${registered ? '#fff' : 'var(--color-navy)'};
                   border-color:${registered ? '#166534' : 'var(--color-navy)'};"
            onclick="toggleMockReg(${m.id}, this)"
            data-registered="${registered ? '1' : '0'}">
            ${registered ? '✓ Registered' : 'Register'}
          </button>`
        : `<button style="margin-top:6px;font-size:0.68rem;font-weight:700;padding:4px 12px;border-radius:3px;border:1px solid var(--color-navy);background:transparent;color:var(--color-navy);cursor:pointer;"
              onclick="window.location.href='${BASE}/auth/signIn.php'">Sign in to Register</button>`}
    </div>
  </div>`;
}

// ── Tab switching ────────────────────────────────────────
function switchTab(name, btn) {
  document.querySelectorAll('.drawer-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.drawer-tab-panel').forEach(p => p.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.getElementById('tab-' + name)?.classList.add('active');
}

// ── Resume practice ──────────────────────────────────────
<?php if ($isLoggedIn && $lastExam): ?>
document.getElementById('btnResume')?.addEventListener('click', () => {
  openMaterials('<?= strtolower($lastExam['exam']) ?>');
});
<?php endif; ?>

// ── View all schedules ───────────────────────────────────
function viewAllSchedules() {
  showToast('Full schedule coming soon. Check back shortly!');
}

// ── Search filter ────────────────────────────────────────
document.getElementById('globalSearch').addEventListener('input', function () {
  const q = this.value.toLowerCase().trim();
  document.querySelectorAll('.resource-item').forEach(item => {
    const text = item.querySelector('.resource-title').textContent.toLowerCase();
    item.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
});

// ── Consultation modal ───────────────────────────────────
function openConsultModal()  { document.getElementById('consultModal').classList.add('open'); }
function closeConsultModal() { document.getElementById('consultModal').classList.remove('open'); }
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
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'book_consultation', name, date, time, exam, notes })
    });
    const data = await res.json();
    if (data.success) {
      closeConsultModal();
      showToast('Consultation booked! We\'ll confirm via email.', 'success');
    } else {
      showToast(data.message || 'Booking failed. Try again.', 'error');
    }
  } catch { showToast('Network error. Please try again.', 'error'); }
}

// ── Escape HTML helper ───────────────────────────────────
function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── On load: try real API, fallback to static ─────────────
async function tryLoadMaterials() {
  const container = document.getElementById('materialsContainer');
  try {
    const res = await fetch(API, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'get_materials', exam_key: _drawerExam, page: _currentPage })
    });
    const data = await res.json();
    if (data.success && data.materials.length > 0) {
      const sections = [...new Set(data.materials.map(m => m.section_tag).filter(Boolean))];
      buildSectionFilters(sections);
      renderMaterials(data.materials);
      renderPagination(data.page, data.total_pages);
    } else {
      loadMaterialsFallback();
    }
  } catch {
    loadMaterialsFallback();
  }
}

// Override loadMaterials to use tryLoadMaterials (graceful fallback)
async function loadMaterials(page = 1) {
  _currentPage = page;
  document.getElementById('materialsContainer').innerHTML =
    '<div class="materials-loading"><i class="ri-loader-4-line"></i>Loading modules…</div>';
  document.getElementById('drawerPagination').innerHTML = '';
  await tryLoadMaterials();
}
</script>
</body>
</html>