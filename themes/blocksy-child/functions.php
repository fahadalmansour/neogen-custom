<?php
/**
 * NeoGen Child theme — bootstrap.
 *
 * Phase 1 of the Blocksy-editor migration. Does ONLY the minimum
 * required for a valid, activatable child theme:
 *   - enqueues the parent (Blocksy) stylesheet so its base styles load
 *   - enqueues this child's style.css after the parent
 *
 * It deliberately does NOT touch any of the mu-plugin hooks in
 * mu-plugins/neogen-custom/ — header, footer, sysbar, front-page,
 * WooCommerce overlays, schema, and CSS/JS continue to flow through
 * those hooks exactly as before. Activating this child should be a
 * no-op visually.
 *
 * Subsequent phases (2 and 3) will progressively un-hook those mu-plugin
 * surfaces and rebuild them as Blocksy header/footer builder rows,
 * theme.json presets, and child-theme template overrides — at which
 * point the Blocksy Customizer / Site Editor becomes useful.
 */

defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    $parent = wp_get_theme(get_template());
    wp_enqueue_style(
        'blocksy-parent',
        get_template_directory_uri() . '/style.css',
        [],
        $parent ? $parent->get('Version') : null
    );

    $child = wp_get_theme();
    wp_enqueue_style(
        'neogen-child',
        get_stylesheet_uri(),
        ['blocksy-parent'],
        $child ? $child->get('Version') : null
    );
}, 9);
