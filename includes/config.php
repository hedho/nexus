<?php
/**
 * Nexus Forum — Core Configuration
 * Auto-detects base path. Loads db_config.php if present.
 */
if (!defined('NEXUS')) { http_response_code(403); exit('Forbidden'); }

/* ── Paths ──────────────────────────────────────────────────── */
define('ROOT',    dirname(__DIR__));
define('DATA',    ROOT . '/data');
define('UPLOADS', ROOT . '/public/uploads');

/* ── Base URL detection ─────────────────────────────────────── */
if (!defined('BASE')) {
    $docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $rootPath = str_replace('\\', '/', ROOT);
    $base     = str_replace($docRoot, '', $rootPath);
    $base     = '/' . trim($base, '/');
    define('BASE', $base === '/' ? '' : $base);
}

/* ── Database config (written by installer) ─────────────────── */
$dbCfg = ROOT . '/includes/db_config.php';
if (file_exists($dbCfg)) {
    require_once $dbCfg;
} else {
    // Default: SQLite (installer not yet run, or config missing)
    if (!defined('DB_DRIVER')) define('DB_DRIVER', 'sqlite');
}

/* ── Security headers (applied on every page load) ──────────── */
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // Content-Security-Policy — blocks injected scripts even if XSS were possible.
    // script-src 'self' allows our own JS; cdnjs for Prism.js; 'unsafe-inline' for
    // onclick= attributes used throughout the forum (toolbar buttons, embeds etc.).
    // frame-src allows trusted embed domains only.
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://platform.twitter.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: https:",
        "media-src 'self' https:",
        "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://player.twitch.tv https://clips.twitch.tv https://open.spotify.com https://w.soundcloud.com https://soundcloud.com https://bandcamp.com https://codepen.io https://jsfiddle.net https://www.loom.com https://rumble.com https://embed.ted.com https://www.dailymotion.com https://streamable.com https://platform.twitter.com https://syndication.twitter.com",
        "connect-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ]);
    header('Content-Security-Policy: ' . $csp);
    // Only set HSTS if running HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/* ── Error handling ──────────────────────────────────────────── */
// Production: hide errors from users, log them instead
if (!(defined('NEXUS_DEBUG') && NEXUS_DEBUG)) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}
