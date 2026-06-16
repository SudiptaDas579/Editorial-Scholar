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
    ['id'=>1,'month'=>'Aug','day'=>'8',  'title'=>'IELTS Full Mock Test',     'duration'=>'3 hrs','scope'=>'All sections',  'past'=>false],
    ['id'=>2,'month'=>'Aug','day'=>'15', 'title'=>'GRE Diagnostic Session',   'duration'=>'2 hrs','scope'=>'Quant focus',   'past'=>false],
    ['id'=>3,'month'=>'Sep','day'=>'3',  'title'=>'TOEFL Full Practice Test',  'duration'=>'2 hrs','scope'=>'All sections',  'past'=>false],
];

// ── FETCH USER PROGRESS (DB) ─────────────────────────
$progress  = ['ielts'=>0,'gre'=>0,'toefl'=>0];
$bookmarks = [];
$lastExam  = null;
$mockRegs  = []; // IDs of mocks the user has registered for

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

        // Mock registrations
        $stmt = $pdo->prepare(
            'SELECT mock_id FROM test_prep_mock_registrations WHERE user_id = ? AND status != "cancelled"'
        );
        $stmt->execute([$userId]);
        $mockRegs = array_column($stmt->fetchAll(), 'mock_id');

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

  <!-- Test Prep component styles (extracted from inline <style>) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/testPrep.css">
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
                           padding:3px 10px;border-radius:3px;border:1px solid;cursor:pointer;
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
                           padding:3px 10px;border-radius:3px;border:1px solid var(--color-navy);
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
const API       = BASE + '/tesrprep_api.php';

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

// ── Mock session register/unregister ─────────────────
async function toggleMockReg(mockId, btn) {
  const alreadyRegistered = btn.dataset.registered === '1';
  const action = alreadyRegistered ? 'unregister_mock' : 'register_mock';

  btn.disabled = true;
  try {
    const res  = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, mock_id: mockId })
    });
    const data = await res.json();
    if (data.success) {
      if (!alreadyRegistered) {
        btn.textContent      = '✓ Registered';
        btn.dataset.registered = '1';
        btn.style.background = '#166534';
        btn.style.color      = '#fff';
        btn.style.borderColor= '#166534';
        showToast('You\'re registered for this mock session.', 'success');
      } else {
        btn.textContent      = 'Register';
        btn.dataset.registered = '0';
        btn.style.background = 'transparent';
        btn.style.color      = 'var(--color-navy)';
        btn.style.borderColor= 'var(--color-navy)';
        showToast('Registration cancelled.');
      }
    } else {
      showToast(data.message || 'Something went wrong.', 'error');
    }
  } catch {
    showToast('Network error. Please try again.', 'error');
  } finally {
    btn.disabled = false;
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