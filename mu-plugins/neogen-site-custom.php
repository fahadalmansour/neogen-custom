<?php
/**
 * Plugin Name: NeoGen Site Custom
 * Description: Central site customizations deployed via git. Auto-loaded (mu-plugin).
 * Version: 1.1.0
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

if (!defined('NEOGEN_CUSTOM_VERSION')) {
    define('NEOGEN_CUSTOM_VERSION', '1.1.0');
}

// Admin bar badge — shows current deployed version (admin-only, visible proof of successful deploy)
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;
    $wp_admin_bar->add_node([
        'id'    => 'neogen-deployed-version',
        'title' => '🚀 NG ' . NEOGEN_CUSTOM_VERSION,
        'href'  => admin_url('tools.php?page=neogen-deploy'),
        'meta'  => ['title' => 'NeoGen Custom deployed version'],
    ]);
}, 100);

// Example hook: log every WooCommerce order to a custom file for Fahad's review
// add_action('woocommerce_new_order', function ($order_id) {
//     error_log('[NEOGEN-ORDER] new order: ' . $order_id);
// });

// Add more site-wide customizations below this line
