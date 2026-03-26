<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$q       = get('q');
$results = [];

if (mb_strlen($q) >= 2) {
    $results = DB::rows(
        "SELECT u.id, u.username, u.avatar, u.role, u.karma, u.post_count, u.topic_count,
                u.joined_at, u.bio
         FROM users u
         WHERE (u.username LIKE ? OR u.bio LIKE ?) AND u.suspended=0
         ORDER BY u.post_count DESC LIMIT 40",
        ['%'.$q.'%', '%'.$q.'%']
    );
}

$PAGE_TITLE = 'Find Users';
include __DIR__ . '/../views/partials/layout.php';
?>
<div style="max-width:720px">
  <div class="sec-title" style="margin-bottom:20px">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    Find Users
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:24px">
    <input type="text" name="q" value="<?= e($q) ?>" class="fi" style="flex:1;max-width:500px"
           placeholder="Search by username or bio…" autofocus autocomplete="off">
    <button type="submit" class="btn-primary">Search</button>
  </form>

  <?php if ($q && mb_strlen($q) >= 2): ?>
    <p style="color:var(--muted);font-size:14px;margin-bottom:16px">
      <?= count($results) ?> result<?= count($results) !== 1 ? 's' : '' ?> for
      "<strong><?= e($q) ?></strong>"
    </p>

    <?php if (empty($results)): ?>
      <div class="empty-state">
        <p>No users found matching "<?= e($q) ?>".</p>
      </div>
    <?php else: ?>
      <div class="user-search-grid">
        <?php foreach ($results as $u):
          $kt = karma_tier((int)$u['karma']); ?>
          <a href="<?= u('users/profile.php?u=' . urlencode($u['username'])) ?>" class="user-search-card">
            <div class="usc-av">
              <?php if ($u['avatar']): ?>
                <img src="<?= e($u['avatar']) ?>" class="av-lg" alt="">
              <?php else: ?>
                <span class="av-lg av-init"><?= strtoupper($u['username'][0]) ?></span>
              <?php endif; ?>
            </div>
            <div class="usc-body">
              <div class="usc-name">
                @<?= e($u['username']) ?>
                <span class="role-tag role-<?= e($u['role']) ?>"><?= e($u['role']) ?></span>
              </div>
              <?php if ($u['bio']): ?>
                <div class="usc-bio"><?= e(mb_substr($u['bio'], 0, 80)) ?><?= mb_strlen($u['bio']) > 80 ? '…' : '' ?></div>
              <?php endif; ?>
              <div class="usc-stats">
                <span><?= number_format($u['post_count']) ?> posts</span>
                <span>·</span>
                <span style="color:<?= e($kt['color']) ?>"><?= $kt['icon'] ?> <?= number_format((int)$u['karma']) ?> karma</span>
              </div>
            </div>
            <?php if ($USER && (int)$USER['id'] !== (int)$u['id']): ?>
              <div class="usc-actions">
                <a href="<?= u('messages/chat.php?with=' . urlencode($u['username'])) ?>"
                   class="btn-ghost btn-sm" onclick="event.preventDefault();event.stopPropagation();window.location=this.href">
                  💬 Message
                </a>
              </div>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php elseif ($q): ?>
    <div class="alert warn">Please enter at least 2 characters to search.</div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
