<?php
// auth/signUp.php — Student registration
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
            'full_name'        => sanitize($_POST['full_name'] ?? ''),
            'email'            => sanitize($_POST['email'] ?? ''),
            'password'         => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
        ];

        // Validation
        if (!$values['full_name'])           $errors[] = 'Full name is required.';
        if (!$values['email'])               $errors[] = 'Email address is required.';
        elseif (!validate_email($values['email'])) $errors[] = 'Please enter a valid email.';
        if (!$values['password'])            $errors[] = 'Password is required.';
        elseif (!validate_password($values['password']))
            $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and a number.';
        if ($values['password'] !== $values['password_confirm'])
            $errors[] = 'Passwords do not match.';
        if (!isset($_POST['terms']))
            $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';

        if (empty($errors)) {
            $pdo = getDB();

            // Check email uniqueness
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$values['email']]);
            if ($check->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                $hash = password_hash($values['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                $ins  = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)'
                );
                $ins->execute([$values['full_name'], $values['email'], $hash, 'user', 'active']);

                flash('success', 'Account created successfully! Please sign in.');
                redirect(BASE_URL . '/auth/signIn.php');
            }
        }
    }
}

$pageTitle = 'Create Account';
$activeNav = '';
$authPage  = 'signup';
$cssPath   = BASE_URL . '/src/output.css';
?>
<?php include __DIR__ . '/../includes/head.php'; ?>

  <!-- MAIN -->
  <main class="flex-1 flex justify-center items-center px-8 pt-[80px] pb-[40px] min-h-[750px]">
    <div class="w-full max-w-[1100px]">

      <?= render_flash() ?>

      <?php if ($errors): ?>
        <div class="alert-error mb-4 max-w-[1100px] mx-auto">
          <?php foreach ($errors as $e): ?>
            <p class="flex items-center gap-1 text-sm"><i class="ri-error-warning-line flex-shrink-0"></i><?= htmlspecialchars($e) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="flex w-full min-h-[600px] shadow-[0_25px_50px_-12px_rgba(0,0,0,0.2)] overflow-hidden rounded-xl">

        <!-- LEFT PANEL -->
        <div class="relative flex-1 min-w-0 signin-panel-bg overflow-hidden hidden md:block">
          <div class="absolute inset-0 bg-[rgba(3,22,50,0.42)]"></div>
          <div class="absolute inset-0 bg-gradient-to-t from-[rgba(3,22,50,0.88)] via-[rgba(3,22,50,0.08)] to-transparent"></div>
          <div class="absolute left-10 right-10 bottom-11 flex flex-col gap-4">
            <h2 class="font-newsreader font-normal text-[28px] leading-[36px] text-white">
              Begin your journey towards academic distinction.
            </h2>
            <p class="font-manrope font-light text-[13px] leading-[1.65] text-white/70">
              Access curated programs, prestigious scholarships, and a global network of institutional partners.
            </p>
            <div class="flex items-center gap-3 mt-1">
              <div class="w-9 h-px bg-[#FFDEA5] shrink-0"></div>
              <span class="font-manrope font-bold text-[10px] tracking-[1.2px] uppercase text-[#FFDEA5]">
                Scholar Verified Network
              </span>
            </div>
          </div>
        </div>

        <!-- RIGHT PANEL -->
        <div class="relative flex-1 min-w-0 bg-white flex flex-col justify-center px-14 overflow-y-auto py-10">

          <!-- Heading -->
          <div class="flex flex-col gap-1 mb-5">
            <h1 class="font-newsreader font-normal text-[30px] leading-[36px] text-[#031632]">
              Create your account
            </h1>
            <p class="font-manrope font-normal text-sm leading-5 text-[#44474D]">
              Join the curated community of intellectual leaders.
            </p>
          </div>

          <form method="POST" action="" novalidate class="flex flex-col gap-4">
            <?= csrf_field() ?>

            <!-- Full Name -->
            <div class="flex flex-col gap-1">
              <label for="full_name" class="font-manrope font-bold text-[11px] leading-4 tracking-[1.2px] uppercase text-[#75777E]">
                Full Name
              </label>
              <input
                type="text"
                id="full_name"
                name="full_name"
                value="<?= htmlspecialchars($values['full_name'] ?? '') ?>"
                placeholder="Julius Emerson"
                required
                autocomplete="name"
                class="field-input"
              />
            </div>

            <!-- Email -->
            <div class="flex flex-col gap-1">
              <label for="email" class="font-manrope font-bold text-[11px] leading-4 tracking-[1.2px] uppercase text-[#75777E]">
                Academic Email
              </label>
              <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($values['email'] ?? '') ?>"
                placeholder="scholar@university.edu"
                required
                autocomplete="email"
                class="field-input"
              />
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-1">
              <label for="password" class="font-manrope font-bold text-[11px] leading-4 tracking-[1.2px] uppercase text-[#75777E]">
                Password
              </label>
              <div class="flex items-center border-b border-[#C5C6CE] pb-2 pt-2 gap-2">
                <input
                  id="password"
                  name="password"
                  type="password"
                  placeholder="••••••••"
                  required
                  autocomplete="new-password"
                  class="field-row-input"
                />
                <button
                  type="button"
                  onclick="var i=document.getElementById('password');i.type=i.type==='password'?'text':'password'"
                  class="shrink-0 opacity-40 hover:opacity-70 transition-opacity"
                  aria-label="Toggle password"
                >
                  <img src="https://cdn.jsdelivr.net/npm/lucide-static@0.469.0/icons/eye.svg" class="w-4 h-4" alt="show" />
                </button>
              </div>
              <!-- Strength bar -->
              <div id="pw-strength" class="flex gap-1 mt-1 h-1">
                <div class="flex-1 rounded bg-[#E5E7EB]" id="s1"></div>
                <div class="flex-1 rounded bg-[#E5E7EB]" id="s2"></div>
                <div class="flex-1 rounded bg-[#E5E7EB]" id="s3"></div>
                <div class="flex-1 rounded bg-[#E5E7EB]" id="s4"></div>
              </div>
              <p id="pw-hint" class="text-xs text-[#9CA3AF] mt-0.5"></p>
            </div>

            <!-- Confirm Password -->
            <div class="flex flex-col gap-1">
              <label for="password_confirm" class="font-manrope font-bold text-[11px] leading-4 tracking-[1.2px] uppercase text-[#75777E]">
                Confirm Password
              </label>
              <div class="flex items-center border-b border-[#C5C6CE] pb-2 pt-2 gap-2">
                <input
                  id="password_confirm"
                  name="password_confirm"
                  type="password"
                  placeholder="••••••••"
                  required
                  autocomplete="new-password"
                  class="field-row-input"
                />
                <button
                  type="button"
                  onclick="var i=document.getElementById('password_confirm');i.type=i.type==='password'?'text':'password'"
                  class="shrink-0 opacity-40 hover:opacity-70 transition-opacity"
                  aria-label="Toggle password"
                >
                  <img src="https://cdn.jsdelivr.net/npm/lucide-static@0.469.0/icons/eye.svg" class="w-4 h-4" alt="show" />
                </button>
              </div>
              <p id="match-hint" class="text-xs mt-0.5 hidden"></p>
            </div>

            <!-- Terms -->
            <label class="flex items-start gap-2 cursor-pointer">
              <input type="checkbox" name="terms" required class="accent-[#775A19] mt-0.5 flex-shrink-0" />
              <span class="font-manrope text-sm text-[#44474D]">
                I agree to the
                <a href="<?= BASE_URL ?>/terms.php" class="text-[#775A19] hover:text-[#A16207] hover:underline">Terms of Service</a>
                and
                <a href="<?= BASE_URL ?>/privacy.php" class="text-[#775A19] hover:text-[#A16207] hover:underline">Privacy Policy</a>
              </span>
            </label>

            <!-- Actions -->
            <div class="flex flex-col gap-3 mt-1">

              <!-- CTA -->
              <button
                type="submit"
                class="flex items-center justify-center gap-2 w-full h-[46px] px-6 bg-[#031632] rounded-md font-manrope font-bold text-sm text-white hover:opacity-85 transition-opacity cursor-pointer"
              >
                Create Account &rarr;
              </button>

              <!-- Divider -->
              <div class="signin-divider w-full py-1">
                <span class="px-4 bg-white font-manrope font-bold text-[10px] leading-4 tracking-[2px] uppercase text-[#A0A2AA] whitespace-nowrap">
                  or continue with
                </span>
              </div>

              <!-- Social buttons -->
              <div class="flex gap-3">
                <a href="<?= BASE_URL ?>/auth/oauth/google.php" class="flex items-center justify-center gap-2.5 flex-1 h-[41px] border border-[#C5C6CE] rounded-md font-manrope font-semibold text-sm text-[#031632] hover:bg-[#F8F9FA] transition-colors">
                  <img src="https://www.google.com/favicon.ico" alt="Google" class="w-4 h-4" />
                  Google
                </a>
                <a href="<?= BASE_URL ?>/auth/oauth/linkedin.php" class="flex items-center justify-center gap-2.5 flex-1 h-[41px] border border-[#C5C6CE] rounded-md font-manrope font-semibold text-sm text-[#031632] hover:bg-[#F8F9FA] transition-colors">
                  <img
                    src="https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/linkedin.svg"
                    alt="LinkedIn"
                    class="w-4 h-4"
                    style="filter: invert(33%) sepia(98%) saturate(600%) hue-rotate(175deg) brightness(90%) contrast(101%);"
                  />
                  LinkedIn
                </a>
              </div>

              <!-- Sign in link -->
              <p class="text-center font-manrope font-normal text-sm text-[#44474D]">
                Already have an account?
                <a href="<?= BASE_URL ?>/auth/signIn.php" class="font-semibold text-[#775A19] hover:text-[#A16207] transition-colors">Sign In</a>
              </p>
              <p class="text-center font-manrope font-normal text-sm text-[#44474D] -mt-2">
                Are you an education expert?
                <a href="<?= BASE_URL ?>/auth/advisor-signup.php" class="font-semibold text-[#031632] hover:text-[#1A2B48] transition-colors">Apply as Advisor</a>
              </p>

            </div>
          </form>

        </div>

      </div>
    </div>
  </main>

  <!-- FOOTER -->
  <footer class="w-full bg-[#F8FAFC] border-t border-[#E2E8F0] py-12">
    <div class="max-w-[1280px] mx-auto px-8">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8">

        <div class="flex flex-col gap-4">
          <p class="font-newsreader font-bold text-lg text-[#0F172A]">The Editorial Scholar</p>
          <p class="font-manrope font-normal text-sm leading-5 tracking-[0.35px] text-[#64748B]">
            &copy; 2026 The Editorial Scholar.<br/>Curating Global Futures.
          </p>
        </div>

        <div class="flex flex-col gap-3">
          <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Company</p>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">About Us</a>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Contact Support</a>
        </div>

        <div class="flex flex-col gap-3">
          <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Legal</p>
          <a href="<?= BASE_URL ?>/terms.php" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Terms of Service</a>
          <a href="<?= BASE_URL ?>/privacy.php" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Privacy Policy</a>
          <a href="#" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Academic Integrity</a>
        </div>

        <div class="flex flex-col gap-3">
          <p class="font-manrope font-bold text-sm tracking-[1.4px] uppercase text-[#0F172A] mb-2">Social</p>
          <div class="flex items-center gap-4 mt-1">
            <a href="#" class="opacity-50 hover:opacity-100 transition-opacity">
              <img src="https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/x.svg" alt="X / Twitter" class="w-4 h-4" style="filter: invert(30%) sepia(5%) saturate(500%) hue-rotate(180deg);" />
            </a>
            <a href="#" class="opacity-50 hover:opacity-100 transition-opacity">
              <img src="https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/linkedin.svg" alt="LinkedIn" class="w-4 h-4" style="filter: invert(33%) sepia(98%) saturate(600%) hue-rotate(175deg) brightness(90%) contrast(101%);" />
            </a>
            <a href="#" class="opacity-50 hover:opacity-100 transition-opacity">
              <img src="https://cdn.jsdelivr.net/npm/simple-icons@v11/icons/instagram.svg" alt="Instagram" class="w-4 h-4" style="filter: invert(30%) sepia(5%) saturate(500%) hue-rotate(180deg);" />
            </a>
          </div>
        </div>

      </div>
    </div>
  </footer>

<script>
function togglePw(id) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

// Password strength
const pwInput = document.getElementById('password');
const bars    = [document.getElementById('s1'), document.getElementById('s2'), document.getElementById('s3'), document.getElementById('s4')];
const hint    = document.getElementById('pw-hint');

pwInput.addEventListener('input', () => {
  const v = pwInput.value;
  let score = 0;
  if (v.length >= 8)          score++;
  if (/[A-Z]/.test(v))        score++;
  if (/[0-9]/.test(v))        score++;
  if (/[^a-zA-Z0-9]/.test(v)) score++;

  const colors = ['#EF4444','#F59E0B','#3B82F6','#10B981'];
  const labels = ['Weak','Fair','Good','Strong'];
  bars.forEach((b, i) => {
    b.style.background = i < score ? colors[score - 1] : '#E5E7EB';
  });
  hint.textContent = v.length ? (labels[score - 1] || '') : '';
  hint.style.color = score > 0 ? colors[score - 1] : '#9CA3AF';
});

// Confirm match
const confInput = document.getElementById('password_confirm');
const matchHint = document.getElementById('match-hint');
confInput.addEventListener('input', () => {
  if (!confInput.value) { matchHint.classList.add('hidden'); return; }
  matchHint.classList.remove('hidden');
  if (pwInput.value === confInput.value) {
    matchHint.textContent = '✓ Passwords match';
    matchHint.style.color = '#10B981';
  } else {
    matchHint.textContent = '✗ Passwords do not match';
    matchHint.style.color = '#EF4444';
  }
});
</script>
</body>
</html>