<?php
if (!defined('NEXUS')) exit('Forbidden');

/**
 * Nexus Forum — Markdown renderer (Discourse/Flarum-compatible)
 *
 * Input has already been sanitised by sanitise() which:
 *  - Strips dangerous tags (script, iframe, etc.)
 *  - htmlspecialchars() remaining content (< > & become entities)
 *
 * Supported syntax:
 *   ```lang  …  ```   fenced code block with syntax highlighting
 *   ```      …  ```   fenced code block, no language
 *   `inline`          inline code span
 *   > text            blockquote (one or more lines)
 *   **bold**          bold
 *   *italic*          italic
 *   ~~strike~~        strikethrough
 *   # – ######        headings
 *   - item / * item   bullet list
 *   1. item           ordered list
 *   [text](url)       link
 *   ![alt](url)       image
 *   @username         mention
 *   ---               horizontal rule
 *   https://…         auto-embed videos / auto-link
 */

// ─────────────────────────────────────────────────────────────────
// Placeholder markers (STX/ETX can't appear in user content)
// ─────────────────────────────────────────────────────────────────
define('_MK_FP', "\x02FENCE");
define('_MK_FS', "FNCE\x03");
define('_MK_IP', "\x02INLIN");
define('_MK_IS', "INLN\x03");
define('_MK_BQ', "\x02BQUOT");
define('_MK_BS', "BQUT\x03");

function render_post(string $raw): string
{
    // ── Normalise ────────────────────────────────────────────────
    $s = str_replace(["\r\n", "\r"], "\n", $raw);

    $fences  = [];
    $quotes  = [];
    $inlines = [];

    // ── Safe URL check ───────────────────────────────────────────
    $safeUrl = static fn(string $u): bool =>
        str_starts_with(strtolower(trim($u)), '/') ||
        (bool) preg_match('#^https?://#i', trim($u));

    // ── Copy button SVG ──────────────────────────────────────────
    $copyBtn = '<button class="cb-copy" onclick="cbCopy(this)" '
             . 'title="Copy" aria-label="Copy code">'
             . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
             . 'stroke-linecap="round" stroke-linejoin="round" width="13" height="13">'
             . '<rect x="9" y="9" width="13" height="13" rx="2"/>'
             . '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'
             . '</svg><span>Copy</span></button>';

    // ════════════════════════════════════════════════════════════
    // PASS 1 — Extract code fences (line-by-line state machine)
    //
    // Runs BEFORE everything else so code content is never touched
    // by any other processor. Handles blank lines, indentation,
    // special characters without any regex backtracking.
    // ════════════════════════════════════════════════════════════
    {
        $in      = false;
        $lang    = '';
        $type    = '';
        $buf     = [];
        $newLines = [];

        foreach (explode("\n", $s) as $line) {
            if (!$in) {
                // Triple-backtick opener: ```lang  or  ```
                if (preg_match('/^```([ \t]*\w*)[ \t]*$/', $line, $m)) {
                    $in = true; $type = 'triple'; $lang = trim($m[1]); $buf = [];
                // Lone backtick on its own line
                } elseif (preg_match('/^`[ \t]*$/', $line)) {
                    $in = true; $type = 'single'; $lang = ''; $buf = [];
                } else {
                    $newLines[] = $line;
                }
            } else {
                $close = ($type === 'triple' && preg_match('/^```[ \t]*$/', $line))
                      || ($type === 'single' && preg_match('/^`[ \t]*$/', $line));
                if ($close) {
                    // Build code block HTML
                    // Content is already entity-encoded by sanitise() — don't double-encode
                    $code = implode("\n", $buf);
                    $l    = strtolower(trim($lang));
                    if ($l !== '') {
                        $lb  = htmlspecialchars($l, ENT_QUOTES, 'UTF-8');
                        $hdr = '<div class="cb-header">'
                             . '<span class="cb-lang">' . $lb . '</span>' . $copyBtn
                             . '</div>';
                        $blk = '<pre class="code-block" data-lang="' . $lb . '">'
                             . '<code class="language-' . $lb . '">' . $code . '</code></pre>';
                    } else {
                        $hdr = '<div class="cb-header cb-header-nolang">' . $copyBtn . '</div>';
                        $blk = '<pre class="code-block"><code>' . $code . '</code></pre>';
                    }
                    $idx      = count($fences);
                    $fences[] = '<div class="code-block-wrap">' . $hdr . $blk . '</div>';
                    $newLines[] = _MK_FP . $idx . _MK_FS;
                    $in = false; $buf = [];
                } else {
                    $buf[] = $line;
                }
            }
        }
        // Unclosed fence — render what was collected
        if ($in && $buf) {
            $code = implode("\n", $buf);
            $hdr  = '<div class="cb-header cb-header-nolang">' . $copyBtn . '</div>';
            $blk  = '<pre class="code-block"><code>' . $code . '</code></pre>';
            $idx  = count($fences);
            $fences[] = '<div class="code-block-wrap">' . $hdr . $blk . '</div>';
            $newLines[] = _MK_FP . $idx . _MK_FS;
        }
        $s = implode("\n", $newLines);
    }

    // ════════════════════════════════════════════════════════════
    // PASS 2 — Extract blockquotes (line-by-line, same approach)
    //
    // Consecutive "> " lines form one blockquote block.
    // Supports nested content: bold, italic, inline code, links.
    // Matches both raw > and entity-encoded &gt; from sanitise().
    // ════════════════════════════════════════════════════════════
    {
        $bqBuf  = [];
        $bqOut  = [];

        $flushBq = function () use (&$bqBuf, &$bqOut, &$quotes, $copyBtn): void {
            if (!$bqBuf) return;
            $inner = implode("\n", $bqBuf);
            // Let inline markdown run inside blockquote
            $inner = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $inner);
            $inner = preg_replace('/\*\*(.+?)\*\*/s',     '<strong>$1</strong>',          $inner);
            $inner = preg_replace('/\*([^\*\n]+)\*/',      '<em>$1</em>',                 $inner);
            $inner = preg_replace('/~~(.+?)~~/s',          '<del>$1</del>',               $inner);
            // Wrap each line in <p> if multiple lines, otherwise just the text
            $bqLines = array_filter(explode("\n", $inner), fn($l) => trim($l) !== '');
            if (count($bqLines) > 1) {
                $inner = implode('', array_map(fn($l) => '<p>' . trim($l) . '</p>', $bqLines));
            } else {
                $inner = trim($inner);
            }
            $idx     = count($quotes);
            $quotes[] = '<blockquote class="post-quote">' . $inner . '</blockquote>';
            $bqOut[] = _MK_BQ . $idx . _MK_BS;
            $bqBuf   = [];
        };

        foreach (explode("\n", $s) as $line) {
            if (preg_match('/^(?:&gt;|>) ?(.*)$/', $line, $m)) {
                $bqBuf[] = $m[1];  // already entity-encoded
            } else {
                $flushBq();
                $bqOut[] = $line;
            }
        }
        $flushBq();
        $s = implode("\n", $bqOut);
    }

    // ════════════════════════════════════════════════════════════
    // PASS 3 — Inline code spans
    // ════════════════════════════════════════════════════════════
    $s = preg_replace_callback(
        '/`([^`\n]+)`/',
        static function (array $m) use (&$inlines): string {
            $idx = count($inlines);
            // Content already entity-encoded by sanitise()
            $inlines[] = '<code class="inline-code">' . $m[1] . '</code>';
            return _MK_IP . $idx . _MK_IS;
        },
        $s
    );

    // ════════════════════════════════════════════════════════════
    // PASS 4 — Block-level markdown
    // ════════════════════════════════════════════════════════════

    // Headings
    $s = preg_replace('/^#{6} (.+)$/m', '<h6>$1</h6>', $s);
    $s = preg_replace('/^#{5} (.+)$/m', '<h5>$1</h5>', $s);
    $s = preg_replace('/^#{4} (.+)$/m', '<h4>$1</h4>', $s);
    $s = preg_replace('/^#{3} (.+)$/m', '<h3>$1</h3>', $s);
    $s = preg_replace('/^#{2} (.+)$/m', '<h2>$1</h2>', $s);
    $s = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $s);

    // Horizontal rules
    $s = preg_replace('/^(-{3,}|\*{3,}|_{3,})$/m', '<hr>', $s);

    // Lists — bullet
    $s = preg_replace('/^[ \t]*[*\-+] (.+)$/m',       '<li>$1</li>',  $s);
    $s = preg_replace('/((?:<li>.*<\/li>\n?)+)/',      '<ul>$1</ul>',  $s);
    $s = preg_replace('/<\/ul>\s*<ul>/',                '',             $s);
    $s = preg_replace_callback('/<ul>(.*?)<\/ul>/s',
         static fn($m) => '<ul>' . str_replace("\n", '', $m[1]) . '</ul>', $s);

    // Lists — ordered
    $s = preg_replace('/^[ \t]*\d+\. (.+)$/m',        '<oli>$1</oli>',$s);
    $s = preg_replace('/((?:<oli>.*<\/oli>\n?)+)/',    '<ol>$1</ol>',  $s);
    $s = preg_replace('/<\/ol>\s*<ol>/',                '',             $s);
    $s = str_replace(['<oli>', '</oli>'], ['<li>', '</li>'], $s);
    $s = preg_replace_callback('/<ol>(.*?)<\/ol>/s',
         static fn($m) => '<ol>' . str_replace("\n", '', $m[1]) . '</ol>', $s);

    // Tables
    $s = preg_replace_callback('/\|(.+)\|\n\|[-| :]+\|\n((?:\|.+\|\n?)+)/',
        static function (array $m): string {
            $ths = implode('', array_map(
                static fn($c) => '<th>' . trim($c) . '</th>',
                array_filter(explode('|', $m[1]), static fn($c) => trim($c) !== '')
            ));
            $trs = implode('', array_map(static function (string $row): string {
                $cells = array_filter(explode('|', $row), static fn($c) => trim($c) !== '');
                return '<tr>' . implode('', array_map(static fn($c) => '<td>' . trim($c) . '</td>', $cells)) . '</tr>';
            }, array_filter(explode("\n", trim($m[2])))));
            return '<table><thead><tr>' . $ths . '</tr></thead><tbody>' . $trs . '</tbody></table>';
        }, $s);

    // ════════════════════════════════════════════════════════════
    // PASS 5 — Inline markdown
    // ════════════════════════════════════════════════════════════

    $s = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $s);
    $s = preg_replace('/\*\*(.+?)\*\*/s',     '<strong>$1</strong>',          $s);
    $s = preg_replace('/\*([^\*\n]+)\*/',      '<em>$1</em>',                 $s);
    $s = preg_replace('/___(.+?)___/s',        '<strong><em>$1</em></strong>', $s);
    $s = preg_replace('/__(.+?)__/s',          '<strong>$1</strong>',          $s);
    $s = preg_replace('/_([^_\n]+)_/',         '<em>$1</em>',                 $s);
    $s = preg_replace('/~~(.+?)~~/s',          '<del>$1</del>',               $s);

    // Images
    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/',
        static function (array $m) use ($safeUrl): string {
            $src = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            $alt = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            if (!$safeUrl($src)) return $m[0];
            return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
                 . '" alt="'   . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8')
                 . '" loading="lazy" class="post-img" onclick="lightbox(this)">';
        }, $s);

    // Links
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/',
        static function (array $m) use ($safeUrl): string {
            $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            $txt = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            if (!$safeUrl($url)) return htmlspecialchars($txt, ENT_QUOTES, 'UTF-8');
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
                 . '" target="_blank" rel="noopener noreferrer nofollow">'
                 . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . '</a>';
        }, $s);

    // @mentions
    $base = BASE;
    $s = preg_replace_callback('/@([a-zA-Z0-9_\-]{3,30})/',
        static function (array $m) use ($base): string {
            $u = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $base . '/users/profile.php?u=' . $u
                 . '" class="mention-tag">@' . $u . '</a>';
        }, $s);

    // ════════════════════════════════════════════════════════════
    // PASS 6 — URL auto-embed + auto-link
    //
    // EVERY bare URL (on its own line OR inline) is attempted as an embed.
    // Entity-encoded URLs from sanitise() are decoded before matching.
    // Multiple video links in one post all embed.
    // ════════════════════════════════════════════════════════════

    // Decode entity-encoded URLs so patterns match (sanitise turns & → &amp;)
    // We match, decode, try embed or link, then re-encode for output.
    $s = preg_replace_callback(
        '#(^|[ \t]|<p>)(https?://[^\s<>"\'&]+(?:&amp;[^\s<>"\'&]*)*)#m',
        static function (array $m): string {
            $pre     = $m[1];
            $rawUrl  = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            $safeEnc = htmlspecialchars($rawUrl, ENT_QUOTES, 'UTF-8');

            // Try embed first
            $embed = try_embed($rawUrl);
            if ($embed !== null) {
                // Wrap standalone embeds cleanly
                return $pre . $embed;
            }

            // Otherwise link
            return $pre . '<a href="' . $safeEnc . '" target="_blank" '
                 . 'rel="noopener noreferrer nofollow">' . $safeEnc . '</a>';
        },
        $s
    );

    // ════════════════════════════════════════════════════════════
    // PASS 7 — Paragraph wrapping
    // ════════════════════════════════════════════════════════════
    $lines   = explode("\n", $s);
    $out     = [];
    $para    = '';

    $fpQ  = preg_quote(_MK_FP, '/');
    $fsQ  = preg_quote(_MK_FS, '/');
    $bpQ  = preg_quote(_MK_BQ, '/');
    $bsQ  = preg_quote(_MK_BS, '/');

    $blockRe = '/^(<(h[1-6]|ul|ol|blockquote|pre|table|hr|img|div|figure|p)|'
             . $fpQ . '\d+' . $fsQ . '|'
             . $bpQ . '\d+' . $bsQ . ')/';

    $flush = static function () use (&$para, &$out): void {
        $t = trim($para);
        if ($t !== '') $out[] = '<p>' . $t . '</p>';
        $para = '';
    };

    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '') {
            $flush();
        } elseif (preg_match($blockRe, $t)) {
            $flush();
            $out[] = $line;
        } else {
            $para .= ($para !== '' ? ' ' : '') . $line;
        }
    }
    $flush();
    $s = implode("\n", $out);

    // ════════════════════════════════════════════════════════════
    // PASS 8 — Restore all placeholders
    // ════════════════════════════════════════════════════════════
    foreach ($fences  as $i => $html) $s = str_replace(_MK_FP . $i . _MK_FS, $html, $s);
    foreach ($quotes  as $i => $html) $s = str_replace(_MK_BQ . $i . _MK_BS, $html, $s);
    foreach ($inlines as $i => $html) $s = str_replace(_MK_IP . $i . _MK_IS, $html, $s);

    return process_embeds($s);
}
