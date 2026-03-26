<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// ── Auth & method ────────────────────────────────────────────────
if (!$USER)
    json_out(['error' => 'Not logged in'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    json_out(['error' => 'Method not allowed'], 405);
if (!csrf_ok())
    json_out(['error' => 'Invalid CSRF token'], 403);

// ── File present? ────────────────────────────────────────────────
// $_FILES is empty when PHP silently drops the upload because it exceeds
// php.ini upload_max_filesize or post_max_size.
if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
    $phpLimit = ini_get('upload_max_filesize') ?: '?';
    json_out(['error' => 'No file received. Check PHP upload_max_filesize (currently: ' . $phpLimit . ')'], 400);
}

$f    = $_FILES['file'];
$code = $f['error'] ?? UPLOAD_ERR_OK;

// ── PHP upload error codes ────────────────────────────────────────
if ($code !== UPLOAD_ERR_OK) {
    $msgs = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit (upload_max_filesize)',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension',
    ];
    json_out(['error' => $msgs[$code] ?? 'Upload error code ' . $code], 400);
}

// ── Detect real MIME type from file content (not browser header) ──
// finfo is reliable; fall back to getimagesize if finfo not available.
$realMime = null;
if (function_exists('finfo_open')) {
    $fi       = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($fi, $f['tmp_name']);
    finfo_close($fi);
} elseif (function_exists('mime_content_type')) {
    $realMime = mime_content_type($f['tmp_name']);
}

// Validate the real MIME type
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if ($realMime && !in_array($realMime, $allowed)) {
    json_out(['error' => 'Only JPEG, PNG, GIF and WebP images are allowed (detected: ' . $realMime . ')'], 400);
}

// ── Size limit ────────────────────────────────────────────────────
$maxMb    = max(1, min(50, (int) cfg('max_upload_mb', '5')));
$maxBytes = $maxMb * 1024 * 1024;
if ($f['size'] > $maxBytes) {
    json_out(['error' => 'Image too large — maximum is ' . $maxMb . ' MB'], 400);
}

// ── Validate it is a real image (catches non-images finfo might miss) ──
$info = @getimagesize($f['tmp_name']);
if (!$info) {
    json_out(['error' => 'File does not appear to be a valid image'], 400);
}

// ── Safe extension from detected MIME ────────────────────────────
$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
$detectedMime = $realMime ?: $info['mime'];
$ext  = $mimeToExt[$detectedMime] ?? ($mimeToExt[$info['mime']] ?? 'jpg');
$name = 'img_' . uniqid('', true) . '.' . $ext;

// ── Ensure upload directory exists and is writable ────────────────
$dir = UPLOADS . '/';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
        json_out(['error' => 'Upload directory could not be created'], 500);
    }
}
if (!is_writable($dir)) {
    json_out(['error' => 'Upload directory is not writable'], 500);
}

// ── Move file ─────────────────────────────────────────────────────
if (!move_uploaded_file($f['tmp_name'], $dir . $name)) {
    json_out(['error' => 'Could not save uploaded file'], 500);
}

// ── Add security .htaccess to uploads dir (prevent PHP execution) ─
$ht = $dir . '.htaccess';
if (!file_exists($ht)) {
    file_put_contents($ht,
        "# Deny PHP execution in uploads\n" .
        "<FilesMatch \"\\.php\$\">\n" .
        "    Require all denied\n" .
        "</FilesMatch>\n"
    );
}

json_out(['ok' => true, 'url' => BASE . '/public/uploads/' . $name]);
