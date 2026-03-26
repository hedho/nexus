<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$slug  = get('slug');
$topic = DB::row("
    SELECT t.*, u.username, u.avatar,
           c.name AS cat_name, c.slug AS cat_slug, c.color AS cat_color
    FROM topics t
    JOIN users u ON u.id = t.user_id
    JOIN categories c ON c.id = t.category_id
    WHERE t.slug = ?
", [$slug]);
if (!$topic) render_404();

// Fetch full category for permission checks
$topicCat = DB::row('SELECT * FROM categories WHERE id=?', [$topic['category_id']]);
if (!can_read_category($topicCat ?? [])) render_403();

DB::run('UPDATE topics SET views = views + 1 WHERE id = ?', [$topic['id']]);

$page = max(1, (int)get('page','1'));
$pp   = max(1, (int)cfg('posts_per_page','20'));

// ?goto=POST_ID — jump directly to a specific post, calculating its page
$gotoId = (int)get('goto');
if ($gotoId) {
    // Find what position this post is in the thread (0-indexed)
    $postPos = (int)DB::val(
        'SELECT COUNT(*) FROM posts WHERE topic_id=? AND deleted=0 AND id<=?',
        [$topic['id'], $gotoId]
    );
    if ($postPos > 0) {
        $targetPage = (int)ceil($postPos / $pp);
        if ($targetPage !== $page) {
            // Redirect to the correct page with the anchor
            $redirectUrl = u('forum/topic.php?slug='.urlencode($slug).'&page='.$targetPage).'#post-'.$gotoId;
            header('Location: ' . $redirectUrl);
            exit;
        }
        // Already on the right page — anchor will handle scrolling
        $page = $targetPage;
    }
}

$off  = ($page - 1) * $pp;

$posts = DB::rows("
    SELECT p.*, u.username, u.avatar, u.role, u.post_count, u.karma, u.location,
           (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes
    FROM posts p
    JOIN users u ON u.id = p.user_id
    WHERE p.topic_id = ? AND p.deleted = 0
    ORDER BY p.post_num
    LIMIT ? OFFSET ?
", [$topic['id'], $pp, $off]);

$total = (int)DB::val('SELECT COUNT(*) FROM posts WHERE topic_id=? AND deleted=0', [$topic['id']]);
$pages = max(1, (int)ceil($total / $pp));

$tags = DB::rows("
    SELECT tg.* FROM tags tg
    JOIN topic_tags tt ON tg.id = tt.tag_id
    WHERE tt.topic_id = ?
", [$topic['id']]);

$liked = [];
if ($USER) {
    foreach (DB::rows('SELECT post_id FROM likes WHERE user_id=?', [$USER['id']]) as $l)
        $liked[$l['post_id']] = true;
}

// Post captcha for reply box
$post_cap = post_captcha_enabled() ? post_captcha_generate() : null;

$PAGE_TITLE = $topic['title'];
include __DIR__ . '/../views/partials/layout.php';
?>

<nav class="bc">
  <a href="<?= u('/') ?>">Home</a> ›
  <a href="<?= u('forum/category.php?slug='.urlencode($topic['cat_slug'])) ?>"><?= e($topic['cat_name']) ?></a> ›
  <span><?= e(mb_substr($topic['title'],0,60)) ?></span>
</nav>

<!-- Topic header -->
<div class="topic-hdr">
  <div class="topic-hdr-main">
    <div class="topic-badges">
      <?php if ($topic['pinned']): ?><span class="tbadge pin">📌 Pinned</span><?php endif; ?>
      <?php if ($topic['closed']): ?><span class="tbadge closed">🔒 Closed</span><?php endif; ?>
    </div>
    <h1 class="topic-title"><?= e($topic['title']) ?></h1>
    <div class="topic-meta">
      <a href="<?= u('forum/category.php?slug='.urlencode($topic['cat_slug'])) ?>"
         class="cat-tag" style="--cc:<?= e($topic['cat_color']) ?>"><?= e($topic['cat_name']) ?></a>
      <?php foreach ($tags as $tg): ?>
        <span class="tag"><?= e($tg['name']) ?></span>
      <?php endforeach; ?>
      <span>·</span>
      <span><?= max(0, $total - 1) ?> <?= $total === 2 ? 'reply' : 'replies' ?></span>
      <span>·</span>
      <span><?= number_format($topic['views']) ?> views</span>
    </div>
  </div>
  <?php if ($USER && is_admin()): ?>
    <div class="topic-mod">
      <?php foreach ([
        [$topic['pinned'],   'unpin',    'pin',       '📌', $topic['pinned']   ? 'Unpin'   : 'Pin'],
        [$topic['closed'],   'open',     'close',     '🔒', $topic['closed']   ? 'Open'    : 'Close'],
        [$topic['archived'], 'unarchive','archive',   '📦', $topic['archived'] ? 'Unarchive': 'Archive'],
      ] as [$state, $onAct, $offAct, $icon, $label]): ?>
        <form method="POST" action="<?= u('api/topic_action.php') ?>" style="display:inline">
          <?= csrf_input() ?>
          <input type="hidden" name="id"     value="<?= $topic['id'] ?>">
          <input type="hidden" name="action" value="<?= $state ? $onAct : $offAct ?>">
          <button class="btn-sm btn-ghost"><?= $icon ?> <?= $label ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php
/* ── render_topic_header addon hook (collector) ── */
echo addon_collect('render_topic_header', $topic);
?>

<!-- Posts list -->
<?php $postLayoutH = cfg('post_layout_horizontal','0') === '1'; ?>
<div id="postsList" class="<?= $postLayoutH ? 'posts-horizontal' : '' ?>">
<?php foreach ($posts as $p): ?>
  <div class="post <?= $p['post_num'] == 1 ? 'post-op' : '' ?><?= $postLayoutH ? ' post-h' : '' ?>" id="post-<?= $p['id'] ?>">

    <!-- Author sidebar -->
    <div class="post-side">
      <?php if ($p['avatar']): ?>
        <img src="<?= e($p['avatar']) ?>" class="av-lg" alt="">
      <?php else: ?>
        <span class="av-lg av-init"><?= strtoupper($p['username'][0]) ?></span>
      <?php endif; ?>
      <a href="<?= u('users/profile.php?u='.urlencode($p['username'])) ?>" class="post-name">
        @<?= e($p['username']) ?>
      </a>
      <?php if ($p['role'] === 'admin'):     ?><span class="role-flair admin">Admin</span><?php endif; ?>
      <?php if ($p['role'] === 'moderator'): ?><span class="role-flair mod">Mod</span><?php endif; ?>
      <span class="post-pcnt"><?= $p['post_count'] ?> posts</span>
      <?php if (!empty($p['location'])): ?>
        <span class="post-location" title="Location">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="9" height="9"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?= e(mb_substr($p['location'],0,20)) ?>
        </span>
      <?php endif; ?>
      <div class="post-karma-badge">
        <span class="pkb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" width="10" height="10"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>
        </span>
        <span class="pkb-val"><?= number_format((int)$p['karma']) ?></span>
      </div>
    </div>

    <!-- Post body -->
    <div class="post-body">
      <div class="post-meta-bar">
        <a class="pnum" href="#post-<?= $p['id'] ?>" title="Permalink to this post">#<?= $p['post_num'] ?></a>
        <button class="post-link-btn" title="Copy link to this post"
                onclick="copyPostLink('<?= $p['id'] ?>',this)"
                data-url="<?= e(rtrim(u('forum/topic.php?slug='.urlencode($slug)), '/')) ?>&amp;goto=<?= $p['id'] ?>">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
          </svg>
        </button>
        <time class="ago" data-ts="<?= e($p['created_at']) ?>"></time>
        <?php if ($p['edited']): ?>
          <em class="edit-lbl" title="<?= e($p['edit_reason'] ?? '') ?>">(edited)</em>
        <?php endif; ?>
        <div class="post-acts">
          <?php if ($USER): ?>
            <button class="pa-btn like-btn <?= isset($liked[$p['id']]) ? 'liked' : '' ?>"
                    onclick="doLike(<?= $p['id'] ?>,this)">
              ♥ <span class="lc"><?= $p['likes'] ?></span>
            </button>
            <?php if (!$topic['closed']): ?>
              <button class="pa-btn" onclick="doQuote(<?= $p['id'] ?>,'<?= e($p['username']) ?>')">↩ Quote</button>
            <?php endif; ?>
            <?php if ($USER['id'] == $p['user_id'] || is_admin()): ?>
              <button class="pa-btn" onclick="doEdit(<?= $p['id'] ?>)">✏ Edit</button>
              <button class="pa-btn del" onclick="doDelete(<?= $p['id'] ?>)">🗑 Delete</button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($p['reply_to']): ?>
        <div class="reply-ref">
          ↩ In reply to <a href="#post-<?= $p['reply_to'] ?>">#<?= $p['reply_to'] ?></a>
        </div>
      <?php endif; ?>

      <!-- Rendered content — server-side markdown + embeds -->
      <div class="post-content rendered-post" id="pc-<?= $p['id'] ?>"
           data-raw="<?= e(base64_encode($p['content'])) ?>">
        <?= addon_hook('render_post_content', render_post($p['content'])) ?>
      </div>

      <?php /* ── render_post_footer addon hook (collector) ── */
      echo addon_collect('render_post_footer', $p);
      ?>

      <!-- Edit box — hidden by default, toggled by doEdit() -->
      <div class="edit-box" id="eb-<?= $p['id'] ?>">
        <div class="ed-toolbar">
          <button type="button" onclick="fmt('bold','et-<?= $p['id'] ?>')"><b>B</b></button>
          <button type="button" onclick="fmt('italic','et-<?= $p['id'] ?>')"><i>I</i></button>
          <button type="button" onclick="fmt('quote','et-<?= $p['id'] ?>')" title="Quote"><svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13" style="vertical-align:middle"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg></button>
          <button type="button" onclick="pickImg('pi-<?= $p['id'] ?>','et-<?= $p['id'] ?>')">🖼</button>
          <input type="file" id="pi-<?= $p['id'] ?>" accept="image/*" style="display:none"
                 onchange="uploadImg(this,'et-<?= $p['id'] ?>')">
        </div>
        <textarea id="et-<?= $p['id'] ?>" class="edit-ta fi" rows="7"
                  placeholder="Edit your post…"></textarea>
        <input type="text" id="er-<?= $p['id'] ?>" class="fi edit-reason-inp"
               placeholder="Edit reason (optional)" style="margin-top:6px">
        <div class="edit-btns">
          <button class="btn-ghost btn-sm" onclick="cancelEdit(<?= $p['id'] ?>)">Cancel</button>
          <button class="btn-primary btn-sm" onclick="saveEdit(<?= $p['id'] ?>)">Save Changes</button>
        </div>
      </div>

    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php if ($page > 1): ?>
      <a href="?slug=<?= urlencode($slug) ?>&page=<?= $page-1 ?>" class="pg-btn">← Prev</a>
    <?php endif; ?>
    <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
      <a href="?slug=<?= urlencode($slug) ?>&page=<?= $i ?>"
         class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?>
      <a href="?slug=<?= urlencode($slug) ?>&page=<?= $page+1 ?>" class="pg-btn">Next →</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<!-- Reply box -->
<?php if ($USER && !$topic['closed']): ?>
  <div class="reply-box" id="replyBox">
    <div class="reply-hdr">
      <?php if ($USER['avatar']): ?>
        <img src="<?= e($USER['avatar']) ?>" class="av-md" alt="">
      <?php else: ?>
        <span class="av-md av-init"><?= strtoupper($USER['username'][0]) ?></span>
      <?php endif; ?>
      <span>Reply as <strong>@<?= e($USER['username']) ?></strong></span>
    </div>

    <div class="ed-toolbar">
      <button type="button" onclick="fmt('bold')"><b>B</b></button>
      <button type="button" onclick="fmt('italic')"><i>I</i></button>
      <button type="button" onclick="fmt('link')">🔗</button>
      <button type="button" onclick="fmt('quote')" title="Quote"><svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13" style="vertical-align:middle"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg></button>
      <button type="button" onclick="fmt('codeblock')">📄</button>
      <button type="button" onclick="fmt('heading')">H</button>
      <button type="button" onclick="fmt('ul')">≡</button>
      <div class="ed-sep"></div>
      <button type="button" onclick="pickImg('mainImg','replyTa')">🖼</button>
      <input type="file" id="mainImg" accept="image/*" style="display:none"
             onchange="uploadImg(this,'replyTa')">
      <div class="ed-sep"></div>
      <button type="button" id="prevBtn" onclick="togglePreview()">👁 Preview</button>
    </div>

    <div class="ed-panes">
      <textarea id="replyTa" class="reply-ta"
                placeholder="Write your reply… Markdown supported.&#10;Paste or drag images to embed.&#10;Type @username to mention someone."
                rows="8"></textarea>
      <div id="replyPreview" class="reply-preview hidden"></div>
    </div>

    <div class="reply-footer">
      <span class="hint">Markdown · Images · @mentions · Auto-embeds YouTube &amp; more</span>
      <div class="reply-footer-right">
        <span id="charCnt" class="cnt">0</span>
        <button class="btn-primary" id="replyBtn"
                onclick="sendReply('<?= e($topic['slug']) ?>')">Post Reply</button>
      </div>
    </div>

    <?php if ($post_cap): ?>
    <div class="post-captcha-row" id="postCaptchaRow">
      <span class="post-captcha-label">
        🔒 Security — What is <strong class="captcha-q"><?= e($post_cap['q']) ?></strong>
      </span>
      <input type="number" id="postCaptchaInput" class="fi post-captcha-inp"
             placeholder="?" autocomplete="off" min="-99" max="99">
      <span class="hint">Solve to post</span>
    </div>
    <?php endif; ?>

    <div class="rate-limit-info" id="rateLimitInfo" style="display:none">
      <div class="rate-limit-bar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span id="rateLimitMsg">Please wait before posting again:</span>
        <div class="rate-countdown" id="rateCountdown"></div>
      </div>
    </div>
  </div>

<?php elseif (!$USER): ?>
  <div class="reply-cta">
    <p>Join the discussion!</p>
    <a href="<?= u('auth/login.php') ?>" class="btn-ghost">Log In</a>
    <a href="<?= u('auth/register.php') ?>" class="btn-primary">Sign Up to Reply</a>
  </div>

<?php else: ?>
  <div class="closed-notice">🔒 This topic is closed to new replies.</div>
<?php endif; ?>

<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
