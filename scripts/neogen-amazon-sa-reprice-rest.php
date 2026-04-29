<?php
/**
 * v1.36.3 — Amazon.sa-confirmed reprice (top-60 scrape, 13 high-confidence matches).
 *
 * Source: Playwright scrape of amazon.sa on 2026-04-29.
 * Filter: high-confidence model match AND price ratio in [0.4, 1.4] of
 * current sale (excludes accessories + wildly-different models).
 *
 * Same caveats as v1.36.2: Amazon.sa is a competitor RETAIL price, not
 * our cost. Pricing 25% above Amazon puts NeoGen above Amazon on shelf.
 * Math runs because user requested it; meta trail makes the source
 * visible for future correction once distributor quotes arrive.
 *
 * Each row stamps:
 *   _ng_amazon_sa_ref_price
 *   _ng_amazon_sa_ref_url
 *   _ng_amazon_sa_ref_at        ISO timestamp
 *   _ng_amazon_sa_ref_confidence
 *   _ng_pre_reprice_regular_price (rollback meta)
 *
 * Run via:
 *   wp eval-file /tmp/neogen-amazon-sa-reprice-rest.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) {
    WP_CLI::error( 'WooCommerce is not loaded.' );
}

$now_iso = '2026-04-29T13:45:00+03:00';
$rows = array(
    array( 'sku' => 'GM-MON-SAM-001', 'ref' => 3893, 'new' => 4870, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Samsung-Odyssey-G81SF-Gaming-Monitor/dp/B0DVBZS5WZ/ref=sr_1_1_mod_primary_new', 'note' => 'Samsung 27" Odyssey OLED G8 G81SF 4K 240' ),
    array( 'sku' => 'NG-ENT-009', 'ref' => 6899, 'new' => 8630, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/ProLiant-DL380-Server-3-20GHz-Renewed/dp/B0GW1G7H16/ref=sr_1_1', 'note' => 'HPE ProLiant DL380 Gen 10 Server,Dual In' ),
    array( 'sku' => 'GM-MON-ASU-001', 'ref' => 3259, 'new' => 4080, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/ASUS-PG27AQDM-26-5-inch-response-compatible/dp/B0BXY85B9F/ref=sr_1_1', 'note' => 'ASUS ROG Swift OLED PG27AQDM gaming moni' ),
    array( 'sku' => 'NG-3DP-003', 'ref' => 2699, 'new' => 3380, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Creality-All-Metal-Extruder-Leveling-Filaments/dp/B0DND3CJQV/ref=sr_1_2', 'note' => 'Creality K1C 3D Printer, 600mm/s High Sp' ),
    array( 'sku' => 'NT-NET-UBQ-001', 'ref' => 1889, 'new' => 2370, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Ubiquiti-Networks-USW-Pro-Max-16-PoE-180W/dp/B0D3WQVCZW/ref=sr_1_2', 'note' => 'Ubiquiti USW-Pro-Max-16-PoE (180W)' ),
    array( 'sku' => 'GM-MON-SAM-002', 'ref' => 2073, 'new' => 2600, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Samsung-Freesync-Adjustable-Equalizer-LS27DG702ENXZA/dp/B0DQQCK3VS/ref=sr_1_1', 'note' => 'Samsung 27” Odyssey G7 (G70D) 4K UHD IPS' ),
    array( 'sku' => 'NG-MKR-004', 'ref' => 2441, 'new' => 3060, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Meta-Quest-512GB-All-One/dp/B0CD1JTBSC/ref=sr_1_2', 'note' => 'Meta Quest 3— 512GB – 3-Month Trial of M' ),
    array( 'sku' => 'NG-SH-002', 'ref' => 2008, 'new' => 2520, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Ubiquiti-Networks-UVC-G4-PRO-PROTECT-G4-PRO/dp/B07R7F7KJM/ref=sr_1_1', 'note' => 'UNIFI PROTECT G4-PRO CAMERA' ),
    array( 'sku' => 'NT-FWL-NGT-002', 'ref' => 2191, 'new' => 2740, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/PfSense-Netgate-2100-Firewall-Router/dp/B0D35Y6RLG/ref=sr_1_1', 'note' => 'PfSense+ Netgate 2100 Base Firewall and ' ),
    array( 'sku' => 'NG-SEC-004', 'ref' => 2511, 'new' => 3140, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Ubiquiti-UniFi-Protect-Doorbell-UVC-G4-DoorBell-Pro/dp/B0BH3XVGK1/ref=sr_1_1', 'note' => 'Ubiquiti UniFi Protect G4 Doorbell Pro (' ),
    array( 'sku' => 'NG-3DP-002', 'ref' => 996, 'new' => 1250, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/AMS-Heater-Bambu-Lab/dp/B0FNDFPJH7/ref=sr_1_1', 'note' => 'AMS Heater for Bambu Lab AMS' ),
    array( 'sku' => 'NG-ENT-007', 'ref' => 884, 'new' => 1110, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Switch-ports-Gigabit-MikroTik-CRS326-24G-2S-RM/dp/B0747TBTDX/ref=sr_1_1', 'note' => 'Switch 24 ports Gigabit MikroTik CRS326-' ),
    array( 'sku' => 'NG-ENT-004', 'ref' => 938, 'new' => 1180, 'conf' => 'high', 'url' => 'https://www.amazon.sa/-/en/Ubiquiti-Wireless-Long-Range-Included-U6-LR-US/dp/B08V1PF29L/ref=sr_1_2', 'note' => 'Ubiquiti - UniFi 6,Wireless Long-Range A' ),

);

$applied = 0; $missing = 0; $skipped = 0;

foreach ( $rows as $r ) {
    $pid = wc_get_product_id_by_sku( $r['sku'] );
    if ( ! $pid ) { WP_CLI::warning( "SKU not found: {$r['sku']}" ); $missing++; continue; }
    $product = wc_get_product( $pid );
    if ( ! $product instanceof WC_Product ) { $skipped++; continue; }

    $current = (float) $product->get_regular_price( 'edit' );
    $sale    = (float) $product->get_sale_price( 'edit' );

    update_post_meta( $pid, '_ng_amazon_sa_ref_price',      (string) $r['ref'] );
    update_post_meta( $pid, '_ng_amazon_sa_ref_url',        (string) $r['url'] );
    update_post_meta( $pid, '_ng_amazon_sa_ref_at',         (string) $now_iso );
    update_post_meta( $pid, '_ng_amazon_sa_ref_confidence', (string) $r['conf'] );

    if ( ! get_post_meta( $pid, '_ng_pre_reprice_at', true ) ) {
        update_post_meta( $pid, '_ng_pre_reprice_regular_price', (string) $current );
        update_post_meta( $pid, '_ng_pre_reprice_sale_price',    (string) $sale );
        update_post_meta( $pid, '_ng_pre_reprice_at',            (string) time() );
    }

    $product->set_regular_price( (string) $r['new'] );
    if ( $sale ) { $product->set_sale_price( '' ); }
    $product->save();

    WP_CLI::log( sprintf( '  %-18s  %5.0f → %-5d  ref=%-5d  conf=%-6s  (#%d)', $r['sku'], $current, $r['new'], $r['ref'], $r['conf'], $pid ) );
    $applied++;
}

WP_CLI::log( '=== v1.36.3 Amazon.sa reprice ===' );
WP_CLI::log( "Applied: $applied" );
WP_CLI::log( "Missing: $missing" );
WP_CLI::log( "Skipped: $skipped" );
