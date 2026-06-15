<?php
/**
 * index.php — Programs page (replaces index.html)
 *
 * What this adds vs the old index.html:
 *  - Session bootstrap (reads who is logged in)
 *  - Avatar + logout dropdown in navbar for logged-in users
 *  - "Get Started" / "Sign In" for guests
 *  - Hero CTA "Explore Programs" scrolls to #programs
 *  - Hero CTA "Speak with an Advisor" → dashboard or advisor booking
 *  - CTA buttons hooked to real destinations
 *  - Flash message display (from login redirects etc.)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_helpers.php';

// ── Session state ──────────────────────────────────────────────
$isLoggedIn   = is_logged_in();
$role         = $_SESSION['role']      ?? '';
$userName     = $_SESSION['full_name'] ?? '';

// Build initials
$parts = array_filter(explode(' ', trim($userName)));
if (count($parts) >= 2) {
    $userInitials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
} elseif (count($parts) === 1) {
    $userInitials = strtoupper(substr($parts[0], 0, 2));
} else {
    $userInitials = '';
}

// ── Page config (used by nav_helper) ──────────────────────────
$activeNav = 'programs';
$authPage  = '';
$pageTitle = 'Programs';
$base      = BASE_URL;

// ── Fetch a few live stats for the hero (optional, silently fails) ──
$totalStudents   = 12000;
$totalScholarships = 500;
try {
    $pdo = getDB();
    $row = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "user" AND status = "active"')->fetchColumn();
    if ($row > 0) $totalStudents = (int)$row;

    $sch = $pdo->query('SELECT COUNT(*) FROM scholarships WHERE is_active = 1')->fetchColumn();
    if ($sch > 0) $totalScholarships = (int)$sch;
} catch (\Throwable $e) {
    // Silently use defaults — DB may not be migrated yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="<?= $base ?>/src/output.css">
  <title>The Editorial Scholar — Programs</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:wght@400;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet" />

  <!-- Remix Icons -->
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />

  <style>
    /* Avatar dropdown fade animation */
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-6px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeInDown 0.15s ease; }

    /* Flash message */
    .flash-success { background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; }
    .flash-error   { background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; }
    .flash-info    { background:#EFF6FF; border:1px solid #BFDBFE; color:#1E40AF; }
  </style>
</head>
<body class="bg-[#F8F9FA] font-manrope">

  <!-- ════════════════════════════════
       NAV (shared partial)
  ════════════════════════════════ -->
  <?php include __DIR__ . '/includes/nav_helper.php'; ?>

  <!-- ════════════════════════════════
       FLASH MESSAGES
  ════════════════════════════════ -->
  <?php
  $flash = render_flash(); // from auth_helpers — returns HTML string or ''
  if ($flash):
  ?>
  <div class="fixed top-[68px] left-1/2 -translate-x-1/2 z-40 w-full max-w-xl px-4">
    <?= $flash ?>
  </div>
  <?php endif; ?>

  <!-- ════════════════════════════════
       HERO
  ════════════════════════════════ -->
  <section class="relative w-full min-h-[600px] bg-[#1A2B48] flex items-center justify-center overflow-hidden mt-[60px]">

    <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=1600')] bg-cover bg-center opacity-40"></div>
    <div class="absolute inset-0" style="background:linear-gradient(90deg,#031632 0%,rgba(3,22,50,.8) 50%,rgba(3,22,50,0) 100%)"></div>

    <div class="relative z-10 max-w-[1280px] w-full mx-auto px-8 py-24">
      <div class="flex flex-col gap-8 max-w-[795px]">

        <p class="font-manrope font-bold text-sm tracking-[2.8px] uppercase text-[#ecaa1c]">
          World Class Education Awaits......
        </p>

        <h1 class="font-newsreader font-bold text-[96px] leading-[96px] tracking-[-2.4px] text-white">
          Curating your <span class="font-light italic">intellectual </span> future.
        </h1>

        <p class="font-manrope font-normal text-2xl leading-8 text-[#8293B5] max-w-[672px]">
          Expert guidance for students navigating the world's most prestigious academic institutions — from application to arrival.
        </p>

        <div class="flex items-center gap-6 pt-2">
          <!-- Scrolls to #programs section -->
          <a href="#programs"
             class="bg-[#b58122] text-[#f1eee9] font-manrope font-bold text-lg px-10 py-4 rounded-md hover:bg-[#f19d18] transition-colors whitespace-nowrap">
            Explore Programs
          </a>

          <?php if ($isLoggedIn): ?>
            <!-- Logged-in user: go to their dashboard directly -->
            <a href="<?= $base ?>/dashboard/<?= $role ?>.php"
               class="flex items-center gap-2 text-white font-manrope font-medium text-lg py-4 hover:opacity-80 transition-opacity">
              My Dashboard
              <i class="ri-arrow-right-line text-base"></i>
            </a>
          <?php else: ?>
            <!-- Guest: watch overview / sign up prompt -->
            <a href="<?= $base ?>/auth/signUp.php"
               class="flex items-center gap-2 text-white font-manrope font-medium text-lg py-4 hover:opacity-80 transition-opacity">
              Get Started Free
              <i class="ri-arrow-right-line text-base"></i>
            </a>
          <?php endif; ?>
        </div>

        <!-- Live stats row -->
        <div class="flex items-center gap-8 pt-4 border-t border-white/10">
          <div>
            <p class="font-newsreader font-bold text-2xl text-white"><?= number_format($totalStudents) ?>+</p>
            <p class="font-manrope text-xs text-[#8293B5] uppercase tracking-[1px] mt-1">Students guided</p>
          </div>
          <div class="w-px h-10 bg-white/20"></div>
          <div>
            <p class="font-newsreader font-bold text-2xl text-white"><?= number_format($totalScholarships) ?>+</p>
            <p class="font-manrope text-xs text-[#8293B5] uppercase tracking-[1px] mt-1">Scholarships listed</p>
          </div>
          <div class="w-px h-10 bg-white/20"></div>
          <div>
            <p class="font-newsreader font-bold text-2xl text-white">2,500+</p>
            <p class="font-manrope text-xs text-[#8293B5] uppercase tracking-[1px] mt-1">Global institutions</p>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ════════════════════════════════
       PROGRAMS
  ════════════════════════════════ -->
  <section id="programs" class="w-full bg-[#F8F9FA] py-15">
    <div class="mx-auto px-8 flex flex-col gap-20 pl-10 pr-10">

      <!-- Header -->
      <div class="flex flex-row justify-between items-end pl-30">
        <div class="flex flex-col gap-6 max-w-[631px]">
          <h2 class="font-newsreader font-bold text-5xl leading-[48px] text-[#16223d]">Our Programs</h2>
          <p class="font-manrope leading-[29px] text-[#16223d] text-xl">
            A complete suite of services built around your ambitions — from early planning to post-acceptance preparation.
          </p>
        </div>
        <div>
          <div class="mr-10 w-24 h-0.5 bg-[#775A19]"></div>
        </div>
      </div>

      <!-- Cards -->
      <div class="relative w-[90%]" style="min-height:900px;">

        <!-- Card 1 — Test Prep (light, left) -->
        <div class="absolute left-0 top-0 ml-32 bg-[#F3F4F5] flex flex-col justify-between p-12 overflow-hidden" style="right:300px;height:450px;">
          <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1513258496099-48168024aec0?w=800')] w-[670px] bg-cover bg-center opacity-20 rounded-l-2xl"></div>

          <div class="relative z-10 flex flex-col gap-4 max-w-[448px]">
            <p class="font-manrope font-bold text-[14px] tracking-[1.2px] uppercase text-[#ae8429]">Precision Testing</p>
            <h3 class="font-newsreader font-bold text-[30px] leading-9 text-[#16223d]">Test Prep Mastery</h3>
            <p class="font-manrope font-normal text-lg leading-[26px] text-[#16223d]">
              Master the IELTS, GRE, and TOEFL with our curated study plans and diagnostic assessments designed by former examiners.
            </p>
            <div class="flex gap-3 pt-4">
              <span class="bg-[#172e6d] text-[#f1f2f4] font-manrope font-bold text-xs tracking-[-0.6px] uppercase px-3 py-1 rounded-sm">IELTS</span>
              <span class="bg-[#c3cdea] text-[#05235a] font-manrope font-bold text-xs tracking-[-0.6px] uppercase px-3 py-1 rounded-sm">GRE</span>
              <span class="bg-[#c3cdea] text-[#05235a] font-manrope font-bold text-xs tracking-[-0.6px] uppercase px-3 py-1 rounded-sm">TOEFL</span>
            </div>
          </div>

          <a href="<?= $base ?>/testPrep.html"
             class="relative z-10 inline-flex items-center gap-2 border-b-gold pb-1 w-fit hover:opacity-70 transition-opacity font-manrope font-bold text-base text-[#031632]">
            Explore Test Prep
            <i class="ri-arrow-right-s-line text-base text-[#031632]"></i>
          </a>
        </div>

        <!-- Card 2 — University Matcher (dark, right) -->
        <div class="absolute top-0 right-0 bg-[#05204a] border-t-gold flex flex-col justify-between p-12 rounded-r-2xl" style="left:800px;height:450px;">
          <div class="flex flex-col gap-4">
            <p class="font-manrope font-bold text-[15px] tracking-[1.2px] uppercase text-[#c4942c]">Algorithmic Precision</p>
            <h3 class="font-newsreader font-bold text-[30px] leading-9 text-white">University Matcher</h3>
            <p class="font-manrope font-normal text-lg leading-[26px] text-[#ebecee]">
              Our proprietary matching system cross-references your profile against 2,500+ global institutions to find your perfect fit.
            </p>
          </div>
          <?php if ($isLoggedIn): ?>
            <a href="<?= $base ?>/dashboard/<?= $role ?>.php"
               class="bg-[#906915] text-white font-manrope font-bold text-sm tracking-[0.35px] uppercase py-4 rounded-md text-center hover:bg-[#be8331] transition-colors block">
              Go to My Dashboard
            </a>
          <?php else: ?>
            <a href="<?= $base ?>/auth/signUp.php"
               class="bg-[#906915] text-white font-manrope font-bold text-sm tracking-[0.35px] uppercase py-4 rounded-md text-center hover:bg-[#be8331] transition-colors block">
              Find Your Match
            </a>
          <?php endif; ?>
        </div>

        <!-- Card 3 — Visa Information (white, center-bottom) -->
        <div class="absolute bg-white flex flex-row ml-26 rounded-2xl" style="left:205.33px;right:205.33px;top:516px;height:400px;">
          <div class="p-12" style="flex:0 0 50%;">
            <div class="w-full h-full bg-[url('https://images.unsplash.com/photo-1554774853-aae0a22c8aa4?w=600')] bg-cover bg-center rounded-sm opacity-80"></div>
          </div>
          <div class="flex flex-col gap-4 justify-center p-12" style="flex:1 1 0;">
            <p class="font-manrope font-bold text-lg tracking-[1.2px] uppercase text-[#b58726]">Regulatory Guidance</p>
            <h3 class="font-newsreader font-bold text-[30px] leading-9 text-[#0f2932]">Visa Information</h3>
            <p class="font-manrope font-normal text-[18px] leading-[26px] text-[#0f2932]">
              Expert navigation through student visa requirements for UK, USA, Canada, and the EU.
            </p>
            <a href="<?= $base ?>/visa.html"
               class="inline-flex items-center gap-2 pt-2 w-fit hover:opacity-70 transition-opacity font-manrope font-bold text-base text-[#0f2932]">
              Read Visa Guides
              <i class="ri-arrow-right-line text-base text-[#0f2932]"></i>
            </a>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ════════════════════════════════
       PREPAREDNESS
  ════════════════════════════════ -->
  <section class="w-full bg-[#F3F4F5] py-32">
    <div class="max-w-[1280px] mx-auto px-8 flex flex-row items-center gap-20">

      <!-- Image column -->
      <div class="relative flex-shrink-0" style="width:568px;height:710px;">
        <div class="absolute border-gold-faint" style="left:-24px;right:24px;top:-24px;bottom:24px;z-index:0;"></div>
        <div class="relative w-full h-full bg-[url('https://plus.unsplash.com/premium_photo-1664372145591-f7cc308ff5da?q=80&w=696&auto=format&fit=crop')] bg-cover bg-center" style="z-index:1;"></div>

        <!-- Testimonial card -->
        <div class="absolute bg-white p-8 flex flex-col gap-4 shadow-xl" style="width:318px;right:-40px;bottom:-40px;z-index:2;">
          <div class="w-[34px] h-6 bg-[#ab8023] flex items-center justify-center">
            <i class="ri-double-quotes-l text-white text-sm"></i>
          </div>
          <p class="font-newsreader font-normal text-lg leading-[25px] text-[#0f2932]">
            "The Editorial Scholar didn't just help me get in — they prepared me to thrive from day one."
          </p>
          <p class="font-manrope font-bold text-xs tracking-[1.2px] uppercase text-[#0f2932]">
            Aisha M. — LSE Class of 2026
          </p>
        </div>
      </div>

      <!-- Text column -->
      <div class="flex flex-col gap-10" style="width:568px;">
        <h2 class="font-newsreader font-bold text-5xl leading-[60px] text-[#0f2932]">
          Beyond placement, we focus on preparedness.
        </h2>

        <div class="flex flex-col gap-8">

          <!-- Feature 1 -->
          <div class="flex flex-row gap-6 items-start">
            <div class="flex-shrink-0 w-12 h-12 bg-[#1A2B48] flex items-center justify-center">
              <i class="ri-book-open-line text-xl text-[#775A19]"></i>
            </div>
            <div class="flex flex-col gap-1">
              <h4 class="font-manrope font-bold text-lg leading-7 text-[#0f2932]">Personalised Study Plans</h4>
              <p class="font-manrope font-normal text-sm leading-5 text-[#0f2932]">Custom diagnostic assessments feed directly into adaptive prep modules built around your target score and timeline.</p>
            </div>
          </div>

          <!-- Feature 2 -->
          <div class="flex flex-row gap-6 items-start">
            <div class="flex-shrink-0 w-12 h-12 bg-[#1A2B48] flex items-center justify-center">
              <i class="ri-user-heart-line text-xl text-[#775A19]"></i>
            </div>
            <div class="flex flex-col gap-1">
              <h4 class="font-manrope font-bold text-lg leading-7 text-[#0f2932]">Expert Advisor Network</h4>
              <p class="font-manrope font-normal text-sm leading-5 text-[#0f2932]">One-on-one consultations with vetted advisors who hold degrees from Oxford, MIT, LSE, and beyond.</p>
            </div>
          </div>

          <!-- Feature 3 -->
          <div class="flex flex-row gap-6 items-start">
            <div class="flex-shrink-0 w-12 h-12 bg-[#1A2B48] flex items-center justify-center">
              <i class="ri-award-line text-xl text-[#775A19]"></i>
            </div>
            <div class="flex flex-col gap-1">
              <h4 class="font-manrope font-bold text-lg leading-7 text-[#0f2932]">Scholarship Access</h4>
              <p class="font-manrope font-normal text-sm leading-5 text-[#0f2932]">Access our curated database of <?= number_format($totalScholarships) ?>+ scholarships with eligibility matching and application timeline support.</p>
            </div>
          </div>

        </div>

        <!-- CTA under features -->
        <div class="flex items-center gap-4 pt-2">
          <?php if ($isLoggedIn): ?>
            <a href="<?= $base ?>/scholarship.php"
               class="bg-[#031632] text-white font-manrope font-bold text-base px-8 py-3.5 rounded-md hover:bg-[#1A2B48] transition-colors">
              Browse Scholarships
            </a>
            <a href="<?= $base ?>/dashboard/<?= $role ?>.php"
               class="font-manrope font-medium text-base text-[#0f2932] underline underline-offset-2 hover:text-[#775A19] transition-colors">
              My Dashboard →
            </a>
          <?php else: ?>
            <a href="<?= $base ?>/auth/signUp.php"
               class="bg-[#031632] text-white font-manrope font-bold text-base px-8 py-3.5 rounded-md hover:bg-[#1A2B48] transition-colors">
              Start for Free
            </a>
            <a href="<?= $base ?>/auth/signIn.php"
               class="font-manrope font-medium text-base text-[#0f2932] underline underline-offset-2 hover:text-[#775A19] transition-colors">
              Already a member? Sign in
            </a>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </section>

  <!-- ════════════════════════════════
       CTA BANNER
  ════════════════════════════════ -->
  <section class="w-full bg-[#031632] py-24 px-48">
    <div class="max-w-[896px] mx-auto px-8 flex flex-col items-center gap-8">

      <h2 class="font-newsreader font-bold text-[60px] leading-[60px] text-white text-center">
        Ready To Write Your Next Chapter?
      </h2>
      <p class="font-manrope font-normal text-xl leading-8 text-[#8293B5] text-center max-w-[764px]">
        Join <?= number_format($totalStudents) ?>+ students who have successfully curated their future with The Editorial Scholar.
      </p>

      <div class="flex flex-col items-center gap-6 pt-2">
        <?php if ($isLoggedIn): ?>
          <a href="<?= $base ?>/dashboard/<?= $role ?>.php"
             class="bg-[#775A19] text-white font-manrope font-bold text-lg px-12 py-5 rounded-md hover:bg-[#A16207] transition-colors shadow-[0_25px_50px_-12px_rgba(0,0,0,0.2)]">
            Go to My Dashboard
          </a>
        <?php else: ?>
          <a href="<?= $base ?>/auth/signUp.php"
             class="bg-[#775A19] text-white font-manrope font-bold text-lg px-12 py-5 rounded-md hover:bg-[#A16207] transition-colors shadow-[0_25px_50px_-12px_rgba(0,0,0,0.2)]">
            Speak with an Advisor
          </a>
          <p class="font-manrope font-normal text-xs tracking-[1.2px] uppercase text-[#8293B5]/80">
            Initial consultations are complimentary
          </p>
        <?php endif; ?>
      </div>

    </div>
  </section>

  <!-- ════════════════════════════════
       FOOTER
  ════════════════════════════════ -->
  <footer class="w-full bg-[#F8FAFC] border-t border-[#E2E8F0] py-12">
    <div class="max-w-[1280px] mx-auto px-8">
      <div class="grid grid-cols-4 gap-8">

        <!-- Brand -->
        <div class="flex flex-col gap-4">
          <p class="font-newsreader font-bold text-lg text-[#0F172A]">The Editorial Scholar</p>
          <p class="font-manrope font-normal text-sm leading-5 tracking-[0.35px] text-[#64748B]">
            &copy; <?= date('Y') ?> The Editorial Scholar.<br/>Curating Global Futures.
          </p>
        </div>

        <!-- Company -->
        <div class="flex flex-col gap-3">
          <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Company</p>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">About Us</a>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Contact Support</a>
        </div>

        <!-- Legal -->
        <div class="flex flex-col gap-3">
          <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Legal</p>
          <a href="<?= $base ?>/terms.html" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Terms of Service</a>
          <a href="<?= $base ?>/privacy.html" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Privacy Policy</a>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Academic Integrity</a>
        </div>

        <!-- Social -->
        <div class="flex flex-col gap-3">
          <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Social</p>
          <div class="flex items-center gap-4">
            <a href="#" class="text-[#64748B] hover:text-[#0F172A] transition-colors"><i class="ri-twitter-x-line text-xl"></i></a>
            <a href="#" class="text-[#64748B] hover:text-[#0F172A] transition-colors"><i class="ri-linkedin-line text-xl"></i></a>
            <a href="#" class="text-[#64748B] hover:text-[#0F172A] transition-colors"><i class="ri-instagram-line text-xl"></i></a>
          </div>
        </div>

      </div>
    </div>
  </footer>

</body>
</html>