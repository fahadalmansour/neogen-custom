<?php
/**
 * Snippet: WooCommerce Order Logger
 * Auto-loaded by plugins/neogen-snippets/neogen-snippets.php
 * Toggle via WP admin → Plugins → NeoGen Snippets.
 *
 * Logs every new WooCommerce order ID to the PHP error log.
 * Disabled by default — flip NEOGEN_ORDER_LOG_ENABLED to true to activate.
 *
 * Output destination:
 * - If WP_DEBUG_LOG is on, goes to wp-content/debug.log
 * - Otherwise, wherever the server's PHP error_log is configured
 * Verify the destination on blazr before relying on this for auditing.
 */

defined('ABSPATH') || exit;

const NEOGEN_ORDER_LOG_ENABLED = false;

if (!NEOGEN_ORDER_LOG_ENABLED) {
    return;
}

add_action('woocommerce_new_order', function ($order_id) {
    error_log('[NEOGEN-ORDER] new order: ' . $order_id);
});
