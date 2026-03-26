<div align="center">

<img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.0+">
<img src="https://img.shields.io/badge/SQLite-003B57?style=for-the-badge&logo=sqlite&logoColor=white" alt="SQLite">
<img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
<img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="MIT License">
<img src="https://img.shields.io/badge/Zero-Dependencies-orange?style=for-the-badge" alt="Zero Dependencies">

<br><br>

```
███╗   ██╗███████╗██╗  ██╗██╗   ██╗███████╗
████╗  ██║██╔════╝╚██╗██╔╝██║   ██║██╔════╝
██╔██╗ ██║█████╗   ╚███╔╝ ██║   ██║███████╗
██║╚██╗██║██╔══╝   ██╔██╗ ██║   ██║╚════██║
██║ ╚████║███████╗██╔╝ ██╗╚██████╔╝███████║
╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚═════╝ ╚══════╝
```

### A modern, full-featured discussion platform built with pure PHP 8

**No frameworks · No npm · No Docker · Just upload and run**

[Features](#-features) · [Quick Start](#-quick-start) · [Installation](#-installation) · [Configuration](#️-configuration) · [Addons](#-addon-system) · [API](#-api-reference) · [FAQ](#-faq)

</div>

---

## ✨ Features

### 💬 Forum Core
- **Categories** with icons, colours, sub-categories, and per-role permissions
- **Topics & threaded replies** with pagination
- **Full Markdown editor** — Google Docs-style toolbar with SVG icons, Write/Preview tabs
- **Syntax-highlighted code blocks** via Prism.js (200+ languages, lazy-loaded only when needed)
- **Styled blockquotes** with gradient left border
- **Post permalinks** — every post gets `#post-{id}` + a 🔗 copy-link button (pagination-aware)
- **Inline image upload** — paste, drag-drop, or file picker directly in the editor
- **Media auto-embeds** — paste a URL and it becomes a player (14 platforms)
- **@mentions** with live autocomplete
- **Live search** — finds topics AND post content, links directly to the matching post on the correct page

### 🔐 Roles & Permissions

| Role | Level | Can Do |
|---|---|---|
| **Guest** | 0 | Read public categories |
| **Member** | 10 | Post, reply, like, message, friend |
| **Moderator** | 20 | + Pin/close topics, edit any post |
| **Admin** | 30 | Full access + admin panel |

**Per-category permissions** — set independently for reading, posting, and replying:

| Permission | Options |
|---|---|
| Who can **read** | 🌐 Everyone · 👤 Members · 🛡️ Moderators+ · 👑 Admins |
| Who can **post topics** | Same four options |
| Who can **reply** | Same four options |

### ⭐ Karma System

Eight progressive tiers earned through activity:

| Tier | Points | Icon |
|---|---|---|
| Newcomer | 0–9 | 🌱 |
| Member | 10–49 | 💬 |
| Regular | 50–99 | ⭐ |
| Contributor | 100–249 | 🌟 |
| Veteran | 250–499 | 🔥 |
| Expert | 500–999 | 💎 |
| Elite | 1000–2499 | 👑 |
| Legend | 2500+ | 🏆 |

Admins can manually adjust karma (Add / Subtract / Set) with an optional reason that notifies the user.

### 📬 Private Messages
- Inbox/Sent with unread badges
- Conversation threads displayed as chat bubbles
- Read receipts (✓ sent · ✓✓ read)
- Online status indicator (green if active in last 5 min)
- Live user search autocomplete

### 🔍 Search
- **Topics tab** — title matches
- **Posts tab** — content matches, jumps directly to the exact post on the correct page
- **Users tab** — username + bio search
- Live header dropdown shows topic + post results simultaneously

### 🧩 Addon System
Extend the forum by dropping a folder into `addons/` and clicking Activate. No core file edits needed. Full PHP API access with 9 event hooks.

### 🔒 Security
- CSRF tokens on all forms and AJAX
- bcrypt password hashing (cost 12)
- Math captcha (admin toggle, separate for posts and new topics)
- Rate limiting with live countdown
- Auto-generated `.htaccess` protection for `data/` and `uploads/`
- Security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, HSTS on HTTPS
- All SQL via PDO prepared statements

### 🗄️ Database Support

| Driver | Version |
|---|---|
| **SQLite** | 3.x — zero configuration, single file |
| **MySQL** | 5.7+ |
| **MariaDB** | 10.3+ |

Schema migrates automatically on every request — update files and existing installs upgrade themselves.

---

## 🚀 Quick Start

### Shared Hosting (5 minutes)

```bash
# 1. Upload to your server
scp -r forum-clean/ user@host:~/public_html/forum/

# 2. Set directory permissions
chmod 755 data/ public/uploads/ public/uploads/avatars/

# 3. Visit the installer
# https://yoursite.com/forum/install/

# 4. Complete the 3-step wizard, then remove /install/
rm -rf install/
```

### Local Development

```bash
# PHP built-in server — SQLite, zero config
cd forum-clean/
php -S localhost:8080
# open http://localhost:8080/install/
```

### Docker (Apache)

```dockerfile
# Dockerfile
FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_sqlite
RUN a2enmod rewrite
COPY forum-clean/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html/data \
    /var/www/html/public/uploads
```

```bash
docker build -t nexus-forum .
docker run -p 8080:80 nexus-forum
# open http://localhost:8080/install/
```

---

## 📦 Installation

### Requirements

| Item | Minimum | Notes |
|---|---|---|
| PHP | **8.0** | 8.2+ recommended |
| PDO | Required | `pdo_sqlite` or `pdo_mysql` |
| GD | Optional | For image thumbnails |
| Web server | Apache or Nginx | See configs below |
| Disk | 10 MB | Plus user uploads |

### Step-by-step

**1 — Upload files**

The forum works at any URL path:
- `https://yoursite.com/`
- `https://yoursite.com/forum/`
- `https://yoursite.com/community/board/`

The `BASE` path is auto-detected. No `.env` changes needed.

**2 — Set permissions**

```bash
chmod 755 data/
chmod 755 public/uploads/
chmod 755 public/uploads/avatars/
```

**3 — Run the web installer**

Visit `/install/` — the 3-step wizard:

| Step | What happens |
|---|---|
| **1 — Requirements** | Checks PHP version, extensions, directory permissions |
| **2 — Database** | Choose SQLite or MySQL, enter site name + admin credentials |
| **3 — Done** | Writes config, runs migration, shows security checklist |

**4 — Post-install (automatic)**

The installer automatically creates:
- `data/.htaccess` — denies all web access to the database directory
- `public/uploads/.htaccess` — blocks PHP execution in uploads folder
- `data/db_config.php` → `chmod 0640`
- `data/forum.db` → `chmod 0640` (SQLite only)
- `data/installed.lock` — prevents re-running the installer

### MySQL Setup

```sql
CREATE DATABASE nexus_forum
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'nexus'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON nexus_forum.* TO 'nexus'@'localhost';
FLUSH PRIVILEGES;
```

Then select "MySQL / MariaDB" in the installer.

---

## ⚙️ Configuration

### Apache

```apache
<VirtualHost *:80>
    ServerName forum.yoursite.com
    DocumentRoot /var/www/nexus-forum

    <Directory /var/www/nexus-forum>
        AllowOverride All
        Require all granted
    </Directory>

    # Protect database directory
    <Directory /var/www/nexus-forum/data>
        Require all denied
    </Directory>
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    server_name forum.yoursite.com;
    root /var/www/nexus-forum;
    index index.php;

    # Block sensitive paths
    location ~ ^/(data|includes)/ {
        deny all;
        return 404;
    }
    location ~ \.(db|sqlite|lock)$ {
        deny all;
        return 404;
    }
    # Block PHP execution in uploads
    location ~ ^/public/uploads/.*\.php$ {
        deny all;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location / {
        try_files $uri $uri/ =404;
    }
}
```

### Admin Settings Panel

Visit **Admin → Settings** to configure:

| Setting | Description |
|---|---|
| Site name & description | Header and `<title>` |
| Topics / Posts per page | Pagination sizes |
| Post captcha | Math captcha on replies (spam protection) |
| Topic captcha | Math captcha on new topics |
| Rate limiting | Seconds between posts |
| Max upload size | Image upload limit |
| Registration | Open or closed |

---

## 📁 Project Structure

```
nexus-forum/
│
├── 📁 addons/                   # Drop addon folders here
│   └── example-hello-world/    # Sample addon (see Addon docs)
│
├── 📁 admin/                   # Admin panel
│   ├── index.php               # Dashboard
│   ├── users.php               # User list
│   ├── user.php                # Edit user + karma manager
│   ├── categories.php          # Categories + role permissions
│   ├── topics.php              # Topic moderation
│   ├── settings.php            # Site settings
│   ├── themes.php              # Theme switching
│   └── addons.php              # Addon manager + developer docs
│
├── 📁 api/                     # JSON endpoints (POST)
│   ├── reply.php               # Post a reply
│   ├── edit.php                # Edit a post
│   ├── delete.php              # Delete a post
│   ├── like.php                # Like / unlike
│   ├── upload.php              # Image upload
│   ├── notifications.php       # Mark read
│   ├── friend.php              # Friend requests
│   ├── karma.php               # Admin karma adjust
│   ├── chat.php                # Private message actions
│   ├── search.php              # Live search (topics + posts)
│   ├── search_users.php        # User autocomplete
│   └── topic_action.php        # Pin / close / delete topic
│
├── 📁 auth/                    # login · register · logout
├── 📁 data/                    # Created by installer (not web-accessible)
├── 📁 forum/                   # category · topic · new-topic · search
│
├── 📁 includes/                # Core library (not web-accessible)
│   ├── bootstrap.php           # Loads everything, boots addons
│   ├── config.php              # Path detection, security headers
│   ├── db.php                  # PDO multi-driver DB class
│   ├── functions.php           # All helpers
│   ├── markdown.php            # Markdown + embed renderer
│   └── addons.php              # AddonManager class
│
├── 📁 install/                 # DELETE after setup
├── 📁 messages/                # inbox · compose · view
│
├── 📁 public/
│   ├── css/main.css            # ~2400 lines — full design system
│   ├── js/app.js               # ~1000 lines — all client JS
│   └── uploads/                # User images (PHP execution blocked)
│
├── 📁 users/                   # profile · edit · search
│
├── 📁 views/partials/
│   ├── layout.php              # Header, sidebar, nav
│   ├── layout_end.php          # Footer, Prism.js loader, app.js
│   ├── admin_layout.php        # Admin sidebar
│   └── editor_toolbar.php      # Reusable Markdown toolbar (SVG icons)
│
└── index.php                   # Homepage
```

---

## 📺 Media Embeds

Paste any of these URLs alone on a line in a post and it auto-embeds as a player:

| Platform | Supported |
|---|---|
| YouTube | Videos, Shorts, YouTube Music |
| Vimeo | Videos |
| Twitch | Live streams, VODs |
| Dailymotion | Videos |
| Streamable | Clips |
| Rumble | Videos |
| Spotify | Tracks, albums, playlists, podcast episodes, artist pages |
| SoundCloud | Tracks |
| Loom | Screen recordings |
| CodePen | Pens |
| JSFiddle | Fiddles |
| Twitter / X | Tweets |
| TED Talks | Talks |
| Bandcamp | Tracks |

---

## 🧩 Addon System

### Installing

1. Drop the addon folder into `addons/`
2. **Admin → Addons → ▶ Activate**

### Creating an Addon

**`nexus-addon.json`** — manifest (required)

```json
{
  "name":        "My Addon",
  "description": "What this addon does.",
  "version":     "1.0.0",
  "author":      "Your Name",
  "url":         "https://yoursite.com",
  "hooks":       ["after_topic_created", "render_post_footer"],
  "requires":    { "nexus": ">=14" }
}
```

**`main.php`** — entry point (required)

```php
<?php
// Runs on every request when the addon is active

// Inject HTML below every post
addon_on('render_post_footer', function(array $post): string {
    return '<div class="my-badge">✓ Verified</div>';
});

// React to new topics
addon_on('after_topic_created', function(array $data): void {
    // $data: topic_id, title, slug, category_id, user_id
    // Call external webhook, send Slack message, etc.
    // file_get_contents('https://hooks.example.com?title=' . urlencode($data['title']));
});

// Filter post HTML before display
addon_on('render_post_content', function(string $html): string {
    return str_replace(':-)', '😊', $html);
});
```

**`install.php`** — runs on activation (optional)

```php
<?php
DB::connect()->exec("CREATE TABLE IF NOT EXISTS my_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    message    TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
cfg_set('my_addon_active', '1');
```

**`uninstall.php`** — runs on deactivation (optional)

```php
<?php
cfg_set('my_addon_active', '0');
// DB::run("DROP TABLE IF EXISTS my_log");  // uncomment to clean up
```

### Hook Reference

| Hook | Data passed | Return | Fires when |
|---|---|---|---|
| `after_topic_created` | `array` {topic_id, title, slug, category_id, user_id} | void | Topic saved |
| `after_reply_saved` | `array` {post_id, topic_id, user_id} | void | Reply posted |
| `after_user_registered` | `array` {user_id, username, email} | void | Registration |
| `render_post_content` | `string` HTML | `string` HTML | Before post output |
| `render_post_footer` | `array` post row | `string` HTML | Below post body |
| `render_topic_header` | `array` topic row | `string` HTML | Above topic |
| `user_karma_changed` | `array` {user_id, old, new, by} | void | Karma adjusted |
| `admin_nav_items` | `array` items | `array` | Admin sidebar |
| `before_page_head` | `string` HTML | `string` HTML | Inside `<head>` |

### Addon PHP API

```php
// Database
DB::rows("SELECT * FROM topics WHERE category_id=?", [$catId]);
DB::row("SELECT * FROM users WHERE id=?", [$uid]);
DB::insert("INSERT INTO my_log (message) VALUES (?)", [$msg]);
DB::run("UPDATE my_table SET col=? WHERE id=?", [$val, $id]);
DB::val("SELECT COUNT(*) FROM posts WHERE topic_id=?", [$tid]);

// Current user
global $USER; // array or null

// Settings
$val = cfg('site_name', 'My Forum');
cfg_set('my_key', 'my_value');

// Notifications
add_notification($userId, 'my_type', ['key' => 'value']);

// Karma
add_karma($userId, 10);   // add 10 points

// URL helpers
$url = u('forum/topic.php?slug=' . urlencode($slug));
$assetUrl = asset('js/app.js');
```

---

## 🎨 Theming

All design tokens are CSS custom properties in `public/css/main.css`:

```css
:root {
  /* Brand colours */
  --blue:    #3b82f6;
  --blue-d:  #2563eb;
  --blue-l:  #eff6ff;
  --green:   #22c55e;
  --red:     #ef4444;
  --purple:  #8b5cf6;

  /* Surfaces */
  --bg:       #f1f5f9;   /* page background */
  --surface:  #ffffff;   /* cards */
  --border:   #e2e8f0;   /* borders */
  --border-l: #f1f5f9;   /* light borders */

  /* Text */
  --text:   #0f172a;
  --muted:  #64748b;
  --faint:  #94a3b8;

  /* Typography */
  --font: 'Inter', -apple-system, sans-serif;
  --mono: 'JetBrains Mono', 'Fira Code', monospace;

  /* Sizing */
  --r:    6px;    /* border radius */
  --r-lg: 10px;
  --r-xl: 16px;
  --header:  56px;
  --sidebar: 220px;
}
```

Override any variable in a custom stylesheet, or inject one via the `before_page_head` addon hook.

---

## 🔑 API Reference

All endpoints accept `POST` (or `GET` for search) and expect a `csrf` parameter from the `NX.csrf` global.

| Endpoint | Auth | Description |
|---|---|---|
| `POST /api/reply.php` | Member | Post a reply (`slug`, `content`) |
| `POST /api/edit.php` | Author/Admin | Edit post (`post_id`, `content`) |
| `POST /api/delete.php` | Author/Admin | Delete post (`post_id`) |
| `POST /api/like.php` | Member | Like/unlike (`post_id`) |
| `POST /api/upload.php` | Member | Upload image (`file`) → `{url}` |
| `POST /api/topic_action.php` | Mod/Admin | Pin/close/delete topic |
| `POST /api/friend.php` | Member | Friend actions (`action`, `other_id`) |
| `POST /api/karma.php` | Admin | Adjust karma (`user_id`, `amount`, `op`) |
| `POST /api/notifications.php` | Member | Mark notifications read |
| `GET  /api/search.php?q=` | Public | Live search → `{topics, posts}` |
| `GET  /api/search_users.php?q=` | Public | User autocomplete → `[{id, username, avatar}]` |
| `POST /api/chat.php` | Member | DM actions (`action`: send/poll/load/conversations) |

**Quick example — posting a reply:**

```javascript
const fd = new FormData();
fd.append('slug',    'my-topic-slug');
fd.append('content', 'My reply content here.');
fd.append('csrf',    NX.csrf);  // NX is the global config object

const res  = await fetch(NX.base + '/api/reply.php', { method: 'POST', body: fd });
const data = await res.json();
// Success: { ok: true, post: { id, content, post_num, created_at, username, ... } }
// Error:   { error: "message", ... }
```

---

## 🧰 Developer Reference

### Helper Functions

| Function | Returns | Description |
|---|---|---|
| `e($val)` | `string` | `htmlspecialchars()` — always use when outputting user data |
| `u($path)` | `string` | URL with BASE prefix |
| `asset($path)` | `string` | Public asset URL |
| `go($path)` | never | Redirect |
| `post($key, $default)` | `mixed` | `$_POST[$key] ?? $default` |
| `get($key, $default)` | `mixed` | `$_GET[$key] ?? $default` |
| `cfg($key, $default)` | `string` | Read a setting (cached) |
| `cfg_set($key, $value)` | void | Write a setting |
| `sanitise($input)` | `string` | Strip HTML/PHP/scripts from user input |
| `must_login()` | void | Redirect if not authenticated |
| `must_admin()` | void | Redirect if not admin |
| `is_admin()` | `bool` | Check admin role |
| `current_user()` | `?array` | Current user row or null |
| `csrf_input()` | `string` | `<input type="hidden" name="csrf" value="...">` |
| `csrf_ok()` | `bool` | Validate CSRF token |
| `render_post($raw)` | `string` | Render Markdown + embeds to HTML |
| `add_karma($uid, $pts)` | void | Add (or subtract) karma points |
| `karma_tier($karma)` | `array` | Tier name, icon, colour, progress |
| `add_notification($uid, $type, $data)` | void | Queue a notification |
| `can_read_category($cat)` | `bool` | Read permission check |
| `can_post_topic($cat)` | `bool` | Post permission check |
| `can_reply_topic($cat)` | `bool` | Reply permission check |
| `unique_slug($title, $table)` | `string` | Generate a unique URL slug |
| `rate_check($uid, $type)` | `array` | `{ok, wait}` |
| `rate_record($uid, $type)` | void | Record a rate-limited action |
| `addon_hook($hook, $data)` | `mixed` | Fire an addon hook |

### Database Class

```php
DB::rows($sql, $params)   // array of rows
DB::row($sql, $params)    // one row or null
DB::insert($sql, $params) // int lastInsertId
DB::run($sql, $params)    // PDOStatement
DB::val($sql, $params)    // scalar or null
DB::now()                 // cross-driver: NOW() or datetime('now')
DB::isMysql()             // bool
DB::insertIgnore($table, $cols, $vals)   // cross-driver INSERT IGNORE
DB::upsert($table, $keyCol, $valCol, $key, $val)  // cross-driver upsert
```

### Adding a New Page

```php
<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_login();   // or must_admin(), or omit for public pages

$PAGE_TITLE = 'My Page';
include __DIR__ . '/../views/partials/layout.php';
?>

<h1>Hello, <?= e($USER['username']) ?></h1>
<p>Your karma: <?= (int)$USER['karma'] ?></p>

<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
```

---

## 🛡️ Security Checklist

After going live:

- [ ] **Delete `install/`** — prevents re-installation
- [ ] **Verify `data/.htaccess`** — should deny all HTTP access (auto-created)
- [ ] **Verify `public/uploads/.htaccess`** — should block `.php` execution (auto-created)
- [ ] **Use HTTPS** — HSTS header is sent automatically when detected
- [ ] **MySQL users** — grant only `SELECT`, `INSERT`, `UPDATE`, `DELETE` (not `DROP`)
- [ ] **Enable captcha** — Admin → Settings → Post Captcha / Topic Captcha
- [ ] **Set rate limits** — Admin → Settings → Rate Limiting

---

## ❓ FAQ

<details>
<summary><strong>Can I run this on shared hosting without shell access?</strong></summary>

Yes. Shared hosting is the primary target. Everything is configured through the web installer. No Composer, npm, or shell access required.
</details>

<details>
<summary><strong>Do I need a separate database server?</strong></summary>

No. SQLite works out of the box with zero configuration — the database is a single file in `data/`. You can switch to MySQL/MariaDB any time by re-running the installer.
</details>

<details>
<summary><strong>How do I upgrade to a new version?</strong></summary>

Replace all files except `data/`. The schema migration runs automatically on the first page load after the update, adding any new columns safely with `IF NOT EXISTS` / `information_schema` checks.
</details>

<details>
<summary><strong>How do I reset a forgotten admin password?</strong></summary>

Run from the command line in your forum directory:

```bash
php -r "
require 'includes/bootstrap.php';
\$hash = password_hash('new_password_here', PASSWORD_BCRYPT, ['cost' => 12]);
DB::run('UPDATE users SET password=? WHERE role=?', [\$hash, 'admin']);
echo 'Password reset successfully.';
"
```
</details>

<details>
<summary><strong>Can I use this behind a reverse proxy / load balancer?</strong></summary>

Yes. The `BASE` path is auto-detected from `DOCUMENT_ROOT` vs `SCRIPT_FILENAME`. No `.env` changes needed. For HTTPS detection behind a proxy, ensure the proxy sets `X-Forwarded-Proto: https`.
</details>

<details>
<summary><strong>How do I back up the forum?</strong></summary>

**SQLite:** Copy `data/forum.db` and `data/db_config.php`.

**MySQL:** `mysqldump nexus_forum > backup.sql`

Also back up `public/uploads/` for user images.
</details>

<details>
<summary><strong>Why no Composer / npm?</strong></summary>

The goal is maximum deployability. Any server running PHP 8 with PDO can run Nexus — no package manager, no build step, no Node.js. The only optional CDN dependency is Prism.js for syntax highlighting, which is lazy-loaded only when a code block is on the page.
</details>

---

## 🤝 Contributing

1. Fork the repository
2. Create a branch: `git checkout -b feature/my-feature`
3. Make your changes — test on **both SQLite and MySQL**
4. Syntax check: `find . -name "*.php" | xargs php -l`
5. Submit a pull request

### Code Guidelines

- **PHP 8.0+** — use `match`, arrow functions, named arguments freely
- **No raw SQL interpolation** — always use PDO prepared statements
- **Always `e()` user output** — never echo user data unescaped
- **Cross-driver SQL** — test on both SQLite and MySQL; use `DB::insertIgnore()`, `DB::upsert()`, `DB::now()` for portability
- **No external dependencies** — no Composer packages, no npm, no build step

---

## 📄 License

MIT License — free to use, modify, and distribute.

---

<div align="center">

**Nexus Discussion**

*Built with PHP 8 · PDO · Vanilla JS*

*No frameworks · No build steps · No Docker required*

**[⬆ Back to top](#nexus-discussion)**

</div>
