<?php
/**
 * Plugin Name: NeoGen — Zero-Price Floor Filter
 * Description: Any WooCommerce product whose `_price` / `_regular_price` /
 *              `_sale_price` reads as numeric zero is bumped to a
 *              configurable floor (default 10,000 SAR) on every read of
 *              `WC_Product::get_*price()`. Display, cart, checkout, emails,
 *              and exports all reflect the floor; the database row stays
 *              at 0 (no DB write). User intent (2026-05-08): no product on
 *              neogen.store should ever read as Free / 0 SAR — until a real
 *              price lands, the placeholder makes the listing visibly
 *              "needs price" without taking it down.
 *
 *              Toggle off without code edits by defining either:
 *                define('NG_PRICE_FLOOR_DISABLED', true);   // skip filter entirely
 *                define('NG_PRICE_FLOOR_FALLBACK', 5000);   // change the floor
 *              in wp-config.php.
 *
 *              Empty-string prices ('') and null are passed through
 *              untouched — WC distinguishes "price not set" (empty) from
 *              "price set to zero" (numeric 0). Only numeric zero is bumped.
 *
 * Version: 1.0.0
 * Author: Fahad Almansour
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NEOGEN_PRICE_FLOOR_VERSION' ) ) {
	define( 'NEOGEN_PRICE_FLOOR_VERSION', '1.0.0' );
}

// Default floor; override in wp-config.php if needed.
if ( ! defined( 'NG_PRICE_FLOOR_FALLBACK' ) ) {
	define( 'NG_PRICE_FLOOR_FALLBACK', 10000 );
}

// Hard kill-switch — define NG_PRICE_FLOOR_DISABLED in wp-config.php to
// fully bypass the filter. Useful for a same-day rollback without a
// code edit / Pull-Latest cycle.
if ( defined( 'NG_PRICE_FLOOR_DISABLED' ) && NG_PRICE_FLOOR_DISABLED ) {
	return;
}

/**
 * Decide whether a price value should be replaced with the floor.
 *
 * - '' (empty string) → leave alone. WC uses '' to mean "price not set",
 *   which is semantically different from "price is zero" and shouldn't
 *   be bumped (it would convert any unpriced product into a 10k product).
 * - null → leave alone. Same reasoning.
 * - numeric '0' / 0 / '0.00' → replace with the floor.
 * - any other numeric → leave alone.
 * - non-numeric string → leave alone (probably already filtered by another plugin).
 */
function ng_price_floor_apply( $price, $product = null ) {
	if ( '' === $price || null === $price ) {
		return $price;
	}
	if ( ! is_numeric( $price ) ) {
		return $price;
	}
	if ( 0.0 === (float) $price ) {
		return (string) NG_PRICE_FLOOR_FALLBACK;
	}
	return $price;
}

// Three filters cover the bulk of WC price reads. WC_Product::get_price()
// already routes through get_regular_price() / get_sale_price() under the
// hood, so this triple-hook catches all of them and keeps the behaviour
// consistent across product types (simple, variable, grouped variation
// children).
add_filter( 'woocommerce_product_get_price',         'ng_price_floor_apply', 99, 2 );
add_filter( 'woocommerce_product_get_regular_price', 'ng_price_floor_apply', 99, 2 );
add_filter( 'woocommerce_product_get_sale_price',    'ng_price_floor_apply', 99, 2 );

// Variable / variation children read their prices through their own
// filter set. Hooking these too means the parent variable product's
// min/max price (computed from children) reflects the floor.
add_filter( 'woocommerce_product_variation_get_price',         'ng_price_floor_apply', 99, 2 );
add_filter( 'woocommerce_product_variation_get_regular_price', 'ng_price_floor_apply', 99, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price',    'ng_price_floor_apply', 99, 2 );
