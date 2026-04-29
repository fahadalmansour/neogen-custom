<?php
/**
 * v1.36.2 — confirmed-only reprice from Amazon.sa references (5 SKUs).
 *
 * For each row below, this script:
 *   1. Stamps the Amazon.sa reference price + URL + capture-timestamp into
 *      post meta so the source is traceable forever.
 *   2. Backs up the current price into _ng_pre_reprice_regular_price /
 *      _ng_pre_reprice_at (same convention as v1.35.0 / v1.36.1) so the
 *      rollback script `neogen-reprice-rollback.php` works on this run too.
 *   3. Sets the new regular price.
 *
 * IMPORTANT: Amazon.sa is a competitor RETAIL price, not our cost. The
 * "20% margin" math here is therefore "price = Amazon × 1.25" which puts
 * NeoGen 25% ABOVE Amazon on shelf — these SKUs will not move at this
 * price unless paired with a real distributor relationship that lowers
 * actual COGS. The reprice still ships per user request, but the meta
 * trail makes the source visible so future review can correct.
 *
 * Capture context:
 *   Date: 2026-04-29
 *   Method: Playwright headless Chromium against amazon.sa search,
 *           first organic result that exact-matches the SKU model.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-amazon-sa-reprice.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) {
    WP_CLI::error( 'WooCommerce is not loaded.' );
}

// SKU → [ref_price_sar, new_sale_sar, ref_url, capture_iso].
// new_sale = ceil(ref / 0.80 / 10) * 10  (20% margin floor, rounded up to 10 SAR).
$now_iso = '2026-04-29T12:00:00+03:00';
$catalog = array(
    'NG-ENT-003'     => array( 'ref' => 2054, 'new' => 2570, 'url' => 'https://www.amazon.sa/-/en/Ubiquiti-Networks-Touchscreen-Enterprise-Cortex-A57/dp/B086967C9X' ),
    'NG-ACC-008'     => array( 'ref' => 1730, 'new' => 2170, 'url' => 'https://www.amazon.sa/-/en/CalDigit-TS4-Thunderbolt-Dock-USB/dp/B09GK8LBWS' ),
    'GM-CHR-SEC-001' => array( 'ref' => 3699, 'new' => 4630, 'url' => 'https://www.amazon.sa/-/en/Secretlab-Titan-Stealth-Gaming-Chair/dp/B0B3RDWTDD' ),
);

// Gift-cards: matched by brand + denom + region (region match is approximate,
// since the Amazon listing is "KSA digital" for PSN $50 and a 3rd-party
// Steam $50 with no region tag).
$giftcards = array(
    array( 'brand' => 'playstation', 'denom' => 50, 'region' => 'ksa', 'ref' => 189, 'new' => 240,
           'url' => 'https://www.amazon.sa/-/en/PlayStation-Network-Card-Account-Digital/dp/B0CHSK2SB8' ),
    array( 'brand' => 'steam',       'denom' => 50, 'region' => null,  'ref' => 177, 'new' => 225,
           'url' => 'https://www.amazon.sa/-/en/GADGET-UPGRADE-gift-card-steam/dp/B00QZLVCU0' ),
);

$applied = 0;
$missing = 0;

function ng_apply_reprice( $product_id, $ref, $new, $url, $now_iso ) {
    $product = wc_get_product( $product_id );
    if ( ! $product instanceof WC_Product ) { return false; }
    $current = (float) $product->get_regular_price( 'edit' );
    $sale    = (float) $product->get_sale_price( 'edit' );

    update_post_meta( $product_id, '_ng_amazon_sa_ref_price', (string) $ref );
    update_post_meta( $product_id, '_ng_amazon_sa_ref_url',   (string) $url );
    update_post_meta( $product_id, '_ng_amazon_sa_ref_at',    (string) $now_iso );

    if ( ! get_post_meta( $product_id, '_ng_pre_reprice_at', true ) ) {
        update_post_meta( $product_id, '_ng_pre_reprice_regular_price', (string) $current );
        update_post_meta( $product_id, '_ng_pre_reprice_sale_price',    (string) $sale );
        update_post_meta( $product_id, '_ng_pre_reprice_at',            (string) time() );
    }

    $product->set_regular_price( (string) $new );
    if ( $sale ) { $product->set_sale_price( '' ); }
    $product->save();
    return true;
}

WP_CLI::log( '=== v1.36.2 Amazon.sa-confirmed reprice ===' );

foreach ( $catalog as $sku => $row ) {
    $pid = wc_get_product_id_by_sku( $sku );
    if ( ! $pid ) {
        WP_CLI::warning( "SKU not found: $sku" );
        $missing++;
        continue;
    }
    if ( ng_apply_reprice( $pid, $row['ref'], $row['new'], $row['url'], $now_iso ) ) {
        WP_CLI::log( sprintf( '  %-20s  ref=%-5d  new=%-5d  (#%d)', $sku, $row['ref'], $row['new'], $pid ) );
        $applied++;
    }
}

global $wpdb;
foreach ( $giftcards as $g ) {
    $sql = $wpdb->prepare(
        "SELECT pm.post_id FROM {$wpdb->postmeta} pm
          JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = pm.post_id AND pm2.meta_key = '_ng_gift_card_denom'
          " . ( $g['region'] ? "JOIN {$wpdb->postmeta} pm3 ON pm3.post_id = pm.post_id AND pm3.meta_key = '_ng_gift_card_region'" : '' ) . "
         WHERE pm.meta_key = '_ng_gift_card_brand' AND pm.meta_value = %s
           AND pm2.meta_value = %s
           " . ( $g['region'] ? "AND pm3.meta_value = %s" : '' ),
        ...( $g['region'] ? array( $g['brand'], (string) $g['denom'], $g['region'] ) : array( $g['brand'], (string) $g['denom'] ) )
    );
    $pids = $wpdb->get_col( $sql );
    if ( empty( $pids ) ) {
        WP_CLI::warning( "No product found: brand={$g['brand']} denom={$g['denom']} region=" . ( $g['region'] ?? 'any' ) );
        $missing++;
        continue;
    }
    foreach ( $pids as $pid ) {
        if ( ng_apply_reprice( (int) $pid, $g['ref'], $g['new'], $g['url'], $now_iso ) ) {
            WP_CLI::log( sprintf( '  GC %-12s d=%-3d r=%-6s ref=%-4d new=%-4d (#%d)',
                $g['brand'], $g['denom'], ( $g['region'] ?? 'any' ), $g['ref'], $g['new'], $pid ) );
            $applied++;
        }
    }
}

WP_CLI::log( "Applied: $applied" );
WP_CLI::log( "Missing: $missing" );
