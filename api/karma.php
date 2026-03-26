<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin();
if (!csrf_ok()) json_out(['error' => 'CSRF'], 403);

$uid    = (int)post('user_id');
$action = post('action'); // set | add | subtract
$amount = (int)post('amount');
$reason = sanitise(post('reason'));

if (!$uid)                              json_out(['error' => 'Invalid user'],    400);
if (!in_array($action, ['set','add','subtract'])) json_out(['error' => 'Invalid action'], 400);
if ($amount < 0)                        json_out(['error' => 'Amount must be >= 0'], 400);
if ($amount > 999999)                   json_out(['error' => 'Amount too large'], 400);

$user = DB::row('SELECT id, username, karma FROM users WHERE id=?', [$uid]);
if (!$user) json_out(['error' => 'User not found'], 404);

$old = (int)$user['karma'];

switch ($action) {
    case 'set':
        $new = $amount;
        DB::run('UPDATE users SET karma=? WHERE id=?', [$new, $uid]);
        break;
    case 'add':
        DB::run('UPDATE users SET karma=karma+? WHERE id=?', [$amount, $uid]);
        $new = $old + $amount;
        break;
    case 'subtract':
        $new = max(0, $old - $amount);
        DB::run('UPDATE users SET karma=? WHERE id=?', [$new, $uid]);
        break;
}

// Add an audit notification to the user
if ($reason) {
    add_notification($uid, 'karma_admin', [
        'from'   => $USER['username'],
        'change' => ($new - $old),
        'new'    => $new,
        'reason' => $reason,
    ]);
}

json_out(['ok' => true, 'old' => $old, 'new' => $new, 'username' => $user['username']]);
