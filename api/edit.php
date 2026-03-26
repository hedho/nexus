<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!$USER) json_out(['error'=>'Not logged in'],401);
if (!csrf_ok()) json_out(['error'=>'CSRF'],403);

$pid     = (int)post('post_id');
$content = sanitise(post('content'));
$reason  = sanitise(post('reason'));

if (!$pid || !$content) json_out(['error'=>'Missing fields'],400);
if (strlen($content) > 20000) json_out(['error'=>'Post too long'],400);

$post = DB::row('SELECT * FROM posts WHERE id=?',[$pid]);
if (!$post) json_out(['error'=>'Not found'],404);
if ($post['user_id']!==$USER['id'] && !is_admin()) json_out(['error'=>'Forbidden'],403);

$now = DB::now();
DB::run("UPDATE posts SET content=?,edited=1,edit_reason=?,updated_at=$now WHERE id=?",
    [$content, $reason ?: null, $pid]);

json_out(['ok'=>true,'content'=>$content]);
