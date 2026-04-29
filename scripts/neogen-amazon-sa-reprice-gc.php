<?php
/**
 * v1.36.4 — Amazon.sa-confirmed gift-card reprice (5 SKUs).
 *
 * Source: Playwright scrape of amazon.sa on 2026-04-29.
 * Filter: brand keyword + denom literal in title + USD->SAR sanity
 * (Amazon ref must be within [0.5x, 2.0x] of denom * 3.75 to rule out
 *  face-value Xbox/PSN cards being mistaken for USD-denominated ones).
 *
 * Keys stamped per product:
 *   _ng_amazon_sa_ref_price | _ng_amazon_sa_ref_url
 *   _ng_amazon_sa_ref_at    | _ng_amazon_sa_ref_confidence='high'
 *   _ng_pre_reprice_*       (rollback meta)
 *
 * Run via:
 *   wp eval-file /tmp/neogen-amazon-sa-reprice-gc.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) { WP_CLI::error( 'WooCommerce is not loaded.' ); }

$now_iso = '2026-04-29T14:15:00+03:00';
$rows = array(
    array( 'pid' => 490, 'brand' => 'playstation', 'denom' => 10, 'region' => 'ksa', 'ref' => 38, 'new' => 50, 'url' => 'https://www.amazon.sa/-/en/PlayStation-Network-Card-Account-Digital/dp/B0CHSK46HQ/ref=sr_1_1' ),
    array( 'pid' => 493, 'brand' => 'playstation', 'denom' => 100, 'region' => 'ksa', 'ref' => 375, 'new' => 470, 'url' => 'https://www.amazon.sa/-/en/PlayStation-Network-Card-Account-Digital/dp/B0CHSK993J/ref=sr_1_1' ),
    array( 'pid' => 512, 'brand' => 'razer-gold', 'denom' => 10, 'region' => 'global', 'ref' => 37, 'new' => 50, 'url' => 'https://www.amazon.sa/-/en/Razer-Gold-Global-Digital-Code/dp/B0CPSDK6ZV/ref=sr_1_1' ),
    array( 'pid' => 514, 'brand' => 'razer-gold', 'denom' => 50, 'region' => 'global', 'ref' => 188, 'new' => 235, 'url' => 'https://www.amazon.sa/-/en/Razer-Gold-Global-Digital-Code/dp/B0CPSMTCPY/ref=sr_1_1' ),
    array( 'pid' => 515, 'brand' => 'razer-gold', 'denom' => 100, 'region' => 'global', 'ref' => 375, 'new' => 470, 'url' => 'https://www.amazon.sa/-/en/Razer-Gold-Global-Digital-Code/dp/B0CPSBG7JH/ref=sr_1_1' ),

);

$applied=0; $missing=0; $skipped=0;
foreach ( $rows as $r ) {
    $product = wc_get_product( $r['pid'] );
    if ( ! $product instanceof WC_Product ) { WP_CLI::warning("PID {$r['pid']} not a product"); $missing++; continue; }
    $current = (float) $product->get_regular_price( 'edit' );
    $sale    = (float) $product->get_sale_price( 'edit' );

    update_post_meta( $r['pid'], '_ng_amazon_sa_ref_price',      (string) $r['ref'] );
    update_post_meta( $r['pid'], '_ng_amazon_sa_ref_url',        (string) $r['url'] );
    update_post_meta( $r['pid'], '_ng_amazon_sa_ref_at',         (string) $now_iso );
    update_post_meta( $r['pid'], '_ng_amazon_sa_ref_confidence', 'high' );

    if ( ! get_post_meta( $r['pid'], '_ng_pre_reprice_at', true ) ) {
        update_post_meta( $r['pid'], '_ng_pre_reprice_regular_price', (string) $current );
        update_post_meta( $r['pid'], '_ng_pre_reprice_sale_price',    (string) $sale );
        update_post_meta( $r['pid'], '_ng_pre_reprice_at',            (string) time() );
    }
    $product->set_regular_price( (string) $r['new'] );
    if ( $sale ) { $product->set_sale_price( '' ); }
    $product->save();

    WP_CLI::log( sprintf( '  #%-5d %-14s $%-3d %-7s %5.0f -> %-5d ref=%-5d', $r['pid'], $r['brand'], $r['denom'], $r['region'], $current, $r['new'], $r['ref'] ) );
    $applied++;
}
WP_CLI::log( '=== v1.36.4 GC reprice ===' );
WP_CLI::log( "Applied: $applied  Missing: $missing  Skipped: $skipped" );
