<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$q = sanitise(get('q'));
if (mb_strlen($q) < 2) json_out([]);
$rows = DB::rows(
    "SELECT id, username, avatar FROM users WHERE username LIKE ? AND suspended=0 ORDER BY username LIMIT 8",
    [$q.'%']
);
json_out($rows);
