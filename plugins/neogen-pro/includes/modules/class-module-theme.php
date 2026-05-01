<?php
/**
 * NeoGen Pro — Theme & UI Module
 * Consolidates A/B testing, Coming Soon mode, Product Videos, and Theme integration.
 */

defined('ABSPATH') || exit;

class NeoGen_Pro_Module_Theme {

    const VERSION = '1.0.0';
    
    public static function init() {
        // ── A/B Testing ──────────────────────────────────────────────────
        add_action('admin_bar_menu', [__CLASS__, 'ab_admin_bar'], 101);

        // ── Coming Soon Mode ──────────────────────────────────────────────
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('template_redirect', [__CLASS__, 'coming_soon_interceptor'], 0);

        // ── Product Videos ────────────────────────────────────────────────
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'product_video_field']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_video_field']);
        add_action('woocommerce_before_single_product_summary', [__CLASS__, 'render_product_video'], 25);

        // ── Theme Integration (Legacy neogen-theme.php) ───────────────────
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 100);
        add_action('wp_head', [__CLASS__, 'emit_theme_meta'], 1);
        add_filter('body_class', [__CLASS__, 'theme_body_classes'], 10);
        add_action('wp_body_open', [__CLASS__, 'render_header'], 5);
        add_action('wp_footer', [__CLASS__, 'render_footer'], 5);
        
        // WooCommerce Overrides
        add_filter('wc_get_template_part', [__CLASS__, 'woocommerce_template_parts'], 10, 3);
        add_filter('wc_get_template', [__CLASS__, 'woocommerce_full_templates'], 10, 5);
        add_action('init', [__CLASS__, 'remove_wc_defaults'], 20);
        
        // Blocksy Handoff
        add_filter('body_class', [__CLASS__, 'blocksy_handoff_classes'], 11);
    }

    /* =====================================================================
     * A/B TESTING ENGINE
     * ===================================================================== */
    public static function ab_bucket($experiment_key, $variants = ['control', 'treatment']) {
        if (empty($experiment_key) || empty($variants)) return $variants[0] ?? null;
        $vid = self::ab_visitor_id();
        if (!$vid) return $variants[0];
        $hash = crc32($vid . '|' . $experiment_key);
        return $variants[$hash % count($variants)];
    }

    private static function ab_visitor_id() {
        if (!empty($_COOKIE['ngab_vid'])) return preg_replace('/[^a-zA-Z0-9]/', '', $_COOKIE['ngab_vid']);
        if (headers_sent()) return null;
        $vid = bin2hex(random_bytes(16));
        setcookie('ngab_vid', $vid, time() + 60*60*24*180, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
        $_COOKIE['ngab_vid'] = $vid;
        return $vid;
    }

    public static function ab_admin_bar($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;
        $wp_admin_bar->add_node(['id' => 'neogen-ab', 'title' => '🧪 A/B Suite', 'parent' => 'top-secondary']);
    }

    /* =====================================================================
     * COMING SOON MODE
     * ===================================================================== */
    public static function coming_soon_interceptor() {
        if (is_admin() || current_user_can('edit_posts') || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) return;
        if (get_option('ng_coming_soon_enabled') !== '1') return;

        // Bypasses for critical endpoints
        $path = strtok($_SERVER['REQUEST_URI'], '?');
        if (preg_match('/^\/(wp-login|wp-admin|wp-json|wp-cron|sitemap|llms\.txt|ads\.txt|robots\.txt)/', $path)) return;

        nocache_headers();
        header('HTTP/1.1 503 Service Unavailable');
        header('Retry-After: 3600');
        include NEOGEN_PRO_DIR . 'includes/modules/views/coming-soon-page.php';
        exit;
    }

    /* =====================================================================
     * PRODUCT VIDEOS
     * ===================================================================== */
    public static function product_video_field() {
        woocommerce_wp_text_input([
            'id' => '_ng_video_url',
            'label' => 'Product Video URL (YouTube)',
            'description' => 'Enter a YouTube URL to show a video in the product gallery.',
            'desc_tip' => true,
        ]);
    }

    public static function save_product_video_field($post_id) {
        $url = isset($_POST['_ng_video_url']) ? esc_url_raw($_POST['_ng_video_url']) : '';
        update_post_meta($post_id, '_ng_video_url', $url);
    }

    public static function render_product_video() {
        $url = get_post_meta(get_the_ID(), '_ng_video_url', true);
        if (!$url) return;
        // Logic to extract ID and render iframe...
    }

    /* =====================================================================
     * THEME INTEGRATION
     * ===================================================================== */
    public static function enqueue_assets() {
        // Enqueue neogen.css and neogen.js from neogen-theme-assets
    }

    public static function emit_theme_meta() {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">' . "\n";
    }

    public static function theme_body_classes($classes) {
        $classes[] = 'ng-theme';
        return $classes;
    }

    public static function render_header() {
        if (self::is_blocksy_handoff()) return;
        include NEOGEN_PRO_DIR . 'includes/modules/views/header.php';
    }

    public static function render_footer() {
        if (self::is_blocksy_handoff()) return;
        include NEOGEN_PRO_DIR . 'includes/modules/views/footer.php';
    }

    /* =====================================================================
     * WOOCOMMERCE OVERRIDES
     * ===================================================================== */
    public static function woocommerce_template_parts($template, $slug, $name) {
        // Map templates to NeoGen Pro versions
        return $template;
    }

    public static function woocommerce_full_templates($template, $template_name, $args, $template_path, $default_path) {
        return $template;
    }

    public static function remove_wc_defaults() {
        remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
    }

    /* =====================================================================
     * ADMIN & HANDOFF
     * ===================================================================== */
    public static function admin_menu() {
        add_management_page('Coming Soon', 'Coming Soon', 'manage_options', 'neogen-coming-soon', [__CLASS__, 'render_coming_soon_admin']);
        add_management_page('Blocksy Handoff', 'Blocksy Handoff', 'manage_options', 'neogen-blocksy-handoff', [__CLASS__, 'render_handoff_admin']);
    }

    public static function is_blocksy_handoff() {
        return (bool)get_option('ng_blocksy_chrome_handoff', false);
    }

    public static function blocksy_handoff_classes($classes) {
        if (!self::is_blocksy_handoff()) $classes[] = 'ng-mu-chrome';
        if (!get_option('ng_blocksy_dark_mode_allowed')) $classes[] = 'ng-light-only';
        return $classes;
    }

    public static function render_coming_soon_admin() { /* UI Logic */ }
    public static function render_handoff_admin() { /* UI Logic */ }
}
