<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!$USER) json_out(['error'=>'Not logged in'],401);
if (!csrf_ok()) json_out(['error'=>'CSRF'],403);
$pid  = (int)post('post_id');
$post = DB::row('SELECT * FROM posts WHERE id=?',[$pid]);
if (!$post) json_out(['error'=>'Not found'],404);
if ($post['user_id']!==$USER['id'] && !is_admin()) json_out(['error'=>'Forbidden'],403);
DB::run('UPDATE posts SET deleted=1 WHERE id=?',[$pid]);
json_out(['ok'=>true]);
