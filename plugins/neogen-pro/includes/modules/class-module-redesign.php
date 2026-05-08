<?php
/**
 * NeoGen Pro — Redesign Module
 *
 * Consolidates the Phase 0 infrastructure for the Claude-design redesign rollout:
 *   - feature-flag panel + .ngrd-on body class kill-switch
 *   - per-phase enqueue of redesign.css and redesign.js
 *   - icon library + sprite (44 icons mirrored from icons.jsx)
 *   - per-product Add-ons & Replacements meta + admin metabox
 *   - per-order-item warranty tracking
 *   - per-order-item gift-card key store (encrypted at rest)
 *   - ng_compatibility_note() for the PDP "Works Best With" green box
 *
 * ZERO customer-visible effect until at least one phase is enabled at
 * NeoGen Pro → Redesign in the WP admin. Phase activation is what
 * triggers the .ngrd-on body class, the asset enqueue, and per-template
 * redesign-on guards.
 *
 * Source of truth for the design recreation:
 *   /tmp/neogen-design/neogen-store/project/*.jsx
 *   /Users/fahadalmansour/.claude/plans/fetch-this-design-file-kind-pizza.md
 *
 * @package NeoGen_Pro
 */

defined('ABSPATH') || exit;

class NeoGen_Pro_Module_Redesign {

    const VERSION         = '1.38.0';
    const OPTION_PHASES   = 'ng_redesign_phases';
    const OPTION_KILL     = 'ng_redesign_killswitch';
    const WARRANTY_DEFAULT_MONTHS = 12;

    /**
     * Module bootstrap. Called from neogen-pro.php's plugins_loaded
     * dispatcher when the redesign module is enabled in
     * `neogen_pro_modules`.
     */
    public static function init() {
        // Body-class gate
        add_filter( 'body_class', [ __CLASS__, 'body_class' ] );

        // Conditional asset enqueue
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ], 30 );

        // Inline icon sprite once per page when the redesign is on
        add_action( 'wp_footer', [ __CLASS__, 'emit_icon_sprite' ], 1 );

        // Admin tools page
        add_action( 'admin_menu', [ __CLASS__, 'admin_menu' ] );

        // Add-ons & Replacements
        add_action( 'add_meta_boxes',     [ __CLASS__, 'addons_metabox' ] );
        add_action( 'save_post_product',  [ __CLASS__, 'addons_save' ] );

        // Warranties — product-level field + line-item capture + completion stamping
        add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'warranty_product_field' ] );
        add_action( 'woocommerce_process_product_meta',                 [ __CLASS__, 'warranty_save_product_field' ] );
        add_action( 'woocommerce_checkout_create_order_line_item',      [ __CLASS__, 'warranty_capture_at_purchase' ], 10, 4 );
        add_action( 'woocommerce_order_status_completed',               [ __CLASS__, 'warranty_stamp_on_complete' ] );

        // removed 2026-05-07: gift-cards purged — hooks detached, methods retained
        // add_action( 'add_meta_boxes',                  [ __CLASS__, 'gift_keys_metabox' ] );
        // add_action( 'woocommerce_process_shop_order_meta', [ __CLASS__, 'gift_keys_save' ] );
    }

    /* =====================================================================
     * Phase catalogue + flag helpers
     * ===================================================================== */

    public static function phases() {
        return [
            'homepage'      => [ 'label' => 'Homepage hero gallery + trust strip',          'phase' => '1',  'version' => '1.38.1' ],
            'shop'          => [ 'label' => 'Shop active-filter chip bar',                  'phase' => '2',  'version' => '1.38.2' ],
            'pdp'           => [ 'label' => 'PDP — Works Best With + Add-ons + tabs',       'phase' => '3',  'version' => '1.38.3' ],
            'cart'          => [ 'label' => 'Cart 3-step indicator',                        'phase' => '4',  'version' => '1.38.4' ],
            'checkout'      => [ 'label' => 'Checkout stepper + carrier styling',           'phase' => '5',  'version' => '1.38.5' ],
            // removed 2026-05-07: gift-cards purged
            // 'gift_cards'    => [ 'label' => 'Gift Cards multi-region showcase',             'phase' => '6',  'version' => '1.39.0' ],
            'thankyou'      => [ 'label' => 'Order confirmation refresh',                   'phase' => '7',  'version' => '1.39.1' ],
            'search'        => [ 'label' => 'Search + quick-view overlay',                  'phase' => '8',  'version' => '1.39.2' ],
            'account'       => [ 'label' => 'Account dashboard (7 tabs)',                   'phase' => '9',  'version' => '1.40.0' ],
            'auth'          => [ 'label' => 'Auth (login/signup/OTP/forgot)',               'phase' => '10', 'version' => '1.40.1' ],
            'tracking'      => [ 'label' => 'Shipment tracking page',                       'phase' => '11', 'version' => '1.41.0' ],
            'support'       => [ 'label' => 'Support / messaging',                          'phase' => '12', 'version' => '1.41.1' ],
            'compare'       => [ 'label' => 'Product comparison',                           'phase' => '13', 'version' => '1.41.2' ],
            'notifications' => [ 'label' => 'Notifications center + toasts',                'phase' => '14', 'version' => '1.41.3' ],
            'admin'         => [ 'label' => 'Admin dashboard',                              'phase' => '15', 'version' => '1.42.0' ],
            'mobile'        => [ 'label' => 'Mobile responsive polish',                     'phase' => '16', 'version' => '1.42.1' ],
            'states'        => [ 'label' => 'Empty/error states',                           'phase' => '17', 'version' => '1.42.2' ],
        ];
    }

    public static function active_phases() {
        $stored = get_option( self::OPTION_PHASES, [] );
        return is_array( $stored ) ? array_values( array_filter( array_map( 'sanitize_key', $stored ) ) ) : [];
    }

    public static function is_active( $phase ) {
        if ( get_option( self::OPTION_KILL, '0' ) === '1' ) { return false; }
        return in_array( (string) $phase, self::active_phases(), true );
    }

    public static function body_class( $classes ) {
        if ( get_option( self::OPTION_KILL, '0' ) === '1' ) { return $classes; }
        $active = self::active_phases();
        if ( ! empty( $active ) ) {
            $classes[] = 'ngrd-on';
            foreach ( $active as $slug ) {
                $classes[] = 'ngrd-' . $slug;
            }
        }
        return $classes;
    }

    public static function enqueue_assets() {
        if ( get_option( self::OPTION_KILL, '0' ) === '1' ) { return; }
        if ( empty( self::active_phases() ) ) { return; }
        $base_url = NEOGEN_PRO_URL . 'assets/redesign/';
        $base_dir = NEOGEN_PRO_DIR . 'assets/redesign/';

        $css = $base_dir . 'redesign.css';
        $js  = $base_dir . 'redesign.js';
        if ( file_exists( $css ) ) {
            wp_enqueue_style(
                'neogen-redesign',
                $base_url . 'redesign.css',
                [],
                (string) filemtime( $css )
            );
        }
        if ( file_exists( $js ) ) {
            wp_enqueue_script(
                'neogen-redesign',
                $base_url . 'redesign.js',
                [],
                (string) filemtime( $js ),
                true
            );
        }
    }

    /* =====================================================================
     * Admin UI
     * ===================================================================== */

    public static function admin_menu() {
        add_submenu_page(
            'neogen-pro',
            'Redesign',
            'Redesign',
            'manage_options',
            'neogen-pro-redesign',
            [ __CLASS__, 'admin_page' ]
        );
    }

    public static function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.' ); }
        $phases = self::phases();

        if ( isset( $_POST['ng_redesign_save'] ) && check_admin_referer( 'ng_redesign_save', 'ng_redesign_nonce' ) ) {
            $kill   = isset( $_POST['ng_redesign_killswitch'] ) ? '1' : '0';
            $active = isset( $_POST['ng_redesign_phase'] ) && is_array( $_POST['ng_redesign_phase'] )
                ? array_keys( wp_unslash( $_POST['ng_redesign_phase'] ) )
                : [];
            $active = array_values( array_intersect( $active, array_keys( $phases ) ) );
            update_option( self::OPTION_PHASES, $active );
            update_option( self::OPTION_KILL,   $kill );
            echo '<div class="updated notice"><p>Redesign settings saved.</p></div>';
        }

        $current_active = self::active_phases();
        $kill_on        = get_option( self::OPTION_KILL, '0' ) === '1';
        ?>
        <div class="wrap">
          <h1>NeoGen Redesign</h1>
          <p style="max-width:760px;font-size:13px;color:#444;">
            Per-phase feature flags for the Claude-design redesign rollout.
            Each phase corresponds to one customer-visible group of pages.
            Toggle ON to render the redesign for that area; toggle OFF to
            instantly fall back to current production.
          </p>
          <form method="post">
            <?php wp_nonce_field( 'ng_redesign_save', 'ng_redesign_nonce' ); ?>
            <h2 style="margin-top:24px;">Global kill switch</h2>
            <label style="display:flex;gap:10px;align-items:center;background:<?php echo $kill_on ? '#fff8e7' : '#f6f7f7'; ?>;border:1px solid #ccd0d4;padding:14px 16px;border-radius:6px;max-width:760px;">
              <input type="checkbox" name="ng_redesign_killswitch" value="1" <?php checked( $kill_on ); ?>>
              <span>
                <strong>Disable ALL redesign phases.</strong>
                One-click rollback for any redesign-related issue. Overrides every per-phase box below.
              </span>
            </label>

            <h2 style="margin-top:32px;">Phases</h2>
            <table class="widefat striped" style="max-width:1080px;">
              <thead>
                <tr>
                  <th style="width:40px;">On</th>
                  <th style="width:64px;">Phase</th>
                  <th>Description</th>
                  <th style="width:120px;">Version</th>
                  <th style="width:100px;">Slug</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $phases as $slug => $info ) : $on = in_array( $slug, $current_active, true ); ?>
                  <tr<?php echo $on ? ' style="background:#e7f5e9;"' : ''; ?>>
                    <td><input type="checkbox" name="ng_redesign_phase[<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( $on ); ?>></td>
                    <td><span style="font-family:Menlo,monospace;color:#646970;">P<?php echo esc_html( $info['phase'] ); ?></span></td>
                    <td><strong><?php echo esc_html( $info['label'] ); ?></strong></td>
                    <td><code><?php echo esc_html( $info['version'] ); ?></code></td>
                    <td><code><?php echo esc_html( $slug ); ?></code></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <p class="submit">
              <button type="submit" name="ng_redesign_save" value="1" class="button button-primary">Save settings</button>
            </p>
          </form>

          <h2 style="margin-top:32px;">Rollback chain</h2>
          <ol style="max-width:760px;font-size:13px;line-height:1.7;">
            <li><strong>First defence:</strong> uncheck the misbehaving phase here, click Save. Reverts immediately.</li>
            <li><strong>Second defence:</strong> check the global kill switch.</li>
            <li><strong>Third defence:</strong> click <em>Rollback −1 commit</em> at <a href="<?php echo esc_url( admin_url( 'tools.php?page=neogen-deploy' ) ); ?>">Tools → NeoGen Deploy</a>.</li>
          </ol>
        </div>
        <?php
    }

    /* =====================================================================
     * Icon library — 44 icons mirrored verbatim from icons.jsx
     * ===================================================================== */

    public static function icons_catalog() {
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

    public static function emit_icon_sprite() {
        static $emitted = false;
        if ( $emitted ) { return; }
        if ( get_option( self::OPTION_KILL, '0' ) === '1' ) { return; }
        if ( empty( self::active_phases() ) ) { return; }
        $emitted = true;
        $icons = self::icons_catalog();
        echo '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden;" aria-hidden="true" focusable="false"><defs>';
        foreach ( $icons as $name => $body ) {
            echo '<symbol id="ngrd-icon-' . sanitize_html_class( $name ) . '" viewBox="0 0 24 24">' . $body . '</symbol>';
        }
        echo '</defs></svg>';
    }

    /* =====================================================================
     * Add-ons & Replacements
     * ===================================================================== */

    public static function addon_types() {
        return [
            'upgrade'    => [ 'ar' => 'ترقية',     'en' => 'Upgrade' ],
            'consumable' => [ 'ar' => 'استهلاكي',  'en' => 'Consumable' ],
            'spare'      => [ 'ar' => 'قطعة غيار', 'en' => 'Spare part' ],
        ];
    }

    public static function get_addons( $product_id ) {
        $raw = get_post_meta( (int) $product_id, '_ng_addons', true );
        if ( empty( $raw ) ) { return []; }
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            if ( ! is_array( $decoded ) ) { return []; }
            $raw = $decoded;
        }
        if ( ! is_array( $raw ) ) { return []; }
        $valid_types = array_keys( self::addon_types() );
        $out = [];
        foreach ( $raw as $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $sku   = isset( $row['sku'] )   ? trim( (string) $row['sku'] )   : '';
            $type  = isset( $row['type'] )  ? (string) $row['type']          : '';
            $title = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
            if ( ! in_array( $type, $valid_types, true ) ) { $type = 'spare'; }
            if ( $sku === '' && $title === '' ) { continue; }
            $out[] = [ 'sku' => $sku, 'type' => $type, 'title' => $title ];
        }
        return $out;
    }

    public static function addons_metabox() {
        add_meta_box(
            'ng-product-addons',
            'NeoGen — Add-ons & Replacements',
            [ __CLASS__, 'addons_metabox_render' ],
            'product',
            'normal',
            'default'
        );
    }

    public static function addons_metabox_render( $post ) {
        wp_nonce_field( 'ng_product_addons_save', 'ng_product_addons_nonce' );
        $rows  = self::get_addons( $post->ID );
        $types = self::addon_types();
        if ( empty( $rows ) ) { $rows = [ [ 'sku' => '', 'type' => 'upgrade', 'title' => '' ] ]; }
        ?>
        <p style="margin:0 0 12px;font-size:12px;color:#666;">
          Each row links to another product by SKU. Title is optional — if blank and the SKU resolves, the linked product's own title is used. Empty rows are dropped on save. The PDP renderer activates only when the <code>pdp</code> phase is enabled at <a href="<?php echo esc_url( admin_url( 'admin.php?page=neogen-pro-redesign' ) ); ?>">NeoGen Pro → Redesign</a>.
        </p>
        <table class="widefat striped" id="ng-addons-table" style="max-width:880px;">
          <thead>
            <tr>
              <th style="width:30%;">SKU</th>
              <th style="width:20%;">Type</th>
              <th>Title (Arabic, optional)</th>
              <th style="width:60px;"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $rows as $i => $row ) : ?>
              <tr>
                <td><input type="text" name="ng_addons[<?php echo (int) $i; ?>][sku]" value="<?php echo esc_attr( $row['sku'] ); ?>" style="width:100%;font-family:monospace;" placeholder="NG-XXX-001"></td>
                <td>
                  <select name="ng_addons[<?php echo (int) $i; ?>][type]" style="width:100%;">
                    <?php foreach ( $types as $key => $label ) : ?>
                      <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $row['type'], $key ); ?>><?php echo esc_html( $label['en'] ); ?> · <?php echo esc_html( $label['ar'] ); ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="text" name="ng_addons[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $row['title'] ); ?>" style="width:100%;direction:rtl;text-align:right;font-family:Tajawal,sans-serif;" placeholder=""></td>
                <td><button type="button" class="button ng-addons-remove">×</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p style="margin:10px 0 0;">
          <button type="button" class="button button-secondary" id="ng-addons-add">+ Add row</button>
        </p>
        <script>
        (function () {
          var table = document.getElementById('ng-addons-table');
          if (!table) return;
          var tbody = table.querySelector('tbody');
          function reindex() {
            Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr, i) {
              Array.prototype.forEach.call(tr.querySelectorAll('input,select'), function (f) {
                f.name = f.name.replace(/ng_addons\[\d+\]/, 'ng_addons[' + i + ']');
              });
            });
          }
          document.getElementById('ng-addons-add').addEventListener('click', function () {
            var first = tbody.querySelector('tr');
            if (!first) return;
            var clone = first.cloneNode(true);
            Array.prototype.forEach.call(clone.querySelectorAll('input'), function (i) { i.value = ''; });
            Array.prototype.forEach.call(clone.querySelectorAll('select'), function (s) { s.selectedIndex = 0; });
            tbody.appendChild(clone);
            reindex();
          });
          tbody.addEventListener('click', function (e) {
            if (!e.target.classList.contains('ng-addons-remove')) return;
            var rows = tbody.querySelectorAll('tr');
            if (rows.length <= 1) {
              Array.prototype.forEach.call(rows[0].querySelectorAll('input'), function (i) { i.value = ''; });
            } else {
              e.target.closest('tr').remove();
            }
            reindex();
          });
        })();
        </script>
        <?php
    }

    public static function addons_save( $post_id ) {
        if ( ! isset( $_POST['ng_product_addons_nonce'] )
             || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ng_product_addons_nonce'] ) ), 'ng_product_addons_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

        $valid_types = array_keys( self::addon_types() );
        $rows_raw    = isset( $_POST['ng_addons'] ) && is_array( $_POST['ng_addons'] ) ? wp_unslash( $_POST['ng_addons'] ) : [];

        $clean = [];
        foreach ( $rows_raw as $r ) {
            if ( ! is_array( $r ) ) { continue; }
            $sku   = isset( $r['sku'] )   ? sanitize_text_field( $r['sku'] )   : '';
            $type  = isset( $r['type'] )  ? sanitize_key( $r['type'] )         : '';
            $title = isset( $r['title'] ) ? sanitize_text_field( $r['title'] ) : '';
            if ( ! in_array( $type, $valid_types, true ) ) { $type = 'spare'; }
            $sku   = trim( $sku );
            $title = trim( $title );
            if ( $sku === '' && $title === '' ) { continue; }
            $clean[] = [ 'sku' => $sku, 'type' => $type, 'title' => $title ];
        }
        if ( empty( $clean ) ) {
            delete_post_meta( $post_id, '_ng_addons' );
        } else {
            update_post_meta( $post_id, '_ng_addons', wp_json_encode( $clean ) );
        }
    }

    /**
     * Public renderer used by the PDP template (Phase 3). Returns ''
     * until the pdp phase is active.
     */
    public static function render_addons( $product_id ) {
        if ( ! self::is_active( 'pdp' ) ) { return ''; }
        $rows = self::get_addons( $product_id );
        if ( empty( $rows ) ) { return ''; }
        $types = self::addon_types();
        ob_start();
        ?>
        <section class="ngrd-pdp__addons" aria-labelledby="ngrd-addons-<?php echo (int) $product_id; ?>">
          <div class="ngrd-pdp__addons-head">
            <div>
              <div class="ngrd-section-mark"><span>B</span><span>الإضافات والاستبدال · ADD-ONS &amp; REPLACEMENTS</span></div>
              <h2 id="ngrd-addons-<?php echo (int) $product_id; ?>">ملحقات، قطع غيار، وترقيات لجهازك</h2>
            </div>
            <div class="ngrd-pdp__addons-filter" data-ngrd-addons-filter data-target="#ngrd-addons-grid-<?php echo (int) $product_id; ?>">
              <button type="button" data-filter="all" aria-pressed="true">الكل</button>
              <?php foreach ( $types as $key => $label ) : ?>
                <button type="button" data-filter="<?php echo esc_attr( $key ); ?>" aria-pressed="false"><?php echo esc_html( $label['ar'] ); ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="ngrd-pdp__addons-grid" id="ngrd-addons-grid-<?php echo (int) $product_id; ?>">
            <?php foreach ( $rows as $row ) :
              $resolved = function_exists( 'wc_get_product_id_by_sku' ) && $row['sku'] !== ''
                  ? wc_get_product( wc_get_product_id_by_sku( $row['sku'] ) )
                  : null;
              $title_ar = $row['title'];
              $title_en = '';
              $perm     = '';
              $price    = '';
              $img_html = '';
              if ( $resolved instanceof WC_Product ) {
                  $name_en = $resolved->get_name();
                  $name_ar = (string) get_post_meta( $resolved->get_id(), '_ng_ar_title', true );
                  if ( $name_ar === '' ) { $name_ar = $name_en; }
                  if ( $title_ar === '' ) { $title_ar = $name_ar; }
                  $title_en = ( $title_ar !== $name_en ) ? $name_en : '';
                  $perm     = get_permalink( $resolved->get_id() );
                  $price    = $resolved->get_price_html();
                  $img_id   = $resolved->get_image_id();
                  if ( $img_id ) {
                      $img_html = wp_get_attachment_image( $img_id, 'woocommerce_thumbnail', false, [ 'alt' => esc_attr( $name_en ) ] );
                  }
              }
              $type    = $row['type'];
              $type_ar = isset( $types[ $type ]['ar'] ) ? $types[ $type ]['ar'] : '';
            ?>
              <article class="ngrd-pdp__addon-card" data-type="<?php echo esc_attr( $type ); ?>">
                <a class="ngrd-pdp__addon-media" href="<?php echo $perm ? esc_url( $perm ) : '#'; ?>" aria-label="<?php echo esc_attr( $title_ar ); ?>">
                  <?php if ( $img_html ) {
                      echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                  } else { ?>
                    <span class="ngrd-r1-ph__label"><?php echo esc_html( $row['sku'] ?: $type_ar ); ?></span>
                  <?php } ?>
                </a>
                <div class="ngrd-pdp__addon-body">
                  <div class="ngrd-pdp__addon-meta">
                    <?php if ( $row['sku'] ) : ?>
                      <span class="ngrd-mono"><?php echo esc_html( strtoupper( $row['sku'] ) ); ?></span>
                    <?php endif; ?>
                    <span class="ngrd-pdp__addon-type" data-type="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type_ar ); ?></span>
                  </div>
                  <div class="ngrd-pdp__addon-title"><?php echo esc_html( $title_ar !== '' ? $title_ar : $row['sku'] ); ?></div>
                  <?php if ( $title_en ) : ?>
                    <div class="ngrd-pdp__addon-en"><?php echo esc_html( $title_en ); ?></div>
                  <?php endif; ?>
                  <div class="ngrd-pdp__addon-foot">
                    <div class="ngrd-price"><?php echo $price ? wp_kses_post( $price ) : '&nbsp;'; ?></div>
                    <?php if ( $perm ) : ?>
                      <a class="ngrd-btn ngrd-btn--sm" href="<?php echo esc_url( $perm ); ?>">عرض ←</a>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /* =====================================================================
     * Warranties
     * ===================================================================== */

    public static function warranty_product_field() {
        woocommerce_wp_text_input( [
            'id'          => '_ng_warranty_months',
            'label'       => __( 'NeoGen warranty (months)', 'neogen-pro' ),
            'placeholder' => (string) self::WARRANTY_DEFAULT_MONTHS,
            'desc_tip'    => true,
            'description' => __( 'Months of warranty for this product. Leave blank for the 12-month default. Captured per line at purchase time.', 'neogen-pro' ),
            'type'        => 'number',
            'custom_attributes' => [ 'min' => '0', 'step' => '1' ],
        ] );
    }

    public static function warranty_save_product_field( $post_id ) {
        if ( ! current_user_can( 'edit_product', $post_id ) ) { return; }
        if ( ! isset( $_POST['_ng_warranty_months'] ) ) {
            delete_post_meta( $post_id, '_ng_warranty_months' );
            return;
        }
        $val = trim( (string) wp_unslash( $_POST['_ng_warranty_months'] ) );
        if ( $val === '' ) {
            delete_post_meta( $post_id, '_ng_warranty_months' );
        } else {
            update_post_meta( $post_id, '_ng_warranty_months', max( 0, (int) $val ) );
        }
    }

    public static function warranty_capture_at_purchase( $item, $cart_item_key, $values, $order ) {
        if ( ! $item instanceof WC_Order_Item_Product ) { return; }
        $product = $item->get_product();
        if ( ! $product instanceof WC_Product ) { return; }
        $months = (int) get_post_meta( $product->get_id(), '_ng_warranty_months', true );
        if ( $months <= 0 ) { $months = self::WARRANTY_DEFAULT_MONTHS; }
        $item->add_meta_data( '_ng_warranty_months', $months, true );
    }

    public static function warranty_stamp_on_complete( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order instanceof WC_Order ) { return; }
        $now = current_time( 'timestamp', true );
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
            if ( $item->get_meta( '_ng_warranty_starts_at', true ) ) { continue; }
            if ( ! $item->meta_exists( '_ng_warranty_months' ) ) { continue; }
            $item->add_meta_data( '_ng_warranty_starts_at', $now, true );
            $item->save_meta_data();
        }
    }

    public static function get_warranties( $user_id = 0 ) {
        $user_id = (int) ( $user_id ?: get_current_user_id() );
        if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) { return []; }
        $orders = wc_get_orders( [
            'customer_id' => $user_id,
            'status'      => [ 'completed', 'processing' ],
            'limit'       => 50,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ] );
        if ( empty( $orders ) ) { return []; }
        $now = current_time( 'timestamp', true );
        $out = [];
        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) { continue; }
            foreach ( $order->get_items( 'line_item' ) as $item ) {
                if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
                $months = (int) $item->get_meta( '_ng_warranty_months', true );
                if ( $months <= 0 ) { continue; }
                $start = (int) $item->get_meta( '_ng_warranty_starts_at', true );
                if ( $start <= 0 ) {
                    $date_obj = $order->get_date_created();
                    $start    = $date_obj ? $date_obj->getTimestamp() : $now;
                }
                $end            = $start + ( $months * MONTH_IN_SECONDS );
                $remaining_secs = max( 0, $end - $now );
                $remaining_days = (int) round( $remaining_secs / DAY_IN_SECONDS );
                $total_days     = max( 1, (int) round( ( $months * MONTH_IN_SECONDS ) / DAY_IN_SECONDS ) );
                $progress_pct   = max( 0, min( 100, (int) round( ( $remaining_days / $total_days ) * 100 ) ) );
                $product        = $item->get_product();
                $out[] = [
                    'order_id'         => $order->get_id(),
                    'order_number'     => $order->get_order_number(),
                    'order_key'        => $order->get_order_key(),
                    'product_id'       => $item->get_product_id(),
                    'product_name'     => $item->get_name(),
                    'product_sku'      => $product ? $product->get_sku() : '',
                    'product_image_id' => $product ? $product->get_image_id() : 0,
                    'months'           => $months,
                    'starts_at'        => (int) $start,
                    'ends_at'          => (int) $end,
                    'remaining_days'   => $remaining_days,
                    'total_days'       => $total_days,
                    'progress_pct'     => $progress_pct,
                    'is_expired'       => $remaining_secs === 0,
                    'status_color'     => $progress_pct > 50 ? 'good' : ( $progress_pct > 20 ? 'warn' : 'sale' ),
                    'purchase_date'    => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
                ];
            }
        }
        return $out;
    }

    /* =====================================================================
     * Gift card keys (encrypted at rest)
     * ===================================================================== */

    private static function gck_key() {
        return hash( 'sha256', wp_salt( 'logged_in' ) . '|ng-gift-card-key|v1', true );
    }

    public static function gck_encrypt( $plain ) {
        $plain = (string) $plain;
        if ( $plain === '' || ! function_exists( 'openssl_encrypt' ) ) { return $plain; }
        $iv = random_bytes( 16 );
        $ct = openssl_encrypt( $plain, 'aes-256-ctr', self::gck_key(), OPENSSL_RAW_DATA, $iv );
        if ( $ct === false ) { return $plain; }
        return 'enc:v1:' . base64_encode( $iv . $ct );
    }

    public static function gck_decrypt( $cipher ) {
        $cipher = (string) $cipher;
        if ( strpos( $cipher, 'enc:v1:' ) !== 0 ) { return $cipher; }
        if ( ! function_exists( 'openssl_decrypt' ) ) { return ''; }
        $blob = base64_decode( substr( $cipher, 7 ), true );
        if ( $blob === false || strlen( $blob ) < 17 ) { return ''; }
        $iv = substr( $blob, 0, 16 );
        $ct = substr( $blob, 16 );
        $pt = openssl_decrypt( $ct, 'aes-256-ctr', self::gck_key(), OPENSSL_RAW_DATA, $iv );
        return $pt === false ? '' : $pt;
    }

    public static function gift_card_set_code( $order_id, $item_id, $code, $extras = [] ) {
        $order = wc_get_order( (int) $order_id );
        if ( ! $order instanceof WC_Order ) { return false; }
        $item = $order->get_item( (int) $item_id );
        if ( ! $item instanceof WC_Order_Item_Product ) { return false; }
        $code = trim( (string) $code );
        if ( $code === '' ) {
            $item->delete_meta_data( '_ng_gift_card_code' );
            $item->delete_meta_data( '_ng_gift_card_status' );
        } else {
            $item->update_meta_data( '_ng_gift_card_code',   self::gck_encrypt( $code ) );
            $item->update_meta_data( '_ng_gift_card_status', isset( $extras['status'] ) ? sanitize_key( $extras['status'] ) : 'active' );
        }
        if ( isset( $extras['expires_at'] ) ) {
            $item->update_meta_data( '_ng_gift_card_expires_at', (int) $extras['expires_at'] );
        }
        if ( isset( $extras['brand'] ) ) {
            $item->update_meta_data( '_ng_gift_card_brand', sanitize_text_field( $extras['brand'] ) );
        }
        if ( isset( $extras['region'] ) ) {
            $item->update_meta_data( '_ng_gift_card_region', sanitize_text_field( $extras['region'] ) );
        }
        $item->save_meta_data();
        return true;
    }

    public static function get_gift_card_keys( $user_id = 0 ) {
        $user_id = (int) ( $user_id ?: get_current_user_id() );
        if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) { return []; }
        $orders = wc_get_orders( [
            'customer_id' => $user_id,
            'status'      => [ 'completed', 'processing' ],
            'limit'       => 50,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ] );
        if ( empty( $orders ) ) { return []; }
        $now = current_time( 'timestamp', true );
        $out = [];
        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) { continue; }
            foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
                if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
                $cipher = (string) $item->get_meta( '_ng_gift_card_code', true );
                $product = $item->get_product();
                $is_gc = false;
                if ( $product instanceof WC_Product ) {
                    $cats  = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'slugs' ] );
                    $is_gc = ! is_wp_error( $cats ) && in_array( 'gift-cards', $cats, true );
                }
                if ( ! $is_gc && $cipher === '' ) { continue; }
                $expires_at = (int) $item->get_meta( '_ng_gift_card_expires_at', true );
                $out[] = [
                    'order_id'      => $order->get_id(),
                    'order_number'  => $order->get_order_number(),
                    'item_id'       => $item_id,
                    'product_id'    => $item->get_product_id(),
                    'product_name'  => $item->get_name(),
                    'product_sku'   => $product ? $product->get_sku() : '',
                    'brand'         => (string) $item->get_meta( '_ng_gift_card_brand',  true ),
                    'region'        => (string) $item->get_meta( '_ng_gift_card_region', true ),
                    'code'          => $cipher === '' ? '' : self::gck_decrypt( $cipher ),
                    'has_code'      => $cipher !== '',
                    'status'        => (string) ( $item->get_meta( '_ng_gift_card_status', true ) ?: ( $cipher === '' ? 'pending' : 'active' ) ),
                    'expires_at'    => $expires_at,
                    'is_expired'    => $expires_at > 0 && $now > $expires_at,
                    'purchase_date' => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
                ];
            }
        }
        return $out;
    }

    public static function gift_keys_metabox() {
        add_meta_box(
            'ng-order-gift-card-keys',
            'NeoGen — Gift Card Keys',
            [ __CLASS__, 'gift_keys_metabox_render' ],
            'shop_order',
            'normal',
            'default'
        );
        if ( function_exists( 'wc_get_page_screen_id' ) ) {
            $screen = wc_get_page_screen_id( 'shop-order' );
            if ( $screen ) {
                add_meta_box(
                    'ng-order-gift-card-keys',
                    'NeoGen — Gift Card Keys',
                    [ __CLASS__, 'gift_keys_metabox_render' ],
                    $screen,
                    'normal',
                    'default'
                );
            }
        }
    }

    public static function gift_keys_metabox_render( $post_or_order ) {
        $order = is_a( $post_or_order, 'WP_Post' ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
        if ( ! $order instanceof WC_Order ) { echo '<p>—</p>'; return; }
        wp_nonce_field( 'ng_gck_save_' . $order->get_id(), 'ng_gck_nonce' );
        ?>
        <p style="font-size:12px;color:#666;margin:0 0 10px;">Gift-card codes are encrypted at rest. Customers see the (decrypted) code in their account under <em>بطاقاتي · الأكواد</em> when the <code>account</code> redesign phase is enabled.</p>
        <table class="widefat striped">
          <thead><tr><th style="width:32%;">Item</th><th>Code</th><th style="width:120px;">Status</th><th style="width:140px;">Expires (YYYY-MM-DD)</th></tr></thead>
          <tbody>
            <?php foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) :
                if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
                $cipher  = (string) $item->get_meta( '_ng_gift_card_code', true );
                $code    = $cipher === '' ? '' : self::gck_decrypt( $cipher );
                $status  = (string) ( $item->get_meta( '_ng_gift_card_status', true ) ?: 'pending' );
                $expires = (int)    $item->get_meta( '_ng_gift_card_expires_at', true );
                $exp_str = $expires ? gmdate( 'Y-m-d', $expires ) : '';
            ?>
              <tr>
                <td><code style="font-size:11px;"><?php echo esc_html( $item->get_name() ); ?></code></td>
                <td><input type="text" name="ng_gck[<?php echo (int) $item_id; ?>][code]" value="<?php echo esc_attr( $code ); ?>" style="width:100%;font-family:monospace;" placeholder="XXXX-XXXX-XXXX-XXXX"></td>
                <td>
                  <select name="ng_gck[<?php echo (int) $item_id; ?>][status]" style="width:100%;">
                    <?php foreach ( [ 'pending', 'active', 'consumed' ] as $s ) : ?>
                      <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( $s ); ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input type="date" name="ng_gck[<?php echo (int) $item_id; ?>][expires]" value="<?php echo esc_attr( $exp_str ); ?>" style="width:100%;"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php
    }

    public static function gift_keys_save( $order_id ) {
        if ( ! isset( $_POST['ng_gck_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ng_gck_nonce'] ) ), 'ng_gck_save_' . (int) $order_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_shop_orders' ) ) { return; }
        $rows = isset( $_POST['ng_gck'] ) && is_array( $_POST['ng_gck'] ) ? wp_unslash( $_POST['ng_gck'] ) : [];
        foreach ( $rows as $item_id => $row ) {
            $code    = isset( $row['code'] )    ? sanitize_text_field( $row['code'] )    : '';
            $status  = isset( $row['status'] )  ? sanitize_key( $row['status'] )         : 'pending';
            $exp_str = isset( $row['expires'] ) ? sanitize_text_field( $row['expires'] ) : '';
            $exp_ts  = $exp_str ? strtotime( $exp_str . ' 23:59:59 UTC' ) : 0;
            self::gift_card_set_code(
                $order_id, (int) $item_id, $code,
                [ 'status' => $status, 'expires_at' => $exp_ts ?: 0 ]
            );
        }
    }

    /* =====================================================================
     * Compatibility-note helper for PDP "Works Best With" (Phase 3)
     * ===================================================================== */

    public static function compatibility_note( $source = null, $compat = null ) {
        $note = 'يعمل بشكل أفضل عند تشغيله مع هذه الوحدة المختارة من نفس الفئة.';
        if ( $source instanceof WC_Product ) {
            $cats = wp_get_post_terms( $source->get_id(), 'product_cat', [ 'fields' => 'slugs' ] );
            if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
                $first  = (string) reset( $cats );
                $by_cat = [
                    'networking' => 'مكوّن مكمّل للشبكة — تكامل مباشر مع الراوتر/السويتش بدون إعداد إضافي.',
                    'homelab'    => 'إضافة موصى بها للهوم لاب — توافق مُختبَر مع المنصات الشائعة.',
                    'smart-home' => 'يندمج مع منظومة البيت الذكي — Matter / HomeKit / Home Assistant.',
                    'gaming'     => 'مكوّن مكمّل لتجهيز الألعاب — أداء مُختبَر مع المنتج الرئيسي.',
                    'hardware'   => 'مكوّن مكمّل للجهاز — قابل للتركيب مباشرةً بدون إعداد إضافي.',
                    'gift-cards' => 'بطاقة مكمّلة — نفس المنطقة وتفعيل فوري.',
                ];
                if ( isset( $by_cat[ $first ] ) ) { $note = $by_cat[ $first ]; }
            }
        }
        return (string) apply_filters( 'ng_compatibility_note', $note, $source, $compat );
    }
}

/* ---------------------------------------------------------------------
 * Global helper functions — stable API for templates and other plugins.
 * Templates call these (not the class methods) so PHP files don't need
 * to know about the namespacing.
 * ------------------------------------------------------------------- */

if ( ! function_exists( 'ng_redesign_active' ) ) {
    function ng_redesign_active( $phase ) {
        return class_exists( 'NeoGen_Pro_Module_Redesign' )
            ? NeoGen_Pro_Module_Redesign::is_active( $phase )
            : false;
    }
}

if ( ! function_exists( 'ng_icon' ) ) {
    function ng_icon( $name, $size = 20, $extra_class = '' ) {
        if ( ! class_exists( 'NeoGen_Pro_Module_Redesign' ) ) { return ''; }
        $icons = NeoGen_Pro_Module_Redesign::icons_catalog();
        if ( ! isset( $icons[ $name ] ) ) { $name = 'close'; }
        $size  = (int) $size;
        $class = 'ngrd-icon ngrd-icon--' . sanitize_html_class( $name );
        if ( $extra_class !== '' ) { $class .= ' ' . esc_attr( $extra_class ); }
        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" aria-hidden="true" focusable="false">%s</svg>',
            esc_attr( $class ),
            $size,
            $size,
            $icons[ $name ]
        );
    }
}

if ( ! function_exists( 'ng_icon_use' ) ) {
    function ng_icon_use( $name, $size = 20, $extra_class = '' ) {
        if ( ! class_exists( 'NeoGen_Pro_Module_Redesign' ) ) { return ''; }
        $icons = NeoGen_Pro_Module_Redesign::icons_catalog();
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
}

if ( ! function_exists( 'ng_render_addons' ) ) {
    function ng_render_addons( $product_id ) {
        return class_exists( 'NeoGen_Pro_Module_Redesign' )
            ? NeoGen_Pro_Module_Redesign::render_addons( $product_id )
            : '';
    }
}

if ( ! function_exists( 'ng_get_warranties' ) ) {
    function ng_get_warranties( $user_id = 0 ) {
        return class_exists( 'NeoGen_Pro_Module_Redesign' )
            ? NeoGen_Pro_Module_Redesign::get_warranties( $user_id )
            : [];
    }
}

if ( ! function_exists( 'ng_get_gift_card_keys' ) ) {
    function ng_get_gift_card_keys( $user_id = 0 ) {
        return class_exists( 'NeoGen_Pro_Module_Redesign' )
            ? NeoGen_Pro_Module_Redesign::get_gift_card_keys( $user_id )
            : [];
    }
}

if ( ! function_exists( 'ng_compatibility_note' ) ) {
    function ng_compatibility_note( $source = null, $compat = null ) {
        return class_exists( 'NeoGen_Pro_Module_Redesign' )
            ? NeoGen_Pro_Module_Redesign::compatibility_note( $source, $compat )
            : '';
    }
}
