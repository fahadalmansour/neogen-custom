<?php
/**
 * v1.36.1 — roll back the v1.35.0 reprice.
 *
 * Restores every product where _ng_pre_reprice_regular_price exists.
 * After restoring, deletes the backup meta so the rollback can't be
 * re-applied accidentally on a subsequent run.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-reprice-rollback.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) {
    WP_CLI::error( 'WooCommerce is not loaded.' );
}

global $wpdb;
$rows = $wpdb->get_results(
    "SELECT post_id, meta_value AS prev_regular
       FROM {$wpdb->postmeta}
      WHERE meta_key = '_ng_pre_reprice_regular_price'"
);

WP_CLI::log( '=== v1.36.1 reprice rollback ===' );
WP_CLI::log( 'Backup rows found: ' . count( $rows ) );

$restored = 0;
$skipped  = 0;
$movers   = array();

foreach ( $rows as $r ) {
    $pid = (int) $r->post_id;
    $product = wc_get_product( $pid );
    if ( ! $product instanceof WC_Product ) { $skipped++; continue; }

    $prev_regular = (string) $r->prev_regular;
    $prev_sale    = (string) get_post_meta( $pid, '_ng_pre_reprice_sale_price', true );
    $current      = (float) $product->get_regular_price( 'edit' );

    $product->set_regular_price( $prev_regular );
    if ( $prev_sale !== '' ) {
        $product->set_sale_price( $prev_sale );
    } else {
        $product->set_sale_price( '' );
    }
    $product->save();

    delete_post_meta( $pid, '_ng_pre_reprice_regular_price' );
    delete_post_meta( $pid, '_ng_pre_reprice_sale_price' );
    delete_post_meta( $pid, '_ng_pre_reprice_at' );

    $movers[] = sprintf( '  #%-5d  %6.0f → %s', $pid, $current, $prev_regular );
    $restored++;
}

WP_CLI::log( "Restored: $restored" );
WP_CLI::log( "Skipped:  $skipped" );
WP_CLI::log( '---' );
foreach ( array_slice( $movers, 0, 20 ) as $m ) { WP_CLI::log( $m ); }
if ( count( $movers ) > 20 ) { WP_CLI::log( sprintf( '  ... +%d more', count( $movers ) - 20 ) ); }
