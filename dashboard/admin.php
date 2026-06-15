<?php
// dashboard/admin.php — Admin Dashboard
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

require_role('admin');

$pdo = getDB();

// ── Stats ──────────────────────────────────────────────
$totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalAdvisors  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'advisor'")->fetchColumn();
$pendingCount   = (int)$pdo->query("SELECT COUNT(*) FROM advisor_applications WHERE status = 'pending'")->fetchColumn();
$suspendedCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();

// ── Advisor applications (with applicant info) ────────
$appsStmt = $pdo->query(
    "SELECT aa.*, u.full_name, u.email, u.status AS user_status
     FROM advisor_applications aa
     JOIN users u ON u.id = aa.user_id
     ORDER BY aa.submitted_at DESC"
);
$applications = $appsStmt->fetchAll();

$pendingApps  = array_filter($applications, fn($a) => $a['status'] === 'pending');
$reviewedApps = array_filter($applications, fn($a) => $a['status'] !== 'pending');

// ── Users list ──────────────────────────────────────────
$usersStmt = $pdo->query(
    "SELECT id, full_name, email, role, status, last_login, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 100"
);
$users = $usersStmt->fetchAll();

$pageTitle = 'Admin Dashboard';
$activeNav = '';
$cssPath   = BASE_URL . '/src/output.css';
?>
<?php include __DIR__ . '/../includes/head.php'; ?>

<div class="app-container">

  <!-- SIDEBAR -->
  <aside class="portal-sidebar">
    <div class="user-card">
      <div class="avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1))) ?></div>
      <div>
        <p style="font-weight:700;font-size:0.85rem;margin:0;"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></p>
        <p style="font-size:0.7rem;color:var(--text-gray);margin:0;text-transform:uppercase;letter-spacing:.05em;">Administrator</p>
      </div>
    </div>

    <a href="#overview" class="nav-item active"><i class="ri-dashboard-line"></i> Overview</a>
    <a href="#advisor-applications" class="nav-item"><i class="ri-user-star-line"></i> Advisor Applications
      <?php if ($pendingCount > 0): ?>
        <span style="margin-left:auto;background:var(--warning);color:#fff;border-radius:999px;font-size:0.7rem;padding:2px 8px;font-weight:800;"><?= $pendingCount ?></span>
      <?php endif; ?>
    </a>
    <a href="#users" class="nav-item"><i class="ri-team-line"></i> User Management</a>

    <div style="margin-top:32px;border-top:1px solid var(--border-color);padding-top:16px;">
      <a href="<?= BASE_URL ?>/index.html" class="nav-item"><i class="ri-home-4-line"></i> Back to Site</a>
      <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-item"><i class="ri-logout-box-line"></i> Sign Out</a>
    </div>
  </aside>

  <!-- MAIN -->
  <main>

    <div class="portal-header">
      <div>
        <h1 style="font-family:'Playfair Display',serif;font-size:1.8rem;margin:0;">Admin Dashboard</h1>
        <p style="color:var(--text-gray);font-size:0.9rem;margin:4px 0 0;">Manage advisor applications and platform users.</p>
      </div>
    </div>

    <?= render_flash() ?>

    <!-- ═══ OVERVIEW STATS ═══ -->
    <section id="overview">
      <div class="filter-row" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 34px;">
        <div>
          <label>Students</label>
          <div style="font-size:1.6rem;font-weight:800;color:var(--primary-dark);"><?= $totalUsers ?></div>
        </div>
        <div>
          <label>Advisors</label>
          <div style="font-size:1.6rem;font-weight:800;color:var(--primary-dark);"><?= $totalAdvisors ?></div>
        </div>
        <div>
          <label>Pending Applications</label>
          <div style="font-size:1.6rem;font-weight:800;color:var(--warning);"><?= $pendingCount ?></div>
        </div>
        <div>
          <label>Suspended Accounts</label>
          <div style="font-size:1.6rem;font-weight:800;color:var(--danger);"><?= $suspendedCount ?></div>
        </div>
      </div>
    </section>

    <!-- ═══ ADVISOR APPLICATIONS ═══ -->
    <section id="advisor-applications" style="margin-bottom:48px;">
      <h2 class="section-title">Advisor Applications</h2>

      <?php if (empty($pendingApps)): ?>
        <div class="flash" style="color:var(--text-gray);">No pending advisor applications.</div>
      <?php else: ?>
        <?php foreach ($pendingApps as $app): ?>
          <div style="background:var(--white);border:1px solid var(--border-color);border-radius:8px;padding:24px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;">
              <div style="flex:1;min-width:260px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                  <h3 style="margin:0;font-size:1.05rem;"><?= htmlspecialchars($app['full_name']) ?></h3>
                  <span style="background:#FEF3C7;color:var(--warning);font-size:0.7rem;font-weight:800;text-transform:uppercase;padding:2px 8px;border-radius:4px;">Pending</span>
                </div>
                <p style="color:var(--text-gray);font-size:0.85rem;margin:0 0 10px;"><?= htmlspecialchars($app['email']) ?></p>

                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;font-size:0.85rem;margin-bottom:12px;">
                  <div><strong>Specialization:</strong> <?= htmlspecialchars($app['specialization']) ?></div>
                  <div><strong>Experience:</strong> <?= (int)$app['experience_yrs'] ?> yrs</div>
                </div>

                <p style="font-size:0.85rem;color:var(--text-gray);margin:0 0 8px;"><strong>Qualifications:</strong> <?= htmlspecialchars($app['qualifications']) ?></p>
                <p style="font-size:0.85rem;color:var(--text-gray);margin:0 0 8px;"><strong>Bio:</strong> <?= htmlspecialchars($app['bio']) ?></p>

                <?php if ($app['linkedin_url']): ?>
                  <p style="font-size:0.85rem;margin:0 0 8px;">
                    <a href="<?= htmlspecialchars($app['linkedin_url']) ?>" target="_blank" rel="noopener" style="color:var(--accent-gold);">
                      <i class="ri-linkedin-box-line"></i> LinkedIn Profile
                    </a>
                  </p>
                <?php endif; ?>

                <p style="font-size:0.75rem;color:var(--text-gray);margin:0;">Submitted <?= htmlspecialchars(date('M j, Y g:ia', strtotime($app['submitted_at']))) ?></p>
              </div>

              <div style="display:flex;flex-direction:column;gap:10px;min-width:220px;">
                <!-- Approve -->
                <form method="POST" action="<?= BASE_URL ?>/dashboard/actions/review-advisor.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                  <input type="hidden" name="decision" value="approved">
                  <button type="submit" class="btn-primary" style="width:100%;border-radius:5px;padding:10px 16px;font-weight:700;background:var(--success);border-color:var(--success);">
                    <i class="ri-check-line"></i> Approve
                  </button>
                </form>

                <!-- Reject (with note) -->
                <form method="POST" action="<?= BASE_URL ?>/dashboard/actions/review-advisor.php" style="display:flex;flex-direction:column;gap:6px;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                  <input type="hidden" name="decision" value="rejected">
                  <textarea name="admin_note" rows="2" placeholder="Reason for rejection (optional)"
                    class="field" style="font-size:0.8rem;resize:none;"></textarea>
                  <button type="submit" class="btn-secondary" style="width:100%;border-radius:5px;padding:10px 16px;font-weight:700;color:var(--danger);border-color:var(--danger);">
                    <i class="ri-close-line"></i> Reject
                  </button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!empty($reviewedApps)): ?>
        <details style="margin-top:20px;">
          <summary style="cursor:pointer;font-weight:700;font-size:0.9rem;color:var(--text-gray);">
            Reviewed applications (<?= count($reviewedApps) ?>)
          </summary>
          <div style="margin-top:14px;display:flex;flex-direction:column;gap:10px;">
            <?php foreach ($reviewedApps as $app): ?>
              <div style="background:var(--white);border:1px solid var(--border-color);border-radius:8px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
                <div>
                  <strong><?= htmlspecialchars($app['full_name']) ?></strong>
                  <span style="color:var(--text-gray);font-size:0.85rem;"> — <?= htmlspecialchars($app['email']) ?></span>
                  <?php if ($app['admin_note']): ?>
                    <p style="font-size:0.8rem;color:var(--text-gray);margin:4px 0 0;">Note: <?= htmlspecialchars($app['admin_note']) ?></p>
                  <?php endif; ?>
                </div>
                <span style="font-size:0.7rem;font-weight:800;text-transform:uppercase;padding:2px 10px;border-radius:4px;
                  <?= $app['status'] === 'approved' ? 'background:#D1FAE5;color:var(--success);' : 'background:#FEE2E2;color:var(--danger);' ?>">
                  <?= htmlspecialchars($app['status']) ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>
    </section>

    <!-- ═══ USER MANAGEMENT ═══ -->
    <section id="users">
      <h2 class="section-title">User Management</h2>

      <div style="background:var(--white);border:1px solid var(--border-color);border-radius:8px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
          <thead>
            <tr style="background:var(--bg-light);text-align:left;">
              <th style="padding:12px 16px;font-weight:800;font-size:0.7rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-gray);">Name</th>
              <th style="padding:12px 16px;font-weight:800;font-size:0.7rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-gray);">Email</th>
              <th style="padding:12px 16px;font-weight:800;font-size:0.7rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-gray);">Role</th>
              <th style="padding:12px 16px;font-weight:800;font-size:0.7rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-gray);">Status</th>
              <th style="padding:12px 16px;font-weight:800;font-size:0.7rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-gray);">Joined</th>
              <th style="padding:12px 16px;font-weight:800;font-size:0.7rem;letter-spacing:.06em;text-transform:uppercase;color:var(--text-gray);">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr style="border-top:1px solid var(--border-color);">
                <td style="padding:12px 16px;font-weight:600;"><?= htmlspecialchars($u['full_name']) ?></td>
                <td style="padding:12px 16px;color:var(--text-gray);"><?= htmlspecialchars($u['email']) ?></td>
                <td style="padding:12px 16px;text-transform:capitalize;"><?= htmlspecialchars($u['role']) ?></td>
                <td style="padding:12px 16px;">
                  <span style="font-size:0.7rem;font-weight:800;text-transform:uppercase;padding:2px 10px;border-radius:4px;
                    <?php
                      echo match($u['status']) {
                          'active'    => 'background:#D1FAE5;color:var(--success);',
                          'suspended' => 'background:#FEE2E2;color:var(--danger);',
                          default     => 'background:#F3F4F6;color:var(--text-gray);',
                      };
                    ?>">
                    <?= htmlspecialchars($u['status']) ?>
                  </span>
                </td>
                <td style="padding:12px 16px;color:var(--text-gray);"><?= htmlspecialchars(date('M j, Y', strtotime($u['created_at']))) ?></td>
                <td style="padding:12px 16px;">
                  <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <form method="POST" action="<?= BASE_URL ?>/dashboard/actions/manage-user.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <?php if ($u['status'] === 'suspended'): ?>
                        <input type="hidden" name="new_status" value="active">
                        <button type="submit" class="btn-secondary" style="padding:6px 12px;font-size:0.75rem;border-radius:4px;color:var(--success);border-color:var(--success);">Activate</button>
                      <?php else: ?>
                        <input type="hidden" name="new_status" value="suspended">
                        <button type="submit" class="btn-secondary" style="padding:6px 12px;font-size:0.75rem;border-radius:4px;color:var(--danger);border-color:var(--danger);">Suspend</button>
                      <?php endif; ?>
                    </form>
                  <?php else: ?>
                    <span style="color:var(--text-gray);font-size:0.75rem;">You</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>
</div>

</body>
</html>