<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin();
$id   = (int)get('id');
$user = DB::row('SELECT * FROM users WHERE id=?',[$id]);
if (!$user) render_404();
$flash = null; $newpw = null;

// All available permissions
$ALL_PERMS = [
    'pin_topics'     => 'Pin/Unpin Topics',
    'close_topics'   => 'Close/Open Topics',
    'delete_posts'   => 'Delete Any Post',
    'edit_posts'     => 'Edit Any Post',
    'manage_users'   => 'View User List',
    'upload_images'  => 'Upload Images',
    'bypass_silence' => 'Bypass Silence',
];

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_ok()) {
    $action = post('action');

    if ($action === 'adjust_karma') {
        $amount  = (int)post('karma_amount');
        $op      = post('karma_op'); // add | subtract | set
        $reason  = sanitise(post('karma_reason'));
        if (!in_array($op, ['add','subtract','set'])) $op = 'add';
        if ($amount < 0) $amount = 0;
        $oldKarma = (int)$user['karma'];
        if ($op === 'set') {
            $newKarma = $amount;
            DB::run('UPDATE users SET karma=? WHERE id=?', [$newKarma, $id]);
        } elseif ($op === 'add') {
            $newKarma = $oldKarma + $amount;
            DB::run('UPDATE users SET karma=karma+? WHERE id=?', [$amount, $id]);
        } else {
            $newKarma = max(0, $oldKarma - $amount);
            DB::run('UPDATE users SET karma=? WHERE id=?', [$newKarma, $id]);
        }
        if ($reason) {
            add_notification($id, 'karma_admin', [
                'from'   => $USER['username'],
                'change' => $newKarma - $oldKarma,
                'new'    => $newKarma,
                'reason' => $reason,
            ]);
        }
        // Fire addon hook
        addon_hook('user_karma_changed', ['user_id'=>$id,'old'=>$oldKarma,'new'=>$newKarma,'by'=>$USER['id']]);
        $flash = ['ok', "Karma updated: {$oldKarma} → {$newKarma}" . ($reason ? " ({$reason})" : '')];
        $user  = DB::row('SELECT * FROM users WHERE id=?', [$id]); // refresh
    }

    if ($action === 'edit_profile') {
        // Admin editing another user's profile details
        $newUsername = trim(post('username'));
        $newEmail    = trim(post('email'));
        $newBio      = trim(post('bio'));
        $newRole     = post('role');

        $errs = [];
        if (strlen($newUsername)<3) $errs[]='Username too short.';
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errs[]='Invalid email.';
        if (!in_array($newRole,['member','moderator','admin'])) $newRole='member';

        // Check uniqueness (excluding current user)
        if (!$errs) {
            $dup = DB::row('SELECT id FROM users WHERE (username=? OR email=?) AND id!=?',[$newUsername,$newEmail,$id]);
            if ($dup) $errs[]='Username or email already taken.';
        }

        if (!$errs) {
            // Build permissions array for moderators
            $permsArr = [];
            if ($newRole === 'moderator' || $newRole === 'admin') {
                foreach (array_keys($ALL_PERMS) as $pkey) {
                    if (!empty($_POST['perm_'.$pkey])) $permsArr[$pkey] = true;
                }
            }
            DB::run('UPDATE users SET username=?,email=?,bio=?,role=?,permissions=? WHERE id=?',
                [$newUsername,$newEmail,$newBio,$newRole,json_encode($permsArr),$id]);

            // Handle new password
            $np = post('new_password');
            if ($np) {
                if (strlen($np)<8) { $flash='Password too short (8+ chars).'; }
                else {
                    DB::run('UPDATE users SET password=? WHERE id=?',[password_hash($np,PASSWORD_BCRYPT,['cost'=>12]),$id]);
                }
            }
            if (!$flash) { $flash='User profile updated!'; $user=DB::row('SELECT * FROM users WHERE id=?',[$id]); }
        } else {
            $flash = implode(' ', $errs);
        }
    }
    elseif ($id !== $USER['id']) {
        switch ($action) {
            case 'suspend':   DB::run('UPDATE users SET suspended=1 WHERE id=?',[$id]); $flash='User suspended.'; break;
            case 'unsuspend': DB::run('UPDATE users SET suspended=0 WHERE id=?',[$id]); $flash='User unsuspended.'; break;
            case 'silence':   DB::run('UPDATE users SET silenced=1  WHERE id=?',[$id]); $flash='User silenced.'; break;
            case 'unsilence': DB::run('UPDATE users SET silenced=0  WHERE id=?',[$id]); $flash='User unsilenced.'; break;
            case 'resetpw':
                $newpw = bin2hex(random_bytes(5));
                DB::run('UPDATE users SET password=? WHERE id=?',[password_hash($newpw,PASSWORD_BCRYPT,['cost'=>12]),$id]);
                break;
        }
        $user = DB::row('SELECT * FROM users WHERE id=?',[$id]);
    }
}

$userPerms = json_decode($user['permissions'] ?? '{}', true) ?: [];
$utopics = DB::rows("SELECT t.*,c.name AS cat FROM topics t JOIN categories c ON c.id=t.category_id WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 10",[$id]);
$uposts  = DB::rows("SELECT p.*,t.title AS tt,t.slug AS ts FROM posts p JOIN topics t ON t.id=p.topic_id WHERE p.user_id=? AND p.deleted=0 ORDER BY p.created_at DESC LIMIT 10",[$id]);

$PAGE_TITLE = 'User: @'.$user['username'];
$ADMIN_PAGE = 'users';
include __DIR__ . '/../views/partials/admin_layout.php';
?>
<p style="margin-bottom:16px"><a href="<?= u('admin/users.php') ?>">← Back to Users</a></p>

<?php if ($flash): ?>
  <div class="alert <?= strpos($flash,'!') ? 'ok' : 'err' ?>"><?= e($flash) ?></div>
<?php endif; ?>
<?php if ($newpw): ?>
  <div class="alert warn" style="font-size:14px">🔑 Password reset! New password: <strong style="font-family:monospace"><?= e($newpw) ?></strong> — share this securely with the user.</div>
<?php endif; ?>

<div class="admin-two-col" style="margin-bottom:0">
  <!-- Edit Profile Card -->
  <div class="acard">
    <div class="acard-head"><h2>✏️ Edit Profile</h2></div>
    <div class="acard-body">
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="edit_profile">

        <div class="fg">
          <label>Username</label>
          <input type="text" name="username" class="fi" required value="<?= e($user['username']) ?>" minlength="3" maxlength="30">
        </div>
        <div class="fg">
          <label>Email</label>
          <input type="email" name="email" class="fi" required value="<?= e($user['email']) ?>">
        </div>
        <div class="fg">
          <label>Bio</label>
          <textarea name="bio" class="fi" rows="3" maxlength="500"><?= e($user['bio']??'') ?></textarea>
        </div>
        <div class="fg">
          <label>Role</label>
          <select name="role" class="fi" id="roleSelect" onchange="togglePerms(this.value)" <?= $id===$USER['id']?'disabled':'' ?>>
            <option value="member"    <?= $user['role']==='member'    ?'selected':'' ?>>Member</option>
            <option value="moderator" <?= $user['role']==='moderator' ?'selected':'' ?>>Moderator</option>
            <option value="admin"     <?= $user['role']==='admin'     ?'selected':'' ?>>Admin</option>
          </select>
        </div>

        <!-- Permissions (shown for moderator) -->
        <div id="permsBox" style="<?= in_array($user['role'],['moderator','admin'])?'':'display:none' ?>">
          <div class="fg">
            <label>Moderator Permissions</label>
            <div class="perms-grid">
              <?php foreach ($ALL_PERMS as $pk => $plabel): ?>
                <label class="perm-chk">
                  <input type="checkbox" name="perm_<?= $pk ?>" value="1"
                    <?= !empty($userPerms[$pk]) ? 'checked' : '' ?>>
                  <?= e($plabel) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="fg">
          <label>Set New Password <small>(leave blank to keep)</small></label>
          <input type="text" name="new_password" class="fi" placeholder="New password (8+ chars)" autocomplete="new-password">
        </div>

        <button type="submit" class="btn-primary">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- Quick Actions Card -->
  <div>
    <div class="acard" style="margin-bottom:16px">
      <div class="acard-head"><h2>👤 User Info</h2></div>
      <div class="acard-body">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
          <?php if ($user['avatar']): ?>
            <img src="<?= e($user['avatar']) ?>" class="av-xl" alt="">
          <?php else: ?>
            <span class="av-xl av-init"><?= strtoupper($user['username'][0]) ?></span>
          <?php endif; ?>
          <div>
            <h3 style="margin-bottom:4px">@<?= e($user['username']) ?></h3>
            <p style="font-size:13px;color:#64748b;margin-bottom:6px"><?= e($user['email']) ?></p>
            <span class="role-tag role-<?= e($user['role']) ?>"><?= e($user['role']) ?></span>
            <?php if ($user['suspended']): ?> <span class="stag suspended">Suspended</span><?php endif; ?>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
          <div>📝 Posts: <strong><?= $user['post_count'] ?></strong></div>
          <div>💬 Topics: <strong><?= $user['topic_count'] ?></strong></div>
          <div>⭐ Karma: <strong><?= $user['karma'] ?></strong></div>
          <div>📅 Joined: <span class="ago" data-ts="<?= e($user['joined_at']) ?>"></span></div>
        </div>
        <a href="<?= u('users/profile.php?u='.urlencode($user['username'])) ?>" target="_blank" class="btn-ghost btn-sm" style="margin-top:12px">View Profile →</a>
      </div>
    </div>

    <?php if ($id !== $USER['id']): ?>
    <div class="acard" style="margin-bottom:16px">
      <div class="acard-head"><h2>⭐ Karma Management</h2>
        <?php $kTier = karma_tier((int)$user['karma']); ?>
        <span style="font-size:13px;color:<?= e($kTier['color']) ?>"><?= $kTier['icon'] ?> <?= e($kTier['name']) ?> — <?= number_format((int)$user['karma']) ?> pts</span>
      </div>
      <div class="acard-body">
        <!-- Progress bar -->
        <div style="margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:4px">
            <span><?= e($kTier['name']) ?> (<?= $kTier['min'] ?>)</span>
            <?php if ($kTier['next']): ?>
              <span><?= e(karma_tier($kTier['next'])['name']) ?> (<?= $kTier['next'] ?>)</span>
            <?php else: ?>
              <span>Max tier 🏆</span>
            <?php endif; ?>
          </div>
          <div style="height:8px;background:var(--border);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?= $kTier['progress'] ?>%;background:<?= e($kTier['color']) ?>;border-radius:4px;transition:width .4s"></div>
          </div>
          <div style="font-size:11px;color:var(--faint);margin-top:3px;text-align:right"><?= $kTier['progress'] ?>% to next tier</div>
        </div>

        <form method="POST">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="adjust_karma">
          <div style="display:grid;grid-template-columns:120px 1fr;gap:10px;margin-bottom:10px">
            <div class="fg" style="margin:0">
              <label style="font-size:12px">Operation</label>
              <select name="karma_op" class="fi" style="padding:7px 10px">
                <option value="add">➕ Add points</option>
                <option value="subtract">➖ Subtract points</option>
                <option value="set">🎯 Set exact value</option>
              </select>
            </div>
            <div class="fg" style="margin:0">
              <label style="font-size:12px">Amount</label>
              <input type="number" name="karma_amount" class="fi" value="10" min="0" max="999999"
                     style="font-family:var(--mono);font-size:16px;text-align:center">
            </div>
          </div>
          <div class="fg" style="margin-bottom:10px">
            <label style="font-size:12px">Reason <small style="color:var(--faint)">(optional — notifies user)</small></label>
            <input type="text" name="karma_reason" class="fi" placeholder="e.g. Helpful answer, code of conduct violation…">
          </div>
          <button type="submit" class="btn-primary btn-sm">Update Karma</button>
        </form>
      </div>
    </div>

    <div class="acard">
      <div class="acard-head"><h2>⚡ Quick Actions</h2></div>
      <div class="acard-body">
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <?php if (!$user['suspended']): ?>
            <form method="POST" style="display:inline"><?= csrf_input() ?><input type="hidden" name="action" value="suspend">
              <button class="btn-warn btn-sm" onclick="return confirm('Suspend this user?')">🚫 Suspend</button></form>
          <?php else: ?>
            <form method="POST" style="display:inline"><?= csrf_input() ?><input type="hidden" name="action" value="unsuspend">
              <button class="btn-ok btn-sm">✅ Unsuspend</button></form>
          <?php endif; ?>
          <?php if (!$user['silenced']): ?>
            <form method="POST" style="display:inline"><?= csrf_input() ?><input type="hidden" name="action" value="silence">
              <button class="btn-warn btn-sm" onclick="return confirm('Silence this user?')">🔇 Silence</button></form>
          <?php else: ?>
            <form method="POST" style="display:inline"><?= csrf_input() ?><input type="hidden" name="action" value="unsilence">
              <button class="btn-ok btn-sm">🔊 Unsilence</button></form>
          <?php endif; ?>
          <form method="POST" style="display:inline"><?= csrf_input() ?><input type="hidden" name="action" value="resetpw">
            <button class="btn-ghost btn-sm" onclick="return confirm('Generate new random password?')">🔑 Reset PW</button></form>
        </div>
      </div>
    </div>
    <?php else: ?>
      <div class="acard"><div class="acard-body"><p style="color:#64748b;font-size:13px">You cannot moderate your own account.</p></div></div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent topics & posts -->
<div class="admin-two-col" style="margin-top:16px">
  <div class="acard">
    <div class="acard-head"><h2>Recent Topics</h2></div>
    <table class="atable">
      <thead><tr><th>Title</th><th>Category</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($utopics as $t): ?>
          <tr><td><a href="<?= u('forum/topic.php?slug='.urlencode($t['slug'])) ?>" target="_blank"><?= e(mb_substr($t['title'],0,50)) ?></a></td>
          <td><?= e($t['cat']) ?></td><td><span class="ago" data-ts="<?= e($t['created_at']) ?>"></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$utopics): ?><tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:1.5rem">No topics.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="acard">
    <div class="acard-head"><h2>Recent Posts</h2></div>
    <table class="atable">
      <thead><tr><th>In Topic</th><th>Preview</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($uposts as $p): ?>
          <tr><td><a href="<?= u('forum/topic.php?slug='.urlencode($p['ts']).'#post-'.$p['id']) ?>" target="_blank"><?= e(mb_substr($p['tt'],0,30)) ?></a></td>
          <td><?= e(mb_substr($p['content'],0,60)) ?>…</td><td><span class="ago" data-ts="<?= e($p['created_at']) ?>"></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$uposts): ?><tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:1.5rem">No posts.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function togglePerms(role) {
  document.getElementById('permsBox').style.display = (role==='moderator'||role==='admin') ? '' : 'none';
}
</script>

<?php include __DIR__ . '/../views/partials/admin_layout_end.php'; ?>
