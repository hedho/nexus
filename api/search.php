<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$q = get('q');
if (mb_strlen($q) < 2) json_out(['topics' => [], 'posts' => []]);

$like = '%' . $q . '%';

// Topic title matches
$topics = DB::rows(
    "SELECT t.id, t.title, t.slug, c.name AS cat, c.color AS cat_color
     FROM topics t
     JOIN categories c ON c.id = t.category_id
     WHERE t.title LIKE ?
     ORDER BY t.last_post_at DESC
     LIMIT 6",
    [$like]
);

// Post content matches — include post_id for goto link
$posts = DB::rows(
    "SELECT p.id AS post_id, p.content, p.post_num,
            t.title AS topic_title, t.slug AS topic_slug,
            u.username
     FROM posts p
     JOIN topics t ON t.id = p.topic_id
     JOIN users u  ON u.id = p.user_id
     WHERE p.content LIKE ? AND p.deleted = 0
     ORDER BY p.created_at DESC
     LIMIT 5",
    [$like]
);

json_out(['topics' => $topics, 'posts' => $posts]);
