<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin_only();
$PAGE_TITLE = 'Themes & Templates';
$ADMIN_PAGE = 'themes';
$flash = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_ok()) {
    $action = post('action');

    if ($action === 'create' || $action === 'update') {
        $name = post('name');
        $css  = post('css');
        if ($name) {
            if ($action === 'create') {
                $slug = unique_slug($name, 'themes');
                DB::insert('INSERT INTO themes (name,slug,css) VALUES (?,?,?)', [$name,$slug,$css]);
                $flash = ['ok','Theme created!'];
            } else {
                $tid = (int)post('id');
                DB::run('UPDATE themes SET name=?,css=? WHERE id=?', [$name,$css,$tid]);
                $flash = ['ok','Theme updated!'];
            }
        }
    } elseif ($action === 'activate') {
        $tid = (int)post('id');
        DB::run('UPDATE themes SET is_active=0');
        DB::run('UPDATE themes SET is_active=1 WHERE id=?', [$tid]);
        $flash = ['ok','Theme activated!'];
    } elseif ($action === 'deactivate') {
        DB::run('UPDATE themes SET is_active=0');
        $flash = ['ok','Default theme restored.'];
    } elseif ($action === 'delete') {
        $tid = (int)post('id');
        DB::run('DELETE FROM themes WHERE id=?', [$tid]);
        $flash = ['ok','Theme deleted.'];
    }
}

$themes = DB::rows('SELECT * FROM themes ORDER BY id');
$editId = (int)get('edit');
$editTheme = $editId ? DB::row('SELECT * FROM themes WHERE id=?', [$editId]) : null;

include __DIR__ . '/../views/partials/admin_layout.php';
?>

<?php if ($flash): ?>
  <div class="alert <?= $flash[0] ?>"><?= e($flash[1]) ?></div>
<?php endif; ?>

<div class="admin-two-col">
  <!-- Theme List -->
  <div class="acard">
    <div class="acard-head">
      <h2>🎨 Themes</h2>
      <span style="font-size:12px;color:#94a3b8">One theme active at a time</span>
    </div>
    <div class="acard-body" style="padding:0">
      <?php if (empty($themes)): ?>
        <p style="padding:20px;color:#94a3b8;text-align:center">No themes yet. Create one below.</p>
      <?php else: ?>
        <?php foreach ($themes as $th): ?>
          <div class="theme-row <?= $th['is_active'] ? 'theme-active' : '' ?>">
            <div class="theme-info">
              <?php if ($th['is_active']): ?>
                <span class="stag active" style="margin-right:8px">● Active</span>
              <?php endif; ?>
              <strong><?= e($th['name']) ?></strong>
              <span style="font-size:12px;color:#94a3b8;margin-left:8px"><?= strlen($th['css']) ?> chars CSS</span>
            </div>
            <div class="theme-btns">
              <a href="?edit=<?= $th['id'] ?>" class="btn-ghost btn-sm">Edit</a>
              <?php if (!$th['is_active']): ?>
                <form method="POST" style="display:inline"><?= csrf_input() ?>
                  <input type="hidden" name="action" value="activate">
                  <input type="hidden" name="id" value="<?= $th['id'] ?>">
                  <button class="btn-primary btn-sm">Activate</button>
                </form>
              <?php else: ?>
                <form method="POST" style="display:inline"><?= csrf_input() ?>
                  <input type="hidden" name="action" value="deactivate">
                  <button class="btn-ghost btn-sm">Deactivate</button>
                </form>
              <?php endif; ?>
              <form method="POST" style="display:inline"><?= csrf_input() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $th['id'] ?>">
                <button class="btn-danger btn-sm" onclick="return confirm('Delete theme?')">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Create / Edit -->
  <div class="acard">
    <div class="acard-head">
      <h2><?= $editTheme ? '✏️ Edit: '.e($editTheme['name']) : '+ New Theme' ?></h2>
      <?php if ($editTheme): ?><a href="<?= u('admin/themes.php') ?>" class="btn-ghost btn-sm">Cancel</a><?php endif; ?>
    </div>
    <div class="acard-body">
      <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="<?= $editTheme ? 'update' : 'create' ?>">
        <?php if ($editTheme): ?>
          <input type="hidden" name="id" value="<?= $editTheme['id'] ?>">
        <?php endif; ?>

        <div class="fg">
          <label>Theme Name</label>
          <input type="text" name="name" class="fi" required placeholder="My Custom Theme"
                 value="<?= e($editTheme['name'] ?? '') ?>">
        </div>

        <div class="fg">
          <label>Custom CSS</label>
          <div class="css-editor-wrap">
            <div class="css-presets">
              <span style="font-size:12px;color:#94a3b8;margin-right:6px">Presets:</span>
              <button type="button" class="btn-ghost btn-sm" onclick="applyPreset('dark')">🌙 Dark</button>
              <button type="button" class="btn-ghost btn-sm" onclick="applyPreset('warm')">🍂 Warm</button>
              <button type="button" class="btn-ghost btn-sm" onclick="applyPreset('green')">🌿 Green</button>
              <button type="button" class="btn-ghost btn-sm" onclick="applyPreset('purple')">💜 Purple</button>
            </div>
            <textarea name="css" id="cssEditor" class="fi css-ta" rows="18" spellcheck="false"
                      placeholder="/* Write your custom CSS here */
/* Override any CSS variables or rules */

/* Example: Change primary color */
:root {
  --blue: #e74c3c;
  --blue-d: #c0392b;
  --blue-l: #fdf2f2;
}

/* Example: Custom background */
body {
  background: #1a1a2e;
  color: #e0e0e0;
}"><?= e($editTheme['css'] ?? '') ?></textarea>
          </div>
        </div>

        <div style="display:flex;gap:10px;align-items:center">
          <button type="submit" class="btn-primary">💾 Save Theme</button>
          <button type="button" class="btn-ghost" onclick="previewTheme()">👁️ Preview</button>
          <span style="font-size:12px;color:#94a3b8">Preview opens forum in new tab</span>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CSS Variable Reference -->
<div class="acard">
  <div class="acard-head"><h2>📖 CSS Variables Reference</h2></div>
  <div class="acard-body">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;font-size:13px">
      <?php
      $vars = [
        ['--blue','Primary color (links, buttons)','#3b82f6'],
        ['--blue-d','Primary hover color','#2563eb'],
        ['--blue-l','Primary light background','#eff6ff'],
        ['--bg','Page background','#f1f5f9'],
        ['--surface','Card/panel background','#ffffff'],
        ['--border','Border color','#e2e8f0'],
        ['--text','Main text color','#0f172a'],
        ['--muted','Secondary text','#64748b'],
        ['--green','Success/accent color','#22c55e'],
        ['--red','Danger color','#ef4444'],
        ['--amber','Warning color','#f59e0b'],
        ['--header','Header height','56px'],
        ['--sidebar','Sidebar width','210px'],
        ['--font','Font family','Inter'],
        ['--mono','Monospace font','JetBrains Mono'],
        ['--r','Border radius','8px'],
      ];
      foreach ($vars as [$var,$desc,$val]): ?>
        <div style="background:#f8fafc;padding:8px 10px;border-radius:6px;border:1px solid #e2e8f0">
          <code style="color:#6366f1;font-size:12px"><?= e($var) ?></code>
          <div style="font-size:11px;color:#94a3b8;margin-top:2px"><?= e($desc) ?></div>
          <div style="font-size:11px;color:#64748b">Default: <em><?= e($val) ?></em></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<style>
.theme-row{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid #f1f5f9;transition:background .18s}
.theme-row:hover{background:#f8fafc}
.theme-row:last-child{border-bottom:none}
.theme-active{background:#eff6ff}
.theme-info{display:flex;align-items:center;flex:1}
.theme-btns{display:flex;gap:6px;align-items:center}
.css-editor-wrap{background:#1e293b;border-radius:8px;overflow:hidden}
.css-presets{display:flex;align-items:center;gap:4px;padding:8px 12px;background:#0f172a}
.css-ta{background:#1e293b!important;color:#e2e8f0!important;font-family:var(--mono)!important;font-size:13px!important;border-radius:0!important;border:none!important;padding:14px 16px!important;min-height:300px;line-height:1.6}
.css-ta:focus{box-shadow:none!important}
</style>

<script>
var presets = {
  dark: `:root {
  --blue: #6366f1;
  --blue-d: #4f46e5;
  --blue-l: #1e1b4b;
  --bg: #0f172a;
  --surface: #1e293b;
  --border: #334155;
  --border-l: #1e293b;
  --text: #f1f5f9;
  --muted: #94a3b8;
  --faint: #64748b;
}
.site-header { background: #1e293b; border-color: #334155; }
.sidebar { background: #1e293b; border-color: #334155; }
.site-footer { background: #1e293b; border-color: #334155; }
.cat-card, .topic-row, .post { background: #1e293b; border-color: #334155; }
.reply-box { background: #1e293b; border-color: #334155; }
.tl-hdr { background: #0f172a; }
.topic-list { background: #1e293b; border-color: #334155; }`,

  warm: `:root {
  --blue: #ea580c;
  --blue-d: #c2410c;
  --blue-l: #fff7ed;
  --bg: #fdf4ec;
  --surface: #fffaf5;
  --border: #fed7aa;
  --text: #431407;
  --muted: #9a3412;
}`,

  green: `:root {
  --blue: #16a34a;
  --blue-d: #15803d;
  --blue-l: #f0fdf4;
  --bg: #f0fdf4;
  --surface: #ffffff;
  --border: #bbf7d0;
  --text: #14532d;
  --muted: #166534;
}`,

  purple: `:root {
  --blue: #9333ea;
  --blue-d: #7e22ce;
  --blue-l: #faf5ff;
  --bg: #faf5ff;
  --surface: #ffffff;
  --border: #e9d5ff;
  --text: #3b0764;
  --muted: #6b21a8;
}`
};

function applyPreset(name) {
  document.getElementById('cssEditor').value = presets[name] || '';
}

function previewTheme() {
  var css = document.getElementById('cssEditor').value;
  var key = 'nx_preview_css';
  localStorage.setItem(key, css);
  var w = window.open('<?= u('/') ?>', '_blank');
  w.addEventListener('load', function() {
    w.postMessage({ type: 'nx_preview', css: css }, '*');
  });
  alert('Preview tip: the theme preview uses postMessage.\nActivate the theme to see it properly in all browsers.');
}
</script>

<?php include __DIR__ . '/../views/partials/admin_layout_end.php'; ?>
