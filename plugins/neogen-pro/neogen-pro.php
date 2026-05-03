<?php
/**
 * Plugin Name:       NeoHub Pro — Store Theme Suite
 * Plugin URI:        https://neohub.dev/pricing/
 * Description:       Premium WooCommerce theme suite for neohub.dev. Includes brand CSS, page templates, bilingual RTL product cards, info-page system, FAQ, contact, pricing pages, and WhatsApp support integration. Licensed per-site via subscription.
 * Version:           1.0.0
 * Author:            NeoHub
 * Author URI:        https://neohub.dev/
 * License:           Proprietary
 * License URI:       https://neohub.dev/terms/
 * Text Domain:       neohub-pro
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * WC requires at least: 8.0
 * WC tested up to:   9.9
 */

defined('ABSPATH') || exit;

define('NEOHUB_PRO_VERSION',  '1.0.0');
define('NEOHUB_PRO_FILE',     __FILE__);
define('NEOHUB_PRO_DIR',      plugin_dir_path(__FILE__));
define('NEOHUB_PRO_URL',      plugin_dir_url(__FILE__));
define('NEOHUB_PRO_SLUG',     'neohub-pro');

// ── License check ─────────────────────────────────────────────────────────
require_once NEOHUB_PRO_DIR . 'includes/class-license.php';
require_once NEOHUB_PRO_DIR . 'includes/class-updater.php';

// ── Initialize Updater (handles update checks) ────────────────────────────
new NeoHub_Pro_Updater(NEOHUB_PRO_FILE, NEOHUB_PRO_VERSION);

// ── Core modules (load only when license is valid) ────────────────────────
add_action('plugins_loaded', function () {
    $license = NeoHub_Pro_License::instance();

    if (!$license->is_active()) {
        // Show admin notice and stop — don't load premium features.
        add_action('admin_notices', [NeoHub_Pro_License::class, 'admin_notice_inactive']);
        return;
    }

    require_once NEOHUB_PRO_DIR . 'includes/class-assets.php';
    require_once NEOHUB_PRO_DIR . 'includes/class-templates.php';
    require_once NEOHUB_PRO_DIR . 'includes/class-info-pages.php';
    require_once NEOHUB_PRO_DIR . 'includes/class-whatsapp.php';

    NeoGen_Pro_Assets::init();
    NeoGen_Pro_Templates::init();
    NeoGen_Pro_Info_Pages::init();
    NeoGen_Pro_WhatsApp::init();

    // ── Dynamic Modules ───────────────────────────────────────────────────
    $modules_config = get_option('neogen_pro_modules', [
        'seo'      => true,
        'commerce' => true,
        'theme'    => true,
        'redesign' => true,
    ]);

    if (!empty($modules_config['seo'])) {
        $seo_module_file = NEOHUB_PRO_DIR . 'includes/modules/class-module-seo.php';
        if (file_exists($seo_module_file)) {
            require_once $seo_module_file;
            if (class_exists('NeoHub_Pro_Module_SEO')) NeoHub_Pro_Module_SEO::init();
        }
    }

    if (!empty($modules_config['commerce'])) {
        $commerce_module_file = NEOHUB_PRO_DIR . 'includes/modules/class-module-commerce.php';
        if (file_exists($commerce_module_file)) {
            require_once $commerce_module_file;
            if (class_exists('NeoHub_Pro_Module_Commerce')) NeoHub_Pro_Module_Commerce::init();
        }
    }

    if (!empty($modules_config['theme'])) {
        $theme_module_file = NEOHUB_PRO_DIR . 'includes/modules/class-module-theme.php';
        if (file_exists($theme_module_file)) {
            require_once $theme_module_file;
            if (class_exists('NeoHub_Pro_Module_Theme')) NeoHub_Pro_Module_Theme::init();
        }
    }

    // Redesign module — feature-flagged, every phase OFF by default.
    // Source plan: /Users/fahadalmansour/.claude/plans/fetch-this-design-file-kind-pizza.md
    if (!empty($modules_config['redesign'])) {
        $redesign_module_file = NEOHUB_PRO_DIR . 'includes/modules/class-module-redesign.php';
        if (file_exists($redesign_module_file)) {
            require_once $redesign_module_file;
            if (class_exists('NeoHub_Pro_Module_Redesign')) NeoHub_Pro_Module_Redesign::init();
        }
    }
}, 5);

// ── Admin menu ────────────────────────────────────────────────────────────
add_action('admin_menu', function () {
    // Add top-level menu
    add_menu_page(
        'NeoHub Pro',
        'NeoHub Pro',
        'manage_options',
        'neohub-pro',
        function () { require NEOHUB_PRO_DIR . 'admin/dashboard.php'; },
        'dashicons-chart-pie', // Plugin icon
        30 // Position
    );

    // Add Dashboard sub-menu (same as top-level)
    add_submenu_page(
        'neohub-pro',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'neohub-pro',
        function () { require NEOHUB_PRO_DIR . 'admin/dashboard.php'; }
    );

    // Add Settings sub-menu
    add_submenu_page(
        'neohub-pro',
        'Settings',
        'Settings',
        'manage_options',
        'neohub-pro-settings',
        function () { require NEOHUB_PRO_DIR . 'admin/settings-page.php'; }
    );
});

// ── Activation / deactivation ─────────────────────────────────────────────
register_activation_hook(__FILE__, function () {
    NeoHub_Pro_License::instance()->on_activate();
});

register_deactivation_hook(__FILE__, function () {
    // Do not delete the license key — user may reactivate.
    delete_transient('neogen_pro_license_cache');
});

// ── Declare WooCommerce HPOS compatibility ────────────────────────────────
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
