<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_login();
$err = $ok = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_ok()) {
    $bio            = sanitise(post('bio'));
    $location       = mb_substr(sanitise(post('location')), 0, 100);
    $friends_hidden = isset($_POST['friends_hidden']) ? 1 : 0;
    $avUrl          = $USER['avatar'];

    // Avatar upload
    if (!empty($_FILES['avatar']['name'])) {
        $f = $_FILES['avatar'];
        if ($f['size'] > 2*1024*1024)   { $err = 'Avatar must be under 2 MB.'; }
        elseif (!in_array($f['type'],['image/jpeg','image/png','image/gif','image/webp'])) { $err='Image files only.'; }
        else {
            $ext  = strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
            $name = 'av_'.$USER['id'].'_'.time().'.'.$ext;
            $dir  = UPLOADS.'/avatars/';
            if (!is_dir($dir)) mkdir($dir,0755,true);
            if (move_uploaded_file($f['tmp_name'],$dir.$name)) {
                $avUrl = BASE.'/public/uploads/avatars/'.$name;
            } else { $err='Upload failed.'; }
        }
    }

    if (!$err) {
        DB::run('UPDATE users SET bio=?,avatar=?,friends_hidden=?,location=? WHERE id=?',
            [$bio?:null, $avUrl, $friends_hidden, $location, $USER['id']]);

        // Password change
        $np = post('new_password');
        if ($np) {
            if (!password_verify(post('cur_password'), $USER['password'])) { $err='Current password incorrect.'; }
            elseif (strlen($np)<8) { $err='New password must be 8+ characters.'; }
            elseif ($np!==post('new_password2')) { $err='New passwords do not match.'; }
            else {
                DB::run('UPDATE users SET password=? WHERE id=?',
                    [password_hash($np,PASSWORD_BCRYPT,['cost'=>12]),$USER['id']]);
            }
        }
        if (!$err) { $ok='Profile updated!'; $USER=current_user(); }
    }
}

$PAGE_TITLE = 'Edit Profile';
include __DIR__ . '/../views/partials/layout.php';
?>
<nav class="bc">
  <a href="<?=u('/')?>">Home</a> ›
  <a href="<?=u('users/profile.php?u='.urlencode($USER['username']))?>">@<?=e($USER['username'])?></a> ›
  <span>Edit Profile</span>
</nav>
<div class="form-card">
  <h1>Edit Profile</h1>
  <?php if($err):?><div class="alert err"><?=e($err)?></div><?php endif;?>
  <?php if($ok): ?><div class="alert ok"><?=e($ok)?></div><?php endif;?>
  <form method="POST" enctype="multipart/form-data">
    <?=csrf_input()?>

    <div class="form-section">
      <h2>Profile Picture</h2>
      <div class="av-upload">
        <?php if($USER['avatar']):?>
          <img src="<?=e($USER['avatar'])?>" class="av-xl" id="avPrev" alt="">
        <?php else:?>
          <span class="av-xl av-init" id="avPrev"><?=strtoupper($USER['username'][0])?></span>
        <?php endif;?>
        <div>
          <label for="avatar" class="btn-ghost" style="cursor:pointer">Choose Image</label>
          <input type="file" id="avatar" name="avatar" accept="image/*" style="display:none" onchange="prevAv(this)">
          <p class="hint">Max 2 MB · JPG, PNG, GIF, WebP</p>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2>About Me</h2>
      <div class="fg">
        <label for="bio">Bio</label>
        <textarea name="bio" id="bio" class="fi" rows="4" maxlength="500"
                  placeholder="Tell the community about yourself…"><?=e($USER['bio']??'')?></textarea>
        <span class="hint"><span id="bioLen"><?=mb_strlen($USER['bio']??'')?></span>/500</span>
      </div>
      <div class="fg">
        <label for="location">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14" style="vertical-align:middle;margin-right:4px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Location <small class="hint" style="display:inline">(optional)</small>
        </label>
        <input type="text" name="location" id="location" class="fi" maxlength="100"
               value="<?=e($USER['location']??'')?>" placeholder="City, Country">
        <span class="hint">Shown on your posts and profile</span>
      </div>
    </div>

    <div class="form-section">
      <h2>Privacy</h2>
      <label class="toggle-label" style="cursor:pointer">
        <div>
          <strong>Hide Friends List</strong>
          <p class="hint">Others won't see your friends list on your profile</p>
        </div>
        <label class="toggle-sw">
          <input type="checkbox" name="friends_hidden" <?=$USER['friends_hidden']?'checked':''?>>
          <span class="toggle-knob"></span>
        </label>
      </label>
    </div>

    <div class="form-section">
      <h2>Change Password</h2>
      <p class="hint">Leave blank to keep your current password.</p>
      <div class="fg"><label>Current Password</label><input type="password" name="cur_password" class="fi" placeholder="Current password"></div>
      <div class="fg"><label>New Password</label><input type="password" name="new_password" class="fi" placeholder="Min. 8 characters"></div>
      <div class="fg"><label>Confirm New Password</label><input type="password" name="new_password2" class="fi" placeholder="Repeat new password"></div>
    </div>

    <div class="form-actions">
      <a href="<?=u('users/profile.php?u='.urlencode($USER['username']))?>" class="btn-ghost">Cancel</a>
      <button type="submit" class="btn-primary">Save Changes</button>
    </div>
  </form>
</div>
<script>
function prevAv(i){if(!i.files[0])return;var r=new FileReader();r.onload=function(e){var p=document.getElementById('avPrev');if(p.tagName==='IMG'){p.src=e.target.result;}else{var img=document.createElement('img');img.src=e.target.result;img.className='av-xl';img.id='avPrev';p.replaceWith(img);}};r.readAsDataURL(i.files[0]);}
document.getElementById('bio').addEventListener('input',function(){document.getElementById('bioLen').textContent=this.value.length;});
</script>

<style>
.toggle-label { display:flex; align-items:center; justify-content:space-between; padding:8px 0; }
</style>
<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
