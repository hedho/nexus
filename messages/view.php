<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_login();

$id  = (int)get('id');
$msg = DB::row("
    SELECT m.*,
           s.username AS sname, s.avatar AS sav, s.role AS srole,
           s.karma AS skarma, s.last_seen AS s_last_seen,
           r.username AS rname, r.avatar AS rav, r.role AS rrole,
           r.last_seen AS r_last_seen
    FROM messages m
    JOIN users s ON s.id = m.sender_id
    JOIN users r ON r.id = m.receiver_id
    WHERE m.id = ?
", [$id]);

if (!$msg)                                                           render_404();
if ($msg['sender_id'] !== $USER['id'] && $msg['receiver_id'] !== $USER['id']) render_403();
if ($msg['receiver_id'] === $USER['id'] && $msg['deleted_by_receiver'])       render_404();
if ($msg['sender_id'] === $USER['id'] && $msg['deleted_by_sender'])           render_404();

// Mark as read
if ($msg['receiver_id'] === $USER['id'] && !$msg['is_read']) {
    DB::run('UPDATE messages SET `is_read`=1 WHERE id=?', [$id]);
}

// Determine the "other" person
$otherId   = $msg['sender_id'] === $USER['id'] ? $msg['receiver_id'] : $msg['sender_id'];
$otherName = $msg['sender_id'] === $USER['id'] ? $msg['rname']       : $msg['sname'];
$otherAv   = $msg['sender_id'] === $USER['id'] ? $msg['rav']         : $msg['sav'];
$otherRole = $msg['sender_id'] === $USER['id'] ? $msg['rrole']       : $msg['srole'];
$otherSeen = $msg['sender_id'] === $USER['id'] ? $msg['r_last_seen'] : $msg['s_last_seen'];
$otherOnline = strtotime($otherSeen) >= (time() - 900);

// Conversation thread (all messages between these two users)
function conv_id_local(int $a, int $b): string { return min($a,$b).'-'.max($a,$b); }
$convId  = conv_id_local((int)$USER['id'], $otherId);
$thread  = DB::rows("
    SELECT m.*, u.username AS sender_name, u.avatar AS sender_av
    FROM messages m JOIN users u ON u.id = m.sender_id
    WHERE (m.sender_id=? AND m.receiver_id=?)
       OR (m.sender_id=? AND m.receiver_id=?)
    ORDER BY m.created_at ASC
", [$USER['id'], $otherId, $otherId, $USER['id']]);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = post('action');

    if ($action === 'delete_msg') {
        $delId = (int)post('del_id');
        $dm    = DB::row('SELECT * FROM messages WHERE id=?', [$delId]);
        if ($dm && ($dm['sender_id'] === $USER['id'] || $dm['receiver_id'] === $USER['id'])) {
            if ($dm['sender_id']   === $USER['id']) DB::run('UPDATE messages SET deleted_by_sender=1 WHERE id=?',   [$delId]);
            if ($dm['receiver_id'] === $USER['id']) DB::run('UPDATE messages SET deleted_by_receiver=1 WHERE id=?', [$delId]);
        }
        go('messages/view.php?id=' . $id);
    }

    if ($action === 'delete') {
        if ($msg['receiver_id'] === $USER['id'])
            DB::run('UPDATE messages SET deleted_by_receiver=1 WHERE id=?', [$id]);
        else
            DB::run('UPDATE messages SET deleted_by_sender=1 WHERE id=?', [$id]);
        go('messages/');
    }

    if ($action === 'reply') {
        $body = sanitise(post('body'));
        if ($body) {
            $subject = $msg['subject'] ? ('Re: ' . ltrim($msg['subject'], 'Re: ')) : '';
            DB::insert(
                'INSERT INTO messages (sender_id,receiver_id,subject,body) VALUES (?,?,?,?)',
                [$USER['id'], $otherId, $subject, $body]
            );
            add_notification($otherId, 'message', [
                'from'    => $USER['username'],
                'from_id' => $USER['id'],
                'subject' => mb_substr($body, 0, 60) . (mb_strlen($body) > 60 ? '…' : ''),
            ]);
            go('messages/view.php?id=' . $id . '&replied=1');
        }
    }
}

$PAGE_TITLE = $msg['subject'] ?: 'Conversation with @' . $otherName;
include __DIR__ . '/../views/partials/layout.php';
?>

<div class="mv-layout">

  <!-- ── Thread sidebar ───────────────────────── -->
  <aside class="mv-aside">
    <!-- Other person card -->
    <div class="mv-user-card">
      <div class="mv-user-av">
        <?php if ($otherAv): ?>
          <img src="<?= e($otherAv) ?>" class="av-lg" alt="">
        <?php else: ?>
          <span class="av-lg av-init"><?= strtoupper($otherName[0]) ?></span>
        <?php endif; ?>
        <?php if ($otherOnline): ?>
          <span class="mv-online-dot" title="Online now"></span>
        <?php endif; ?>
      </div>
      <div class="mv-user-info">
        <a href="<?= u('users/profile.php?u=' . urlencode($otherName)) ?>" class="mv-user-name">
          @<?= e($otherName) ?>
        </a>
        <span class="role-tag role-<?= e($otherRole) ?>"><?= e($otherRole) ?></span>
        <div class="mv-user-status <?= $otherOnline ? 'online' : 'offline' ?>">
          <span class="mv-status-dot"></span>
          <?= $otherOnline ? 'Online now' : 'Last seen <span class="ago" data-ts="' . e($otherSeen) . '"></span>' ?>
        </div>
      </div>
      <div class="mv-user-actions">
        <a href="<?= u('users/profile.php?u=' . urlencode($otherName)) ?>" class="btn-ghost btn-sm">Profile</a>
        <a href="<?= u('messages/compose.php?to=' . urlencode($otherName)) ?>" class="btn-ghost btn-sm">New Message</a>
      </div>
    </div>

    <!-- Conversation stats -->
    <div class="mv-conv-stats">
      <div class="mv-cs-item">
        <span class="mv-cs-num"><?= count($thread) ?></span>
        <span class="mv-cs-lbl">messages</span>
      </div>
      <div class="mv-cs-item">
        <span class="mv-cs-num"><?= count(array_filter($thread, fn($m) => !$m['is_read'] && $m['sender_id'] == $otherId)) ?></span>
        <span class="mv-cs-lbl">unread</span>
      </div>
    </div>

    <a href="<?= u('messages/') ?>" class="btn-ghost btn-block" style="margin-top:4px">← Back to Inbox</a>
  </aside>

  <!-- ── Main thread ───────────────────────────── -->
  <div class="mv-main">

    <!-- Thread header -->
    <div class="mv-thread-head">
      <h1 class="mv-thread-title">
        <?= $msg['subject'] ? e($msg['subject']) : 'Conversation with @' . e($otherName) ?>
      </h1>
      <form method="POST" style="display:inline-block">
        <?= csrf_input() ?><input type="hidden" name="action" value="delete">
        <button class="btn-ghost btn-sm" style="color:var(--red)"
                onclick="return confirm('Delete this message thread?')">🗑 Delete</button>
      </form>
    </div>

    <?php if (isset($_GET['replied'])): ?>
      <div class="alert ok" style="margin-bottom:16px">✓ Reply sent.</div>
    <?php endif; ?>

    <!-- Message thread -->
    <div class="mv-thread">
      <?php foreach ($thread as $tm):
        $isMine  = ((int)$tm['sender_id'] === (int)$USER['id']);
        $canDel  = ($tm['sender_id'] == $USER['id'] && !$tm['deleted_by_sender'])
                || ($tm['receiver_id'] == $USER['id'] && !$tm['deleted_by_receiver']);
        $isHighlight = ((int)$tm['id'] === $id && !$isMine);
      ?>
        <div class="mv-msg <?= $isMine ? 'mv-mine' : 'mv-theirs' ?> <?= $isHighlight ? 'mv-highlight' : '' ?>"
             id="msg-<?= $tm['id'] ?>">

          <!-- Avatar -->
          <div class="mv-msg-av" <?= $isMine ? 'style="order:3"' : '' ?>>
            <?php if ($tm['sender_av']): ?>
              <img src="<?= e($tm['sender_av']) ?>" class="av-sm" alt="">
            <?php else: ?>
              <span class="av-sm av-init"><?= strtoupper($tm['sender_name'][0]) ?></span>
            <?php endif; ?>
          </div>

          <!-- Bubble -->
          <div class="mv-msg-bubble <?= $isMine ? 'mv-bubble-mine' : 'mv-bubble-theirs' ?>">
            <div class="mv-msg-body"><?= nl2br(e($tm['body'])) ?></div>
            <div class="mv-msg-meta">
              <span class="ago" data-ts="<?= e($tm['created_at']) ?>"></span>
              <?php if ($isMine && $tm['is_read']): ?>
                <span class="mv-read-tick" title="Read">✓✓</span>
              <?php elseif ($isMine): ?>
                <span class="mv-sent-tick" title="Sent">✓</span>
              <?php endif; ?>
              <?php if ($canDel): ?>
                <form method="POST" style="display:inline">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="delete_msg">
                  <input type="hidden" name="del_id" value="<?= $tm['id'] ?>">
                  <button class="mv-del-btn" title="Delete" onclick="return confirm('Delete this message?')">✕</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Reply box -->
    <div class="mv-reply-box" id="replyBox">
      <div class="mv-reply-head">
        <span>↩ Reply to @<?= e($otherName) ?></span>
        <?php if ($otherOnline): ?>
          <span style="color:var(--green);font-size:12px">● Online — will see it right away</span>
        <?php endif; ?>
      </div>
      <form method="POST" id="replyForm">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="reply">
        <textarea name="body" id="replyBody" class="mv-reply-ta" rows="4" required
                  maxlength="5000" placeholder="Write your reply…"></textarea>
        <div class="mv-reply-footer">
          <span class="mv-char-cnt" id="charCnt">0 / 5000</span>
          <button type="submit" class="btn-primary">Send Reply</button>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
// Scroll to highlighted message
var hl = document.querySelector('.mv-highlight');
if (hl) hl.scrollIntoView({behavior:'smooth', block:'center'});

// Scroll thread to bottom if viewing latest
var thread = document.querySelector('.mv-thread');
if (thread && !hl) thread.scrollTop = thread.scrollHeight;

// Char counter
var ta = document.getElementById('replyBody');
var cc = document.getElementById('charCnt');
if (ta && cc) {
  ta.addEventListener('input', function() {
    var n = this.value.length;
    cc.textContent = n + ' / 5000';
    cc.style.color = n > 4500 ? '#ef4444' : '';
  });
}
</script>

<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
