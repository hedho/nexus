<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin_only();
$PAGE_TITLE = 'Settings';
$ADMIN_PAGE = 'settings';
$saved = false;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_ok()) {
    // Checkboxes (default 0 if unchecked)
    $checkboxes = ['allow_reg','rate_limit_enabled','post_captcha_enabled','topic_captcha_enabled','post_layout_horizontal'];
    $textfields  = ['site_name','site_desc','logo_url','topics_per_page','posts_per_page',
                    'footer_text','custom_head','rate_limit_count','rate_limit_window',
                    'max_upload_mb'];
    foreach ($checkboxes as $k) cfg_set($k, isset($_POST[$k]) ? '1' : '0');
    foreach ($textfields  as $k) cfg_set($k, post($k));
    $saved = true;
}

$s = [];
foreach (DB::rows('SELECT key,value FROM settings') as $r) $s[$r['key']] = $r['value'];
include __DIR__ . '/../views/partials/admin_layout.php';
?>
<?php if ($saved): ?><div class="alert ok">✅ Settings saved!</div><?php endif; ?>

<form method="POST">
  <?= csrf_input() ?>

  <!-- Site Identity -->
  <div class="acard" style="margin-bottom:18px">
    <div class="acard-head"><h2>🏠 Site Identity</h2></div>
    <div class="acard-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="fg"><label>Site Name</label>
          <input type="text" name="site_name" class="fi" value="<?= e($s['site_name']??'Nexus Forum') ?>"></div>
        <div class="fg"><label>Logo URL <small>(blank = text logo)</small></label>
          <input type="text" name="logo_url" class="fi" value="<?= e($s['logo_url']??'') ?>" placeholder="/public/uploads/logo.png"></div>
        <div class="fg" style="grid-column:1/-1"><label>Description</label>
          <textarea name="site_desc" class="fi" rows="2"><?= e($s['site_desc']??'') ?></textarea></div>
        <div class="fg" style="grid-column:1/-1"><label>Footer Text <small>(HTML allowed)</small></label>
          <input type="text" name="footer_text" class="fi" value="<?= e($s['footer_text']??'') ?>"></div>
      </div>
    </div>
  </div>

  <!-- Registration -->
  <div class="acard" style="margin-bottom:18px">
    <div class="acard-head"><h2>👥 Registration</h2></div>
    <div class="acard-body">
      <label class="setting-toggle">
        <div><strong>Allow Registration</strong><p class="hint">Let new users create accounts</p></div>
        <label class="toggle-sw"><input type="checkbox" name="allow_reg" <?= ($s['allow_reg']??'1')==='1'?'checked':'' ?>><span class="toggle-knob"></span></label>
      </label>
    </div>
  </div>

  <!-- Content -->
  <div class="acard" style="margin-bottom:18px">
    <div class="acard-head"><h2>📄 Pagination</h2></div>
    <div class="acard-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="fg"><label>Topics Per Page</label>
          <input type="number" name="topics_per_page" class="fi" value="<?= e($s['topics_per_page']??'30') ?>" min="5" max="100"></div>
        <div class="fg"><label>Posts Per Page</label>
          <input type="number" name="posts_per_page" class="fi" value="<?= e($s['posts_per_page']??'20') ?>" min="5" max="100"></div>

        <div class="arow">
          <div>
            <label class="form-label">Max Image Upload Size (MB)</label>
            <small style="color:var(--muted);display:block">Maximum file size per uploaded image. Range: 1–50 MB.</small>
          </div>
          <input type="number" name="max_upload_mb" class="fi" value="<?= e($s['max_upload_mb']??'5') ?>" min="1" max="50" style="width:80px"></div>
      </div>
    </div>
  </div>

  <!-- Rate Limiting -->
  <div class="acard" style="margin-bottom:18px">
    <div class="acard-head"><h2>⏱️ Post Rate Limiting</h2></div>
    <div class="acard-body">
      <label class="setting-toggle" style="margin-bottom:16px">
        <div><strong>Enable Rate Limiting</strong><p class="hint">Prevent users from posting too fast. Admins &amp; moderators are exempt.</p></div>
        <label class="toggle-sw"><input type="checkbox" name="rate_limit_enabled" id="rlToggle"
          <?= ($s['rate_limit_enabled']??'0')==='1'?'checked':'' ?>
          onchange="document.getElementById('rlSettings').style.display=this.checked?'':'none'">
          <span class="toggle-knob"></span></label>
      </label>
      <div id="rlSettings" style="<?= ($s['rate_limit_enabled']??'0')==='1'?'':'display:none' ?>;background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:16px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="fg">
            <label>Max Posts Per Window</label>
            <input type="number" name="rate_limit_count" class="fi" value="<?= e($s['rate_limit_count']??'3') ?>" min="1" max="100">
            <span class="hint">Number of posts allowed in the time window</span>
          </div>
          <div class="fg">
            <label>Time Window (seconds)</label>
            <input type="number" name="rate_limit_window" class="fi" value="<?= e($s['rate_limit_window']??'60') ?>" min="5" max="86400">
            <span class="hint">E.g. 60 = 1 minute, 3600 = 1 hour</span>
          </div>
        </div>
        <div class="rate-presets">
          <span style="font-size:12px;color:#94a3b8;margin-right:6px">Presets:</span>
          <button type="button" class="btn-ghost btn-sm" onclick="setRL(3,60)">Relaxed (3/min)</button>
          <button type="button" class="btn-ghost btn-sm" onclick="setRL(1,30)">Moderate (1/30s)</button>
          <button type="button" class="btn-ghost btn-sm" onclick="setRL(1,120)">Strict (1/2min)</button>
          <button type="button" class="btn-ghost btn-sm" onclick="setRL(5,300)">5 per 5 mins</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Post/Reply Captcha -->
  <div class="acard" style="margin-bottom:18px">
    <div class="acard-head"><h2>🔒 Post/Reply Security Captcha</h2></div>
    <div class="acard-body">
      <label class="setting-toggle" style="margin-bottom:12px">
        <div>
          <strong>Require Math Captcha to Reply</strong>
          <p class="hint">Users must solve a simple math question before posting a reply to any topic</p>
        </div>
        <label class="toggle-sw"><input type="checkbox" name="post_captcha_enabled"
          <?= ($s['post_captcha_enabled']??'0')==='1'?'checked':'' ?>><span class="toggle-knob"></span></label>
      </label>
      <label class="setting-toggle">
        <div>
          <strong>Require Math Captcha to Create Topic</strong>
          <p class="hint">Users must solve a math question when creating a new topic</p>
        </div>
        <label class="toggle-sw"><input type="checkbox" name="topic_captcha_enabled"
          <?= ($s['topic_captcha_enabled']??'0')==='1'?'checked':'' ?>><span class="toggle-knob"></span></label>
      </label>
    </div>
  </div>

  <!-- Advanced -->
  <div class="acard" style="margin-bottom:18px">
    <div class="acard-head"><h2>🔧 Advanced</h2></div>
    <div class="acard-body">
      <div class="fg">
        <label>Custom &lt;head&gt; HTML <small>(analytics, meta tags)</small></label>
        <textarea name="custom_head" class="fi" rows="4" style="font-family:var(--mono);font-size:13px"
                  placeholder="<!-- Google Analytics, custom meta, etc. -->"><?= e($s['custom_head']??'') ?></textarea>
      </div>
    </div>
  </div>

  <button type="submit" class="btn-primary btn-lg">💾 Save Settings</button>
</form>

<style>
.setting-toggle { display:flex; align-items:center; justify-content:space-between; padding:6px 0; }
.rate-presets { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; align-items:center; }
</style>
<script>
function setRL(count,window){
  document.querySelector('[name=rate_limit_count]').value  = count;
  document.querySelector('[name=rate_limit_window]').value = window;
}
</script>
<?php include __DIR__ . '/../views/partials/admin_layout_end.php'; ?>
