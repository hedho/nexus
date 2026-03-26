<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_admin();
$PAGE_TITLE = 'Dashboard';
$ADMIN_PAGE = 'dashboard';

$stats = [
    'users'  => (int)DB::val('SELECT COUNT(*) FROM users'),
    'topics' => (int)DB::val('SELECT COUNT(*) FROM topics'),
    'posts'  => (int)DB::val("SELECT COUNT(*) FROM posts WHERE deleted=0"),
    'cats'   => (int)DB::val('SELECT COUNT(*) FROM categories'),
    'u_today'=> (int)DB::val("SELECT COUNT(*) FROM users WHERE DATE(joined_at)=DATE('now')"),
    't_today'=> (int)DB::val("SELECT COUNT(*) FROM topics WHERE DATE(created_at)=DATE('now')"),
    'p_today'=> (int)DB::val("SELECT COUNT(*) FROM posts WHERE DATE(created_at)=DATE('now') AND deleted=0"),
];
$recent_users  = DB::rows('SELECT * FROM users ORDER BY joined_at DESC LIMIT 6');
$recent_topics = DB::rows("SELECT t.*,u.username,c.name AS cat FROM topics t JOIN users u ON u.id=t.user_id JOIN categories c ON c.id=t.category_id ORDER BY t.created_at DESC LIMIT 6");
$activity      = DB::rows("SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM posts WHERE created_at>=DATE('now','-7 days') AND deleted=0 GROUP BY DATE(created_at) ORDER BY day");

include __DIR__ . '/../views/partials/admin_layout.php';
?>

<div class="stat-grid">
  <?php foreach ([
    ['👥','Total Users',$stats['users'],'+'.  $stats['u_today'].' today','#3b82f6'],
    ['💬','Topics',     $stats['topics'],'+'. $stats['t_today'].' today','#10b981'],
    ['📝','Posts',      $stats['posts'],'+'.  $stats['p_today'].' today','#8b5cf6'],
    ['📂','Categories', $stats['cats'],'','#f59e0b'],
  ] as [$icon,$label,$num,$sub,$col]): ?>
    <div class="stat-card">
      <div class="stat-icon" style="background:<?= $col ?>22;color:<?= $col ?>"><?= $icon ?></div>
      <div>
        <div class="stat-num"><?= number_format($num) ?></div>
        <div class="stat-label"><?= $label ?></div>
        <?php if ($sub): ?><div class="stat-sub"><?= $sub ?></div><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="acard">
  <div class="acard-head"><h2>Post Activity — Last 7 Days</h2></div>
  <div class="acard-body"><canvas id="actChart" height="70"></canvas></div>
</div>

<div class="admin-two-col">
  <div class="acard">
    <div class="acard-head"><h2>Recent Users</h2><a href="<?= u('admin/users.php') ?>" class="btn-ghost btn-sm">All</a></div>
    <table class="atable">
      <thead><tr><th>User</th><th>Role</th><th>Joined</th></tr></thead>
      <tbody>
        <?php foreach ($recent_users as $u): ?>
          <tr>
            <td><a href="<?= u('admin/user.php?id='.$u['id']) ?>">@<?= e($u['username']) ?></a></td>
            <td><span class="role-tag role-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
            <td><span class="ago" data-ts="<?= e($u['joined_at']) ?>"></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="acard">
    <div class="acard-head"><h2>Recent Topics</h2><a href="<?= u('admin/topics.php') ?>" class="btn-ghost btn-sm">All</a></div>
    <table class="atable">
      <thead><tr><th>Title</th><th>By</th></tr></thead>
      <tbody>
        <?php foreach ($recent_topics as $t): ?>
          <tr>
            <td><a href="<?= u('forum/topic.php?slug='.urlencode($t['slug'])) ?>" target="_blank"><?= e(mb_substr($t['title'],0,40)) ?></a></td>
            <td>@<?= e($t['username']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
var actData = <?= json_encode($activity) ?>;
(function(){
  var s=document.createElement('script');
  s.src='https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
  s.onload=function(){
    new Chart(document.getElementById('actChart'),{
      type:'bar',
      data:{labels:actData.map(function(d){return d.day;}),datasets:[{label:'Posts',data:actData.map(function(d){return d.cnt;}),backgroundColor:'rgba(59,130,246,.6)',borderColor:'#3b82f6',borderWidth:1,borderRadius:4}]},
      options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
    });
  };
  document.head.appendChild(s);
})();
</script>
<?php include __DIR__ . '/../views/partials/admin_layout_end.php'; ?>
