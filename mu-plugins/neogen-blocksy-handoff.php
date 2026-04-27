<?php
/**
 * Plugin Name: NeoGen Blocksy Handoff
 * Description: Phase-2b feature flag — hand control of the storefront header + footer over to Blocksy's header builder / footer builder. When OFF (default), the mu-plugin continues to inject the custom sysbar/topnav/footer. When ON, those injectors no-op and Blocksy chrome takes over. Toggle at Tools → NeoGen Blocksy Handoff.
 * Version: 1.21.1
 * Author: Fahad Almansour
 *
 * Why this exists
 * ---------------
 * The mu-plugin in neogen-theme.php replaces Blocksy's header + footer
 * entirely (see lines 1171–1249 and 1266–1370). That makes the Blocksy
 * Customize → Header / Footer panels useless — they edit chrome that
 * never renders. This plugin gates those injectors on a single option
 * so the operator can configure Blocksy's builder offline first, then
 * flip the switch when the Blocksy chrome looks right.
 *
 * The CSS hide rules in neogen.css that hide ct-header/ct-footer are
 * scoped under .ng-mu-chrome (added by the body_class filter below)
 * so they only fire while the mu-plugin is in charge.
 *
 * Two helper functions are exposed to the rest of the codebase:
 *   ng_blocksy_chrome_handoff()         → bool: handoff currently ON?
 *   ng_blocksy_chrome_mu_active()       → bool: mu-plugin chrome currently rendering?
 *
 * They are inverses; use whichever reads better in context.
 */

defined('ABSPATH') || exit;

const NG_BLOCKSY_HANDOFF_OPTION = 'ng_blocksy_chrome_handoff';

/**
 * Single source of truth — option, with a wp-config constant override.
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
 * Add the `.ng-mu-chrome` body class while the mu-plugin owns the
 * chrome. The CSS hide rules in neogen.css depend on this class.
 */
add_filter('body_class', function ($classes) {
    if ( ng_blocksy_chrome_mu_active() ) {
        $classes[] = 'ng-mu-chrome';
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

    $forced = defined('NG_BLOCKSY_CHROME_HANDOFF');

    // POST handler
    if ( isset($_POST['ng_blocksy_handoff_nonce'])
        && wp_verify_nonce($_POST['ng_blocksy_handoff_nonce'], 'ng_blocksy_handoff_save')
        && ! $forced ) {
        $new = isset($_POST['ng_blocksy_handoff_enabled']) ? 1 : 0;
        update_option(NG_BLOCKSY_HANDOFF_OPTION, $new ? 1 : 0, false);
        echo '<div class="notice notice-success is-dismissible"><p>Saved. Reload the storefront in a new tab to verify.</p></div>';
    }

    $on = ng_blocksy_chrome_handoff();

    ?>
    <div class="wrap">
      <h1>NeoGen Blocksy Handoff</h1>
      <p>This switch controls whether Blocksy's header builder and footer builder render the storefront chrome (sysbar / nav / footer), or whether the mu-plugin in <code>neogen-theme.php</code> keeps doing it.</p>

      <h2>Current state</h2>
      <table class="widefat striped" style="max-width:720px;">
        <tbody>
          <tr><th>Handoff toggle</th><td><strong style="color:<?php echo $on ? '#1f9d57' : '#c14a1a'; ?>;"><?php echo $on ? 'ON — Blocksy chrome' : 'OFF — mu-plugin chrome'; ?></strong></td></tr>
          <tr><th>Source</th><td><?php echo $forced ? 'wp-config.php constant <code>NG_BLOCKSY_CHROME_HANDOFF</code> (overrides this UI)' : 'admin option <code>' . esc_html(NG_BLOCKSY_HANDOFF_OPTION) . '</code>'; ?></td></tr>
          <tr><th>Body class while OFF</th><td><code>ng-mu-chrome</code> — used by <code>neogen.css</code> to hide Blocksy header/footer</td></tr>
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

      <form method="post" style="margin-top:2em;<?php echo $forced ? 'opacity:.5;' : ''; ?>">
        <?php wp_nonce_field('ng_blocksy_handoff_save', 'ng_blocksy_handoff_nonce'); ?>
        <label style="display:flex;gap:.6em;align-items:flex-start;max-width:720px;">
          <input type="checkbox" name="ng_blocksy_handoff_enabled" value="1" <?php checked($on); ?> <?php disabled($forced); ?> style="margin-top:.3em;">
          <span><strong>Hand storefront chrome over to Blocksy</strong><br>
          <small>When checked: the mu-plugin <code>wp_body_open</code> + <code>wp_footer</code> injectors stop firing, and the body class <code>ng-mu-chrome</code> is no longer added (so the CSS hide rules stop matching, exposing Blocksy chrome).</small></span>
        </label>
        <p style="margin-top:1.5em;"><button class="button button-primary" type="submit" <?php disabled($forced); ?>>Save</button></p>
        <?php if ( $forced ) : ?>
          <p><small>The toggle is forced by <code>define('NG_BLOCKSY_CHROME_HANDOFF', <?php echo NG_BLOCKSY_CHROME_HANDOFF ? 'true' : 'false'; ?>)</code> in <code>wp-config.php</code>. Remove that line to control from this page.</small></p>
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

    $on = ng_blocksy_chrome_handoff();
    $url = admin_url('tools.php?page=neogen-blocksy-handoff');
    $color = $on ? '#1f9d57' : '#c14a1a';
    $label = $on ? 'Blocksy chrome' : 'mu-plugin chrome';
    echo '<div class="notice notice-info"><p><strong>Storefront chrome:</strong> <span style="color:' . esc_attr($color) . ';">' . esc_html($label) . '</span> — <a href="' . esc_url($url) . '">change</a></p></div>';
});
