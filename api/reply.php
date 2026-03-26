<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!$USER) json_out(['error'=>'Not logged in'],401);
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['error'=>'Method not allowed'],405);
if (!csrf_ok()) json_out(['error'=>'Invalid CSRF token'],403);
if ($USER['silenced']) json_out(['error'=>'You are silenced and cannot post'],403);

// ── Rate limit check ─────────────────────────────────────────
$rl = rate_check((int)$USER['id'], 'post');
if (!$rl['ok']) {
    $wait = (int)$rl['wait'];
    json_out(['error'=>"You're posting too fast. Please wait {$wait} second".($wait!==1?'s':'').' before posting again.','rate_limited'=>true,'wait'=>$wait], 429);
}

// ── Post captcha check ───────────────────────────────────────
if (post_captcha_enabled()) {
    $cap = post('post_captcha');
    if (!post_captcha_verify($cap)) {
        json_out(['error'=>'Incorrect security answer.','captcha_failed'=>true], 403);
    }
}

$slug    = sanitise(post('slug'));
$content = sanitise(post('content'));
$replyTo = (int)post('reply_to');

if (!$slug || !$content) json_out(['error'=>'Missing required fields'],400);
if (strlen($content) < 1)     json_out(['error'=>'Reply cannot be empty'],400);
if (strlen($content) > 20000) json_out(['error'=>'Post too long (max 20,000 chars)'],400);

$topic = DB::row('SELECT * FROM topics WHERE slug=?', [$slug]);
if (!$topic) json_out(['error'=>'Topic not found'],404);
if ($topic['closed']) json_out(['error'=>'This topic is closed'],403);
$replyCat = DB::row('SELECT * FROM categories WHERE id=?',[$topic['category_id']]);
if (!can_reply_topic($replyCat ?? [])) json_out(['error'=>'No permission to reply in this category'],403);

$last = DB::row('SELECT post_num FROM posts WHERE topic_id=? ORDER BY post_num DESC LIMIT 1',[$topic['id']]);
$num  = ($last ? (int)$last['post_num'] : 0) + 1;

$pid = DB::insert(
    'INSERT INTO posts (topic_id,user_id,content,post_num,reply_to) VALUES (?,?,?,?,?)',
    [$topic['id'], $USER['id'], $content, $num, $replyTo ?: null]
);

// Record rate limit event AFTER successful post
rate_record((int)$USER['id'], 'post');

$now = DB::now();
DB::run("UPDATE topics SET last_post_at=$now,reply_count=reply_count+1 WHERE id=?",[$topic['id']]);
DB::run('UPDATE users SET post_count=post_count+1 WHERE id=?',[$USER['id']]);
DB::run('UPDATE categories SET post_count=post_count+1 WHERE id=?',[$topic['category_id']]);

if ($topic['user_id'] !== $USER['id']) {
    add_notification((int)$topic['user_id'],'reply',[
        'slug'=>$topic['slug'],'title'=>$topic['title'],'from'=>$USER['username']
    ]);
}
process_mentions($content, $pid, $topic['slug'], (int)$USER['id'], $USER['username']);

$post = DB::row("
    SELECT p.*,u.username,u.avatar,u.role,u.post_count,0 AS likes
    FROM posts p JOIN users u ON u.id=p.user_id WHERE p.id=?
",[$pid]);

// Send new captcha if post captcha enabled
$newCaptcha = post_captcha_enabled() ? post_captcha_generate() : null;

addon_hook('after_reply_saved', ['post_id'=>$pid,'topic_id'=>$topic['id'],'user_id'=>(int)$USER['id']]);
json_out(['ok'=>true,'post'=>$post,'new_captcha'=>$newCaptcha]);
