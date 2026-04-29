<?php
/**
 * v1.36.9 — extract _ng_ar_title from pipe-split product titles.
 *
 * Many products were imported with post_title in the form
 *   "English Product Name | الاسم بالعربية"
 * and the templates render bilingual properly only when post_title
 * is EN-only AND _ng_ar_title meta carries the AR string.
 *
 * This pass:
 *   1. Walks every product whose post_title contains " | "
 *   2. Stamps _ng_ar_title with the right-side fragment (AR)
 *   3. Updates post_title to the left-side fragment (EN-only)
 *   4. Stamps _ng_original_post_title with the original (rollback)
 *
 * Idempotent: re-running on a cleaned title is a no-op (no " | ").
 *
 * Truth-rule guardrail: NO TRANSLATION is invented. Products that
 * lack an AR fragment in their title are skipped — admin must write
 * AR titles for those manually (or via a translation review pass).
 *
 * Run via:
 *   wp eval-file /tmp/neogen-extract-ar-titles.php --skip-plugins=litespeed-cache --user=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) { WP_CLI::error( 'WC not loaded' ); }

global $wpdb;
$rows = $wpdb->get_results(
    "SELECT ID, post_title FROM {$wpdb->posts}
      WHERE post_type = 'product'
        AND post_status IN ('publish','draft')
        AND post_title LIKE '% | %'"
);

WP_CLI::log( '=== v1.36.9 extract AR titles ===' );
WP_CLI::log( 'Pipe-split candidates: ' . count( $rows ) );

$updated = 0; $skipped_no_ar = 0;
foreach ( $rows as $r ) {
    $parts = explode( ' | ', $r->post_title, 2 );
    if ( count( $parts ) !== 2 ) { continue; }
    $en = trim( $parts[0] );
    $ar = trim( $parts[1] );

    // Sanity: right side must contain at least one Arabic character (U+0600 - U+06FF).
    if ( ! preg_match( '/[\x{0600}-\x{06FF}]/u', $ar ) ) {
        $skipped_no_ar++;
        continue;
    }

    // Backup original.
    if ( ! get_post_meta( $r->ID, '_ng_original_post_title', true ) ) {
        update_post_meta( $r->ID, '_ng_original_post_title', $r->post_title );
    }

    update_post_meta( $r->ID, '_ng_ar_title', $ar );
    wp_update_post( array(
        'ID'         => $r->ID,
        'post_title' => $en,
    ) );
    $updated++;
}

WP_CLI::log( "Updated:                $updated" );
WP_CLI::log( "Skipped (no AR right):  $skipped_no_ar" );

// Coverage report
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status IN ('publish','draft')" );
$with  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_ng_ar_title' AND meta_value!=''" );
WP_CLI::log( '---' );
WP_CLI::log( "Final coverage: $with / $total products carry _ng_ar_title (" . round( $with / max( $total, 1 ) * 100, 1 ) . '%)' );

if ( $total > $with ) {
    $missing = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
          WHERE p.post_type='product' AND p.post_status IN ('publish','draft')
            AND p.ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_ng_ar_title' AND meta_value!='')"
    );
    WP_CLI::log( "Missing AR title (no pipe in title): $missing — admin must add AR via wp-admin or translation pass" );
}
