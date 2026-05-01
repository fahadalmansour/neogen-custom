<?php
/**
 * Plugin Name: NeoGen Redesign Controller
 * Description: Feature-flag plumbing for the Claude-design redesign rollout. Provides ng_redesign_active($phase), the .ngrd-on body class kill-switch, conditional enqueue of redesign.css/redesign.js, and the admin UI at Tools → NeoGen Redesign for toggling phases. Default: every phase is OFF — shipping this plugin causes ZERO visible change to the customer-facing site. Each phase's templates check ng_redesign_active() before emitting redesign markup.
 * Version: 1.38.0
 * Author: Fahad Almansour
 *
 * Phases (matches /Users/fahadalmansour/.claude/plans/fetch-this-design-file-kind-pizza.md):
 *   homepage     · Hero rotating gallery + trust strip
 *   shop         · Active-filter chip bar
 *   pdp          · Works Best With + Add-ons + tabs
 *   cart         · 3-step indicator
 *   checkout     · Stepper + carrier styling
 *   gift_cards   · Multi-region showcase
 *   thankyou     · Order confirmation refresh
 *   search       · Quick-view overlay
 *   account      · 7-tab dashboard
 *   auth         · Login/signup/OTP/forgot
 *   tracking     · Shipment tracking page
 *   support      · Ticket-based support
 *   compare      · 4-product comparison
 *   notifications · Notifications center + toasts
 *   admin        · NeoGen admin dashboard
 *   mobile       · Responsive enhancements ≤720px
 *   states       · Empty/error states
 */

defined('ABSPATH') || exit;

const NG_REDESIGN_OPTION = 'ng_redesign_phases';
const NG_REDESIGN_KILL   = 'ng_redesign_killswitch';

/**
 * Catalogue of phases. Keys persist; English labels render in admin UI.
 * The 'default' value isn't used at runtime (default is always OFF) but
 * documents which phases are intended-on for a given site posture.
 */
function ng_redesign_phases() {
    return [
        'homepage'      => [ 'label' => 'Homepage hero gallery + trust strip',          'phase' => '1',  'version' => '1.38.1' ],
        'shop'          => [ 'label' => 'Shop active-filter chip bar',                  'phase' => '2',  'version' => '1.38.2' ],
        'pdp'           => [ 'label' => 'PDP — Works Best With + Add-ons + tabs',       'phase' => '3',  'version' => '1.38.3' ],
        'cart'          => [ 'label' => 'Cart 3-step indicator',                        'phase' => '4',  'version' => '1.38.4' ],
        'checkout'      => [ 'label' => 'Checkout stepper + carrier styling',           'phase' => '5',  'version' => '1.38.5' ],
        'gift_cards'    => [ 'label' => 'Gift Cards multi-region showcase',             'phase' => '6',  'version' => '1.39.0' ],
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

/**
 * Return the active-phase set as an array of slugs.
 */
function ng_redesign_active_phases() {
    $stored = get_option( NG_REDESIGN_OPTION, [] );
    return is_array( $stored ) ? array_values( array_filter( array_map( 'sanitize_key', $stored ) ) ) : [];
}

/**
 * Is the named phase active?
 *
 * Returns false unconditionally when the global kill-switch is on so a
 * single click in admin disables every redesign phase at once.
 */
function ng_redesign_active( $phase ) {
    if ( get_option( NG_REDESIGN_KILL, '0' ) === '1' ) {
        return false;
    }
    $active = ng_redesign_active_phases();
    return in_array( (string) $phase, $active, true );
}

/**
 * Body class — gates ALL redesign CSS rules so a flipped flag also
 * removes the styling without needing a code redeploy.
 */
add_filter( 'body_class', function ( $classes ) {
    if ( get_option( NG_REDESIGN_KILL, '0' ) === '1' ) {
        return $classes;
    }
    $active = ng_redesign_active_phases();
    if ( ! empty( $active ) ) {
        $classes[] = 'ngrd-on';
        foreach ( $active as $slug ) {
            $classes[] = 'ngrd-' . $slug;
        }
    }
    return $classes;
} );

/**
 * Enqueue redesign.css and redesign.js — only when at least one phase
 * is active. Filemtime-versioned for cache-busting; loaded after
 * neogen-theme.css so its rules can override the existing ones.
 */
add_action( 'wp_enqueue_scripts', function () {
    if ( get_option( NG_REDESIGN_KILL, '0' ) === '1' ) {
        return;
    }
    if ( empty( ng_redesign_active_phases() ) ) {
        return;
    }
    if ( ! defined( 'NG_THEME_ASSET_URL' ) || ! defined( 'NG_THEME_ASSET_DIR' ) ) {
        return;
    }
    $css_path = NG_THEME_ASSET_DIR . '/redesign.css';
    $js_path  = NG_THEME_ASSET_DIR . '/redesign.js';
    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'neogen-redesign',
            NG_THEME_ASSET_URL . '/redesign.css',
            [ 'neogen-theme' ],
            (string) filemtime( $css_path )
        );
    }
    if ( file_exists( $js_path ) ) {
        wp_enqueue_script(
            'neogen-redesign',
            NG_THEME_ASSET_URL . '/redesign.js',
            [],
            (string) filemtime( $js_path ),
            true
        );
    }
}, 30 );

/* ---------------------------------------------------------------------
 * Admin UI — Tools → NeoGen Redesign
 * ------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
    add_management_page(
        'NeoGen Redesign',
        'NeoGen Redesign',
        'manage_options',
        'neogen-redesign',
        'ng_redesign_admin_page'
    );
} );

function ng_redesign_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Access denied.' ); }

    $phases = ng_redesign_phases();

    if ( isset( $_POST['ng_redesign_save'] ) && check_admin_referer( 'ng_redesign_save', 'ng_redesign_nonce' ) ) {
        $kill   = isset( $_POST['ng_redesign_killswitch'] ) ? '1' : '0';
        $active = isset( $_POST['ng_redesign_phase'] ) && is_array( $_POST['ng_redesign_phase'] )
            ? array_keys( wp_unslash( $_POST['ng_redesign_phase'] ) )
            : [];
        $active = array_values( array_intersect( $active, array_keys( $phases ) ) );
        update_option( NG_REDESIGN_OPTION, $active );
        update_option( NG_REDESIGN_KILL,   $kill );
        echo '<div class="updated notice"><p>Redesign settings saved.</p></div>';
    }

    $current_active = ng_redesign_active_phases();
    $kill_on        = get_option( NG_REDESIGN_KILL, '0' ) === '1';
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
            When checked, no phase renders regardless of the per-phase boxes below. Use this as a one-click rollback for any redesign-related issue.
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
              <th style="width:80px;">Slug</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $phases as $slug => $info ) : $on = in_array( $slug, $current_active, true ); ?>
              <tr<?php echo $on ? ' style="background:#e7f5e9;"' : ''; ?>>
                <td><input type="checkbox" name="ng_redesign_phase[<?php echo esc_attr( $slug ); ?>]" value="1" <?php checked( $on ); ?>></td>
                <td><span class="ngrd-phase-num" style="font-family:Menlo,monospace;color:#646970;">P<?php echo esc_html( $info['phase'] ); ?></span></td>
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

      <h2 style="margin-top:32px;">How rollback works</h2>
      <ol style="max-width:760px;font-size:13px;line-height:1.7;">
        <li><strong>First defence:</strong> uncheck the misbehaving phase here, click Save. Reverts immediately, no deploy needed.</li>
        <li><strong>Second defence:</strong> check the global kill switch. Disables every phase at once.</li>
        <li><strong>Third defence:</strong> click <em>Rollback −1 commit</em> at <a href="<?php echo esc_url( admin_url( 'tools.php?page=neogen-deploy' ) ); ?>">Tools → NeoGen Deploy</a>.</li>
      </ol>
    </div>
    <?php
}
