  </div><!-- /.wrap -->
</main>

<?php $stats = forum_stats(); ?>
<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-stats">
      <span title="Topics">💬 <strong><?= number_format($stats['topics']) ?></strong> topics</span>
      <span class="fs-div">·</span>
      <span title="Posts">📝 <strong><?= number_format($stats['posts']) ?></strong> posts</span>
      <span class="fs-div">·</span>
      <span title="Members">👥 <strong><?= number_format($stats['users']) ?></strong> members</span>
      <span class="fs-div">·</span>
      <span title="Online now">🟢 <strong><?= $stats['online'] ?></strong> online</span>
      <?php if ($stats['newest']): ?>
        <span class="fs-div">·</span>
        <span>Newest: <a href="<?= u('users/profile.php?u='.urlencode($stats['newest']['username'])) ?>">@<?= e($stats['newest']['username']) ?></a></span>
      <?php endif; ?>
    </div>
    <div class="footer-right">
      <span><?= e(cfg('site_name','Nexus Forum')) ?></span>
      <?php if ($USER && is_admin()): ?>
        <a href="<?= u('admin/') ?>">Admin</a>
      <?php endif; ?>
    </div>
  </div>
</footer>

<script>
var NX = {
  base:        '<?= BASE ?>',
  csrf:        '<?= csrf() ?>',
  maxUploadMb: <?= (int)(cfg('max_upload_mb', '5')) ?>,
  user:        <?= $USER ? json_encode(['id'=>(int)$USER['id'],'name'=>$USER['username'],'role'=>$USER['role']]) : 'null' ?>
};
</script>
<!-- Prism.js syntax tokenizer — loaded lazily only when a code block with a language exists -->
<!-- We use our own token color theme from main.css, NOT Prism's default theme CSS -->
<script>
(function(){
  if (!document.querySelector('.code-block-wrap pre.code-block code[class]')) return;
  // Load Prism core + autoloader (no theme CSS — we provide our own via main.css)
  var s = document.createElement('script');
  s.src = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js';
  s.onload = function() {
    var a = document.createElement('script');
    a.src = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js';
    a.onload = function() {
      // Autoloader path for language components
      if (Prism.plugins && Prism.plugins.autoloader) {
        Prism.plugins.autoloader.languages_path =
          'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/';
      }
      Prism.highlightAll();
    };
    document.body.appendChild(a);
  };
  document.body.appendChild(s);
})();
</script>
<script src="<?= asset('js/app.js') ?>"></script>

<script>
/* ── Dark mode ────────────────────────────────────────────── */
(function(){
  var stored = localStorage.getItem('nexus-theme');
  var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme:dark)').matches;
  var dark = stored ? stored === 'dark' : prefersDark;
  if (dark) document.documentElement.setAttribute('data-theme','dark');
  updateThemeIcons(dark);
})();

function toggleTheme(){
  var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  var newDark = !isDark;
  if (newDark) {
    document.documentElement.setAttribute('data-theme','dark');
    localStorage.setItem('nexus-theme','dark');
  } else {
    document.documentElement.removeAttribute('data-theme');
    localStorage.setItem('nexus-theme','light');
  }
  updateThemeIcons(newDark);
}

function updateThemeIcons(dark){
  var light = document.querySelector('.theme-icon-light');
  var moon  = document.querySelector('.theme-icon-dark');
  if (light) light.style.display = dark ? 'none' : '';
  if (moon)  moon.style.display  = dark ? '' : 'none';
}
</script>

</body>
</html>
