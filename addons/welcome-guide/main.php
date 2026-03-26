<?php
/**
 * Welcome & Formatting Guide Addon
 *
 * Fires on render_post_footer for post #1 of every topic.
 * Shows a collapsible formatting cheatsheet under the first post.
 * wgToggle() is defined in public/js/app.js — no inline script needed.
 */
if (!defined('NEXUS')) exit('Forbidden');

addon_on('render_post_footer', function (array $post): string {

    // Only show on the first post (the OP) in every topic
    if ((int)($post['post_num'] ?? 0) !== 1) return '';

    // Respect admin on/off toggle
    if (cfg('welcome_guide_enabled', '1') !== '1') return '';

    return '
<div class="wg-guide" id="wgGuide">
  <button class="wg-toggle" onclick="wgToggle()" aria-expanded="false">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" width="13" height="13">
      <circle cx="12" cy="12" r="10"/>
      <line x1="12" y1="8" x2="12" y2="12"/>
      <line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    Formatting guide
    <svg class="wg-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2.5" stroke-linecap="round" width="11" height="11">
      <polyline points="6 9 12 15 18 9"/>
    </svg>
  </button>
  <div class="wg-body" style="display:none">
    <div class="wg-grid">

      <div class="wg-section">
        <div class="wg-section-title">Text Formatting</div>
        <div class="wg-row"><code class="wg-mono">**bold**</code>   <span><strong>bold</strong></span></div>
        <div class="wg-row"><code class="wg-mono">*italic*</code>   <span><em>italic</em></span></div>
        <div class="wg-row"><code class="wg-mono">~~strike~~</code> <span><del>strike</del></span></div>
        <div class="wg-row"><code class="wg-mono">[text](url)</code><span><a href="#">link</a></span></div>
      </div>

      <div class="wg-section">
        <div class="wg-section-title">Code</div>
        <div class="wg-row">
          <code class="wg-mono">`inline`</code>
          <span><code style="background:#f5f3ff;color:#7c3aed;padding:1px 5px;border-radius:4px;font-size:12px">inline</code></span>
        </div>
        <div class="wg-row wg-block-row">
          <div>
            <code class="wg-mono">```php</code><br>
            <code class="wg-mono">echo "hi";</code><br>
            <code class="wg-mono">```</code>
          </div>
          <span>→ purple code block</span>
        </div>
        <div class="wg-hint">Languages: php, js, python, sql, html, css, bash…</div>
      </div>

      <div class="wg-section">
        <div class="wg-section-title">Quote</div>
        <div class="wg-row wg-block-row">
          <div>
            <code class="wg-mono">&gt; first line</code><br>
            <code class="wg-mono">&gt; second line</code>
          </div>
          <span>→ green quote block</span>
        </div>
        <div class="wg-section-title" style="margin-top:10px">Lists</div>
        <div class="wg-row"><code class="wg-mono">- item</code>  <span>bullet list</span></div>
        <div class="wg-row"><code class="wg-mono">1. item</code> <span>numbered list</span></div>
      </div>

      <div class="wg-section">
        <div class="wg-section-title">Other</div>
        <div class="wg-row"><code class="wg-mono">## Heading</code>  <span><strong>Heading</strong></span></div>
        <div class="wg-row"><code class="wg-mono">@username</code>   <span>mention user</span></div>
        <div class="wg-row"><code class="wg-mono">---</code>         <span>horizontal rule</span></div>
        <div class="wg-hint" style="margin-top:8px">
          Paste a YouTube, Spotify, Twitter or Vimeo URL on its own line to auto-embed it.
        </div>
      </div>

    </div>
  </div>
</div>
';
}, 100);
