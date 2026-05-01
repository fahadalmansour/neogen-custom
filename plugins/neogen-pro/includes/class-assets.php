<?php
/**
 * NeoGen Pro — Asset enqueuing.
 * Enqueues brand fonts and the main neogen.css from the mu-plugin layer.
 * Falls back gracefully if the mu-plugin CSS is already enqueued.
 */

defined('ABSPATH') || exit;

class NeoGen_Pro_Assets {

    public static function init(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 20);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin'], 20);
    }

    public static function enqueue(): void {
        // Brand fonts — only if not already registered by mu-plugin
        if (!wp_style_is('neogen-fonts', 'registered')) {
            wp_enqueue_style(
                'neogen-fonts',
                'https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Manrope:wght@400;500;600;700;800&family=Tajawal:wght@300;400;500;700&family=Reem+Kufi:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap',
                [],
                null
            );
        }

        // Main brand CSS — dequeue any old handle then re-enqueue from pro
        if (!wp_style_is('neogen-theme', 'enqueued')) {
            $mu_css = trailingslashit(WPMU_PLUGIN_DIR) . 'neogen-theme-assets/neogen.css';
            if (file_exists($mu_css)) {
                wp_enqueue_style(
                    'neogen-theme',
                    trailingslashit(WPMU_PLUGIN_URL) . 'neogen-theme-assets/neogen.css',
                    ['neogen-fonts'],
                    filemtime($mu_css)
                );
            }
        }
    }

    public static function enqueue_admin(): void {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'neogen-pro') === false) return;

        wp_enqueue_style(
            'neogen-pro-admin',
            NEOGEN_PRO_URL . 'admin/admin.css',
            [],
            NEOGEN_PRO_VERSION
        );
    }
}
