<?php
/**
 * Snippet: WhatsApp Float Button
 * Auto-loaded by plugins/neogen-snippets/neogen-snippets.php
 * Toggle via WP admin → Plugins → NeoGen Snippets.
 *
 * Inert until you set NEOGEN_WHATSAPP_PHONE below to a real number.
 */

defined('ABSPATH') || exit;

// Phone number in international format, digits only (no +, spaces, or dashes).
// Example: '966512345678'. Leave empty to keep the snippet disabled.
const NEOGEN_WHATSAPP_PHONE = '';

if (NEOGEN_WHATSAPP_PHONE === '') {
    return;
}

// Ready to go live? Uncomment the block below after setting the phone above.
//
// add_action('wp_footer', function () {
//     $phone = rawurlencode(NEOGEN_WHATSAPP_PHONE);
//     echo '<a href="https://wa.me/' . esc_attr($phone) . '"'
//        . ' target="_blank" rel="noopener"'
//        . ' style="position:fixed;right:20px;bottom:20px;z-index:9999;'
//        . 'display:inline-block;padding:12px 16px;border-radius:999px;'
//        . 'background:#25d366;color:#fff;font-weight:600;text-decoration:none;'
//        . 'box-shadow:0 4px 12px rgba(0,0,0,.2);">WhatsApp</a>';
// });
