    </div><!-- /.admin-content -->
  </div><!-- /.admin-main -->
</div><!-- /.admin-wrap -->
<script>
var NX={base:'<?= BASE ?>',csrf:'<?= csrf() ?>',user:<?= json_encode(['id'=>(int)$USER['id'],'name'=>$USER['username'],'role'=>$USER['role']]) ?>};
</script>
<script src="<?= asset('js/app.js') ?>"></script>
</body></html>
