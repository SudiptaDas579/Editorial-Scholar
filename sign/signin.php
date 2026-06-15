<?php
// auth/signin.php
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../config/db.php';

// Already logged in → redirect to own dashboard
if (is_logged_in()) {
    redirect('/dashboard/' . $_SESSION['role'] . '.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {

        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $errors[] = 'Email and password are required.';
        } elseif (!validate_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Invalid email or password.';
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'Your account is currently suspended. Please contact support.';
            } else {
                // If advisor, check their application is approved
                if ($user['role'] === 'advisor') {
                    $appStmt = $pdo->prepare(
                        'SELECT status FROM advisor_applications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1'
                    );
                    $appStmt->execute([$user['id']]);
                    $app = $appStmt->fetch();

                    if (!$app || $app['status'] !== 'approved') {
                        $errors[] = 'Your advisor application is pending admin approval. We\'ll notify you by email.';
                    }
                }

                if (empty($errors)) {
                    // Update last login
                    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
                        ->execute([$user['id']]);

                    login_user($user);

                    $dest = match($user['role']) {
                        'admin'   => '/dashboard/admin.php',
                        'advisor' => '/dashboard/advisor.php',
                        default   => '/dashboard/user.php',
                    };
                    flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                    redirect($dest);
                }
            }
        }
    }
}

$pageTitle = 'Sign In';
$cssPath   = '../dist/output.css';
?>
<?php include __DIR__ . '/../includes/head.php'; ?>

<main class="min-h-screen flex items-center justify-center pt-[60px] px-4 py-12">
  <div class="w-full max-w-md">

    <!-- Card -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">

      <!-- Header band -->
      <div class="auth-gradient px-8 py-8 text-center">
        <p class="font-newsreader font-bold text-2xl text-white">The Editorial Scholar</p>
        <p class="font-manrope text-sm text-[#8293B5] mt-1">Sign in to your account</p>
      </div>

      <div class="px-8 py-8">

        <?= render_flash() ?>

        <?php if ($errors): ?>
          <div class="alert-error mb-4">
            <?php foreach ($errors as $e): ?>
              <p class="flex items-center gap-1"><i class="ri-error-warning-line"></i><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate class="flex flex-col gap-5">
          <?= csrf_field() ?>

          <!-- Email -->
          <div class="flex flex-col gap-1">
            <label for="email" class="font-manrope font-medium text-sm text-[#374151]">
              Email Address
            </label>
            <input
              type="email" id="email" name="email"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              placeholder="you@example.com"
              required autocomplete="email"
              class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm text-[#111827] placeholder-[#9CA3AF] w-full"
            />
          </div>

          <!-- Password -->
          <div class="flex flex-col gap-1">
            <div class="flex justify-between items-center">
              <label for="password" class="font-manrope font-medium text-sm text-[#374151]">Password</label>
              <a href="/auth/forgot-password.php" class="font-manrope text-xs text-[#A16207] hover:underline">Forgot password?</a>
            </div>
            <div class="relative">
              <input
                type="password" id="password" name="password"
                placeholder="••••••••"
                required autocomplete="current-password"
                class="input-focus border border-[#D1D5DB] rounded-md px-4 py-2.5 text-sm text-[#111827] placeholder-[#9CA3AF] w-full pr-10"
              />
              <button type="button" onclick="togglePw('password', this)"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] hover:text-[#374151]">
                <i class="ri-eye-off-line" id="pw-icon"></i>
              </button>
            </div>
          </div>

          <!-- Remember me -->
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="remember" class="accent-[#775A19]" />
            <span class="font-manrope text-sm text-[#374151]">Remember me for 30 days</span>
          </label>

          <!-- Submit -->
          <button type="submit"
            class="btn-primary w-full py-3 rounded-md font-manrope font-semibold text-sm tracking-wide mt-1">
            Sign In
          </button>
        </form>

        <!-- Divider -->
        <div class="flex items-center gap-3 my-6">
          <div class="flex-1 h-px bg-[#E5E7EB]"></div>
          <span class="font-manrope text-xs text-[#9CA3AF]">OR</span>
          <div class="flex-1 h-px bg-[#E5E7EB]"></div>
        </div>

        <!-- Register links -->
        <div class="flex flex-col gap-3 text-center">
          <p class="font-manrope text-sm text-[#6B7280]">
            Don't have an account?
            <a href="/auth/signup.php" class="text-[#A16207] font-semibold hover:underline">Register as Student</a>
          </p>
          <p class="font-manrope text-sm text-[#6B7280]">
            Want to join our team?
            <a href="/auth/advisor-signup.php" class="text-[#031632] font-semibold hover:underline">Register as Advisor</a>
          </p>
        </div>

      </div>
    </div>

    <!-- Back link -->
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
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'ri-eye-line';
  } else {
    inp.type = 'password';
    icon.className = 'ri-eye-off-line';
  }
}
</script>
</body>
</html>
