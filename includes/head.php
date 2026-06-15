<?php
// includes/head.php  — shared <head> + nav partial
// Usage: include __DIR__ . '/../includes/head.php';
// Expects: $pageTitle (string), $activeNav (string, optional)
// Optional: $authPage = 'signin' | 'signup'
//   When set, the right-side nav area shows a single button linking to
//   the *other* auth page instead of the normal Sign In / Get Started
//   (or Dashboard / Sign Out) pair.

require_once __DIR__ . '/../config/app.php';

$pageTitle  = $pageTitle  ?? 'The Editorial Scholar';
$activeNav  = $activeNav  ?? '';
$authPage   = $authPage   ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
$role       = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- Tailwind compiled output (relative path — adjust per file depth) -->
  <link rel="stylesheet" href="<?= $cssPath ?? '../dist/output.css' ?>">

  <title><?= htmlspecialchars($pageTitle) ?> — The Editorial Scholar</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:wght@400;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet" />
  <!-- Remix Icons -->
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />

  <style>
    /* Auth-page & dashboard utility overrides not in Tailwind build */
    .auth-gradient   { background: linear-gradient(135deg, #031632 0%, #1A2B48 60%, #775A19 100%); }
    .gold-border     { border-color: #775A19; }
    .input-focus:focus { border-color: #A16207; outline: none; box-shadow: 0 0 0 3px rgba(161,98,7,.18); }
    .btn-primary     { background:#031632; color:#fff; transition: background .2s; }
    .btn-primary:hover{ background:#1A2B48; }
    .btn-gold        { background:#775A19; color:#fff; transition: background .2s; }
    .btn-gold:hover  { background:#A16207; }
    .alert-error     { background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:.75rem 1rem; border-radius:.375rem; }
    .alert-success   { background:#F0FDF4; border:1px solid #BBF7D0; color:#166534; padding:.75rem 1rem; border-radius:.375rem; }
    .alert-info      { background:#EFF6FF; border:1px solid #BFDBFE; color:#1E40AF; padding:.75rem 1rem; border-radius:.375rem; }
  </style>
</head>
<body class="bg-[#F8F9FA] font-manrope">

<!-- NAV -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-b border-[#F1F5F9] shadow-sm">
  <div class="px-8 h-[60px] flex items-center justify-between gap-4">

    <a href="<?= BASE_URL ?>/index.html" class="font-newsreader font-bold text-2xl text-[#0F172A] whitespace-nowrap ml-5">
      The Editorial Scholar
    </a>

    <div class="hidden md:flex items-center gap-8">
      <a href="<?= BASE_URL ?>/index.html"        class="font-newsreader font-semibold text-sm tracking-[-0.3px] <?= $activeNav==='programs'    ? 'text-[#A16207] border-b-2 border-[#A16207] pb-1' : 'text-[#475569] hover:text-[#0F172A]' ?> transition-colors">Programs</a>
      <a href="<?= BASE_URL ?>/scholarship.php"   class="font-newsreader font-semibold text-sm tracking-[-0.3px] <?= $activeNav==='scholarships' ? 'text-[#A16207] border-b-2 border-[#A16207] pb-1' : 'text-[#475569] hover:text-[#0F172A]' ?> transition-colors">Scholarships</a>
      <a href="<?= BASE_URL ?>/testPrep.html"     class="font-newsreader font-semibold text-sm tracking-[-0.3px] <?= $activeNav==='testprep'     ? 'text-[#A16207] border-b-2 border-[#A16207] pb-1' : 'text-[#475569] hover:text-[#0F172A]' ?> transition-colors">Test Prep</a>
      <a href="<?= BASE_URL ?>/visa.html"         class="font-newsreader font-semibold text-sm tracking-[-0.3px] <?= $activeNav==='visa'         ? 'text-[#A16207] border-b-2 border-[#A16207] pb-1' : 'text-[#475569] hover:text-[#0F172A]' ?> transition-colors">Visa Guide</a>
      <a href="<?= BASE_URL ?>/research.html"     class="font-newsreader font-semibold text-sm tracking-[-0.3px] <?= $activeNav==='research'     ? 'text-[#A16207] border-b-2 border-[#A16207] pb-1' : 'text-[#475569] hover:text-[#0F172A]' ?> transition-colors">Research</a>
    </div>

    <div class="flex items-center gap-4 mr-3">
      <?php if ($authPage === 'signin'): ?>
        <!-- On Sign In page: single button to switch to Sign Up -->
        <a href="<?= BASE_URL ?>/auth/signUp.php"
           class="btn-primary font-manrope font-medium text-sm px-5 py-2 rounded-md">
          Sign Up
        </a>
      <?php elseif ($authPage === 'signup'): ?>
        <!-- On Sign Up page: single button to switch to Sign In -->
        <a href="<?= BASE_URL ?>/auth/signIn.php"
           class="btn-primary font-manrope font-medium text-sm px-5 py-2 rounded-md">
          Sign In
        </a>
      <?php elseif ($isLoggedIn): ?>
        <!-- Logged-in state -->
        <a href="<?= BASE_URL ?>/dashboard/<?= $role ?>.php"
           class="font-manrope font-medium text-sm text-[#475569] hover:text-[#0F172A] transition-colors flex items-center gap-1">
          <i class="ri-dashboard-line"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>/auth/logout.php"
           class="btn-primary font-manrope font-medium text-sm px-5 py-2 rounded-md">
          Sign Out
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/auth/signIn.php"
           class="font-manrope font-medium text-sm text-[#475569] hover:text-[#0F172A] transition-colors">
          Sign In
        </a>
        <a href="<?= BASE_URL ?>/auth/signUp.php"
           class="btn-primary font-manrope font-medium text-sm px-5 py-2 rounded-md">
          Get Started
        </a>
      <?php endif; ?>
    </div>

  </div>
</nav>