<?php
/**
 * Plugin Name: NeoGen — Disable YITH Compare Button
 * Description: Removes the "Compare" button injected by yith-woocommerce-compare
 *              into product cards on category archives, the shop, related-products
 *              strips, and single-product pages. Three layers of defence so a
 *              YITH update can't quietly bring the button back:
 *                1. Filter `yith_woocompare_is_show_button_in_products_list`
 *                   (and its older alias) → false.
 *                2. remove_action() targeting the YITH frontend instance on
 *                   wp_loaded (after YITH has registered its hooks).
 *                3. CSS belt-and-braces: hide any leftover `.compare.button`
 *                   in case the renderer slips a different hook.
 *
 *              The YITH plugin itself stays active — orders / customer-side
 *              compare flows aren't touched here. Only the catalog button is
 *              removed. To bring it back, deactivate this mu-plugin.
 *
 *              Lands as v1.46.0 of the apps/neogen-custom overlay.
 *
 * Version: 1.0.0
 * Author: Fahad Almansour
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NEOGEN_DISABLE_COMPARE_VERSION' ) ) {
	define( 'NEOGEN_DISABLE_COMPARE_VERSION', '1.0.0' );
}

/* =====================================================================
 * Layer 1 — YITH-native filters (safest, idiomatic).
 * ===================================================================== */

add_filter( 'yith_woocompare_is_show_button_in_products_list', '__return_false', 99 );
// Older filter alias kept across YITH releases.
add_filter( 'yith_woocompare_show_button_in_products_list',    '__return_false', 99 );
// Pre-3.x filter name; harmless on 3.x.
add_filter( 'yith_woocompare_show_button',                     '__return_false', 99 );

/* =====================================================================
 * Layer 2 — remove_action against the live YITH frontend object.
 *
 * YITH attaches `add_compare_link` (and on some versions
 * `add_compare_button_in_block_loop`) to the catalog hooks. Whichever
 * priority YITH chose, walking the global $yith_woocompare instance and
 * calling remove_action against the bound method handles it.
 * ===================================================================== */

add_action( 'wp_loaded', function () {
	if ( empty( $GLOBALS['yith_woocompare'] ) || ! is_object( $GLOBALS['yith_woocompare'] ) ) {
		return;
	}
	$yith = $GLOBALS['yith_woocompare'];
	$obj  = isset( $yith->obj ) && is_object( $yith->obj ) ? $yith->obj : $yith;

	$hooks = array(
		'woocommerce_after_shop_loop_item',
		'woocommerce_after_subcategory',
		'woocommerce_single_product_summary',
		'woocommerce_blocks_product_grid_item_html',
	);

	$methods = array(
		'add_compare_link',
		'add_compare_button',
		'add_compare_button_in_block_loop',
		'add_button_in_loop',
	);

	foreach ( $hooks as $hook ) {
		foreach ( $methods as $method ) {
			if ( method_exists( $obj, $method ) ) {
				// Try common priorities YITH has used over versions.
				foreach ( array( 5, 10, 15, 20, 25 ) as $priority ) {
					remove_action( $hook, array( $obj, $method ), $priority );
				}
			}
		}
	}
}, 99 );

/* =====================================================================
 * Layer 3 — CSS belt-and-braces. The site theme already enqueues
 * `neogen.css` from the canonical mu-plugin route; we add a single
 * inline rule rather than touching that file (keeps the override
 * traceable to this plugin in dev tools).
 * ===================================================================== */

add_action( 'wp_head', function () {
	echo '<style id="ng-disable-compare">'
	   . '.compare.button,a.button.compare,.yith-compare,'
	   . '.product .compare,.products .compare,'
	   . '.yith-woocompare-widget,.compare-button{display:none!important;}'
	   . '</style>' . "\n";
}, 100 );
