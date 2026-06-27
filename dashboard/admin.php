<?php
// dashboard/admin.php — Admin Dashboard (full rebuild)
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

require_role('admin');

$pdo      = getDB();
$userName = $_SESSION['full_name'] ?? 'Admin';
$initials = strtoupper(substr($userName, 0, 1));

// ── Aggregate stats ──────────────────────────────────────────────────────────
$totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalAdvisors  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'advisor'")->fetchColumn();
$pendingCount   = (int)$pdo->query("SELECT COUNT(*) FROM advisor_applications WHERE status = 'pending'")->fetchColumn();
$suspendedCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();
$totalAll       = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// New users this week
$newThisWeek = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetchColumn();

// ── Advisor applications ─────────────────────────────────────────────────────
$appsStmt = $pdo->query(
    "SELECT aa.*, u.full_name, u.email
     FROM advisor_applications aa
     JOIN users u ON u.id = aa.user_id
     ORDER BY aa.submitted_at DESC"
);
$applications = $appsStmt->fetchAll();
$pendingApps  = array_filter($applications, fn($a) => $a['status'] === 'pending');
$reviewedApps = array_filter($applications, fn($a) => $a['status'] !== 'pending');

// ── Users list ───────────────────────────────────────────────────────────────
$usersStmt = $pdo->query(
    "SELECT id, full_name, email, role, status, last_login, created_at
     FROM users ORDER BY created_at DESC LIMIT 100"
);
$users = $usersStmt->fetchAll();

// ── Role breakdown for mini chart ────────────────────────────────────────────
$roleBreakdown = [
    ['label' => 'Students',      'count' => $totalUsers,    'color' => '#031632', 'pct' => $totalAll > 0 ? round($totalUsers / $totalAll * 100) : 0],
    ['label' => 'Advisors',      'count' => $totalAdvisors, 'color' => '#775A19', 'pct' => $totalAll > 0 ? round($totalAdvisors / $totalAll * 100) : 0],
    ['label' => 'Admins',        'count' => $totalAll - $totalUsers - $totalAdvisors, 'color' => '#1D4ED8', 'pct' => $totalAll > 0 ? round(($totalAll - $totalUsers - $totalAdvisors) / $totalAll * 100) : 0],
];

// ── Current admin section from query param ───────────────────────────────────
$section = $_GET['section'] ?? 'overview';

$pageTitle = 'Admin Dashboard';
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
    position: sticky; top: 60px;
    height: calc(100vh - 60px);
    overflow-y: auto;
  }

  .user-card {
    background: #F4F7F9; border-radius: 10px; padding: 14px;
    display: flex; align-items: center; gap: 10px; margin-bottom: 28px;
  }

  .avatar {
    width: 42px; height: 42px; border-radius: 8px;
    background: var(--primary-dark); color: white;
    display: grid; place-items: center; font-weight: 800; font-size: 0.85rem; flex-shrink: 0;
  }

  .badge-count {
    margin-left: auto; background: var(--warning); color: white;
    border-radius: 999px; font-size: 0.68rem; padding: 2px 7px; font-weight: 800;
  }

  .nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 6px;
    color: var(--text-gray); font-size: 0.875rem; font-weight: 500;
    text-decoration: none; transition: all 0.15s; margin-bottom: 2px;
    cursor: pointer; border: none; background: none; width: 100%; text-align: left;
  }
  .nav-item:hover, .nav-item.active { background: #F1F5F9; color: #031632; }
  .nav-item i { font-size: 1rem; width: 18px; flex-shrink: 0; }
  .nav-divider { margin: 16px 0; border: none; border-top: 1px solid var(--border-color); }

  /* ── Main ── */
  .dash-main { padding: 36px 40px; min-width: 0; }

  .page-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 32px; flex-wrap: wrap; gap: 16px;
  }

  .page-title {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.9rem; font-weight: 700; margin: 0 0 4px;
  }

  /* ── Stat grid ── */
  .stat-grid {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 16px; margin-bottom: 32px;
  }

  .stat-card {
    background: var(--white); border: 1px solid var(--border-color);
    border-radius: 10px; padding: 20px 22px;
    display: flex; flex-direction: column; gap: 12px;
  }

  .stat-top {
    display: flex; justify-content: space-between; align-items: flex-start;
  }

  .stat-icon {
    width: 40px; height: 40px; border-radius: 8px;
    display: grid; place-items: center; font-size: 1.1rem;
  }

  .stat-icon.navy  { background: #EFF4FF; color: #031632; }
  .stat-icon.gold  { background: #FEF3C7; color: #A16207; }
  .stat-icon.green { background: #D1FAE5; color: #059669; }
  .stat-icon.red   { background: #FEF2F2; color: #DC2626; }

  .stat-value { font-size: 1.8rem; font-weight: 800; line-height: 1; }
  .stat-label { font-size: 0.75rem; color: var(--text-gray); }
  .stat-trend { font-size: 0.72rem; font-weight: 700; }
  .stat-trend.up   { color: var(--success); }
  .stat-trend.warn { color: var(--warning); }
  .stat-trend.bad  { color: var(--danger); }

  /* ── Content grid ── */
  .content-grid {
    display: grid; grid-template-columns: 1fr 300px;
    gap: 24px; align-items: start; margin-bottom: 32px;
  }

  /* ── Section card ── */
  .section-card {
    background: var(--white); border: 1px solid var(--border-color);
    border-radius: 10px; overflow: hidden; margin-bottom: 24px;
  }

  .card-head {
    padding: 16px 22px; border-bottom: 1px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: center;
  }

  .card-head h2 {
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.08rem; font-weight: 700; margin: 0;
  }

  /* ── Application card ── */
  .app-card {
    padding: 20px 22px;
    border-bottom: 1px solid var(--border-color);
  }

  .app-card:last-child { border-bottom: none; }

  .app-card-header {
    display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;
  }

  .badge {
    font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
    padding: 3px 10px; border-radius: 999px; letter-spacing: .04em; white-space: nowrap;
  }
  .badge-pending  { background: #FEF3C7; color: var(--warning); }
  .badge-approved { background: #D1FAE5; color: var(--success); }
  .badge-rejected { background: #FEE2E2; color: var(--danger); }
  .badge-active   { background: #D1FAE5; color: var(--success); }
  .badge-suspended{ background: #FEE2E2; color: var(--danger); }
  .badge-inactive { background: #F3F4F6; color: #6B7280; }
  .badge-user     { background: #EFF6FF; color: #1D4ED8; }
  .badge-advisor  { background: #FEF3C7; color: var(--warning); }
  .badge-admin    { background: #EFF4FF; color: #031632; }

  .info-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 8px; margin: 12px 0; font-size: 0.825rem;
  }

  .info-grid strong { color: var(--primary-dark); }

  .action-row {
    display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px;
  }

  /* ── Buttons ── */
  .btn-approve {
    background: var(--success); color: white; border: none;
    padding: 8px 16px; border-radius: 5px; font-size: 0.8rem; font-weight: 700;
    cursor: pointer; transition: opacity 0.15s;
  }
  .btn-approve:hover { opacity: .85; }

  .btn-reject {
    background: white; color: var(--danger); border: 1px solid var(--danger);
    padding: 8px 16px; border-radius: 5px; font-size: 0.8rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s;
  }
  .btn-reject:hover { background: #FEF2F2; }

  .btn-suspend {
    background: white; color: var(--danger); border: 1px solid var(--border-color);
    padding: 5px 12px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s;
  }
  .btn-suspend:hover { border-color: var(--danger); }

  .btn-activate {
    background: white; color: var(--success); border: 1px solid var(--border-color);
    padding: 5px 12px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s;
  }
  .btn-activate:hover { border-color: var(--success); }

  /* ── User table ── */
  .user-table { width: 100%; border-collapse: collapse; font-size: 0.825rem; }
  .user-table thead th {
    padding: 12px 16px; background: #F8FAFC;
    font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .06em; color: var(--text-gray); text-align: left;
    border-bottom: 1px solid var(--border-color);
  }
  .user-table tbody tr { border-top: 1px solid var(--border-color); }
  .user-table tbody tr:hover { background: #FAFBFC; }
  .user-table tbody td { padding: 12px 16px; vertical-align: middle; }

  /* ── Role bar chart ── */
  .role-bar {
    display: flex; height: 8px; border-radius: 999px; overflow: hidden;
    margin-bottom: 12px;
  }

  /* ── Section toggle ── */
  .section-tabs {
    display: flex; gap: 4px; border-bottom: 1px solid var(--border-color);
    margin-bottom: 28px; overflow-x: auto;
  }

  .section-tab {
    padding: 10px 18px; font-size: 0.875rem; font-weight: 600;
    color: var(--text-gray); border-bottom: 2px solid transparent;
    cursor: pointer; white-space: nowrap; text-decoration: none;
    transition: all 0.15s;
  }

  .section-tab.active, .section-tab:hover {
    color: var(--primary-dark); border-bottom-color: #031632;
  }

  /* ── Rejection textarea ── */
  .reject-note {
    width: 100%; border: 1px solid var(--border-color);
    border-radius: 4px; padding: 8px 10px; font-family: 'Manrope', sans-serif;
    font-size: 0.78rem; resize: none; margin-top: 6px;
  }

  .reject-note:focus { border-color: #A16207; outline: none; }

  @media (max-width: 1100px) {
    .dash-layout { grid-template-columns: 1fr; }
    .dash-sidebar { position: static; height: auto; border-right: none; border-bottom: 1px solid var(--border-color); }
    .content-grid { grid-template-columns: 1fr; }
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
  }

  @media (max-width: 700px) {
    .stat-grid { grid-template-columns: 1fr; }
    .dash-main { padding: 20px; }
    .info-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="dash-layout">

  <!-- ═══ SIDEBAR ═══ -->
  <aside class="dash-sidebar">
    <div class="user-card">
      <div class="avatar"><?= htmlspecialchars($initials) ?></div>
      <div>
        <p style="font-weight:700;font-size:0.85rem;margin:0;line-height:1.3;"><?= htmlspecialchars($userName) ?></p>
        <p style="font-size:0.7rem;color:var(--text-gray);margin:0;text-transform:uppercase;letter-spacing:.06em;">Administrator</p>
      </div>
    </div>

    <nav>
      <a href="?section=overview"      class="nav-item <?= $section === 'overview' ? 'active' : '' ?>">
        <i class="ri-dashboard-line"></i> Overview
      </a>
      <a href="?section=applications"  class="nav-item <?= $section === 'applications' ? 'active' : '' ?>">
        <i class="ri-user-star-line"></i> Advisor Applications
        <?php if ($pendingCount > 0): ?>
          <span class="badge-count"><?= $pendingCount ?></span>
        <?php endif; ?>
      </a>
      <a href="?section=users"         class="nav-item <?= $section === 'users' ? 'active' : '' ?>">
        <i class="ri-team-line"></i> User Management
      </a>
    </nav>

    <hr class="nav-divider">

    <nav>
      <a href="<?= BASE_URL ?>/index.php"      class="nav-item"><i class="ri-home-4-line"></i> Back to Site</a>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item" style="color:#DC2626;"><i class="ri-logout-box-line"></i> Sign Out</a>
    </nav>
  </aside>

  <!-- ═══ MAIN ═══ -->
  <main class="dash-main">

    <?= render_flash() ?>

    <div class="page-header">
      <div>
        <h1 class="page-title">Admin Dashboard</h1>
        <p style="color:var(--text-gray);font-size:0.875rem;margin:0;">
          <?php if ($section === 'overview'): ?>Platform overview and analytics<?php endif; ?>
          <?php if ($section === 'applications'): ?>Review and manage advisor applications<?php endif; ?>
          <?php if ($section === 'users'): ?>View and manage all platform users<?php endif; ?>
        </p>
      </div>
    </div>

    <!-- ═════════ OVERVIEW ═════════ -->
    <?php if ($section === 'overview'): ?>

    <!-- Stats -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-top">
          <div>
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Total Students</div>
          </div>
          <div class="stat-icon navy"><i class="ri-graduation-cap-line"></i></div>
        </div>
        <div class="stat-trend up"><i class="ri-arrow-up-line"></i> +<?= $newThisWeek ?> this week</div>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <div>
            <div class="stat-value"><?= $totalAdvisors ?></div>
            <div class="stat-label">Active Advisors</div>
          </div>
          <div class="stat-icon gold"><i class="ri-user-star-line"></i></div>
        </div>
        <div class="stat-trend up"><i class="ri-arrow-up-line"></i> Verified network</div>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <div>
            <div class="stat-value" style="color:var(--warning);"><?= $pendingCount ?></div>
            <div class="stat-label">Pending Applications</div>
          </div>
          <div class="stat-icon gold"><i class="ri-time-line"></i></div>
        </div>
        <div class="stat-trend warn">
          <?= $pendingCount > 0 ? '<i class="ri-error-warning-line"></i> Needs review' : '<i class="ri-checkbox-circle-line"></i> All clear' ?>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-top">
          <div>
            <div class="stat-value" style="color:var(--danger);"><?= $suspendedCount ?></div>
            <div class="stat-label">Suspended Accounts</div>
          </div>
          <div class="stat-icon red"><i class="ri-forbid-line"></i></div>
        </div>
        <div class="stat-trend <?= $suspendedCount > 0 ? 'bad' : 'up' ?>">
          <?= $suspendedCount > 0 ? '<i class="ri-error-warning-line"></i> Active restrictions' : '<i class="ri-shield-check-line"></i> All clear' ?>
        </div>
      </div>
    </div>

    <!-- Content grid -->
    <div class="content-grid">
      <div>

        <!-- Recent users -->
        <div class="section-card">
          <div class="card-head">
            <h2><i class="ri-team-line" style="color:#775A19;margin-right:6px;"></i> Recent Registrations</h2>
            <a href="?section=users" style="font-size:0.78rem;color:#A16207;text-decoration:none;font-weight:700;">View all →</a>
          </div>
          <table class="user-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($users, 0, 8) as $u): ?>
              <tr>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($u['full_name']) ?></div>
                  <div style="font-size:0.75rem;color:var(--text-gray);"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                <td><span class="badge badge-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
                <td style="color:var(--text-gray);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>

      <!-- Right: role breakdown -->
      <div>
        <div class="section-card" style="margin-bottom:22px;">
          <div class="card-head"><h2>User Breakdown</h2></div>
          <div style="padding:20px 22px;">

            <!-- Mini bar -->
            <div class="role-bar" style="margin-bottom:16px;">
              <?php foreach ($roleBreakdown as $rb): ?>
                <?php if ($rb['pct'] > 0): ?>
                <div style="width:<?= $rb['pct'] ?>%;background:<?= $rb['color'] ?>;"></div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>

            <?php foreach ($roleBreakdown as $rb): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;font-size:0.825rem;">
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="width:10px;height:10px;border-radius:2px;background:<?= $rb['color'] ?>;flex-shrink:0;"></div>
                <span><?= htmlspecialchars($rb['label']) ?></span>
              </div>
              <span style="font-weight:700;"><?= $rb['count'] ?> <span style="color:var(--text-gray);font-weight:400;">(<?= $rb['pct'] ?>%)</span></span>
            </div>
            <?php endforeach; ?>

            <hr style="border:none;border-top:1px solid var(--border-color);margin:16px 0;">
            <div style="display:flex;justify-content:space-between;font-size:0.875rem;font-weight:700;">
              <span>Total accounts</span>
              <span><?= $totalAll ?></span>
            </div>
          </div>
        </div>

        <!-- Quick links -->
        <div class="section-card">
          <div class="card-head"><h2>Quick Actions</h2></div>
          <div style="padding:16px;">
            <?php if ($pendingCount > 0): ?>
            <a href="?section=applications"
               style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#FEF3C7;border-radius:8px;text-decoration:none;color:var(--warning);font-weight:700;font-size:0.825rem;margin-bottom:8px;">
              <span><i class="ri-time-line margin-right:6px"></i> <?= $pendingCount ?> application<?= $pendingCount > 1 ? 's' : '' ?> pending review</span>
              <i class="ri-arrow-right-line"></i>
            </a>
            <?php endif; ?>
            <?php if ($suspendedCount > 0): ?>
            <a href="?section=users"
               style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#FEF2F2;border-radius:8px;text-decoration:none;color:var(--danger);font-weight:700;font-size:0.825rem;margin-bottom:8px;">
              <span><i class="ri-forbid-line"></i> <?= $suspendedCount ?> suspended account<?= $suspendedCount > 1 ? 's' : '' ?></span>
              <i class="ri-arrow-right-line"></i>
            </a>
            <?php endif; ?>
            <a href="?section=users"
               style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#F1F5F9;border-radius:8px;text-decoration:none;color:var(--primary-dark);font-weight:700;font-size:0.825rem;">
              <span><i class="ri-team-line"></i> Manage all users</span>
              <i class="ri-arrow-right-line"></i>
            </a>
          </div>
        </div>

      </div>
    </div>

    <?php endif; /* /overview */ ?>


    <!-- ═════════ ADVISOR APPLICATIONS ═════════ -->
    <?php if ($section === 'applications'): ?>

    <?php if (empty($pendingApps) && empty($reviewedApps)): ?>
      <div style="background:var(--white);border:1px dashed var(--border-color);border-radius:10px;padding:48px;text-align:center;color:var(--text-gray);">
        <i class="ri-user-star-line" style="font-size:3rem;opacity:.25;display:block;margin-bottom:12px;"></i>
        <p style="font-weight:700;margin:0 0 4px;">No applications yet</p>
        <p style="font-size:0.875rem;margin:0;">Advisor applications will appear here once submitted.</p>
      </div>

    <?php else: ?>

      <!-- Pending -->
      <?php if (!empty($pendingApps)): ?>
      <div class="section-card" style="margin-bottom:24px;">
        <div class="card-head">
          <h2><i class="ri-time-line" style="color:var(--warning);margin-right:6px;"></i> Pending Review
            <span style="margin-left:8px;background:#FEF3C7;color:var(--warning);font-size:0.68rem;font-weight:800;padding:2px 8px;border-radius:4px;">
              <?= count($pendingApps) ?>
            </span>
          </h2>
        </div>

        <?php foreach ($pendingApps as $app): ?>
        <div class="app-card">
          <div class="app-card-header">
            <div>
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <h3 style="font-size:1rem;font-weight:700;margin:0;"><?= htmlspecialchars($app['full_name']) ?></h3>
                <span class="badge badge-pending">Pending</span>
              </div>
              <p style="font-size:0.825rem;color:var(--text-gray);margin:0;">
                <?= htmlspecialchars($app['email']) ?> ·
                Submitted <?= date('M j, Y g:ia', strtotime($app['submitted_at'])) ?>
              </p>
            </div>
          </div>

          <div class="info-grid">
            <div><strong>Specialization:</strong> <?= htmlspecialchars($app['specialization']) ?></div>
            <div><strong>Experience:</strong> <?= (int)$app['experience_yrs'] ?> years</div>
            <div style="grid-column:1/-1;"><strong>Qualifications:</strong> <?= htmlspecialchars($app['qualifications']) ?></div>
            <div style="grid-column:1/-1;"><strong>Bio:</strong> <?= htmlspecialchars($app['bio']) ?></div>
            <?php if ($app['linkedin_url']): ?>
            <div>
              <a href="<?= htmlspecialchars($app['linkedin_url']) ?>" target="_blank" rel="noopener"
                 style="color:#A16207;font-size:0.825rem;"><i class="ri-linkedin-box-line"></i> LinkedIn Profile</a>
            </div>
            <?php endif; ?>
          </div>

          <div class="action-row">
            <!-- Approve -->
            <form method="POST" action="<?= BASE_URL ?>/dashboard/actions/review-advisor.php" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
              <input type="hidden" name="decision" value="approved">
              <button type="submit" class="btn-approve"><i class="ri-check-line"></i> Approve</button>
            </form>

            <!-- Reject form -->
            <form method="POST" action="<?= BASE_URL ?>/dashboard/actions/review-advisor.php" style="display:flex;flex-direction:column;gap:6px;min-width:260px;">
              <?= csrf_field() ?>
              <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
              <input type="hidden" name="decision" value="rejected">
              <textarea name="admin_note" rows="2" placeholder="Rejection reason (optional)" class="reject-note"></textarea>
              <button type="submit" class="btn-reject"><i class="ri-close-line"></i> Reject</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Reviewed -->
      <?php if (!empty($reviewedApps)): ?>
      <div class="section-card">
        <div class="card-head">
          <h2><i class="ri-history-line" style="color:var(--text-gray);margin-right:6px;"></i> Previously Reviewed</h2>
          <span style="font-size:0.78rem;color:var(--text-gray);"><?= count($reviewedApps) ?> total</span>
        </div>
        <?php foreach ($reviewedApps as $app): ?>
        <div style="padding:14px 22px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
          <div>
            <div style="font-weight:700;font-size:0.875rem;"><?= htmlspecialchars($app['full_name']) ?></div>
            <div style="font-size:0.78rem;color:var(--text-gray);"><?= htmlspecialchars($app['email']) ?> · <?= htmlspecialchars($app['specialization']) ?></div>
            <?php if ($app['admin_note']): ?>
              <div style="font-size:0.75rem;color:var(--text-gray);margin-top:3px;font-style:italic;">Note: <?= htmlspecialchars($app['admin_note']) ?></div>
            <?php endif; ?>
          </div>
          <span class="badge badge-<?= $app['status'] ?>"><?= htmlspecialchars($app['status']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    <?php endif; /* empty check */ ?>
    <?php endif; /* /applications */ ?>


    <!-- ═════════ USER MANAGEMENT ═════════ -->
    <?php if ($section === 'users'): ?>

    <div class="section-card">
      <div class="card-head">
        <h2><i class="ri-team-line" style="color:#775A19;margin-right:6px;"></i> All Users
          <span style="font-size:0.78rem;color:var(--text-gray);font-weight:500;margin-left:8px;"><?= count($users) ?> accounts</span>
        </h2>
        <!-- Search (client-side) -->
        <input type="text" id="user-search" placeholder="Search by name or email…"
          style="border:1px solid var(--border-color);border-radius:6px;padding:7px 12px;font-size:0.825rem;width:220px;outline:none;"
          oninput="filterUsers(this.value)" />
      </div>

      <div style="overflow-x:auto;">
        <table class="user-table" id="users-table">
          <thead>
            <tr>
              <th>Name / Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Last Login</th>
              <th>Joined</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr data-search="<?= strtolower(htmlspecialchars($u['full_name'] . ' ' . $u['email'])) ?>">
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($u['full_name']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-gray);"><?= htmlspecialchars($u['email']) ?></div>
              </td>
              <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
              <td><span class="badge badge-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
              <td style="color:var(--text-gray);font-size:0.8rem;">
                <?= $u['last_login'] ? date('M j, Y', strtotime($u['last_login'])) : '—' ?>
              </td>
              <td style="color:var(--text-gray);font-size:0.8rem;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
              <td>
                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                  <form method="POST" action="<?= BASE_URL ?>/dashboard/actions/manage-user.php" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <?php if ($u['status'] === 'suspended'): ?>
                      <input type="hidden" name="new_status" value="active">
                      <button type="submit" class="btn-activate">Activate</button>
                    <?php else: ?>
                      <input type="hidden" name="new_status" value="suspended">
                      <button type="submit" class="btn-suspend">Suspend</button>
                    <?php endif; ?>
                  </form>
                <?php else: ?>
                  <span style="font-size:0.75rem;color:var(--text-gray);">You</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <script>
    function filterUsers(q) {
      const rows = document.querySelectorAll('#users-table tbody tr');
      const lower = q.toLowerCase().trim();
      rows.forEach(row => {
        const val = row.dataset.search || '';
        row.style.display = (!lower || val.includes(lower)) ? '' : 'none';
      });
    }
    </script>

    <?php endif; /* /users */ ?>

  </main>
</div>

</body>
</html>