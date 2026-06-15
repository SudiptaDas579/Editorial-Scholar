<?php
// auth/signIn.php
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';

// Already logged in → redirect to own dashboard
if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard/' . $_SESSION['role'] . '.php');
}

$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {

        $values = [
            'email' => sanitize($_POST['email'] ?? ''),
        ];
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!$values['email'] || !$password) {
            $errors[] = 'Email and password are required.';
        } elseif (!validate_email($values['email'])) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$values['email']]);
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

                    // Note: "remember me" is currently UI-only and does not
                    // change session lifetime or set a persistent cookie.
                    unset($remember);

                    $dest = match ($user['role']) {
                        'admin'   => BASE_URL . '/dashboard/admin.php',
                        'advisor' => BASE_URL . '/dashboard/advisor.php',
                        default   => BASE_URL . '/dashboard/user.php',
                    };
                    flash('success', 'Welcome back, ' . $user['full_name'] . '!');
                    redirect($dest);
                }
            }
        }
    }
}

$pageTitle = 'Sign In';
$activeNav = '';
$authPage  = 'signin';
$cssPath   = BASE_URL . '/src/output.css';
?>
<?php include __DIR__ . '/../includes/head.php'; ?>

  <!-- MAIN -->
  <main class="flex-1 flex justify-center items-center px-8 pt-[80px] pb-[40px] min-h-[750px]">
    <div class="w-full max-w-[1024px]">

      <?= render_flash() ?>

      <?php if ($errors): ?>
        <div class="alert-error mb-4 max-w-[1024px] mx-auto">
          <?php foreach ($errors as $e): ?>
            <p class="flex items-center gap-1 text-sm"><i class="ri-error-warning-line flex-shrink-0"></i><?= htmlspecialchars($e) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="flex w-full h-[600px] min-h-[550px] shadow-[0_25px_50px_-12px_rgba(0,0,0,0.2)] overflow-hidden rounded-xl">

        <!-- LEFT PANEL -->
        <div class="relative flex-1 min-w-0 signin-panel-bg overflow-hidden hidden md:block">
          <div class="absolute inset-0 bg-[rgba(3,22,50,0.42)]"></div>
          <div class="absolute inset-0 bg-gradient-to-t from-[rgba(3,22,50,0.88)] via-[rgba(3,22,50,0.08)] to-transparent"></div>
          <div class="absolute left-10 right-10 bottom-11 flex flex-col gap-4">
            <p class="font-newsreader font-normal text-[28px] leading-[36px] text-white">
              "Education is the most powerful weapon which you can use to change the world."
            </p>
            <div class="flex items-center gap-3 mt-1">
              <div class="w-9 h-px bg-[#FFDEA5] shrink-0"></div>
              <span class="font-newsreader font-bold text-base text-white">
                The Editorial Scholar
              </span>
            </div>
          </div>
        </div>

        <!-- RIGHT PANEL -->
        <div class="relative flex-1 min-w-0 bg-white flex flex-col justify-center px-14 overflow-y-auto">

          <!-- Heading -->
          <div class="flex flex-col gap-1.5 mb-7">
            <h1 class="font-newsreader font-normal text-[34px] leading-[40px] text-[#031632]">
              Welcome Back
            </h1>
            <p class="font-manrope font-normal text-sm leading-5 text-[#44474D]">
              Continue your journey in curated global education.
            </p>
          </div>

          <form method="POST" action="" novalidate class="flex flex-col gap-5">
            <?= csrf_field() ?>

            <!-- Email -->
            <div class="flex flex-col gap-1">
              <label for="email" class="font-manrope font-bold text-[11px] leading-4 tracking-[1.2px] uppercase text-[#75777E]">
                Email Address
              </label>
              <input
                id="email"
                name="email"
                type="email"
                value="<?= htmlspecialchars($values['email'] ?? '') ?>"
                placeholder="scholar@institution.edu"
                required
                autocomplete="email"
                class="field-input"
              />
            </div>

            <!-- Password -->
            <div class="flex flex-col gap-1">
              <div class="flex justify-between items-center mb-0.5">
                <label for="password" class="font-manrope font-bold text-[11px] leading-4 tracking-[1.2px] uppercase text-[#75777E]">
                  Password
                </label>
                <a href="<?= BASE_URL ?>/auth/forgot-password.php" class="font-manrope font-bold text-[11px] leading-4 tracking-[1.2px] uppercase text-[#775A19] hover:text-[#A16207] transition-colors">
                  Forgot Password
                </a>
              </div>
              <div class="flex items-center border-b border-[#C5C6CE] pb-2 pt-2 gap-2">
                <input
                  id="password"
                  name="password"
                  type="password"
                  placeholder="••••••••"
                  required
                  autocomplete="current-password"
                  class="flex-1 border-none bg-transparent font-manrope text-[15px] text-[#031632] placeholder-[#B0B2B8] outline-none"
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
            </div>

            <!-- Remember me -->
            <label class="flex items-center gap-2 cursor-pointer -mt-1">
              <input type="checkbox" name="remember" class="accent-[#775A19]" />
              <span class="font-manrope text-sm text-[#44474D]">Remember me for 30 days</span>
            </label>

            <!-- Actions -->
            <div class="flex flex-col gap-3 mt-1">

              <!-- CTA -->
              <button
                type="submit"
                class="flex items-center justify-center gap-2 w-full h-[46px] px-6 bg-[#031632] rounded-md font-manrope font-bold text-sm text-white hover:opacity-85 transition-opacity cursor-pointer"
              >
                Sign In &rarr;
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

              <!-- Sign up link -->
              <p class="text-center font-manrope font-normal text-sm text-[#44474D]">
                New to The Editorial Scholar?
                <a href="<?= BASE_URL ?>/auth/signUp.php" class="font-semibold text-[#775A19] hover:text-[#A16207] transition-colors">Create an account</a>
              </p>
              <p class="text-center font-manrope font-normal text-sm text-[#44474D] -mt-2">
                Want to join our team?
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
          <a href="<?= BASE_URL ?>/terms.html" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Terms of Service</a>
          <a href="<?= BASE_URL ?>/privacy.html" class="font-manrope font-normal text-sm tracking-[0.35px] text-[#64748B] hover:text-[#0F172A] transition-colors">Privacy Policy</a>
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

</body>
</html>