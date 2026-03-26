<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_login();

$toUser = get('to');
$errs   = [];

// Online users for quick selection
$since       = DB::sinceSeconds(900);
$onlineUsers = DB::rows(
    "SELECT id, username, avatar, role FROM users
     WHERE last_seen >= $since AND id != ? AND suspended = 0
     ORDER BY last_seen DESC LIMIT 12",
    [$USER['id']]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $errs[] = 'Invalid request.'; }
    else {
        $to      = sanitise(post('to'));
        $subject = sanitise(post('subject'));
        $body    = sanitise(post('body'));

        if (!$to)                     $errs[] = 'Recipient required.';
        if (!$body)                   $errs[] = 'Message body required.';
        if (strlen($body) > 5000)     $errs[] = 'Message too long (max 5000 chars).';

        if (!$errs) {
            $recipient = DB::row('SELECT * FROM users WHERE username=?', [$to]);
            if (!$recipient)                          $errs[] = 'User "@' . e($to) . '" not found.';
            elseif ($recipient['id'] === $USER['id']) $errs[] = 'You cannot message yourself.';
        }

        if (!$errs) {
            $newId = DB::insert(
                'INSERT INTO messages (sender_id,receiver_id,subject,body) VALUES (?,?,?,?)',
                [$USER['id'], $recipient['id'], $subject, $body]
            );
            add_notification((int)$recipient['id'], 'message', [
                'from'    => $USER['username'],
                'from_id' => $USER['id'],
                'subject' => mb_substr($body, 0, 60) . (mb_strlen($body) > 60 ? '…' : ''),
            ]);
            go('messages/view.php?id=' . $newId);
        }
    }
}

$PAGE_TITLE = 'New Message';
include __DIR__ . '/../views/partials/layout.php';
?>

<div class="cp-layout">

  <!-- ── Compose form ─────────────────────────── -->
  <div class="cp-main">
    <div class="form-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px">
        <h1 style="font-size:1.2rem;font-weight:700;margin:0">✉️ New Message</h1>
        <a href="<?= u('messages/') ?>" class="btn-ghost btn-sm">← Inbox</a>
      </div>

      <?php if (!empty($errs)): ?>
        <div class="alert err"><?= implode('<br>', array_map('e', $errs)) ?></div>
      <?php endif; ?>

      <form method="POST" id="composeForm">
        <?= csrf_input() ?>

        <!-- To field with autocomplete -->
        <div class="fg">
          <label>To <span class="req">*</span></label>
          <div class="to-input-wrap" style="position:relative">
            <span class="at-prefix">@</span>
            <input type="text" name="to" id="toInput" class="fi" required
                   value="<?= e($_POST['to'] ?? $toUser ?? '') ?>"
                   placeholder="username" autocomplete="off">
            <div id="toSuggestions" class="to-suggestions"></div>
          </div>
        </div>

        <!-- Subject -->
        <div class="fg">
          <label>Subject <small>(optional)</small></label>
          <input type="text" name="subject" class="fi" maxlength="150"
                 value="<?= e($_POST['subject'] ?? '') ?>" placeholder="What's this about?">
        </div>

        <!-- Body -->
        <div class="fg">
          <label>Message <span class="req">*</span></label>
          <textarea name="body" id="msgBody" class="fi" rows="10" required maxlength="5000"
                    placeholder="Write your message…"><?= e($_POST['body'] ?? '') ?></textarea>
          <div style="display:flex;justify-content:space-between;margin-top:4px">
            <span class="hint">Max 5000 characters</span>
            <span class="hint" id="bodyCount">0 / 5000</span>
          </div>
        </div>

        <div class="form-actions">
          <a href="<?= u('messages/') ?>" class="btn-ghost">Cancel</a>
          <button type="submit" class="btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/></svg>
            Send Message
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Online people panel ────────────────────── -->
  <aside class="cp-aside">
    <div class="mx-widget">
      <div class="mx-widget-head">
        <span class="mx-online-dot" style="position:static;margin-right:4px"></span>
        Online Now
        <span class="mx-widget-count"><?= count($onlineUsers) ?></span>
      </div>
      <?php if (empty($onlineUsers)): ?>
        <div class="mx-widget-empty">No one online right now</div>
      <?php else: ?>
        <p style="padding:10px 14px 4px;font-size:12px;color:var(--muted)">Click to message them directly</p>
        <div class="mx-online-list">
          <?php foreach ($onlineUsers as $ou): ?>
            <button class="mx-online-row cp-pick" type="button"
                    onclick="pickUser('<?= e($ou['username']) ?>')"
                    title="Send to @<?= e($ou['username']) ?>">
              <div style="position:relative;flex-shrink:0">
                <?php if ($ou['avatar']): ?>
                  <img src="<?= e($ou['avatar']) ?>" class="av-sm" alt="">
                <?php else: ?>
                  <span class="av-sm av-init"><?= strtoupper($ou['username'][0]) ?></span>
                <?php endif; ?>
                <span class="mx-online-dot mx-online-dot-sm"></span>
              </div>
              <div class="mx-online-info">
                <span class="mx-online-name">@<?= e($ou['username']) ?></span>
                <span class="role-tag role-<?= e($ou['role']) ?>"><?= e($ou['role']) ?></span>
              </div>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--faint)" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Search any user -->
    <div class="mx-widget" style="margin-top:14px">
      <div class="mx-widget-head">🔍 Find User</div>
      <div style="padding:10px">
        <input type="text" id="findUser" class="fi" placeholder="Search username…" autocomplete="off">
        <div id="findResults" class="to-suggestions" style="position:static;margin-top:6px;border-radius:var(--r)"></div>
      </div>
    </div>
  </aside>
</div>

<script>
/* ── To-field autocomplete ──────────────────── */
var toInp = document.getElementById('toInput');
var toSug = document.getElementById('toSuggestions');
var toTmr;
if (toInp) {
  toInp.addEventListener('input', function() {
    clearTimeout(toTmr);
    var q = this.value.trim().replace(/^@/, '');
    if (!q) { toSug.style.display = 'none'; return; }
    toTmr = setTimeout(function() {
      fetch(NX.base + '/api/search_users.php?q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(rows) {
          if (!rows.length) { toSug.style.display = 'none'; return; }
          toSug.innerHTML = rows.map(function(u) {
            return '<div class="to-sug-item" onclick="pickUser(\'' + u.username + '\')">' +
              '@' + u.username + '</div>';
          }).join('');
          toSug.style.display = 'block';
        });
    }, 220);
  });
  document.addEventListener('click', function(e) {
    if (!toInp.contains(e.target)) toSug.style.display = 'none';
  });
}

/* ── Find user panel ────────────────────────── */
var findInp = document.getElementById('findUser');
var findRes = document.getElementById('findResults');
var findTmr;
if (findInp) {
  findInp.addEventListener('input', function() {
    clearTimeout(findTmr);
    var q = this.value.trim();
    if (!q) { findRes.style.display = 'none'; return; }
    findTmr = setTimeout(function() {
      fetch(NX.base + '/api/search_users.php?q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(rows) {
          if (!rows.length) { findRes.style.display = 'none'; return; }
          findRes.innerHTML = rows.map(function(u) {
            return '<div class="to-sug-item" onclick="pickUser(\'' + u.username + '\')">' +
              '@' + u.username + '</div>';
          }).join('');
          findRes.style.display = 'block';
        });
    }, 220);
  });
}

/* ── Pick user ──────────────────────────────── */
function pickUser(uname) {
  document.getElementById('toInput').value = uname;
  toSug.style.display = 'none';
  if (findRes) findRes.style.display = 'none';
  document.getElementById('toInput').focus();
  document.getElementById('toInput').dispatchEvent(new Event('input'));
}

/* ── Char counter ───────────────────────────── */
var body = document.getElementById('msgBody');
var cnt  = document.getElementById('bodyCount');
if (body && cnt) {
  body.addEventListener('input', function() {
    var n = this.value.length;
    cnt.textContent = n + ' / 5000';
    cnt.style.color = n > 4500 ? '#ef4444' : '';
  });
}
</script>

<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
