<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($USER) go('/');

$err = '';
$next = get('next', u('/'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $err = 'Invalid request. Please try again.'; }
    else {
        $login = post('login');
        $pass  = post('password');
        $row   = DB::row('SELECT * FROM users WHERE username=? OR email=?', [$login, $login]);
        if (!$row || !password_verify($pass, $row['password'])) {
            $err = 'Incorrect username or password.';
        } elseif ($row['suspended']) {
            $err = 'This account has been suspended.';
        } else {
            login_user((int)$row['id']);
            go(ltrim(str_replace(BASE, '', $next), '/') ?: '/');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Log In — <?= e(cfg('site_name','Nexus Forum')) ?></title>
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
    <h1>Welcome back</h1>
    <p class="auth-sub">Sign in to your account</p>
    <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
    <form method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="fg">
        <label for="login">Username or Email</label>
        <input type="text" id="login" name="login" class="fi" required autofocus
               value="<?= e($_POST['login'] ?? '') ?>" placeholder="your_username">
      </div>
      <div class="fg">
        <label for="password">Password</label>
        <div class="pw-row">
          <input type="password" id="password" name="password" class="fi" required placeholder="••••••••">
          <button type="button" class="pw-eye" onclick="togglePwd('password')">👁</button>
        </div>
      </div>
      <button type="submit" class="btn-primary btn-block">Sign In</button>
    </form>
    <p class="auth-foot">No account? <a href="<?= u('auth/register.php') ?>">Sign up →</a></p>
  </div>
  <p class="auth-back"><a href="<?= u('/') ?>">← Back to forum</a></p>
</div>
<script>function togglePwd(id){var e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}</script>
</body></html>
