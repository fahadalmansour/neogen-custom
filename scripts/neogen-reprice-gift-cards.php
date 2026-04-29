<?php
/**
 * v1.35.0 gift-card reprice — apply 20% gross-margin floor to every
 * product with `_ng_gift_card_denom` post meta.
 *
 * Source-of-truth formula:
 *   landed_SAR = denom_USD * 3.75    (FX, no freight/duty/VAT — digital codes)
 *   new_sale   = ceil( (landed / 0.80) / 5 ) * 5   (20% margin, round up to 5 SAR)
 *
 * The original bulk seeder (scripts/neogen-gift-cards-bulk.php) used
 * a 7% markup (`* 1.07`). This script lifts that to 20% gross margin.
 *
 * For diamond/UC games where _ng_gift_card_denom is the SAR floor
 * (already a SAR figure, not USD), we treat denom as cost-SAR and
 * apply the same /0.80 lift.
 *
 * Backup: stamps `_ng_pre_reprice_regular_price` + `_ng_pre_reprice_at`
 * the same way the catalog reprice does, for parallel rollback.
 *
 * Idempotent: rerunning detects backup already in place and uses the
 * original-source price, not the already-uplifted one — so rerunning
 * does NOT keep stacking 25% increments.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) {
    WP_CLI::error( 'WooCommerce is not loaded.' );
}

global $wpdb;
$rows = $wpdb->get_results(
    "SELECT pm.post_id, pm.meta_value AS denom
       FROM {$wpdb->postmeta} pm
       JOIN {$wpdb->posts} p ON p.ID = pm.post_id
      WHERE pm.meta_key = '_ng_gift_card_denom'
        AND p.post_type = 'product'
        AND p.post_status = 'publish'"
);

$now = time();
$applied = 0;
$identical = 0;
$skipped = 0;
$movers = array();

foreach ( $rows as $r ) {
    $product_id = (int) $r->post_id;
    $denom      = (float) $r->denom;
    if ( $denom <= 0 ) { $skipped++; continue; }

    $product = wc_get_product( $product_id );
    if ( ! $product instanceof WC_Product ) { $skipped++; continue; }

    // Decide cost basis: denoms < 200 are USD, ≥ 200 are SAR floors
    // (UC / diamonds / Roblox / PUBG packs that ship with denom in SAR).
    $cost_sar = ( $denom < 200 ) ? ( $denom * 3.75 ) : $denom;

    // Round up to nearest 5 SAR for clean retail.
    $new_sale = (int) ( ceil( ( $cost_sar / 0.80 ) / 5 ) * 5 );

    $current_regular = (float) $product->get_regular_price( 'edit' );
    $current_sale    = (float) $product->get_sale_price( 'edit' );

    if ( (int) $current_regular === $new_sale ) {
        $identical++;
        continue;
    }

    if ( ! get_post_meta( $product_id, '_ng_pre_reprice_at', true ) ) {
        update_post_meta( $product_id, '_ng_pre_reprice_regular_price', (string) $current_regular );
        update_post_meta( $product_id, '_ng_pre_reprice_sale_price',    (string) $current_sale );
        update_post_meta( $product_id, '_ng_pre_reprice_at',            (string) $now );
    }

    $product->set_regular_price( (string) $new_sale );
    if ( $current_sale ) { $product->set_sale_price( '' ); }
    $product->save();

    $movers[] = sprintf( '  #%-5d denom=%-5s  %6.0f → %-6d', $product_id, (string) $denom, $current_regular, $new_sale );
    $applied++;
}

WP_CLI::log( '=== v1.35.0 gift-card reprice (20% margin floor) ===' );
WP_CLI::log( "Total gift-card products: " . count( $rows ) );
WP_CLI::log( "Applied:                  $applied" );
WP_CLI::log( "Already at target:        $identical" );
WP_CLI::log( "Skipped (no denom/zero):  $skipped" );
WP_CLI::log( '---' );
foreach ( array_slice( $movers, 0, 25 ) as $m ) { WP_CLI::log( $m ); }
if ( count( $movers ) > 25 ) { WP_CLI::log( sprintf( '  ... +%d more', count( $movers ) - 25 ) ); }
