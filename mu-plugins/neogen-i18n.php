<?php
/**
 * Plugin Name: NeoGen i18n — root locale switch
 * Description: Adds the missing /?lang=ar root-locale toggle. The site is
 *              bilingual same-page (EN + AR via inline `<span class="ar">`
 *              spans) but the `<html lang>` attribute was static "en-US"
 *              regardless of content, breaking RTL form inputs / carousels
 *              / flex layouts on Arabic-content pages. This filter switches
 *              the root `lang` + `dir` attributes when `?lang=ar` is in
 *              the query string. Body content is unaffected — the existing
 *              `<span class="ar">` spans still render normally.
 *
 *              Lands as Phase A of the 2026-05-08 readiness audit fix
 *              (BLOCKER #3). The conservative `?lang=ar` toggle was chosen
 *              over flipping the site default to AR because the audit
 *              specified `/?lang=ar` as the verification URL and changing
 *              the default would affect every existing LTR-tuned style.
 *
 * Version: 1.0.0
 * Author: Fahad Almansour
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NEOGEN_I18N_VERSION' ) ) {
	define( 'NEOGEN_I18N_VERSION', '1.0.0' );
}

/**
 * True when the current request is on the Arabic locale toggle.
 * Cached per-request because language_attributes can fire more than once
 * (e.g. some themes invoke it from both wp_head and the AMP/feed paths).
 */
function ng_i18n_is_ar() {
	static $cached = null;
	if ( null !== $cached ) return $cached;

	// Honour an explicit ?lang=ar (or lang[]=ar) query var. We avoid using
	// $_GET inside an `init` filter directly; this function may be called
	// before parse_request() so we read $_GET defensively.
	if ( isset( $_GET['lang'] ) ) {
		$lang = is_array( $_GET['lang'] ) ? reset( $_GET['lang'] ) : $_GET['lang']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public toggle, no state changes
		$lang = is_scalar( $lang ) ? strtolower( sanitize_text_field( wp_unslash( $lang ) ) ) : '';
		if ( $lang === 'ar' || strpos( $lang, 'ar-' ) === 0 || strpos( $lang, 'ar_' ) === 0 ) {
			return $cached = true;
		}
	}

	return $cached = false;
}

/**
 * Switch the root <html> attributes when the AR toggle is active.
 *
 * WordPress emits `lang="<locale>"` and `dir="rtl"` (when is_rtl()) via
 * `language_attributes()`. Site default locale is en_US so by default
 * the call returns `lang="en-US" dir="ltr"` regardless of body content.
 * On `?lang=ar`, override to `lang="ar" dir="rtl"`.
 */
add_filter( 'language_attributes', function ( $output, $doctype = 'html' ) {
	if ( ! ng_i18n_is_ar() ) return $output;
	if ( 'html' !== $doctype ) return $output;
	return 'lang="ar" dir="rtl"';
}, 10, 2 );

/**
 * Mirror a sensible Content-Language hint for crawlers and proxies.
 */
add_action( 'send_headers', function () {
	if ( ng_i18n_is_ar() ) {
		header( 'Content-Language: ar' );
	}
} );

/**
 * Add hreflang alternates so search engines understand the bilingual
 * relationship between the EN and AR views of the same canonical URL.
 * Cheap, additive, and non-breaking if a SEO mu-plugin already emits
 * its own (duplicates are deduplicated by Google in practice).
 */
add_action( 'wp_head', function () {
	// Use home_url() so the scheme is correct behind a CDN/proxy
	// (Cloudflare flips X-Forwarded-Proto; bare $_SERVER['HTTPS'] would
	// emit `http://` on an HTTPS-fronted request). REQUEST_URI is still
	// the path source — strip query so the canonical doesn't carry
	// session-noise params.
	$path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) : '/';
	$current = esc_url_raw( home_url( (string) $path ) );
	if ( ! $current ) return;

	$en_url = $current;
	$ar_url = add_query_arg( 'lang', 'ar', $current );

	echo '<link rel="alternate" hreflang="en" href="' . esc_url( $en_url ) . '">' . "\n";
	echo '<link rel="alternate" hreflang="ar" href="' . esc_url( $ar_url ) . '">' . "\n";
	echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $en_url ) . '">' . "\n";
}, 5 );
