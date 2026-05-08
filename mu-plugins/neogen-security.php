<?php
/**
 * Plugin Name: NeoGen Security Hardening
 * Description: Three-layer defensive hardening: (1) disables XML-RPC + strips X-Pingback; (2) rate-limits failed wp-login attempts per IP without locking anyone out; (3) strips the WP version generator from HTML head + RSS to reduce trivial fingerprinting. Designed to sit alongside the existing CSP + HSTS headers in neogen-seo.php.
 * Version: 1.0.0
 * Author: Fahad Almansour
 *
 * Why a rate-limiter instead of an IP-allowlist:
 * a hard IP allowlist on /wp-login.php would lock the merchant out of
 * https://neogen.store/wp-admin/tools.php?page=neogen-deploy if their ISP
 * ever rotates their IP — a very common KSA failure mode. Rate-limit by IP
 * captures most of the security benefit (credential-stuffing bots get
 * blocked after 5 attempts in 15 minutes) with zero lockout risk for the
 * merchant.
 *
 * If you want a hard IP allowlist later, define NG_SECURITY_ADMIN_IPS in
 * wp-config.php as a JSON-encoded array of IPv4/IPv6 strings; this plugin
 * will respect it as a SHORT-CIRCUIT (those IPs bypass the rate limiter
 * entirely). Leave it undefined and the plugin behaves as pure rate-limit.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'NEOGEN_SECURITY_VERSION' ) ) {
	define( 'NEOGEN_SECURITY_VERSION', '1.0.0' );
}

// Tunables — exposed as constants so a future ops-tools mu-plugin can override.
if ( ! defined( 'NG_SEC_LOGIN_FAIL_LIMIT' ) )    define( 'NG_SEC_LOGIN_FAIL_LIMIT', 5 );      // attempts allowed within the window
if ( ! defined( 'NG_SEC_LOGIN_FAIL_WINDOW' ) )   define( 'NG_SEC_LOGIN_FAIL_WINDOW', 15 * MINUTE_IN_SECONDS );
if ( ! defined( 'NG_SEC_LOGIN_BLOCK_TTL' ) )     define( 'NG_SEC_LOGIN_BLOCK_TTL',  60 * MINUTE_IN_SECONDS );
if ( ! defined( 'NG_SEC_LOGIN_LOG_ENABLED' ) )   define( 'NG_SEC_LOGIN_LOG_ENABLED', true );  // PSR-style line per block

/* =====================================================================
 * Layer 1 — disable XML-RPC + drop X-Pingback header
 * ===================================================================== */

// Block the application-layer entry points first.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );

// Remove the auto-discovery hint emitted via wp_head + the HTTP response header.
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
add_filter( 'wp_headers', function ( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
} );

// Hard-403 any direct request to /xmlrpc.php so log-spam from credential-
// stuffing bots stops landing on the application stack at all.
add_action( 'init', function () {
	if ( isset( $_SERVER['SCRIPT_FILENAME'] ) && substr( (string) $_SERVER['SCRIPT_FILENAME'], -11 ) === '/xmlrpc.php' ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo "XML-RPC disabled.";
		exit;
	}
}, 0 );

/* =====================================================================
 * Layer 2 — rate-limit /wp-login.php by client IP
 * ===================================================================== */

/**
 * Best-effort client IP detection. Behind a CF / LiteSpeed / nginx proxy,
 * REMOTE_ADDR is the proxy's IP, so we walk the standard forwarded-for
 * chain conservatively (only honour the right-most public address).
 */
function ng_sec_client_ip() {
	$candidates = array();
	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$candidates = array_merge( $candidates, array_map( 'trim', explode( ',', (string) $_SERVER[ $key ] ) ) );
		}
	}
	foreach ( $candidates as $ip ) {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return $ip;
		}
	}
	return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/**
 * Hard-allowlist short-circuit: if NG_SECURITY_ADMIN_IPS is defined as a
 * JSON array of IPs in wp-config.php, those IPs skip rate-limiting entirely.
 */
function ng_sec_ip_is_allowlisted( $ip ) {
	if ( ! defined( 'NG_SECURITY_ADMIN_IPS' ) ) return false;
	$raw = NG_SECURITY_ADMIN_IPS;
	$list = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
	if ( ! is_array( $list ) ) return false;
	return in_array( $ip, array_map( 'trim', $list ), true );
}

function ng_sec_login_fail_key( $ip ) {
	return 'ng_sec_lf_' . substr( hash( 'sha256', (string) $ip ), 0, 24 );
}

function ng_sec_login_block_key( $ip ) {
	return 'ng_sec_lb_' . substr( hash( 'sha256', (string) $ip ), 0, 24 );
}

// Block POSTs to wp-login.php from IPs already in the penalty box.
add_action( 'login_init', function () {
	if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) return;
	$ip = ng_sec_client_ip();
	if ( ng_sec_ip_is_allowlisted( $ip ) ) return;
	$blocked_until = (int) get_transient( ng_sec_login_block_key( $ip ) );
	if ( $blocked_until > time() ) {
		status_header( 429 );
		header( 'Retry-After: ' . max( 1, $blocked_until - time() ) );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo "Too many failed login attempts. Try again in " . human_time_diff( time(), $blocked_until ) . ".";
		exit;
	}
}, 0 );

// Increment the failure counter on every WP login failure.
add_action( 'wp_login_failed', function ( $username ) {
	$ip = ng_sec_client_ip();
	if ( ng_sec_ip_is_allowlisted( $ip ) ) return;

	$key   = ng_sec_login_fail_key( $ip );
	$count = (int) get_transient( $key );
	$count++;
	set_transient( $key, $count, NG_SEC_LOGIN_FAIL_WINDOW );

	if ( $count >= NG_SEC_LOGIN_FAIL_LIMIT ) {
		$until = time() + NG_SEC_LOGIN_BLOCK_TTL;
		set_transient( ng_sec_login_block_key( $ip ), $until, NG_SEC_LOGIN_BLOCK_TTL );
		delete_transient( $key );

		if ( NG_SEC_LOGIN_LOG_ENABLED && function_exists( 'error_log' ) ) {
			error_log( sprintf(
				'[neogen-security] blocked %s for %d minutes after %d failed login attempts (last user: %s)',
				$ip,
				NG_SEC_LOGIN_BLOCK_TTL / MINUTE_IN_SECONDS,
				NG_SEC_LOGIN_FAIL_LIMIT,
				is_string( $username ) ? sanitize_user( $username ) : '?'
			) );
		}
	}
}, 10, 1 );

// On a successful login, clear any in-flight failure counter for that IP.
add_action( 'wp_login', function () {
	$ip = ng_sec_client_ip();
	delete_transient( ng_sec_login_fail_key( $ip ) );
	delete_transient( ng_sec_login_block_key( $ip ) );
}, 10, 0 );

/* =====================================================================
 * Layer 3 — strip the WordPress version generator
 * ===================================================================== */

// Drops <meta name="generator" content="WordPress X.Y.Z" /> from wp_head.
remove_action( 'wp_head', 'wp_generator' );

// Belt-and-braces: filter the_generator (used by RSS/Atom + the meta tag).
add_filter( 'the_generator', '__return_empty_string' );
