<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$slug = get('slug');
$cat  = DB::row('SELECT * FROM categories WHERE slug=?', [$slug]);
if (!$cat) render_404();
if (!can_read_category($cat)) render_403();

$page = max(1, (int)get('page','1'));
$pp   = (int)cfg('topics_per_page','30');
$off  = ($page - 1) * $pp;

$topics = DB::rows("
    SELECT t.*, u.username, u.avatar,
      (SELECT u2.username FROM posts p2 JOIN users u2 ON u2.id=p2.user_id
       WHERE p2.topic_id=t.id ORDER BY p2.created_at DESC LIMIT 1) AS last_poster,
      (SELECT COUNT(*)-1 FROM posts p WHERE p.topic_id=t.id AND p.deleted=0) AS replies
    FROM topics t JOIN users u ON u.id=t.user_id
    WHERE t.category_id=? AND t.archived=0
    ORDER BY t.pinned DESC, t.last_post_at DESC
    LIMIT ? OFFSET ?
", [$cat['id'], $pp, $off]);

$total = (int) DB::val('SELECT COUNT(*) FROM topics WHERE category_id=? AND archived=0', [$cat['id']]);
$pages = max(1, (int)ceil($total / $pp));
$subs  = DB::rows('SELECT * FROM categories WHERE parent_id=? ORDER BY position', [$cat['id']]);

$PAGE_TITLE = $cat['name'];
include __DIR__ . '/../views/partials/layout.php';
?>
<nav class="bc"><a href="<?= u('/') ?>">Home</a> › <span><?= e($cat['name']) ?></span></nav>

<div class="cat-hdr" style="border-left:4px solid <?= e($cat['color']) ?>">
  <span class="cat-hdr-icon"><?= e($cat['icon']) ?></span>
  <div>
    <h1><?= e($cat['name']) ?></h1>
    <p><?= e($cat['description']) ?></p>
    <div class="cat-hdr-meta"><?= $total ?> topics · <?= $cat['post_count'] ?> posts</div>
  </div>
  <?php if ($USER): ?>
    <a href="<?= u('forum/new-topic.php?cat=' . $cat['id']) ?>" class="btn-primary">+ New Topic</a>
  <?php endif; ?>
</div>

<?php if ($subs): ?>
  <div class="subcats">
    <?php foreach ($subs as $s): ?>
      <a href="<?= u('forum/category.php?slug=' . urlencode($s['slug'])) ?>" class="subcat" style="border-color:<?= e($s['color']) ?>"><?= e($s['icon']) ?> <?= e($s['name']) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="topic-list">
  <div class="tl-hdr"><span>Topic</span><span class="tl-r">Replies</span><span class="tl-r">Views</span><span class="tl-r">Activity</span></div>
  <?php foreach ($topics as $t): ?>
    <div class="tl-row <?= $t['pinned'] ? 'is-pinned' : '' ?>">
      <div class="tl-main">
        <?php if ($t['avatar']): ?>
          <img src="<?= e($t['avatar']) ?>" class="av-sm" alt="">
        <?php else: ?>
          <span class="av-sm av-init"><?= strtoupper($t['username'][0]) ?></span>
        <?php endif; ?>
        <div>
          <div class="tl-title">
            <?php if ($t['pinned']): ?><span title="Pinned">📌</span><?php endif; ?>
            <?php if ($t['closed']): ?><span title="Closed">🔒</span><?php endif; ?>
            <a href="<?= u('forum/topic.php?slug=' . urlencode($t['slug'])) ?>"><?= e($t['title']) ?></a>
          </div>
          <div class="tl-meta">
            by <a href="<?= u('users/profile.php?u=' . urlencode($t['username'])) ?>">@<?= e($t['username']) ?></a>
            <?php if ($t['last_poster'] && $t['last_poster'] !== $t['username']): ?>
              · last by @<?= e($t['last_poster']) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="tl-r"><?= max(0,(int)$t['replies']) ?></div>
      <div class="tl-r"><?= $t['views'] ?></div>
      <div class="tl-r"><span class="ago" data-ts="<?= e($t['last_post_at']) ?>"></span></div>
    </div>
  <?php endforeach; ?>
  <?php if (empty($topics)): ?>
    <div class="empty-state">
      <p>No topics yet.</p>
      <?php if ($USER): ?>
        <a href="<?= u('forum/new-topic.php?cat=' . $cat['id']) ?>" class="btn-primary">Start the first one!</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php if ($page > 1): ?><a href="?slug=<?= urlencode($slug) ?>&page=<?= $page-1 ?>" class="pg-btn">← Prev</a><?php endif; ?>
    <?php for ($i=max(1,$page-2); $i<=min($pages,$page+2); $i++): ?>
      <a href="?slug=<?= urlencode($slug) ?>&page=<?= $i ?>" class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?slug=<?= urlencode($slug) ?>&page=<?= $page+1 ?>" class="pg-btn">Next →</a><?php endif; ?>
  </nav>
<?php endif; ?>

<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
