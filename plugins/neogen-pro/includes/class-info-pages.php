<?php
/**
 * NeoGen Pro — Info page routing.
 * Registers ?neogen_page=X query var and routes it to the
 * info-page.php template via template_include.
 */

defined('ABSPATH') || exit;

class NeoGen_Pro_Info_Pages {

    public static function init(): void {
        add_filter('query_vars', [self::class, 'add_query_var']);
        add_action('init',       [self::class, 'add_rewrite_rules']);
    }

    public static function add_query_var(array $vars): array {
        $vars[] = 'neogen_page';
        return $vars;
    }

    public static function add_rewrite_rules(): void {
        if (!function_exists('ng_info_pages')) return;

        foreach (array_keys(ng_info_pages()) as $slug) {
            add_rewrite_rule(
                '^' . preg_quote($slug, '/') . '/?$',
                'index.php?neogen_page=' . $slug,
                'top'
            );
        }

        // Also register /pricing/ and /services/ pointing to the pricing page
        add_rewrite_rule('^pricing/?$',  'index.php?neogen_page=pricing',  'top');
        add_rewrite_rule('^services/?$', 'index.php?neogen_page=services', 'top');
    }
}
