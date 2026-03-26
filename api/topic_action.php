<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin();
if (!csrf_ok()) go('/');
$id     = (int)post('id');
$action = post('action');
// Validate referer to prevent open redirect
$rawRef  = $_SERVER['HTTP_REFERER'] ?? '';
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$ref     = (strpos($rawRef, $baseUrl) === 0) ? $rawRef : u('/');
$map    = [
    'pin'       => 'UPDATE topics SET pinned=1 WHERE id=?',
    'unpin'     => 'UPDATE topics SET pinned=0 WHERE id=?',
    'close'     => 'UPDATE topics SET closed=1 WHERE id=?',
    'open'      => 'UPDATE topics SET closed=0 WHERE id=?',
    'archive'   => 'UPDATE topics SET archived=1 WHERE id=?',
    'unarchive' => 'UPDATE topics SET archived=0 WHERE id=?',
];
if ($id && isset($map[$action])) DB::run($map[$action], [$id]);
header('Location: ' . $ref);
exit;
