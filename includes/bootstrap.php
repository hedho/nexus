<?php
define('NEXUS', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

start_session();

// Redirect to installer if not installed
if (!file_exists(DATA . '/installed.lock')) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/install') === false) {
        header('Location: ' . BASE . '/install/');
        exit;
    }
}

// Auto-migrate: ensure all tables exist for upgraded installs.
// CREATE TABLE IF NOT EXISTS is idempotent — safe to run on every request.
// This fixes "table not found" errors when upgrading from older versions.
static $_dbInited = false;
if (!$_dbInited) {
    try {
        DB::init();
        $_dbInited = true;
    } catch (Throwable $e) {
        // Log but don't crash — if DB is unreachable the next query will fail with a clearer error
        error_log('Nexus DB::init() failed: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/markdown.php';
require_once __DIR__ . '/addons.php';

// Set global current user
$USER = current_user();

// Boot addons after user is set
AddonManager::boot();
