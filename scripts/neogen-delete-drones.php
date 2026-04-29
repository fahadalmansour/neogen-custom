<?php
/**
 * v1.36.7 — delete every product in `drones-robotics` (term 139,
 * "Drones & Robotics | درونز وروبوتات", 8 products at runtime).
 *
 * Force-deletes each product (skip trash) so they don't reappear in
 * admin lists, then drops the term itself so the category is fully gone.
 *
 * Idempotent. Mirrors scripts/neogen-delete-netflix.php pattern.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) { WP_CLI::error( 'WC not loaded' ); }

$term = get_term_by( 'slug', 'drones-robotics', 'product_cat' );
if ( ! $term || is_wp_error( $term ) ) {
    WP_CLI::log( 'Term `drones-robotics` not present (already removed).' );
    return;
}
WP_CLI::log( "=== v1.36.7 delete drones-robotics ===" );

$pids = get_posts( array(
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
    'tax_query'      => array(
        array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => array( (int) $term->term_id ),
            'include_children' => true,
        ),
    ),
) );

WP_CLI::log( 'Products in category: ' . count( $pids ) );
$deleted = 0;
foreach ( $pids as $pid ) {
    $title = get_the_title( $pid );
    $r = wp_delete_post( (int) $pid, true );
    if ( $r ) { WP_CLI::log( "  DELETED #$pid  $title" ); $deleted++; }
    else      { WP_CLI::warning( "  failed #$pid  $title" ); }
}
WP_CLI::log( "Products deleted: $deleted" );

// Drop the term + any sub-cats (include_children=true above already
// covered descendants in the product list; here we delete the parent term).
$r = wp_delete_term( $term->term_id, 'product_cat' );
if ( is_wp_error( $r ) ) { WP_CLI::warning( 'wp_delete_term failed: ' . $r->get_error_message() ); }
else                     { WP_CLI::log( 'Term drones-robotics removed.' ); }
