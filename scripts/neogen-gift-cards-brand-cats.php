<?php
/**
 * Phase C of v1.34.0 — create brand sub-categories under each parent
 * gift-cards sub-cat, then re-tag every existing gift-card product so
 * its brand category is in its category_ids list. Result: clean URLs
 * like /product-category/gift-cards/game-cards/playstation/.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-gift-cards-brand-cats.php --skip-plugins=litespeed-cache --user=1
 *
 * Idempotent: matches by slug; existing brand terms update, no duplicates.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product' ) ) {
    WP_CLI::error( 'WooCommerce is not loaded.' );
}

// Brand → parent sub-cat mapping (mirrors scripts/neogen-gift-cards-bulk.php).
$brand_map = array(
    // game-cards
    'playstation'      => array( 'parent' => 'game-cards',    'name' => 'بلايستيشن'        ),
    'xbox'             => array( 'parent' => 'game-cards',    'name' => 'إكس بوكس'        ),
    'steam'            => array( 'parent' => 'game-cards',    'name' => 'ستيم'            ),
    'razer-gold'       => array( 'parent' => 'game-cards',    'name' => 'رازر قولد'        ),
    'roblox'           => array( 'parent' => 'game-cards',    'name' => 'روبلكس'          ),
    'pubg-mobile'      => array( 'parent' => 'game-cards',    'name' => 'ببجي موبايل'      ),
    'free-fire'        => array( 'parent' => 'game-cards',    'name' => 'فري فاير'         ),
    'mobile-legends'   => array( 'parent' => 'game-cards',    'name' => 'موبايل ليجندز'    ),
    // app-stores
    'apple-itunes'     => array( 'parent' => 'app-stores',    'name' => 'آبل أيتونز'       ),
    'google-play'      => array( 'parent' => 'app-stores',    'name' => 'قوقل بلاي'        ),
    // subscriptions
    'netflix'          => array( 'parent' => 'subscriptions', 'name' => 'نتفلكس'          ),
    'spotify'          => array( 'parent' => 'subscriptions', 'name' => 'سبوتيفاي'         ),
    'disney-plus'      => array( 'parent' => 'subscriptions', 'name' => 'ديزني بلس'        ),
    'playstation-plus' => array( 'parent' => 'subscriptions', 'name' => 'بلايستيشن بلس'    ),
);

$created = 0;
$updated = 0;
$brand_term_ids = array();

foreach ( $brand_map as $slug => $meta ) {
    $parent = get_term_by( 'slug', $meta['parent'], 'product_cat' );
    if ( ! $parent ) {
        WP_CLI::warning( "Parent slug not found: {$meta['parent']} — skipping $slug." );
        continue;
    }
    $existing = get_term_by( 'slug', $slug, 'product_cat' );
    if ( $existing ) {
        wp_update_term( $existing->term_id, 'product_cat', array(
            'name'   => $meta['name'],
            'parent' => $parent->term_id,
        ) );
        $brand_term_ids[ $slug ] = (int) $existing->term_id;
        $updated++;
    } else {
        $r = wp_insert_term( $meta['name'], 'product_cat', array(
            'slug'   => $slug,
            'parent' => $parent->term_id,
        ) );
        if ( is_wp_error( $r ) ) {
            WP_CLI::warning( "$slug term insert failed: " . $r->get_error_message() );
            continue;
        }
        $brand_term_ids[ $slug ] = (int) $r['term_id'];
        $created++;
    }
}
WP_CLI::log( "Brand sub-cats — created: $created, updated: $updated." );

// Re-tag every product whose _ng_gift_card_brand matches a brand we just
// processed. Keep their existing category assignments and APPEND the
// brand sub-cat term so the product appears in both the parent
// (game-cards) and the brand archive (playstation).
global $wpdb;
$products = $wpdb->get_results(
    "SELECT post_id, meta_value AS brand_slug
       FROM {$wpdb->postmeta}
      WHERE meta_key = '_ng_gift_card_brand'"
);

$retagged = 0;
foreach ( $products as $row ) {
    if ( ! isset( $brand_term_ids[ $row->brand_slug ] ) ) { continue; }
    $product = wc_get_product( (int) $row->post_id );
    if ( ! $product instanceof WC_Product ) { continue; }
    $cat_ids = (array) $product->get_category_ids();
    $brand_term_id = $brand_term_ids[ $row->brand_slug ];
    if ( ! in_array( $brand_term_id, $cat_ids, true ) ) {
        $cat_ids[] = $brand_term_id;
        $product->set_category_ids( array_values( array_unique( $cat_ids ) ) );
        $product->save();
        $retagged++;
    }
}
WP_CLI::log( "Products re-tagged with brand sub-cat: $retagged" );

// Refresh term counts so /product-category/gift-cards/game-cards/playstation/ shows the right number.
foreach ( $brand_term_ids as $tid ) {
    wp_update_term_count_now( array( $tid ), 'product_cat' );
}
WP_CLI::log( 'Term counts refreshed.' );

WP_CLI::log( '---' );
WP_CLI::log( 'Brand sub-cats overview:' );
foreach ( $brand_term_ids as $slug => $tid ) {
    $term = get_term( $tid, 'product_cat' );
    if ( ! $term || is_wp_error( $term ) ) { continue; }
    $link = get_term_link( $term );
    $link = is_wp_error( $link ) ? '?' : $link;
    WP_CLI::log( sprintf( '  %-20s  count=%-3d  %s', $slug, (int) $term->count, $link ) );
}
