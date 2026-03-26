<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin();
$PAGE_TITLE = 'Categories';
$ADMIN_PAGE = 'categories';
$flash = null;

// Role permission helper
function roleSelect(string $name, string $current, string $label): string {
    $opts = [
        'guest'     => '🌐 Everyone (guests)',
        'member'    => '👤 Members only',
        'moderator' => '🛡️ Moderators+',
        'admin'     => '👑 Admins only',
    ];
    $html = '<div class="fg" style="margin-bottom:8px">'
          . '<label style="font-size:12px;color:var(--muted)">' . htmlspecialchars($label) . '</label>'
          . '<select name="' . htmlspecialchars($name) . '" class="fi" style="padding:6px 8px;font-size:13px">';
    foreach ($opts as $val => $lbl) {
        $html .= '<option value="' . $val . '"' . ($current === $val ? ' selected' : '') . '>' . $lbl . '</option>';
    }
    return $html . '</select></div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action     = post('action');
    $validRoles = ['guest', 'member', 'moderator', 'admin'];

    if ($action === 'create') {
        $name = post('name');
        if ($name) {
            $readRole  = in_array(post('read_role'),  $validRoles) ? post('read_role')  : 'guest';
            $postRole  = in_array(post('post_role'),  $validRoles) ? post('post_role')  : 'member';
            $replyRole = in_array(post('reply_role'), $validRoles) ? post('reply_role') : 'member';
            $slug = unique_slug($name, 'categories');
            DB::insert(
                'INSERT INTO categories (name,slug,description,color,icon,position,parent_id,read_role,post_role,reply_role)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$name, $slug, post('desc'), post('color', '#3b82f6'), post('icon', '💬'),
                 (int)post('pos', 99), post('parent_id') ?: null, $readRole, $postRole, $replyRole]
            );
            $flash = ['ok', 'Category created!'];
        }
    } elseif ($action === 'update') {
        $readRole  = in_array(post('read_role'),  $validRoles) ? post('read_role')  : 'guest';
        $postRole  = in_array(post('post_role'),  $validRoles) ? post('post_role')  : 'member';
        $replyRole = in_array(post('reply_role'), $validRoles) ? post('reply_role') : 'member';
        DB::run(
            'UPDATE categories SET name=?,description=?,color=?,icon=?,position=?,read_role=?,post_role=?,reply_role=? WHERE id=?',
            [post('name'), post('desc'), post('color', '#3b82f6'), post('icon', '💬'),
             (int)post('pos'), $readRole, $postRole, $replyRole, (int)post('id')]
        );
        $flash = ['ok', 'Saved!'];
    } elseif ($action === 'delete') {
        $cid = (int)post('id');
        $tc  = (int)DB::val('SELECT COUNT(*) FROM topics WHERE category_id=?', [$cid]);
        if ($tc > 0) {
            $flash = ['err', 'Cannot delete a category that has topics.'];
        } else {
            DB::run('DELETE FROM categories WHERE id=?', [$cid]);
            $flash = ['ok', 'Deleted.'];
        }
    }
}

$cats = DB::rows('SELECT * FROM categories ORDER BY position, id');
include __DIR__ . '/../views/partials/admin_layout.php';
?>

<?php if ($flash): ?>
  <div class="alert <?= $flash[0] ?>"><?= e($flash[1]) ?></div>
<?php endif; ?>

<div class="admin-two-col">

  <!-- ── Category list ──────────────────────────── -->
  <div class="acard">
    <div class="acard-head"><h2>All Categories</h2></div>
    <table class="atable">
      <thead>
        <tr><th>Name</th><th>Permissions</th><th>Topics</th><th>Posts</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($cats as $c): ?>
          <tr id="cr-<?= $c['id'] ?>">
            <td>
              <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= e($c['color']) ?>;margin-right:6px;vertical-align:middle"></span>
              <?= e($c['icon']) ?> <?= e($c['name']) ?>
            </td>
            <td style="font-size:11px;color:var(--muted)">
              Read: <strong><?= e($c['read_role'] ?? 'guest') ?></strong> ·
              Post: <strong><?= e($c['post_role'] ?? 'member') ?></strong> ·
              Reply: <strong><?= e($c['reply_role'] ?? 'member') ?></strong>
            </td>
            <td><?= $c['topic_count'] ?></td>
            <td><?= $c['post_count'] ?></td>
            <td style="white-space:nowrap">
              <button class="btn-ghost btn-sm" onclick="toggleEdit(<?= $c['id'] ?>)">Edit</button>
              <form method="POST" style="display:inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button class="btn-danger btn-sm" onclick="return confirm('Delete this category?')">Delete</button>
              </form>
            </td>
          </tr>

          <!-- Inline edit row -->
          <tr id="ce-<?= $c['id'] ?>" style="display:none;background:#f8fafc">
            <td colspan="5" style="padding:16px 18px">
              <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">

                <div style="display:grid;grid-template-columns:1fr 60px 80px 70px;gap:10px;margin-bottom:10px">
                  <div class="fg" style="margin:0">
                    <label style="font-size:12px;color:var(--muted)">Name</label>
                    <input type="text" name="name" value="<?= e($c['name']) ?>" class="fi" required>
                  </div>
                  <div class="fg" style="margin:0">
                    <label style="font-size:12px;color:var(--muted)">Icon</label>
                    <input type="text" name="icon" value="<?= e($c['icon']) ?>" class="fi" style="font-size:1.3rem;text-align:center">
                  </div>
                  <div class="fg" style="margin:0">
                    <label style="font-size:12px;color:var(--muted)">Color</label>
                    <input type="color" name="color" value="<?= e($c['color']) ?>" class="fi" style="height:40px;padding:2px">
                  </div>
                  <div class="fg" style="margin:0">
                    <label style="font-size:12px;color:var(--muted)">Order</label>
                    <input type="number" name="pos" value="<?= $c['position'] ?>" class="fi" min="0">
                  </div>
                </div>

                <div style="margin-bottom:12px">
                  <label style="font-size:12px;color:var(--muted)">Description</label>
                  <input type="text" name="desc" value="<?= e($c['description']) ?>" class="fi" placeholder="Short description">
                </div>

                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:12px">
                  <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:10px">🔐 Role Permissions</div>
                  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <?= roleSelect('read_role',  $c['read_role']  ?? 'guest',  'Who can read') ?>
                    <?= roleSelect('post_role',  $c['post_role']  ?? 'member', 'Who can post topics') ?>
                    <?= roleSelect('reply_role', $c['reply_role'] ?? 'member', 'Who can reply') ?>
                  </div>
                </div>

                <div style="display:flex;gap:8px;justify-content:flex-end">
                  <button type="button" class="btn-ghost btn-sm" onclick="toggleEdit(<?= $c['id'] ?>)">Cancel</button>
                  <button type="submit" class="btn-primary btn-sm">Save Changes</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($cats)): ?>
          <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--muted)">No categories yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Create category ────────────────────────── -->
  <div class="acard">
    <div class="acard-head"><h2>Create Category</h2></div>
    <div class="acard-body">
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="create">

        <div class="fg">
          <label>Name <span class="req">*</span></label>
          <input type="text" name="name" class="fi" required placeholder="Category name">
        </div>

        <div class="fg">
          <label>Description</label>
          <input type="text" name="desc" class="fi" placeholder="Short description (optional)">
        </div>

        <div style="display:grid;grid-template-columns:1fr 70px 90px 80px;gap:10px;margin-bottom:16px">
          <div class="fg" style="margin:0">
            <label>Icon</label>
            <input type="text" name="icon" class="fi" value="💬" style="font-size:1.3rem;text-align:center">
          </div>
          <div class="fg" style="margin:0">
            <label>Color</label>
            <input type="color" name="color" class="fi" value="#3b82f6" style="height:40px;padding:2px">
          </div>
          <div class="fg" style="margin:0">
            <label>Position</label>
            <input type="number" name="pos" class="fi" value="99" min="0">
          </div>
        </div>

        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:16px">
          <div style="font-size:13px;font-weight:600;margin-bottom:10px">🔐 Role Permissions</div>
          <div style="display:grid;grid-template-columns:1fr;gap:8px">
            <?= roleSelect('read_role',  'guest',  'Who can read this category') ?>
            <?= roleSelect('post_role',  'member', 'Who can create topics') ?>
            <?= roleSelect('reply_role', 'member', 'Who can reply to topics') ?>
          </div>
        </div>

        <div class="fg">
          <label>Parent Category <small>(optional)</small></label>
          <select name="parent_id" class="fi">
            <option value="">None (top-level)</option>
            <?php foreach (array_filter($cats, fn($c) => !$c['parent_id']) as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['icon']) ?> <?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn-primary" style="width:100%">Create Category</button>
      </form>
    </div>
  </div>

</div>

<script>
function toggleEdit(id) {
  var row  = document.getElementById('cr-' + id);
  var edit = document.getElementById('ce-' + id);
  var show = edit.style.display === 'none';
  edit.style.display = show ? '' : 'none';
  row.style.opacity  = show ? '0.4' : '1';
}
</script>

<?php include __DIR__ . '/../views/partials/admin_layout_end.php'; ?>
