<?php
/**
 * Plugin Name: NeoGen Site Custom
 * Description: Central site customizations deployed via git. Auto-loaded (mu-plugin).
 * Version: 1.20.5
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

if (!defined('NEOGEN_CUSTOM_VERSION')) {
    define('NEOGEN_CUSTOM_VERSION', '1.20.5');
}

/**
 * The WooCommerce version we last reconciled our 15 template overrides
 * against (under mu-plugins/neogen-theme-assets/templates/woocommerce/).
 * Bump after each upstream WC template review pass.
 */
if (!defined('NG_TESTED_WC')) {
    define('NG_TESTED_WC', '10.7');
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

/**
 * WC compat sentinel — fire a one-line admin notice when the live
 * WooCommerce version is more than two minors newer than NG_TESTED_WC.
 * Two minors is WC's typical "templates may have moved" threshold.
 * Acts as a forcing function to walk the templates/woocommerce/ tree
 * and reconcile against upstream after a major WC bump.
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    if (!defined('WC_VERSION')) return;

    $tested_parts = array_pad(explode('.', NG_TESTED_WC), 2, '0');
    $live_parts   = array_pad(explode('.', WC_VERSION),    2, '0');
    $tested = ((int) $tested_parts[0]) * 100 + ((int) $tested_parts[1]);
    $live   = ((int) $live_parts[0])   * 100 + ((int) $live_parts[1]);

    if ($live - $tested >= 2) {
        echo '<div class="notice notice-warning"><p><strong>NeoGen:</strong> '
           . 'WooCommerce ' . esc_html(WC_VERSION) . ' is more than two minor '
           . 'versions newer than the version this overlay was last verified '
           . 'against (' . esc_html(NG_TESTED_WC) . '). Reconcile template '
           . 'overrides under <code>mu-plugins/neogen-theme-assets/templates/'
           . 'woocommerce/</code> against the upstream WC files, then bump '
           . '<code>NG_TESTED_WC</code> in <code>neogen-site-custom.php</code>.'
           . '</p></div>';
    }
});

// Add more site-wide customizations below this line
