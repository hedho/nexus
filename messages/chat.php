<?php
/**
 * Live Chat — conversation view with real-time polling
 */
require_once __DIR__ . '/../includes/bootstrap.php';
must_login();

$withUser = get('with');
$other    = null;

if ($withUser) {
    $other = DB::row('SELECT id,username,avatar,role,karma,last_seen FROM users WHERE username=?', [$withUser]);
    if (!$other) render_404();
    if ((int)$other['id'] === (int)$USER['id']) go('messages/chat.php');
}

$PAGE_TITLE = $other ? 'Chat with @' . $other['username'] : 'Messages';
include __DIR__ . '/../views/partials/layout.php';
?>
<div class="chat-shell">

  <!-- Sidebar: conversation list -->
  <aside class="chat-aside" id="chatAside">
    <div class="chat-aside-head">
      <h2>💬 Messages</h2>
      <a href="<?= u('messages/chat.php') ?>" class="chat-new-btn" title="New conversation">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 5v14M5 12h14"/>
        </svg>
      </a>
    </div>
    <div class="chat-aside-search">
      <input type="text" id="convSearch" class="chat-search-inp" placeholder="Search people…" autocomplete="off">
      <div id="convSearchResults" class="chat-search-results"></div>
    </div>
    <div class="chat-conv-list" id="convList">
      <div class="chat-loading">Loading…</div>
    </div>
  </aside>

  <!-- Main chat area -->
  <div class="chat-main" id="chatMain">
    <?php if ($other): ?>
      <!-- Chat header -->
      <div class="chat-head">
        <div class="chat-head-left">
          <button class="chat-back-btn" onclick="history.back()">←</button>
          <?php if ($other['avatar']): ?>
            <img src="<?= e($other['avatar']) ?>" class="av-md" alt="">
          <?php else: ?>
            <span class="av-md av-init"><?= strtoupper($other['username'][0]) ?></span>
          <?php endif; ?>
          <div>
            <a href="<?= u('users/profile.php?u=' . urlencode($other['username'])) ?>" class="chat-head-name">
              @<?= e($other['username']) ?>
            </a>
            <div class="chat-head-status" id="chatStatus">
              <span class="status-dot" id="statusDot"></span>
              <span id="statusTxt">Loading…</span>
            </div>
          </div>
        </div>
        <div class="chat-head-right">
          <a href="<?= u('users/profile.php?u=' . urlencode($other['username'])) ?>" class="btn-ghost btn-sm">View Profile</a>
        </div>
      </div>

      <!-- Messages area -->
      <div class="chat-messages" id="chatMessages">
        <div class="chat-loading-msgs">
          <div class="chat-spinner"></div>
          Loading messages…
        </div>
      </div>

      <!-- Typing + input -->
      <div class="chat-input-area">
        <div class="chat-input-wrap">
          <textarea id="chatInput" class="chat-input" placeholder="Write a message… (Enter to send, Shift+Enter for newline)"
                    rows="1" maxlength="2000"></textarea>
          <button id="chatSendBtn" class="chat-send-btn" title="Send">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
            </svg>
          </button>
        </div>
        <div class="chat-input-meta">
          <span id="chatCharCount" class="chat-char">0 / 2000</span>
          <span class="chat-hint">Enter ↵ to send · Shift+Enter for new line</span>
        </div>
      </div>

    <?php else: ?>
      <!-- No conversation selected -->
      <div class="chat-empty-state">
        <div class="chat-empty-icon">💬</div>
        <h2>Your Messages</h2>
        <p>Select a conversation or start a new one</p>
        <div class="chat-new-search">
          <input type="text" id="newChatSearch" class="fi" placeholder="Search for a user to message…" autocomplete="off">
          <div id="newChatResults" class="chat-search-results chat-search-results-lg"></div>
        </div>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
(function() {
  var OTHER_ID   = <?= $other ? (int)$other['id'] : 'null' ?>;
  var OTHER_NAME = <?= $other ? json_encode($other['username']) : 'null' ?>;
  var MY_ID      = NX.user ? NX.user.id : null;
  var lastMsgId  = 0;
  var pollTimer  = null;
  var sending    = false;

  /* ── Helpers ─────────────────────────────────────────── */
  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function timeStr(ts) {
    if (!ts) return '';
    var d = new Date(ts.replace(' ','T') + 'Z');
    var now = new Date();
    var diff = (now - d) / 1000;
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return d.toLocaleDateString();
  }

  function avatarHtml(name, av, size) {
    size = size || 'av-sm';
    if (av) return '<img src="'+esc(av)+'" class="'+size+'" alt="">';
    return '<span class="'+size+' av-init">'+esc(name[0].toUpperCase())+'</span>';
  }

  /* ── Render a single message bubble ────────────────── */
  function renderBubble(m) {
    var mine = (parseInt(m.sender_id) === MY_ID);
    var ts   = timeStr(m.created_at);
    return '<div class="chat-bubble-row '+(mine?'mine':'theirs')+'" data-id="'+m.id+'">'
      + (mine ? '' : '<div class="chat-av">'+avatarHtml(m.sender_name, m.sender_av)+'</div>')
      + '<div class="chat-bubble-wrap">'
      +   '<div class="chat-bubble">'
      +     '<div class="chat-bubble-body">'+esc(m.body).replace(/\n/g,'<br>')+'</div>'
      +     '<div class="chat-bubble-time">'+ts+'</div>'
      +   '</div>'
      +   (mine ? '<button class="chat-del-btn" onclick="deleteMsg('+m.id+',this)" title="Delete">✕</button>' : '')
      + '</div>'
      + (mine ? '<div class="chat-av">'+avatarHtml(m.sender_name, m.sender_av)+'</div>' : '')
      + '</div>';
  }

  /* ── Load conversation ─────────────────────────────── */
  function loadConversation() {
    if (!OTHER_ID) return;
    var fd = new FormData();
    fd.append('action','load'); fd.append('other_id',OTHER_ID); fd.append('csrf',NX.csrf);
    fetch(NX.base+'/api/chat.php', {method:'POST', body:fd})
      .then(function(r){return r.json();})
      .then(function(d){
        if (!d.ok) return;
        var box = document.getElementById('chatMessages');
        if (d.messages.length === 0) {
          box.innerHTML = '<div class="chat-no-msgs">No messages yet. Say hello! 👋</div>';
        } else {
          box.innerHTML = d.messages.map(renderBubble).join('');
          lastMsgId = d.messages[d.messages.length-1].id;
          scrollToBottom(true);
        }
        updateStatus(d.other);
        startPolling();
      });
  }

  /* ── Update online status ─────────────────────────── */
  function updateStatus(other) {
    if (!other) return;
    var dot = document.getElementById('statusDot');
    var txt = document.getElementById('statusTxt');
    if (!dot || !txt) return;
    var ls  = new Date((other.last_seen||'').replace(' ','T')+'Z');
    var diff = (Date.now() - ls) / 1000;
    if (diff < 300) {
      dot.className = 'status-dot online';
      txt.textContent = 'Online';
    } else {
      dot.className = 'status-dot offline';
      txt.textContent = 'Last seen ' + timeStr(other.last_seen);
    }
  }

  /* ── Scroll to bottom ─────────────────────────────── */
  function scrollToBottom(force) {
    var box = document.getElementById('chatMessages');
    if (!box) return;
    var atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
    if (force || atBottom) box.scrollTop = box.scrollHeight;
  }

  /* ── Poll for new messages ────────────────────────── */
  function poll() {
    if (!OTHER_ID) return;
    var fd = new FormData();
    fd.append('action','poll'); fd.append('other_id',OTHER_ID);
    fd.append('since_id',lastMsgId); fd.append('csrf',NX.csrf);
    fetch(NX.base+'/api/chat.php', {method:'POST', body:fd})
      .then(function(r){return r.json();})
      .then(function(d){
        if (!d.ok || !d.messages.length) return;
        var box = document.getElementById('chatMessages');
        var noMsg = box.querySelector('.chat-no-msgs');
        if (noMsg) noMsg.remove();
        d.messages.forEach(function(m){
          if (!box.querySelector('[data-id="'+m.id+'"]')) {
            box.insertAdjacentHTML('beforeend', renderBubble(m));
            lastMsgId = Math.max(lastMsgId, m.id);
          }
        });
        scrollToBottom();
      });
  }

  function startPolling() {
    clearInterval(pollTimer);
    pollTimer = setInterval(poll, 2500);
  }

  /* ── Send message ────────────────────────────────── */
  function sendMessage() {
    if (sending) return;
    var inp  = document.getElementById('chatInput');
    var body = inp.value.trim();
    if (!body || !OTHER_ID) return;
    sending = true;
    var btn = document.getElementById('chatSendBtn');
    btn.disabled = true;
    inp.disabled = true;

    var fd = new FormData();
    fd.append('action','send'); fd.append('to_id',OTHER_ID);
    fd.append('body',body); fd.append('csrf',NX.csrf);
    fetch(NX.base+'/api/chat.php', {method:'POST', body:fd})
      .then(function(r){return r.json();})
      .then(function(d){
        sending = false; btn.disabled = false; inp.disabled = false; inp.focus();
        if (d.ok && d.message) {
          var box = document.getElementById('chatMessages');
          var noMsg = box.querySelector('.chat-no-msgs');
          if (noMsg) noMsg.remove();
          box.insertAdjacentHTML('beforeend', renderBubble(d.message));
          lastMsgId = Math.max(lastMsgId, d.message.id);
          scrollToBottom(true);
          inp.value = ''; autoResize(inp);
          updateCharCount();
        } else {
          toast(d.error || 'Failed to send', 'err');
        }
      })
      .catch(function(){ sending=false; btn.disabled=false; inp.disabled=false; toast('Network error','err'); });
  }

  /* ── Delete message ──────────────────────────────── */
  window.deleteMsg = function(id, btn) {
    if (!confirm('Delete this message?')) return;
    var fd = new FormData();
    fd.append('action','delete'); fd.append('msg_id',id); fd.append('csrf',NX.csrf);
    fetch(NX.base+'/api/chat.php', {method:'POST', body:fd})
      .then(function(r){return r.json();})
      .then(function(d){
        if (d.ok) btn.closest('.chat-bubble-row').remove();
        else toast(d.error,'err');
      });
  };

  /* ── Input auto-resize ───────────────────────────── */
  function autoResize(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 160) + 'px';
  }
  function updateCharCount() {
    var inp = document.getElementById('chatInput');
    var cnt = document.getElementById('chatCharCount');
    if (inp && cnt) {
      var n = inp.value.length;
      cnt.textContent = n + ' / 2000';
      cnt.style.color = n > 1800 ? '#ef4444' : '';
    }
  }

  /* ── Load conversation list ──────────────────────── */
  function loadConvList() {
    var fd = new FormData();
    fd.append('action','conversations'); fd.append('csrf',NX.csrf);
    fetch(NX.base+'/api/chat.php', {method:'POST', body:fd})
      .then(function(r){return r.json();})
      .then(function(d){
        var list = document.getElementById('convList');
        if (!list) return;
        if (!d.ok || !d.conversations.length) {
          list.innerHTML = '<div class="chat-empty-conv">No conversations yet</div>';
          return;
        }
        list.innerHTML = d.conversations.map(function(c){
          var isOther = parseInt(c.sender_id) === MY_ID ? false : true;
          var name    = c.other_name;
          var av      = c.other_av;
          var active  = OTHER_NAME && OTHER_NAME === name ? ' active' : '';
          var unread  = parseInt(c.unread_count) > 0;
          return '<a href="'+NX.base+'/messages/chat.php?with='+encodeURIComponent(name)+'" class="conv-item'+active+'">'
            + avatarHtml(name, av)
            + '<div class="conv-body">'
            +   '<div class="conv-name">@'+esc(name)+(unread?'<span class="conv-unread">'+c.unread_count+'</span>':'')+'</div>'
            +   '<div class="conv-preview">'+(parseInt(c.sender_id)===MY_ID?'You: ':'')+esc(c.body.substring(0,50))+(c.body.length>50?'…':'')+'</div>'
            + '</div>'
            + '<div class="conv-time">'+timeStr(c.created_at)+'</div>'
            + '</a>';
        }).join('');
      });
  }

  /* ── Conversation search (sidebar) ──────────────── */
  function setupConvSearch() {
    var inp = document.getElementById('convSearch');
    if (!inp) return;
    var res = document.getElementById('convSearchResults');
    var timer;
    inp.addEventListener('input', function(){
      clearTimeout(timer);
      var q = this.value.trim();
      if (q.length < 1) { res.style.display='none'; return; }
      timer = setTimeout(function(){
        fetch(NX.base+'/api/search_users.php?q='+encodeURIComponent(q))
          .then(function(r){return r.json();})
          .then(function(rows){
            if (!rows.length) { res.style.display='none'; return; }
            res.innerHTML = rows.map(function(u){
              return '<a class="chat-sug-item" href="'+NX.base+'/messages/chat.php?with='+encodeURIComponent(u.username)+'">'
                + avatarHtml(u.username, u.avatar)
                + '<span>@'+esc(u.username)+'</span></a>';
            }).join('');
            res.style.display='block';
          });
      }, 200);
    });
    document.addEventListener('click', function(e){
      if (!inp.contains(e.target)) res.style.display='none';
    });
  }

  /* ── New chat search (empty state) ──────────────── */
  function setupNewChatSearch() {
    var inp = document.getElementById('newChatSearch');
    if (!inp) return;
    var res = document.getElementById('newChatResults');
    var timer;
    inp.addEventListener('input', function(){
      clearTimeout(timer);
      var q = this.value.trim();
      if (q.length < 1) { res.style.display='none'; return; }
      timer = setTimeout(function(){
        fetch(NX.base+'/api/search_users.php?q='+encodeURIComponent(q))
          .then(function(r){return r.json();})
          .then(function(rows){
            if (!rows.length) { res.style.display='none'; return; }
            res.innerHTML = rows.map(function(u){
              return '<a class="chat-sug-item" href="'+NX.base+'/messages/chat.php?with='+encodeURIComponent(u.username)+'">'
                + avatarHtml(u.username, u.avatar, 'av-md')
                + '<div><div style="font-weight:600">@'+esc(u.username)+'</div></div></a>';
            }).join('');
            res.style.display='block';
          });
      }, 200);
    });
  }

  /* ── Wire up input ───────────────────────────────── */
  var inp = document.getElementById('chatInput');
  if (inp) {
    inp.addEventListener('keydown', function(e){
      if (e.key==='Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    inp.addEventListener('input', function(){ autoResize(this); updateCharCount(); });
    document.getElementById('chatSendBtn').addEventListener('click', sendMessage);
  }

  /* ── Init ─────────────────────────────────────────── */
  loadConvList();
  setupConvSearch();
  setupNewChatSearch();
  if (OTHER_ID) loadConversation();

  // Refresh conv list every 10s
  setInterval(loadConvList, 10000);

})();
</script>
<?php include __DIR__ . '/../views/partials/layout_end.php'; ?>
