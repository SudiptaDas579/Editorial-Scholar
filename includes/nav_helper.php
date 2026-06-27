<?php
/**
 * includes/nav_helper.php
 *
 * Renders the site-wide <nav> bar.
 * Expects these variables to be set before including:
 *   $activeNav  (string)  — 'programs' | 'scholarships' | 'testprep' | 'visa' | 'research' | ''
 *   $isLoggedIn (bool)
 *   $role       (string)  — 'user' | 'advisor' | 'admin'
 *   $userName   (string)  — full name of logged-in user (or '')
 *   $userInitials (string)— 1-2 letter initials (or '')
 *   $authPage   (string)  — 'signin' | 'signup' | '' (special nav states)
 *
 * The file reads $isLoggedIn / $role / $userName from $_SESSION if
 * they haven't already been set by the calling page.
 */

// Auto-resolve from session if not already provided
if (!isset($isLoggedIn)) {
    $isLoggedIn = isset($_SESSION['user_id']);
}
if (!isset($role)) {
    $role = $_SESSION['role'] ?? '';
}
if (!isset($userName)) {
    $userName = $_SESSION['full_name'] ?? '';
}
if (!isset($userInitials)) {
    // Build initials from the full name: "John Doe" → "JD"
    $parts = array_filter(explode(' ', trim($userName)));
    if (count($parts) >= 2) {
        $userInitials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    } elseif (count($parts) === 1) {
        $userInitials = strtoupper(substr($parts[0], 0, 2));
    } else {
        $userInitials = '?';
    }
}
if (!isset($authPage)) {
    $authPage = '';
}
if (!isset($activeNav)) {
    $activeNav = '';
}

$base = defined('BASE_URL') ? BASE_URL : '';

/**
 * Helper: build nav link class
 */
function navClass(string $key, string $active): string {
    $base = 'font-newsreader font-semibold text-base tracking-[-0.4px] transition-colors';
    if ($key === $active) {
        return $base . ' text-[#A16207] border-b-2 border-[#A16207] pb-1';
    }
    return $base . ' text-[#475569] hover:text-[#0F172A]';
}
?>
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-b border-[#F1F5F9] shadow-sm">
  <div class="px-8 h-[60px] flex items-center justify-between gap-4">

    <!-- Logo -->
    <a href="<?= $base ?>/index.php"
       class="font-newsreader font-bold text-2xl text-[#0F172A] whitespace-nowrap ml-3">
      The Editorial Scholar
    </a>

    <!-- Main nav links -->
    <div class="hidden md:flex items-center gap-8">
      <a href="<?= $base ?>/index.php"         class="<?= navClass('programs',    $activeNav) ?>">Programs</a>
      <a href="<?= $base ?>/scholarship.php"   class="<?= navClass('scholarships', $activeNav) ?>">Scholarships</a>
      <a href="<?= $base ?>/testPrep.php"     class="<?= navClass('testprep',    $activeNav) ?>">Test Prep</a>
      <a href="<?= $base ?>/visa.php"         class="<?= navClass('visa',        $activeNav) ?>">Visa Guide</a>
      <a href="<?= $base ?>/research.php"      class="<?= navClass('research',    $activeNav) ?>">Research</a>
    </div>

    <!-- Right side: search + auth controls -->
    <div class="flex items-center gap-5 mr-3">

      <!-- Search -->
      <div class="hidden md:flex items-center gap-2">
        <i class="ri-search-line text-lg text-[#44474D]"></i>
        <input
          type="search"
          name="q"
          placeholder="Search"
          class="w-44 px-2 py-1 text-sm rounded-md border border-[#E2E8F0]
                 focus:outline-none focus:ring-2 focus:ring-[#A16207]/50 focus:border-[#A16207]/50 transition-colors"
        />
      </div>

      <?php if ($authPage === 'signin'): ?>
        <!-- On Sign-In page: only show Sign Up -->
        <a href="<?= $base ?>/auth/signUp.php"
           class="bg-[#031632] text-white font-manrope font-medium text-sm px-5 py-2 rounded-md hover:bg-[#1A2B48] transition-colors">
          Sign Up
        </a>

      <?php elseif ($authPage === 'signup'): ?>
        <!-- On Sign-Up page: only show Sign In -->
        <a href="<?= $base ?>/auth/signIn.php"
           class="bg-[#031632] text-white font-manrope font-medium text-sm px-5 py-2 rounded-md hover:bg-[#1A2B48] transition-colors">
          Sign In
        </a>

      <?php elseif ($isLoggedIn): ?>
        <!-- ── LOGGED-IN: Avatar + dropdown ───────────────────── -->
        <div class="relative" id="avatarMenu">

          <!-- Avatar trigger button -->
          <button
            id="avatarBtn"
            onclick="toggleAvatarMenu()"
            class="flex items-center gap-2 focus:outline-none group"
            aria-expanded="false"
            aria-haspopup="true"
          >
            <!-- Initials circle -->
            <div class="w-9 h-9 rounded-full bg-[#031632] text-white flex items-center justify-center
                        font-manrope font-bold text-sm ring-2 ring-white group-hover:ring-[#A16207]
                        transition-all select-none">
              <?= htmlspecialchars($userInitials) ?>
            </div>
            <!-- Name (hidden on small screens) -->
            <span class="hidden md:block font-manrope font-medium text-sm text-[#374151] max-w-[120px] truncate">
              <?= htmlspecialchars(explode(' ', $userName)[0]) ?>
            </span>
            <i class="ri-arrow-down-s-line text-[#9CA3AF] text-base transition-transform" id="avatarChevron"></i>
          </button>

          <!-- Dropdown panel -->
          <div
            id="avatarDropdown"
            class="hidden absolute right-0 top-[calc(100%+10px)] w-56 bg-white rounded-xl shadow-xl
                   border border-[#E5E7EB] overflow-hidden z-50 animate-fade-in"
            role="menu"
          >
            <!-- User info header -->
            <div class="px-4 py-3 border-b border-[#F1F5F9] bg-[#F8F9FA]">
              <p class="font-manrope font-semibold text-sm text-[#0F172A] truncate">
                <?= htmlspecialchars($userName) ?>
              </p>
              <p class="font-manrope text-xs text-[#9CA3AF] mt-0.5 capitalize">
                <?= htmlspecialchars($role) ?>
              </p>
            </div>

            <!-- Links -->
            <div class="py-1">
              <a href="<?= $base ?>/dashboard/<?= $role ?>.php"
                 class="flex items-center gap-2.5 px-4 py-2.5 font-manrope text-sm text-[#374151]
                        hover:bg-[#F8F9FA] hover:text-[#0F172A] transition-colors"
                 role="menuitem">
                <i class="ri-dashboard-line text-base text-[#775A19]"></i>
                My Dashboard
              </a>

              <?php if ($role === 'user'): ?>
              <a href="<?= $base ?>/scholarship.php"
                 class="flex items-center gap-2.5 px-4 py-2.5 font-manrope text-sm text-[#374151]
                        hover:bg-[#F8F9FA] hover:text-[#0F172A] transition-colors"
                 role="menuitem">
                <i class="ri-award-line text-base text-[#775A19]"></i>
                Browse Scholarships
              </a>
              <?php endif; ?>

              <?php if ($role === 'advisor'): ?>
              <a href="<?= $base ?>/dashboard/advisor.php#sessions"
                 class="flex items-center gap-2.5 px-4 py-2.5 font-manrope text-sm text-[#374151]
                        hover:bg-[#F8F9FA] hover:text-[#0F172A] transition-colors"
                 role="menuitem">
                <i class="ri-calendar-line text-base text-[#775A19]"></i>
                My Sessions
              </a>
              <?php endif; ?>

              <?php if ($role === 'admin'): ?>
              <a href="<?= $base ?>/dashboard/admin.php"
                 class="flex items-center gap-2.5 px-4 py-2.5 font-manrope text-sm text-[#374151]
                        hover:bg-[#F8F9FA] hover:text-[#0F172A] transition-colors"
                 role="menuitem">
                <i class="ri-shield-user-line text-base text-[#775A19]"></i>
                Admin Panel
              </a>
              <?php endif; ?>
            </div>

            <!-- Divider + Sign Out -->
            <div class="border-t border-[#F1F5F9] py-1">
              <a href="<?= $base ?>/auth/logout.php"
                 class="flex items-center gap-2.5 px-4 py-2.5 font-manrope text-sm text-red-600
                        hover:bg-red-50 transition-colors"
                 role="menuitem">
                <i class="ri-logout-box-r-line text-base"></i>
                Sign Out
              </a>
            </div>

          </div><!-- /dropdown -->
        </div><!-- /avatarMenu -->

      <?php else: ?>
        <!-- ── GUEST: Sign In + Get Started ───────────────────── -->
        <a href="<?= $base ?>/auth/signIn.php"
           class="font-manrope font-medium text-sm text-[#475569] hover:text-[#0F172A] transition-colors">
          Sign In
        </a>
        <a href="<?= $base ?>/auth/signUp.php"
           class="bg-[#031632] text-white font-manrope font-medium text-sm px-5 py-2 rounded-md
                  hover:bg-[#1A2B48] transition-colors">
          Get Started
        </a>
      <?php endif; ?>

    </div><!-- /right controls -->
  </div>
</nav>

<!-- Avatar dropdown JS (only needed when logged in) -->
<?php if ($isLoggedIn): ?>
<script>
(function () {
  function toggleAvatarMenu() {
    const dropdown = document.getElementById('avatarDropdown');
    const chevron  = document.getElementById('avatarChevron');
    const btn      = document.getElementById('avatarBtn');
    const isOpen   = !dropdown.classList.contains('hidden');

    if (isOpen) {
      dropdown.classList.add('hidden');
      chevron.style.transform  = '';
      btn.setAttribute('aria-expanded', 'false');
    } else {
      dropdown.classList.remove('hidden');
      chevron.style.transform  = 'rotate(180deg)';
      btn.setAttribute('aria-expanded', 'true');
    }
  }

  // Expose to onclick
  window.toggleAvatarMenu = toggleAvatarMenu;

  // Close when clicking outside
  document.addEventListener('click', function (e) {
    const menu = document.getElementById('avatarMenu');
    if (menu && !menu.contains(e.target)) {
      const dropdown = document.getElementById('avatarDropdown');
      const chevron  = document.getElementById('avatarChevron');
      const btn      = document.getElementById('avatarBtn');
      if (dropdown && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
        chevron.style.transform = '';
        btn.setAttribute('aria-expanded', 'false');
      }
    }
  });

  // Close on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      const dropdown = document.getElementById('avatarDropdown');
      const chevron  = document.getElementById('avatarChevron');
      const btn      = document.getElementById('avatarBtn');
      if (dropdown && !dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
        chevron.style.transform = '';
        btn.setAttribute('aria-expanded', 'false');
        btn.focus();
      }
    }
  });
})();
</script>
<?php endif; ?>