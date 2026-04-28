<?php
/**
 * Plugin Name: NeoGen SEO Cutover
 * Description: Phase R4 — admin toggle that activates real SEO emission from the in-house engine AND suppresses Rank Math's frontend output. Default OFF (Rank Math keeps emitting; engine stays in shadow). When ON, only the engine emits and Rank Math becomes a silent ghost ready for deactivation. Toggle at Tools → NeoGen SEO Cutover.
 * Version: 1.22.4
 * Author: Fahad Almansour
 *
 * Phase R4 contract
 * -----------------
 * Two complementary moves, gated by a single admin toggle (default OFF):
 *
 *   1. ENGINE EMISSION ON — the engine in neogen-seo-engine.php
 *      starts emitting real <title>, <meta name="description">,
 *      <link rel="canonical">, OG, Twitter, robots tags into <head>
 *      (instead of the parity-only HTML comments shipped in R1).
 *
 *   2. RANK MATH SUPPRESSION ON — every Rank Math frontend filter
 *      we know about returns empty, so RM stops emitting any meta
 *      tags or JSON-LD. RM still loads in PHP, runs admin UIs,
 *      manages its own settings — it just goes silent on the front.
 *
 * Both moves activate together. Either both or neither, gated by:
 *   - Admin option `ng_seo_engine_enabled` (default false)
 *   - wp-config constant `NG_SEO_ENGINE_ENABLED` (overrides option)
 *
 * Deactivation flow (after a 14-day soak with this toggle ON):
 *   wp-admin → Plugins → Rank Math SEO → Deactivate → Delete
 * No further code change. The engine never depended on Rank Math.
 *
 * Rollback: uncheck the toggle. Rank Math frontend output unblocks
 * instantly; engine stops emitting. No deploy needed.
 */

defined('ABSPATH') || exit;

const NG_SEO_CUTOVER_OPTION = 'ng_seo_engine_enabled';

/**
 * Single source of truth — option, with a wp-config constant override.
 */
function ng_seo_engine_enabled() {
    if ( defined('NG_SEO_ENGINE_ENABLED') ) {
        return (bool) NG_SEO_ENGINE_ENABLED;
    }
    return (bool) get_option(NG_SEO_CUTOVER_OPTION, false);
}

/* =====================================================================
 * REGISTRATION — only when the toggle is ON
 * ===================================================================== */

add_action('init', function () {
    if ( ! ng_seo_engine_enabled() ) return;
    if ( ! class_exists('NG_SEO_Engine') )    return; // engine plugin must be loaded

    // 1. Title — short-circuit document_title generation entirely.
    add_filter( 'pre_get_document_title', ['NG_SEO_Engine', 'title'], 99 );

    // 2. Meta tags + canonical + robots + OG + Twitter into <head>.
    add_action( 'wp_head', 'ng_seo_cutover_emit_meta', 2 );

    // 2b. JSON-LD @graph (R5). Late priority so it lands after meta.
    //     The legacy Store-only emitter in neogen-theme.php short-circuits
    //     itself via ng_seo_engine_enabled() to avoid dual emission.
    add_action( 'wp_head', ['NG_SEO_Engine', 'emit_json_ld'], 20 );

    // 3. Suppress Rank Math frontend output.
    foreach ( [
        'rank_math/frontend/title',
        'rank_math/frontend/description',
        'rank_math/frontend/canonical',
        'rank_math/frontend/breadcrumb/items',
        'rank_math/opengraph/facebook/og_title',
        'rank_math/opengraph/facebook/og_description',
        'rank_math/opengraph/facebook/og_url',
        'rank_math/opengraph/facebook/og_type',
        'rank_math/opengraph/facebook/og_image',
        'rank_math/opengraph/facebook/og_image_secure_url',
        'rank_math/opengraph/facebook/og_locale',
        'rank_math/opengraph/facebook/site_name',
        'rank_math/opengraph/twitter/twitter_title',
        'rank_math/opengraph/twitter/twitter_description',
        'rank_math/opengraph/twitter/twitter_image',
        'rank_math/opengraph/twitter/twitter_card',
    ] as $f ) {
        add_filter( $f, '__return_empty_string', 9999 );
    }
    add_filter( 'rank_math/json_ld',                    '__return_empty_array',  9999, 2 );
    add_filter( 'rank_math/opengraph/disable_facebook', '__return_true',         9999 );
    add_filter( 'rank_math/opengraph/disable_twitter',  '__return_true',         9999 );

    // Robots meta — Rank Math's `rank_math/frontend/robots` returns an
    // array; force it to a value that effectively no-ops. Our own
    // <meta name="robots"> emitted from ng_seo_cutover_emit_meta() is
    // canonical.
    add_filter( 'rank_math/frontend/robots', '__return_empty_array', 9999 );
});

/* =====================================================================
 * EMITTER — runs only when the toggle is ON, on every front-end request
 * ===================================================================== */

function ng_seo_cutover_emit_meta() {
    if ( is_admin() || is_customize_preview() ) return;
    if ( ! class_exists('NG_SEO_Engine') )      return;

    try {
        $desc   = NG_SEO_Engine::description();
        $canon  = NG_SEO_Engine::canonical();
        $robots = NG_SEO_Engine::robots();
        $og     = NG_SEO_Engine::og_meta();
        $tw     = NG_SEO_Engine::twitter_meta();
    } catch ( Throwable $e ) {
        echo "\n<!-- NG-SEO EMIT ERROR: " . esc_html($e->getMessage()) . " -->\n";
        return;
    }

    echo "\n<!-- NG-SEO emit · cutover ON · v" . esc_html(NG_SEO_Engine::VERSION) . " -->\n";

    if ( $desc !== '' ) {
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    }
    if ( $canon !== '' ) {
        echo '<link rel="canonical" href="' . esc_url($canon) . '">' . "\n";
    }
    if ( $robots !== '' ) {
        echo '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";
    }

    // Open Graph — distinguish property vs name for non-og: tags.
    foreach ( $og as $k => $v ) {
        if ( $v === '' || $v === null ) continue;
        $is_property = ( strpos($k, 'og:') === 0
                      || strpos($k, 'article:') === 0
                      || strpos($k, 'product:') === 0
                      || strpos($k, 'fb:') === 0 );
        $attr = $is_property ? 'property' : 'name';
        echo '<meta ' . $attr . '="' . esc_attr($k) . '" content="' . esc_attr( (string) $v ) . '">' . "\n";
    }

    // Twitter — always name=
    foreach ( $tw as $k => $v ) {
        if ( $v === '' || $v === null ) continue;
        echo '<meta name="' . esc_attr($k) . '" content="' . esc_attr( (string) $v ) . '">' . "\n";
    }

    echo "<!-- /NG-SEO emit -->\n";
}

/* =====================================================================
 * ADMIN — Tools → NeoGen SEO Cutover
 * ===================================================================== */

add_action('admin_menu', function () {
    add_management_page(
        'NeoGen SEO Cutover',
        'NeoGen SEO Cutover',
        'manage_options',
        'neogen-seo-cutover',
        'ng_seo_cutover_render'
    );
});

function ng_seo_cutover_render() {
    if ( ! current_user_can('manage_options') ) wp_die('forbidden');

    $forced = defined('NG_SEO_ENGINE_ENABLED');

    if ( isset($_POST['ng_seo_cutover_nonce'])
        && wp_verify_nonce( $_POST['ng_seo_cutover_nonce'], 'ng_seo_cutover_save' )
        && ! $forced ) {
        $new = isset($_POST['ng_seo_cutover_enabled']) ? 1 : 0;
        update_option(NG_SEO_CUTOVER_OPTION, $new ? 1 : 0, false);
        echo '<div class="notice notice-success is-dismissible"><p>Saved. Reload the storefront in another tab and view-source to verify the cutover.</p></div>';
    }

    $on = ng_seo_engine_enabled();
    $rm_active = is_plugin_active('seo-by-rank-math/rank-math.php');

    ?>
    <div class="wrap">
      <h1>NeoGen SEO Cutover</h1>
      <p>This is the cutover switch for Phase R4 of the Rank Math replacement plan. Default OFF — Rank Math continues to emit frontend SEO and our engine stays in shadow mode (parity comments only). When ON, the engine emits real meta tags into <code>&lt;head&gt;</code> and every Rank Math frontend filter we know about is forced to empty so Rank Math becomes a silent ghost.</p>

      <h2>Current state</h2>
      <table class="widefat striped" style="max-width:720px;">
        <tbody>
          <tr><th>Cutover toggle</th><td><strong style="color:<?php echo $on ? '#22C55E' : '#EF4444'; ?>;"><?php echo $on ? 'ON — engine emits, Rank Math suppressed' : 'OFF — Rank Math emits, engine in shadow'; ?></strong></td></tr>
          <tr><th>Source</th><td><?php echo $forced ? 'wp-config <code>NG_SEO_ENGINE_ENABLED</code> (overrides UI)' : 'admin option <code>' . esc_html(NG_SEO_CUTOVER_OPTION) . '</code>'; ?></td></tr>
          <tr><th>Engine class loaded</th><td><?php echo class_exists('NG_SEO_Engine') ? '<strong style="color:#22C55E;">yes</strong>' : '<strong style="color:#EF4444;">no — neogen-seo-engine.php not loaded</strong>'; ?></td></tr>
          <tr><th>Rank Math active</th><td><?php
            if ( ! function_exists('is_plugin_active') ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
            echo is_plugin_active('seo-by-rank-math/rank-math.php') ? 'yes' : 'no';
          ?></td></tr>
        </tbody>
      </table>

      <h2 style="margin-top:1.5em;">Before turning ON</h2>
      <ol style="max-width:720px;">
        <li>Confirm Phase R2 metabox is shipped (v1.22.1) — visit any post edit screen and verify the "NeoGen SEO" panel is present.</li>
        <li>Run the <a href="<?php echo esc_url( admin_url('tools.php?page=neogen-seo-migration') ); ?>">Rank Math → NeoGen SEO meta migration</a> at least once. Migrator is idempotent — safe to re-run.</li>
        <li>Confirm Phase R3 sitemaps are live — visit <a href="<?php echo esc_url( admin_url('tools.php?page=neogen-seo-sitemap') ); ?>">Tools → NeoGen SEO Sitemap</a>.</li>
        <li>(Optional) Define <code>NG_SEO_PARITY</code> in wp-config and view-source on a few surfaces to compare engine intent vs Rank Math actual output. Adjust per-post overrides if any look wrong.</li>
      </ol>

      <form method="post" style="margin-top:2em;<?php echo $forced ? 'opacity:.5;' : ''; ?>">
        <?php wp_nonce_field('ng_seo_cutover_save', 'ng_seo_cutover_nonce'); ?>
        <label style="display:flex;gap:.6em;align-items:flex-start;max-width:720px;">
          <input type="checkbox" name="ng_seo_cutover_enabled" value="1" <?php checked($on); ?> <?php disabled($forced); ?> style="margin-top:.3em;">
          <span><strong>Activate the in-house SEO engine (Phase R4)</strong><br>
          <small>When checked: real <code>&lt;title&gt;</code>, <code>&lt;meta&gt;</code>, OG, Twitter, canonical, robots tags AND a full JSON-LD <code>@graph</code> (Store + WebSite + WebPage + per-surface nodes) emit from <code>NG_SEO_Engine</code>; Rank Math frontend filters all forced to empty; legacy Store JSON-LD in <code>neogen-theme.php</code> short-circuits to avoid dual emission.</small></span>
        </label>
        <p style="margin-top:1.5em;"><button class="button button-primary" type="submit" <?php disabled($forced); ?>>Save</button></p>
        <?php if ( $forced ) : ?>
          <p><small>Toggle is forced by <code>define('NG_SEO_ENGINE_ENABLED', <?php echo NG_SEO_ENGINE_ENABLED ? 'true' : 'false'; ?>)</code> in <code>wp-config.php</code>. Remove that line to control from this page.</small></p>
        <?php endif; ?>
      </form>

      <h2 style="margin-top:2em;">After turning ON — soak window</h2>
      <ol style="max-width:720px;">
        <li>Run the live audit:
          <pre style="background:#f6f7f7;padding:8px;font-size:12px;">curl -s https://neogen.store/ | grep -c 'NG-SEO emit'  # expect &gt;= 1
curl -s https://neogen.store/ | grep -c 'rank_math\|rankmath'  # expect 0 or comments-only
curl -s https://neogen.store/ | grep -oE '&lt;meta name="robots"[^&gt;]+&gt;'  # single tag, index/follow</pre>
        </li>
        <li>Spot-check 5 surfaces (home / product / category / page / search). Title + description + canonical match the engine's intent.</li>
        <li>Search Console — submit <code>/wp-sitemap.xml</code> if not already done in Phase R3.</li>
        <li><strong>Wait 14 days.</strong> Monitor Search Console for crawl errors, GA / Site Kit for traffic dips. If something breaks, uncheck the toggle — instant rollback.</li>
        <li>After the soak, deactivate Rank Math: Plugins → Rank Math SEO → Deactivate → Delete. No further code change needed.</li>
      </ol>

      <h2 style="margin-top:2em;">Rollback</h2>
      <p>Uncheck the toggle on this page and click Save. Rank Math's frontend output unblocks instantly; engine emission stops. The site reverts to its pre-R4 state with no deploy needed.</p>
    </div>
    <?php
}

/* =====================================================================
 * Status notice on the deploy admin page
 * ===================================================================== */

add_action('admin_notices', function () {
    if ( ! current_user_can('manage_options') ) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || $screen->id !== 'tools_page_neogen-deploy' ) return;

    $on  = ng_seo_engine_enabled();
    $url = admin_url('tools.php?page=neogen-seo-cutover');
    $color = $on ? '#22C55E' : '#EF4444';
    $label = $on ? 'engine ON · Rank Math suppressed' : 'engine OFF · Rank Math emits';
    echo '<div class="notice notice-info"><p><strong>SEO emission:</strong> '
        . '<span style="color:' . esc_attr($color) . ';">' . esc_html($label) . '</span>'
        . ' — <a href="' . esc_url($url) . '">change</a></p></div>';
});
