<?php
/**
 * Plugin Name: NeoGen Icons
 * Description: SVG icon sprite + ng_icon() helper. Mirrors icons.jsx from the Claude design bundle (44 icons). Ships an inline <symbol> sprite once per page in wp_footer; templates reference icons via ng_icon('name', 20, 'extra-class'). All icons use stroke="currentColor" (and fill="currentColor" for the few solid ones) so colour follows CSS `color`.
 * Version: 1.38.0
 * Author: Fahad Almansour
 *
 * Source: /tmp/neogen-design/neogen-store/project/icons.jsx (lines 4–46).
 * Inner-SVG markup is verbatim from the design — keeps stroke widths,
 * geometry, and design intent intact.
 *
 * Usage:
 *   echo ng_icon('truck');                   // 20px, currentColor
 *   echo ng_icon('shield', 24, 'ngrd-x');    // 24px, extra class
 *   echo ng_icon_sprite();                   // one-shot sprite (auto in footer)
 */

defined('ABSPATH') || exit;

/**
 * Catalogue of icons. Each entry is the verbatim inner SVG from
 * icons.jsx — wrapped in <symbol> at sprite-build time.
 */
function ng_icons_catalog() {
    static $icons = null;
    if ( $icons !== null ) { return $icons; }
    $icons = [
        'truck'       => '<path d="M1 3h13v10H1zM14 7h3l2 3v3h-5V7z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/><circle cx="5" cy="17" r="1.5" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="15" cy="17" r="1.5" stroke="currentColor" stroke-width="1.5" fill="none"/>',
        'package'     => '<path d="M21 8l-9-5L3 8m18 0v8l-9 5-9-5V8m18 0L12 13 3 8m9 5v8" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/>',
        'refresh'     => '<path d="M4 4v5h5M20 20v-5h-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L4 10M3.51 15a9 9 0 0 0 14.85 3.36L20 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
        'replace'     => '<path d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'xCircle'     => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M15 9l-6 6M9 9l6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        'shield'      => '<path d="M12 2l8 3v7c0 5-4 8-8 10C8 20 4 17 4 12V5l8-3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'chat'        => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/>',
        'bell'        => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'star'        => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/>',
        'starFilled'  => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="currentColor"/>',
        'mail'        => '<rect x="2" y="4" width="20" height="16" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M2 8l10 7 10-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
        'phone'       => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.4 11.5 19.79 19.79 0 0 1 1.08 4.18 2 2 0 0 1 3 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 17z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
        'calendar'    => '<rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
        'location'    => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.5" fill="none"/>',
        'warning'     => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/><line x1="12" y1="9" x2="12" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="16.5" r="0.5" fill="currentColor"/>',
        'check'       => '<polyline points="20 6 9 17 4 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'checkCircle' => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" fill="none"/><polyline points="9 12 11 14 15 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'tag'         => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/><circle cx="7" cy="7" r="1.5" fill="currentColor"/>',
        'receipt'     => '<path d="M4 2h16v20l-2-1-2 1-2-1-2 1-2-1-2 1-2-1-2 1V2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/><path d="M8 7h8M8 11h8M8 15h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
        'search'      => '<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        'settings'    => '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" stroke="currentColor" stroke-width="1.5" fill="none"/>',
        'gift'        => '<rect x="3" y="8" width="18" height="14" rx="1" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M21 8H3V5a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M12 8V22M12 8c0-2 2-5 4-3s0 3 0 3H12zM12 8c0-2-2-5-4-3s0 3 0 3h4z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
        'home'        => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/><polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/>',
        'user'        => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.5" fill="none"/>',
        'heart'       => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/>',
        'copy'        => '<rect x="9" y="9" width="13" height="13" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
        'lock'        => '<rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
        'eye'         => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" fill="none"/>',
        'image'       => '<rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"/><polyline points="21 15 16 10 5 21" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/>',
        'video'       => '<polygon points="23 7 16 12 23 17 23 7" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/><rect x="1" y="5" width="15" height="14" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>',
        'attachment'  => '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>',
        'close'       => '<line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        'arrowRight'  => '<line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><polyline points="12 5 19 12 12 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'creditCard'  => '<rect x="1" y="4" width="22" height="16" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/><line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="1.5"/>',
        'whatsapp'    => '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z" fill="currentColor"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.558 4.118 1.531 5.845L0 24l6.29-1.525A11.955 11.955 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.992 0-3.85-.544-5.44-1.488l-.39-.23-4.043.98.999-3.941-.255-.406A9.945 9.945 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z" fill="currentColor"/>',
        'share'       => '<circle cx="18" cy="5" r="3" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="6" cy="12" r="3" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="18" cy="19" r="3" stroke="currentColor" stroke-width="1.5" fill="none"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/><polyline points="7 10 12 15 17 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/><line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        'send'        => '<line x1="22" y1="2" x2="11" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><polygon points="22 2 15 22 11 13 2 9 22 2" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/>',
        'filter'      => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" fill="none"/>',
        'grid'        => '<rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="1.5" fill="none"/><rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="1.5" fill="none"/><rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="1.5" fill="none"/><rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="1.5" fill="none"/>',
        'list'        => '<line x1="8" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="8" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="8" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="3" y1="6" x2="3.01" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="12" x2="3.01" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="18" x2="3.01" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    ];
    return $icons;
}

/**
 * Render one icon as a self-contained <svg>. Use this when the sprite
 * may not be present yet (e.g. inside an email or a server-side
 * render before wp_footer fires).
 */
function ng_icon( $name, $size = 20, $extra_class = '' ) {
    $icons = ng_icons_catalog();
    if ( ! isset( $icons[ $name ] ) ) {
        $name = 'close';
    }
    $size  = (int) $size;
    $class = 'ngrd-icon ngrd-icon--' . sanitize_html_class( $name );
    if ( $extra_class !== '' ) {
        $class .= ' ' . esc_attr( $extra_class );
    }
    return sprintf(
        '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" aria-hidden="true" focusable="false">%s</svg>',
        esc_attr( $class ),
        $size,
        $size,
        $icons[ $name ]   // safe: literal SVG markup we authored, not user input
    );
}

/**
 * Build the inline sprite (one <svg> with N <symbol> children) for
 * pages that prefer <use href="#ngrd-icon-name"> references. Emitted
 * once per page in wp_footer (priority 1) so any redesign templates
 * later in the page can <use> any icon without re-emitting markup.
 */
function ng_icon_sprite() {
    $icons  = ng_icons_catalog();
    $out    = '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden;" aria-hidden="true" focusable="false"><defs>';
    foreach ( $icons as $name => $body ) {
        $out .= '<symbol id="ngrd-icon-' . sanitize_html_class( $name ) . '" viewBox="0 0 24 24">' . $body . '</symbol>';
    }
    $out .= '</defs></svg>';
    return $out;
}

/**
 * Reference an icon via the sprite. Lighter than ng_icon() when the
 * same icon repeats many times on a page.
 */
function ng_icon_use( $name, $size = 20, $extra_class = '' ) {
    $icons = ng_icons_catalog();
    if ( ! isset( $icons[ $name ] ) ) { $name = 'close'; }
    $size  = (int) $size;
    $class = 'ngrd-icon ngrd-icon--' . sanitize_html_class( $name );
    if ( $extra_class !== '' ) { $class .= ' ' . esc_attr( $extra_class ); }
    return sprintf(
        '<svg class="%s" width="%d" height="%d" aria-hidden="true" focusable="false"><use href="#ngrd-icon-%s"/></svg>',
        esc_attr( $class ),
        $size,
        $size,
        sanitize_html_class( $name )
    );
}

/**
 * Auto-emit the sprite once in the footer when the redesign body class
 * is present. Idempotent — multiple add_action calls won't double up.
 */
add_action( 'wp_footer', function () {
    static $emitted = false;
    if ( $emitted ) { return; }
    if ( ! function_exists( 'ng_redesign_active_phases' ) ) { return; }
    if ( empty( ng_redesign_active_phases() ) ) { return; }
    $emitted = true;
    echo ng_icon_sprite(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — sprite is fully literal SVG with no user input.
}, 1 );
