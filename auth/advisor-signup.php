<?php
// auth/advisor-signup.php — Advisor registration (creates user + advisor_application)
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard/' . $_SESSION['role'] . '.php');
}

$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {

        $values = [
            'full_name'        => sanitize($_POST['full_name']       ?? ''),
            'email'            => sanitize($_POST['email']            ?? ''),
            'password'         => $_POST['password']                  ?? '',
            'password_confirm' => $_POST['password_confirm']          ?? '',
            'specialization'   => sanitize($_POST['specialization']   ?? ''),
            'qualifications'   => sanitize($_POST['qualifications']   ?? ''),
            'experience_yrs'   => (int)($_POST['experience_yrs']      ?? 0),
            'bio'              => sanitize($_POST['bio']               ?? ''),
            'linkedin_url'     => sanitize($_POST['linkedin_url']      ?? ''),
        ];

        // Validation
        if (!$values['full_name'])            $errors[] = 'Full name is required.';
        if (!$values['email'])                $errors[] = 'Email is required.';
        elseif (!validate_email($values['email'])) $errors[] = 'Please enter a valid email.';
        if (!$values['password'])             $errors[] = 'Password is required.';
        elseif (!validate_password($values['password']))
            $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and a number.';
        if ($values['password'] !== $values['password_confirm'])
            $errors[] = 'Passwords do not match.';
        if (!$values['specialization'])       $errors[] = 'Specialization is required.';
        if (strlen($values['qualifications']) < 20) $errors[] = 'Please describe your qualifications (min 20 characters).';
        if (strlen($values['bio']) < 30)      $errors[] = 'Bio must be at least 30 characters.';
        if ($values['experience_yrs'] < 0)   $errors[] = 'Experience years must be 0 or more.';
        if (!isset($_POST['terms']))          $errors[] = 'You must agree to the Terms of Service and Advisor Code of Conduct.';

        if (empty($errors)) {
            $pdo = getDB();

            // Check uniqueness
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$values['email']]);
            if ($check->fetch()) {
                $errors[] = 'An account with this email already exists. Please sign in.';
            } else {

                $pdo->beginTransaction();
                try {
                    // 1. Create user with role=advisor but status=active
                    //    They still can't log in until application is approved (checked in login)
                    $hash = password_hash($values['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                    $ins  = $pdo->prepare(
                        'INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)'
                    );
                    $ins->execute([$values['full_name'], $values['email'], $hash, 'advisor', 'active']);
                    $userId = (int)$pdo->lastInsertId();

                    // 2. Create advisor application
                    $app = $pdo->prepare(
                        'INSERT INTO advisor_applications
                         (user_id, specialization, qualifications, experience_yrs, bio, linkedin_url, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $app->execute([
                        $userId,
                        $values['specialization'],
                        $values['qualifications'],
                        $values['experience_yrs'],
                        $values['bio'],
                        $values['linkedin_url'] ?: null,
                        'pending',
                    ]);

                    $pdo->commit();

                    flash('success', 'Application submitted! Our team will review it and notify you at ' . $values['email'] . ' within 2–3 business days.');
                    redirect(BASE_URL . '/auth/signIn.php');

                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    error_log('Advisor signup error: ' . $e->getMessage());
                    $errors[] = 'An error occurred. Please try again.';
                }
            }
        }
    }
}

$specializationOptions = [
    'University Admissions',
    'Scholarship Guidance',
    'IELTS / TOEFL Coaching',
    'GRE / GMAT Coaching',
    'Visa & Immigration',
    'Career Counselling',
    'Research Guidance',
    'MBA Admissions',
    'Undergraduate Admissions',
    'Other',
];

$pageTitle = 'Apply as Advisor';
$activeNav = '';
$cssPath   = BASE_URL . '/src/output.css';
?>
<?php include __DIR__ . '/../includes/head.php'; ?>

<main class="min-h-screen pt-[60px] px-4 py-12 bg-[#F8F9FA]">
  <div class="max-w-2xl mx-auto">

    <!-- Page header -->
    <div class="text-center mb-8">
      <p class="font-manrope font-bold text-xs tracking-[2px] uppercase text-[#A16207] mb-2">Join Our Expert Network</p>
      <h1 class="font-newsreader font-bold text-4xl text-[#031632]">Apply as an Advisor</h1>
      <p class="font-manrope text-[#64748B] mt-3 text-sm max-w-lg mx-auto">
        Share your expertise with thousands of aspiring students. Applications are reviewed within 2–3 business days.
      </p>
    </div>

    <!-- Steps indicator -->
    <div class="flex items-center justify-center gap-2 mb-8">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-full bg-[#031632] text-white text-xs font-bold flex items-center justify-center">1</div>
        <span class="font-manrope text-xs font-semibold text-[#031632]">Account</span>
      </div>
      <div class="w-8 h-px bg-[#D1D5DB]"></div>
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-full bg-[#031632] text-white text-xs font-bold flex items-center justify-center">2</div>
        <span class="font-manrope text-xs font-semibold text-[#031632]">Profile</span>
      </div>
      <div class="w-8 h-px bg-[#D1D5DB]"></div>
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 rounded-full bg-[#D1D5DB] text-[#9CA3AF] text-xs font-bold flex items-center justify-center">3</div>
        <span class="font-manrope text-xs text-[#9CA3AF]">Review</span>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">

      <div class="px-8 py-8">

        <?= render_flash() ?>

        <?php if ($errors): ?>
          <div class="alert-error mb-6">
            <?php foreach ($errors as $e): ?>
              <p class="flex items-center gap-1 text-sm"><i class="ri-error-warning-line flex-shrink-0"></i><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate class="flex flex-col gap-6">
          <?= csrf_field() ?>

          <!-- ── SECTION 1: Account Details ─────────────────── -->
          <div>
            <div class="flex items-center gap-3 mb-4 pb-3 border-b border-[#F1F5F9]">
              <div class="w-8 h-8 bg-[#031632] rounded-full flex items-center justify-center">
                <i class="ri-user-line text-white text-sm"></i>
              </div>
              <h2 class="font-newsreader font-semibold text-lg text-[#0F172A]">Account Details</h2>
            </div>

            <div class="grid grid-cols-1 gap-4">
              <div class="flex flex-col gap-1">
                <label for="full_name" class="font-manrope font-medium text-sm text-[#374151]">Full Name <span class="text-red-500">*</span></label>
                <input type="text" id="full_name" name="full_name"
                  value="<?= htmlspecialchars($values['full_name'] ?? '') ?>"
                  placeholder="Dr. Karim Al-Hassan"
                  class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm w-full" />
              </div>

              <div class="flex flex-col gap-1">
                <label for="email" class="font-manrope font-medium text-sm text-[#374151]">Email Address <span class="text-red-500">*</span></label>
                <input type="email" id="email" name="email"
                  value="<?= htmlspecialchars($values['email'] ?? '') ?>"
                  placeholder="your@email.com"
                  class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm w-full" />
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                  <label for="password" class="font-manrope font-medium text-sm text-[#374151]">Password <span class="text-red-500">*</span></label>
                  <div class="relative">
                    <input type="password" id="password" name="password"
                      placeholder="Min. 8 chars"
                      class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm w-full pr-9" />
                    <button type="button" onclick="togglePw('password', this)"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9CA3AF]">
                      <i class="ri-eye-off-line text-sm"></i>
                    </button>
                  </div>
                </div>
                <div class="flex flex-col gap-1">
                  <label for="password_confirm" class="font-manrope font-medium text-sm text-[#374151]">Confirm Password <span class="text-red-500">*</span></label>
                  <div class="relative">
                    <input type="password" id="password_confirm" name="password_confirm"
                      placeholder="Repeat password"
                      class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm w-full pr-9" />
                    <button type="button" onclick="togglePw('password_confirm', this)"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9CA3AF]">
                      <i class="ri-eye-off-line text-sm"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── SECTION 2: Professional Profile ───────────── -->
          <div>
            <div class="flex items-center gap-3 mb-4 pb-3 border-b border-[#F1F5F9]">
              <div class="w-8 h-8 bg-[#775A19] rounded-full flex items-center justify-center">
                <i class="ri-briefcase-line text-white text-sm"></i>
              </div>
              <h2 class="font-newsreader font-semibold text-lg text-[#0F172A]">Professional Profile</h2>
            </div>

            <div class="flex flex-col gap-4">

              <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                  <label for="specialization" class="font-manrope font-medium text-sm text-[#374151]">Specialization <span class="text-red-500">*</span></label>
                  <select id="specialization" name="specialization"
                    class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm w-full bg-white">
                    <option value="">Select area...</option>
                    <?php foreach ($specializationOptions as $opt): ?>
                      <option value="<?= $opt ?>" <?= ($values['specialization'] ?? '') === $opt ? 'selected' : '' ?>>
                        <?= $opt ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="flex flex-col gap-1">
                  <label for="experience_yrs" class="font-manrope font-medium text-sm text-[#374151]">Years of Experience <span class="text-red-500">*</span></label>
                  <input type="number" id="experience_yrs" name="experience_yrs"
                    min="0" max="50"
                    value="<?= (int)($values['experience_yrs'] ?? 0) ?>"
                    class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm w-full" />
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <label for="qualifications" class="font-manrope font-medium text-sm text-[#374151]">
                  Qualifications & Credentials <span class="text-red-500">*</span>
                </label>
                <textarea id="qualifications" name="qualifications" rows="3"
                  placeholder="E.g. PhD in Education, Oxford. Former IELTS examiner with British Council. Certified university admissions consultant…"
                  class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm w-full resize-none"><?= htmlspecialchars($values['qualifications'] ?? '') ?></textarea>
                <p class="text-xs text-[#9CA3AF]">Min. 20 characters</p>
              </div>

              <div class="flex flex-col gap-1">
                <label for="bio" class="font-manrope font-medium text-sm text-[#374151]">
                  Bio / About You <span class="text-red-500">*</span>
                </label>
                <textarea id="bio" name="bio" rows="4"
                  placeholder="Tell students about your background, approach, and why you're passionate about educational consulting…"
                  class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm w-full resize-none"><?= htmlspecialchars($values['bio'] ?? '') ?></textarea>
                <p class="text-xs text-[#9CA3AF]">Min. 30 characters. This will appear on your public profile.</p>
              </div>

              <div class="flex flex-col gap-1">
                <label for="linkedin_url" class="font-manrope font-medium text-sm text-[#374151]">
                  LinkedIn Profile <span class="text-[#9CA3AF] font-normal">(optional)</span>
                </label>
                <input type="url" id="linkedin_url" name="linkedin_url"
                  value="<?= htmlspecialchars($values['linkedin_url'] ?? '') ?>"
                  placeholder="https://linkedin.com/in/yourname"
                  class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm w-full" />
              </div>

            </div>
          </div>

          <!-- Terms -->
          <label class="flex items-start gap-2 cursor-pointer">
            <input type="checkbox" name="terms" required class="accent-[#775A19] mt-0.5 flex-shrink-0" />
            <span class="font-manrope text-sm text-[#374151]">
              I confirm that all information provided is accurate and I agree to the
              <a href="<?= BASE_URL ?>/terms.html" class="text-[#A16207] hover:underline">Terms of Service</a>
              and
              <a href="<?= BASE_URL ?>/advisor-code.html" class="text-[#A16207] hover:underline">Advisor Code of Conduct</a>
            </span>
          </label>

          <!-- Application notice -->
          <div class="alert-info text-sm">
            <i class="ri-information-line mr-1"></i>
            Your application will be reviewed by our admin team. Upon approval, you'll receive an email and can sign in to access your Advisor Dashboard.
          </div>

          <button type="submit"
            class="btn-gold w-full py-3 rounded-md font-manrope font-semibold text-sm tracking-wide">
            Submit Application <i class="ri-send-plane-line ml-1"></i>
          </button>

        </form>

        <p class="text-center mt-6 font-manrope text-sm text-[#6B7280]">
          Already have an account?
          <a href="<?= BASE_URL ?>/auth/signIn.php" class="text-[#A16207] font-semibold hover:underline">Sign In</a>
        </p>

      </div>
    </div>

    <p class="text-center mt-6 font-manrope text-sm text-[#9CA3AF]">
      <a href="<?= BASE_URL ?>/index.html" class="hover:text-[#374151] transition-colors">
        <i class="ri-arrow-left-line"></i> Back to home
      </a>
    </p>
  </div>
</main>

<script>
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  const icon = btn.querySelector('i');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  icon.className = inp.type === 'password' ? 'ri-eye-off-line text-sm' : 'ri-eye-line text-sm';
}
</script>
</body>
</html>