<?php
/**
 * Live chat API for private messages.
 * Actions: send, poll, delete, conversations
 */
require_once __DIR__ . '/../includes/bootstrap.php';
must_login();
if (!csrf_ok()) json_out(['error' => 'CSRF'], 403);

$action = post('action');
$uid    = (int)$USER['id'];

/* ── Helper: build conversation_id ───────────────────── */
function conv_id(int $a, int $b): string {
    return min($a,$b) . '-' . max($a,$b);
}

/* ── Send a message ──────────────────────────────────── */
if ($action === 'send') {
    $toId   = (int)post('to_id');
    $body   = sanitise(post('body'));

    if (!$toId || $toId === $uid) json_out(['error' => 'Invalid recipient'], 400);
    if (!$body)                   json_out(['error' => 'Message is empty'], 400);
    if (mb_strlen($body) > 2000)  json_out(['error' => 'Message too long (max 2000)'], 400);

    $other = DB::row('SELECT id,username FROM users WHERE id=? AND suspended=0', [$toId]);
    if (!$other) json_out(['error' => 'User not found'], 404);

    $convId = conv_id($uid, $toId);

    $msgId = DB::insert(
        'INSERT INTO messages (sender_id,receiver_id,subject,body,conversation_id) VALUES (?,?,?,?,?)',
        [$uid, $toId, '', $body, $convId]
    );

    // Notify recipient
    add_notification($toId, 'message', [
        'from'    => $USER['username'],
        'from_id' => $uid,
        'subject' => mb_substr($body, 0, 60) . (mb_strlen($body) > 60 ? '…' : ''),
    ]);

    // Return the new message row for live append
    $msg = DB::row(
        'SELECT m.*, u.username AS sender_name, u.avatar AS sender_av
         FROM messages m JOIN users u ON u.id=m.sender_id
         WHERE m.id=?', [$msgId]
    );
    json_out(['ok' => true, 'message' => $msg]);
}

/* ── Poll: fetch new messages since a given id ───────── */
if ($action === 'poll') {
    $otherId  = (int)post('other_id');
    $sinceId  = (int)post('since_id');
    if (!$otherId) json_out(['error' => 'Missing other_id'], 400);

    $convId = conv_id($uid, $otherId);
    $msgs   = DB::rows(
        'SELECT m.*, u.username AS sender_name, u.avatar AS sender_av
         FROM messages m JOIN users u ON u.id=m.sender_id
         WHERE m.conversation_id=? AND m.id>?
         ORDER BY m.id ASC LIMIT 50',
        [$convId, $sinceId]
    );

    // Mark messages from other user as read
    if ($msgs) {
        $now = DB::now();
        DB::run(
            "UPDATE messages SET is_read=1
             WHERE conversation_id=? AND receiver_id=? AND `is_read`=0",
            [$convId, $uid]
        );
    }

    json_out(['ok' => true, 'messages' => $msgs]);
}

/* ── Load full conversation ──────────────────────────── */
if ($action === 'load') {
    $otherId = (int)post('other_id');
    $before  = (int)post('before_id'); // for pagination
    if (!$otherId) json_out(['error' => 'Missing other_id'], 400);

    $convId = conv_id($uid, $otherId);
    $params = [$convId];
    $where  = '';
    if ($before) {
        $where   = ' AND m.id < ?';
        $params[] = $before;
    }

    $msgs = DB::rows(
        "SELECT m.*, u.username AS sender_name, u.avatar AS sender_av
         FROM messages m JOIN users u ON u.id=m.sender_id
         WHERE m.conversation_id=? $where
         ORDER BY m.id DESC LIMIT 40",
        $params
    );
    $msgs = array_reverse($msgs); // oldest first

    // Mark as read
    DB::run(
        "UPDATE messages SET `is_read`=1
         WHERE conversation_id=? AND receiver_id=? AND `is_read`=0",
        [$convId, $uid]
    );

    $other = DB::row('SELECT id,username,avatar,role,karma,last_seen FROM users WHERE id=?', [$otherId]);
    json_out(['ok' => true, 'messages' => $msgs, 'other' => $other]);
}

/* ── List conversations ──────────────────────────────── */
if ($action === 'conversations') {
    $convs = DB::rows(
        "SELECT m.*,
                u.username AS other_name, u.avatar AS other_av,
                (SELECT COUNT(*) FROM messages m2
                 WHERE m2.conversation_id=m.conversation_id
                   AND m2.receiver_id=? AND m2.is_read=0) AS unread_count
         FROM messages m
         JOIN users u ON u.id = CASE WHEN m.sender_id=? THEN m.receiver_id ELSE m.sender_id END
         WHERE m.conversation_id IN (
             SELECT conversation_id FROM messages
             WHERE sender_id=? OR receiver_id=?
         )
         AND m.id IN (
             SELECT MAX(id) FROM messages
             WHERE sender_id=? OR receiver_id=?
             GROUP BY conversation_id
         )
         ORDER BY m.created_at DESC",
        [$uid, $uid, $uid, $uid, $uid, $uid]
    );
    json_out(['ok' => true, 'conversations' => $convs]);
}

/* ── Delete a message ────────────────────────────────── */
if ($action === 'delete') {
    $msgId = (int)post('msg_id');
    $msg   = DB::row('SELECT * FROM messages WHERE id=?', [$msgId]);
    if (!$msg) json_out(['error' => 'Not found'], 404);
    if ($msg['sender_id'] !== $uid && $msg['receiver_id'] !== $uid) json_out(['error' => 'Forbidden'], 403);

    if ($msg['sender_id'] === $uid)
        DB::run('UPDATE messages SET deleted_by_sender=1 WHERE id=?', [$msgId]);
    else
        DB::run('UPDATE messages SET deleted_by_receiver=1 WHERE id=?', [$msgId]);

    json_out(['ok' => true]);
}

json_out(['error' => 'Unknown action'], 400);
