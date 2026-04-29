<?php
/**
 * v1.35.2 — delete the Netflix gift-card line entirely.
 *
 * Removes:
 *   - Every product with _ng_gift_card_brand = 'netflix' (force-delete,
 *     skip trash so they don't keep showing up in admin).
 *   - The `netflix` product_cat term itself (under subscriptions).
 *
 * Idempotent: safe to rerun — finds nothing on the second pass.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-delete-netflix.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) {
    WP_CLI::error( 'WooCommerce is not loaded.' );
}

global $wpdb;

// 1) Find all Netflix products.
$pids = $wpdb->get_col(
    "SELECT post_id FROM {$wpdb->postmeta}
      WHERE meta_key = '_ng_gift_card_brand'
        AND meta_value = 'netflix'"
);

WP_CLI::log( '=== v1.35.2 delete Netflix gift-cards ===' );
WP_CLI::log( 'Found ' . count( $pids ) . ' Netflix products.' );

$deleted = 0;
foreach ( $pids as $pid ) {
    $pid = (int) $pid;
    $p   = get_post( $pid );
    if ( ! $p || $p->post_type !== 'product' ) { continue; }
    $title = $p->post_title;
    $r = wp_delete_post( $pid, true ); // force delete
    if ( $r ) {
        WP_CLI::log( "  DELETED #$pid  $title" );
        $deleted++;
    } else {
        WP_CLI::warning( "  failed #$pid  $title" );
    }
}
WP_CLI::log( "Products deleted: $deleted" );

// 2) Drop the netflix product_cat term so it stops appearing in nav and brand grid.
$term = get_term_by( 'slug', 'netflix', 'product_cat' );
if ( $term && ! is_wp_error( $term ) ) {
    $r = wp_delete_term( $term->term_id, 'product_cat' );
    if ( is_wp_error( $r ) ) {
        WP_CLI::warning( 'wp_delete_term failed: ' . $r->get_error_message() );
    } else {
        WP_CLI::log( 'Term "netflix" removed.' );
    }
} else {
    WP_CLI::log( 'Term "netflix" was not present (already removed).' );
}

// 3) Refresh subscriptions parent term count so the lane number is honest.
$subs = get_term_by( 'slug', 'subscriptions', 'product_cat' );
if ( $subs && ! is_wp_error( $subs ) ) {
    wp_update_term_count_now( array( $subs->term_id ), 'product_cat' );
    WP_CLI::log( 'Subscriptions term count refreshed.' );
}
