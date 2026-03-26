<?php
$_title  = isset($PAGE_TITLE) ? e($PAGE_TITLE) . ' — ' : '';
$_site   = e(cfg('site_name', 'Nexus Forum'));
$_unread = unread_count();
$_msgs   = unread_messages();
$_cats   = nav_categories();
?>
<!DOCTYPE html>
<html lang="en">
<script>
(function(){
  try{
    var t=localStorage.getItem('nexus-theme');
    var p=window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches;
    if(t==='dark'||(t===null&&p)) document.documentElement.setAttribute('data-theme','dark');
  }catch(e){}
})();
</script>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $_title . $_site ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
  <?= active_theme_css() ?>
  <?= cfg('custom_head') ?>
<?php /* ── before_page_head addon hook (collector) ── */
echo addon_collect('before_page_head');
?>
</head>
<body>
<header class="site-header">
  <div class="hdr-inner">
    <div class="hdr-left">
      <button class="burger" id="burgerBtn"><span></span><span></span><span></span></button>
      <a href="<?= u('/') ?>" class="logo">
        <?php if (cfg('logo_url')): ?>
          <img src="<?= e(cfg('logo_url')) ?>" alt="<?= $_site ?>" class="logo-img">
        <?php else: ?>
          <div class="logo-mark"><?= e(substr(cfg('site_name','N'),0,1)) ?></div>
        <?php endif; ?>
        <span class="logo-name"><?= $_site ?></span>
      </a>
    </div>

    <div class="hdr-search">
      <div class="search-wrap">
        <svg class="srch-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8.5" cy="8.5" r="5.5"/><path d="m13.5 13.5 3 3"/></svg>
        <input type="text" id="searchInput" placeholder="Search topics…" autocomplete="off">
        <div class="search-results" id="searchResults"></div>
      </div>
    </div>

    <div class="hdr-right">
      <?php if ($USER): ?>

        <!-- Messages icon -->
        <a href="<?= u('messages/') ?>" class="icon-btn" title="Messages" style="position:relative">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <?php if ($_msgs > 0): ?>
            <span class="badge-dot"><?= $_msgs > 9 ? '9+' : $_msgs ?></span>
          <?php endif; ?>
        </a>

        <!-- Notifications -->
        <!-- Dark mode toggle -->
        <button class="icon-btn" id="themeToggle" onclick="toggleTheme()" title="Toggle dark mode" aria-label="Toggle dark mode">
          <svg class="theme-icon-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
          <svg class="theme-icon-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="display:none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="notif-wrap" id="notifWrap">
          <button class="icon-btn" id="notifBtn" title="Notifications">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($_unread > 0): ?>
              <span class="badge-dot"><?= $_unread > 9 ? '9+' : $_unread ?></span>
            <?php endif; ?>
          </button>
          <div class="notif-drop" id="notifDrop">
            <div class="notif-head"><span>Notifications</span><button id="readAllBtn" class="link-btn">Mark all read</button></div>
            <div id="notifList"><p class="notif-empty">Loading…</p></div>
          </div>
        </div>

        <!-- User menu -->
        <div class="user-wrap" id="userWrap">
          <button class="avatar-btn" id="userBtn">
            <?php if ($USER['avatar']): ?>
              <img src="<?= e($USER['avatar']) ?>" class="av-sm" alt="">
            <?php else: ?>
              <span class="av-sm av-init"><?= strtoupper($USER['username'][0]) ?></span>
            <?php endif; ?>
          </button>
          <div class="user-drop" id="userDrop">
            <div class="user-drop-top">
              <strong>@<?= e($USER['username']) ?></strong>
              <span class="role-tag role-<?= e($USER['role']) ?>"><?= e($USER['role']) ?></span>
            </div>
            <a href="<?= u('users/profile.php?u='.urlencode($USER['username'])) ?>">👤 My Profile</a>
            <a href="<?= u('messages/') ?>">📬 Messages <?php if ($_msgs>0):?><span class="badge-dot" style="position:static;margin-left:4px"><?=$_msgs?></span><?php endif;?></a>
            <a href="<?= u('users/edit.php') ?>">✏️ Edit Profile</a>
            <?php if (is_admin()): ?>
              <a href="<?= u('admin/') ?>" class="admin-lnk">🛡️ Admin Panel</a>
            <?php endif; ?>
            <div class="drop-div"></div>
            <a href="<?= u('auth/logout.php') ?>" class="logout-lnk">🚪 Log Out</a>
          </div>
        </div>

      <?php else: ?>
        <a href="<?= u('auth/login.php') ?>" class="btn-ghost">Log In</a>
        <a href="<?= u('auth/register.php') ?>" class="btn-primary">Sign Up</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="sb-overlay" id="sbOverlay"></div>
<aside class="sidebar" id="sidebar">
  <nav>
    <a href="<?= u('/') ?>" class="nav-link">🏠 Home</a>
    <?php if ($USER): ?>
      <a href="<?= u('forum/new-topic.php') ?>" class="nav-link new-link">+ New Topic</a>
      <a href="<?= u('messages/') ?>" class="nav-link">📬 Messages<?php if($_msgs>0):?> <span style="background:#ef4444;color:#fff;border-radius:10px;padding:1px 6px;font-size:10px;font-weight:700"><?=$_msgs?></span><?php endif;?></a>
    <?php endif; ?>
    <div class="nav-sep">Categories</div>
    <?php foreach ($_cats as $c): ?>
      <a href="<?= u('forum/category.php?slug='.urlencode($c['slug'])) ?>" class="nav-link">
        <span class="cat-dot" style="background:<?= e($c['color']) ?>"></span>
        <?= e($c['icon']) ?> <?= e($c['name']) ?>
      </a>
    <?php endforeach; ?>
    <div class="nav-sep">More</div>
    <a href="<?= u('forum/search.php') ?>" class="nav-link">🔍 Search</a>
  </nav>
</aside>

<main class="main"><div class="wrap">
