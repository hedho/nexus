<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!$USER) json_out(['error'=>'Not logged in'],401);
if (!csrf_ok()) json_out(['error'=>'CSRF'],403);

$action   = sanitise(post('action'));
$targetId = (int)post('target_id');
if (!$targetId || $targetId === $USER['id']) json_out(['error'=>'Invalid target'],400);

$target = DB::row('SELECT id,username FROM users WHERE id=?',[$targetId]);
if (!$target) json_out(['error'=>'User not found'],404);

$uid = (int)$USER['id'];

switch ($action) {
    case 'send':
        // Send friend request
        $existing = DB::row('SELECT * FROM friends WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)',
            [$uid,$targetId,$targetId,$uid]);
        if ($existing) json_out(['error'=>'Request already exists'],400);
        DB::insert('INSERT INTO friends (user_id,friend_id,status) VALUES (?,?,\'pending\')',[$uid,$targetId]);
        add_notification($targetId,'friend_request',[
            'from'=>$USER['username'],'from_id'=>$uid
        ]);
        json_out(['ok'=>true,'msg'=>'Friend request sent!']);
        break;

    case 'cancel':
        // Cancel outgoing request
        DB::run('DELETE FROM friends WHERE user_id=? AND friend_id=? AND status=\'pending\'',[$uid,$targetId]);
        json_out(['ok'=>true,'msg'=>'Request cancelled.']);
        break;

    case 'accept':
        // Accept incoming request
        $req = DB::row('SELECT * FROM friends WHERE user_id=? AND friend_id=? AND status=\'pending\'',[$targetId,$uid]);
        if (!$req) json_out(['error'=>'No pending request found'],404);
        DB::run("UPDATE friends SET status='accepted' WHERE id=?",[$req['id']]);
        add_notification($targetId,'friend_accepted',[
            'from'=>$USER['username'],'from_id'=>$uid
        ]);
        json_out(['ok'=>true,'msg'=>'Friend request accepted!']);
        break;

    case 'decline':
        // Decline incoming request
        DB::run('DELETE FROM friends WHERE user_id=? AND friend_id=? AND status=\'pending\'',[$targetId,$uid]);
        json_out(['ok'=>true,'msg'=>'Request declined.']);
        break;

    case 'remove':
        // Remove existing friendship
        DB::run('DELETE FROM friends WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)',
            [$uid,$targetId,$targetId,$uid]);
        json_out(['ok'=>true,'msg'=>'Friend removed.']);
        break;

    default:
        json_out(['error'=>'Unknown action'],400);
}
