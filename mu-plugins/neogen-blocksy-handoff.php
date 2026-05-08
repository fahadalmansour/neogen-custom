<?php
/**
 * Plugin Name: NeoGen Blocksy Handoff
 * Description: Phase 2b/2c feature flags — (b) hand storefront header+footer to Blocksy header/footer builder, and (c) allow Blocksy dark-mode picker to drive color-scheme. Both default OFF. Toggle at Tools → NeoGen Blocksy Handoff.
 * Version: 1.21.2
 * Author: Fahad Almansour
 *
 * Why this exists
 * ---------------
 * The mu-plugin in neogen-theme.php replaces Blocksy's header + footer
 * entirely (see lines 1171–1249 and 1266–1370). That makes the Blocksy
 * Customize → Header / Footer panels useless — they edit chrome that
 * never renders.
 *
 * The same mu-plugin also force-locks the storefront to light mode
 * via an inline wp_head script + a stack of CSS hide rules — which
 * means the Blocksy Customize → Color Scheme picker is also dead UI.
 *
 * This plugin gates BOTH bypasses behind admin-toggleable options so
 * the operator can:
 *   (b) configure Blocksy header/footer builder, then flip the switch
 *       when chrome parity is reached, and
 *   (c) configure Blocksy color-scheme settings, then opt the site
 *       in to dark-mode-aware rendering.
 *
 * The CSS hide rules in neogen.css that hide ct-header/ct-footer are
 * scoped under .ng-mu-chrome (added by the body_class filter below).
 * The CSS dark-mode kill rules are scoped under .ng-light-only,
 * added by the same body_class filter when dark mode is not allowed.
 *
 * Helpers exposed to the rest of the codebase:
 *   ng_blocksy_chrome_handoff()    → bool: chrome handoff ON?
 *   ng_blocksy_chrome_mu_active()  → bool: mu-plugin chrome rendering?
 *   ng_blocksy_dark_mode_allowed() → bool: dark mode allowed?
 *
 * Each option also accepts a wp-config constant override for emergencies:
 *   NG_BLOCKSY_CHROME_HANDOFF
 *   NG_BLOCKSY_DARK_MODE_ALLOWED
 */

defined('ABSPATH') || exit;

const NG_BLOCKSY_HANDOFF_OPTION   = 'ng_blocksy_chrome_handoff';
const NG_BLOCKSY_DARK_MODE_OPTION = 'ng_blocksy_dark_mode_allowed';

/**
 * Phase 2b — chrome handoff. Option, with wp-config constant override.
 * Define `NG_BLOCKSY_CHROME_HANDOFF` in wp-config.php to force a value
 * (true or false) regardless of the admin option. Useful for emergencies.
 */
function ng_blocksy_chrome_handoff() {
    if ( defined('NG_BLOCKSY_CHROME_HANDOFF') ) {
        return (bool) NG_BLOCKSY_CHROME_HANDOFF;
    }
    return (bool) get_option(NG_BLOCKSY_HANDOFF_OPTION, false);
}

function ng_blocksy_chrome_mu_active() {
    return ! ng_blocksy_chrome_handoff();
}

/**
 * Phase 2c — dark mode. When false (default) the inline light-mode
 * lock script and the dark-mode-kill CSS rules in neogen.css fire.
 * When true, both step out of the way and Blocksy's color-scheme
 * picker takes over.
 */
function ng_blocksy_dark_mode_allowed() {
    if ( defined('NG_BLOCKSY_DARK_MODE_ALLOWED') ) {
        return (bool) NG_BLOCKSY_DARK_MODE_ALLOWED;
    }
    return (bool) get_option(NG_BLOCKSY_DARK_MODE_OPTION, false);
}

/**
 * Body classes:
 *   ng-mu-chrome  — present while mu-plugin chrome is rendering.
 *   ng-light-only — present while the light-mode lock is active.
 * Both are consumed by neogen.css to scope hide-rules so flipping
 * a toggle is a pure CSS change with no deploy needed.
 */
add_filter('body_class', function ($classes) {
    if ( ng_blocksy_chrome_mu_active() ) {
        $classes[] = 'ng-mu-chrome';
    }
    if ( ! ng_blocksy_dark_mode_allowed() ) {
        $classes[] = 'ng-light-only';
    }
    return $classes;
}, 10);

/**
 * Admin page — Tools → NeoGen Blocksy Handoff
 */
add_action('admin_menu', function () {
    add_management_page(
        'NeoGen Blocksy Handoff',
        'NeoGen Blocksy Handoff',
        'manage_options',
        'neogen-blocksy-handoff',
        'ng_blocksy_handoff_render'
    );
});

function ng_blocksy_handoff_render() {
    if ( ! current_user_can('manage_options') ) wp_die('forbidden');

    $forced_chrome = defined('NG_BLOCKSY_CHROME_HANDOFF');
    $forced_dark   = defined('NG_BLOCKSY_DARK_MODE_ALLOWED');

    // POST handler — both checkboxes saved on a single submit.
    if ( isset($_POST['ng_blocksy_handoff_nonce'])
        && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ng_blocksy_handoff_nonce'] ) ), 'ng_blocksy_handoff_save' ) ) {

        if ( ! $forced_chrome ) {
            $new = isset($_POST['ng_blocksy_handoff_enabled']) ? 1 : 0;
            update_option(NG_BLOCKSY_HANDOFF_OPTION, $new ? 1 : 0, false);
        }
        if ( ! $forced_dark ) {
            $new = isset($_POST['ng_blocksy_dark_mode_enabled']) ? 1 : 0;
            update_option(NG_BLOCKSY_DARK_MODE_OPTION, $new ? 1 : 0, false);
        }
        echo '<div class="notice notice-success is-dismissible"><p>Saved. Reload the storefront in a new tab to verify.</p></div>';
    }

    $on   = ng_blocksy_chrome_handoff();
    $dark = ng_blocksy_dark_mode_allowed();

    ?>
    <div class="wrap">
      <h1>NeoGen Blocksy Handoff</h1>
      <p>This switch controls whether Blocksy's header builder and footer builder render the storefront chrome (sysbar / nav / footer), or whether the mu-plugin in <code>neogen-theme.php</code> keeps doing it.</p>

      <h2>Current state</h2>
      <table class="widefat striped" style="max-width:720px;">
        <tbody>
          <tr><th>Chrome handoff (Phase 2b)</th><td><strong style="color:<?php echo $on ? '#22C55E' : '#EF4444'; ?>;"><?php echo $on ? 'ON — Blocksy header/footer' : 'OFF — mu-plugin header/footer'; ?></strong> · source: <?php echo $forced_chrome ? 'wp-config <code>NG_BLOCKSY_CHROME_HANDOFF</code>' : 'admin option <code>' . esc_html(NG_BLOCKSY_HANDOFF_OPTION) . '</code>'; ?></td></tr>
          <tr><th>Dark mode allowed (Phase 2c)</th><td><strong style="color:<?php echo $dark ? '#22C55E' : '#EF4444'; ?>;"><?php echo $dark ? 'ON — Blocksy color-scheme picker' : 'OFF — site locked to light'; ?></strong> · source: <?php echo $forced_dark ? 'wp-config <code>NG_BLOCKSY_DARK_MODE_ALLOWED</code>' : 'admin option <code>' . esc_html(NG_BLOCKSY_DARK_MODE_OPTION) . '</code>'; ?></td></tr>
          <tr><th>Body classes when OFF</th><td><code>ng-mu-chrome</code> (chrome OFF), <code>ng-light-only</code> (dark mode OFF) — both consumed by <code>neogen.css</code></td></tr>
        </tbody>
      </table>

      <h2 style="margin-top:2em;">Before turning ON</h2>
      <ol style="max-width:720px;">
        <li><strong>Open the storefront in another tab.</strong> Take screenshots of the header and footer as they look now — these are your reference.</li>
        <li>Go to <strong>Customize → Header</strong>. Build a header with: brand logo (Site Identity), main menu (existing nav), search/account/cart icons (Blocksy built-ins). Save (don't publish yet — Blocksy supports preview).</li>
        <li>Go to <strong>Customize → Footer</strong>. Build a footer with: logo column, support menu, info menu, store menu, payment-methods row, legal disclosure row.</li>
        <li>If you want a top sysbar (<code>الساعة الرياض</code> / queue counter / stock LED), add a row above the main header in <strong>Customize → Header</strong> and place a Custom HTML element with markup roughly like: <pre style="background:#f6f7f7;padding:8px;font-size:12px;">&lt;div class="ng-sysbar"&gt;
  &lt;span class="led" aria-hidden="true"&gt;&lt;/span&gt;
  &lt;span&gt;الساعة &lt;b id="ng-clock"&gt;00:00:00&lt;/b&gt; الرياض&lt;/span&gt;
  &lt;span class="sep"&gt;&lt;/span&gt;
  &lt;span&gt;المخزون &lt;b class="cyan"&gt;مباشر&lt;/b&gt;&lt;/span&gt;
&lt;/div&gt;</pre>The <code>neogen.js</code> clock IIFE will hydrate <code>#ng-clock</code> automatically.</li>
        <li>Publish in Customize. Verify in another tab. Only then come back here and flip the switch.</li>
      </ol>

      <form method="post" style="margin-top:2em;">
        <?php wp_nonce_field('ng_blocksy_handoff_save', 'ng_blocksy_handoff_nonce'); ?>

        <label style="display:flex;gap:.6em;align-items:flex-start;max-width:720px;<?php echo $forced_chrome ? 'opacity:.5;' : ''; ?>">
          <input type="checkbox" name="ng_blocksy_handoff_enabled" value="1" <?php checked($on); ?> <?php disabled($forced_chrome); ?> style="margin-top:.3em;">
          <span><strong>Hand storefront chrome over to Blocksy (Phase 2b)</strong><br>
          <small>When checked: the mu-plugin <code>wp_body_open</code> + <code>wp_footer</code> injectors stop firing, and the body class <code>ng-mu-chrome</code> is no longer added (so the CSS hide rules stop matching, exposing Blocksy chrome).</small></span>
        </label>

        <label style="display:flex;gap:.6em;align-items:flex-start;max-width:720px;margin-top:1.2em;<?php echo $forced_dark ? 'opacity:.5;' : ''; ?>">
          <input type="checkbox" name="ng_blocksy_dark_mode_enabled" value="1" <?php checked($dark); ?> <?php disabled($forced_dark); ?> style="margin-top:.3em;">
          <span><strong>Allow Blocksy dark mode (Phase 2c)</strong><br>
          <small>When checked: the inline <code>data-prefers-color-scheme=light</code> lock script stops emitting, and the body class <code>ng-light-only</code> is no longer added (so the dark-mode-kill CSS rules stop matching). Configure Blocksy's color-scheme settings in <strong>Customize → General Options → Color Scheme</strong> first.</small></span>
        </label>

        <p style="margin-top:1.5em;"><button class="button button-primary" type="submit">Save</button></p>

        <?php if ( $forced_chrome ) : ?>
          <p><small>Chrome toggle is forced by <code>define('NG_BLOCKSY_CHROME_HANDOFF', <?php echo NG_BLOCKSY_CHROME_HANDOFF ? 'true' : 'false'; ?>)</code> in <code>wp-config.php</code>. Remove that line to control from this page.</small></p>
        <?php endif; ?>
        <?php if ( $forced_dark ) : ?>
          <p><small>Dark-mode toggle is forced by <code>define('NG_BLOCKSY_DARK_MODE_ALLOWED', <?php echo NG_BLOCKSY_DARK_MODE_ALLOWED ? 'true' : 'false'; ?>)</code> in <code>wp-config.php</code>. Remove that line to control from this page.</small></p>
        <?php endif; ?>
      </form>

      <h2 style="margin-top:2em;">Rollback</h2>
      <p>If the storefront looks wrong after flipping ON: come back here, uncheck the box, save. The mu-plugin chrome returns instantly — no deploy needed.</p>
    </div>
    <?php
}

/**
 * Admin notice on the deploy page only — surfaces the current state.
 * Stops the operator from forgetting whether handoff is on or off.
 */
add_action('admin_notices', function () {
    if ( ! current_user_can('manage_options') ) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || $screen->id !== 'tools_page_neogen-deploy' ) return;

    $on   = ng_blocksy_chrome_handoff();
    $dark = ng_blocksy_dark_mode_allowed();
    $url  = admin_url('tools.php?page=neogen-blocksy-handoff');
    $chrome_color = $on   ? '#22C55E' : '#EF4444';
    $dark_color   = $dark ? '#22C55E' : '#EF4444';
    $chrome_label = $on   ? 'Blocksy chrome' : 'mu-plugin chrome';
    $dark_label   = $dark ? 'dark mode allowed' : 'light only';
    echo '<div class="notice notice-info"><p><strong>Storefront:</strong> '
        . '<span style="color:' . esc_attr($chrome_color) . ';">' . esc_html($chrome_label) . '</span>'
        . ' · <span style="color:' . esc_attr($dark_color) . ';">' . esc_html($dark_label) . '</span>'
        . ' — <a href="' . esc_url($url) . '">change</a></p></div>';
});
