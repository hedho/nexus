/* ================================================================
   Nexus Forum — app.js  v2
   ================================================================ */
'use strict';

/* ── Escape HTML ──────────────────────────────────────────── */
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

/* ── Media embed detection ────────────────────────────────── */
/* Called on bare URLs that end up in <p> tags after md() rendering */
function tryEmbed(url) {
  var m;
  // YouTube full
  m = url.match(/youtube\.com\/watch\?.*v=([a-zA-Z0-9_\-]{11})/);
  if (m) return ytEmbed(m[1]);
  // YouTube short
  m = url.match(/youtu\.be\/([a-zA-Z0-9_\-]{11})/);
  if (m) return ytEmbed(m[1]);
  // YouTube Shorts
  m = url.match(/youtube\.com\/shorts\/([a-zA-Z0-9_\-]{11})/);
  if (m) return ytEmbed(m[1], true);
  // Vimeo
  m = url.match(/vimeo\.com\/(\d{5,12})/);
  if (m) return embedIframe('https://player.vimeo.com/video/'+m[1]+'?dnt=1', 'Vimeo');
  // Spotify
  m = url.match(/open\.spotify\.com\/(track|album|playlist|episode|artist)\/([a-zA-Z0-9]+)/);
  if (m) {
    var h = (m[1]==='track'||m[1]==='episode') ? '152' : '352';
    return '<div class="embed-spotify"><iframe src="https://open.spotify.com/embed/'+m[1]+'/'+m[2]+'" width="100%" height="'+h+'" frameborder="0" allow="autoplay;clipboard-write;encrypted-media;fullscreen" loading="lazy"></iframe></div>';
  }
  // SoundCloud
  if (/soundcloud\.com\/[a-zA-Z0-9\-_]+\/[a-zA-Z0-9\-_]+/.test(url)) {
    return '<div class="embed-sc"><iframe width="100%" height="166" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url='+encodeURIComponent(url)+'&color=%233b82f6&auto_play=false"></iframe></div>';
  }
  // Twitch
  m = url.match(/twitch\.tv\/videos\/(\d+)/);
  if (m) return embedIframe('https://player.twitch.tv/?video=v'+m[1]+'&parent='+location.hostname+'&autoplay=false', 'Twitch VOD');
  m = url.match(/twitch\.tv\/([a-zA-Z0-9_]{4,25})(?:\/|$|\?)/);
  if (m) return embedIframe('https://player.twitch.tv/?channel='+m[1]+'&parent='+location.hostname+'&autoplay=false', 'Twitch');
  // Twitter/X
  m = url.match(/(?:twitter|x)\.com\/[a-zA-Z0-9_]+\/status\/(\d+)/);
  if (m) return '<div class="embed-tweet" data-tweet-id="'+m[1]+'"><a href="'+esc(url)+'" target="_blank" rel="noopener" class="tweet-fallback">🐦 View on Twitter/X →</a></div>';
  // Streamable
  m = url.match(/streamable\.com\/([a-zA-Z0-9]+)/);
  if (m) return embedIframe('https://streamable.com/e/'+m[1], 'Streamable');
  // Dailymotion
  m = url.match(/dailymotion\.com\/video\/([a-zA-Z0-9]+)/);
  if (m) return embedIframe('https://www.dailymotion.com/embed/video/'+m[1], 'Dailymotion');
  // Loom
  m = url.match(/loom\.com\/share\/([a-zA-Z0-9]+)/);
  if (m) return embedIframe('https://www.loom.com/embed/'+m[1], 'Loom');
  // CodePen
  m = url.match(/codepen\.io\/([a-zA-Z0-9\-_]+)\/pen\/([a-zA-Z0-9]+)/);
  if (m) return embedIframeTall('https://codepen.io/'+m[1]+'/embed/'+m[2]+'?default-tab=result', 'CodePen', 420);
  // JSFiddle
  m = url.match(/jsfiddle\.net\/([a-zA-Z0-9\/]+)/);
  if (m) return embedIframeTall('https://jsfiddle.net/'+m[1].replace(/\/$/,'')+'/embedded/result', 'JSFiddle', 380);
  // TED
  m = url.match(/ted\.com\/talks\/([a-zA-Z0-9_]+)/);
  if (m) return embedIframe('https://embed.ted.com/talks/'+m[1], 'TED Talk');
  return null;
}

function ytEmbed(id, isShort) {
  var pad = isShort ? 'padding-bottom:177.78%;max-width:360px' : 'padding-bottom:56.25%';
  return '<div class="embed-wrap" style="'+pad+'"><iframe class="embed-yt" src="https://www.youtube-nocookie.com/embed/'+esc(id)+'?rel=0&modestbranding=1" allowfullscreen loading="lazy" title="YouTube"></iframe></div>';
}
function embedIframe(src, label) {
  return '<div class="embed-wrap"><iframe class="embed-yt" src="'+esc(src)+'" allowfullscreen loading="lazy" title="'+esc(label||'')+'"></iframe></div>';
}
function embedIframeTall(src, label, h) {
  return '<div class="embed-wrap" style="padding-bottom:0;height:'+(h||400)+'px"><iframe class="embed-yt" src="'+esc(src)+'" allowfullscreen loading="lazy" title="'+esc(label||'')+'"></iframe></div>';
}
function processEmbeds(html) {
  return html.replace(/<p>\s*(https?:\/\/[^\s<>"']+)\s*<\/p>/gi, function(match, url) {
    var em = tryEmbed(url);
    return em !== null ? em : match;
  });
}


/* ── Markdown renderer ────────────────────────────────────── */
function md(raw) {
  if (!raw) return '';
  var s = raw;
  var fences = [], quotes = [], inlines = [];

  // ── PASS 1: Extract code fences (state machine) ─────────────
  var fenceLines = s.split('\n');
  var fenceOut = [], inFence = false, fLang = '', fType = '', fBuf = [];

  var buildFence = function(lang, lines) {
    var code = lines.join('\n');
    var ll   = lang ? lang.toLowerCase() : '';
    var copyBtn = '<button class="cb-copy" onclick="cbCopy(this)" title="Copy" aria-label="Copy code">'
      + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
      + 'stroke-linecap="round" stroke-linejoin="round" width="13" height="13">'
      + '<rect x="9" y="9" width="13" height="13" rx="2"/>'
      + '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'
      + '</svg><span>Copy</span></button>';
    var hdr, blk;
    if (ll) {
      hdr = '<div class="cb-header"><span class="cb-lang">' + esc(ll) + '</span>' + copyBtn + '</div>';
      blk = '<pre class="code-block" data-lang="' + esc(ll) + '">'
          + '<code class="language-' + esc(ll) + '">' + esc(code) + '</code></pre>';
    } else {
      hdr = '<div class="cb-header cb-header-nolang">' + copyBtn + '</div>';
      blk = '<pre class="code-block"><code>' + esc(code) + '</code></pre>';
    }
    var i = fences.length;
    fences.push('<div class="code-block-wrap">' + hdr + blk + '</div>');
    return '\x02FENCE' + i + 'FNCE\x03';
  };

  for (var fi = 0; fi < fenceLines.length; fi++) {
    var fl = fenceLines[fi];
    if (!inFence) {
      var tm = fl.match(/^```([ \t]*\w*)[ \t]*$/);
      if (tm) { inFence = true; fType = 'triple'; fLang = tm[1].trim(); fBuf = []; continue; }
      if (/^`[ \t]*$/.test(fl)) { inFence = true; fType = 'single'; fLang = ''; fBuf = []; continue; }
      fenceOut.push(fl);
    } else {
      var isClose = (fType === 'triple' && /^```[ \t]*$/.test(fl))
                 || (fType === 'single' && /^`[ \t]*$/.test(fl));
      if (isClose) { fenceOut.push(buildFence(fLang, fBuf)); inFence = false; fBuf = []; }
      else          { fBuf.push(fl); }
    }
  }
  if (inFence && fBuf.length) fenceOut.push(buildFence(fLang, fBuf));
  s = fenceOut.join('\n');

  // ── PASS 2: Extract blockquotes (state machine) ──────────────
  var bqLines = s.split('\n'), bqOut = [], bqBuf = [];
  var flushBq = function() {
    if (!bqBuf.length) return;
    var inner = bqBuf.join('\n');
    inner = inner.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
    inner = inner.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    inner = inner.replace(/\*([^\*\n]+)\*/g, '<em>$1</em>');
    inner = inner.replace(/~~(.+?)~~/g, '<del>$1</del>');
    var bqParts = inner.split('\n').filter(function(l){return l.trim();});
    var content = bqParts.length > 1
      ? bqParts.map(function(l){return '<p>'+l.trim()+'</p>';}).join('')
      : inner.trim();
    var i = quotes.length;
    quotes.push('<blockquote class="post-quote">' + content + '</blockquote>');
    bqOut.push('\x02BQUOT' + i + 'BQUT\x03');
    bqBuf = [];
  };
  for (var bi = 0; bi < bqLines.length; bi++) {
    var bm = bqLines[bi].match(/^(?:&gt;|>) ?(.*)/);
    if (bm) { bqBuf.push(bm[1]); }
    else    { flushBq(); bqOut.push(bqLines[bi]); }
  }
  flushBq();
  s = bqOut.join('\n');

  // ── PASS 3: Inline code ──────────────────────────────────────
  s = s.replace(/`([^`\n]+)`/g, function(_, code) {
    var i = inlines.length;
    inlines.push('<code class="inline-code">' + esc(code) + '</code>');
    return '\x02INLIN' + i + 'INLN\x03';
  });

  // ── PASS 4: Block markdown ───────────────────────────────────
  s = s.replace(/^#{6} (.+)$/gm, '<h6>$1</h6>');
  s = s.replace(/^#{5} (.+)$/gm, '<h5>$1</h5>');
  s = s.replace(/^#{4} (.+)$/gm, '<h4>$1</h4>');
  s = s.replace(/^#{3} (.+)$/gm, '<h3>$1</h3>');
  s = s.replace(/^#{2} (.+)$/gm, '<h2>$1</h2>');
  s = s.replace(/^# (.+)$/gm,   '<h1>$1</h1>');
  s = s.replace(/^(-{3,}|\*{3,}|_{3,})$/gm, '<hr>');

  s = s.replace(/^[ \t]*[*\-+] (.+)$/gm, '<li>$1</li>');
  s = s.replace(/((?:<li>.*<\/li>\n?)+)/g, '<ul>$1</ul>');
  s = s.replace(/<\/ul>\s*<ul>/g, '');

  s = s.replace(/^[ \t]*\d+\. (.+)$/gm, '<oli>$1</oli>');
  s = s.replace(/((?:<oli>.*<\/oli>\n?)+)/g, '<ol>$1</ol>');
  s = s.replace(/<\/ol>\s*<ol>/g, '');
  s = s.replace(/<oli>/g, '<li>').replace(/<\/oli>/g, '</li>');

  // ── PASS 5: Inline markdown ──────────────────────────────────
  s = s.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
  s = s.replace(/\*\*(.+?)\*\*/g,     '<strong>$1</strong>');
  s = s.replace(/\*([^\*\n]+)\*/g,    '<em>$1</em>');
  s = s.replace(/___(.+?)___/g,       '<strong><em>$1</em></strong>');
  s = s.replace(/__(.+?)__/g,         '<strong>$1</strong>');
  s = s.replace(/_([^_\n]+)_/g,       '<em>$1</em>');
  s = s.replace(/~~(.+?)~~/g,         '<del>$1</del>');

  s = s.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, function(_,alt,src){
    if (!/^https?:\/\/|^\//.test(src)) return esc(_);
    return '<img src="'+esc(src)+'" alt="'+esc(alt)+'" loading="lazy" onclick="lightbox(this)">';
  });
  s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(_,txt,url){
    if (!/^https?:\/\/|^\//.test(url)) return esc(txt);
    return '<a href="'+esc(url)+'" target="_blank" rel="noopener noreferrer nofollow">'+esc(txt)+'</a>';
  });

  // ── PASS 6: Embeds + auto-link (ALL bare URLs) ───────────────
  s = s.replace(/(^|[ \t]|<p>)(https?:\/\/[^\s<>"']+)/gm, function(_, pre, url) {
    if (/^<(div|iframe|a|pre)/.test(url)) return pre + url;
    var em = tryEmbed(url);
    if (em) return pre + em;
    return pre + '<a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer nofollow">' + esc(url) + '</a>';
  });

  // ── PASS 7: Paragraph wrapping ───────────────────────────────
  var pLines = s.split('\n'), pOut = [], para = '';
  var blockRe = /^(<(h[1-6]|ul|ol|blockquote|pre|table|hr|img|div|figure|p)|\x02FENCE|\x02BQUOT)/;
  function flush(){ if(para.trim()){ pOut.push('<p>'+para.trim()+'</p>'); para=''; } }
  for (var pi = 0; pi < pLines.length; pi++) {
    var pl = pLines[pi];
    if (!pl.trim())             { flush(); }
    else if (blockRe.test(pl.trim())) { flush(); pOut.push(pl); }
    else                        { para += (para ? ' ' : '') + pl; }
  }
  flush();
  s = pOut.join('\n');

  // ── PASS 8: Restore placeholders ─────────────────────────────
  fences.forEach(function(b,i){  s = s.replace('\x02FENCE' + i + 'FNCE\x03', b); });
  quotes.forEach(function(b,i){  s = s.replace('\x02BQUOT' + i + 'BQUT\x03', b); });
  inlines.forEach(function(b,i){ s = s.replace('\x02INLIN' + i + 'INLN\x03', b); });

  return s;
}


/* ── Render all .md elements ──────────────────────────────── */
function renderAllMd() {
  document.querySelectorAll('.md[data-raw]').forEach(function(el) {
    el.innerHTML = md(atob(el.getAttribute('data-raw')));
    el.removeAttribute('data-raw');
  });
  loadTwitterWidgets();
}

/* ── Lightbox ─────────────────────────────────────────────── */
function lightbox(img) {
  var ov = document.createElement('div');
  ov.className = 'lightbox';
  var im = document.createElement('img');
  im.src = img.src; im.alt = img.alt;
  ov.appendChild(im);
  ov.onclick = function(){ov.remove();};
  document.body.appendChild(ov);
}

/* ── Time-ago ─────────────────────────────────────────────── */
function timeAgo(dt) {
  var diff = Math.floor((Date.now() - new Date(dt)) / 1000);
  if (diff < 60)      return 'just now';
  if (diff < 3600)    return Math.floor(diff/60)    + 'm ago';
  if (diff < 86400)   return Math.floor(diff/3600)  + 'h ago';
  if (diff < 604800)  return Math.floor(diff/86400) + 'd ago';
  return new Date(dt).toLocaleDateString();
}
function renderTimeAgo() {
  document.querySelectorAll('.ago[data-ts]').forEach(function(el) {
    el.textContent = timeAgo(el.dataset.ts);
    el.title = new Date(el.dataset.ts).toLocaleString();
  });
}

/* ── Sidebar toggle ───────────────────────────────────────── */
var burger  = document.getElementById('burgerBtn');
var sidebar = document.getElementById('sidebar');
var sbOv    = document.getElementById('sbOverlay');
if (burger && sidebar) {
  burger.addEventListener('click', function(){
    var open = sidebar.classList.toggle('open');
    burger.classList.toggle('open', open);
    if (sbOv) sbOv.classList.toggle('show', open);
    document.body.style.overflow = open ? 'hidden' : '';
  });
  if (sbOv) sbOv.addEventListener('click', function(){
    sidebar.classList.remove('open');
    burger.classList.remove('open');
    sbOv.classList.remove('show');
    document.body.style.overflow = '';
  });
}

/* ── Dropdowns ────────────────────────────────────────────── */
function setupDrop(btnId, dropId, onOpen) {
  var btn  = document.getElementById(btnId);
  var drop = document.getElementById(dropId);
  if (!btn || !drop) return;
  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    var opening = !drop.classList.contains('show');
    document.querySelectorAll('.notif-drop.show,.user-drop.show').forEach(function(d){d.classList.remove('show');});
    if (opening) { drop.classList.add('show'); if (onOpen) onOpen(); }
  });
}
document.addEventListener('click', function(){
  document.querySelectorAll('.notif-drop.show,.user-drop.show').forEach(function(d){d.classList.remove('show');});
});
setupDrop('notifBtn', 'notifDrop', loadNotifs);
setupDrop('userBtn',  'userDrop');

/* ── Notifications ────────────────────────────────────────── */
function loadNotifs() {
  if (!window.NX || !NX.user) return;
  var list = document.getElementById('notifList');
  if (!list) return;
  fetch(NX.base + '/api/notifications.php')
    .then(function(r){ return r.json(); })
    .then(function(rows) {
      if (!rows.length) { list.innerHTML = '<p class="notif-empty">Nothing new 🎉</p>'; return; }
      list.innerHTML = rows.map(function(n) {
        var d = n.payload || {};
        var text = (n.type === 'reply' && d.title)
          ? '<strong>@'+esc(d.from)+'</strong> replied in <em>'+esc(d.title)+'</em>'
          : esc(n.type);
        var href = (n.type === 'reply' && d.slug) ? NX.base+'/forum/topic.php?slug='+encodeURIComponent(d.slug) : '#';
        return '<a href="'+href+'" class="notif-item'+(n.read?'':' unread')+'">'
          +'<div class="notif-item-text">'+text+'</div>'
          +'<div class="notif-item-time">'+timeAgo(n.created_at)+'</div>'
          +'</a>';
      }).join('');
    })
    .catch(function(){ if (list) list.innerHTML = '<p class="notif-empty">Failed to load</p>'; });
}
var readAllBtn = document.getElementById('readAllBtn');
if (readAllBtn) {
  readAllBtn.addEventListener('click', function(){
    fetch(NX.base + '/api/notifications.php?action=read').then(function(){
      var dot = document.querySelector('.badge-dot');
      if (dot) dot.remove();
      loadNotifs();
    });
  });
}

/* ── Header search ────────────────────────────────────────── */
var searchInput   = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimer   = null;
if (searchInput && searchResults) {
  searchInput.addEventListener('input', function(){
    clearTimeout(searchTimer);
    var q = searchInput.value.trim();
    if (q.length < 2) { searchResults.classList.remove('show'); return; }
    searchTimer = setTimeout(function(){
      fetch(NX.base + '/api/search.php?q=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(d){
          var topics = Array.isArray(d) ? d : (d.topics || []);
          var posts  = Array.isArray(d) ? [] : (d.posts  || []);
          if (!topics.length && !posts.length) {
            searchResults.classList.remove('show'); return;
          }
          var html = '';
          if (topics.length) {
            html += '<div class="sr-section-lbl">Topics</div>';
            topics.forEach(function(r){
              html += '<a class="sr-item" href="'+NX.base+'/forum/topic.php?slug='+encodeURIComponent(r.slug)+'">'
                + '<span class="sr-dot" style="background:'+(r.cat_color||'#3b82f6')+'"></span>'
                + '<span class="sr-text"><strong>'+esc(r.title)+'</strong>'
                + '<small>'+esc(r.cat)+'</small></span></a>';
            });
          }
          if (posts.length) {
            html += '<div class="sr-section-lbl">Matching Posts</div>';
            posts.forEach(function(r){
              var preview = (r.content||'').substring(0,65).replace(/\n/g,' ');
              var url = NX.base+'/forum/topic.php?slug='+encodeURIComponent(r.topic_slug)+'&goto='+r.post_id+'#post-'+r.post_id;
              html += '<a class="sr-item sr-post" href="'+url+'">'
                + '<span class="sr-text">'
                + '<strong>'+esc(r.topic_title)+'</strong>'
                + '<small>Post #'+r.post_num+' by @'+esc(r.username)+': '+esc(preview)+'…</small>'
                + '</span>'
                + '<span class="sr-goto-badge">→</span></a>';
            });
          }
          html += '<a class="sr-item sr-all" href="'+NX.base+'/forum/search.php?q='+encodeURIComponent(q)+'&type=posts">'
                + '🔍 See all results for &ldquo;'+esc(q)+'&rdquo;</a>';
          searchResults.innerHTML = html;
          searchResults.classList.add('show');
        });
    }, 280);
  });
  searchInput.addEventListener('keydown', function(e){
    if (e.key === 'Enter') window.location = NX.base + '/forum/search.php?q=' + encodeURIComponent(searchInput.value);
    if (e.key === 'Escape') searchResults.classList.remove('show');
  });
  document.addEventListener('click', function(e){
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) searchResults.classList.remove('show');
  });
}

/* ── Editor commands ──────────────────────────────────────── */
function fmt(cmd, taId) {
  var ta = document.getElementById(taId || 'replyTa');
  if (!ta) return;
  var s = ta.selectionStart, e = ta.selectionEnd;
  var sel = ta.value.slice(s, e);
  var pre = ta.value.slice(0, s);
  var post = ta.value.slice(e);

  // ── Link ──────────────────────────────────────────────────────
  if (cmd === 'link') {
    var url = prompt('Enter URL:');
    if (!url) return;
    var txt = sel || 'link text';
    ta.value = pre + '[' + txt + '](' + url + ')' + post;
    ta.selectionStart = s + txt.length + url.length + 4;
    ta.selectionEnd   = ta.selectionStart;
    ta.focus(); ta.dispatchEvent(new Event('input')); return;
  }

  // ── Quote — prefix EVERY selected line with "> " ──────────────
  // Mirrors Discourse / GitHub behaviour:
  //   • each line of the selection gets its own "> " prefix
  //   • trailing newline in the selection is stripped so no ghost blank line
  //   • if nothing is selected, inserts a placeholder quote line
  //   • a blank separator line is added before/after so the block renders correctly
  if (cmd === 'quote') {
    var text  = sel ? sel.replace(/\n+$/, '') : '';   // strip trailing newlines
    var lines = text ? text.split('\n') : ['quoted text'];
    var quoted = lines.map(function(l) { return '> ' + l; }).join('\n');

    // Ensure there's a blank line before the quote block (so renderer sees it as a block)
    var needPre = pre.length > 0 && !/\n\n$/.test(pre) && !pre.endsWith('\n');
    var before  = needPre ? '\n\n' : (pre.length > 0 && !pre.endsWith('\n') ? '\n' : '');

    // Ensure there's a blank line after so the next paragraph starts clean
    var needPost = post.length > 0 && !post.startsWith('\n\n') && !post.startsWith('\n');
    var after    = needPost ? '\n\n' : '\n';

    ta.value = pre + before + quoted + after + post;

    // Select the quoted lines so the user can see exactly what was quoted
    var qStart = s + before.length;
    var qEnd   = qStart + quoted.length;
    ta.selectionStart = qStart;
    ta.selectionEnd   = qEnd;
    ta.focus();
    ta.dispatchEvent(new Event('input'));
    return;
  }

  // ── All other commands ─────────────────────────────────────────
  var map = {
    bold:      ['**', '**', 'bold text'],
    italic:    ['*',  '*',  'italic text'],
    strike:    ['~~', '~~', 'strikethrough'],
    code:      ['```\n', '\n```', 'code here'],
    codeblock: ['```\n', '\n```', 'code here'],
    heading:   ['## ', '', 'Heading'],
    ul:        ['- ',  '', 'list item'],
  };
  var c = map[cmd];
  if (!c) return;
  var text    = sel || c[2];
  var newText = c[0] + text + c[1];
  ta.value          = pre + newText + post;
  ta.selectionStart = s + c[0].length;
  ta.selectionEnd   = s + c[0].length + text.length;
  ta.focus(); ta.dispatchEvent(new Event('input'));
}

/* ── Preview toggle ───────────────────────────────────────── */
function togglePreview() {
  var ta   = document.getElementById('replyTa');
  var prev = document.getElementById('replyPreview');
  var btn  = document.getElementById('prevBtn');
  if (!ta || !prev) return;
  if (prev.classList.contains('hidden')) {
    prev.innerHTML = md(ta.value);
    loadTwitterWidgets();
    prev.classList.remove('hidden');
    ta.classList.add('hidden');
    if (btn) btn.classList.add('on');
  } else {
    prev.classList.add('hidden');
    ta.classList.remove('hidden');
    if (btn) btn.classList.remove('on');
  }
}

/* ── Reply submit ─────────────────────────────────────────── */
function sendReply(slug) {
  var ta = document.getElementById('replyTa');
  if (!ta) return;
  var content = ta.value.trim();
  if (!content) { toast('Reply cannot be empty', 'err'); return; }

  var btn = document.getElementById('replyBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Posting…'; }

  var fd = new FormData();
  fd.append('slug', slug);
  fd.append('content', content);
  fd.append('csrf', NX.csrf);

  // Attach post captcha if present
  var capInp = document.getElementById('postCaptchaInput');
  if (capInp) fd.append('post_captcha', capInp.value);

  fetch(NX.base + '/api/reply.php', {method:'POST', body:fd})
    .then(function(r){ return r.json().then(function(d){ d._status=r.status; return d; }); })
    .then(function(data){
      if (data.ok) {
        // Clear editor
        ta.value = '';
        var prev = document.getElementById('replyPreview');
        if (prev) prev.classList.add('hidden');
        ta.classList.remove('hidden');
        var prevBtn = document.getElementById('prevBtn');
        if (prevBtn) prevBtn.classList.remove('on');
        var cnt = document.getElementById('charCnt');
        if (cnt) { cnt.textContent = '0'; cnt.style.color = ''; }

        // Update captcha if server sent a new one
        if (data.new_captcha && capInp) {
          var ql = capInp.closest('.post-captcha-row')&&capInp.closest('.post-captcha-row').querySelector('.captcha-q');
          if (ql) ql.textContent = data.new_captcha.q;
          capInp.value = '';
        }

        // Append post to list
        var list = document.getElementById('postsList');
        if (list && data.post) {
          // Server-rendered post via reload or AJAX HTML
          list.insertAdjacentHTML('beforeend', buildPost(data.post));
          var np = list.lastElementChild;
          renderTimeAgo();
          loadTwitterWidgets();
          np.scrollIntoView({behavior:'smooth',block:'center'});
        } else { location.reload(); }
        toast('Reply posted!', 'ok');

      } else if (data.rate_limited) {
        // Rate limit — show countdown
        startRateCountdown(data.wait || 30);
        toast(data.error, 'warn');

      } else if (data.captcha_failed) {
        toast(data.error, 'err');
        // Reload page to get new captcha
        setTimeout(function(){ location.reload(); }, 1500);

      } else {
        toast(data.error || 'Failed to post', 'err');
      }
    })
    .catch(function(){ toast('Network error — please try again', 'err'); })
    .finally(function(){ if (btn) { btn.disabled=false; btn.textContent='Post Reply'; } });
}

/* Rate limit countdown */
function startRateCountdown(seconds) {
  var info = document.getElementById('rateLimitInfo');
  var btn  = document.getElementById('replyBtn');
  var msg  = document.getElementById('rateLimitMsg');
  var cd   = document.getElementById('rateCountdown');
  if (info) info.style.display = '';
  if (btn)  btn.disabled = true;
  var remaining = seconds;
  function tick() {
    if (msg) msg.textContent = 'Please wait before posting again:';
    if (cd)  cd.textContent = remaining + 's';
    if (remaining <= 0) {
      if (info) info.style.display = 'none';
      if (btn)  btn.disabled = false;
      if (cd)   cd.textContent = '';
      return;
    }
    remaining--;
    setTimeout(tick, 1000);
  }
  tick();
}

function buildPost(p) {
  var av = p.avatar
    ? '<img src="'+esc(p.avatar)+'" class="av-lg" alt="">'
    : '<span class="av-lg av-init">'+esc(p.username[0].toUpperCase())+'</span>';
  var flair = p.role==='admin' ? '<span class="role-flair admin">Admin</span>'
    : p.role==='moderator' ? '<span class="role-flair mod">Mod</span>' : '';
  return '<div class="post" id="post-'+p.id+'">'
    +'<div class="post-side">'+av
    +'<a href="'+NX.base+'/users/profile.php?u='+encodeURIComponent(p.username)+'" class="post-name">@'+esc(p.username)+'</a>'
    +flair+'<span class="post-pcnt">'+p.post_count+' posts</span></div>'
    +'<div class="post-body"><div class="post-meta-bar"><span class="pnum">#'+p.post_num+'</span>'
    +'<time class="ago" data-ts="'+esc(p.created_at)+'"></time>'
    +'<div class="post-acts">'
    +'<button class="pa-btn" onclick="doLike('+p.id+',this)">♥ <span class="lc">0</span></button>'
    +'<button class="pa-btn" onclick="doQuote('+p.id+',\''+esc(p.username)+'\')">↩ Reply</button>'
    +'</div></div>'
    +'<div class="post-content rendered-post" id="pc-'+p.id+'" data-raw="'+btoa(unescape(encodeURIComponent(p.content)))+'">'
    +md(p.content)+'</div></div></div>';
}

/* ── Like ─────────────────────────────────────────────────── */
function doLike(pid, btn) {
  if (!NX.user) { toast('Log in to like posts', 'warn'); return; }
  var fd = new FormData(); fd.append('post_id', pid); fd.append('csrf', NX.csrf);
  fetch(NX.base + '/api/like.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.ok) {
        var lc = btn.querySelector('.lc');
        btn.classList.toggle('liked', data.liked);
        if (lc) lc.textContent = data.count;
      }
    });
}

/* ── Quote reply ──────────────────────────────────────────── */
function doQuote(pid, uname) {
  var bodyEl = document.getElementById('pc-'+pid);
  var text   = bodyEl ? bodyEl.innerText.trim().slice(0,300) : '';
  var ta     = document.getElementById('replyTa');
  if (!ta) return;
  ta.value = '> **@'+uname+'** wrote:\n> '+text.replace(/\n/g,'\n> ')+'\n\n' + ta.value;
  ta.focus();
  ta.scrollIntoView({behavior:'smooth'});
}

/* ── Edit post ────────────────────────────────────────────── */
function doEdit(pid) {
  var box  = document.getElementById('eb-'+pid);
  var body = document.getElementById('pc-'+pid);
  var ta   = document.getElementById('et-'+pid);
  if (!box||!body||!ta) return;
  // Use innerText for plaintext content (already sanitised, we edit raw)
  var rawAttr = body.getAttribute('data-raw');
  ta.value = rawAttr ? atob(rawAttr) : body.innerText;
  box.classList.add('visible');
  box.classList.remove('hidden');
  body.classList.add('hidden');
  ta.focus();
}
function cancelEdit(pid) {
  var box  = document.getElementById('eb-'+pid);
  var body = document.getElementById('pc-'+pid);
  if (box)  { box.classList.remove('visible'); box.classList.add('hidden'); }
  if (body) body.classList.remove('hidden');
}
function saveEdit(pid) {
  var ta = document.getElementById('et-'+pid);
  var ri = document.getElementById('er-'+pid);
  if (!ta) return;
  var fd = new FormData();
  fd.append('post_id',pid);
  fd.append('content', ta.value);
  fd.append('reason',  ri ? ri.value : '');
  fd.append('csrf', NX.csrf);
  fetch(NX.base+'/api/edit.php', {method:'POST',body:fd})
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.ok) {
        var body = document.getElementById('pc-'+pid);
        if (body) {
          // Re-render using client-side md() + mention rendering
          body.innerHTML = renderMentions(md(data.content));
          // Update data-raw so next edit reads correct value
          try { body.setAttribute('data-raw', btoa(unescape(encodeURIComponent(data.content)))); } catch(e){}
          body.classList.remove('hidden');
          loadTwitterWidgets();
        }
        cancelEdit(pid);
        toast('Post updated!','ok');
        var meta = document.querySelector('#post-'+pid+' .post-meta-bar');
        if (meta && !meta.querySelector('.edit-lbl')) {
          var em = document.createElement('em');
          em.className = 'edit-lbl';
          em.textContent = ' (edited)';
          var t = meta.querySelector('time');
          if (t) t.after(em);
        }
      } else {
        toast(data.error || 'Failed to save', 'err');
      }
    })
    .catch(function(){ toast('Network error', 'err'); });
}

/* ── Delete post ──────────────────────────────────────────── */
function doDelete(pid) {
  if (!confirm('Delete this post?')) return;
  var fd = new FormData(); fd.append('post_id',pid); fd.append('csrf',NX.csrf);
  fetch(NX.base+'/api/delete.php', {method:'POST',body:fd})
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.ok) {
        var el = document.getElementById('post-'+pid);
        if (el) { el.style.opacity='.3'; el.style.pointerEvents='none';
          var b = document.getElementById('pc-'+pid);
          if (b) b.innerHTML='<em style="color:#94a3b8">This post has been deleted.</em>'; }
        toast('Deleted','ok');
      }
    });
}

/* ── Image upload ─────────────────────────────────────────── */
function pickImg(inputId, taId) {
  var inp = document.getElementById(inputId);
  if (inp) { inp._taId = taId; inp.click(); }
}
function uploadImg(inp, taId) {
  if (inp.files && inp.files[0]) uploadFileToEditor(inp.files[0], taId || inp._taId || 'replyTa');
}
function uploadFileToEditor(file, taId) {
  var ta = document.getElementById(taId || 'replyTa');
  if (!ta) return;

  // Client-side size check — max from server setting (NX.maxUploadMb, default 5)
  var maxMb = (NX.maxUploadMb || 5);
  if (file.size > maxMb * 1024 * 1024) {
    toast('Image too large — max ' + maxMb + ' MB', 'err');
    return;
  }

  var ph  = '![Uploading ' + file.name + '…]()';
  var cur = ta.selectionStart;
  ta.value = ta.value.slice(0, cur) + ph + ta.value.slice(cur);
  toast('Uploading…', 'warn');

  var fd = new FormData();
  fd.append('file', file);
  fd.append('csrf', NX.csrf);   // ← CSRF token (was missing — caused 403)

  fetch(NX.base + '/api/upload.php', { method: 'POST', body: fd })
    .then(function(r) {
      // Always try to parse JSON — even error responses are JSON
      return r.json().then(function(data) {
        return { ok: r.ok, data: data };
      });
    })
    .then(function(result) {
      var data = result.data;
      if (data.ok) {
        ta.value = ta.value.replace(ph, '![' + file.name + '](' + data.url + ')');
        toast('Image uploaded!', 'ok');
      } else {
        ta.value = ta.value.replace(ph, '');
        toast(data.error || 'Upload failed', 'err');
      }
      ta.dispatchEvent(new Event('input'));
    })
    .catch(function(err) {
      ta.value = ta.value.replace(ph, '');
      toast('Upload failed — check console for details', 'err');
      console.error('Upload error:', err);
    });
}

/* ── DOMContentLoaded ─────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function(){
  var ta  = document.getElementById('replyTa');
  var cnt = document.getElementById('charCnt');
  if (ta) {
    if (cnt) { ta.addEventListener('input', function(){ var n=ta.value.length; cnt.textContent=n; cnt.style.color=n>19000?'var(--red)':n>15000?'var(--amber)':''; }); }
    ta.addEventListener('paste', function(e){
      var items = (e.clipboardData || e.originalEvent.clipboardData).items;
      for (var i=0; i<items.length; i++) {
        if (items[i].type.indexOf('image')!==-1) { e.preventDefault(); uploadFileToEditor(items[i].getAsFile(),'replyTa'); break; }
      }
    });
    ta.addEventListener('dragover',  function(e){ e.preventDefault(); ta.classList.add('dragging'); });
    ta.addEventListener('dragleave', function(){ ta.classList.remove('dragging'); });
    ta.addEventListener('drop', function(e){
      e.preventDefault(); ta.classList.remove('dragging');
      var files = e.dataTransfer.files;
      for (var i=0; i<files.length; i++) { if (files[i].type.startsWith('image/')) uploadFileToEditor(files[i],'replyTa'); }
    });
  }
  renderAllMd();
  renderTimeAgo();
  setInterval(renderTimeAgo, 60000);
});

/* ── Toast ────────────────────────────────────────────────── */
var _tc = 0;
function toast(msg, type) {
  var col = {ok:'#22c55e',err:'#ef4444',warn:'#f59e0b'}[type||'ok'] || '#3b82f6';
  var t = document.createElement('div');
  t.style.cssText = 'position:fixed;bottom:'+(20+_tc*56)+'px;right:20px;'
    +'background:'+col+';color:#fff;padding:11px 16px;border-radius:8px;'
    +'font-size:14px;font-family:var(--font,sans-serif);box-shadow:0 4px 16px rgba(0,0,0,.2);'
    +'z-index:9999;max-width:320px;line-height:1.4;animation:fadeIn .25s ease';
  t.textContent = msg;
  document.body.appendChild(t);
  _tc++;
  setTimeout(function(){ t.style.transition='opacity .3s'; t.style.opacity='0'; setTimeout(function(){ t.remove(); _tc=Math.max(0,_tc-1); },300); }, 3400);
}

/* ================================================================
   @mention autocomplete
   ================================================================ */
(function () {
  var popup     = null;
  var popupItems= [];
  var popupIdx  = -1;
  var mentionStart = -1;
  var mentionTA    = null;

  function createPopup() {
    if (popup) return;
    popup = document.createElement('div');
    popup.className = 'mention-popup';
    popup.id = 'mentionPopup';
    document.body.appendChild(popup);
  }

  function showPopup(ta, users) {
    createPopup();
    popupItems = users;
    popupIdx   = -1;
    if (!users.length) { hidePopup(); return; }
    popup.innerHTML = users.map(function(u, i){
      var av = u.avatar
        ? '<img src="'+esc(u.avatar)+'" class="av-xs" alt="">'
        : '<span class="av-xs">'+esc(u.username[0].toUpperCase())+'</span>';
      return '<div class="mention-item" data-i="'+i+'" onclick="insertMention('+i+')">'+av+'@'+esc(u.username)+'</div>';
    }).join('');
    popup.classList.add('show');

    // Position popup below cursor
    var rect   = ta.getBoundingClientRect();
    var coords = getCaretCoords(ta, ta.selectionStart);
    var top    = rect.top + window.scrollY + coords.top + 20;
    var left   = rect.left + window.scrollX + coords.left;
    popup.style.top  = top  + 'px';
    popup.style.left = left + 'px';
    popup.style.position = 'absolute';
  }

  function hidePopup() {
    if (popup) { popup.classList.remove('show'); popup.innerHTML = ''; }
    mentionStart = -1; mentionTA = null; popupItems = []; popupIdx = -1;
  }

  window.insertMention = function(idx) {
    if (!mentionTA || idx < 0 || idx >= popupItems.length) return;
    var u    = popupItems[idx];
    var val  = mentionTA.value;
    var pre  = val.slice(0, mentionStart);
    var post = val.slice(mentionTA.selectionStart);
    var ins  = '@' + u.username + ' ';
    mentionTA.value = pre + ins + post;
    var pos  = (pre + ins).length;
    mentionTA.selectionStart = mentionTA.selectionEnd = pos;
    mentionTA.focus();
    hidePopup();
  };

  function handleMentionKey(e) {
    if (!popup || !popup.classList.contains('show')) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      popupIdx = (popupIdx + 1) % popupItems.length;
      updateSelected();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      popupIdx = (popupIdx - 1 + popupItems.length) % popupItems.length;
      updateSelected();
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      if (popupIdx >= 0) { e.preventDefault(); insertMention(popupIdx); }
      else hidePopup();
    } else if (e.key === 'Escape') {
      hidePopup();
    }
  }

  function updateSelected() {
    popup.querySelectorAll('.mention-item').forEach(function(el, i){
      el.classList.toggle('selected', i === popupIdx);
      if (i === popupIdx) el.scrollIntoView({block:'nearest'});
    });
  }

  var mentionTimer;
  function handleMentionInput(ta) {
    var val   = ta.value;
    var caret = ta.selectionStart;
    // Find the @ that triggers a mention (word char follows)
    var before = val.slice(0, caret);
    var match  = before.match(/@([a-zA-Z0-9_\-]*)$/);
    if (!match) { hidePopup(); return; }
    var query  = match[1];
    mentionStart = caret - match[0].length;
    mentionTA    = ta;
    if (query.length < 1) { hidePopup(); return; }
    clearTimeout(mentionTimer);
    mentionTimer = setTimeout(function(){
      fetch(NX.base + '/api/search_users.php?q=' + encodeURIComponent(query))
        .then(function(r){ return r.json(); })
        .then(function(users){ showPopup(ta, users); })
        .catch(function(){ hidePopup(); });
    }, 200);
  }

  // Attach to all textareas present or added
  function attachMention(ta) {
    if (ta.dataset.mentionBound) return;
    ta.dataset.mentionBound = '1';
    ta.addEventListener('input',  function(){ handleMentionInput(ta); });
    ta.addEventListener('keydown', handleMentionKey);
    ta.addEventListener('blur', function(){ setTimeout(hidePopup, 200); });
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('textarea.reply-ta, textarea.edit-ta').forEach(attachMention);
    // Also attach to dynamically added textareas via MutationObserver
    new MutationObserver(function(muts){
      muts.forEach(function(m){
        m.addedNodes.forEach(function(n){
          if (n.nodeType!==1) return;
          n.querySelectorAll && n.querySelectorAll('textarea.reply-ta,textarea.edit-ta').forEach(attachMention);
          if (n.matches && (n.matches('textarea.reply-ta')||n.matches('textarea.edit-ta'))) attachMention(n);
        });
      });
    }).observe(document.body, {childList:true,subtree:true});
  });

  /* Simple caret coordinate helper */
  function getCaretCoords(el, pos) {
    var div   = document.createElement('div');
    var style = getComputedStyle(el);
    ['fontFamily','fontSize','fontWeight','lineHeight','padding','border','whiteSpace','wordWrap'].forEach(function(p){
      div.style[p] = style[p];
    });
    div.style.position = 'absolute'; div.style.visibility = 'hidden';
    div.style.overflow = 'auto';     div.style.width = el.offsetWidth + 'px';
    var text = el.value.slice(0, pos);
    div.textContent = text;
    var span = document.createElement('span');
    span.textContent = '|';
    div.appendChild(span);
    document.body.appendChild(div);
    var coords = { top: span.offsetTop, left: span.offsetLeft };
    document.body.removeChild(div);
    return coords;
  }
})();

/* ================================================================
   @mention rendering in post content
   Convert @username text → clickable mention links
   ================================================================ */
function renderMentions(html) {
  return html.replace(/@([a-zA-Z0-9_\-]{3,30})/g, function(_, uname) {
    return '<a href="' + NX.base + '/users/profile.php?u=' + encodeURIComponent(uname) +
      '" class="mention-tag">@' + esc(uname) + '</a>';
  });
}

/* Patch md() to run renderMentions after rendering */
var _origMd = md;
md = function(raw) {
  return renderMentions(_origMd(raw));
};

/* ================================================================
   Extended notification renderer (friend requests, mentions, messages)
   ================================================================ */
var _origLoadNotifs = loadNotifs;
loadNotifs = function() {
  if (!window.NX || !NX.user) return;
  var list = document.getElementById('notifList');
  if (!list) return;
  fetch(NX.base + '/api/notifications.php')
    .then(function(r){ return r.json(); })
    .then(function(rows){
      if (!rows.length) { list.innerHTML = '<p class="notif-empty">Nothing new 🎉</p>'; return; }
      list.innerHTML = rows.map(function(n) {
        var d = n.payload || {};
        var text = '';
        var href = '#';
        switch (n.type) {
          case 'reply':
            text = '<strong>@'+esc(d.from||'')+'</strong> replied in <em>'+esc(d.title||'')+'</em>';
            if (d.slug) href = NX.base+'/forum/topic.php?slug='+encodeURIComponent(d.slug);
            break;
          case 'mention':
            text = '<strong>@'+esc(d.from||'')+'</strong> mentioned you in a post';
            if (d.topicSlug) href = NX.base+'/forum/topic.php?slug='+encodeURIComponent(d.topicSlug)+'#post-'+(d.postId||'');
            break;
          case 'friend_request':
            text = '<strong>@'+esc(d.from||'')+'</strong> sent you a friend request';
            href = NX.base+'/users/profile.php?u='+encodeURIComponent(d.from||'');
            break;
          case 'friend_accepted':
            text = '<strong>@'+esc(d.from||'')+'</strong> accepted your friend request 🎉';
            href = NX.base+'/users/profile.php?u='+encodeURIComponent(d.from||'');
            break;
          case 'message':
            text = '<strong>@'+esc(d.from||'')+'</strong> sent you a message: <em>'+esc(d.subject||'')+'</em>';
            href = NX.base+'/messages/';
            break;
          case 'karma_admin':
            var diff = d.change||0;
            var sign = diff >= 0 ? '+' : '';
            text = 'Your karma was adjusted by an admin: <strong>'+sign+diff+'</strong>'
                 + (d.new ? ' (now '+d.new+')' : '')
                 + (d.reason ? ' — <em>'+esc(d.reason)+'</em>' : '');
            href = NX.base+'/users/profile.php?u='+encodeURIComponent(NX.user.name||'');
            break;
          default:
            text = esc(n.type);
        }
        return '<a href="'+href+'" class="notif-item'+(n.read?'':' unread')+'">'
          +'<div class="notif-item-text">'+text+'</div>'
          +'<div class="notif-item-time">'+timeAgo(n.created_at)+'</div>'
          +'</a>';
      }).join('');
    })
    .catch(function(){ if (list) list.innerHTML = '<p class="notif-empty">Failed to load</p>'; });
};

/* ================================================================
   Edit toggle — show edit form only when Edit button clicked
   (edit button itself is always visible; form is hidden by default)
   ================================================================ */
/* doEdit() already exists above — we just make sure edit-box starts hidden via CSS */

/* ================================================================
   Twitter/X widget loader
   ================================================================ */
function loadTwitterWidgets() {
  var tweets = document.querySelectorAll('.embed-tweet[data-tweet-id]:not([data-loaded])');
  if (!tweets.length) return;
  function doLoad() {
    tweets.forEach(function(el) {
      el.setAttribute('data-loaded','1');
      var id = el.dataset.tweetId;
      if (id && window.twttr && window.twttr.widgets) {
        window.twttr.widgets.createTweet(id, el, {theme:'light',dnt:true,align:'left'});
      }
    });
  }
  if (window.twttr && window.twttr.widgets) { doLoad(); return; }
  if (document.querySelector('script[src*="platform.twitter.com"]')) {
    // Already loading
    var interval = setInterval(function(){
      if (window.twttr && window.twttr.widgets) { clearInterval(interval); doLoad(); }
    },300);
    return;
  }
  var s = document.createElement('script');
  s.src = 'https://platform.twitter.com/widgets.js';
  s.async = true;
  s.onload = doLoad;
  document.head.appendChild(s);
}

/* ================================================================
   Lazy load embeds — for heavy iframes (only load when in viewport)
   ================================================================ */
document.addEventListener('DOMContentLoaded', function(){
  loadTwitterWidgets();

  // IntersectionObserver for lazy embed loading
  if ('IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          var iframe = entry.target;
          if (iframe.dataset.src) {
            iframe.src = iframe.dataset.src;
            iframe.removeAttribute('data-src');
            obs.unobserve(iframe);
          }
        }
      });
    }, {rootMargin:'200px'});

    document.querySelectorAll('iframe.embed-yt[data-src]').forEach(function(el){
      obs.observe(el);
    });
  }
});

/* ================================================================
   Post content — use server-rendered HTML, add edit toggle via CSS
   edit-box already hidden in CSS (display:none on .edit-box.hidden)
   ================================================================ */
/* buildPost is used for AJAX-appended posts (no server rendering there) */
/* so we keep client-side md() for those */

/* ── Post permalink copy ─────────────────────────────────── */
function copyPostLink(postId, btn) {
  var baseUrl = btn.getAttribute('data-url');
  var full = window.location.protocol + '//' + window.location.host + baseUrl;
  var icon_link = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
  var icon_check = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
  function showCopied() {
    btn.innerHTML = icon_check;
    btn.title = 'Copied!';
    setTimeout(function(){ btn.innerHTML = icon_link; btn.title = 'Copy link to this post'; }, 2000);
    toast('Post link copied!', 'ok');
  }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(full).then(showCopied).catch(function() {
      prompt('Copy this link:', full);
    });
  } else {
    prompt('Copy this link:', full);
  }
}

/* ── Code block copy ─────────────────────────────────────── */
function cbCopy(btn) {
  // Walk up to .code-block-wrap, then find the <code> element
  var wrap = btn.closest('.code-block-wrap');
  if (!wrap) return;
  var code = wrap.querySelector('pre.code-block code');
  if (!code) return;

  var text = code.innerText !== undefined ? code.innerText : code.textContent;

  var ok = function() {
    var orig = btn.innerHTML;
    btn.classList.add('copied');
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" '
      + 'stroke-linecap="round" stroke-linejoin="round" width="14" height="14">'
      + '<polyline points="20 6 9 17 4 12"/></svg><span>Copied!</span>';
    setTimeout(function() {
      btn.classList.remove('copied');
      btn.innerHTML = orig;
    }, 2000);
  };

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(ok).catch(function() {
      fallbackCopy(text, ok);
    });
  } else {
    fallbackCopy(text, ok);
  }
}

function fallbackCopy(text, cb) {
  var ta = document.createElement('textarea');
  ta.value = text;
  ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
  document.body.appendChild(ta);
  ta.focus();
  ta.select();
  try { document.execCommand('copy'); if (cb) cb(); }
  catch(e) {}
  document.body.removeChild(ta);
}



/* ── Content security: warn on raw HTML/PHP injection attempt ──── */
(function () {
  // Patterns that suggest someone is typing raw code to inject
  var DANGEROUS = [
    /<script/i,
    /<iframe/i,
    /<object/i,
    /<embed/i,
    /<form/i,
    /<base\s/i,
    /<link\s/i,
    /<meta\s/i,
    /<svg[\s>]/i,
    /<\?php/i,
    /<\?=/,
    /javascript\s*:/i,
    /vbscript\s*:/i,
    /on\w+\s*=/i,       // onerror=, onclick=, onload= etc.
    /data\s*:\s*text\/html/i,
  ];

  function checkContent(ta) {
    var val = ta.value;
    for (var i = 0; i < DANGEROUS.length; i++) {
      if (DANGEROUS[i].test(val)) {
        showHtmlWarning(ta);
        return;
      }
    }
    hideHtmlWarning(ta);
  }

  function showHtmlWarning(ta) {
    var id  = 'sec-warn-' + ta.id;
    if (document.getElementById(id)) return;
    var box = document.createElement('div');
    box.id  = id;
    box.className = 'sec-warning';
    box.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
      + 'stroke-linecap="round" stroke-linejoin="round" width="15" height="15">'
      + '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>'
      + '<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
      + '<span>Raw HTML/code detected. For security, HTML is stripped on save. '
      + 'Use ``` code blocks ``` to display code.</span>';
    ta.parentNode.insertBefore(box, ta.nextSibling);
  }

  function hideHtmlWarning(ta) {
    var id  = 'sec-warn-' + ta.id;
    var el  = document.getElementById(id);
    if (el) el.remove();
  }

  function attachTo(taId) {
    var ta = document.getElementById(taId);
    if (!ta) return;
    ta.addEventListener('input', function () { checkContent(ta); });
    ta.addEventListener('paste', function () {
      setTimeout(function () { checkContent(ta); }, 10);
    });
  }

  // Attach to both editors on page load and after AJAX reply renders
  document.addEventListener('DOMContentLoaded', function () {
    attachTo('replyTa');
  });

  // Expose for dynamic attachment
  window.attachSecCheck = attachTo;
})();

/* ── Welcome Guide addon toggle ──────────────────────────── */
function wgToggle() {
  var b   = document.querySelector('.wg-body');
  var btn = document.querySelector('.wg-toggle');
  if (!b) return;
  var open = b.style.display === 'none';
  b.style.display = open ? '' : 'none';
  if (btn) {
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.classList.toggle('wg-open', open);
  }
}
