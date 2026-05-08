<?php
/**
 * Plugin Name: NeoGen Performance Hints
 * Description: Emits resource hints (preconnect / dns-prefetch / preload) in
 *              wp_head for the third-party origins NeoGen actually uses.
 *              Cheap latency wins (~80–200ms per first-paint per third-party
 *              host) without touching the existing SEO mu-plugin or the
 *              theme. Lands as a follow-up to the readiness-2026-05-08 perf
 *              pass — the audit flagged 28× external <script> + 55× inline
 *              <script> on the homepage with only 2 preconnect hints
 *              (Google Fonts) and called it a HIGH item.
 *
 *              Hint policy:
 *                - Analytics/GTM: preconnect (loaded on every page early in
 *                  the request, full TLS handshake amortised matters).
 *                - Payment providers (Tabby, Checkout.com, STC Pay,
 *                  ApplePay): dns-prefetch sitewide; preconnect only when
 *                  is_cart() / is_checkout() / is_product() so the TLS
 *                  handshake isn't paid on pages that never hit them.
 *                - Static asset CDNs (Google Fonts gstatic): already
 *                  preconnected by the theme; we don't duplicate.
 *
 *              CSP-aligned: every host below appears in the existing
 *              Content-Security-Policy header (verified against the
 *              live response 2026-05-08).
 *
 * Version: 1.0.1
 * Author: Fahad Almansour
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NEOGEN_PERF_HINTS_VERSION' ) ) {
	define( 'NEOGEN_PERF_HINTS_VERSION', '1.0.1' );
}

/**
 * Resource hints WordPress emits via wp_resource_hints.
 *
 * The wp_resource_hints filter receives an array of hosts/URLs for a
 * given hint type ('preconnect', 'dns-prefetch', 'preload', etc.) and
 * lets us push our own. WP handles HTML emission; we just supply data.
 *
 * Each entry is either a string (raw URL/host) or an array with
 * 'href' and optional 'crossorigin' / 'as' / 'type' / 'pr'.
 *
 * @param array  $hints           Existing hints (often empty).
 * @param string $relation_type   The hint kind being assembled.
 * @return array                  Hints to emit.
 */
add_filter( 'wp_resource_hints', function ( $hints, $relation_type ) {

	if ( $relation_type === 'preconnect' ) {
		// Always-on preconnects — these origins are hit during the
		// initial render on every page (GTM/GA fire from the theme
		// snippet before first paint).
		$hints[] = array(
			'href'        => 'https://www.googletagmanager.com',
			'crossorigin' => 'anonymous',
		);
		$hints[] = array(
			'href'        => 'https://www.google-analytics.com',
			'crossorigin' => 'anonymous',
		);

		// Conditional preconnects — Woo flow pages where the payment
		// SDKs are guaranteed to load. Outside this scope we fall
		// through to dns-prefetch only (cheaper).
		if ( function_exists( 'is_cart' ) && function_exists( 'is_checkout' ) && function_exists( 'is_product' ) ) {
			if ( is_cart() || is_checkout() || is_product() ) {
				$hints[] = array(
					'href'        => 'https://api.tabby.ai',
					'crossorigin' => 'anonymous',
				);
				$hints[] = array(
					'href'        => 'https://cdn.checkout.com', // SDK lives here, not on the apex
					'crossorigin' => 'anonymous',
				);
				$hints[] = 'https://api.stcpay.com.sa';
			}
		}
	}

	if ( $relation_type === 'dns-prefetch' ) {
		// Cheap DNS resolution for everything in the CSP allowlist
		// that isn't already in preconnect — saves the DNS round-trip
		// when the user lands on a Woo flow page even if not preconnected.
		$hints[] = '//www.googleadservices.com';
		$hints[] = '//www.googlesyndication.com';
		$hints[] = '//stats.g.doubleclick.net'; // real asset host, not the apex
		$hints[] = '//api.tabby.ai';
		$hints[] = '//cdn.checkout.com';
		$hints[] = '//api.stcpay.com.sa';
		$hints[] = '//applepay.cdn-apple.com';
	}

	return $hints;
}, 10, 2 );
