<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_login();

$box = get('box', 'inbox');
if (!in_array($box, ['inbox', 'sent'])) $box = 'inbox';

/* ── Fetch messages ─────────────────────────────────── */
if ($box === 'inbox') {
    $msgs = DB::rows("
        SELECT m.*, u.username AS other_name, u.avatar AS other_av,
               u.last_seen, u.role
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.receiver_id = ? AND m.deleted_by_receiver = 0
        ORDER BY m.created_at DESC
    ", [$USER['id']]);
} else {
    $msgs = DB::rows("
        SELECT m.*, u.username AS other_name, u.avatar AS other_av,
               u.last_seen, u.role
        FROM messages m
        JOIN users u ON u.id = m.receiver_id
        WHERE m.sender_id = ? AND m.deleted_by_sender = 0
        ORDER BY m.created_at DESC
    ", [$USER['id']]);
}

/* ── Online users (last seen within 15 min) ─────────── */
$since    = DB::sinceSeconds(900);
$onlineUsers = DB::rows(
    "SELECT id, username, avatar, role FROM users
     WHERE last_seen >= $since AND id != ? AND suspended = 0
     ORDER BY last_seen DESC LIMIT 20",
    [$USER['id']]
);

$unread    = unread_messages();
$sentCount = (int)DB::val("SELECT COUNT(*) FROM messages WHERE sender_id=? AND deleted_by_sender=0", [$USER['id']]);

$PAGE_TITLE = 'Messages';
include __DIR__ . '/../views/partials/layout.php';
?>

<div class="mx-layout">

  <!-- ── Left: Inbox ───────────────────────────── -->
  <div class="mx-main">

    <div class="mx-topbar">
      <div>
        <h1 class="mx-title">
          <?= $box === 'inbox' ? '📥 Inbox' : '📤 Sent' ?>
          <?php if ($box === 'inbox' && $unread > 0): ?>
            <span class="mx-unread-badge"><?= $unread ?> unread</span>
          <?php endif; ?>
        </h1>
      </div>
      <a href="<?= u('messages/compose.php') ?>" class="btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Compose
      </a>
    </div>

    <!-- Tabs -->
    <div class="mx-tabs">
      <a href="?box=inbox" class="mx-tab <?= $box === 'inbox' ? 'active' : '' ?>">
        📥 Inbox
        <?php if ($unread > 0): ?>
          <span class="mx-tab-badge"><?= $unread ?></span>
        <?php endif; ?>
      </a>
      <a href="?box=sent" class="mx-tab <?= $box === 'sent' ? 'active' : '' ?>">
        📤 Sent
        <?php if ($sentCount > 0): ?>
          <span class="mx-tab-badge muted"><?= $sentCount ?></span>
        <?php endif; ?>
      </a>
    </div>

    <?php if (isset($_GET['sent'])): ?>
      <div class="alert ok" style="margin-bottom:16px">✓ Message sent successfully.</div>
    <?php endif; ?>

    <?php if (empty($msgs)): ?>
      <div class="mx-empty">
        <div class="mx-empty-icon"><?= $box === 'inbox' ? '📭' : '📤' ?></div>
        <p><?= $box === 'inbox' ? 'Your inbox is empty.' : 'No sent messages yet.' ?></p>
        <a href="<?= u('messages/compose.php') ?>" class="btn-primary">Send a message</a>
      </div>
    <?php else: ?>
      <div class="mx-list">
        <?php foreach ($msgs as $m):
          $isUnread = !$m['is_read'] && $box === 'inbox';
          $isOnline = strtotime($m['last_seen']) >= (time() - 900);
          $name     = $m['other_name'];
          $av       = $m['other_av'];
        ?>
          <a href="<?= u('messages/view.php?id=' . $m['id']) ?>"
             class="mx-row <?= $isUnread ? 'unread' : '' ?>">

            <!-- Avatar + online dot -->
            <div class="mx-row-av">
              <?php if ($av): ?>
                <img src="<?= e($av) ?>" class="av-md" alt="">
              <?php else: ?>
                <span class="av-md av-init"><?= strtoupper($name[0]) ?></span>
              <?php endif; ?>
              <?php if ($isOnline): ?>
                <span class="mx-online-dot" title="Online now"></span>
              <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="mx-row-body">
              <div class="mx-row-top">
                <span class="mx-row-from">
                  <?= $box === 'inbox' ? 'From' : 'To' ?>
                  <strong>@<?= e($name) ?></strong>
                  <span class="role-tag role-<?= e($m['role']) ?>"><?= e($m['role']) ?></span>
                  <?php if ($isOnline): ?>
                    <span class="mx-online-label">● Online</span>
                  <?php endif; ?>
                </span>
                <span class="mx-row-time">
                  <?php if ($isUnread): ?>
                    <span class="mx-new-badge">NEW</span>
                  <?php endif; ?>
                  <span class="ago" data-ts="<?= e($m['created_at']) ?>"></span>
                </span>
              </div>
              <?php if ($m['subject']): ?>
                <div class="mx-row-subject"><?= e($m['subject']) ?></div>
              <?php endif; ?>
              <div class="mx-row-preview"><?= e(mb_substr($m['body'], 0, 100)) ?><?= mb_strlen($m['body']) > 100 ? '…' : '' ?></div>
            </div>

            <!-- Arrow -->
            <div class="mx-row-arrow">›</div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Right: Online users + quick compose ───── -->
  <aside class="mx-aside">

    <!-- Online users -->
    <div class="mx-widget">
      <div class="mx-widget-head">
        <span class="mx-online-dot" style="position:static;margin-right:4px"></span>
        Online Now
        <span class="mx-widget-count"><?= count($onlineUsers) ?></span>
      </div>
      <?php if (empty($onlineUsers)): ?>
        <div class="mx-widget-empty">No one online right now</div>
      <?php else: ?>
        <div class="mx-online-list">
          <?php foreach ($onlineUsers as $ou): ?>
            <div class="mx-online-row">
              <div style="position:relative;flex-shrink:0">
                <?php if ($ou['avatar']): ?>
                  <img src="<?= e($ou['avatar']) ?>" class="av-sm" alt="">
                <?php else: ?>
                  <span class="av-sm av-init"><?= strtoupper($ou['username'][0]) ?></span>
                <?php endif; ?>
                <span class="mx-online-dot mx-online-dot-sm"></span>
              </div>
              <div class="mx-online-info">
                <a href="<?= u('users/profile.php?u=' . urlencode($ou['username'])) ?>"
                   class="mx-online-name">@<?= e($ou['username']) ?></a>
                <span class="role-tag role-<?= e($ou['role']) ?>"><?= e($ou['role']) ?></span>
              </div>
              <a href="<?= u('messages/compose.php?to=' . urlencode($ou['username'])) ?>"
                 class="mx-msg-quick" title="Send message">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quick compose -->
    <div class="mx-widget" style="margin-top:16px">
      <div class="mx-widget-head">✏️ Quick Message</div>
      <div style="padding:14px">
        <div style="position:relative;margin-bottom:10px">
          <input type="text" id="quickTo" class="fi" placeholder="@username" autocomplete="off"
                 style="padding-left:26px">
          <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--faint);font-size:13px;pointer-events:none">@</span>
          <div id="quickSuggestions" class="to-suggestions"></div>
        </div>
        <a id="quickComposeBtn" href="<?= u('messages/compose.php') ?>" class="btn-primary btn-block">
          Compose →
        </a>
      </div>
    </div>

  </aside>
</div>

<script>
/* Quick compose — update link as user types */
var qTo  = document.getElementById('quickTo');
var qBtn = document.getElementById('quickComposeBtn');
var qSug = document.getElementById('quickSuggestions');
var qTimer;

if (qTo) {
  qTo.addEventListener('input', function() {
    var val = this.value.trim().replace(/^@/, '');
    qBtn.href = NX.base + '/messages/compose.php' + (val ? '?to=' + encodeURIComponent(val) : '');
    clearTimeout(qTimer);
    if (val.length < 1) { qSug.style.display = 'none'; return; }
    qTimer = setTimeout(function() {
      fetch(NX.base + '/api/search_users.php?q=' + encodeURIComponent(val))
        .then(function(r) { return r.json(); })
        .then(function(rows) {
          if (!rows.length) { qSug.style.display = 'none'; return; }
          qSug.innerHTML = rows.slice(0, 5).map(function(u) {
            return '<div class="to-sug-item" onclick="pickQuick(\'' + u.username + '\')">@' + u.username + '</div>';
          }).join('');
          qSug.style.display = 'block';
        });
    }, 220);
  });
  document.addEventListener('click', function(e) {
    if (!qTo.contains(e.target)) qSug.style.display = 'none';
  });
}

function pickQuick(uname) {
  qTo.value = uname;
  qBtn.href = NX.base + '/messages/compose.php?to=' + encodeURIComponent(uname);
  qSug.style.display = 'none';
  qBtn.click();
}
</script>

<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
