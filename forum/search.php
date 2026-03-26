<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$q    = get('q');
$type = get('type', 'topics'); // topics | users | posts
if (!in_array($type, ['topics','users','posts'])) $type = 'topics';

$topicResults = [];
$postResults  = [];
$userResults  = [];

if (mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';

    if ($type === 'topics' || $type === 'posts') {
        // Topics matching title
        $topicResults = DB::rows("
            SELECT t.*, u.username, c.name AS cat_name, c.slug AS cat_slug, c.color AS cat_color,
                   NULL AS match_post_id
            FROM topics t
            JOIN users u ON u.id = t.user_id
            JOIN categories c ON c.id = t.category_id
            WHERE t.title LIKE ?
            ORDER BY t.last_post_at DESC LIMIT 20
        ", [$like]);
    }

    if ($type === 'posts') {
        // Posts matching content — include post ID so we can anchor to it
        $postResults = DB::rows("
            SELECT p.id AS post_id, p.content, p.post_num, p.created_at AS post_date,
                   t.id AS topic_id, t.title, t.slug,
                   u.username, u.avatar,
                   c.name AS cat_name, c.slug AS cat_slug, c.color AS cat_color
            FROM posts p
            JOIN topics t ON t.id = p.topic_id
            JOIN users u ON u.id = p.user_id
            JOIN categories c ON c.id = t.category_id
            WHERE p.content LIKE ? AND p.deleted = 0
            ORDER BY p.created_at DESC LIMIT 30
        ", [$like]);
    }

    if ($type === 'users') {
        $userResults = DB::rows("
            SELECT id, username, avatar, role, karma, post_count, bio, joined_at
            FROM users
            WHERE (username LIKE ? OR bio LIKE ?) AND suspended = 0
            ORDER BY post_count DESC LIMIT 30
        ", [$like, $like]);
    }
}

$allCount = count($topicResults) + count($postResults) + count($userResults);
$PAGE_TITLE = 'Search';
include __DIR__ . '/../views/partials/layout.php';
?>
<div style="max-width:800px">

  <!-- Search form with type tabs -->
  <form method="GET" id="searchForm">
    <div style="display:flex;gap:10px;margin-bottom:16px">
      <div style="position:relative;flex:1;max-width:560px">
        <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--faint);pointer-events:none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" name="q" value="<?= e($q) ?>" class="fi"
               style="padding-left:34px" placeholder="Search topics, posts, users…"
               autofocus autocomplete="off" id="mainSearchInput">
      </div>
      <button type="submit" class="btn-primary">Search</button>
    </div>

    <!-- Type tabs -->
    <div style="display:flex;gap:0;margin-bottom:22px;border-bottom:2px solid var(--border)">
      <?php foreach (['topics'=>'📋 Topics','posts'=>'💬 Posts','users'=>'👥 Users'] as $t => $lbl): ?>
        <button type="submit" name="type" value="<?= $t ?>"
                class="srch-tab <?= $type===$t?'active':'' ?>"><?= $lbl ?></button>
      <?php endforeach; ?>
    </div>
  </form>

  <?php if (mb_strlen($q) >= 2): ?>
    <p class="search-info">
      <?= count($type==='posts'?$postResults:($type==='users'?$userResults:$topicResults)) ?>
      result<?= count($type==='posts'?$postResults:($type==='users'?$userResults:$topicResults))!==1?'s':'' ?>
      for "<strong><?= e($q) ?></strong>"
    </p>

    <?php if ($type === 'topics'): ?>
      <?php if (empty($topicResults)): ?>
        <div class="empty-state"><p>No topics found matching "<?= e($q) ?>".</p></div>
      <?php else: ?>
        <?php foreach ($topicResults as $t): ?>
          <div class="topic-row">
            <div class="tr-body">
              <a href="<?= u('forum/topic.php?slug=' . urlencode($t['slug'])) ?>" class="tr-title"><?= e($t['title']) ?></a>
              <div class="tr-meta">
                <a href="<?= u('forum/category.php?slug=' . urlencode($t['cat_slug'])) ?>"
                   class="cat-tag" style="--cc:<?= e($t['cat_color']) ?>"><?= e($t['cat_name']) ?></a>
                by <a href="<?= u('users/profile.php?u=' . urlencode($t['username'])) ?>">@<?= e($t['username']) ?></a>
                <span class="ago" data-ts="<?= e($t['created_at']) ?>"></span>
              </div>
            </div>
            <div class="tr-counts"><span>💬 <?= $t['reply_count'] ?></span><span>👁 <?= $t['views'] ?></span></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php elseif ($type === 'posts'): ?>
      <?php if (empty($postResults)): ?>
        <div class="empty-state"><p>No posts found matching "<?= e($q) ?>".</p></div>
      <?php else: ?>
        <?php foreach ($postResults as $p): ?>
          <?php
            // Build URL with post anchor so user lands on the exact post
            $postUrl = u('forum/topic.php?slug=' . urlencode($p['slug']) . '&goto=' . $p['post_id']) . '#post-' . $p['post_id'];
            // Highlight the search term in content snippet
            $snippet = mb_substr($p['content'], 0, 200);
            $pos     = mb_stripos($p['content'], $q);
            if ($pos !== false) {
                $start   = max(0, $pos - 60);
                $snippet = ($start > 0 ? '…' : '') . mb_substr($p['content'], $start, 200) . (mb_strlen($p['content']) > $start+200 ? '…' : '');
            }
          ?>
          <div class="post-search-card">
            <div class="psc-head">
              <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0">
                <?php if ($p['avatar']): ?>
                  <img src="<?= e($p['avatar']) ?>" class="av-sm" alt="">
                <?php else: ?>
                  <span class="av-sm av-init"><?= strtoupper($p['username'][0]) ?></span>
                <?php endif; ?>
                <div style="min-width:0">
                  <a href="<?= u('users/profile.php?u='.urlencode($p['username'])) ?>" style="font-weight:600;font-size:13px">@<?= e($p['username']) ?></a>
                  <span style="color:var(--faint);font-size:12px;margin-left:6px">
                    in <a href="<?= u('forum/topic.php?slug='.urlencode($p['slug'])) ?>" style="color:var(--muted)"><?= e(mb_substr($p['title'],0,60)) ?><?= mb_strlen($p['title'])>60?'…':'' ?></a>
                  </span>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                <span class="ago" data-ts="<?= e($p['post_date']) ?>" style="font-size:12px;color:var(--faint)"></span>
                <a href="<?= e($postUrl) ?>" class="btn-ghost btn-sm">View Post →</a>
              </div>
            </div>
            <div class="psc-body"><?= e($snippet) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php elseif ($type === 'users'): ?>
      <?php if (empty($userResults)): ?>
        <div class="empty-state"><p>No users found matching "<?= e($q) ?>".</p></div>
      <?php else: ?>
        <div class="user-search-grid">
          <?php foreach ($userResults as $u):
            $kt = karma_tier((int)$u['karma']); ?>
            <a href="<?= u('users/profile.php?u='.urlencode($u['username'])) ?>" class="user-search-card">
              <div class="usc-av">
                <?php if ($u['avatar']): ?>
                  <img src="<?= e($u['avatar']) ?>" class="av-lg" alt="">
                <?php else: ?>
                  <span class="av-lg av-init"><?= strtoupper($u['username'][0]) ?></span>
                <?php endif; ?>
              </div>
              <div class="usc-body">
                <div class="usc-name">@<?= e($u['username']) ?> <span class="role-tag role-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></div>
                <?php if ($u['bio']): ?><div class="usc-bio"><?= e(mb_substr($u['bio'],0,80)) ?></div><?php endif; ?>
                <div class="usc-stats">
                  <span><?= number_format($u['post_count']) ?> posts</span>
                  <span>·</span>
                  <span style="color:<?= e($kt['color']) ?>"><?= $kt['icon'] ?> <?= number_format((int)$u['karma']) ?> karma</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  <?php elseif ($q): ?>
    <div class="alert warn">Please enter at least 2 characters to search.</div>
  <?php endif; ?>
</div>

<script>
// Auto-submit on tab change keeps query
document.querySelectorAll('.srch-tab').forEach(function(btn){
  btn.addEventListener('click',function(){
    document.getElementById('mainSearchInput').form.submit();
  });
});
</script>
<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
