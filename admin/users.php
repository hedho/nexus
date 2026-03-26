<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin();
$PAGE_TITLE = 'Users';
$ADMIN_PAGE = 'users';

$q    = get('q');
$page = max(1,(int)get('page','1'));
$pp   = 30;
$off  = ($page-1)*$pp;
$like = '%'.$q.'%';
$where = $q ? "WHERE username LIKE ? OR email LIKE ?" : "";
$params = $q ? [$like,$like] : [];
$users = DB::rows("SELECT * FROM users $where ORDER BY joined_at DESC LIMIT $pp OFFSET $off", $params);
$total = (int)DB::val("SELECT COUNT(*) FROM users $where", $params);
$pages = max(1,(int)ceil($total/$pp));

include __DIR__ . '/../views/partials/admin_layout.php';
?>
<div class="admin-toolbar">
  <form method="GET" class="at-form">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search users…" class="fi at-fi">
    <button type="submit" class="btn-primary">Search</button>
    <?php if ($q): ?><a href="<?= u('admin/users.php') ?>" class="btn-ghost">Clear</a><?php endif; ?>
  </form>
</div>
<div class="acard">
  <table class="atable">
    <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Posts</th><th>Status</th><th>Joined</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td>@<?= e($u['username']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="role-tag role-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
          <td><?= $u['post_count'] ?></td>
          <td>
            <?php if ($u['suspended']): ?><span class="stag suspended">Suspended</span>
            <?php elseif ($u['silenced']): ?><span class="stag silenced">Silenced</span>
            <?php else: ?><span class="stag active">Active</span><?php endif; ?>
          </td>
          <td><span class="ago" data-ts="<?= e($u['joined_at']) ?>"></span></td>
          <td><a href="<?= u('admin/user.php?id='.$u['id']) ?>" class="btn-ghost btn-sm">Manage</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="7" style="text-align:center;padding:2rem;color:#6b7280">No users found.</td></tr><?php endif; ?>
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
