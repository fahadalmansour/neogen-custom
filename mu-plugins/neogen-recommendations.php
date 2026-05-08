<?php
/**
 * Plugin Name: NeoGen Recommendations
 * Description: Cookie-based recently-viewed tracking and rule-based product recommendations. No ML, no third-party. Renders themed .ng-rec-strip below related products on single-product pages, and via [neogen_recommendations] shortcode anywhere. Admin test mode: ?ng_simulate_recent=12,15,22 to preview the engine.
 * Version: 1.42.0
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

if (!defined('NG_REC_COOKIE'))    { define('NG_REC_COOKIE', 'ng_recent'); }
if (!defined('NG_REC_MAX'))       { define('NG_REC_MAX', 12); }
if (!defined('NG_REC_TTL_DAYS'))  { define('NG_REC_TTL_DAYS', 30); }

/* ----------------------------------------------------------------
   1. Track recently-viewed product IDs in a cookie.

   Implementation detail (changed v1.42.0 — readiness-2026-05-08 BLOCKER #2):
   the cookie is set client-side via a tiny inline JS in the page footer,
   not server-side via setcookie() in template_redirect.

   Why: a server-emitted Set-Cookie on every cold product-page GET makes
   LiteSpeed Cache treat the response as private and refuses to serve it
   from cache to other anonymous visitors. Moving the cookie write to
   client-side JS keeps the HTML response cookie-free, so LSCache can
   serve the same cached HTML to every anonymous visitor (cache HIT
   instead of MISS).

   Trade-off: the recommendations strip on the *current* page won't yet
   include the product just being viewed in its history — but that's
   fine because the strip is "you might also like", not "you're looking
   at this". The next product page picks up the previous IDs as before.

   The reader (ng_rec_read_cookie) still reads $_COOKIE[NG_REC_COOKIE]
   server-side, populated by the JS write on the previous request.
   HttpOnly is dropped (JS needs to write it), but the cookie carries
   only product IDs — no auth, no PII — so reducing it to non-HttpOnly
   is acceptable. Secure + SameSite=Lax retained.
   ---------------------------------------------------------------- */
add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!function_exists('is_product') || !is_product()) return;

    $id = (int) get_queried_object_id();
    if (!$id) return;

    // ng_recent is a comma-separated list of product IDs, newest first,
    // capped at NG_REC_MAX entries, retained for NG_REC_TTL_DAYS days.
    $cookie_name = wp_json_encode( NG_REC_COOKIE );
    $product_id  = (int) $id;
    $max         = (int) NG_REC_MAX;
    $ttl_days    = (int) NG_REC_TTL_DAYS;
    $secure_attr = is_ssl() ? '; Secure' : '';
    ?>
    <script>
    (function () {
        try {
            var name = <?php echo $cookie_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_json_encode output is JSON-safe ?>;
            var id   = <?php echo $product_id; ?>;
            var max  = <?php echo $max; ?>;
            var days = <?php echo $ttl_days; ?>;

            var raw = '';
            var m   = document.cookie.split('; ').find(function (r) { return r.indexOf(name + '=') === 0; });
            if (m) raw = m.substring(name.length + 1);

            var ids = raw ? raw.split(',').map(function (n) { return parseInt(n, 10); }).filter(function (n) { return n > 0; }) : [];
            ids = ids.filter(function (n) { return n !== id; });
            ids.unshift(id);
            if (ids.length > max) ids = ids.slice(0, max);

            var d = new Date();
            d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
            document.cookie = name + '=' + ids.join(',')
                + '; expires=' + d.toUTCString()
                + '; path=/; SameSite=Lax<?php echo esc_js( $secure_attr ); ?>';
        } catch (e) { /* fail silent — recommendations gracefully degrade */ }
    })();
    </script>
    <?php
}, 99);

/* ----------------------------------------------------------------
   2. Read the recent-viewed list. Admin test mode supported via
      ?ng_simulate_recent=ID,ID,ID — only honored for users who
      can manage_options, so customers can't poison their own
      recommendations via URL.
   ---------------------------------------------------------------- */
function ng_rec_read_cookie() {
    if (current_user_can('manage_options')
        && isset($_GET['ng_simulate_recent'])
        && is_string($_GET['ng_simulate_recent'])) {
        $sim = sanitize_text_field((string) wp_unslash($_GET['ng_simulate_recent']));
        $ids = array_filter(array_map('intval', explode(',', $sim)));
        return array_values($ids);
    }

    if (!isset($_COOKIE[NG_REC_COOKIE])) return [];
    $raw = sanitize_text_field((string) wp_unslash($_COOKIE[NG_REC_COOKIE]));
    $ids = array_filter(array_map('intval', explode(',', $raw)));
    return array_values($ids);
}

function ng_recent_product_ids($exclude = 0) {
    $ids = ng_rec_read_cookie();
    if ($exclude) {
        $ex = (int) $exclude;
        $ids = array_values(array_filter($ids, function ($x) use ($ex) {
            return (int) $x !== $ex;
        }));
    }
    return $ids;
}

/* ----------------------------------------------------------------
   3. Recommendation engine.
      Strategy:
        a) From the most-recent N viewed products, collect their
           product_cat term IDs.
        b) Query published products in those cats, ordered by
           popularity (total_sales meta), excluding recent-viewed
           ids and the current product. Take up to $limit.
        c) If still short, top up with featured products.
        d) If still short, top up with latest products.
   ---------------------------------------------------------------- */
function ng_recommended_products($exclude = 0, $limit = 4) {
    if (!function_exists('wc_get_products')) return [];
    $limit = max(1, (int) $limit);

    $recent       = ng_recent_product_ids($exclude);
    $exclude_ids  = array_unique(array_filter(array_merge(
        [(int) $exclude],
        $recent
    )));
    $exclude_ids  = array_values(array_map('intval', $exclude_ids));

    /* a) Category seeds — from up to 4 most recent. */
    $cat_ids = [];
    foreach (array_slice($recent, 0, 4) as $rid) {
        $terms = wp_get_post_terms((int) $rid, 'product_cat', ['fields' => 'ids']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $cat_ids = array_merge($cat_ids, array_map('intval', $terms));
        }
    }
    $cat_ids = array_values(array_unique(array_filter($cat_ids)));

    $picks      = [];
    $picked_ids = [];

    /* b) Same-category popular — only if we have categories.
     *    Single batched wc_get_products() call returning WC_Product
     *    objects directly (visibility-filtered by Woo). Replaces the
     *    prior WP_Query + per-row wc_get_product() lookup loop, which
     *    was the N+1 the 2026-05-08 audit flagged. */
    if (!empty($cat_ids)) {
        $candidates = wc_get_products([
            'status'   => 'publish',
            'limit'    => $limit * 2, // overshoot, dedupe + visibility-filter below
            'exclude'  => $exclude_ids,
            'category' => array_map(function ($tid) {
                $term = get_term((int) $tid, 'product_cat');
                return ($term && !is_wp_error($term)) ? $term->slug : null;
            }, $cat_ids),
            'orderby'  => 'meta_value_num',
            'meta_key' => 'total_sales',
            'order'    => 'DESC',
            'visibility' => 'visible', // catalog-visible only
        ]);
        $candidates = array_filter($candidates ?: []);
        foreach ($candidates as $p) {
            if (count($picks) >= $limit) break;
            if (!$p instanceof WC_Product) continue;
            if (in_array($p->get_id(), $picked_ids, true)) continue;
            $picks[]      = $p;
            $picked_ids[] = $p->get_id();
        }
    }

    /* c) Top up from featured. */
    if (count($picks) < $limit && function_exists('wc_get_featured_product_ids')) {
        $featured = wc_get_featured_product_ids();
        $featured = array_values(array_diff($featured, $exclude_ids, $picked_ids));
        if (!empty($featured)) {
            $more = wc_get_products([
                'status'  => 'publish',
                'limit'   => $limit - count($picks),
                'include' => $featured,
            ]);
            foreach ((array) $more as $p) {
                if (!$p instanceof WC_Product || !$p->is_visible()) continue;
                if (in_array($p->get_id(), $picked_ids, true)) continue;
                $picks[]      = $p;
                $picked_ids[] = $p->get_id();
                if (count($picks) >= $limit) break;
            }
        }
    }

    /* d) Top up from latest. */
    if (count($picks) < $limit) {
        $more = wc_get_products([
            'status'  => 'publish',
            'limit'   => ($limit - count($picks)) * 2,
            'exclude' => array_values(array_unique(array_merge($exclude_ids, $picked_ids))) ?: [0],
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);
        foreach ((array) $more as $p) {
            if (!$p instanceof WC_Product || !$p->is_visible()) continue;
            if (in_array($p->get_id(), $picked_ids, true)) continue;
            $picks[]      = $p;
            $picked_ids[] = $p->get_id();
            if (count($picks) >= $limit) break;
        }
    }

    return array_slice($picks, 0, $limit);
}

/**
 * v1.38.0 — generic compatibility-note resolver for the Phase 3 PDP
 * "Works Best With" green box. Returns a short Arabic explanation of
 * why a recommended product pairs well with the source product. Default
 * copy is generic; site operators can override per (source, compat)
 * pair via the `ng_compatibility_note` filter.
 *
 * @param WC_Product|null $source   The PDP product the user is viewing.
 * @param WC_Product|null $compat   The recommended companion product.
 * @return string                   Filtered Arabic copy.
 */
function ng_compatibility_note( $source = null, $compat = null ) {
    $note = 'يعمل بشكل أفضل عند تشغيله مع هذه الوحدة المختارة من نفس الفئة.';

    // Category-aware default — try to pick a richer line when the
    // source product belongs to a known category.
    if ( $source instanceof WC_Product ) {
        $cats = wp_get_post_terms( $source->get_id(), 'product_cat', [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
            $first = (string) reset( $cats );
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

    /**
     * Filter the per-pair compatibility note shown in the PDP Works Best
     * With section. Most operators will override this for specific SKU
     * pairs (e.g. router + AP) where a hand-crafted line works better.
     *
     * @param string          $note   Default note.
     * @param WC_Product|null $source The PDP product.
     * @param WC_Product|null $compat The recommended companion.
     */
    return (string) apply_filters( 'ng_compatibility_note', $note, $source, $compat );
}

/* ----------------------------------------------------------------
   4. Renderer — themed strip reusing the .ng-product card via
      Woo's template_part loop. Output captured via ob_*().
   ---------------------------------------------------------------- */
function ng_render_recommendations($args = []) {
    $args = wp_parse_args($args, [
        'exclude'  => 0,
        'limit'    => 4,
        'title_ar' => 'مقترحات لك',
        'title_en' => 'RECOMMENDED FOR YOU',
        'kicker'   => 'OPERATOR · NEXT PICKS',
    ]);

    $products = ng_recommended_products((int) $args['exclude'], (int) $args['limit']);
    if (empty($products)) return '';

    ob_start();
    ?>
    <section class="ng-rec-strip" aria-label="Recommended products">
      <div class="ng-rec-head">
        <span class="ng-rec-kicker">
          <span class="led" aria-hidden="true"></span>
          <?php echo esc_html($args['kicker']); ?>
        </span>
        <h2 class="ng-rec-h">
          <span class="ar"><?php echo esc_html($args['title_ar']); ?></span>
          <span class="en"><?php echo esc_html($args['title_en']); ?></span>
        </h2>
      </div>
      <ul class="products columns-<?php echo (int) $args['limit']; ?>">
        <?php foreach ($products as $p) :
            global $product;
            $product = $p;
            setup_postdata(get_post($p->get_id()));
            wc_get_template_part('content', 'product');
        endforeach;
        wp_reset_postdata();
        ?>
      </ul>
    </section>
    <?php
    return ob_get_clean();
}

/* ----------------------------------------------------------------
   5. Auto-render on single-product pages, AFTER the existing
      related-products section emitted by content-single-product.php.
   ---------------------------------------------------------------- */
add_action('woocommerce_after_single_product', function () {
    if (!function_exists('is_product') || !is_product()) return;
    $id = (int) get_queried_object_id();
    echo ng_render_recommendations([
        'exclude' => $id,
        'limit'   => 4,
    ]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — internal-render trusted output.
}, 20);

/* ----------------------------------------------------------------
   6. Shortcode for manual placement on cart, account, etc.
      Usage: [neogen_recommendations limit="4" title_ar="..." title_en="..."]
   ---------------------------------------------------------------- */
add_shortcode('neogen_recommendations', function ($atts = []) {
    $atts = shortcode_atts([
        'exclude'  => 0,
        'limit'    => 4,
        'title_ar' => 'مقترحات لك',
        'title_en' => 'RECOMMENDED FOR YOU',
        'kicker'   => 'OPERATOR · NEXT PICKS',
    ], $atts, 'neogen_recommendations');
    return ng_render_recommendations($atts);
});
