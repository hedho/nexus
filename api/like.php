<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!$USER) json_out(['error'=>'Not logged in'],401);
if (!csrf_ok()) json_out(['error'=>'CSRF'],403);
$pid = (int)post('post_id');
if (!$pid) json_out(['error'=>'Missing post_id'],400);
$post = DB::row('SELECT * FROM posts WHERE id=?',[$pid]);
if (!$post) json_out(['error'=>'Not found'],404);

$exists = DB::row('SELECT 1 FROM likes WHERE post_id=? AND user_id=?',[$pid,$USER['id']]);
if ($exists) {
    DB::run('DELETE FROM likes WHERE post_id=? AND user_id=?',[$pid,$USER['id']]);
    DB::run('UPDATE posts SET likes=MAX(0,likes-1) WHERE id=?',[$pid]);
    // Remove karma from post author
    add_karma((int)$post['user_id'], -1);
    $liked = false;
} else {
    DB::insertIgnore('likes', ['post_id','user_id'], [$pid,$USER['id']]);
    DB::run('UPDATE posts SET likes=likes+1 WHERE id=?',[$pid]);
    // Award karma to post author
    add_karma((int)$post['user_id'], 1);
    $liked = true;
}
$count = (int)DB::val('SELECT COUNT(*) FROM likes WHERE post_id=?',[$pid]);
json_out(['ok'=>true,'liked'=>$liked,'count'=>$count]);
