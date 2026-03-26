<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($PAGE_TITLE??'Admin') ?> — Admin — <?= e(cfg('site_name')) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="admin-body">
<div class="admin-wrap">
  <aside class="admin-sb">
    <div class="admin-sb-top">
      <a href="<?= u('/') ?>" class="admin-logo">
        <div class="logo-mark"><?= e(substr(cfg('site_name','N'),0,1)) ?></div>
        <div>
          <div class="admin-logo-name"><?= e(cfg('site_name','Nexus Forum')) ?></div>
          <div class="admin-logo-sub">Admin Panel</div>
        </div>
      </a>
    </div>
    <nav class="admin-nav">
      <?php $p = $ADMIN_PAGE ?? ''; ?>
      <a href="<?= u('admin/') ?>"               class="anav <?= $p==='dashboard' ?'on':'' ?>">📊 Dashboard</a>
      <a href="<?= u('admin/users.php') ?>"       class="anav <?= $p==='users'     ?'on':'' ?>">👥 Users</a>
      <a href="<?= u('admin/categories.php') ?>"  class="anav <?= $p==='categories'?'on':'' ?>">📂 Categories</a>
      <a href="<?= u('admin/topics.php') ?>"      class="anav <?= $p==='topics'    ?'on':'' ?>">💬 Topics</a>
      <a href="<?= u('admin/themes.php') ?>"      class="anav <?= $p==='themes'    ?'on':'' ?>">🎨 Themes</a>
      <a href="<?= u('admin/settings.php') ?>"    class="anav <?= $p==='settings'  ?'on':'' ?>">⚙️ Settings</a>
      <a href="<?= u('admin/addons.php') ?>" class="anav <?= $p==='addons'?'on':'' ?>">🧩 Addons</a>
      <div class="anav-div"></div>
      <a href="<?= u('/') ?>" class="anav">🏠 Back to Forum</a>
    </nav>
  </aside>
  <div class="admin-main">
    <header class="admin-topbar">
      <h1><?= e($PAGE_TITLE??'Admin') ?></h1>
      <span>@<?= e($USER['username']) ?></span>
    </header>
    <div class="admin-content">
