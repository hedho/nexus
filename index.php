<?php
require_once __DIR__ . '/includes/bootstrap.php';

$PAGE_TITLE = cfg('site_name', 'Nexus Forum');

$cats = DB::rows("
    SELECT c.*,
      (SELECT t.title FROM topics t WHERE t.category_id=c.id ORDER BY t.last_post_at DESC LIMIT 1) AS last_title,
      (SELECT t.slug  FROM topics t WHERE t.category_id=c.id ORDER BY t.last_post_at DESC LIMIT 1) AS last_slug
    FROM categories c WHERE c.parent_id IS NULL ORDER BY c.position, c.id
");

$recent = DB::rows("
    SELECT t.*, u.username, u.avatar, c.name AS cat_name, c.slug AS cat_slug, c.color AS cat_color
    FROM topics t
    JOIN users u ON u.id = t.user_id
    JOIN categories c ON c.id = t.category_id
    WHERE t.archived = 0
    ORDER BY t.last_post_at DESC LIMIT 15
");

$stats = [
    'topics' => (int) DB::val('SELECT COUNT(*) FROM topics'),
    'posts'  => (int) DB::val('SELECT COUNT(*) FROM posts WHERE deleted=0'),
    'users'  => (int) DB::val('SELECT COUNT(*) FROM users'),
    'newest' => DB::row('SELECT username FROM users ORDER BY joined_at DESC LIMIT 1'),
];

include __DIR__ . '/views/partials/layout.php';
?>

<div class="home-hero">
  <h1><?= e(cfg('site_name','Nexus Forum')) ?></h1>
  <p><?= e(cfg('site_desc','A community for discussion.')) ?></p>
  <?php if (!$USER): ?>
    <div class="hero-btns">
      <a href="<?= u('auth/register.php') ?>" class="btn-primary btn-lg">Join the Community</a>
      <a href="<?= u('auth/login.php') ?>" class="btn-ghost btn-lg">Log In</a>
    </div>
  <?php else: ?>
    <a href="<?= u('forum/new-topic.php') ?>" class="btn-primary btn-lg">+ New Topic</a>
  <?php endif; ?>
</div>

<div class="stats-row">
  <div class="stat"><strong><?= number_format($stats['topics']) ?></strong><span>Topics</span></div>
  <div class="stat-div"></div>
  <div class="stat"><strong><?= number_format($stats['posts']) ?></strong><span>Posts</span></div>
  <div class="stat-div"></div>
  <div class="stat"><strong><?= number_format($stats['users']) ?></strong><span>Members</span></div>
  <?php if ($stats['newest']): ?>
    <div class="stat-div"></div>
    <div class="stat"><span>Newest: <a href="<?= u('users/profile.php?u=' . urlencode($stats['newest']['username'])) ?>">@<?= e($stats['newest']['username']) ?></a></span></div>
  <?php endif; ?>
</div>

<div class="home-grid">
  <section>
    <h2 class="sec-title">Categories</h2>
    <?php foreach ($cats as $c): ?>
      <a href="<?= u('forum/category.php?slug=' . urlencode($c['slug'])) ?>" class="cat-card">
        <div class="cat-stripe" style="background:<?= e($c['color']) ?>"></div>
        <div class="cat-icon" style="color:<?= e($c['color']) ?>"><?= e($c['icon']) ?></div>
        <div class="cat-body">
          <div class="cat-name"><?= e($c['name']) ?></div>
          <div class="cat-desc"><?= e($c['description']) ?></div>
          <div class="cat-stats"><?= $c['topic_count'] ?> topics · <?= $c['post_count'] ?> posts</div>
        </div>
        <div class="cat-chevron">›</div>
      </a>
    <?php endforeach; ?>
    <?php if (empty($cats)): ?>
      <p class="empty-msg">No categories yet.</p>
    <?php endif; ?>
  </section>

  <section>
    <h2 class="sec-title">Latest Activity</h2>
    <?php foreach ($recent as $t): ?>
      <div class="topic-row">
        <?php if ($t['avatar']): ?>
          <img src="<?= e($t['avatar']) ?>" class="av-sm" alt="">
        <?php else: ?>
          <span class="av-sm av-init"><?= strtoupper($t['username'][0]) ?></span>
        <?php endif; ?>
        <div class="tr-body">
          <a href="<?= u('forum/topic.php?slug=' . urlencode($t['slug'])) ?>" class="tr-title">
            <?= e($t['title']) ?>
          </a>
          <div class="tr-meta">
            <a href="<?= u('forum/category.php?slug=' . urlencode($t['cat_slug'])) ?>" class="cat-tag" style="--cc:<?= e($t['cat_color']) ?>"><?= e($t['cat_name']) ?></a>
            by <a href="<?= u('users/profile.php?u=' . urlencode($t['username'])) ?>">@<?= e($t['username']) ?></a>
            <span class="ago" data-ts="<?= e($t['last_post_at']) ?>"></span>
          </div>
        </div>
        <div class="tr-counts">
          <span>💬 <?= $t['reply_count'] ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($recent)): ?>
      <p class="empty-msg">No topics yet. <a href="<?= u('forum/new-topic.php') ?>">Start one!</a></p>
    <?php endif; ?>
  </section>
</div>

<?php include __DIR__ . '/views/partials/layout_end.php'; ?>
