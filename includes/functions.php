<?php
if (!defined('NEXUS')) exit('Forbidden');

/* ── Output escaping ───────────────────────────────────────── */
function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ── URL building ──────────────────────────────────────────── */
function u(string $path = ''): string {
    return BASE . '/' . ltrim($path, '/');
}
function asset(string $path): string {
    return BASE . '/public/' . ltrim($path, '/');
}

/* ── Redirects ─────────────────────────────────────────────── */
function go(string $path): never {
    header('Location: ' . u($path));
    exit;
}

/* ── Settings ──────────────────────────────────────────────── */
function cfg(string $key, string $default = ''): string {
    static $c = null;
    if ($c === null) {
        try {
            $rows = DB::rows('SELECT key, value FROM settings');
            $c = array_column($rows, 'value', 'key');
        } catch (\Throwable $e) { $c = []; }
    }
    return $c[$key] ?? $default;
}
function cfg_set(string $key, string $value): void {
    DB::upsert('settings', 'key', 'value', $key, $value);
}

/* ── Active theme CSS ──────────────────────────────────────── */
function active_theme_css(): string {
    try {
        $theme = DB::row("SELECT css FROM themes WHERE is_active=1 LIMIT 1");
        return $theme ? '<style id="theme-css">' . $theme['css'] . '</style>' : '';
    } catch (\Throwable $e) { return ''; }
}

/* ── Auth ──────────────────────────────────────────────────── */
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params(['lifetime' => 86400*30,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
function current_user(): ?array {
    start_session();
    if (empty($_SESSION['uid'])) return null;
    $u = DB::row('SELECT * FROM users WHERE id=?', [$_SESSION['uid']]);
    if (!$u) { unset($_SESSION['uid']); return null; }
    $now = DB::now();
    DB::run("UPDATE users SET last_seen=$now WHERE id=?", [$u['id']]);
    return $u;
}
function login_user(int $id): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
}
function logout_user(): void {
    start_session();
    session_destroy();
}
function must_login(): void {
    global $USER;
    if (!$USER) go('auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
}
function must_admin(): void {
    global $USER;
    must_login();
    if (!is_admin()) render_403();
}
function must_admin_only(): void {
    global $USER;
    must_login();
    if ($USER['role'] !== 'admin') render_403();
}
function is_admin(?array $u = null): bool {
    global $USER;
    $u = $u ?? $USER;
    return $u && in_array($u['role'], ['admin','moderator']);
}
/* Check a specific permission (stored as JSON in users.permissions) */
function has_perm(string $perm, ?array $u = null): bool {
    global $USER;
    $u = $u ?? $USER;
    if (!$u) return false;
    if ($u['role'] === 'admin') return true;
    $perms = json_decode($u['permissions'] ?? '{}', true) ?: [];
    return !empty($perms[$perm]);
}

/* ── CSRF ──────────────────────────────────────────────────── */
function csrf(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_input(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
}
function csrf_ok(): bool {
    $t = $_POST['csrf'] ?? '';
    return $t !== '' && hash_equals(csrf(), $t);
}

/* ── Slugs ─────────────────────────────────────────────────── */
function make_slug(string $text): string {
    $s = mb_strtolower(trim($text), 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $s);
    $s = preg_replace('/[\s\-]+/', '-', $s);
    return substr($s, 0, 80) ?: 'post';
}
function unique_slug(string $text, string $table, ?int $skip = null): string {
    $base = make_slug($text); $slug = $base; $n = 1;
    while (true) {
        $sql = "SELECT id FROM $table WHERE slug=?"; $p = [$slug];
        if ($skip) { $sql .= ' AND id!=?'; $p[] = $skip; }
        if (!DB::row($sql, $p)) break;
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

/* ── Time ──────────────────────────────────────────────────── */
function time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff <    60) return 'just now';
    if ($diff <  3600) return floor($diff/60)   . 'm ago';
    if ($diff < 86400) return floor($diff/3600)  . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($dt));
}

/* ── Notifications ─────────────────────────────────────────── */
function unread_count(): int {
    global $USER;
    if (!$USER) return 0;
    return (int) DB::val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND `read`=0', [$USER['id']]);
}
function add_notification(int $uid, string $type, array $data): void {
    global $USER;
    if ($USER && $uid === (int)$USER['id']) return;
    DB::insert('INSERT INTO notifications (user_id,type,payload) VALUES (?,?,?)',
        [$uid, $type, json_encode($data)]);
}

/* ── Karma ─────────────────────────────────────────────────── */
function add_karma(int $uid, int $pts = 1): void {
    DB::run('UPDATE users SET karma=karma+? WHERE id=?', [$pts, $uid]);
}

/* ── Sidebar categories ────────────────────────────────────── */
function nav_categories(): array {
    return DB::rows('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY position, id');
}

/* ── Forum-wide stats ──────────────────────────────────────── */
function forum_stats(): array {
    return [
        'topics'  => (int) DB::val('SELECT COUNT(*) FROM topics WHERE archived=0'),
        'posts'   => (int) DB::val('SELECT COUNT(*) FROM posts WHERE deleted=0'),
        'users'   => (int) DB::val('SELECT COUNT(*) FROM users'),
        'online'  => (int) DB::val("SELECT COUNT(*) FROM users WHERE last_seen >= ".DB::sinceSeconds(900)),
        'newest'  => DB::row('SELECT username FROM users ORDER BY joined_at DESC LIMIT 1'),
    ];
}

/* ── Error pages ───────────────────────────────────────────── */
function render_403(): never {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title>'
        .'<link rel="stylesheet" href="'.asset('css/main.css').'"></head>'
        .'<body class="auth-body"><div class="err-pg"><div class="err-code">403</div>'
        .'<h1>Access Denied</h1><p>You do not have permission to view this page.</p>'
        .'<a href="'.u('/').'\" class="btn-primary">← Home</a></div></body></html>';
    exit;
}
function render_404(): never {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title>'
        .'<link rel="stylesheet" href="'.asset('css/main.css').'"></head>'
        .'<body class="auth-body"><div class="err-pg"><div class="err-code">404</div>'
        .'<h1>Not Found</h1><p>The page you are looking for does not exist.</p>'
        .'<a href="'.u('/').'\" class="btn-primary">← Home</a></div></body></html>';
    exit;
}

/* ── POST/GET helpers ──────────────────────────────────────── */
function post(string $k, string $d = ''): string { return trim($_POST[$k] ?? $d); }
function get(string $k, string $d = ''): string  { return trim($_GET[$k]  ?? $d); }

/* ── JSON response ─────────────────────────────────────────── */
function json_out(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* ── Security: Input sanitisation ────────────────────────────
 * ALL user content MUST pass through sanitise() before DB insert.
 * Strips HTML, PHP, script tags, and HTML entities.
 */
function sanitise(string $input): string {
    // ── 1. Kill PHP execution tags completely ────────────────────────
    $s = preg_replace('/<\?(?:php|=)?.*?\?>/si', '', $input);

    // ── 2. Multi-round decode to catch all encoding tricks ───────────
    //      &#106;avascript:, &lt;script&gt;, \u003cscript\u003e etc.
    for ($i = 0; $i < 4; $i++) {
        $prev = $s;
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Resolve JS-style unicode escapes: \u003c → <
        $s = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            return mb_chr(hexdec($m[1]), 'UTF-8') ?? $m[0];
        }, $s);
        if ($s === $prev) break;
    }

    // ── 3. Strip execution-dangerous HTML completely (with content) ──
    //      Everything inside these tags is removed, not just the tags.
    $exec = 'script|style|iframe|frame|frameset|object|embed|applet|form';
    $s = preg_replace('/<(' . $exec . ')\b[^>]*>.*?<\/\1>/si', '', $s);
    $s = preg_replace('/<(' . $exec . ')\b[^>]*\/?>/si', '', $s);

    // Also strip PHP/server-side tags that survived step 1
    $s = preg_replace('/<\?.*?\?>/s', '', $s);

    // ── 4. NOW escape all remaining < > & so they display as text ────
    //      Safe HTML tags like <b>, <i>, <p>, custom tags from users:
    //      they are NOT executed — they show as literal &lt;b&gt; etc.
    //      This is the key change: ESCAPE instead of STRIP harmless tags.
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // ── 5. Remove dangerous URL schemes (defence in depth) ───────────
    $s = preg_replace('/\b(javascript|vbscript|livescript|mocha)\s*:/i', '[blocked]:', $s);

    // ── 6. Remove ASCII control characters ───────────────────────────
    //      Keep tab (\x09) and newlines. Remove everything else < \x20.
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);

    // ── 7. Normalise line endings ─────────────────────────────────────
    $s = str_replace("\r\n", "\n", $s);
    $s = str_replace("\r",   "\n", $s);

    return trim($s);
}

/* ── Math captcha ─────────────────────────────────────────── */
function captcha_generate(): array {
    start_session();
    $a  = random_int(2, 9);
    $b  = random_int(1, 9);
    $op = ['+', '-'][random_int(0, 1)];
    // Ensure subtraction result is always positive and non-zero
    if ($op === '-') {
        if ($a <= $b) $b = $a - 1;
        if ($b < 1)   { $op = '+'; }
    }
    $ans = $op === '+' ? $a + $b : $a - $b;
    $_SESSION['captcha_ans'] = $ans;
    return ['q' => "$a $op $b = ?"];
}
function captcha_verify(string $input): bool {
    start_session();
    $ans = $_SESSION['captcha_ans'] ?? null;
    unset($_SESSION['captcha_ans']);
    return $ans !== null && intval(trim($input)) === (int)$ans;
}

/* ── Friend helpers ───────────────────────────────────────── */
function friend_status(int $userId, int $otherId): string {
    if ($userId === $otherId) return 'self';
    try {
        $r = DB::row(
            'SELECT status, user_id FROM friends
             WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)',
            [$userId, $otherId, $otherId, $userId]
        );
        if (!$r) return 'none';
        if ($r['status'] === 'accepted') return 'friends';
        if ($r['status'] === 'pending')
            return ((int)$r['user_id'] === $userId) ? 'pending_sent' : 'pending_received';
    } catch (Throwable $e) {
        return 'none'; // Table may not exist on old installs
    }
    return 'none';
}

function friends_list(int $userId): array {
    try {
        return DB::rows("
            SELECT u.* FROM users u
            JOIN friends f ON (f.user_id=? AND f.friend_id=u.id)
                           OR (f.friend_id=? AND f.user_id=u.id)
            WHERE f.status='accepted'
            ORDER BY u.username
        ", [$userId, $userId]);
    } catch (Throwable $e) {
        return []; // Table may not exist on old installs
    }
}

function pending_requests(int $userId): array {
    try {
        return DB::rows("
            SELECT u.*, f.id AS fid FROM users u
            JOIN friends f ON f.user_id=u.id AND f.friend_id=? AND f.status='pending'
            ORDER BY f.created_at DESC
        ", [$userId]);
    } catch (Throwable $e) {
        return []; // Table may not exist on old installs
    }
}

/* ── Message unread count ─────────────────────────────────── */
function unread_messages(): int {
    global $USER;
    if (!$USER) return 0;
    try {
        return (int) DB::val(
            'SELECT COUNT(*) FROM messages
             WHERE receiver_id=? AND is_read=0 AND deleted_by_receiver=0',
            [$USER['id']]
        );
    } catch (Throwable $e) {
        return 0; // Table may not exist on old installs
    }
}

/* ── @mention processor ───────────────────────────────────── */
function process_mentions(string $content, int $postId, string $topicSlug, int $authorId, string $authorName): void {
    preg_match_all('/@([a-zA-Z0-9_\-]{3,30})/', $content, $m);
    $mentioned = array_unique($m[1] ?? []);
    foreach ($mentioned as $uname) {
        $t = DB::row('SELECT id FROM users WHERE username=?', [$uname]);
        if ($t && (int)$t['id'] !== $authorId) {
            add_notification((int)$t['id'], 'mention', [
                'from'      => $authorName,
                'topicSlug' => $topicSlug,
                'postId'    => $postId,
            ]);
        }
    }
}

/* ── Rate limiting ─────────────────────────────────────────── */
/**
 * Check if a user has exceeded their rate limit.
 * Returns true = allowed, false = rate-limited.
 * $limit = max posts allowed, $window = seconds window, $type = event type
 */
function rate_check(int $userId, string $type = 'post'): array {
    $enabled = cfg('rate_limit_enabled','0');
    if ($enabled !== '1') return ['ok'=>true,'wait'=>0];

    // Admins/moderators bypass rate limits
    $user = DB::row('SELECT role FROM users WHERE id=?', [$userId]);
    if ($user && in_array($user['role'],['admin','moderator'])) return ['ok'=>true,'wait'=>0];

    $limit  = (int) cfg('rate_limit_count','3');
    $window = (int) cfg('rate_limit_window','60'); // seconds

    $since = DB::sinceSeconds($window);
    $count = (int) DB::val(
        "SELECT COUNT(*) FROM rate_events
         WHERE user_id=? AND event_type=? AND created_at >= $since",
        [$userId, $type]
    );

    if ($count >= $limit) {
        // Find oldest event in window to calculate wait time
        $oldest = DB::row(
            "SELECT created_at FROM rate_events
             WHERE user_id=? AND event_type=? AND created_at >= $since
             ORDER BY created_at ASC LIMIT 1",
            [$userId, $type]
        );
        $wait = 0;
        if ($oldest) {
            $elapsed = time() - strtotime($oldest['created_at']);
            $wait    = max(0, $window - $elapsed);
        }
        return ['ok'=>false,'wait'=>$wait,'limit'=>$limit,'window'=>$window];
    }
    return ['ok'=>true,'wait'=>0];
}

function rate_record(int $userId, string $type = 'post'): void {
    $enabled = cfg('rate_limit_enabled','0');
    if ($enabled !== '1') return;
    DB::insert('INSERT INTO rate_events (user_id,event_type) VALUES (?,?)', [$userId, $type]);
    // Prune old events (older than 24h) periodically
    if (random_int(1,50) === 1) {
        $cutoff = DB::sinceSeconds(86400);
        DB::run("DELETE FROM rate_events WHERE created_at < $cutoff");
    }
}

/* ── Post/Reply captcha ────────────────────────────────────── */
function post_captcha_enabled(): bool {
    return cfg('post_captcha_enabled','0') === '1';
}
function post_captcha_verify(string $input): bool {
    start_session();
    $key = 'post_captcha_ans';
    $ans = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);
    return $ans !== null && intval(trim($input)) === (int)$ans;
}
function post_captcha_generate(): array {
    start_session();
    $a  = random_int(2, 9);
    $b  = random_int(1, 9);
    $op = ['+', '-'][random_int(0, 1)];
    if ($op === '-') {
        if ($a <= $b) $b = $a - 1;
        if ($b < 1)   { $op = '+'; }
    }
    $ans = $op === '+' ? $a + $b : $a - $b;
    $_SESSION['post_captcha_ans'] = $ans;
    return ['q' => "$a $op $b = ?"];
}

/* ── Media embed processor ─────────────────────────────────────
 * Converts bare URLs in post content into embeds.
 * Called server-side so embeds render even without JS.
 * Each URL on its own line (possibly wrapped in <p>) gets replaced.
 */
function process_embeds(string $html): string {
    // Process each <p>...</p> block that contains a bare URL.
    // We do this line by line to avoid catastrophic regex failures.
    return preg_replace_callback(
        '/<p>\s*(https?:\/\/[^\s<>"\']+)\s*<\/p>/i',
        function ($m) {
            $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            $em  = try_embed($url);
            return $em !== null ? $em : $m[0];
        },
        $html
    );
}

function try_embed(string $url): ?string {
    // YouTube (full URL)
    if (preg_match('~youtube\.com/watch\?.*v=([a-zA-Z0-9_\-]{11})~', $url, $m)) {
        return yt_embed($m[1]);
    }
    // YouTube short URL
    if (preg_match('~youtu\.be/([a-zA-Z0-9_\-]{11})~', $url, $m)) {
        return yt_embed($m[1]);
    }
    // YouTube Shorts
    if (preg_match('~youtube\.com/shorts/([a-zA-Z0-9_\-]{11})~', $url, $m)) {
        return yt_embed($m[1], true);
    }
    // YouTube Music
    if (preg_match('~music\.youtube\.com/watch\?.*v=([a-zA-Z0-9_\-]{11})~', $url, $m)) {
        return yt_embed($m[1]);
    }
    // Vimeo
    if (preg_match('~vimeo\.com/(\d{5,12})~', $url, $m)) {
        return embed_iframe('https://player.vimeo.com/video/' . $m[1] . '?dnt=1', 'Vimeo');
    }
    // Twitch VOD
    if (preg_match('~twitch\.tv/videos/(\d+)~', $url, $m)) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return embed_iframe('https://player.twitch.tv/?video=v' . $m[1] . '&parent=' . urlencode($host) . '&autoplay=false', 'Twitch VOD');
    }
    // Twitch channel
    if (preg_match('~twitch\.tv/([a-zA-Z0-9_]{4,25})(?:\?|$|/)~', $url, $m)) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return embed_iframe('https://player.twitch.tv/?channel=' . $m[1] . '&parent=' . urlencode($host) . '&autoplay=false', 'Twitch');
    }
    // Dailymotion
    if (preg_match('~dailymotion\.com/video/([a-zA-Z0-9]+)~', $url, $m)) {
        return embed_iframe('https://www.dailymotion.com/embed/video/' . $m[1], 'Dailymotion');
    }
    // Streamable
    if (preg_match('~streamable\.com/([a-zA-Z0-9]+)~', $url, $m)) {
        return embed_iframe('https://streamable.com/e/' . $m[1], 'Streamable');
    }
    // Rumble
    if (preg_match('~rumble\.com/(?:embed/)?([a-zA-Z0-9\-]+)(?:\.html)?~', $url, $m)) {
        return embed_iframe('https://rumble.com/embed/' . $m[1] . '/', 'Rumble');
    }
    // Spotify
    if (preg_match('~open\.spotify\.com/(track|album|playlist|episode|artist)/([a-zA-Z0-9]+)~', $url, $m)) {
        $h = ($m[1] === 'track' || $m[1] === 'episode') ? '152' : '352';
        return '<div class="embed-spotify"><iframe src="https://open.spotify.com/embed/' . $m[1] . '/' . $m[2]
            . '" width="100%" height="' . $h . '" frameborder="0" allow="autoplay;clipboard-write;encrypted-media;fullscreen" loading="lazy"></iframe></div>';
    }
    // SoundCloud
    if (preg_match('~soundcloud\.com/[a-zA-Z0-9\-_]+/[a-zA-Z0-9\-_]+~', $url)) {
        return '<div class="embed-sc"><iframe width="100%" height="166" scrolling="no" frameborder="no" allow="autoplay"'
            . ' src="https://w.soundcloud.com/player/?url=' . urlencode($url) . '&color=%233b82f6&auto_play=false"></iframe></div>';
    }
    // Loom
    if (preg_match('~loom\.com/share/([a-zA-Z0-9]+)~', $url, $m)) {
        return embed_iframe('https://www.loom.com/embed/' . $m[1], 'Loom');
    }
    // CodePen
    if (preg_match('~codepen\.io/([a-zA-Z0-9\-_]+)/pen/([a-zA-Z0-9]+)~', $url, $m)) {
        return embed_iframe_tall('https://codepen.io/' . $m[1] . '/embed/' . $m[2] . '?default-tab=result', 'CodePen', 420);
    }
    // JSFiddle
    if (preg_match('~jsfiddle\.net/([a-zA-Z0-9/]+)~', $url, $m)) {
        return embed_iframe_tall('https://jsfiddle.net/' . rtrim($m[1], '/') . '/embedded/result', 'JSFiddle', 380);
    }
    // Twitter / X
    if (preg_match('~(?:twitter|x)\.com/[a-zA-Z0-9_]+/status/(\d+)~', $url, $m)) {
        $safe = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return '<div class="embed-tweet" data-tweet-id="' . $m[1] . '">'
            . '<a href="' . $safe . '" target="_blank" rel="noopener" class="tweet-fallback">🐦 View on Twitter/X →</a></div>';
    }
    // TED Talks
    if (preg_match('~ted\.com/talks/([a-zA-Z0-9_]+)~', $url, $m)) {
        return embed_iframe('https://embed.ted.com/talks/' . $m[1], 'TED Talk');
    }
    // Bandcamp track
    if (preg_match('~([a-zA-Z0-9\-]+)\.bandcamp\.com/track/([a-zA-Z0-9\-]+)~', $url, $m)) {
        return '<div class="embed-spotify"><iframe style="border:0;width:100%;height:120px"'
            . ' src="https://bandcamp.com/EmbeddedPlayer/track=' . urlencode($m[2]) . '/size=large/bgcol=ffffff/linkcol=0687f5/tracklist=false/artwork=small/" seamless></iframe></div>';
    }
    // No match
    return null;
}


function yt_embed(string $id, bool $short = false): string {
    $pad = $short ? 'padding-bottom:177.78%;max-width:360px' : 'padding-bottom:56.25%';
    return '<div class="embed-wrap" style="'.$pad.'"><iframe class="embed-yt" src="https://www.youtube-nocookie.com/embed/'.htmlspecialchars($id).'?rel=0&amp;modestbranding=1" allowfullscreen loading="lazy" title="YouTube video"></iframe></div>';
}
function embed_iframe(string $src, string $label = ''): string {
    return '<div class="embed-wrap"><iframe class="embed-yt" src="'.htmlspecialchars($src).'" allowfullscreen loading="lazy" title="'.htmlspecialchars($label).'"></iframe></div>';
}
function embed_iframe_tall(string $src, string $label = '', int $height = 400): string {
    return '<div class="embed-wrap" style="padding-bottom:0;height:'.$height.'px"><iframe class="embed-yt" src="'.htmlspecialchars($src).'" allowfullscreen loading="lazy" title="'.htmlspecialchars($label).'"></iframe></div>';
}

/* ── Karma tier system ─────────────────────────────────────── */
/* ── Category permission helpers ──────────────────────── */
// Roles in ascending order: guest < member < moderator < admin
function role_level(string $role): int {
    return match($role) {
        'admin'     => 30,
        'moderator' => 20,
        'member'    => 10,
        default     => 0,   // guest / not logged in
    };
}

function user_role_level(?array $u = null): int {
    global $USER;
    $u = $u ?? $USER;
    if (!$u) return 0;
    return role_level($u['role'] ?? 'member');
}

function can_read_category(array $cat, ?array $u = null): bool {
    $required = role_level($cat['read_role'] ?? 'guest');
    return user_role_level($u) >= $required;
}

function can_post_topic(array $cat, ?array $u = null): bool {
    $required = role_level($cat['post_role'] ?? 'member');
    return user_role_level($u) >= $required;
}

function can_reply_topic(array $cat, ?array $u = null): bool {
    $required = role_level($cat['reply_role'] ?? 'member');
    return user_role_level($u) >= $required;
}

function karma_tier(int $karma): array {
    $tiers = [
        ['name'=>'Newcomer',     'min'=>0,    'max'=>9,    'icon'=>'🌱', 'color'=>'#94a3b8', 'next'=>10],
        ['name'=>'Member',       'min'=>10,   'max'=>49,   'icon'=>'💬', 'color'=>'#64748b', 'next'=>50],
        ['name'=>'Regular',      'min'=>50,   'max'=>99,   'icon'=>'⭐', 'color'=>'#f59e0b', 'next'=>100],
        ['name'=>'Contributor',  'min'=>100,  'max'=>249,  'icon'=>'🌟', 'color'=>'#f97316', 'next'=>250],
        ['name'=>'Veteran',      'min'=>250,  'max'=>499,  'icon'=>'🔥', 'color'=>'#ef4444', 'next'=>500],
        ['name'=>'Expert',       'min'=>500,  'max'=>999,  'icon'=>'💎', 'color'=>'#8b5cf6', 'next'=>1000],
        ['name'=>'Elite',        'min'=>1000, 'max'=>2499, 'icon'=>'👑', 'color'=>'#7c3aed', 'next'=>2500],
        ['name'=>'Legend',       'min'=>2500, 'max'=>PHP_INT_MAX, 'icon'=>'🏆', 'color'=>'#6d28d9', 'next'=>null],
    ];
    $current = $tiers[0];
    foreach ($tiers as $tier) {
        if ($karma >= $tier['min']) $current = $tier;
        else break;
    }
    // Progress to next tier
    $progress = 0;
    if ($current['next'] !== null) {
        $range    = $current['next'] - $current['min'];
        $earned   = $karma - $current['min'];
        $progress = $range > 0 ? min(100, (int)round($earned / $range * 100)) : 100;
    } else {
        $progress = 100;
    }
    return array_merge($current, ['karma' => $karma, 'progress' => $progress]);
}
