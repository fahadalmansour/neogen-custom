<?php
/**
 * Plugin Name: NeoGen A/B Testing
 * Description: Lightweight deterministic A/B bucketing and exposure logging. Primitive only — experiments live as toggleable snippets in plugins/neogen-snippets/.
 * Version: 1.0.0
 * Author: Fahad Almansour
 *
 * Public API (all safe to call on any WP hook):
 *   neogen_ab_bucket($experiment_key, $variants = ['control','treatment'])
 *     Returns the variant slug for this visitor. Deterministic: same visitor + same
 *     experiment → same variant, forever (until they clear cookies).
 *
 *   neogen_ab_expose($experiment_key, $variant)
 *     Log that this visitor saw this variant. Deduped per-experiment via a 30-day
 *     cookie, so one exposure per visitor per experiment gets recorded.
 *
 *   neogen_ab_convert($experiment_key, $variant, $meta = [])
 *     Log a conversion event. Not deduped — call on purchase, signup, etc.
 *
 * Events land as JSON lines in wp-content/uploads/neogen-ab.log. Analysis is
 * manual (grep/awk) for v1.
 *
 * Privacy note: sets long-lived cookies (ngab_vid, ngab_seen_*). Saudi PDPL
 * and EU GDPR may require a consent gate before calling neogen_ab_bucket().
 * Wire that up in the calling snippet, not here.
 */

defined('ABSPATH') || exit;

if (!defined('NEOGEN_AB_VERSION')) {
    define('NEOGEN_AB_VERSION', '1.0.0');
}

function neogen_ab_bucket($experiment_key, $variants = ['control', 'treatment']) {
    if (!is_string($experiment_key) || $experiment_key === '') return null;
    if (!is_array($variants) || empty($variants)) return null;

    $vid = neogen_ab_visitor_id();
    if ($vid === null) {
        // Cookie couldn't be set (headers already sent). Return first variant
        // so the caller still gets a usable value, but skip logging.
        return $variants[0];
    }

    $hash = crc32($vid . '|' . $experiment_key);
    $idx  = $hash % count($variants);
    return $variants[$idx];
}

function neogen_ab_expose($experiment_key, $variant) {
    if (headers_sent()) return;
    $safe_key = preg_replace('/[^a-z0-9_]/i', '', (string) $experiment_key);
    if ($safe_key === '') return;
    $cookie = 'ngab_seen_' . $safe_key;
    if (isset($_COOKIE[$cookie])) return;
    setcookie(
        $cookie,
        '1',
        time() + 60 * 60 * 24 * 30,
        defined('COOKIEPATH') ? COOKIEPATH : '/',
        defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
        is_ssl(),
        true
    );
    $_COOKIE[$cookie] = '1';
    neogen_ab_write_event('expose', $experiment_key, $variant, []);
}

function neogen_ab_convert($experiment_key, $variant, $meta = []) {
    neogen_ab_write_event('convert', $experiment_key, $variant, is_array($meta) ? $meta : []);
}

function neogen_ab_visitor_id() {
    if (!empty($_COOKIE['ngab_vid'])) {
        $vid = preg_replace('/[^a-zA-Z0-9]/', '', (string) $_COOKIE['ngab_vid']);
        if (strlen($vid) >= 16) return $vid;
    }
    if (headers_sent()) return null;
    try {
        $vid = bin2hex(random_bytes(16));
    } catch (\Throwable $e) {
        // random_bytes failed (extremely rare). Use WP's UUID v4
        // generator — RFC 4122 compliant and safer than the prior
        // md5(uniqid . REMOTE_ADDR) fallback, which leaked low-entropy
        // process state and tied the visitor ID to source IP.
        $vid = function_exists('wp_generate_uuid4') ? str_replace('-', '', wp_generate_uuid4()) : md5(uniqid('ngab', true));
    }
    setcookie(
        'ngab_vid',
        $vid,
        time() + 60 * 60 * 24 * 180,
        defined('COOKIEPATH') ? COOKIEPATH : '/',
        defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
        is_ssl(),
        true
    );
    $_COOKIE['ngab_vid'] = $vid;
    return $vid;
}

function neogen_ab_write_event($type, $experiment_key, $variant, $meta) {
    if (!function_exists('wp_upload_dir') || !function_exists('wp_json_encode')) return;
    $uploads = wp_upload_dir();
    if (!empty($uploads['error']) || empty($uploads['basedir'])) return;
    $file = trailingslashit($uploads['basedir']) . 'neogen-ab.log';
    $row = [
        't'   => gmdate('c'),
        'ev'  => (string) $type,
        'exp' => (string) $experiment_key,
        'var' => (string) $variant,
        'vid' => isset($_COOKIE['ngab_vid']) ? (string) $_COOKIE['ngab_vid'] : null,
    ];
    if (!empty($meta)) {
        $row['meta'] = $meta;
    }
    @file_put_contents($file, wp_json_encode($row) . "\n", FILE_APPEND | LOCK_EX);
}

// Admin-bar readout — admins see the primitive is loaded. Experiments register
// themselves into a submenu via the `neogen_ab_admin_bar` filter if they want
// to be visible here.
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;
    $wp_admin_bar->add_node([
        'id'     => 'neogen-ab',
        'title'  => 'A/B ' . NEOGEN_AB_VERSION,
        'parent' => 'top-secondary',
        'meta'   => ['title' => 'NeoGen A/B primitive — events at wp-content/uploads/neogen-ab.log'],
    ]);

    $children = apply_filters('neogen_ab_admin_bar', []);
    if (is_array($children)) {
        foreach ($children as $child) {
            if (empty($child['id']) || empty($child['title'])) continue;
            $wp_admin_bar->add_node([
                'id'     => 'neogen-ab-' . sanitize_key($child['id']),
                'parent' => 'neogen-ab',
                'title'  => (string) $child['title'],
                'href'   => isset($child['href']) ? (string) $child['href'] : false,
            ]);
        }
    }
}, 101);
