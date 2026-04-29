<?php
/** v1.36.6 — Amazon.sa-confirmed reprice (cat 121-180 tier, 3 SKUs).
 *  Match rate dropped at lower retail tiers (3/60 vs 13/60 in higher tiers)
 *  — diminishing returns confirmed. This run closes the catalog scraping.
 *  Run via: wp eval-file /tmp/neogen-amazon-sa-reprice-cat3.php --skip-plugins=litespeed-cache --user=1
 */
if ( ! defined('ABSPATH') ) { exit; }
if ( ! function_exists('wc_get_product') ) { WP_CLI::error('WC not loaded'); }
$now_iso='2026-04-29T15:00:00+03:00';
$rows=array(
    array( 'sku'=>'NG-GAM-001', 'ref'=>140, 'new'=>180, 'url'=>'https://www.amazon.sa/-/en/Joysticks-Bluetooth-Controller-Remappable-Vibration/dp/B0DLG23TQZ/ref=sr_1_2' ),
    array( 'sku'=>'NG-GAM-011', 'ref'=>158, 'new'=>200, 'url'=>'https://www.amazon.sa/-/en/Steelseries-67500-Qck-Gaming-Mauspad-Gummiunterseite/dp/B00WAA2704/ref=sr_1_1' ),
    array( 'sku'=>'NT-CBL-FSC-001', 'ref'=>63, 'new'=>80, 'url'=>'https://www.amazon.sa/-/en/QSFPTEK-SFP-SFP-H10GB-CU1M-Ubiquiti-Mikrotik/dp/B087PB2ZP3/ref=sr_1_4' ),

);
$applied=0;
foreach ($rows as $r) {
    $pid = wc_get_product_id_by_sku($r['sku']);
    if (!$pid) { WP_CLI::warning("missing: {$r['sku']}"); continue; }
    $p = wc_get_product($pid); if (!$p instanceof WC_Product) continue;
    $cur = (float)$p->get_regular_price('edit'); $sale=(float)$p->get_sale_price('edit');
    update_post_meta($pid, '_ng_amazon_sa_ref_price', (string)$r['ref']);
    update_post_meta($pid, '_ng_amazon_sa_ref_url', (string)$r['url']);
    update_post_meta($pid, '_ng_amazon_sa_ref_at', (string)$now_iso);
    update_post_meta($pid, '_ng_amazon_sa_ref_confidence', 'high');
    if (!get_post_meta($pid,'_ng_pre_reprice_at',true)) {
        update_post_meta($pid,'_ng_pre_reprice_regular_price',(string)$cur);
        update_post_meta($pid,'_ng_pre_reprice_sale_price',(string)$sale);
        update_post_meta($pid,'_ng_pre_reprice_at',(string)time());
    }
    $p->set_regular_price((string)$r['new']);
    if ($sale) $p->set_sale_price('');
    $p->save();
    WP_CLI::log(sprintf('  %-18s  %4.0f -> %-4d  ref=%-4d  (#%d)', $r['sku'], $cur, $r['new'], $r['ref'], $pid));
    $applied++;
}
WP_CLI::log("=== v1.36.6 ===  Applied: $applied");
