<?php
/**
 * Hello World — Example addon
 * Appends a small note below every post footer. Demonstrates the hook system.
 */
if (!defined('NEXUS')) exit('Forbidden');

addon_on('render_post_footer', function (array $post): string {
    // Return empty string to add nothing, or HTML to append below the post
    return ''; // Disabled by default — remove the return to activate
    return '<div style="font-size:11px;color:#94a3b8;margin-top:8px;padding-top:6px;'
         . 'border-top:1px solid var(--border)">Hello World addon is active ✓</div>';
}, 999);

addon_on('after_topic_created', function (array $data): void {
    // $data: topic_id, title, slug, category_id, user_id
    // Example: error_log("New topic created: " . $data['title']);
});
