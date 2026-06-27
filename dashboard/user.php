<?php
// dashboard/user.php — Student Dashboard
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

require_role('user');

$pdo      = getDB();
$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'Student';
$initials = strtoupper(substr($userName, 0, 1));

// ── Tracked scholarships (session-based, mirrors scholarship.php) ──────────
$trackedGrants = $_SESSION['tracked_grants'] ?? [];

// ── Saved research programs ─────────────────────────────────────────────────
$savedPrograms = $_SESSION['saved_programs'] ?? [];

// ── Quick stats ─────────────────────────────────────────────────────────────
$trackedCount  = count($trackedGrants);
$savedCount    = count($savedPrograms);

// ── Upcoming deadlines from session-tracked scholarships ────────────────────
// We reference the same fixture data as scholarship_backend.php uses
$deadlines = [];
require_once __DIR__ . '/../config/scholarship_data.php';
$allScholarships = $scholarshipFixture['scholarships'] ?? [];

// Rebuild from fixture if require_once already loaded the data differently
// (scholarship_data.php returns an array, so we load it directly)
$fixture = require __DIR__ . '/../config/scholarship_data.php';
$allScholarships = $fixture['scholarships'] ?? [];

foreach ($trackedGrants as $id => $status) {
    foreach ($allScholarships as $s) {
        if ($s['id'] === $id) {
            $deadlines[] = [
                'title'    => $s['title'],
                'program'  => $s['program'],
                'deadline' => $s['deadline_label'],
                'date'     => $s['deadline_date'],
                'status'   => $status,
                'amount'   => $s['amount'],
            ];
            break;
        }
    }
}

usort($deadlines, fn($a, $b) => strcmp($a['date'], $b['date']));

// ── Advisors (pull approved advisors from DB) ───────────────────────────────
try {
    $advisorStmt = $pdo->prepare(
        "SELECT u.full_name, ap.specialization, ap.experience_yrs, ap.bio, ap.rating, ap.linkedin_url
         FROM advisor_profiles ap
         JOIN users u ON u.id = ap.user_id
         WHERE ap.available = 1
         ORDER BY ap.rating DESC
         LIMIT 3"
    );
    $advisorStmt->execute();
    $advisors = $advisorStmt->fetchAll();
} catch (\Throwable $e) {
    $advisors = [];
}

$pageTitle = 'My Dashboard';
$activeNav = '';
$cssPath   = BASE_URL . '/src/output.css';
?>
<?php include __DIR__ . '/../includes/head.php'; ?>

<style>
  :root {
    --primary-dark: #0A192F;
    --accent-gold: #8B6E30;
    --gold-light: #A16207;
    --bg-light: #F4F7F9;
    --white: #FFFFFF;
    --text-gray: #64748B;
    --border-color: #E2E8F0;
    --success: #059669;
    --danger: #DC2626;
    --warning: #A16207;
  }

  * { box-sizing: border-box; }

  body {
    font-family: 'Manrope', sans-serif;
    background: var(--bg-light);
    color: var(--primary-dark);
  }

  .dash-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    min-height: calc(100vh - 60px);
    margin-top: 60px;
  }

  /* ── Sidebar ── */
  .dash-sidebar {
    background: var(--white);
    border-right: 1px solid var(--border-color);
    padding: 28px 16px;
    position: sticky;
    top: 60px;
    height: calc(100vh - 60px);
    overflow-y: auto;
  }

  .user-card {
    background: var(--bg-light);
    border-radius: 10px;
    padding: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 28px;
  }

  .avatar {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    background: var(--primary-dark);
    color: white;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 0.85rem;
    flex-shrink: 0;
  }

  .avatar.gold { background: #775A19; }

  .nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 6px;
    color: var(--text-gray);
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.15s;
    margin-bottom: 2px;
  }

  .nav-item:hover, .nav-item.active {
    background: #F1F5F9;
    color: var(--accent-gold);
  }

  .nav-item i { font-size: 1rem; width: 18px; }

  .nav-divider {
    margin: 16px 0;
    border: none;
    border-top: 1px solid var(--border-color);
  }

  /* ── Main content ── */
  .dash-main { padding: 36px 40px; min-width: 0; }

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 16px;
  }

  .page-title {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.9rem;
    font-weight: 700;
    margin: 0 0 4px;
    color: var(--primary-dark);
  }

  /* ── Stat cards ── */
  .stat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 36px;
  }

  .stat-card {
    background: var(--white);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 22px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 1.2rem;
    flex-shrink: 0;
  }

  .stat-icon.navy  { background: #EFF4FF; color: #031632; }
  .stat-icon.gold  { background: #FEF3C7; color: #A16207; }
  .stat-icon.green { background: #D1FAE5; color: #059669; }

  .stat-value { font-size: 1.7rem; font-weight: 800; line-height: 1; }
  .stat-label { font-size: 0.78rem; color: var(--text-gray); margin-top: 3px; }

  /* ── Content grid ── */
  .content-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
  }

  /* ── Section card ── */
  .section-card {
    background: var(--white);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 24px;
  }

  .card-head {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .card-head h2 {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
  }

  .card-body { padding: 20px 24px; }

  /* ── Deadline row ── */
  .deadline-row {
    display: flex;
    gap: 14px;
    padding: 14px 0;
    border-bottom: 1px solid var(--border-color);
    align-items: flex-start;
  }

  .deadline-row:last-child { border-bottom: none; }

  .deadline-date-box {
    background: var(--bg-light);
    border-radius: 6px;
    padding: 8px 10px;
    text-align: center;
    min-width: 52px;
    flex-shrink: 0;
  }

  .deadline-month { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: var(--text-gray); }
  .deadline-day   { font-size: 1.3rem; font-weight: 800; line-height: 1; color: var(--primary-dark); }

  .status-badge {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 999px;
    letter-spacing: .04em;
  }

  .badge-watching   { background: #EFF6FF; color: #1D4ED8; }
  .badge-preparing  { background: #FEF3C7; color: #A16207; }
  .badge-submitted  { background: #D1FAE5; color: #059669; }
  .badge-archived   { background: #F3F4F6; color: #6B7280; }

  /* ── Document checklist ── */
  .doc-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
  }

  .doc-item:last-child { border-bottom: none; }

  .doc-icon {
    width: 34px;
    height: 34px;
    background: #F8FAFC;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    display: grid;
    place-items: center;
    font-size: 0.9rem;
    flex-shrink: 0;
    color: var(--text-gray);
  }

  /* ── Advisor cards ── */
  .advisor-card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    background: #FAFBFC;
  }

  .advisor-card:last-child { margin-bottom: 0; }

  .star-rating { color: #A16207; font-size: 0.75rem; }

  /* ── Hero banner ── */
  .welcome-banner {
    background: linear-gradient(135deg, #031632 0%, #1A2B48 100%);
    border-radius: 10px;
    padding: 28px 32px;
    color: white;
    margin-bottom: 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
  }

  .welcome-banner h2 {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 6px;
  }

  .welcome-banner p { font-size: 0.875rem; color: #8293B5; margin: 0; }

  .btn-gold-sm {
    background: #775A19;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-family: 'Manrope', sans-serif;
    font-weight: 700;
    font-size: 0.825rem;
    cursor: pointer;
    white-space: nowrap;
    text-decoration: none;
    display: inline-block;
    transition: background 0.2s;
  }

  .btn-gold-sm:hover { background: #A16207; }

  .btn-outline-sm {
    border: 1px solid var(--border-color);
    background: white;
    color: var(--primary-dark);
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
  }

  .btn-outline-sm:hover { border-color: #775A19; color: #775A19; }

  /* ── Progress bar ── */
  .progress-bar {
    background: var(--border-color);
    border-radius: 999px;
    height: 6px;
    overflow: hidden;
    margin-top: 8px;
  }

  .progress-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #775A19, #A16207);
  }

  /* ── Empty state ── */
  .empty-state {
    text-align: center;
    padding: 36px 20px;
    color: var(--text-gray);
  }

  .empty-state i { font-size: 2.5rem; opacity: .3; display: block; margin-bottom: 8px; }

  @media (max-width: 1100px) {
    .dash-layout { grid-template-columns: 1fr; }
    .dash-sidebar { position: static; height: auto; border-right: none; border-bottom: 1px solid var(--border-color); }
    .content-grid { grid-template-columns: 1fr; }
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
  }

  @media (max-width: 600px) {
    .stat-grid { grid-template-columns: 1fr; }
    .dash-main { padding: 20px; }
  }
</style>

<div class="dash-layout">

  <!-- ═══ SIDEBAR ═══ -->
  <aside class="dash-sidebar">
    <div class="user-card">
      <div class="avatar"><?= htmlspecialchars($initials) ?></div>
      <div>
        <p style="font-weight:700;font-size:0.85rem;margin:0;line-height:1.3;"><?= htmlspecialchars($userName) ?></p>
        <p style="font-size:0.7rem;color:var(--text-gray);margin:0;text-transform:uppercase;letter-spacing:.06em;">Student</p>
      </div>
    </div>

    <nav>
      <a href="#overview"     class="nav-item active"><i class="ri-home-4-line"></i> Overview</a>
      <a href="<?= BASE_URL ?>/scholarship.php" class="nav-item"><i class="ri-award-line"></i> Scholarships</a>
      <a href="<?= BASE_URL ?>/research.php"    class="nav-item"><i class="ri-flask-line"></i> Research Programs</a>
      <a href="<?= BASE_URL ?>/visa.php"       class="nav-item"><i class="ri-global-line"></i> Visa Guide</a>
      <a href="<?= BASE_URL ?>/testPrep.php"   class="nav-item"><i class="ri-pencil-ruler-2-line"></i> Test Prep</a>
    </nav>

    <hr class="nav-divider" />

    <nav>
      <a href="#profile"  class="nav-item"><i class="ri-user-line"></i> My Profile</a>
      <a href="#advisors" class="nav-item"><i class="ri-customer-service-2-line"></i> My Advisors</a>
    </nav>

    <hr class="nav-divider" />

    <nav>
      <a href="<?= BASE_URL ?>/index.php"      class="nav-item"><i class="ri-arrow-left-line"></i> Back to Site</a>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item" style="color:#DC2626;"><i class="ri-logout-box-line"></i> Sign Out</a>
    </nav>
  </aside>

  <!-- ═══ MAIN ═══ -->
  <main class="dash-main">

    <?= render_flash() ?>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div>
        <h2>Welcome back, <?= htmlspecialchars(explode(' ', $userName)[0]) ?> 👋</h2>
        <p>Track your applications, explore new scholarships, and connect with your advisors.</p>
      </div>
      <a href="<?= BASE_URL ?>/scholarship.php" class="btn-gold-sm">
        <i class="ri-search-line"></i> Browse Scholarships
      </a>
    </div>

    <!-- Stats -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon navy"><i class="ri-award-line"></i></div>
        <div>
          <div class="stat-value"><?= $trackedCount ?></div>
          <div class="stat-label">Tracked Scholarships</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon gold"><i class="ri-bookmark-line"></i></div>
        <div>
          <div class="stat-value"><?= $savedCount ?></div>
          <div class="stat-label">Saved Programs</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green"><i class="ri-calendar-check-line"></i></div>
        <div>
          <div class="stat-value"><?= count(array_filter($trackedGrants, fn($s) => $s === 'submitted')) ?></div>
          <div class="stat-label">Applications Submitted</div>
        </div>
      </div>
    </div>

    <!-- Main grid -->
    <div class="content-grid">

      <!-- LEFT column -->
      <div>

        <!-- Upcoming Deadlines -->
        <div class="section-card" id="overview">
          <div class="card-head">
            <h2><i class="ri-calendar-2-line" style="color:var(--accent-gold);margin-right:6px;"></i> Upcoming Deadlines</h2>
            <a href="<?= BASE_URL ?>/scholarship.php" class="btn-outline-sm">View All</a>
          </div>
          <div class="card-body" style="padding:0 24px;">
            <?php if (empty($deadlines)): ?>
              <div class="empty-state">
                <i class="ri-calendar-line"></i>
                <p style="font-weight:600;margin-bottom:4px;">No tracked scholarships yet</p>
                <p style="font-size:0.825rem;">Start tracking scholarships to see your deadlines here.</p>
                <a href="<?= BASE_URL ?>/scholarship.php" class="btn-gold-sm" style="margin-top:14px;display:inline-block;">Browse Scholarships</a>
              </div>
            <?php else: ?>
              <?php foreach ($deadlines as $d):
                $dt    = new DateTime($d['date']);
                $month = $dt->format('M');
                $day   = $dt->format('j');
                $badge = match($d['status']) {
                  'preparing'  => 'badge-preparing',
                  'submitted'  => 'badge-submitted',
                  'archived'   => 'badge-archived',
                  default      => 'badge-watching',
                };
              ?>
              <div class="deadline-row">
                <div class="deadline-date-box">
                  <div class="deadline-month"><?= $month ?></div>
                  <div class="deadline-day"><?= $day ?></div>
                </div>
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:700;font-size:0.9rem;margin-bottom:3px;"><?= htmlspecialchars($d['title']) ?></div>
                  <div style="font-size:0.8rem;color:var(--text-gray);margin-bottom:6px;"><?= htmlspecialchars($d['program']) ?> · <?= htmlspecialchars($d['amount']) ?></div>
                  <span class="status-badge <?= $badge ?>"><?= htmlspecialchars($d['status']) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Document Checklist -->
        <div class="section-card">
          <div class="card-head">
            <h2><i class="ri-file-check-line" style="color:var(--accent-gold);margin-right:6px;"></i> Document Checklist</h2>
            <span style="font-size:0.75rem;color:var(--text-gray);">For scholarship applications</span>
          </div>
          <div class="card-body" style="padding:0 24px;">
            <?php
            $docs = [
              ['icon' => 'ri-user-3-line',        'name' => 'Academic CV',          'status' => 'verified', 'note' => 'Updated 2 weeks ago'],
              ['icon' => 'ri-article-line',        'name' => 'Personal Statement',   'status' => 'ready',    'note' => 'Draft ready — needs review'],
              ['icon' => 'ri-file-list-3-line',    'name' => 'Official Transcript',  'status' => 'verified', 'note' => 'Certified copy uploaded'],
              ['icon' => 'ri-message-2-line',      'name' => 'Reference Letters',    'status' => 'missing',  'note' => '2 of 3 received'],
              ['icon' => 'ri-english-input',       'name' => 'Language Test Score',  'status' => 'missing',  'note' => 'IELTS / TOEFL required'],
            ];
            foreach ($docs as $doc):
              $color = match($doc['status']) {
                'verified' => 'var(--success)',
                'ready'    => 'var(--warning)',
                default    => 'var(--danger)',
              };
              $icon2 = match($doc['status']) {
                'verified' => 'ri-checkbox-circle-fill',
                'ready'    => 'ri-time-line',
                default    => 'ri-error-warning-line',
              };
            ?>
            <div class="doc-item">
              <div class="doc-icon"><i class="<?= $doc['icon'] ?>"></i></div>
              <div style="flex:1;">
                <div style="font-weight:600;font-size:0.875rem;"><?= htmlspecialchars($doc['name']) ?></div>
                <div style="font-size:0.775rem;color:var(--text-gray);margin-top:2px;"><?= htmlspecialchars($doc['note']) ?></div>
              </div>
              <i class="<?= $icon2 ?>" style="color:<?= $color ?>;font-size:1.1rem;flex-shrink:0;"></i>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Application Progress -->
        <div class="section-card" id="profile">
          <div class="card-head">
            <h2><i class="ri-bar-chart-line" style="color:var(--accent-gold);margin-right:6px;"></i> Application Readiness</h2>
          </div>
          <div class="card-body">
            <?php
            $steps = [
              ['label' => 'Profile Completed',       'pct' => 80],
              ['label' => 'Documents Uploaded',      'pct' => 60],
              ['label' => 'Scholarship Matched',     'pct' => 100],
              ['label' => 'Advisor Consultation',    'pct' => 0],
            ];
            foreach ($steps as $step): ?>
            <div style="margin-bottom:18px;">
              <div style="display:flex;justify-content:space-between;font-size:0.825rem;font-weight:600;margin-bottom:4px;">
                <span><?= htmlspecialchars($step['label']) ?></span>
                <span style="color:var(--text-gray);"><?= $step['pct'] ?>%</span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $step['pct'] ?>%;"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /left col -->

      <!-- RIGHT column -->
      <div>

        <!-- Quick Links -->
        <div class="section-card" style="margin-bottom:24px;">
          <div class="card-head"><h2>Quick Actions</h2></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:8px;padding:16px;">
            <a href="<?= BASE_URL ?>/scholarship.php"  class="btn-gold-sm" style="text-align:center;"><i class="ri-award-line"></i> Browse Scholarships</a>
            <a href="<?= BASE_URL ?>/research.php"     class="btn-outline-sm" style="text-align:center;"><i class="ri-flask-line"></i> Research Programs</a>
            <a href="<?= BASE_URL ?>/visa.php"        class="btn-outline-sm" style="text-align:center;"><i class="ri-global-line"></i> Visa Checklist</a>
            <a href="<?= BASE_URL ?>/testPrep.php"    class="btn-outline-sm" style="text-align:center;"><i class="ri-pencil-ruler-2-line"></i> Test Preparation</a>
          </div>
        </div>

        <!-- Available Advisors -->
        <div class="section-card" id="advisors">
          <div class="card-head">
            <h2><i class="ri-customer-service-2-line" style="color:var(--accent-gold);margin-right:6px;"></i> Available Advisors</h2>
          </div>
          <div class="card-body" style="padding:16px 20px;">
            <?php if (empty($advisors)): ?>
              <?php
              // Fallback placeholder advisors when DB has none yet
              $placeholders = [
                ['full_name' => 'Dr. Helena Moore',  'specialization' => 'University Admissions',    'experience_yrs' => 8,  'rating' => 4.9],
                ['full_name' => 'Samir Chowdhury',   'specialization' => 'Scholarship Guidance',     'experience_yrs' => 5,  'rating' => 4.7],
                ['full_name' => 'Dr. Priya Nair',    'specialization' => 'IELTS / TOEFL Coaching',   'experience_yrs' => 10, 'rating' => 4.8],
              ];
              foreach ($placeholders as $a): ?>
              <div class="advisor-card">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                  <div class="avatar gold" style="width:34px;height:34px;font-size:0.75rem;border-radius:50%;">
                    <?= strtoupper(substr($a['full_name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:700;font-size:0.875rem;"><?= htmlspecialchars($a['full_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-gray);"><?= htmlspecialchars($a['specialization']) ?></div>
                  </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                  <div class="star-rating">
                    <?php for ($i = 0; $i < 5; $i++) echo $i < round($a['rating']) ? '★' : '☆'; ?>
                    <span style="font-size:0.7rem;color:var(--text-gray);margin-left:3px;"><?= $a['rating'] ?></span>
                  </div>
                  <span style="font-size:0.7rem;color:var(--text-gray);"><?= $a['experience_yrs'] ?> yrs exp</span>
                </div>
                <button class="btn-gold-sm" style="width:100%;margin-top:10px;text-align:center;">Book Consultation</button>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <?php foreach ($advisors as $a): ?>
              <div class="advisor-card">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                  <div class="avatar gold" style="width:34px;height:34px;font-size:0.75rem;border-radius:50%;">
                    <?= strtoupper(substr($a['full_name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div style="font-weight:700;font-size:0.875rem;"><?= htmlspecialchars($a['full_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-gray);"><?= htmlspecialchars($a['specialization']) ?></div>
                  </div>
                </div>
                <p style="font-size:0.8rem;color:var(--text-gray);margin:0 0 8px;"><?= htmlspecialchars(substr($a['bio'] ?? '', 0, 80)) ?>…</p>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <div class="star-rating">
                    <?php for ($i = 0; $i < 5; $i++) echo $i < round($a['rating']) ? '★' : '☆'; ?>
                    <span style="font-size:0.7rem;color:var(--text-gray);margin-left:3px;"><?= number_format($a['rating'], 1) ?></span>
                  </div>
                  <span style="font-size:0.7rem;color:var(--text-gray);"><?= (int)$a['experience_yrs'] ?> yrs exp</span>
                </div>
                <?php if ($a['linkedin_url']): ?>
                  <a href="<?= htmlspecialchars($a['linkedin_url']) ?>" target="_blank" rel="noopener"
                     style="font-size:0.75rem;color:#A16207;display:block;margin-top:6px;"><i class="ri-linkedin-box-line"></i> LinkedIn</a>
                <?php endif; ?>
                <button class="btn-gold-sm" style="width:100%;margin-top:10px;text-align:center;">Book Consultation</button>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tip of the day -->
        <div style="background:linear-gradient(135deg,#031632,#1A2B48);border-radius:10px;padding:22px;color:white;">
          <p style="font-size:0.65rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#FFDEA5;margin:0 0 8px;">Scholar Tip</p>
          <p style="font-family:'Newsreader',Georgia,serif;font-size:1rem;line-height:1.5;margin:0 0 12px;">
            "Start your personal statement 3 months before the deadline. Allow 2 full weeks per revision cycle."
          </p>
          <p style="font-size:0.75rem;color:#8293B5;margin:0;">— The Editorial Scholar Advisory Team</p>
        </div>

      </div><!-- /right col -->

    </div><!-- /content-grid -->

  </main>
</div>

</body>
</html>