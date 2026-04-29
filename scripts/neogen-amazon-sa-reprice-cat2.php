<?php
/**
 * v1.36.5 — Amazon.sa-confirmed reprice (catalog SKUs 61-120 by retail).
 *
 * Source: Playwright scrape of amazon.sa on 2026-04-29 (cat2.ndjson).
 * Filter: high confidence + price ratio in [0.4, 1.4] of current sale +
 *         brand mismatch sentinel.
 *
 * Same caveats as v1.36.2/3/4: Amazon.sa is competitor RETAIL, not cost.
 * Price-25%-above-Amazon math runs because user requested it; meta trail
 * makes the source visible for future correction.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-amazon-sa-reprice-cat2.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) { WP_CLI::error( 'WooCommerce is not loaded.' ); }

$now_iso = '2026-04-29T14:40:00+03:00';
$rows = array(
    array( 'sku' => 'NG-MKR-008', 'ref' => 1000, 'new' => 1250, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Creality-Printer-Printing-Printers-Auto-Load/dp/B0CYHHFH79/ref=sr_1_6' ),
    array( 'sku' => 'GM-HST-STS-001', 'ref' => 684, 'new' => 860, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/SteelSeries-Arctis-Wireless-Multi-Platform-Headset/dp/B0CCSHV2W8/ref=sr_1_2' ),
    array( 'sku' => 'GM-CTR-MSF-001', 'ref' => 569, 'new' => 720, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Xbox-Elite-Wireless-Controller-Core/dp/B0B9GJLV2D/ref=sr_1_1' ),
    array( 'sku' => 'GM-KBD-KEY-001', 'ref' => 549, 'new' => 690, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Keychron-Full-Size-Hall-Effect-Double-Rail-Hot-Swappable/dp/B0FX9LKQGJ/ref=sr_1_1' ),
    array( 'sku' => 'NT-WAP-UBQ-001', 'ref' => 725, 'new' => 910, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Ubiquiti-Professional-Access-Throughput-Plastic/dp/B09RGHTGBB/ref=sr_1_1' ),
    array( 'sku' => 'GM-STR-HPX-001', 'ref' => 462, 'new' => 580, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/HyperX-QuadCast-Microphone-Podcasting-Board/dp/B0F3K25XHL/ref=sr_1_3' ),
    array( 'sku' => 'NG-NET-012', 'ref' => 540, 'new' => 680, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Ubiquiti-Cloud-Gateway-Ultra-UCG-Ultra/dp/B0CWLKD9RP/ref=sr_1_1' ),
    array( 'sku' => 'NT-CBL-GEN-001', 'ref' => 592, 'new' => 740, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Cable-Matters-Listed-10Gbps-Shielded/dp/B0FHNX9TBZ/ref=sr_1_1' ),
    array( 'sku' => 'NG-GAM-007', 'ref' => 419, 'new' => 530, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Windows-Wireless-Bluetooth-Receiver-Connection/dp/B08GJC5WSS/ref=sr_1_1' ),
    array( 'sku' => 'NT-MPC-DEL-001', 'ref' => 1282, 'new' => 1610, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Dell-OptiPlex-5060-Computer-Professional/dp/B0C31DTK9C/ref=sr_1_1' ),
    array( 'sku' => 'GM-RGB-GOV-001', 'ref' => 629, 'new' => 790, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Govee-DreamView-Pro-Gaming-Light/dp/B0CCLP92ZC/ref=sr_1_3' ),
    array( 'sku' => 'SH-HUB-HASS-001', 'ref' => 707, 'new' => 890, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Assistant-Advanced-Automation-Official-Hardware/dp/B0CXVKSG19/ref=sr_1_1' ),
    array( 'sku' => 'NG-3DP-006', 'ref' => 430, 'new' => 540, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/ZILOU-direct-extruder-Printer-Extruder/dp/B0C4YQ7S8V/ref=sr_1_2' ),

);

$applied=0; $missing=0;
foreach ( $rows as $r ) {
    $pid = wc_get_product_id_by_sku( $r['sku'] );
    if ( ! $pid ) { WP_CLI::warning( "SKU not found: {$r['sku']}" ); $missing++; continue; }
    $p = wc_get_product( $pid );
    if ( ! $p instanceof WC_Product ) continue;

    $current = (float) $p->get_regular_price( 'edit' );
    $sale    = (float) $p->get_sale_price( 'edit' );

    update_post_meta( $pid, '_ng_amazon_sa_ref_price',      (string) $r['ref'] );
    update_post_meta( $pid, '_ng_amazon_sa_ref_url',        (string) $r['url'] );
    update_post_meta( $pid, '_ng_amazon_sa_ref_at',         (string) $now_iso );
    update_post_meta( $pid, '_ng_amazon_sa_ref_confidence', (string) $r['conf'] );

    if ( ! get_post_meta( $pid, '_ng_pre_reprice_at', true ) ) {
        update_post_meta( $pid, '_ng_pre_reprice_regular_price', (string) $current );
        update_post_meta( $pid, '_ng_pre_reprice_sale_price',    (string) $sale );
        update_post_meta( $pid, '_ng_pre_reprice_at',            (string) time() );
    }
    $p->set_regular_price( (string) $r['new'] );
    if ( $sale ) { $p->set_sale_price( '' ); }
    $p->save();

    WP_CLI::log( sprintf( '  %-18s  %5.0f -> %-5d  ref=%-5d  (#%d)', $r['sku'], $current, $r['new'], $r['ref'], $pid ) );
    $applied++;
}
WP_CLI::log( '=== v1.36.5 cat2 reprice ===' );
WP_CLI::log( "Applied: $applied  Missing: $missing" );
