<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin();
$PAGE_TITLE = 'Addons';
$ADMIN_PAGE = 'addons';
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $action = post('action');
    $slug   = preg_replace('/[^a-zA-Z0-9_\-]/', '', post('slug'));

    if ($action === 'activate' && $slug) {
        if (AddonManager::activate($slug)) {
            $flash = ['ok', "Addon «{$slug}» activated."];
        } else {
            $flash = ['err', "Failed to activate «{$slug}». Check error logs."];
        }
    } elseif ($action === 'deactivate' && $slug) {
        AddonManager::deactivate($slug);
        $flash = ['ok', "Addon «{$slug}» deactivated."];
    }
}

$addons = AddonManager::all();
include __DIR__ . '/../views/partials/admin_layout.php';
?>

<?php if ($flash): ?>
  <div class="alert <?= $flash[0] ?>"><?= e($flash[1]) ?></div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div>
    <h1 style="font-size:1.3rem;font-weight:700;margin:0">🧩 Addons</h1>
    <p style="color:var(--muted);font-size:14px;margin:4px 0 0">Extend your forum with addons. Place addon folders in <code>addons/</code>.</p>
  </div>
  <a href="#developer-docs" class="btn-ghost btn-sm">📖 Developer Docs</a>
</div>

<?php if (empty($addons)): ?>
  <div class="acard">
    <div class="acard-body" style="text-align:center;padding:40px">
      <div style="font-size:3rem;margin-bottom:12px">🧩</div>
      <h2 style="font-size:1.1rem;margin-bottom:8px">No addons installed</h2>
      <p style="color:var(--muted);font-size:14px;margin-bottom:16px">
        Place addon folders in <code><?= e(ROOT) ?>/addons/</code> and they will appear here.
      </p>
      <a href="#developer-docs" class="btn-primary">Create Your First Addon →</a>
    </div>
  </div>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:28px">
    <?php foreach ($addons as $slug => $addon): ?>
      <div class="acard addon-card <?= $addon['active'] ? 'addon-active' : '' ?>">
        <div class="addon-card-inner">
          <div class="addon-info">
            <div class="addon-header">
              <span class="addon-name"><?= e($addon['name']) ?></span>
              <span class="addon-version">v<?= e($addon['version']) ?></span>
              <?php if ($addon['active']): ?>
                <span class="addon-status-badge active">● Active</span>
              <?php else: ?>
                <span class="addon-status-badge inactive">○ Inactive</span>
              <?php endif; ?>
            </div>
            <p class="addon-desc"><?= e($addon['description']) ?></p>
            <div class="addon-meta">
              <span>By <strong><?= e($addon['author']) ?></strong></span>
              <?php if ($addon['url']): ?>
                <a href="<?= e($addon['url']) ?>" target="_blank" rel="noopener" style="font-size:12px">🔗 Website</a>
              <?php endif; ?>
              <span class="addon-slug-tag"><code><?= e($slug) ?></code></span>
              <?php if (!empty($addon['hooks'])): ?>
                <span style="font-size:12px;color:var(--muted)">
                  Hooks: <?= implode(', ', array_map('htmlspecialchars', $addon['hooks'])) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="addon-actions">
            <form method="POST">
              <?= csrf_input() ?>
              <input type="hidden" name="slug" value="<?= e($slug) ?>">
              <?php if ($addon['active']): ?>
                <input type="hidden" name="action" value="deactivate">
                <button class="btn-warn btn-sm" onclick="return confirm('Deactivate this addon?')">
                  ⏸ Deactivate
                </button>
              <?php else: ?>
                <input type="hidden" name="action" value="activate">
                <button class="btn-ok btn-sm">▶ Activate</button>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ── Developer Documentation ─────────────────────── -->
<div class="acard" id="developer-docs">
  <div class="acard-head">
    <h2>📖 Developer Documentation — How to Create an Addon</h2>
  </div>
  <div class="acard-body addon-docs">

    <h3>Overview</h3>
    <p>Addons extend Nexus Forum without modifying core files. Each addon is a folder inside <code>addons/</code> containing at minimum a <code>nexus-addon.json</code> manifest and a <code>main.php</code> entry point.</p>

    <h3>Directory Structure</h3>
    <pre><code>addons/
└── your-addon-slug/
    ├── nexus-addon.json   ← Required: manifest
    ├── main.php           ← Required: entry point, registers hooks
    ├── install.php        ← Optional: runs on first activation
    ├── uninstall.php      ← Optional: runs on deactivation
    └── assets/            ← Optional: CSS, JS, images</code></pre>

    <h3>1. The Manifest (<code>nexus-addon.json</code>)</h3>
    <pre><code>{
  "name":        "My Addon",
  "description": "A short description of what this addon does.",
  "version":     "1.0.0",
  "author":      "Your Name",
  "url":         "https://yoursite.com/addon",
  "hooks": [
    "after_post_saved",
    "render_post_footer"
  ],
  "requires": {
    "nexus": ">=14"
  }
}</code></pre>

    <h3>2. The Entry Point (<code>main.php</code>)</h3>
    <p>Use <code>addon_on($hook, $callback)</code> to register listeners. Hooks fire in priority order (default 10). Lower number = runs first.</p>
    <pre><code>&lt;?php
// main.php — loaded once per request if addon is active

// Example: add a footer line after every post
addon_on('render_post_footer', function(array $post): string {
    return '&lt;div class="custom-footer"&gt;Addon says hi on post #' . $post['id'] . '&lt;/div&gt;';
});

// Example: run code when a new topic is created
addon_on('after_topic_created', function(array $data): void {
    // $data has: topic_id, title, slug, category_id, user_id
    // Do something: call external API, log it, etc.
    error_log('New topic: ' . $data['title']);
});

// Example: filter post content before display
addon_on('render_post_content', function(string $html): string {
    // Transform HTML before output
    return str_replace(':-)', '😊', $html);
});</code></pre>

    <h3>3. Available Hooks</h3>
    <table class="atable" style="margin-top:8px">
      <thead><tr><th>Hook</th><th>Receives</th><th>Returns</th><th>When</th></tr></thead>
      <tbody>
        <tr><td><code>after_topic_created</code></td><td><code>array</code> topic data</td><td><em>void</em></td><td>After a new topic is saved</td></tr>
        <tr><td><code>after_reply_saved</code></td><td><code>array</code> post data</td><td><em>void</em></td><td>After a reply is posted</td></tr>
        <tr><td><code>after_user_registered</code></td><td><code>array</code> user data</td><td><em>void</em></td><td>After a new user registers</td></tr>
        <tr><td><code>render_post_content</code></td><td><code>string</code> HTML</td><td><code>string</code> HTML</td><td>Before post content is output (filter)</td></tr>
        <tr><td><code>render_post_footer</code></td><td><code>array</code> post row</td><td><code>string</code> HTML</td><td>Below each post body</td></tr>
        <tr><td><code>render_topic_header</code></td><td><code>array</code> topic row</td><td><code>string</code> HTML</td><td>Above topic content</td></tr>
        <tr><td><code>admin_nav_items</code></td><td><code>array</code> nav items</td><td><code>array</code></td><td>Admin sidebar links</td></tr>
        <tr><td><code>user_karma_changed</code></td><td><code>array</code> {user_id, old, new}</td><td><em>void</em></td><td>When karma is adjusted</td></tr>
        <tr><td><code>before_page_head</code></td><td><code>string</code> HTML</td><td><code>string</code> HTML</td><td>Inside &lt;head&gt; on every page</td></tr>
      </tbody>
    </table>

    <h3>4. Install / Uninstall Scripts</h3>
    <pre><code>&lt;?php
// install.php — runs once when admin activates the addon
// Use this to create tables, insert settings, etc.
DB::connect()->exec("
    CREATE TABLE IF NOT EXISTS my_addon_logs (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        message    TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )
");
cfg_set('my_addon_enabled', '1');</code></pre>

    <pre><code>&lt;?php
// uninstall.php — runs when admin deactivates the addon
// Clean up tables and settings (optional — you may want to keep data)
// DB::run("DROP TABLE IF EXISTS my_addon_logs");
cfg_set('my_addon_enabled', '0');</code></pre>

    <h3>5. Accessing Nexus APIs</h3>
    <p>All Nexus helpers are available inside addon code:</p>
    <pre><code>// Database
$rows = DB::rows("SELECT * FROM topics WHERE category_id=?", [1]);
DB::insert("INSERT INTO my_addon_logs (message) VALUES (?)", ['hello']);

// Users
global $USER;  // currently logged-in user (or null)

// Settings
$siteName = cfg('site_name', 'My Forum');
cfg_set('my_addon_setting', 'value');

// Notifications
add_notification($userId, 'my_addon', ['key' => 'value']);

// Karma
add_karma($userId, 5);  // award 5 karma points

// URL helpers
$url = u('forum/topic.php?slug=hello');  // respects BASE path</code></pre>

    <h3>6. Example Addon</h3>
    <p>A complete working example is included at <code>addons/example-hello-world/</code>.</p>
  </div>
</div>

<style>
.addon-card { transition:box-shadow .18s; }
.addon-card.addon-active { border-color:var(--green); }
.addon-card-inner { display:flex; align-items:flex-start; gap:16px; justify-content:space-between; flex-wrap:wrap; }
.addon-info { flex:1; min-width:0; }
.addon-header { display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap; }
.addon-name { font-size:15px; font-weight:700; }
.addon-version { font-size:11px; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:1px 7px; color:var(--muted); }
.addon-status-badge { font-size:11px; font-weight:700; padding:2px 8px; border-radius:8px; }
.addon-status-badge.active   { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.addon-status-badge.inactive { background:#f8fafc; color:#94a3b8; border:1px solid #e2e8f0; }
.addon-desc { font-size:14px; color:var(--muted); margin:0 0 8px; }
.addon-meta { display:flex; align-items:center; gap:14px; font-size:13px; color:var(--muted); flex-wrap:wrap; }
.addon-slug-tag code { background:var(--bg); padding:1px 6px; border-radius:4px; font-size:12px; border:1px solid var(--border); }
.addon-actions { flex-shrink:0; }
.addon-docs h3 { font-size:14px; font-weight:700; margin:20px 0 8px; color:var(--text); }
.addon-docs p  { font-size:14px; color:var(--muted); margin-bottom:10px; line-height:1.7; }
.addon-docs pre { background:#1e293b; color:#e2e8f0; padding:16px; border-radius:var(--r); overflow-x:auto; margin:8px 0 16px; font-size:13px; line-height:1.6; }
.addon-docs code { font-family:var(--mono); font-size:13px; }
.addon-docs p code { background:var(--bg); border:1px solid var(--border); padding:1px 6px; border-radius:4px; }
</style>

<?php include __DIR__ . '/../views/partials/admin_layout_end.php'; ?>
