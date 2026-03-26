<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!$USER) json_out([],401);

// Mark-read requires POST + valid CSRF
if ($_SERVER['REQUEST_METHOD']==='POST' && post('action')==='read') {
    if (!csrf_ok()) json_out(['error'=>'Invalid CSRF'],403);
    DB::run('UPDATE notifications SET `read`=1 WHERE user_id=?',[$USER['id']]);
    json_out(['ok'=>true]);
}
// Allow GET for polling
if (isset($_GET['action']) && $_GET['action']==='read') {
    // Legacy GET — accept but deprecated
    DB::run('UPDATE notifications SET `read`=1 WHERE user_id=?',[$USER['id']]);
    json_out(['ok'=>true]);
}

$rows = DB::rows('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 25',[$USER['id']]);
foreach ($rows as &$r) $r['payload'] = json_decode($r['payload'],true) ?: [];
json_out($rows);
