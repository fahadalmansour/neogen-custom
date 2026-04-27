<?php
/**
 * Snippet: A/B Example — Hero Headline (INERT demo)
 * Auto-loaded by plugins/neogen-snippets/neogen-snippets.php
 * Toggle via WP admin → Plugins → NeoGen Snippets.
 *
 * Reference implementation for the NeoGen A/B primitive (mu-plugins/neogen-ab.php).
 * Ships DISABLED. Flip NEOGEN_AB_EXAMPLE_ENABLED to true only after:
 *   1. You've defined a real experiment_key and variants.
 *   2. You've decided whether consent is required (PDPL/GDPR — see primitive docs).
 *   3. You've wired a real conversion trigger (the demo doesn't track conversion).
 */

defined('ABSPATH') || exit;

const NEOGEN_AB_EXAMPLE_ENABLED = false;

if (!NEOGEN_AB_EXAMPLE_ENABLED) {
    return;
}

if (!function_exists('neogen_ab_bucket')) {
    // Primitive not loaded — fail silently rather than break the page.
    return;
}

// Pattern: bucket → expose → render variant.
add_filter('the_title', function ($title, $post_id) {
    // Only target the homepage title, as a demo.
    if (!is_front_page() || !in_the_loop()) return $title;

    $variant = neogen_ab_bucket('hero_headline_v1', ['control', 'treatment']);
    neogen_ab_expose('hero_headline_v1', $variant);

    if ($variant === 'treatment') {
        return 'NeoGen — Smart Homes, Done Right';
    }
    return $title; // control
}, 10, 2);

// Conversion pattern (add to wherever the win happens — checkout complete, signup, etc):
//
//   add_action('woocommerce_thankyou', function () {
//       $variant = neogen_ab_bucket('hero_headline_v1', ['control', 'treatment']);
//       neogen_ab_convert('hero_headline_v1', $variant, ['source' => 'thankyou']);
//   });
