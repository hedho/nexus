<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$uname   = get('u');
$profile = DB::row('SELECT * FROM users WHERE username=?', [$uname]);
if (!$profile) render_404();

$topics = DB::rows("
    SELECT t.*,c.name AS cat_name,c.slug AS cat_slug
    FROM topics t JOIN categories c ON c.id=t.category_id
    WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 15
", [$profile['id']]);

$posts = DB::rows("
    SELECT p.*,t.title AS topic_title,t.slug AS topic_slug
    FROM posts p JOIN topics t ON t.id=p.topic_id
    WHERE p.user_id=? AND p.deleted=0 ORDER BY p.created_at DESC LIMIT 15
", [$profile['id']]);

$showFriends = !$profile['friends_hidden']
    || ($USER && (int)$USER['id'] === (int)$profile['id'])
    || ($USER && is_admin());

$friendsList = $showFriends ? friends_list((int)$profile['id']) : [];
$fStatus     = $USER ? friend_status((int)$USER['id'], (int)$profile['id']) : 'none';
$kTier       = karma_tier((int)$profile['karma']);

$PAGE_TITLE = '@' . $profile['username'];
include __DIR__ . '/../views/partials/layout.php';
?>

<div class="profile-hdr">
  <!-- Avatar -->
  <div class="profile-av">
    <?php if ($profile['avatar']): ?>
      <img src="<?= e($profile['avatar']) ?>" class="av-xl" alt="">
    <?php else: ?>
      <span class="av-xl av-init"><?= strtoupper($profile['username'][0]) ?></span>
    <?php endif; ?>
    <!-- Karma tier badge under avatar — green leaf -->
    <div class="profile-tier-badge profile-tier-badge-green">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="11" height="11"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>
      <?= e($kTier['name']) ?>
    </div>
  </div>

  <!-- Info -->
  <div class="profile-info">
    <div class="profile-name-row">
      <h1>@<?= e($profile['username']) ?></h1>
      <span class="role-tag role-<?= e($profile['role']) ?>"><?= e($profile['role']) ?></span>
      <?php if ($profile['suspended']): ?>
        <span class="role-tag role-admin">Suspended</span>
      <?php endif; ?>
    </div>
    <?php if ($profile['bio']): ?>
      <p class="profile-bio"><?= e($profile['bio']) ?></p>
    <?php endif; ?>
    <div class="profile-meta">
      <span>📅 Joined <span class="ago" data-ts="<?= e($profile['joined_at']) ?>"></span></span>
      <span>🕐 Last seen <span class="ago" data-ts="<?= e($profile['last_seen']) ?>"></span></span>
      <?php if (!empty($profile['location'])): ?>
        <span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" style="vertical-align:middle"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?= e($profile['location']) ?>
        </span>
      <?php endif; ?>
    </div>

    <!-- Karma bar — always green -->
    <div class="profile-karma-row">
      <div class="pkr-head">
        <span class="pkr-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>
        </span>
        <span class="pkr-label pkr-label-green"><?= e($kTier['name']) ?></span>
        <span class="pkr-pts"><?= number_format((int)$profile['karma']) ?> karma</span>
      </div>
      <div class="pkr-bar">
        <div class="pkr-fill pkr-fill-green" style="width:<?= $kTier['progress'] ?>%"></div>
      </div>
      <?php if ($kTier['next'] !== null): ?>
        <div class="pkr-next">
          <?= $kTier['progress'] ?>% · <?= number_format($kTier['next'] - (int)$profile['karma']) ?> more to <?= e(karma_tier($kTier['next'])['name']) ?>
        </div>
      <?php else: ?>
        <div class="pkr-next">🏆 Maximum tier reached!</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats -->
  <div class="profile-stats">
    <div class="pstat">
      <strong><?= number_format($profile['post_count']) ?></strong>
      <span>Posts</span>
    </div>
    <div class="pstat">
      <strong><?= number_format($profile['topic_count']) ?></strong>
      <span>Topics</span>
    </div>
    <div class="pstat karma-stat" style="--kc:<?= e($kTier['color']) ?>">
      <strong><?= number_format((int)$profile['karma']) ?></strong>
      <span><?= $kTier['icon'] ?> Karma</span>
    </div>
    <div class="pstat">
      <strong><?= count($friendsList) ?></strong>
      <span>Friends</span>
    </div>
  </div>

  <!-- Actions -->
  <div class="profile-actions">
    <?php if ($USER && $USER['username'] === $profile['username']): ?>
      <a href="<?= u('users/edit.php') ?>" class="btn-ghost btn-sm">✏️ Edit Profile</a>
      <a href="<?= u('messages/') ?>" class="btn-ghost btn-sm">📬 Messages</a>
    <?php elseif ($USER): ?>
      <?php if ($fStatus === 'none'): ?>
        <button class="btn-primary btn-sm" onclick="friendAction('send',<?= $profile['id'] ?>,this)">+ Add Friend</button>
      <?php elseif ($fStatus === 'pending_sent'): ?>
        <button class="btn-ghost btn-sm" onclick="friendAction('cancel',<?= $profile['id'] ?>,this)">✓ Sent (Cancel)</button>
      <?php elseif ($fStatus === 'pending_received'): ?>
        <button class="btn-primary btn-sm" onclick="friendAction('accept',<?= $profile['id'] ?>,this)">✓ Accept</button>
        <button class="btn-ghost btn-sm"   onclick="friendAction('decline',<?= $profile['id'] ?>,this)">✗ Decline</button>
      <?php elseif ($fStatus === 'friends'): ?>
        <button class="btn-ghost btn-sm" onclick="friendAction('remove',<?= $profile['id'] ?>,this)">👥 Friends</button>
      <?php endif; ?>
      <a href="<?= u('messages/compose.php?to='.urlencode($profile['username'])) ?>" class="btn-ghost btn-sm">✉️ Message</a>
    <?php endif; ?>

    <?php if ($USER && is_admin() && (int)$USER['id'] !== (int)$profile['id']): ?>
      <a href="<?= u('admin/user.php?id='.$profile['id']) ?>" class="btn-ghost btn-sm">🛡️ Admin</a>
    <?php endif; ?>
  </div>
</div>

<!-- Pending friend requests (profile owner only) -->
<?php if ($USER && (int)$USER['id'] === (int)$profile['id']): ?>
  <?php $pending = pending_requests((int)$USER['id']); ?>
  <?php if ($pending): ?>
    <div class="pending-requests">
      <h3>📥 Friend Requests (<?= count($pending) ?>)</h3>
      <?php foreach ($pending as $req): ?>
        <div class="pending-row">
          <?php if ($req['avatar']): ?>
            <img src="<?= e($req['avatar']) ?>" class="av-sm" alt="">
          <?php else: ?>
            <span class="av-sm av-init"><?= strtoupper($req['username'][0]) ?></span>
          <?php endif; ?>
          <a href="<?= u('users/profile.php?u='.urlencode($req['username'])) ?>">@<?= e($req['username']) ?></a>
          <button class="btn-primary btn-sm" onclick="friendAction('accept',<?= $req['id'] ?>,this)">Accept</button>
          <button class="btn-ghost btn-sm"   onclick="friendAction('decline',<?= $req['id'] ?>,this)">Decline</button>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- Tabs -->
<div class="profile-tabs">
  <button class="tab-btn active" onclick="showTab('topics',this)">
    Topics <span class="tab-count"><?= count($topics) ?></span>
  </button>
  <button class="tab-btn" onclick="showTab('replies',this)">
    Replies <span class="tab-count"><?= count($posts) ?></span>
  </button>
  <?php if ($showFriends): ?>
    <button class="tab-btn" onclick="showTab('friends',this)">
      Friends <span class="tab-count"><?= count($friendsList) ?></span>
    </button>
  <?php endif; ?>
</div>

<!-- Topics tab -->
<div id="tab-topics" class="tab-pane">
  <?php foreach ($topics as $t): ?>
    <div class="topic-row">
      <div class="tr-body">
        <a href="<?= u('forum/topic.php?slug='.urlencode($t['slug'])) ?>" class="tr-title"><?= e($t['title']) ?></a>
        <div class="tr-meta">
          <a href="<?= u('forum/category.php?slug='.urlencode($t['cat_slug'])) ?>"
             class="cat-tag" style="--cc:#3b82f6"><?= e($t['cat_name']) ?></a>
          <span class="ago" data-ts="<?= e($t['created_at']) ?>"></span>
        </div>
      </div>
      <div class="tr-counts"><span>💬 <?= $t['reply_count'] ?></span></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$topics): ?><p class="empty-msg">No topics yet.</p><?php endif; ?>
</div>

<!-- Replies tab -->
<div id="tab-replies" class="tab-pane hidden">
  <?php foreach ($posts as $p): ?>
    <div class="reply-card">
      <div class="rc-top">
        <a href="<?= u('forum/topic.php?slug='.urlencode($p['topic_slug']).'#post-'.$p['id']) ?>">
          ↩ <?= e($p['topic_title']) ?>
        </a>
        <span class="ago" data-ts="<?= e($p['created_at']) ?>"></span>
      </div>
      <div class="rc-body md" data-raw="<?= e(base64_encode($p['content'])) ?>"><?= e($p['content']) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$posts): ?><p class="empty-msg">No replies yet.</p><?php endif; ?>
</div>

<!-- Friends tab -->
<?php if ($showFriends): ?>
  <div id="tab-friends" class="tab-pane hidden">
    <?php if ($friendsList): ?>
      <div class="friends-grid">
        <?php foreach ($friendsList as $f): ?>
          <?php $ft = karma_tier((int)$f['karma']); ?>
          <a href="<?= u('users/profile.php?u='.urlencode($f['username'])) ?>" class="friend-card">
            <?php if ($f['avatar']): ?>
              <img src="<?= e($f['avatar']) ?>" class="av-md" alt="">
            <?php else: ?>
              <span class="av-md av-init"><?= strtoupper($f['username'][0]) ?></span>
            <?php endif; ?>
            <div class="friend-name">@<?= e($f['username']) ?></div>
            <div class="friend-karma" style="color:<?= e($ft['color']) ?>"><?= $ft['icon'] ?> <?= number_format((int)$f['karma']) ?></div>
            <div class="friend-role"><span class="role-tag role-<?= e($f['role']) ?>"><?= e($f['role']) ?></span></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="empty-msg">No friends yet.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<script>
function showTab(name,btn){
  document.querySelectorAll('.tab-pane').forEach(function(p){p.classList.add('hidden');});
  document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active');});
  document.getElementById('tab-'+name).classList.remove('hidden');
  btn.classList.add('active');
}
function friendAction(action,targetId,btn){
  var fd=new FormData();
  fd.append('action',action);fd.append('target_id',targetId);fd.append('csrf',NX.csrf);
  fetch(NX.base+'/api/friend.php',{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok){toast(d.msg,'ok');setTimeout(function(){location.reload();},800);}
      else toast(d.error,'err');
    });
}
</script>
<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
