<?php
require_once __DIR__ . '/../includes/bootstrap.php';
must_login();

$allCats = DB::rows('SELECT * FROM categories ORDER BY position, id');
$cats    = array_filter($allCats, fn($c) => can_post_topic($c));
$selCat  = (int)get('cat');
$errs    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $errs[] = 'Invalid request.'; }
    else {
        $title   = sanitise(post('title'));
        $content = sanitise(post('content'));
        $catId   = (int)post('cat_id');
        $tagStr  = sanitise(post('tags'));

        if (!$title)   $errs[] = 'Title is required.';
        if (!$content) $errs[] = 'Post content is required.';
        if (!$catId)   $errs[] = 'Please select a category.';
        if (strlen($title) > 200) $errs[] = 'Title too long (max 200 chars).';
        if (strlen($content) > 50000) $errs[] = 'Content too long.';

        if (!$errs && !DB::row('SELECT id FROM categories WHERE id=?', [$catId])) $errs[] = 'Invalid category.';
        if (!$errs) {
            $postCat = DB::row('SELECT * FROM categories WHERE id=?', [$catId]);
            if (!$postCat || !can_post_topic($postCat)) $errs[] = 'You do not have permission to post in this category.';
        }

        if (!$errs && cfg('topic_captcha_enabled', '0') === '1') {
            if (!post_captcha_verify(post('topic_captcha'))) $errs[] = 'Incorrect security answer.';
        }

        if (!$errs) {
            $rl = rate_check((int)$USER['id'], 'topic');
            if (!$rl['ok']) {
                $wait  = (int)$rl['wait'];
                $errs[] = "Slow down! Please wait {$wait} second" . ($wait !== 1 ? 's' : '') . '.';
            }
        }

        if (!$errs) {
            $slug = unique_slug($title, 'topics');
            $tid  = DB::insert('INSERT INTO topics (title,slug,category_id,user_id) VALUES (?,?,?,?)',
                [$title, $slug, $catId, $USER['id']]);
            $pid  = DB::insert('INSERT INTO posts (topic_id,user_id,content,post_num) VALUES (?,?,?,1)',
                [$tid, $USER['id'], $content]);

            if ($tagStr) {
                $names = array_slice(array_filter(array_map('trim', explode(',', mb_strtolower($tagStr)))), 0, 5);
                foreach ($names as $n) {
                    if (!$n) continue;
                    $tg   = DB::row('SELECT id FROM tags WHERE name=?', [$n]);
                    $tgId = $tg ? $tg['id'] : DB::insert('INSERT INTO tags (name) VALUES (?)', [$n]);
                    DB::insertIgnore('topic_tags', ['topic_id', 'tag_id'], [$tid, $tgId]);
                    DB::run('UPDATE tags SET topic_count=topic_count+1 WHERE id=?', [$tgId]);
                }
            }
            DB::run('UPDATE users SET topic_count=topic_count+1,post_count=post_count+1 WHERE id=?', [$USER['id']]);
            DB::run('UPDATE categories SET topic_count=topic_count+1,post_count=post_count+1 WHERE id=?', [$catId]);
            process_mentions($content, $pid, $slug, (int)$USER['id'], $USER['username']);
            rate_record((int)$USER['id'], 'topic');
            addon_hook('after_topic_created', ['topic_id'=>$tid,'title'=>$title,'slug'=>$slug,'category_id'=>$catId,'user_id'=>(int)$USER['id']]);
            go('forum/topic.php?slug=' . urlencode($slug));
        }
    }
}

$PAGE_TITLE = 'New Topic';
include __DIR__ . '/../views/partials/layout.php';
?>
<nav class="bc"><a href="<?=u('/')?>">Home</a> › <span>New Topic</span></nav>
<div class="form-card">
  <h1>Create a New Topic</h1>
  <?php if($errs):?><div class="alert err"><?=implode('<br>',array_map('e',$errs))?></div><?php endif;?>
  <form method="POST" id="ntForm">
    <?=csrf_input()?>
    <div class="fg">
      <label>Category <span class="req">*</span></label>
      <select name="cat_id" class="fi" required>
        <option value="">— Select a category —</option>
        <?php foreach($cats as $c):?>
          <option value="<?=$c['id']?>" <?=($selCat==$c['id']||((int)($_POST['cat_id']??0))==$c['id'])?'selected':''?>>
            <?=e($c['icon'])?> <?=e($c['name'])?>
          </option>
        <?php endforeach;?>
      </select>
    </div>
    <div class="fg">
      <label>Title <span class="req">*</span></label>
      <input type="text" name="title" class="fi" required maxlength="200"
             value="<?=e($_POST['title']??'')?>" placeholder="What is this about?">
    </div>
    <div class="fg">
      <label>Tags <small>(optional, comma-separated)</small></label>
      <input type="text" name="tags" id="tagsIn" class="fi" value="<?=e($_POST['tags']??'')?>" placeholder="help, tutorial">
      <div id="tagPreview" class="tag-preview"></div>
    </div>
    <div class="fg">
      <label>Content <span class="req">*</span></label>
      <div class="ed-toolbar">
        <button type="button" onclick="fmt('bold','replyTa')"><b>B</b></button>
        <button type="button" onclick="fmt('italic','replyTa')"><i>I</i></button>
        <button type="button" onclick="fmt('link','replyTa')">🔗</button>
        <button type="button" onclick="fmt('quote','replyTa')" title="Quote"><svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13" style="vertical-align:middle"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg></button>
        <button type="button" onclick="fmt('codeblock','replyTa')">📄</button>
        <button type="button" onclick="fmt('heading','replyTa')">H</button>
        <button type="button" onclick="fmt('ul','replyTa')">≡</button>
        <div class="ed-sep"></div>
        <button type="button" onclick="pickImg('ntImg','replyTa')">🖼</button>
        <input type="file" id="ntImg" accept="image/*" style="display:none" onchange="uploadImg(this,'replyTa')">
        <div class="ed-sep"></div>
        <button type="button" id="prevBtn" onclick="togglePreview()">👁 Preview</button>
      </div>
      <div class="ed-panes">
        <textarea id="replyTa" name="content" class="reply-ta" rows="14" required
                  placeholder="Write your post… Markdown supported.&#10;Tip: use @username to mention someone!"><?=e($_POST['content']??'')?></textarea>
        <div id="replyPreview" class="reply-preview hidden"></div>
      </div>
    </div>
    <?php if (cfg('topic_captcha_enabled','0') === '1'): ?>
      <?php $tc = post_captcha_generate(); ?>
      <div class="fg captcha-box">
        <label class="captcha-label">🔒 Security — What is <strong class="captcha-q"><?= e($tc['q']) ?></strong></label>
        <input type="number" name="topic_captcha" class="fi captcha-input" required placeholder="Answer" autocomplete="off" min="-99" max="99">
        <span class="hint">Solve this math problem to post</span>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <a href="<?=u('/')?>" class="btn-ghost">Cancel</a>
      <button type="submit" class="btn-primary btn-lg">Post Topic</button>
    </div>
  </form>
</div>
<script>
document.getElementById('tagsIn').addEventListener('input',function(){
  var tags=this.value.split(',').map(function(t){return t.trim();}).filter(Boolean);
  document.getElementById('tagPreview').innerHTML=tags.map(function(t){return '<span class="tag">'+escH(t)+'</span>';}).join('');
});
function escH(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
document.getElementById('replyTa').addEventListener('paste',function(e){
  var items=(e.clipboardData||e.originalEvent.clipboardData).items;
  for(var i=0;i<items.length;i++){if(items[i].type.indexOf('image')!==-1){e.preventDefault();uploadFileToEditor(items[i].getAsFile(),'replyTa');}}
});
</script>
<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
