<?php
/**
 * Plugin Name: NeoGen SEO Sitemap
 * Description: Force-enables WP core's native sitemap (Rank Math disables it by default) and 301-redirects Rank Math sitemap URLs to the WP core equivalents so external submissions (Search Console, Bing Webmaster, IndexNow feeds) keep working through the cutover.
 * Version: 1.22.2
 * Author: Fahad Almansour
 *
 * Phase R3 contract
 * -----------------
 * Two jobs:
 *   1. Re-enable wp_sitemaps even when Rank Math is active. Rank Math
 *      hooks `wp_sitemaps_enabled` and returns false to suppress WP
 *      core's sitemap so its own one wins. We override that filter at
 *      maximum priority so /wp-sitemap.xml always serves a real index.
 *   2. 301-redirect every Rank Math sitemap URL to the WP core
 *      equivalent before Rank Math's own handlers fire, so existing
 *      Search Console submissions continue to resolve.
 *
 * Mapping (Rank Math URL → WP core URL):
 *   /sitemap_index.xml    → /wp-sitemap.xml
 *   /sitemap.xml          → /wp-sitemap.xml   (some bots use this shape)
 *   /post-sitemap.xml     → /wp-sitemap-posts-post-1.xml
 *   /page-sitemap.xml     → /wp-sitemap-posts-page-1.xml
 *   /product-sitemap{N}.xml → /wp-sitemap-posts-product-1.xml
 *     (RM splits products at 1000 URLs into product-sitemap1, product-sitemap2;
 *     WP core's index lists separate paginated files, so any RM-style URL
 *     with a digit suffix lands on the first product sitemap and Google
 *     follows the index from there.)
 *   /category-sitemap.xml → /wp-sitemap-taxonomies-product_cat-1.xml
 *
 * What we deliberately don't do here
 * -----------------------------------
 * - We don't strip `noindex` posts from the sitemap yet. WP core
 *   already excludes private/draft/trash; the post-meta-driven
 *   noindex from the Phase R2 metabox is a refinement that can ship
 *   later without breaking anything.
 * - We don't emit image / news / video sitemaps. Rank Math may have
 *   been emitting an image sitemap; Google now derives image discovery
 *   from in-page <img> + the regular page sitemap, so this is OK.
 * - We don't suppress Rank Math's sitemap rewrite rules. They still
 *   register, but our 301 fires earlier so Rank Math never gets to
 *   render. Phase R4's Rank Math suppression filters will silence
 *   the rest.
 */

defined('ABSPATH') || exit;

/* =====================================================================
 * 1. Force WP core sitemap ON, regardless of what Rank Math wants.
 * ===================================================================== */

add_filter('wp_sitemaps_enabled', '__return_true', PHP_INT_MAX);

/* =====================================================================
 * 2. 301 redirects from Rank Math sitemap URLs → WP core equivalents.
 *    Hooks `init` priority 0 so we preempt Rank Math's own handlers.
 * ===================================================================== */

add_action('init', function () {
    if ( ! isset($_SERVER['REQUEST_URI']) ) return;
    $path = strtok( (string) $_SERVER['REQUEST_URI'], '?' );
    if ( $path === false || $path === '' ) return;

    // Exact-match map first.
    $exact = [
        '/sitemap_index.xml'    => '/wp-sitemap.xml',
        '/sitemap.xml'          => '/wp-sitemap.xml',
        '/post-sitemap.xml'     => '/wp-sitemap-posts-post-1.xml',
        '/page-sitemap.xml'     => '/wp-sitemap-posts-page-1.xml',
        '/category-sitemap.xml' => '/wp-sitemap-taxonomies-product_cat-1.xml',
    ];

    if ( isset($exact[$path]) ) {
        wp_safe_redirect( home_url($exact[$path]), 301 );
        exit;
    }

    // Pattern: product-sitemap1.xml, product-sitemap2.xml, ... → first product sitemap.
    if ( preg_match('#^/product-sitemap\d+\.xml$#', $path) ) {
        wp_safe_redirect( home_url('/wp-sitemap-posts-product-1.xml'), 301 );
        exit;
    }

    // Pattern: product_cat-sitemap.xml or any other category-* alias → product_cat sitemap.
    if ( $path === '/product_cat-sitemap.xml' || $path === '/product-cat-sitemap.xml' ) {
        wp_safe_redirect( home_url('/wp-sitemap-taxonomies-product_cat-1.xml'), 301 );
        exit;
    }

    // Pattern: product_tag-sitemap.xml → product_tag taxonomy sitemap.
    if ( $path === '/product_tag-sitemap.xml' || $path === '/product-tag-sitemap.xml' ) {
        wp_safe_redirect( home_url('/wp-sitemap-taxonomies-product_tag-1.xml'), 301 );
        exit;
    }
}, 0);

/* =====================================================================
 * 3. Tools → NeoGen SEO Sitemap — quick-status admin page.
 * ===================================================================== */

add_action('admin_menu', function () {
    add_management_page(
        'NeoGen SEO Sitemap',
        'NeoGen SEO Sitemap',
        'manage_options',
        'neogen-seo-sitemap',
        'ng_seo_sitemap_render'
    );
});

function ng_seo_sitemap_render() {
    if ( ! current_user_can('manage_options') ) wp_die('forbidden');

    // Probe the WP-core sitemap index. We only HEAD it via a local
    // HTTP request to avoid loading the full XML in the admin page.
    $home = home_url('/');
    $core_url = home_url('/wp-sitemap.xml');

    $rm_urls = [
        '/sitemap_index.xml',
        '/post-sitemap.xml',
        '/page-sitemap.xml',
        '/product-sitemap1.xml',
        '/category-sitemap.xml',
    ];

    ?>
    <div class="wrap">
      <h1>NeoGen SEO Sitemap</h1>
      <p>Phase R3 forces WP core's native sitemap on, and redirects every Rank Math sitemap URL to its WP core equivalent so existing Search Console submissions keep working.</p>

      <h2>WP core sitemap</h2>
      <table class="widefat striped" style="max-width:720px;">
        <tbody>
          <tr><th>Index URL</th><td><a href="<?php echo esc_url($core_url); ?>" target="_blank"><code><?php echo esc_html($core_url); ?></code></a></td></tr>
          <tr><th><code>wp_sitemaps_enabled</code></th><td><?php echo wp_sitemaps_get_server() ? '<strong style="color:#22C55E;">enabled</strong>' : '<strong style="color:#EF4444;">disabled</strong>'; ?></td></tr>
          <tr><th>Public post types</th><td><?php
            $pts = get_post_types(['public' => true], 'names');
            echo esc_html( implode(', ', $pts) );
          ?></td></tr>
        </tbody>
      </table>

      <h2 style="margin-top:1.5em;">Rank Math URL redirects</h2>
      <p>Each old Rank Math URL should return <code>301 Moved Permanently</code> with a <code>Location</code> header pointing at the WP core equivalent.</p>
      <table class="widefat striped" style="max-width:720px;">
        <thead><tr><th>Rank Math URL</th><th>Should 301 to</th><th>Test</th></tr></thead>
        <tbody>
          <?php
          $tests = [
            '/sitemap_index.xml'    => '/wp-sitemap.xml',
            '/post-sitemap.xml'     => '/wp-sitemap-posts-post-1.xml',
            '/page-sitemap.xml'     => '/wp-sitemap-posts-page-1.xml',
            '/product-sitemap1.xml' => '/wp-sitemap-posts-product-1.xml',
            '/category-sitemap.xml' => '/wp-sitemap-taxonomies-product_cat-1.xml',
          ];
          foreach ( $tests as $rm => $core ) :
            $rm_full   = home_url($rm);
            $core_full = home_url($core);
          ?>
          <tr>
            <td><a href="<?php echo esc_url($rm_full); ?>" target="_blank"><code><?php echo esc_html($rm); ?></code></a></td>
            <td><code><?php echo esc_html($core); ?></code></td>
            <td><a class="button button-small" href="<?php echo esc_url($rm_full); ?>" target="_blank">Open</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:1.5em;">Search Console action</h2>
      <ol style="max-width:720px;">
        <li>Open <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a> for <code><?php echo esc_html(parse_url($home, PHP_URL_HOST)); ?></code>.</li>
        <li>Sitemaps panel — confirm the existing <code>/sitemap_index.xml</code> submission still reads as <em>Success</em> (the 301 keeps it working).</li>
        <li>Submit the new canonical URL <code>/wp-sitemap.xml</code> as a fresh submission.</li>
        <li>After a week, you can delete the old <code>/sitemap_index.xml</code> submission.</li>
      </ol>

      <h2 style="margin-top:1.5em;">Verification (CLI)</h2>
      <pre style="background:#f6f7f7;padding:8px;font-size:12px;">curl -sI <?php echo esc_html($home); ?>sitemap_index.xml | head -5
# Expected: HTTP/2 301
#           location: <?php echo esc_html($home); ?>wp-sitemap.xml

curl -s <?php echo esc_html($home); ?>wp-sitemap.xml | head -20
# Expected: valid &lt;sitemapindex&gt; XML listing posts/pages/products/product_cat sub-sitemaps</pre>
    </div>
    <?php
}
