<?php
// auth/signup.php — Student registration
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../config/db.php';

if (is_logged_in()) {
    redirect('/dashboard/' . $_SESSION['role'] . '.php');
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
                redirect('/auth/signin.php');
            }
        }
    }
}

$pageTitle = 'Create Account';
$cssPath   = '../dist/output.css';
?>
<?php include __DIR__ . '/../includes/head.php'; ?>

<main class="min-h-screen flex items-center justify-center pt-[60px] px-4 py-12">
  <div class="w-full max-w-md">

    <div class="bg-white rounded-xl shadow-lg overflow-hidden">

      <!-- Header band -->
      <div class="auth-gradient px-8 py-8 text-center">
        <p class="font-newsreader font-bold text-2xl text-white">Create Your Account</p>
        <p class="font-manrope text-sm text-[#8293B5] mt-1">Join thousands of students curating their future</p>
      </div>

      <div class="px-8 py-8">

        <?= render_flash() ?>

        <?php if ($errors): ?>
          <div class="alert-error mb-4">
            <?php foreach ($errors as $e): ?>
              <p class="flex items-center gap-1 text-sm"><i class="ri-error-warning-line flex-shrink-0"></i><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate class="flex flex-col gap-5">
          <?= csrf_field() ?>

          <!-- Full Name -->
          <div class="flex flex-col gap-1">
            <label for="full_name" class="font-manrope font-medium text-sm text-[#374151]">Full Name</label>
            <input
              type="text" id="full_name" name="full_name"
              value="<?= htmlspecialchars($values['full_name'] ?? '') ?>"
              placeholder="Ayesha Rahman"
              required autocomplete="name"
              class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm text-[#111827] placeholder-[#9CA3AF] w-full"
            />
          </div>

          <!-- Email -->
          <div class="flex flex-col gap-1">
            <label for="email" class="font-manrope font-medium text-sm text-[#374151]">Email Address</label>
            <input
              type="email" id="email" name="email"
              value="<?= htmlspecialchars($values['email'] ?? '') ?>"
              placeholder="you@example.com"
              required autocomplete="email"
              class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm text-[#111827] placeholder-[#9CA3AF] w-full"
            />
          </div>

          <!-- Password -->
          <div class="flex flex-col gap-1">
            <label for="password" class="font-manrope font-medium text-sm text-[#374151]">Password</label>
            <div class="relative">
              <input
                type="password" id="password" name="password"
                placeholder="Min. 8 chars, upper, lower, number"
                required autocomplete="new-password"
                class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm text-[#111827] placeholder-[#9CA3AF] w-full pr-10"
              />
              <button type="button" onclick="togglePw('password', this)"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] hover:text-[#374151]">
                <i class="ri-eye-off-line"></i>
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
            <label for="password_confirm" class="font-manrope font-medium text-sm text-[#374151]">Confirm Password</label>
            <div class="relative">
              <input
                type="password" id="password_confirm" name="password_confirm"
                placeholder="Repeat your password"
                required autocomplete="new-password"
                class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm text-[#111827] placeholder-[#9CA3AF] w-full pr-10"
              />
              <button type="button" onclick="togglePw('password_confirm', this)"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] hover:text-[#374151]">
                <i class="ri-eye-off-line"></i>
              </button>
            </div>
            <p id="match-hint" class="text-xs mt-0.5 hidden"></p>
          </div>

          <!-- Terms -->
          <label class="flex items-start gap-2 cursor-pointer">
            <input type="checkbox" name="terms" required class="accent-[#775A19] mt-0.5 flex-shrink-0" />
            <span class="font-manrope text-sm text-[#374151]">
              I agree to the
              <a href="/terms.html" class="text-[#A16207] hover:underline">Terms of Service</a>
              and
              <a href="/privacy.html" class="text-[#A16207] hover:underline">Privacy Policy</a>
            </span>
          </label>

          <button type="submit"
            class="btn-primary w-full py-3 rounded-md font-manrope font-semibold text-sm tracking-wide mt-1">
            Create Account
          </button>
        </form>

        <div class="flex items-center gap-3 my-6">
          <div class="flex-1 h-px bg-[#E5E7EB]"></div>
          <span class="font-manrope text-xs text-[#9CA3AF]">OR</span>
          <div class="flex-1 h-px bg-[#E5E7EB]"></div>
        </div>

        <div class="flex flex-col gap-3 text-center">
          <p class="font-manrope text-sm text-[#6B7280]">
            Already have an account?
            <a href="/auth/signin.php" class="text-[#A16207] font-semibold hover:underline">Sign In</a>
          </p>
          <p class="font-manrope text-sm text-[#6B7280]">
            Are you an education expert?
            <a href="/auth/advisor-signup.php" class="text-[#031632] font-semibold hover:underline">Apply as Advisor</a>
          </p>
        </div>

      </div>
    </div>

    <p class="text-center mt-6 font-manrope text-sm text-[#9CA3AF]">
      <a href="/index.html" class="hover:text-[#374151] transition-colors">
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
  icon.className = inp.type === 'password' ? 'ri-eye-off-line' : 'ri-eye-line';
}

// Password strength
const pwInput = document.getElementById('password');
const bars    = [document.getElementById('s1'), document.getElementById('s2'), document.getElementById('s3'), document.getElementById('s4')];
const hint    = document.getElementById('pw-hint');

pwInput.addEventListener('input', () => {
  const v = pwInput.value;
  let score = 0;
  if (v.length >= 8)         score++;
  if (/[A-Z]/.test(v))      score++;
  if (/[0-9]/.test(v))      score++;
  if (/[^a-zA-Z0-9]/.test(v)) score++;

  const colors = ['#EF4444','#F59E0B','#3B82F6','#10B981'];
  const labels = ['Weak','Fair','Good','Strong'];
  bars.forEach((b, i) => {
    b.style.background = i < score ? colors[score - 1] : '#E5E7EB';
  });
  hint.textContent = v.length ? labels[score - 1] || '' : '';
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
