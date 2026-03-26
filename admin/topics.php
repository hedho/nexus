<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin();
$PAGE_TITLE = 'Topics';
$ADMIN_PAGE = 'topics';
$q    = get('q');
$page = max(1,(int)get('page','1'));
$pp   = 30; $off = ($page-1)*$pp;
$like = '%'.$q.'%';
$where = $q ? "WHERE t.title LIKE ?" : "";
$params = $q ? [$like] : [];
$topics = DB::rows("SELECT t.*,u.username,c.name AS cat FROM topics t JOIN users u ON u.id=t.user_id JOIN categories c ON c.id=t.category_id $where ORDER BY t.created_at DESC LIMIT $pp OFFSET $off", $params);
$total = (int)DB::val("SELECT COUNT(*) FROM topics t $where", $params);
$pages = max(1,(int)ceil($total/$pp));
include __DIR__ . '/../views/partials/admin_layout.php';
?>
<div class="admin-toolbar">
  <form method="GET" class="at-form">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search topics…" class="fi at-fi">
    <button type="submit" class="btn-primary">Search</button>
    <?php if ($q): ?><a href="<?= u('admin/topics.php') ?>" class="btn-ghost">Clear</a><?php endif; ?>
  </form>
</div>
<div class="acard">
  <table class="atable">
    <thead><tr><th>Title</th><th>Category</th><th>By</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($topics as $t): ?>
        <tr>
          <td><a href="<?= u('forum/topic.php?slug='.urlencode($t['slug'])) ?>" target="_blank"><?= e(mb_substr($t['title'],0,50)) ?></a></td>
          <td><?= e($t['cat']) ?></td>
          <td>@<?= e($t['username']) ?></td>
          <td><?= $t['pinned']?'📌':'' ?><?= $t['closed']?'🔒':'' ?><?= $t['archived']?'📦':'' ?><?= (!$t['pinned']&&!$t['closed']&&!$t['archived'])?'Active':'' ?></td>
          <td>
            <?php foreach ([['pin','unpin','📌'],['close','open','🔒'],['archive','unarchive','📦']] as [$a,$b,$icon]): ?>
              <form method="POST" action="<?= u('api/topic_action.php') ?>" style="display:inline">
                <?= csrf_input() ?><input type="hidden" name="id" value="<?= $t['id'] ?>">
                <input type="hidden" name="action" value="<?= $t[$a==='pin'?'pinned':($a==='close'?'closed':'archived')] ? $b : $a ?>">
                <button class="btn-ghost btn-sm" title="<?= $a ?>"><?= $icon ?></button>
              </form>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$topics): ?><tr><td colspan="5" style="text-align:center;color:#6b7280;padding:2rem">No topics.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($pages>1): ?>
  <nav class="pager">
    <?php for($i=1;$i<=$pages;$i++): ?>
      <a href="?page=<?= $i ?>&q=<?= urlencode($q) ?>" class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php include __DIR__ . '/../views/partials/admin_layout_end.php'; ?>
