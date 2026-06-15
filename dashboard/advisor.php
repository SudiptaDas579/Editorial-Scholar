<?php
// dashboard/advisor.php — Advisor Dashboard
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

require_role('advisor');

$pdo      = getDB();
$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'Advisor';
$initials = strtoupper(substr($userName, 0, 1));

// ── Advisor profile ──────────────────────────────────────────────────────────
try {
    $profileStmt = $pdo->prepare(
        "SELECT ap.*, u.email
         FROM advisor_profiles ap
         JOIN users u ON u.id = ap.user_id
         WHERE ap.user_id = ?"
    );
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch();
} catch (\Throwable $e) {
    $profile = null;
}

// ── Stats ────────────────────────────────────────────────────────────────────
$rating         = $profile ? (float)$profile['rating']       : 0.0;
$totalReviews   = $profile ? (int)$profile['total_reviews']  : 0;
$specialization = $profile['specialization'] ?? 'General Advising';
$available      = $profile ? (bool)$profile['available']     : true;
$experienceYrs  = $profile ? (int)$profile['experience_yrs'] : 0;

// ── Toggle availability (POST) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        try {
            $newVal = $available ? 0 : 1;
            $pdo->prepare("UPDATE advisor_profiles SET available = ? WHERE user_id = ?")
                ->execute([$newVal, $userId]);
            $available = (bool)$newVal;
            flash('success', $available ? 'You are now marked as available.' : 'You are now marked as unavailable.');
        } catch (\Throwable $e) {
            flash('error', 'Could not update availability. Please try again.');
        }
    }
    redirect(BASE_URL . '/dashboard/advisor.php');
}

// ── Recent platform activity (students who viewed advisor profiles) ──────────
// Using a stub since bookings table not yet in schema
$recentActivity = [
    ['student' => 'Maya Rahman',    'action' => 'Requested consultation',    'time' => '2 hours ago',   'status' => 'pending'],
    ['student' => 'Arjun Kapoor',   'action' => 'Viewed your profile',       'time' => '5 hours ago',   'status' => 'view'],
    ['student' => 'Fatima Al-Haj',  'action' => 'Session completed',         'time' => 'Yesterday',     'status' => 'done'],
    ['student' => 'Chen Wei',       'action' => 'Left a 5-star review',      'time' => '2 days ago',    'status' => 'review'],
];

// ── Upcoming sessions (stub) ──────────────────────────────────────────────────
$upcomingSessions = [
    ['student' => 'Maya Rahman',    'topic' => 'Rhodes Scholarship SOP Review',   'date' => 'Today, 3:00 PM',     'duration' => '45 min'],
    ['student' => 'Aiko Tanaka',    'topic' => 'Erasmus Mundus Application',      'date' => 'Tomorrow, 10:00 AM', 'duration' => '60 min'],
    ['student' => 'Omar Siddiqui',  'topic' => 'Fulbright Interview Prep',        'date' => 'June 18, 2:30 PM',   'duration' => '30 min'],
];

// ── Specialization stats for the chart area ───────────────────────────────────
$specializationStats = [
    ['label' => 'Consultations done',  'value' => 42,    'icon' => 'ri-calendar-check-line', 'color' => 'navy'],
    ['label' => 'Avg rating',          'value' => number_format($rating ?: 4.8, 1), 'icon' => 'ri-star-fill',           'color' => 'gold'],
    ['label' => 'Students helped',     'value' => 38,    'icon' => 'ri-team-line',            'color' => 'green'],
    ['label' => 'Reviews received',    'value' => $totalReviews ?: 27, 'icon' => 'ri-chat-check-line', 'color' => 'blue'],
];

$pageTitle = 'Advisor Dashboard';
$activeNav = '';
$cssPath   = BASE_URL . '/src/output.css';
?>
<?php include __DIR__ . '/../includes/head.php'; ?>

<style>
  :root {
    --primary-dark: #0A192F;
    --accent-gold: #8B6E30;
    --bg-light: #F4F7F9;
    --white: #FFFFFF;
    --text-gray: #64748B;
    --border-color: #E2E8F0;
    --success: #059669;
    --danger: #DC2626;
    --warning: #A16207;
  }

  * { box-sizing: border-box; }
  body { font-family: 'Manrope', sans-serif; background: var(--bg-light); color: var(--primary-dark); }

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
    width: 42px; height: 42px; border-radius: 8px;
    background: #775A19; color: white;
    display: grid; place-items: center;
    font-weight: 800; font-size: 0.85rem; flex-shrink: 0;
  }

  .avail-dot {
    width: 8px; height: 8px; border-radius: 50%;
    display: inline-block; margin-right: 5px;
  }

  .avail-dot.on  { background: var(--success); }
  .avail-dot.off { background: #9CA3AF; }

  .nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 6px;
    color: var(--text-gray); font-size: 0.875rem; font-weight: 500;
    text-decoration: none; transition: all 0.15s; margin-bottom: 2px;
  }
  .nav-item:hover, .nav-item.active { background: #F1F5F9; color: #775A19; }
  .nav-item i { font-size: 1rem; width: 18px; }
  .nav-divider { margin: 16px 0; border: none; border-top: 1px solid var(--border-color); }

  /* ── Main ── */
  .dash-main { padding: 36px 40px; min-width: 0; }

  .page-title {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.9rem; font-weight: 700; margin: 0 0 4px;
  }

  /* ── Profile banner ── */
  .profile-banner {
    background: linear-gradient(135deg, #031632 0%, #1A2B48 100%);
    border-radius: 12px; padding: 28px 32px; color: white;
    margin-bottom: 28px;
    display: flex; justify-content: space-between; align-items: center;
    gap: 20px; flex-wrap: wrap;
  }

  .profile-banner h2 {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.5rem; font-weight: 700; margin: 0 0 4px;
  }

  /* ── Stats grid ── */
  .stat-grid {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 16px; margin-bottom: 28px;
  }

  .stat-card {
    background: var(--white); border: 1px solid var(--border-color);
    border-radius: 10px; padding: 20px;
  }

  .stat-icon {
    width: 40px; height: 40px; border-radius: 8px;
    display: grid; place-items: center; font-size: 1.1rem; margin-bottom: 12px;
  }

  .stat-icon.navy  { background: #EFF4FF; color: #031632; }
  .stat-icon.gold  { background: #FEF3C7; color: #A16207; }
  .stat-icon.green { background: #D1FAE5; color: #059669; }
  .stat-icon.blue  { background: #EFF6FF; color: #1D4ED8; }

  .stat-value { font-size: 1.6rem; font-weight: 800; line-height: 1; }
  .stat-label { font-size: 0.75rem; color: var(--text-gray); margin-top: 3px; }

  /* ── Content grid ── */
  .content-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px; align-items: start;
  }

  /* ── Section card ── */
  .section-card {
    background: var(--white); border: 1px solid var(--border-color);
    border-radius: 10px; overflow: hidden; margin-bottom: 22px;
  }

  .card-head {
    padding: 16px 22px; border-bottom: 1px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: center;
  }

  .card-head h2 {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.05rem; font-weight: 700; margin: 0;
  }

  /* ── Session row ── */
  .session-row {
    display: flex; gap: 14px; padding: 14px 22px;
    border-bottom: 1px solid var(--border-color); align-items: center;
  }

  .session-row:last-child { border-bottom: none; }

  .session-time {
    background: #EFF4FF; border-radius: 6px;
    padding: 8px 10px; text-align: center;
    min-width: 80px; flex-shrink: 0;
    font-size: 0.72rem; font-weight: 800; color: #031632;
    line-height: 1.4;
  }

  .btn-sm {
    font-size: 0.75rem; font-weight: 700;
    padding: 6px 14px; border-radius: 5px; cursor: pointer;
    border: 1px solid var(--border-color); background: white;
    color: var(--primary-dark); transition: all 0.15s; text-decoration: none;
    display: inline-block;
  }
  .btn-sm:hover { border-color: #775A19; color: #775A19; }

  .btn-gold-sm {
    background: #775A19; color: white; border: none;
    padding: 8px 18px; border-radius: 6px;
    font-family: 'Manrope', sans-serif; font-weight: 700;
    font-size: 0.8rem; cursor: pointer; white-space: nowrap;
    transition: background 0.2s; text-decoration: none; display: inline-block;
  }
  .btn-gold-sm:hover { background: #A16207; }

  /* ── Activity feed ── */
  .activity-row {
    display: flex; gap: 12px; padding: 13px 22px;
    border-bottom: 1px solid var(--border-color); align-items: center;
  }
  .activity-row:last-child { border-bottom: none; }

  .activity-icon {
    width: 32px; height: 32px; border-radius: 50%;
    display: grid; place-items: center; font-size: 0.875rem; flex-shrink: 0;
  }

  .activity-icon.pending { background: #FEF3C7; color: #A16207; }
  .activity-icon.done    { background: #D1FAE5; color: #059669; }
  .activity-icon.view    { background: #EFF6FF; color: #1D4ED8; }
  .activity-icon.review  { background: #FEF3C7; color: #A16207; }

  /* ── Star rating ── */
  .star-row { display: flex; gap: 3px; }
  .star { font-size: 1.1rem; }
  .star.filled { color: #A16207; }
  .star.empty  { color: #E2E8F0; }

  /* ── Profile edit widget ── */
  .profile-stat {
    display: flex; flex-direction: column; gap: 4px;
    background: rgba(255,255,255,0.1); border-radius: 8px;
    padding: 12px 18px; text-align: center;
  }
  .profile-stat span { font-size: 0.7rem; color: #8293B5; text-transform: uppercase; letter-spacing: .06em; }
  .profile-stat strong { font-size: 1.2rem; font-weight: 800; color: white; }

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
        <p style="font-size:0.7rem;color:var(--text-gray);margin:2px 0 0;text-transform:uppercase;letter-spacing:.06em;">Advisor</p>
        <p style="font-size:0.7rem;margin:2px 0 0;">
          <span class="avail-dot <?= $available ? 'on' : 'off' ?>"></span>
          <span style="color:<?= $available ? 'var(--success)' : '#9CA3AF' ?>;font-weight:600;"><?= $available ? 'Available' : 'Unavailable' ?></span>
        </p>
      </div>
    </div>

    <nav>
      <a href="#overview"   class="nav-item active"><i class="ri-home-4-line"></i> Overview</a>
      <a href="#sessions"   class="nav-item"><i class="ri-calendar-line"></i> My Sessions</a>
      <a href="#students"   class="nav-item"><i class="ri-team-line"></i> My Students</a>
      <a href="#profile"    class="nav-item"><i class="ri-user-line"></i> My Profile</a>
    </nav>

    <hr class="nav-divider">

    <!-- Toggle availability -->
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="toggle_availability" value="1">
      <button type="submit" class="nav-item" style="width:100%;background:none;border:none;cursor:pointer;text-align:left;
        color:<?= $available ? 'var(--danger)' : 'var(--success)' ?>;">
        <i class="<?= $available ? 'ri-toggle-fill' : 'ri-toggle-line' ?>"></i>
        <?= $available ? 'Go Unavailable' : 'Go Available' ?>
      </button>
    </form>

    <hr class="nav-divider">

    <nav>
      <a href="<?= BASE_URL ?>/index.html"      class="nav-item"><i class="ri-arrow-left-line"></i> Back to Site</a>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item" style="color:#DC2626;"><i class="ri-logout-box-line"></i> Sign Out</a>
    </nav>
  </aside>

  <!-- ═══ MAIN ═══ -->
  <main class="dash-main">

    <?= render_flash() ?>

    <!-- Profile Banner -->
    <div class="profile-banner" id="overview">
      <div>
        <h2><?= htmlspecialchars($userName) ?></h2>
        <p style="color:#8293B5;font-size:0.875rem;margin:0 0 8px;"><?= htmlspecialchars($specialization) ?> · <?= $experienceYrs ?> yrs experience</p>
        <div class="star-row">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="star <?= $i <= round($rating ?: 4.8) ? 'filled' : 'empty' ?>">★</span>
          <?php endfor; ?>
          <span style="font-size:0.8rem;color:#8293B5;margin-left:6px;"><?= number_format($rating ?: 4.8, 1) ?> (<?= $totalReviews ?: 27 ?> reviews)</span>
        </div>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="profile-stat">
          <span>Consultations</span>
          <strong>42</strong>
        </div>
        <div class="profile-stat">
          <span>Students</span>
          <strong>38</strong>
        </div>
        <div class="profile-stat">
          <span>Rating</span>
          <strong><?= number_format($rating ?: 4.8, 1) ?></strong>
        </div>
      </div>
    </div>

    <!-- Stats row -->
    <div class="stat-grid">
      <?php foreach ($specializationStats as $stat): ?>
      <div class="stat-card">
        <div class="stat-icon <?= $stat['color'] ?>"><i class="<?= $stat['icon'] ?>"></i></div>
        <div class="stat-value"><?= $stat['value'] ?></div>
        <div class="stat-label"><?= htmlspecialchars($stat['label']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Content grid -->
    <div class="content-grid">

      <!-- LEFT column -->
      <div>

        <!-- Upcoming Sessions -->
        <div class="section-card" id="sessions">
          <div class="card-head">
            <h2><i class="ri-calendar-event-line" style="color:#775A19;margin-right:6px;"></i> Upcoming Sessions</h2>
            <a href="#" class="btn-sm">+ Add Session</a>
          </div>
          <?php if (empty($upcomingSessions)): ?>
            <div style="padding:36px;text-align:center;color:var(--text-gray);">
              <i class="ri-calendar-line" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:8px;"></i>
              <p>No upcoming sessions scheduled.</p>
            </div>
          <?php else: ?>
            <?php foreach ($upcomingSessions as $s): ?>
            <div class="session-row">
              <div class="session-time"><?= htmlspecialchars($s['date']) ?><br><?= htmlspecialchars($s['duration']) ?></div>
              <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:0.875rem;margin-bottom:3px;"><?= htmlspecialchars($s['student']) ?></div>
                <div style="font-size:0.8rem;color:var(--text-gray);"><?= htmlspecialchars($s['topic']) ?></div>
              </div>
              <div style="display:flex;gap:8px;flex-shrink:0;">
                <a href="#" class="btn-sm">View</a>
                <a href="#" class="btn-gold-sm">Join</a>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="section-card">
          <div class="card-head">
            <h2><i class="ri-notification-3-line" style="color:#775A19;margin-right:6px;"></i> Recent Activity</h2>
          </div>
          <?php foreach ($recentActivity as $a):
            $iconClass = match($a['status']) {
              'pending' => 'ri-time-line',
              'done'    => 'ri-check-line',
              'review'  => 'ri-star-line',
              default   => 'ri-eye-line',
            };
          ?>
          <div class="activity-row">
            <div class="activity-icon <?= $a['status'] ?>"><i class="<?= $iconClass ?>"></i></div>
            <div style="flex:1;">
              <span style="font-weight:700;font-size:0.875rem;"><?= htmlspecialchars($a['student']) ?></span>
              <span style="font-size:0.82rem;color:var(--text-gray);"> — <?= htmlspecialchars($a['action']) ?></span>
              <div style="font-size:0.75rem;color:var(--text-gray);margin-top:2px;"><?= htmlspecialchars($a['time']) ?></div>
            </div>
            <?php if ($a['status'] === 'pending'): ?>
              <a href="#" class="btn-gold-sm" style="padding:6px 12px;font-size:0.72rem;">Respond</a>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

      </div><!-- /left col -->

      <!-- RIGHT column -->
      <div>

        <!-- Profile completeness -->
        <div class="section-card" id="profile" style="margin-bottom:22px;">
          <div class="card-head"><h2>Profile Completeness</h2></div>
          <div style="padding:20px 22px;">
            <?php
            $profileFields = [
              ['label' => 'Bio & Introduction',    'done' => !empty($profile['bio'])],
              ['label' => 'Specialization set',    'done' => !empty($profile['specialization'])],
              ['label' => 'LinkedIn connected',    'done' => !empty($profile['linkedin_url'])],
              ['label' => 'Experience listed',     'done' => ($profile['experience_yrs'] ?? 0) > 0],
              ['label' => 'First review received', 'done' => ($profile['total_reviews'] ?? 0) > 0],
            ];
            $done = count(array_filter($profileFields, fn($f) => $f['done']));
            $pct  = (int)(($done / count($profileFields)) * 100);
            ?>
            <div style="display:flex;justify-content:space-between;font-size:0.825rem;font-weight:600;margin-bottom:6px;">
              <span>Profile strength</span><span style="color:var(--text-gray);"><?= $pct ?>%</span>
            </div>
            <div style="background:var(--border-color);border-radius:999px;height:6px;overflow:hidden;margin-bottom:16px;">
              <div style="width:<?= $pct ?>%;height:100%;border-radius:999px;background:linear-gradient(90deg,#775A19,#A16207);"></div>
            </div>
            <?php foreach ($profileFields as $f): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:0.825rem;">
              <i class="<?= $f['done'] ? 'ri-checkbox-circle-fill' : 'ri-checkbox-blank-circle-line' ?>"
                 style="color:<?= $f['done'] ? 'var(--success)' : '#D1D5DB' ?>;font-size:1rem;flex-shrink:0;"></i>
              <span style="color:<?= $f['done'] ? 'var(--primary-dark)' : 'var(--text-gray)' ?>"><?= htmlspecialchars($f['label']) ?></span>
            </div>
            <?php endforeach; ?>
            <a href="#" class="btn-gold-sm" style="width:100%;text-align:center;margin-top:8px;display:block;">Edit Profile</a>
          </div>
        </div>

        <!-- Quick Stats mini chart -->
        <div class="section-card" style="margin-bottom:22px;">
          <div class="card-head"><h2>This Month</h2></div>
          <div style="padding:18px 22px;">
            <?php
            $monthStats = [
              ['label' => 'New student inquiries', 'val' => 8,  'max' => 20],
              ['label' => 'Sessions completed',    'val' => 11, 'max' => 20],
              ['label' => 'Response rate',         'val' => 95, 'max' => 100, 'suffix' => '%'],
            ];
            foreach ($monthStats as $ms):
              $pctBar = (int)(($ms['val'] / $ms['max']) * 100);
            ?>
            <div style="margin-bottom:16px;">
              <div style="display:flex;justify-content:space-between;font-size:0.8rem;font-weight:600;margin-bottom:4px;">
                <span><?= htmlspecialchars($ms['label']) ?></span>
                <span style="color:var(--text-gray);"><?= $ms['val'] ?><?= $ms['suffix'] ?? '' ?></span>
              </div>
              <div style="background:var(--border-color);border-radius:999px;height:5px;overflow:hidden;">
                <div style="width:<?= $pctBar ?>%;height:100%;border-radius:999px;background:linear-gradient(90deg,#775A19,#A16207);"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Resources -->
        <div style="background:linear-gradient(135deg,#031632,#1A2B48);border-radius:10px;padding:22px;">
          <p style="font-size:0.65rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#FFDEA5;margin:0 0 10px;">Advisor Resources</p>
          <?php
          $resources = [
            ['icon' => 'ri-book-open-line',       'label' => 'Advisor Handbook'],
            ['icon' => 'ri-video-line',            'label' => 'Session Best Practices'],
            ['icon' => 'ri-customer-service-line', 'label' => 'Student Support Guide'],
          ];
          foreach ($resources as $r): ?>
          <a href="#" style="display:flex;align-items:center;gap:8px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.1);text-decoration:none;color:white;font-size:0.825rem;">
            <i class="<?= $r['icon'] ?>" style="color:#FFDEA5;font-size:1rem;flex-shrink:0;"></i>
            <?= htmlspecialchars($r['label']) ?>
            <i class="ri-arrow-right-line" style="margin-left:auto;opacity:.5;"></i>
          </a>
          <?php endforeach; ?>
        </div>

      </div><!-- /right col -->
    </div><!-- /content-grid -->

  </main>
</div>

</body>
</html>