<?php
/**
 * Nexus Forum — Web Installer
 * Supports: SQLite3, MySQL, MariaDB
 */

/* ── Bootstrap (standalone — does not use bootstrap.php) ─── */
define('NEXUS', true);
define('ROOT',  dirname(__DIR__));
define('DATA',  ROOT . '/data');
define('UPLOADS', ROOT . '/public/uploads');

// Detect web root offset (so the forum works at /forum/, /app/forum/, etc.)
$_dr = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$_rp = str_replace('\\', '/', ROOT);
$_b  = str_replace($_dr, '', $_rp);
$_b  = '/' . trim($_b, '/');
define('BASE', $_b === '/' ? '' : $_b);
unset($_dr, $_rp, $_b);

// Already installed → redirect
if (file_exists(DATA . '/installed.lock')) {
    header('Location: ' . BASE . '/');
    exit;
}

// Load DB class only (no bootstrap, no session)
require_once ROOT . '/includes/db.php';

/* ── State ──────────────────────────────────────────────── */
$step      = (int)($_GET['step'] ?? 1);
$errs      = [];
$info      = [];
$selDb     = $_POST['db_type'] ?? 'sqlite';

// Check which PDO drivers are available
$hasSQLite = extension_loaded('pdo_sqlite')  || in_array('sqlite', PDO::getAvailableDrivers());
$hasMySQL  = extension_loaded('pdo_mysql')   || in_array('mysql',  PDO::getAvailableDrivers());

/* ── Requirements ───────────────────────────────────────── */
$reqs = [
    'PHP 8.0+'                => version_compare(PHP_VERSION, '8.0', '>='),
    'PDO extension'           => extension_loaded('PDO'),
    'Cryptographic random'    => function_exists('random_int'),
    'JSON support'            => function_exists('json_encode'),
    'SQLite3 or MySQL driver' => $hasSQLite || $hasMySQL,
    'data/ directory writable'=> !is_dir(DATA) ? is_writable(ROOT) : is_writable(DATA),
];
$allOk = !in_array(false, $reqs);

/* ── Step 2: process form ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_submit'])) {

    // Re-read db choice from POST
    $selDb = $_POST['db_type'] ?? 'sqlite';

    // Validate common fields
    $siteName   = trim($_POST['site_name']   ?? '');
    $siteDesc   = trim($_POST['site_desc']   ?? '');
    $adminUser  = trim($_POST['admin_user']  ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_pass']  ?? '';
    $adminPass2 = $_POST['admin_pass2'] ?? '';

    if (!$siteName)                                             $errs[] = 'Site name is required.';
    if (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $adminUser))  $errs[] = 'Admin username must be 3–30 alphanumeric characters (letters, numbers, _ -)';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL))       $errs[] = 'Admin email is not valid.';
    if (strlen($adminPass) < 8)                                $errs[] = 'Admin password must be at least 8 characters.';
    if ($adminPass !== $adminPass2)                            $errs[] = 'Passwords do not match.';

    // Validate DB-specific fields
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = max(1, min(65535, (int)($_POST['db_port'] ?? 3306)));
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';

    if ($selDb === 'mysql') {
        if (!$hasMySQL)   $errs[] = 'PDO MySQL driver is not available on this server.';
        if (!$dbName)     $errs[] = 'Database name is required for MySQL.';
        if (!$dbUser)     $errs[] = 'Database username is required for MySQL.';

        // Test connection before proceeding
        if (!$errs) {
            try {
                $testPdo = new PDO(
                    "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
                    $dbUser, $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
                unset($testPdo);
            } catch (PDOException $e) {
                $errs[] = 'Could not connect to MySQL: ' . htmlspecialchars($e->getMessage());
            }
        }
    } else {
        if (!$hasSQLite) $errs[] = 'PDO SQLite driver is not available on this server. Please choose MySQL.';
    }

    // All good — run installation
    if (!$errs) {
        try {
            /* 1. Write db_config.php */
            $cfgPath = ROOT . '/includes/db_config.php';
            if ($selDb === 'mysql') {
                $cfg = "<?php\n"
                     . "if (!defined('NEXUS')) { http_response_code(403); exit('Forbidden'); }\n"
                     . "define('DB_DRIVER',  'mysql');\n"
                     . "define('DB_HOST',    " . var_export($dbHost,        true) . ");\n"
                     . "define('DB_PORT',    " . var_export((string)$dbPort, true) . ");\n"
                     . "define('DB_NAME',    " . var_export($dbName,         true) . ");\n"
                     . "define('DB_USER',    " . var_export($dbUser,         true) . ");\n"
                     . "define('DB_PASS',    " . var_export($dbPass,         true) . ");\n"
                     . "define('DB_CHARSET', 'utf8mb4');\n";
            } else {
                $cfg = "<?php\n"
                     . "if (!defined('NEXUS')) { http_response_code(403); exit('Forbidden'); }\n"
                     . "define('DB_DRIVER', 'sqlite');\n";
            }
            file_put_contents($cfgPath, $cfg);
            @chmod($cfgPath, 0640);

            /* 2. Build schema */
            DB::init();
            $db = DB::connect();

            /* 3. Insert settings */
            $upsert = DB::isMysql()
                ? 'INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)'
                : 'INSERT OR REPLACE INTO settings (key,value) VALUES (?,?)';
            $si = $db->prepare($upsert);
            $settingsToInsert = [
                'site_name'              => $siteName,
                'site_desc'              => $siteDesc ?: 'A community forum',
                'allow_reg'              => '1',
                'topics_per_page'        => '30',
                'posts_per_page'         => '20',
                'rate_limit_enabled'     => '0',
                'rate_limit_count'       => '3',
                'rate_limit_window'      => '60',
                'post_captcha_enabled'   => '0',
                'topic_captcha_enabled'  => '0',
            ];
            foreach ($settingsToInsert as $k => $v) {
                $si->execute([$k, $v]);
            }

            /* 4. Create admin user */
            $adminId = DB::insert(
                'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)',
                [$adminUser, $adminEmail, password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]), 'admin']
            );

            /* 5. Create default categories */
            $catStmt = $db->prepare('INSERT INTO categories (name, slug, description, color, icon, position) VALUES (?, ?, ?, ?, ?, ?)');
            $defaultCats = [
                ['Announcements', 'announcements', 'Important updates from the team',  '#ef4444', '📢', 1],
                ['General',       'general',       'Talk about anything',               '#3b82f6', '💬', 2],
                ['Support',       'support',       'Get help from the community',       '#10b981', '🛟', 3],
                ['Ideas',         'ideas',         'Share your suggestions',            '#8b5cf6', '💡', 4],
                ['Showcase',      'showcase',      'Show off your projects',            '#f59e0b', '🌟', 5],
            ];
            foreach ($defaultCats as $cat) {
                $catStmt->execute($cat);
            }

            /* 6. Create welcome topic in General */
            $generalId = (int) DB::val("SELECT id FROM categories WHERE slug = 'general'");
            if ($generalId) {
                $welcomeBody = "# Welcome to {$siteName}!\n\n"
                    . "This is your new community forum. Here's how to get started:\n\n"
                    . "## Quick Start\n\n"
                    . "- Browse the categories in the left sidebar\n"
                    . "- Click **+ New Topic** to start a discussion\n"
                    . "- Type **@username** in a post to mention someone\n"
                    . "- Paste a YouTube, Vimeo, or Spotify URL on its own line to auto-embed it\n"
                    . "- Like posts to give ⭐ Karma to helpful members\n\n"
                    . "Enjoy the community! 👋";

                $nowExpr = DB::isMysql() ? 'NOW()' : "datetime('now')";
                $topicId = DB::insert(
                    "INSERT INTO topics (title, slug, category_id, user_id, pinned, last_post_at)
                     VALUES (?, ?, ?, ?, 1, {$nowExpr})",
                    ["Welcome to {$siteName}!", 'welcome', $generalId, $adminId]
                );
                DB::insert(
                    'INSERT INTO posts (topic_id, user_id, content, post_num) VALUES (?, ?, ?, 1)',
                    [$topicId, $adminId, $welcomeBody]
                );
                DB::run('UPDATE categories SET topic_count = 1, post_count = 1 WHERE id = ?', [$generalId]);
            }

            /* 7. Create directories */
            foreach ([DATA, UPLOADS, UPLOADS . '/avatars'] as $dir) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }
            }

            /* 8. Security hardening */
            // Block web access to data/
            file_put_contents(DATA . '/.htaccess', "Order Deny,Allow\nDeny from all\n");

            // Block PHP execution in uploads/
            file_put_contents(UPLOADS . '/.htaccess',
                "Options -Indexes -ExecCGI\n"
                . "<FilesMatch \"\\.(?i:php|phtml|php3|php4|php5|php7|phar|cgi|pl|sh|exe)$\">\n"
                . "  Order Allow,Deny\n"
                . "  Deny from all\n"
                . "</FilesMatch>\n"
            );

            // Secure the SQLite file
            if (!DB::isMysql() && file_exists(DATA . '/forum.db')) {
                @chmod(DATA . '/forum.db', 0640);
            }

            /* 9. Write installed.lock */
            file_put_contents(DATA . '/installed.lock', json_encode([
                'installed_at' => date('c'),
                'db_driver'    => $selDb,
                'php_version'  => PHP_VERSION,
            ]));
            @chmod(DATA . '/installed.lock', 0640);

            /* Done */
            $step = 3;
            $info = ['username' => $adminUser, 'db' => $selDb];

        } catch (Throwable $ex) {
            $errs[] = 'Installation error: ' . htmlspecialchars($ex->getMessage());
            // Roll back config file if install failed
            if (isset($cfgPath) && file_exists($cfgPath)) {
                @unlink($cfgPath);
            }
        }
    }
}

/* ── HTML helpers ────────────────────────────────────────── */
function esc(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Install — Nexus Forum</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE ?>/public/css/main.css">
  <style>
    body {
      background: linear-gradient(135deg, #1e40af 0%, #0f172a 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      font-family: 'Inter', sans-serif;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      padding: 40px;
      max-width: 580px;
      width: 100%;
      box-shadow: 0 25px 60px rgba(0,0,0,.3);
    }
    .logo-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 32px;
    }
    .logo-mark {
      width: 46px;
      height: 46px;
      background: #3b82f6;
      color: #fff;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 700;
      flex-shrink: 0;
    }
    .logo-text { font-size: 20px; font-weight: 700; color: #0f172a; }
    .logo-sub  { font-size: 13px; color: #64748b; }

    /* Steps */
    .steps { display: flex; margin-bottom: 32px; }
    .step  { flex: 1; text-align: center; position: relative; }
    .step::after { content: ''; position: absolute; top: 14px; left: 50%; width: 100%; height: 2px; background: #e2e8f0; z-index: 0; }
    .step:last-child::after { display: none; }
    .step-n {
      width: 30px; height: 30px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 13px; font-weight: 700; margin: 0 auto 6px;
      position: relative; z-index: 1;
    }
    .step.done .step-n { background: #22c55e; color: #fff; }
    .step.active .step-n { background: #3b82f6; color: #fff; box-shadow: 0 0 0 4px #dbeafe; }
    .step.todo .step-n  { background: #e2e8f0; color: #94a3b8; }
    .step-label { font-size: 11px; font-weight: 600; color: #94a3b8; }
    .step.active .step-label { color: #3b82f6; }
    .step.done .step-label   { color: #22c55e; }

    /* Req table */
    .req-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    .req-row:last-child { border-bottom: none; }
    .ok   { color: #22c55e; font-weight: 700; }
    .fail { color: #ef4444; font-weight: 700; }

    /* DB switcher */
    .db-switch { display: flex; border: 2px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 18px; }
    .db-btn {
      flex: 1; padding: 14px 12px; text-align: center; cursor: pointer;
      background: #f8fafc; border: none; font-family: inherit;
      font-size: 14px; font-weight: 600; color: #64748b;
      transition: all .2s; line-height: 1.4;
    }
    .db-btn:first-child { border-right: 2px solid #e2e8f0; }
    .db-btn.active { background: #3b82f6; color: #fff; }
    .db-btn .db-sub { font-size: 11px; font-weight: 400; opacity: .75; display: block; margin-top: 2px; }
    .db-panel { display: none; }
    .db-panel.visible { display: block; }

    /* Notices */
    .notice { border-radius: 8px; padding: 11px 14px; font-size: 13px; margin-bottom: 14px; }
    .notice-green  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .notice-yellow { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .notice-blue   { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

    /* Form sections */
    .fsection { margin-bottom: 22px; }
    .fsection legend, .fsection-title {
      display: block; font-weight: 700; font-size: 13px; color: #374151;
      padding-bottom: 8px; border-bottom: 2px solid #f1f5f9; margin-bottom: 14px; width: 100%;
    }
    .fg { margin-bottom: 14px; }
    .fg label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 5px; }
    .fg label small { font-weight: 400; color: #94a3b8; }
    .fi {
      width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 8px;
      font-size: 14px; font-family: inherit; color: #111827; outline: none; transition: border-color .18s;
    }
    .fi:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
    .grid-2 { display: grid; grid-template-columns: 1fr 90px; gap: 10px; }

    /* Buttons */
    .btn-install {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; padding: 13px; background: #3b82f6; color: #fff; border: none;
      border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer;
      font-family: inherit; transition: all .2s; margin-top: 6px;
    }
    .btn-install:hover { background: #2563eb; transform: translateY(-1px); }
    .btn-next {
      display: flex; align-items: center; justify-content: center;
      width: 100%; padding: 12px; background: #3b82f6; color: #fff; border-radius: 10px;
      text-decoration: none; font-size: 15px; font-weight: 600; transition: all .2s;
    }
    .btn-next:hover { background: #2563eb; text-decoration: none; color: #fff; }

    /* Error alert */
    .alert-err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: 12px 16px; font-size: 14px; margin-bottom: 18px; }

    /* Success */
    .success-hero { text-align: center; padding: 10px 0 20px; }
    .success-hero .emoji { font-size: 4rem; line-height: 1; margin-bottom: 12px; }
    .success-hero h2 { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 6px; }
    .success-hero p  { color: #64748b; font-size: 15px; }
    .info-table { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 0; margin-bottom: 20px; overflow: hidden; }
    .info-row { display: flex; justify-content: space-between; align-items: center; padding: 11px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    .info-row:last-child { border-bottom: none; }
    .info-row .label { font-weight: 600; color: #374151; }
    .info-row code { background: #e2e8f0; padding: 2px 8px; border-radius: 5px; font-size: 13px; }
    .security-list { list-style: none; padding: 0; margin: 8px 0 0; }
    .security-list li { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; margin-bottom: 6px; line-height: 1.4; }
  </style>
</head>
<body>
<div class="card">

  <!-- Logo -->
  <div class="logo-row">
    <div class="logo-mark">N</div>
    <div>
      <div class="logo-text">Nexus Forum</div>
      <div class="logo-sub">Installation Wizard</div>
    </div>
  </div>

  <!-- Steps indicator -->
  <div class="steps">
    <?php
    $stepDefs = ['Requirements', 'Configure', 'Complete'];
    foreach ($stepDefs as $i => $label):
      $n = $i + 1;
      $cls = $step > $n ? 'done' : ($step === $n ? 'active' : 'todo');
    ?>
      <div class="step <?= $cls ?>">
        <div class="step-n"><?= $step > $n ? '✓' : $n ?></div>
        <div class="step-label"><?= $label ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($step === 1): /* ─── STEP 1: Requirements ─── */ ?>

    <h2 style="font-size:1.25rem;font-weight:700;margin-bottom:6px">System Check</h2>
    <p style="color:#64748b;font-size:14px;margin-bottom:20px">Checking your server before we begin.</p>

    <?php foreach ($reqs as $label => $ok): ?>
      <div class="req-row">
        <span><?= esc($label) ?></span>
        <span class="<?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? '✓ OK' : '✗ FAIL' ?></span>
      </div>
    <?php endforeach; ?>

    <div style="margin-top:8px;padding:10px 0;border-top:1px solid #f1f5f9">
      <div class="req-row">
        <span>SQLite3 (PDO)</span>
        <span class="<?= $hasSQLite ? 'ok' : 'fail' ?>"><?= $hasSQLite ? '✓ Available' : '✗ Not available' ?></span>
      </div>
      <div class="req-row">
        <span>MySQL / MariaDB (PDO)</span>
        <span class="<?= $hasMySQL ? 'ok' : 'fail' ?>"><?= $hasMySQL ? '✓ Available' : '✗ Not available' ?></span>
      </div>
    </div>

    <div style="margin-top:22px">
      <?php if ($allOk): ?>
        <a href="?step=2" class="btn-next">Continue to Configuration →</a>
      <?php else: ?>
        <div class="alert-err">Please fix the issues above, then reload this page.</div>
      <?php endif; ?>
    </div>

  <?php elseif ($step === 2): /* ─── STEP 2: Configure ─── */ ?>

    <h2 style="font-size:1.25rem;font-weight:700;margin-bottom:6px">Configure Your Forum</h2>
    <p style="color:#64748b;font-size:14px;margin-bottom:22px">Fill in all fields below and click <strong>Install</strong>.</p>

    <?php if ($errs): ?>
      <div class="alert-err">
        <?php foreach ($errs as $e): ?>
          <div>• <?= esc($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="?step=2">
      <input type="hidden" name="install_submit" value="1">
      <input type="hidden" name="db_type" id="dbTypeHidden" value="<?= esc($selDb) ?>">

      <!-- Database selection -->
      <div class="fsection">
        <span class="fsection-title">1. Database Engine</span>

        <div class="db-switch">
          <button type="button" class="db-btn <?= $selDb !== 'mysql' ? 'active' : '' ?>"
                  id="btnSQLite" onclick="switchDb('sqlite')">
            🗃️ SQLite3
            <span class="db-sub">Easiest — no setup needed</span>
          </button>
          <button type="button" class="db-btn <?= $selDb === 'mysql' ? 'active' : '' ?>"
                  id="btnMySQL" onclick="switchDb('mysql')">
            🐬 MySQL / MariaDB
            <span class="db-sub">Recommended for production</span>
          </button>
        </div>

        <!-- SQLite panel -->
        <div id="panelSQLite" class="db-panel <?= $selDb !== 'mysql' ? 'visible' : '' ?>">
          <?php if ($hasSQLite): ?>
            <div class="notice notice-green">
              ✓ SQLite will be automatically created at <code>data/forum.db</code>. No extra configuration needed.
            </div>
          <?php else: ?>
            <div class="notice notice-yellow">
              ⚠️ PDO SQLite is not available on this server. Please switch to MySQL.
            </div>
          <?php endif; ?>
        </div>

        <!-- MySQL panel -->
        <div id="panelMySQL" class="db-panel <?= $selDb === 'mysql' ? 'visible' : '' ?>">
          <?php if ($hasMySQL): ?>
            <div class="grid-2">
              <div class="fg">
                <label>Host</label>
                <input type="text" name="db_host" class="fi" value="<?= esc($_POST['db_host'] ?? '127.0.0.1') ?>" placeholder="127.0.0.1">
              </div>
              <div class="fg">
                <label>Port</label>
                <input type="number" name="db_port" class="fi" value="<?= esc($_POST['db_port'] ?? '3306') ?>" min="1" max="65535">
              </div>
            </div>
            <div class="fg">
              <label>Database Name</label>
              <input type="text" name="db_name" class="fi" value="<?= esc($_POST['db_name'] ?? '') ?>" placeholder="nexus_forum">
            </div>
            <div class="fg">
              <label>Database Username</label>
              <input type="text" name="db_user" class="fi" value="<?= esc($_POST['db_user'] ?? '') ?>" placeholder="nexus_user">
            </div>
            <div class="fg">
              <label>Database Password</label>
              <input type="password" name="db_pass" class="fi" placeholder="Your database password">
            </div>
            <div class="notice notice-blue" style="font-size:12px">
              <strong>Create the database first if you haven't:</strong><br>
              <code style="font-size:11px">CREATE DATABASE nexus_forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code>
            </div>
          <?php else: ?>
            <div class="notice notice-yellow">⚠️ PDO MySQL is not available on this server.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Site details -->
      <div class="fsection">
        <span class="fsection-title">2. Site Details</span>
        <div class="fg">
          <label>Site Name <span style="color:#ef4444">*</span></label>
          <input type="text" name="site_name" class="fi" required
                 value="<?= esc($_POST['site_name'] ?? 'My Forum') ?>" placeholder="My Community">
        </div>
        <div class="fg">
          <label>Description <small>(optional)</small></label>
          <input type="text" name="site_desc" class="fi"
                 value="<?= esc($_POST['site_desc'] ?? '') ?>" placeholder="A place for great discussions">
        </div>
      </div>

      <!-- Admin account -->
      <div class="fsection">
        <span class="fsection-title">3. Admin Account</span>
        <div class="fg">
          <label>Username <span style="color:#ef4444">*</span> <small>(letters, numbers, _ -)</small></label>
          <input type="text" name="admin_user" class="fi" required
                 value="<?= esc($_POST['admin_user'] ?? '') ?>" placeholder="admin"
                 minlength="3" maxlength="30" autocomplete="off">
        </div>
        <div class="fg">
          <label>Email Address <span style="color:#ef4444">*</span></label>
          <input type="email" name="admin_email" class="fi" required
                 value="<?= esc($_POST['admin_email'] ?? '') ?>" placeholder="admin@example.com">
        </div>
        <div class="fg">
          <label>Password <span style="color:#ef4444">*</span> <small>(min. 8 characters)</small></label>
          <input type="password" name="admin_pass" class="fi" required
                 minlength="8" placeholder="Choose a strong password" autocomplete="new-password">
        </div>
        <div class="fg">
          <label>Confirm Password <span style="color:#ef4444">*</span></label>
          <input type="password" name="admin_pass2" class="fi" required
                 minlength="8" placeholder="Repeat your password" autocomplete="new-password">
        </div>
      </div>

      <button type="submit" class="btn-install">
        🚀 Install Nexus Forum
      </button>
    </form>

  <?php elseif ($step === 3): /* ─── STEP 3: Done ─── */ ?>

    <div class="success-hero">
      <div class="emoji">🎉</div>
      <h2>Installation Complete!</h2>
      <p>Your forum is ready. Log in with your admin credentials below.</p>
    </div>

    <div class="info-table">
      <div class="info-row">
        <span class="label">Forum URL</span>
        <a href="<?= BASE ?>/"><?= BASE ?: '/' ?></a>
      </div>
      <div class="info-row">
        <span class="label">Admin Panel</span>
        <a href="<?= BASE ?>/admin/"><?= BASE ?>/admin/</a>
      </div>
      <div class="info-row">
        <span class="label">Admin Username</span>
        <code><?= esc($info['username'] ?? '') ?></code>
      </div>
      <div class="info-row">
        <span class="label">Database</span>
        <span><?= ($info['db'] ?? 'sqlite') === 'mysql' ? '🐬 MySQL / MariaDB' : '🗃️ SQLite3' ?></span>
      </div>
    </div>

    <div class="notice notice-blue">
      <strong>🔒 Post-Install Security Checklist</strong>
      <ul class="security-list">
        <li>✅ <code>data/.htaccess</code> configured — web access blocked automatically</li>
        <li>✅ <code>uploads/.htaccess</code> configured — PHP execution blocked automatically</li>
        <li>⚠️ <strong>Delete or restrict the <code>install/</code> directory</strong> — it's no longer needed</li>
        <li>⚠️ Enable HTTPS on your web server</li>
        <?php if (($info['db'] ?? '') !== 'mysql'): ?>
          <li>📦 Back up <code>data/forum.db</code> regularly</li>
        <?php endif; ?>
      </ul>
    </div>

    <a href="<?= BASE ?>/" class="btn-next" style="margin-top:8px">Visit Your Forum →</a>

  <?php endif; ?>

</div>

<script>
function switchDb(type) {
  // Update hidden input
  document.getElementById('dbTypeHidden').value = type;

  // Update button styles
  document.getElementById('btnSQLite').className = 'db-btn' + (type === 'sqlite' ? ' active' : '');
  document.getElementById('btnMySQL').className  = 'db-btn' + (type === 'mysql'  ? ' active' : '');

  // Show/hide panels
  document.getElementById('panelSQLite').className = 'db-panel' + (type === 'sqlite' ? ' visible' : '');
  document.getElementById('panelMySQL').className  = 'db-panel' + (type === 'mysql'  ? ' visible' : '');
}
</script>
</body>
</html>
