<?php
/**
 * v1.35.0 reprice — apply 20% gross-margin floor across the catalog.
 *
 * Reads scripts/reprice_floor_20pct.csv (210 rows) and for every row
 * marked action=RAISE, updates the product's regular_price + price to
 * the new_sale value. Healthy SKUs (action=KEEP) are skipped.
 *
 * Backup safety net: before changing any price, the previous
 * regular_price + sale_price are stamped into post meta keys
 *   _ng_pre_reprice_regular_price
 *   _ng_pre_reprice_sale_price
 *   _ng_pre_reprice_at  (unix ts)
 * so a rollback is `wp post meta update <id> _regular_price <backup>`.
 *
 * IMPORTANT — landed costs in the CSV are ESTIMATES from
 * NeoGen_Supplier_Research_v2.xlsx, not confirmed quotes. New retail
 * prices may exceed Amazon.sa for some SKUs; review the CSV before
 * trusting any volume forecast.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-reprice-bulk.php --skip-plugins=litespeed-cache --user=1
 *
 * Idempotent: re-running with the same CSV is a no-op for already-
 * priced SKUs (the new_sale matches existing _regular_price).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) {
    WP_CLI::error( 'WooCommerce is not loaded.' );
}

$csv_path = __DIR__ . '/reprice_floor_20pct.csv';
if ( ! file_exists( $csv_path ) ) {
    // Fallback: when invoked via wp eval-file the script is copied to /tmp
    // and the CSV is alongside.
    $csv_path = '/tmp/reprice_floor_20pct.csv';
}
if ( ! file_exists( $csv_path ) ) {
    WP_CLI::error( "CSV not found at {$csv_path}" );
}

$fh = fopen( $csv_path, 'r' );
$header = fgetcsv( $fh );
$idx = array_flip( $header );

$now = time();
$applied = 0;
$skipped_keep = 0;
$missing = 0;
$identical = 0;
$movers = array();

while ( ( $row = fgetcsv( $fh ) ) !== false ) {
    $sku    = $row[ $idx['sku'] ];
    $action = $row[ $idx['action'] ];
    $new    = (int) $row[ $idx['new_sale'] ];

    if ( strpos( $action, 'RAISE' ) === false && strpos( $action, 'LOWER' ) === false ) {
        $skipped_keep++;
        continue;
    }
    $product_id = wc_get_product_id_by_sku( $sku );
    if ( ! $product_id ) {
        $missing++;
        continue;
    }
    $product = wc_get_product( $product_id );
    if ( ! $product instanceof WC_Product ) { $missing++; continue; }

    $current_regular = (float) $product->get_regular_price( 'edit' );
    $current_sale    = (float) $product->get_sale_price( 'edit' );

    if ( (int) $current_regular === $new ) {
        $identical++;
        continue;
    }

    // Backup once.
    if ( ! get_post_meta( $product_id, '_ng_pre_reprice_at', true ) ) {
        update_post_meta( $product_id, '_ng_pre_reprice_regular_price', (string) $current_regular );
        update_post_meta( $product_id, '_ng_pre_reprice_sale_price',    (string) $current_sale );
        update_post_meta( $product_id, '_ng_pre_reprice_at',            (string) $now );
    }

    $product->set_regular_price( (string) $new );
    // Drop any existing sale price — repricing baseline is the new "regular".
    if ( $current_sale ) {
        $product->set_sale_price( '' );
    }
    $product->save();

    $movers[] = sprintf( '  %-20s  %6d → %-6d  (%s)', $sku, (int) $current_regular, $new, $action );
    $applied++;
}
fclose( $fh );

WP_CLI::log( '=== v1.35.0 reprice (20% margin floor) ===' );
WP_CLI::log( "Applied:           $applied" );
WP_CLI::log( "Already at target: $identical" );
WP_CLI::log( "Kept (healthy):    $skipped_keep" );
WP_CLI::log( "SKU not in store:  $missing" );
WP_CLI::log( '---' );
foreach ( array_slice( $movers, 0, 20 ) as $m ) { WP_CLI::log( $m ); }
if ( count( $movers ) > 20 ) { WP_CLI::log( sprintf( '  ... +%d more', count( $movers ) - 20 ) ); }
