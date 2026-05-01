<?php
/**
 * NeoGen Pro — WooCommerce template overrides.
 * Points WC template_include at the mu-plugin template directory
 * for all custom page templates.
 */

defined('ABSPATH') || exit;

class NeoGen_Pro_Templates {

    private static string $tpl_dir;

    public static function init(): void {
        self::$tpl_dir = trailingslashit(WPMU_PLUGIN_DIR) . 'neogen-theme-assets/templates/';

        // WooCommerce template overrides
        add_filter('wc_get_template',      [self::class, 'wc_template'],      10, 2);
        add_filter('wc_get_template_part', [self::class, 'wc_template_part'], 10, 3);
        add_filter('template_include',     [self::class, 'template_include'],  99);

        // Declare WooCommerce block templates support
        add_action('after_setup_theme', [self::class, 'declare_wc_support']);
    }

    public static function declare_wc_support(): void {
        add_theme_support('woocommerce', [
            'thumbnail_image_width' => 400,
            'single_image_width'    => 800,
            'product_grid'          => ['default_rows' => 3, 'min_rows' => 1, 'default_columns' => 3, 'min_columns' => 1, 'max_columns' => 5],
        ]);
        add_theme_support('wc-product-gallery-zoom');
        add_theme_support('wc-product-gallery-lightbox');
        add_theme_support('wc-product-gallery-slider');
    }

    public static function wc_template(string $template, string $template_name): string {
        $custom = self::$tpl_dir . 'woocommerce/' . $template_name;
        return file_exists($custom) ? $custom : $template;
    }

    public static function wc_template_part(string $template, string $slug, string $name): string {
        $file = $name ? "{$slug}-{$name}.php" : "{$slug}.php";
        $custom = self::$tpl_dir . 'woocommerce/' . $file;
        return file_exists($custom) ? $custom : $template;
    }

    public static function template_include(string $template): string {
        // Front page
        if (is_front_page()) {
            $fp = self::$tpl_dir . 'front-page.php';
            if (file_exists($fp)) return $fp;
        }

        // WooCommerce archive (shop, product_cat, product_tag, search with post_type=product)
        if (is_shop() || is_product_category() || is_product_tag()) {
            $ar = self::$tpl_dir . 'woocommerce/archive-product.php';
            if (file_exists($ar)) return $ar;
        }

        // Single product
        if (is_product()) {
            $sp = self::$tpl_dir . 'woocommerce/content-single-product.php';
            if (file_exists($sp)) return $sp;
        }

        // Info pages (about, contact, faq, shipping, returns, warranty, terms)
        if (function_exists('ng_info_pages')) {
            $qv   = get_query_var('neogen_page', '');
            $pages = ng_info_pages();
            if ($qv && isset($pages[$qv])) {
                define('NG_RENDER_INFO_PAGE', $qv);
                $info = self::$tpl_dir . 'info-page.php';
                if (file_exists($info)) return $info;
            }
        }

        // 404
        if (is_404()) {
            $e404 = self::$tpl_dir . '404.php';
            if (file_exists($e404)) return $e404;
        }

        return $template;
    }
}
