<?php
/**
 * v1.36.8 — seed 6 home-robot draft products under smart-home.
 *
 * Categories user asked for: window robots, garden/lawn robots, pool
 * robots. None of these existed in the catalog. Stubs are created with
 * post_status=draft so they don't go live — admin reviews + adds real
 * pricing/imagery in wp-admin before publishing.
 *
 * Each draft carries:
 *   - smart-home product_cat
 *   - realistic SKU + AR title
 *   - placeholder retail SAR (mid-tier benchmark; flagged TBD in body)
 *   - _ng_ar_title meta so the front-page card uses the right line
 *
 * Idempotent: lookup by SKU before insert.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-add-home-robots.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) { WP_CLI::error( 'WC not loaded' ); }

$cat = get_term_by( 'slug', 'smart-home', 'product_cat' );
if ( ! $cat ) { WP_CLI::error( 'smart-home term missing' ); }
$cat_id = (int) $cat->term_id;

$drafts = array(
    // Window-cleaning robots
    array(
        'sku'        => 'SH-WIN-ECVX-001',
        'title_en'   => 'Ecovacs WINBOT W2 OMNI - Robotic Window Cleaner',
        'title_ar'   => 'إيكوفاكس WINBOT W2 OMNI · روبوت تنظيف زجاج',
        'price'      => 2299,
        'short_desc' => 'Suction-mounted window-cleaning robot with HEPA filter and dual brushless fans. Cleans interior + exterior glass, skylights, mirrors. KSA-friendly: works in heat, with safety tether.',
    ),
    array(
        'sku'        => 'SH-WIN-HBT-001',
        'title_en'   => 'HOBOT-R3 - Window Cleaning Robot',
        'title_ar'   => 'HOBOT R3 · روبوت تنظيف نوافذ',
        'price'      => 1599,
        'short_desc' => 'Compact window-cleaning robot, app-controlled, ultrasonic spray, climbs vertical glass and tilted skylights.',
    ),

    // Smart garden / lawn robots
    array(
        'sku'        => 'SH-LWN-MAM-001',
        'title_en'   => 'Mammotion LUBA 2 AWD 5000H - Wireless Robotic Lawn Mower',
        'title_ar'   => 'ماموشن لوبا 2 · جزّازة عشب آلية بلا أسلاك',
        'price'      => 14999,
        'short_desc' => 'Boundary-wire-free RTK navigation, 4WD chassis for slopes up to 80%, mows up to 5,000 m². Ideal for villa lawns in Riyadh / Jeddah compounds.',
    ),
    array(
        'sku'        => 'SH-LWN-WRX-001',
        'title_en'   => 'Worx Landroid Vision M600 - Camera-Guided Robot Mower',
        'title_ar'   => 'ووركس لاندرويد فيجن M600 · جزّازة عشب ذكية بكاميرا',
        'price'      => 6999,
        'short_desc' => 'Camera-guided lawn mower, no boundary wire required, AI-driven obstacle detection, mows up to 600 m². Quiet operation suited for villa gardens.',
    ),

    // Swimming pool robots
    array(
        'sku'        => 'SH-POL-BBT-001',
        'title_en'   => 'Beatbot AquaSense Pro - Cordless Pool Cleaning Robot',
        'title_ar'   => 'بيت بوت أكواسنس برو · روبوت تنظيف مسابح لاسلكي',
        'price'      => 7499,
        'short_desc' => 'Cordless robotic pool cleaner with surface-skimming, wall + floor scrubbing, 5-in-1 cleaning modes, 10-hour battery, app + voice control.',
    ),
    array(
        'sku'        => 'SH-POL-DOL-001',
        'title_en'   => 'Maytronics Dolphin Premier - Robotic Pool Cleaner',
        'title_ar'   => 'مايترونيكس دولفين بريمير · روبوت تنظيف مسابح',
        'price'      => 5999,
        'short_desc' => 'Multi-layer fine + ultra-fine filter cartridges, climbs walls + waterline, suitable for pools up to 15m, weekly programmable cycles.',
    ),
);

WP_CLI::log( '=== v1.36.8 home-robot drafts ===' );
$created = 0; $existing = 0;
foreach ( $drafts as $d ) {
    $existing_pid = wc_get_product_id_by_sku( $d['sku'] );
    if ( $existing_pid ) {
        WP_CLI::log( "  EXISTS  {$d['sku']}  (#$existing_pid)" );
        $existing++;
        continue;
    }
    $product = new WC_Product_Simple();
    $product->set_name( $d['title_en'] );
    $product->set_status( 'draft' );
    $product->set_sku( $d['sku'] );
    $product->set_regular_price( (string) $d['price'] );
    $product->set_short_description( '<p>' . esc_html( $d['short_desc'] ) . '</p><p><strong>السعر النهائي قيد التأكيد بعد ربط مزوّد التوزيع.</strong> Pricing TBD pending authorized distributor quote.</p>' );
    $product->set_category_ids( array( $cat_id ) );
    $product->set_manage_stock( false );
    $product->set_stock_status( 'instock' );
    $pid = $product->save();
    update_post_meta( $pid, '_ng_ar_title', $d['title_ar'] );
    update_post_meta( $pid, '_ng_pricing_status', 'tbd' );
    update_post_meta( $pid, '_ng_seed_origin', 'home-robots-v1.36.8' );

    WP_CLI::log( sprintf( '  CREATED #%d  %s', $pid, $d['sku'] ) );
    $created++;
}
WP_CLI::log( "Created: $created   Existing: $existing" );
WP_CLI::log( 'All saved as DRAFT — review in wp-admin → Products → All (filter: Drafts) before publishing.' );
