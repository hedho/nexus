<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($USER) go('/');
if (cfg('allow_reg','1') !== '1') $disabled = true;

$errs = [];

// IMPORTANT: Only generate a NEW captcha on GET requests (page load).
// On POST requests, verify FIRST against the stored session answer,
// then generate a new one only if needed for re-display.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $captcha = captcha_generate();
} else {
    // On POST: we don't overwrite the session answer yet.
    // captcha_verify() will read and unset it.
    $captcha = ['q' => ''];  // placeholder, overwritten below if needed
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($disabled)) {
    if (!csrf_ok()) {
        $errs[]  = 'Invalid request. Please refresh and try again.';
        $captcha = captcha_generate();
    } else {
        $name  = sanitise(post('username'));
        $email = sanitise(post('email'));
        $pass  = post('password');
        $pass2 = post('password2');
        $cap   = post('captcha');

        // Verify captcha FIRST before anything else
        if (!captcha_verify($cap)) {
            $errs[]  = 'Incorrect answer to the security question. Please try again.';
            $captcha = captcha_generate(); // generate fresh question for retry
        }

        // Only run other validation if captcha passed
        if (!$errs) {
            if (strlen($name) < 3 || strlen($name) > 30)          $errs[] = 'Username must be 3–30 characters.';
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name))         $errs[] = 'Username: letters, numbers, _ and - only.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))         $errs[] = 'Invalid email address.';
            if (strlen($pass) < 8)                                  $errs[] = 'Password must be at least 8 characters.';
            if ($pass !== $pass2)                                   $errs[] = 'Passwords do not match.';
        }

        if (!$errs) {
            if (DB::row('SELECT id FROM users WHERE username=? OR email=?', [$name, $email])) {
                $errs[] = 'Username or email is already taken.';
                $captcha = captcha_generate(); // fresh question after failed attempt
            } else {
                $id = DB::insert(
                    'INSERT INTO users (username, email, password) VALUES (?, ?, ?)',
                    [$name, $email, password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])]
                );
                addon_hook('after_user_registered', ['user_id'=>$id,'username'=>$name,'email'=>$email]);
                login_user($id);
                go('/');
            }
        }

        // If we have errors but captcha already passed (errs from other fields),
        // generate a fresh captcha for the re-shown form
        if ($errs && empty(array_filter($errs, fn($e) => str_contains($e, 'security')))) {
            if (!isset($captcha['q']) || $captcha['q'] === '') {
                $captcha = captcha_generate();
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign Up — <?= e(cfg('site_name','Nexus Forum')) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
</head>
<body class="auth-body">
<div class="auth-page">
  <div class="auth-card">
    <a href="<?= u('/') ?>" class="auth-logo">
      <div class="logo-mark"><?= e(substr(cfg('site_name','N'),0,1)) ?></div>
      <span><?= e(cfg('site_name','Nexus Forum')) ?></span>
    </a>
    <h1>Create an account</h1>
    <p class="auth-sub">Join the community</p>

    <?php if (!empty($errs)): ?>
      <div class="alert err"><?= implode('<br>', array_map('e', $errs)) ?></div>
    <?php endif; ?>

    <?php if (isset($disabled)): ?>
      <div class="alert warn">Registration is currently disabled.</div>
    <?php else: ?>
    <form method="POST" autocomplete="off">
      <?= csrf_input() ?>
      <div class="fg">
        <label>Username</label>
        <input type="text" name="username" class="fi" required autofocus
               value="<?= e($_POST['username'] ?? '') ?>"
               placeholder="your_username" minlength="3" maxlength="30">
        <span class="hint">Letters, numbers, _ and - only</span>
      </div>
      <div class="fg">
        <label>Email</label>
        <input type="email" name="email" class="fi" required
               value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com">
      </div>
      <div class="fg">
        <label>Password</label>
        <div class="pw-row">
          <input type="password" id="pw1" name="password" class="fi" required
                 placeholder="Min. 8 characters" minlength="8">
          <button type="button" class="pw-eye" onclick="togglePwd('pw1')">👁</button>
        </div>
        <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
        <span class="hint" id="pwHint"></span>
      </div>
      <div class="fg">
        <label>Confirm Password</label>
        <div class="pw-row">
          <input type="password" id="pw2" name="password2" class="fi" required
                 placeholder="Repeat password">
          <button type="button" class="pw-eye" onclick="togglePwd('pw2')">👁</button>
        </div>
      </div>

      <!-- Math Captcha -->
      <div class="captcha-box">
        <div class="captcha-label">
          🔒 Security check — What is
          <strong class="captcha-q"><?= e($captcha['q']) ?></strong>
        </div>
        <input type="number" name="captcha" class="fi captcha-input"
               required placeholder="Your answer" autocomplete="off">
        <span class="hint">Solve this simple math problem to continue</span>
      </div>

      <button type="submit" class="btn-primary btn-block" style="margin-top:16px">
        Create Account
      </button>
    </form>
    <?php endif; ?>
    <p class="auth-foot">Have an account? <a href="<?= u('auth/login.php') ?>">Sign in →</a></p>
  </div>
  <p class="auth-back"><a href="<?= u('/') ?>">← Back to forum</a></p>
</div>
<script>
function togglePwd(id) {
  var e = document.getElementById(id);
  e.type = e.type === 'password' ? 'text' : 'password';
}
var pw = document.getElementById('pw1');
if (pw) pw.addEventListener('input', function() {
  var p=this.value, f=document.getElementById('pwFill'), h=document.getElementById('pwHint'), s=0;
  if (p.length >= 8)  s++;
  if (p.length >= 12) s++;
  if (/[A-Z]/.test(p)) s++;
  if (/[0-9]/.test(p)) s++;
  if (/[^A-Za-z0-9]/.test(p)) s++;
  f.style.width = (s / 5 * 100) + '%';
  var cols = ['','#ef4444','#f59e0b','#f59e0b','#22c55e','#22c55e'];
  f.style.background = cols[s] || '#22c55e';
  h.textContent = ['','Weak','Fair','Good','Strong','Very strong'][s] || '';
  h.style.color = f.style.background;
});
</script>
</body>
</html>
