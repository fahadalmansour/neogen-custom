<?php
/**
 * Plugin Name: NeoGen Theme
 * Description: Sitewide visual skin for neogen.store. Tokens + logo system follow Brand Kit v1.1; layout follows Homepage Preview v1. Includes header/footer, front-page template, Woo archive/single overrides, /legal route with MOC identity readout, and Schema.org Store JSON-LD.
 * Version: 1.20.3
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

if (!defined('NEOGEN_THEME_VERSION')) {
    define('NEOGEN_THEME_VERSION', '1.5.9');
}

/**
 * Commercial Registration data — single source of truth.
 * Values reproduced verbatim from the active MOC register for
 * CR 7053130576. Do NOT edit to add speculative data — VAT /
 * physical address / extra contacts should arrive via filters
 * (neogen_org_data, neogen_org_jsonld, neogen_legal_extra).
 */
if (!defined('NG_CR')) {
    define('NG_CR', [
        'legal_name_ar'  => 'مكتب فهد سعد فهد المنصور للخدمات الإلكترونية',
        'legal_name_en'  => 'FAHAD SAAD FAHAD ALMANSOUR Office For electronic services',
        'brand_ar'       => 'نيوجين ستور',
        'brand_en'       => 'NeoGen Store',
        'owner'          => 'FAHAD SAAD FAHAD ALMANSOUR',
        'entity_type'    => 'Sole Proprietorship',
        'entity_type_ar' => 'مؤسسة فردية',
        'cr'             => '7053130576',
        'status'         => 'Active',
        'status_ar'      => 'نشط',
        'register_type'  => 'Main register',
        'registered_ad'  => '2025-12-27',
        'registered_ah'  => '1447-07-07',
        'next_conf_ad'   => '2026-12-26',
        'next_conf_ah'   => '1448-07-17',
        'capital_sar'    => '1.00',
        'phone_landline' => '+966 11 421 3842',
        'phone_mobile'   => '+966 57 013 1122',
        'email'          => 'support@neogen.store',
        'website'        => 'https://neogen.store/',
        'activities'     => [
            ['code' => '620101', 'ar' => 'تكامل الأنظمة',                            'en' => 'System integration'],
            ['code' => '821100', 'ar' => 'أنشطة الخدمات الإدارية المتكاملة للمكاتب', 'en' => 'Combined office administrative service activities'],
            ['code' => '829903', 'ar' => 'أنشطة تعقيب المعاملات',                     'en' => 'Transaction follow-up activities'],
        ],
        'authority'      => 'Ministry of Commerce, Kingdom of Saudi Arabia',
        'authority_ar'   => 'وزارة التجارة - المملكة العربية السعودية',
        'authority_url'  => 'https://mc.gov.sa/',
        'verify_url'     => 'https://eservices.mc.gov.sa/',
        // Additional regulatory body registrations (factual, supplied by user).
        'regulatory'     => [
            [
                'key'    => 'zatca',
                'label'  => 'ZATCA REGISTRATION',
                'authority_ar' => 'هيئة الزكاة والضريبة والجمارك',
                'authority_en' => 'Zakat, Tax and Customs Authority',
                'number' => '3145127947',
                'url'    => 'https://zatca.gov.sa/',
            ],
            [
                'key'    => 'csc',
                'label'  => 'CHAMBER OF COMMERCE',
                'authority_ar' => 'هيئة الغرف التجارية',
                'authority_en' => 'Council of Saudi Chambers',
                'number' => '1238532',
                'url'    => 'https://csc.org.sa/',
            ],
        ],
    ]);
}

/**
 * Accessor so future filters can override any field without
 * touching the constant. Always use ng_cr() — never NG_CR directly.
 */
function ng_cr() {
    /** @var array $cr */
    static $cached = null;
    if ($cached === null) {
        $cached = apply_filters('neogen_org_data', NG_CR);
    }
    return $cached;
}

/**
 * Strip the English side of a bilingual "English | Arabic" string.
 * Returns the Arabic part if a pipe separator is present and the
 * trailing portion contains Arabic characters. Otherwise returns
 * the input unchanged. Used to prefer Arabic labels site-wide
 * without requiring editors to clean up every term/title.
 */
function ng_ar_label( $s ) {
    $s = (string) $s;
    if ( strpos( $s, '|' ) === false ) return $s;
    $parts = array_map( 'trim', explode( '|', $s, 2 ) );
    if ( count( $parts ) === 2 && preg_match( '/[\x{0600}-\x{06FF}]/u', $parts[1] ) ) {
        return $parts[1];
    }
    return $s;
}

// Apply Arabic-side preference at the WP filter layer so every render path
// (Woo grid, breadcrumbs, archive titles, product titles, term names) gets
// the cleaned label without per-site code changes.
add_filter( 'term_name',         'ng_ar_label', 5 );
add_filter( 'single_term_title', 'ng_ar_label', 5 );
add_filter( 'single_cat_title',  'ng_ar_label', 5 );
add_filter( 'list_cats',         'ng_ar_label', 5 );
add_filter( 'woocommerce_product_title', 'ng_ar_label', 5 );

// Scope the_title to products only — protects WC order item titles,
// blog post titles, and any non-product titles that legitimately
// contain a "|" character.
add_filter( 'the_title', function ( $title, $post_id = null ) {
    if ( $post_id && 'product' === get_post_type( $post_id ) ) {
        return ng_ar_label( $title );
    }
    return $title;
}, 5, 2 );
add_filter( 'woocommerce_breadcrumb_main_term', function ( $term ) {
    if ( is_object( $term ) && isset( $term->name ) ) {
        $term->name = ng_ar_label( $term->name );
    }
    return $term;
}, 5 );

/**
 * Top-level product_cat terms by SKU count, transient-cached for 1
 * hour. Used by the sysbar nav, footer, and homepage front-page
 * template. On a host without a persistent object cache (Blocksy on
 * blazr.net VPS), this saves one terms-table SELECT per page load.
 *
 * Cache busts via the edited/created/delete _product_cat hooks
 * registered below.
 */
function ng_top_product_cats($limit = 6) {
    if (!taxonomy_exists('product_cat')) { return []; }
    $limit = max(1, (int) $limit);
    $key   = 'neogen_top_cats_' . $limit;
    $cached = get_transient($key);
    if (is_array($cached)) { return $cached; }

    // Slugs to hide from public top-cats lists (rack, topnav, footer).
    // Filterable so individual snippets can override per-surface later.
    $exclude_slugs = apply_filters( 'neogen_top_cats_exclude_slugs', [ 'homelab', 'uncategorized' ] );

    // Pull a few extra so we still get $limit results after filtering.
    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => $limit + count($exclude_slugs),
    ]);
    if (is_wp_error($terms)) { return []; }

    if ( ! empty( $exclude_slugs ) ) {
        $terms = array_values( array_filter( $terms, function ( $t ) use ( $exclude_slugs ) {
            return ! in_array( $t->slug, $exclude_slugs, true );
        } ) );
    }
    $terms = array_slice( $terms, 0, $limit );

    set_transient($key, $terms, HOUR_IN_SECONDS);
    return $terms;
}

/**
 * Bust the ng_top_product_cats() transient when categories change.
 * Covers the three limit values currently called from this codebase
 * (5, 6, plus a safety margin in case future call sites add more).
 */
$ng_bust_cats = function () {
    foreach ([4, 5, 6, 7, 8] as $n) {
        delete_transient('neogen_top_cats_' . $n);
    }
};
add_action('edited_product_cat',  $ng_bust_cats);
add_action('created_product_cat', $ng_bust_cats);
add_action('delete_product_cat',  $ng_bust_cats);

/**
 * Resolve a product's primary-category slug, honoring Rank Math then
 * Yoast SEO before falling back to the alphabetical-first term. Used
 * by the homepage picker (templates/front-page.php) so the
 * "diversify across categories" rule respects the editor's intent
 * instead of WP's default term ordering.
 */
function ng_primary_product_cat_slug($product) {
    if ( ! is_object($product) || ! method_exists($product, 'get_id') ) return '';
    $id = (int) $product->get_id();
    if ($id <= 0) return '';

    // Rank Math primary cat
    $rm_id = (int) get_post_meta($id, 'rank_math_primary_product_cat', true);
    if ($rm_id > 0) {
        $term = get_term($rm_id, 'product_cat');
        if ($term && ! is_wp_error($term)) return (string) $term->slug;
    }

    // Yoast SEO primary cat
    $yo_id = (int) get_post_meta($id, '_yoast_wpseo_primary_product_cat', true);
    if ($yo_id > 0) {
        $term = get_term($yo_id, 'product_cat');
        if ($term && ! is_wp_error($term)) return (string) $term->slug;
    }

    // Fallback — first attached product_cat (alphabetical)
    $terms = get_the_terms($id, 'product_cat');
    if ( ! is_wp_error($terms) && ! empty($terms) ) {
        return (string) $terms[0]->slug;
    }
    return '';
}

/**
 * Code-level fallback image for a product_cat slug. Resolves in this
 * order:
 *   1. neogen_theme_category_image_fallbacks filter map (slug → URL)
 *   2. brands/{slug}/_default.webp if file exists on disk
 *   3. null — caller falls back to SVG icon
 *
 * Admin uploads via Products → Categories → Thumbnail always win
 * over this; the helper is only consulted when no thumbnail_id is
 * set in term-meta. Filter the map from any mu-plugin to extend.
 *
 * Bundled defaults shipped with the repo:
 *   - gift-cards → playstation.webp (most-recognized of the 11 brand
 *     cards we ship; admin can override per-category later)
 *
 * @param string $slug product_cat term slug
 * @return string|null image URL or null if none available
 */
function ng_category_image_fallback( $slug ) {
    static $resolved = [];
    if ( array_key_exists($slug, $resolved) ) return $resolved[$slug];

    $base = defined('NG_THEME_ASSET_URL') ? NG_THEME_ASSET_URL : '';
    $defaults = [
        'gift-cards' => $base . '/img/gift-cards/playstation.webp',
    ];

    /**
     * Filter the slug → image-URL fallback map. Only consulted when
     * the category has no admin-uploaded thumbnail.
     *
     * @param array  $defaults Default map (slug => URL).
     * @param string $slug     The slug being resolved.
     */
    $map = apply_filters( 'neogen_theme_category_image_fallbacks', $defaults, $slug );

    $url = isset($map[$slug]) ? (string) $map[$slug] : '';

    // Auto-discover a brands/{slug}/_default.* file as a secondary path.
    if ( $url === '' && defined('NG_THEME_ASSET_DIR') ) {
        foreach ( ['webp','jpg','png'] as $ext ) {
            $disk = NG_THEME_ASSET_DIR . '/img/brands/' . $slug . '/_default.' . $ext;
            if ( is_readable($disk) ) {
                $url = $base . '/img/brands/' . $slug . '/_default.' . $ext;
                break;
            }
        }
    }

    return $resolved[$slug] = ( $url !== '' ? $url : null );
}

/**
 * Inject a category tiles strip at the top of the WooCommerce shop
 * and product-category archives. Reuses the homepage `.ng-rack-*`
 * markup so the existing CSS picks it up site-wide. Only fires on
 * is_shop() and is_product_category() — search/tag archives skip.
 */
// v1.34.2: split rendering by context.
//   - Bare /shop/ archive: rack stays at TOP (priority 5 before the loop)
//     because it's the primary entry point — "اختر فئة".
//   - Inside any /product-category/.../ archive: rack moves to BOTTOM
//     (priority 15 after the loop) — it's cross-nav, not a primary
//     action, so the products themselves should lead the page.
add_action('woocommerce_before_shop_loop', 'ng_shop_category_tiles_top', 5);
add_action('woocommerce_after_shop_loop',  'ng_shop_category_tiles_bottom', 15);

function ng_shop_category_tiles_top() {
    if ( ! function_exists('is_shop') ) return;
    if ( ! is_shop() ) return; // category archives render via _bottom
    ng_shop_category_tiles();
}
function ng_shop_category_tiles_bottom() {
    if ( ! function_exists('is_product_category') ) return;
    if ( ! is_product_category() ) return; // bare shop renders via _top
    ng_shop_category_tiles();
}

function ng_shop_category_tiles() {
    if ( ! function_exists('is_shop') ) return;
    if ( ! ( is_shop() || is_product_category() ) ) return;
    if ( ! function_exists('ng_top_product_cats') ) return;

    // v1.34.1: skip on gift-cards parent — the brand grid below
    // (ng_gift_cards_brand_grid, prio 9) is the primary cross-nav
    // there, so this rack would just stack redundantly. Descendant
    // pages (game-cards/playstation/) still get the rack so users
    // can jump back to other top-level cats from a brand drilldown.
    if ( is_product_category( 'gift-cards' ) ) return;

    $current_id = is_product_category() ? get_queried_object_id() : 0;
    $cats = ng_top_product_cats(6);
    if ( empty( $cats ) ) return;

    $icons    = apply_filters('neogen_theme_category_icons', []);
    $fallback = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 10h8M8 14h6"/></svg>';
    $led_patterns = [
        '<span class="l on"></span><span class="l"></span><span class="l cyan"></span>',
        '<span class="l cyan"></span><span class="l on"></span><span class="l"></span>',
        '<span class="l"></span><span class="l cyan"></span><span class="l on"></span>',
    ];

    echo '<section class="ng-section ng-shop-cats">';
    echo '<div class="ng-container">';
    // v1.31.0: contextualize the heading.
    // - Bare Shop archive (no category selected): "اختر فئة" prompt.
    // - Inside a category archive: cross-nav "تصفّح فئات أخرى".
    $is_inside_cat = is_product_category();
    echo '<div class="ng-section-head">';
    echo   '<div class="ng-section-kicker"><span></span>المتجر · <b>حسب الفئة</b></div>';
    echo   '<div class="ng-section-titles">';
    if ( $is_inside_cat ) {
        echo '<h2 class="ng-section-en">تصفّح فئات أخرى.</h2>';
        echo '<div class="ng-section-ar">كل الفئات في المتجر — للقفز السريع.</div>';
    } else {
        echo '<h2 class="ng-section-en">اختر فئة.</h2>';
        echo '<div class="ng-section-ar">اختر فئة لبدء التصفّح.</div>';
    }
    echo   '</div>';
    echo '</div>';
    echo '<div class="ng-rack">';
    foreach ( $cats as $i => $term ) {
        $slug      = $term->slug;
        $thumb_id  = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
        $link      = get_term_link($term);
        $link      = is_wp_error($link) ? '#' : $link;
        $is_curr   = ( (int) $term->term_id === $current_id );
        $ar_name   = trim((string) $term->description);
        if ( $ar_name === '' ) { $ar_name = ng_ar_label( $term->name ); }
        $led       = $led_patterns[$i % count($led_patterns)];
        $rack_id   = sprintf('%02d · رف %s', $i + 1, chr(65 + $i));
        $cls       = 'ng-rack-unit reveal' . ( $is_curr ? ' is-current' : '' );

        echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($link) . '">';
        echo   '<span class="ng-rack-id">' . esc_html($rack_id) . '</span>';
        echo   '<span class="ng-rack-led" aria-hidden="true">' . $led . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        $fallback_url = ng_category_image_fallback( $slug );

        if ( $thumb_id ) {
            // Admin-uploaded term thumbnail wins over everything.
            echo '<span class="ng-rack-photo" aria-hidden="true">';
            echo wp_get_attachment_image( $thumb_id, 'medium', false, [ 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' ] );
            echo '</span>';
        } elseif ( $fallback_url ) {
            // Code-level fallback for categories without a manual upload.
            echo '<span class="ng-rack-photo" aria-hidden="true">';
            echo '<img src="' . esc_url($fallback_url) . '" alt="" loading="lazy" decoding="async" width="240" height="240">';
            echo '</span>';
        } else {
            $icon = isset($icons[$slug]) ? $icons[$slug] : $fallback;
            echo '<span class="ng-rack-icon" aria-hidden="true">' . $icon . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo   '<span class="ng-rack-title">';
        echo     '<span class="ar">' . esc_html($ar_name) . '</span>';
        echo   '</span>';
        echo   '<span class="ng-rack-count"><b>' . (int) $term->count . '</b> منتج</span>';
        echo   '<span class="ng-rack-link">تصفّح <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>';
        echo '</a>';
    }
    echo '</div></div></section>';
}

/**
 * Gift-cards category-archive extras (v1.32.0 → v1.33.0).
 *
 * On /product-category/gift-cards/ only, inject a region-tab row + a
 * trust strip ABOVE the product grid — LikeCard-style intent. Region
 * tabs filter the loop via `_ng_gift_card_region` post meta when set.
 *
 * Hooks `woocommerce_before_shop_loop` at priority 8 — after
 * ng_shop_category_tiles (prio 5) which renders the cross-nav, before
 * the WC sort row (prio 10).
 */

/**
 * Filter the main archive query on /product-category/gift-cards/ (and
 * its descendants) by ?region=<slug> and/or ?brand=<slug>. Both meta
 * lookups stack so combinations like ?region=us&brand=playstation work.
 */
add_action('pre_get_posts', function ($q) {
    if ( is_admin() || ! $q->is_main_query() ) return;
    // Apply on /gift-cards/ and any descendant brand sub-cat too.
    $on_gift_cards = is_product_category( 'gift-cards' );
    if ( ! $on_gift_cards && function_exists('is_product_category') && is_product_category() ) {
        $term = get_queried_object();
        if ( $term && $term->taxonomy === 'product_cat' ) {
            $ancestors = get_ancestors( $term->term_id, 'product_cat' );
            $gc        = get_term_by( 'slug', 'gift-cards', 'product_cat' );
            if ( $gc && in_array( $gc->term_id, $ancestors, true ) ) {
                $on_gift_cards = true;
            }
        }
    }
    if ( ! $on_gift_cards ) return;

    $region = isset( $_GET['region'] ) ? sanitize_key( $_GET['region'] ) : '';
    $brand  = isset( $_GET['brand'] )  ? sanitize_key( $_GET['brand'] )  : '';
    if ( $region === '' && $brand === '' ) return;

    $existing = (array) $q->get( 'meta_query' );
    if ( $region !== '' ) {
        $existing[] = array(
            'key'     => '_ng_gift_card_region',
            'value'   => $region,
            'compare' => '=',
        );
    }
    if ( $brand !== '' ) {
        $existing[] = array(
            'key'     => '_ng_gift_card_brand',
            'value'   => $brand,
            'compare' => '=',
        );
    }
    $q->set( 'meta_query', $existing );
});

add_action('woocommerce_before_shop_loop', 'ng_gift_cards_archive_extras', 8);
function ng_gift_cards_archive_extras() {
    if ( ! is_product_category( 'gift-cards' ) ) { return; }

    // Region tabs — wired to ?region= via the pre_get_posts hook above.
    // Each region: [label, flag-glyph]. v1.34.0 adds country-flag emoji
    // and a "Global" tab (Steam/Razer/Spotify/etc. live under that).
    $regions = array(
        ''       => array( 'الكل',                '🌐' ),
        'ksa'    => array( 'السعودية',            '🇸🇦' ),
        'gcc'    => array( 'دول الخليج',          ''   ),
        'us'     => array( 'الولايات المتحدة',     '🇺🇸' ),
        'uk'     => array( 'المملكة المتحدة',      '🇬🇧' ),
        'global' => array( 'عالمي',               '🌍' ),
    );
    $current = isset( $_GET['region'] ) ? sanitize_key( $_GET['region'] ) : '';

    echo '<section class="ng-gc-extras">';
    echo   '<div class="ng-gc-trust" aria-label="ضمانات بطاقات الهدايا">';
    echo     '<div class="ng-gc-trust-item">';
    echo       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg>';
    echo       '<div><div class="ng-gc-trust-label">تسليم فوري</div><div class="ng-gc-trust-sub">رمز التفعيل خلال دقيقة</div></div>';
    echo     '</div>';
    echo     '<div class="ng-gc-trust-item">';
    echo       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="m9 12 2 2 4-4"/></svg>';
    echo       '<div><div class="ng-gc-trust-label">تفعيل آمن</div><div class="ng-gc-trust-sub">مفاتيح أصلية مضمونة</div></div>';
    echo     '</div>';
    echo     '<div class="ng-gc-trust-item">';
    echo       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
    echo       '<div><div class="ng-gc-trust-label">دعم سريع</div><div class="ng-gc-trust-sub">واتساب · يوم عمل</div></div>';
    echo     '</div>';
    echo     '<div class="ng-gc-trust-item">';
    echo       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M7 15h3"/></svg>';
    echo       '<div><div class="ng-gc-trust-label">دفع موثوق</div><div class="ng-gc-trust-sub">Mada · Apple Pay · STC Pay</div></div>';
    echo     '</div>';
    echo   '</div>';

    echo   '<div class="ng-gc-regions" role="tablist" aria-label="فلتر المنطقة">';
    echo     '<div class="ng-gc-regions-label">المنطقة:</div>';
    echo     '<div class="ng-gc-regions-tabs">';
    foreach ( $regions as $key => $info ) {
        list( $label, $flag ) = $info;
        $is_current = ( $current === $key );
        $url = $key === ''
            ? esc_url( get_term_link( get_queried_object() ) )
            : esc_url( add_query_arg( 'region', $key, get_term_link( get_queried_object() ) ) );
        echo '<a class="ng-gc-region-tab' . ( $is_current ? ' is-current' : '' ) . '" href="' . $url . '" role="tab" aria-selected="' . ( $is_current ? 'true' : 'false' ) . '">';
        if ( $flag !== '' ) {
            echo '<span class="ng-gc-region-flag" aria-hidden="true">' . $flag . '</span>';
        }
        echo   '<span class="ng-gc-region-label">' . esc_html( $label ) . '</span>';
        echo '</a>';
    }
    echo     '</div>';
    echo   '</div>';
    echo '</section>';
}

/**
 * Brand grid above the gift-cards product loop.
 *
 * v1.34.0 — flat 14-up grid.
 * v1.35.1 — REDESIGN: three themed lanes (Game Cards / App Stores /
 * Subscriptions) instead of one flat row. Each lane has its own
 * kicker, brands ranked by variant count inside the lane, and a
 * "الأكثر تنوّعاً" pill on the lane leader. Tiles use a clean square
 * logo well + AR name + count chip top-corner — easier to scan than
 * the v1.34.0 image-stack layout.
 *
 * Click → brand sub-category URL when it exists, otherwise ?brand=
 * filter fallback. Only fires on /product-category/gift-cards/ when
 * no brand filter is active.
 */
add_action('woocommerce_before_shop_loop', 'ng_gift_cards_brand_grid', 9);
function ng_gift_cards_brand_grid() {
    if ( ! is_product_category( 'gift-cards' ) ) { return; }
    if ( ! empty( $_GET['brand'] ) ) { return; }

    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT pm.meta_value AS brand_slug, MIN(p.ID) AS sample_pid, COUNT(*) AS variant_count
           FROM {$wpdb->postmeta} pm
           JOIN {$wpdb->posts}    p  ON p.ID = pm.post_id
          WHERE pm.meta_key = '_ng_gift_card_brand'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
          GROUP BY pm.meta_value
          ORDER BY variant_count DESC"
    );
    if ( empty( $rows ) ) { return; }

    // Brand → AR label + parent lane. Keep in sync with
    // scripts/neogen-gift-cards-bulk.php and -brand-cats.php.
    $brand_meta = array(
        'playstation'      => array( 'ar' => 'بلايستيشن',     'lane' => 'game-cards'    ),
        'xbox'             => array( 'ar' => 'إكس بوكس',     'lane' => 'game-cards'    ),
        'steam'            => array( 'ar' => 'ستيم',         'lane' => 'game-cards'    ),
        'razer-gold'       => array( 'ar' => 'رازر قولد',    'lane' => 'game-cards'    ),
        'roblox'           => array( 'ar' => 'روبلكس',       'lane' => 'game-cards'    ),
        'pubg-mobile'      => array( 'ar' => 'ببجي موبايل',  'lane' => 'game-cards'    ),
        'free-fire'        => array( 'ar' => 'فري فاير',     'lane' => 'game-cards'    ),
        'mobile-legends'   => array( 'ar' => 'موبايل ليجندز','lane' => 'game-cards'    ),
        'apple-itunes'     => array( 'ar' => 'آبل أيتونز',   'lane' => 'app-stores'    ),
        'google-play'      => array( 'ar' => 'قوقل بلاي',    'lane' => 'app-stores'    ),
        'netflix'          => array( 'ar' => 'نتفلكس',       'lane' => 'subscriptions' ),
        'spotify'          => array( 'ar' => 'سبوتيفاي',      'lane' => 'subscriptions' ),
        'disney-plus'      => array( 'ar' => 'ديزني بلس',    'lane' => 'subscriptions' ),
        'playstation-plus' => array( 'ar' => 'بلايستيشن بلس','lane' => 'subscriptions' ),
    );

    $lanes = array(
        'game-cards'    => array( 'ar' => 'بطاقات الألعاب',     'kicker' => 'العاب · GAMING',        'brands' => array() ),
        'app-stores'    => array( 'ar' => 'متاجر التطبيقات',    'kicker' => 'تطبيقات · APP STORES',  'brands' => array() ),
        'subscriptions' => array( 'ar' => 'الاشتراكات الترفيهية', 'kicker' => 'ترفيه · STREAMING',   'brands' => array() ),
    );

    foreach ( $rows as $row ) {
        $slug = $row->brand_slug;
        $meta = $brand_meta[ $slug ] ?? null;
        if ( ! $meta ) { continue; }
        $lanes[ $meta['lane'] ]['brands'][] = array(
            'slug'    => $slug,
            'ar'      => $meta['ar'],
            'pid'     => (int) $row->sample_pid,
            'variants'=> (int) $row->variant_count,
        );
    }

    $total_brands = count( $rows );
    $total_cards  = (int) array_sum( array_map( fn( $r ) => (int) $r->variant_count, $rows ) );

    echo '<section class="ng-gc-brands-grid-wrap ng-gc-brands--lanes">';
    echo   '<div class="ng-gc-brands-head">';
    echo     '<div class="ng-gc-brands-kicker"><span></span>المتجر · <b>بطاقات رقمية</b></div>';
    echo     '<h2 class="ng-gc-brands-h">العلامات التجارية</h2>';
    echo     '<div class="ng-gc-brands-sub">' . (int) $total_brands . ' علامة · ' . (int) $total_cards . ' بطاقة · ' . count( $lanes ) . ' فئات</div>';
    echo   '</div>';

    foreach ( $lanes as $lane_key => $lane ) {
        if ( empty( $lane['brands'] ) ) { continue; }

        $lane_term = get_term_by( 'slug', $lane_key, 'product_cat' );
        $lane_url  = ( $lane_term && ! is_wp_error( $lane_term ) ) ? get_term_link( $lane_term ) : '';
        $lane_url  = is_wp_error( $lane_url ) ? '' : $lane_url;
        $lane_total = array_sum( array_map( fn( $b ) => $b['variants'], $lane['brands'] ) );

        echo '<div class="ng-gc-lane" data-lane="' . esc_attr( $lane_key ) . '">';
        echo   '<header class="ng-gc-lane-head">';
        echo     '<div class="ng-gc-lane-kicker">' . esc_html( $lane['kicker'] ) . '</div>';
        echo     '<div class="ng-gc-lane-titles">';
        echo       '<h3 class="ng-gc-lane-h">' . esc_html( $lane['ar'] ) . '</h3>';
        echo       '<span class="ng-gc-lane-count">' . count( $lane['brands'] ) . ' علامة · ' . (int) $lane_total . ' بطاقة</span>';
        echo     '</div>';
        if ( $lane_url ) {
            echo   '<a class="ng-gc-lane-all" href="' . esc_url( $lane_url ) . '">عرض الكل <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></a>';
        }
        echo   '</header>';
        echo   '<div class="ng-gc-lane-strip">';

        $is_first = true;
        foreach ( $lane['brands'] as $b ) {
            $sample = wc_get_product( $b['pid'] );
            $img_id = $sample instanceof WC_Product ? (int) $sample->get_image_id() : 0;
            $img    = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';

            $term = get_term_by( 'slug', $b['slug'], 'product_cat' );
            $url  = ( $term && ! is_wp_error( $term ) )
                ? get_term_link( $term )
                : add_query_arg( 'brand', $b['slug'], get_term_link( get_queried_object() ) );
            $url  = is_wp_error( $url ) ? '#' : $url;

            $cls = 'ng-gc-brand-tile';
            if ( $is_first ) { $cls .= ' is-lead'; }

            echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '" data-brand="' . esc_attr( $b['slug'] ) . '">';
            echo   '<span class="ng-gc-brand-chip" dir="ltr">' . (int) $b['variants'] . '</span>';
            if ( $is_first ) {
                echo '<span class="ng-gc-brand-pill">الأكثر تنوّعاً</span>';
            }
            if ( $img ) {
                echo '<span class="ng-gc-brand-img"><img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async"></span>';
            } else {
                echo '<span class="ng-gc-brand-img ng-gc-brand-img--placeholder" aria-hidden="true"></span>';
            }
            echo   '<span class="ng-gc-brand-name">' . esc_html( $b['ar'] ) . '</span>';
            echo   '<span class="ng-gc-brand-cta">تصفّح <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>';
            echo '</a>';

            $is_first = false;
        }

        echo   '</div>';
        echo '</div>';
    }

    echo '</section>';
}

/**
 * Info-page registry — drives every /about/, /terms/, /privacy/,
 * /returns/, /warranty/, /shipping/, /contact/ route through one
 * shared template. Pages marked draft=true render a "PENDING LEGAL
 * REVIEW" banner and use placeholder section bodies.
 *
 * Real legal copy arrives via add_action('neogen_info_extra_<slug>',
 * fn($cr) => echo '<div>...</div>'). The registry stays factual.
 */
function ng_info_pages() {
    static $cached = null;
    if ($cached !== null) { return $cached; }

    $cr = ng_cr();

    $cached = [

        'about' => [
            'kicker'   => '02 · ABOUT',
            'h1_en'    => 'WHO WE ARE',
            'h1_ar'    => 'من نحن',
            'lede_en'  => 'A Saudi technology retailer built for the operators behind the screens — networks, homelabs, smart homes, competitive gaming, and the people who keep them running.',
            'lede_ar'  => 'متجر تقني سعودي للمختصين خلف الشاشات — شبكات، هوم لاب، بيوت ذكية، عتاد ألعاب تنافسية، والمشغّلين الذين يديرون كل ذلك.',
            'draft'    => false,
            'sections' => [
                [
                    'kicker_en' => '01 · MISSION',
                    'h_en' => 'Specialized hardware, no commodity noise.',
                    'h_ar' => 'عتاد متخصص، بدون ضوضاء العامة.',
                    'body' => [
                        'NeoGen Store carries gear used by people who actually deploy infrastructure inside the Kingdom. We curate by reliability, repairability, and parts availability — not by the brand with the loudest ad spend.',
                        'إذا لم يكن المنتج مفيداً على شبكة جدية، تركيبة هوم لاب، بيت ذكي حقيقي، محطة ألعاب تنافسية، أو مشروع تركيب فعلي — لا نحمله.',
                    ],
                ],
                [
                    'kicker_en' => '02 · WHO WE SERVE',
                    'h_en' => 'Built for operators.',
                    'h_ar' => 'مبني للمشغّلين.',
                    'body' => [
                        'Network engineers buying enterprise APs and switches for SMB sites. Hobbyists running k3s clusters in their garages on Synology + Mikrotik. Smart-home installers commissioning Aqara and Shelly across villas. Competitive gamers chasing 0.1 ms latency cuts. We carry exactly what each of these jobs requires.',
                    ],
                ],
                [
                    'kicker_en' => '03 · WHAT WE STOCK',
                    'h_en' => 'Five racks. Each one curated.',
                    'h_ar' => 'خمسة "رفوف". كل رف مختار بعناية.',
                    'body' => [
                        'NETWORKING / WI-FI 7 — Ubiquiti, MikroTik, TP-Link Omada. Routers, PoE switches, access points, SFP+ modules, fiber patch.',
                        'HOMELAB / STORAGE — NAS, rack-mount mini-PCs, enterprise HDDs and SSDs, UPS units, rack accessories, cable management.',
                        'SMART HOME / MATTER — Aqara, Shelly, Sonoff. Hubs, sensors, relays, door locks, lighting, Matter-over-Thread bridges.',
                        'GAMING / COMPETITIVE — Hall-effect switches, low-latency monitors, studio audio, DAC/AMP, mechanical keyboards, custom cables.',
                        'SERVICES / BUILDS — Network design, rack builds, smart-home commissioning, on-site setup inside KSA metros.',
                    ],
                ],
                [
                    'kicker_en' => '04 · HOW WE OPERATE',
                    'h_en' => 'Plain specs. Honest stock. Real tracking.',
                    'h_ar' => 'مواصفات واضحة. مخزون حقيقي. تتبع فعلي.',
                    'body' => [
                        'Every product page shows the SKU, real specifications without marketing inflation, live stock count, and a single primary action. No decoy buttons.',
                        'الشحن من داخل المملكة، 2-5 أيام عمل لمدن الرياض وجدة والدمام. ضمان 12 شهراً، ودعم بالعربي.',
                    ],
                ],
                [
                    'kicker_en' => '05 · LEGAL · OFFICIAL',
                    'h_en' => 'Saudi MOC-registered establishment.',
                    'h_ar' => 'منشأة سعودية مرخصة من وزارة التجارة.',
                    'body' => [
                        sprintf('Trade name: %s. Owner: %s. CR: %s. Status: Active.', $cr['legal_name_en'], $cr['owner'], $cr['cr']),
                        sprintf('الاسم التجاري: %s · المالك: %s · السجل التجاري: %s · الحالة: نشط.', $cr['legal_name_ar'], $cr['owner'], $cr['cr']),
                        'Full identity readout, regulatory registrations, and verification links: <a href="' . esc_url(home_url('/legal/')) . '">/legal/ →</a>',
                    ],
                ],
            ],
        ],

        'shipping' => [
            'kicker'   => '03 · SHIPPING',
            'h1_en'    => 'SHIPPING POLICY',
            'h1_ar'    => 'سياسة الشحن',
            'lede_en'  => 'Shipped from inside the Kingdom. 2–5 business days to Riyadh, Jeddah, and Dammam metros. Tracking via WhatsApp and email.',
            'lede_ar'  => 'الشحن من داخل المملكة. 2-5 أيام عمل لمدن الرياض وجدة والدمام. التتبع عبر واتساب والبريد الإلكتروني.',
            'draft'    => false,
            'sections' => [
                [
                    'kicker_en' => '01 · ZONES',
                    'h_en' => 'Where we ship.',
                    'h_ar' => 'مناطق التغطية.',
                    'body' => [
                        'PRIMARY METROS — Riyadh · Jeddah · Dammam · Khobar · Dhahran. Same-day or next-day handling, 2–3 business days transit.',
                        'OTHER KSA CITIES — Madinah · Makkah · Tabuk · Abha · Hail · Buraidah · Najran · Yanbu · Jubail and others. 3–5 business days transit.',
                        'REMOTE GOVERNORATES — Smaller villages and remote areas may add 1–2 days. Tracking is provided regardless.',
                        'INTERNATIONAL — Not currently offered.',
                    ],
                ],
                [
                    'kicker_en' => '02 · TIMELINES',
                    'h_en' => 'When it ships.',
                    'h_ar' => 'متى يُشحن.',
                    'body' => [
                        'Orders placed before 14:00 KSA time on a business day are typically dispatched the same day. Orders placed later, or on weekends/holidays, are dispatched the next business day.',
                        'Backorders are flagged at checkout and on the product page (LOW STOCK / OUT badges). For backorders, we contact you with the expected restock date before processing payment.',
                    ],
                ],
                [
                    'kicker_en' => '03 · PAYMENT & ON-DELIVERY',
                    'h_en' => 'Payment methods accepted.',
                    'h_ar' => 'وسائل الدفع المقبولة.',
                    'body' => [
                        'Mada · Apple Pay · STC Pay · Tabby (split into 4) · all major credit cards via the secure payment gateway.',
                        'الدفع عند الاستلام: غير متوفر حالياً.',
                    ],
                ],
                [
                    'kicker_en' => '04 · TRACKING & ISSUES',
                    'h_en' => 'After it ships.',
                    'h_ar' => 'بعد الشحن.',
                    'body' => [
                        'You receive a tracking number by email and on your order page. For shipping issues, contact us via the channels listed below — we respond within one business day.',
                        sprintf('Mobile: %s · Email: %s', $cr['phone_mobile'], $cr['email']),
                    ],
                ],
            ],
        ],

        'returns' => [
            'kicker'   => '04 · RETURNS',
            'h1_en'    => 'RETURNS & REFUNDS',
            'h1_ar'    => 'سياسة الاسترجاع والاسترداد',
            'lede_en'  => 'Statutory baseline summarized below. Store-specific extensions (extended return windows, free-return shipping, restocking fees) are pending legal review.',
            'lede_ar'  => 'الحد الأدنى النظامي ملخص أدناه. التفاصيل الخاصة بالمتجر (تمديد المدة، رسوم إعادة الترتيب، شحن الإرجاع) قيد المراجعة القانونية.',
            'draft'    => true,
            'sections' => [
                [
                    'kicker_en' => '01 · STATUTORY BASELINE',
                    'h_en' => 'Your default rights under KSA E-Commerce Law.',
                    'h_ar' => 'الحقوق الافتراضية بموجب نظام التجارة الإلكترونية السعودي.',
                    'body' => [
                        'Saudi E-Commerce Law (نظام التجارة الإلكترونية, Royal Decree M/126) gives you a default right to return distance-purchased goods within seven (7) days of delivery, provided the item is unused, unopened where applicable, and in its original condition and packaging.',
                        'Certain categories may be excluded by law: digital downloads or activation keys after redemption, software with broken seals, perishable goods, and items personalized to your specifications.',
                    ],
                ],
                [
                    'kicker_en' => '02 · STORE-SPECIFIC TERMS',
                    'h_en' => 'NeoGen Store extensions.',
                    'h_ar' => 'تمديدات متجر نيوجين.',
                    'body' => [
                        '<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>',
                        'The store currently advertises a 14-day return window in its footer chips. Specific eligibility conditions, restocking fees, and refund timelines are being finalized by legal counsel and will be published here.',
                        'In the interim, please contact us before returning any item so we can confirm eligibility and arrange the return.',
                    ],
                ],
            ],
        ],

        'warranty' => [
            'kicker'   => '05 · WARRANTY',
            'h1_en'    => 'WARRANTY POLICY',
            'h1_ar'    => 'سياسة الضمان',
            'lede_en'  => 'Statutory minimums under KSA Consumer Protection law plus pass-through manufacturer warranties. Specific claim procedures are pending legal review.',
            'lede_ar'  => 'الحد الأدنى النظامي بموجب نظام حماية المستهلك السعودي بالإضافة إلى ضمانات المصنّع. إجراءات المطالبة قيد المراجعة القانونية.',
            'draft'    => true,
            'sections' => [
                [
                    'kicker_en' => '01 · STATUTORY MINIMUM',
                    'h_en' => 'Defect protection by law.',
                    'h_ar' => 'الحماية من العيوب بحكم النظام.',
                    'body' => [
                        'Under the Saudi Consumer Protection regime, the seller is responsible for hidden defects (عيب خفي) that materially affect the product\'s value or fitness for purpose, regardless of any manufacturer warranty.',
                        'This statutory protection runs in parallel with — not instead of — the manufacturer\'s commercial warranty.',
                    ],
                ],
                [
                    'kicker_en' => '02 · MANUFACTURER WARRANTY',
                    'h_en' => 'Pass-through coverage.',
                    'h_ar' => 'الضمان المُقدَّم من المصنّع.',
                    'body' => [
                        'Each product page lists the manufacturer warranty period the unit ships with (e.g., 12 months on most networking equipment). Claims under manufacturer warranty are processed through our service desk and routed to the relevant brand.',
                    ],
                ],
                [
                    'kicker_en' => '03 · CLAIM PROCEDURE',
                    'h_en' => 'How to open a warranty claim.',
                    'h_ar' => 'كيفية فتح طلب ضمان.',
                    'body' => [
                        '<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>',
                        'Detailed claim procedures, RMA timelines, and replacement-vs-refund rules are being finalized.',
                        sprintf('In the meantime, contact: %s · %s.', $cr['phone_mobile'], $cr['email']),
                    ],
                ],
            ],
        ],

        'terms' => [
            'kicker'   => '06 · TERMS',
            'h1_en'    => 'TERMS & CONDITIONS',
            'h1_ar'    => 'الشروط والأحكام',
            'lede_en'  => 'Section headings reflect the standard structure required for a Saudi e-commerce store. The body text in each section is pending legal counsel review and is not authoritative.',
            'lede_ar'  => 'عناوين الأقسام تعكس البنية المعتادة لمتجر إلكتروني سعودي. النصوص داخل كل قسم قيد المراجعة القانونية وليست نهائية.',
            'draft'    => true,
            'sections' => [
                [
                    'kicker_en' => '01 · ACCEPTANCE',
                    'h_en'      => 'Acceptance of terms.',
                    'h_ar'      => 'قبول الشروط.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'By accessing or using neogen.store, you agree to be bound by these terms. Specific binding language is being drafted by legal counsel.'],
                ],
                [
                    'kicker_en' => '02 · ACCOUNTS',
                    'h_en'      => 'Customer accounts.',
                    'h_ar'      => 'حسابات العملاء.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Account creation requirements, security obligations, and termination conditions will appear here.'],
                ],
                [
                    'kicker_en' => '03 · ORDERS · PRICING',
                    'h_en'      => 'Orders, pricing, and payment.',
                    'h_ar'      => 'الطلبات والأسعار والدفع.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Order acceptance, price changes, currency, payment processors, and tax handling will be specified here. Note: VAT 15% is included in displayed prices.'],
                ],
                [
                    'kicker_en' => '04 · IP',
                    'h_en'      => 'Intellectual property.',
                    'h_ar'      => 'الملكية الفكرية.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Trademarks, brand assets, and content ownership clauses will appear here.'],
                ],
                [
                    'kicker_en' => '05 · LIABILITY',
                    'h_en'      => 'Limitation of liability.',
                    'h_ar'      => 'تحديد المسؤولية.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Liability caps and exclusions, subject to mandatory consumer-protection rules, will appear here.'],
                ],
                [
                    'kicker_en' => '06 · GOVERNING LAW',
                    'h_en'      => 'Governing law and disputes.',
                    'h_ar'      => 'القانون الواجب التطبيق وفض النزاعات.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'These terms are governed by the laws of the Kingdom of Saudi Arabia. Disputes are subject to the competent commercial courts in KSA and the e-commerce dispute mechanism, as applicable.'],
                ],
                [
                    'kicker_en' => '07 · CHANGES',
                    'h_en'      => 'Changes to these terms.',
                    'h_ar'      => 'تعديل الشروط.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Modification rights, notice mechanisms, and effective-date rules will appear here.'],
                ],
            ],
        ],

        'privacy' => [
            'kicker'   => '07 · PRIVACY',
            'h1_en'    => 'PRIVACY POLICY',
            'h1_ar'    => 'سياسة الخصوصية',
            'lede_en'  => 'This policy is structured against the Saudi Personal Data Protection Law (PDPL, Royal Decree M/19, in full effect from September 2024). Specific clauses are pending legal review and are not authoritative.',
            'lede_ar'  => 'هذه السياسة مبنية على نظام حماية البيانات الشخصية السعودي (PDPL، المرسوم الملكي م/19، نافذ بالكامل من سبتمبر 2024). البنود الخاصة قيد المراجعة القانونية.',
            'draft'    => true,
            'sections' => [
                [
                    'kicker_en' => '01 · CONTROLLER',
                    'h_en'      => 'Who controls your data.',
                    'h_ar'      => 'الجهة المتحكمة في بياناتك.',
                    'body'      => [
                        sprintf('Data controller: %s (CR %s).', $cr['legal_name_en'], $cr['cr']),
                        sprintf('Contact: %s · %s.', $cr['email'], $cr['phone_mobile']),
                    ],
                ],
                [
                    'kicker_en' => '02 · DATA WE COLLECT',
                    'h_en'      => 'Categories of personal data.',
                    'h_ar'      => 'فئات البيانات الشخصية.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Identity (name), contact (email, phone, shipping address), order history, payment metadata (handled by the payment gateway, not stored by us in raw form), device and usage data (IP, browser, cookies). The full enumeration will be confirmed by counsel.'],
                ],
                [
                    'kicker_en' => '03 · PURPOSES',
                    'h_en'      => 'Why we process it.',
                    'h_ar'      => 'أغراض المعالجة.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Order fulfillment, customer support, fraud prevention, regulatory compliance (CR / ZATCA), and (with consent) marketing communications.'],
                ],
                [
                    'kicker_en' => '04 · LEGAL BASES',
                    'h_en'      => 'Lawful grounds under PDPL.',
                    'h_ar'      => 'الأسس النظامية بموجب PDPL.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Contract performance (orders), legal obligation (tax/CR), legitimate interest (fraud prevention), and explicit consent (marketing).'],
                ],
                [
                    'kicker_en' => '05 · SHARING',
                    'h_en'      => 'Who we share with.',
                    'h_ar'      => 'الجهات التي نشارك معها.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Payment processors (Mada / Apple Pay / STC / Tabby / card gateway), shipping couriers, Saudi tax/regulatory authorities when legally required.'],
                ],
                [
                    'kicker_en' => '06 · RETENTION',
                    'h_en'      => 'How long we keep data.',
                    'h_ar'      => 'مدة الاحتفاظ.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Retention periods will be specified by category (e.g., order records for tax-law-mandated minimum, marketing data until consent withdrawal).'],
                ],
                [
                    'kicker_en' => '07 · YOUR RIGHTS',
                    'h_en'      => 'Data subject rights under PDPL.',
                    'h_ar'      => 'حقوقك بموجب PDPL.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Right to be informed, right of access, right of correction, right of deletion, right to data portability, right to object to processing, right to withdraw consent. Exercise via the controller contact above.'],
                ],
                [
                    'kicker_en' => '08 · COOKIES',
                    'h_en'      => 'Cookies and tracking.',
                    'h_ar'      => 'ملفات الارتباط والتتبع.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Strictly-necessary cookies (cart, session, security) plus optional analytics. A consent banner will be wired in a follow-up commit when the cookie inventory is finalized.'],
                ],
                [
                    'kicker_en' => '09 · CROSS-BORDER',
                    'h_en'      => 'International data transfers.',
                    'h_ar'      => 'نقل البيانات خارج المملكة.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Cross-border transfer disclosures (e.g., to payment gateway processors hosted outside KSA) and the safeguards relied upon will be specified.'],
                ],
            ],
        ],

        'usage' => [
            'kicker'   => '09 · USAGE',
            'h1_en'    => 'ACCEPTABLE USE POLICY',
            'h1_ar'    => 'سياسة الاستخدام',
            'lede_en'  => 'How you may and may not use neogen.store, your account, and any related services. Distinct from the Terms & Conditions, which govern the commercial transaction. Section bodies are pending legal review and not authoritative.',
            'lede_ar'  => 'القواعد التي تحكم استخدامك للموقع والحساب والخدمات ذات الصلة. مستقلة عن "الشروط والأحكام" التي تحكم المعاملة التجارية. النصوص داخل الأقسام قيد المراجعة القانونية.',
            'draft'    => true,
            'sections' => [
                [
                    'kicker_en' => '01 · ACCEPTANCE',
                    'h_en'      => 'Using the site means accepting this policy.',
                    'h_ar'      => 'استخدام الموقع يعني قبول هذه السياسة.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'By accessing, browsing, registering, or transacting on neogen.store you agree to this Acceptable Use Policy in addition to the Terms &amp; Conditions. Specific binding language is being drafted by counsel.'],
                ],
                [
                    'kicker_en' => '02 · PROHIBITED USES',
                    'h_en'      => 'What you must not do.',
                    'h_ar'      => 'ممنوعات الاستخدام.',
                    'body'      => [
                        '<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>',
                        'Indicative non-exhaustive list, subject to final wording: automated scraping or harvesting; placing fraudulent or fictitious orders; impersonating another person or entity; circumventing security or rate-limiting; uploading or transmitting malware; infringing intellectual-property rights; using the site to violate any KSA law including the Anti-Cybercrime Law (نظام مكافحة جرائم المعلوماتية).',
                    ],
                ],
                [
                    'kicker_en' => '03 · ACCOUNT CONDUCT',
                    'h_en'      => 'Your account, your responsibility.',
                    'h_ar'      => 'الحساب على مسؤوليتك.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'You are responsible for keeping your credentials confidential, for the accuracy of the information you supply (delivery address, contact details, payment data), and for any activity that occurs under your account.'],
                ],
                [
                    'kicker_en' => '04 · USER CONTENT',
                    'h_en'      => 'Reviews, comments, and other submissions.',
                    'h_ar'      => 'التقييمات والتعليقات.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'Where the site allows you to submit content (e.g., product reviews), you grant us a non-exclusive licence to display it and undertake that the content is lawful, accurate, and non-infringing. We may moderate, edit, or remove submissions at our discretion. Final terms pending counsel.'],
                ],
                [
                    'kicker_en' => '05 · ENFORCEMENT',
                    'h_en'      => 'Suspension and termination.',
                    'h_ar'      => 'الإيقاف وإنهاء الحساب.',
                    'body'      => ['<span class="ng-pending">مسودة — بانتظار المراجعة القانونية</span>', 'We may suspend or terminate access for violations of this policy, with or without prior notice depending on severity, and we may seek to recover any losses caused. Specific enforcement procedures are being finalized.'],
                ],
                [
                    'kicker_en' => '06 · REPORTING ABUSE',
                    'h_en'      => 'How to report violations.',
                    'h_ar'      => 'كيفية الإبلاغ عن المخالفات.',
                    'body'      => [
                        sprintf('Email: %s — please include URLs, timestamps, and any supporting evidence.', $cr['email']),
                        sprintf('Mobile: %s', $cr['phone_mobile']),
                    ],
                ],
            ],
        ],

        'contact' => [
            'kicker'   => '08 · CONTACT',
            'h1_en'    => 'CONTACT',
            'h1_ar'    => 'تواصل معنا',
            'lede_en'  => 'Direct lines to the operator behind the desk. We respond within one business day.',
            'lede_ar'  => 'قنوات تواصل مباشرة مع المسؤول. نرد خلال يوم عمل واحد.',
            'draft'    => false,
            'sections' => [
                [
                    'kicker_en' => '01 · CHANNELS',
                    'h_en' => 'How to reach us.',
                    'h_ar' => 'وسائل التواصل.',
                    'body' => [
                        sprintf('Mobile · %s', $cr['phone_mobile']),
                        sprintf('Landline · %s', $cr['phone_landline']),
                        sprintf('Email · %s', $cr['email']),
                        sprintf('Website · %s', $cr['website']),
                    ],
                ],
                [
                    'kicker_en' => '02 · HOURS',
                    'h_en' => 'When we answer.',
                    'h_ar' => 'ساعات الرد.',
                    'body' => [
                        'Saturday – Thursday · 09:00 – 22:00 KSA time. Friday: limited support, expect next-business-day response.',
                    ],
                ],
                [
                    'kicker_en' => '03 · SUPPORT POSTURE',
                    'h_en' => 'What we handle directly.',
                    'h_ar' => 'ما نعالجه مباشرة.',
                    'body' => [
                        'Pre-sales spec questions, order status, shipping issues, returns, warranty intake, build briefs.',
                        'For technical product support beyond setup, manufacturer warranty channels are usually faster — we route those for you.',
                    ],
                ],
            ],
        ],

    ];

    return $cached;
}

// Resolve asset dir + URL regardless of where the deploy plugin clones us.
$ng_theme_asset_dir = __DIR__ . '/neogen-theme-assets';
$ng_theme_rel       = str_replace(
    wp_normalize_path(WP_CONTENT_DIR),
    '',
    wp_normalize_path($ng_theme_asset_dir)
);
if (!defined('NG_THEME_ASSET_DIR')) {
    define('NG_THEME_ASSET_DIR', $ng_theme_asset_dir);
}
if (!defined('NG_THEME_ASSET_URL')) {
    define('NG_THEME_ASSET_URL', content_url($ng_theme_rel));
}

/**
 * Enqueue Google Fonts + theme CSS + theme JS sitewide.
 */
add_action('wp_enqueue_scripts', function () {
    // Google Fonts (display=swap, matches preview).
    wp_enqueue_style(
        'neogen-google-fonts',
        'https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&family=Major+Mono+Display&family=Manrope:wght@300;400;500;600;700&family=Rakkas&family=Reem+Kufi:wght@400;500;600;700&family=Tajawal:wght@300;400;500;700&display=swap',
        [],
        null
    );

    $css_path = NG_THEME_ASSET_DIR . '/neogen.css';
    $js_path  = NG_THEME_ASSET_DIR . '/neogen.js';

    $css_ver = file_exists($css_path) ? (string) filemtime($css_path) : NEOGEN_THEME_VERSION;
    $js_ver  = file_exists($js_path)  ? (string) filemtime($js_path)  : NEOGEN_THEME_VERSION;

    wp_enqueue_style(
        'neogen-theme',
        NG_THEME_ASSET_URL . '/neogen.css',
        ['neogen-google-fonts'],
        $css_ver
    );

    wp_enqueue_script(
        'neogen-theme',
        NG_THEME_ASSET_URL . '/neogen.js',
        [],
        $js_ver,
        true
    );
}, 20);

/**
 * Preconnect hints so Google Fonts ship fast.
 */
add_action('wp_head', function () {
    echo "\n" . '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    // Match the manifest theme_color (#38BDF8 — R6 Sky + Cool White) + light operator-console aesthetic.
    echo '<meta name="theme-color" content="#38BDF8">' . "\n";
    echo '<meta name="color-scheme" content="light only">' . "\n";
}, 2);

/**
 * Force light mode on the HTML root before first paint so the parent
 * Blocksy theme's dark-mode initialization can't briefly flash dark.
 * Runs at wp_head priority 0 — before any other inline scripts.
 *
 * Phase 2c: gated by ng_blocksy_dark_mode_allowed(). When the operator
 * opts into dark mode in Tools → NeoGen Blocksy Handoff, this script
 * stops emitting, the data-prefers-color-scheme attribute is no
 * longer forced, and Blocksy's Customize → Color Scheme picker is
 * free to set the attribute itself.
 */
add_action('wp_head', function () {
    if ( function_exists('ng_blocksy_dark_mode_allowed') && ng_blocksy_dark_mode_allowed() ) return;
    echo "\n<script>" .
         "document.documentElement.setAttribute('data-prefers-color-scheme','light');" .
         "document.documentElement.classList.remove('ct-dark','dark','dark-mode');" .
         "</script>\n";
}, 0);

/**
 * Favicon + PWA icon set. Brand kit v1.1 approves the 8-point star
 * for favicon use specifically (it reads cleanly at 16x16 unlike
 * the NG monogram lockup, which needs typography).
 *
 * Cascade:
 *   - SVG primary (modern browsers Chrome 80+, Firefox 41+, Safari 9+)
 *   - 32x32 PNG fallback (older browsers)
 *   - 180x180 apple-touch-icon (iOS home screen)
 *   - 192x192 + 512x512 PNG via webmanifest (PWA / Android home screen)
 *   - WP's wp_site_icon() default is suppressed by remove_action below
 *     so we have a single source of truth.
 */
remove_action('wp_head', 'wp_site_icon', 99);

add_action('wp_head', function () {
    $base = NG_THEME_ASSET_URL . '/icons';
    echo "\n";
    echo '<link rel="icon" href="' . esc_url($base . '/favicon.ico') . '" sizes="any">' . "\n";
    echo '<link rel="icon" type="image/svg+xml" href="' . esc_url($base . '/favicon.svg') . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="16x16" href="' . esc_url($base . '/icon-16.png') . '">' . "\n";
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url($base . '/icon-32.png') . '">' . "\n";
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url($base . '/apple-touch-icon.png') . '">' . "\n";
    echo '<link rel="mask-icon" href="' . esc_url($base . '/safari-pinned-tab.svg') . '" color="#38BDF8">' . "\n";
    echo '<link rel="manifest" href="' . esc_url($base . '/site.webmanifest') . '">' . "\n";
}, 1);

/**
 * Google Tag Manager — head script.
 * Container ID is admin-overridable via option `ng_gtm_container_id`;
 * default is the production container.
 */
add_action('wp_head', function () {
    if ( is_admin() || is_customize_preview() ) return;
    $gtm_id = (string) get_option( 'ng_gtm_container_id', 'GTM-PRTBSHTW' );
    $gtm_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', $gtm_id );
    if ( $gtm_id === '' ) return;
    ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
<!-- End Google Tag Manager -->
    <?php
}, 1);

/**
 * Google Tag Manager — noscript iframe at the very top of <body>.
 */
add_action('wp_body_open', function () {
    if ( is_admin() || is_customize_preview() ) return;
    $gtm_id = (string) get_option( 'ng_gtm_container_id', 'GTM-PRTBSHTW' );
    $gtm_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', $gtm_id );
    if ( $gtm_id === '' ) return;
    ?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm_id ); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
    <?php
}, 1);

/**
 * Schema.org Store JSON-LD so crawlers can verify the business.
 * Only fields from the active MOC register are emitted — no
 * fabricated VAT, address, or contact.
 */
add_action('wp_head', function () {
    // R5 cutover: when the engine is ON it emits a complete @graph
    // (Store + WebSite + WebPage + per-surface nodes) from
    // mu-plugins/neogen-seo-engine.php. Avoid dual emission here.
    if ( function_exists('ng_seo_engine_enabled') && ng_seo_engine_enabled() ) return;

    $cr       = ng_cr();
    $tel_e164 = preg_replace('/\s+/', '', $cr['phone_mobile']); // +966570131122
    $home     = rtrim(home_url('/'), '/') . '/';

    $store = [
        '@type'         => 'Store',
        '@id'           => $home . '#organization',
        'name'          => $cr['brand_en'],
        'alternateName' => [$cr['brand_ar'], $cr['legal_name_en']],
        'legalName'     => $cr['legal_name_ar'],
        'url'           => home_url('/'),
        'email'         => $cr['email'],
        'telephone'     => $tel_e164,
        'foundingDate'  => $cr['registered_ad'],
        'founder'       => ['@type' => 'Person', 'name' => $cr['owner']],
        'address'       => ['@type' => 'PostalAddress', 'addressCountry' => 'SA'],
        'areaServed'    => ['@type' => 'Country', 'name' => 'Saudi Arabia'],
        'inLanguage'    => ['ar-SA', 'en'],
        'identifier'    => array_merge(
            [[
                '@type'      => 'PropertyValue',
                'propertyID' => 'Saudi Ministry of Commerce Unified CR Number',
                'value'      => $cr['cr'],
            ]],
            array_map(function ($r) {
                return [
                    '@type'      => 'PropertyValue',
                    'propertyID' => $r['authority_en'] . ' Registration',
                    'value'      => $r['number'],
                ];
            }, $cr['regulatory']),
            array_map(function ($a) {
                return [
                    '@type'      => 'PropertyValue',
                    'propertyID' => 'ISIC v4 ' . $a['code'],
                    'value'      => $a['en'],
                ];
            }, $cr['activities'])
        ),
        'knowsAbout'    => [
            'System integration', 'Network infrastructure', 'Homelab hardware',
            'Smart home systems', 'Gaming hardware', 'E-commerce',
        ],
        'contactPoint'  => [[
            '@type'             => 'ContactPoint',
            'telephone'         => $tel_e164,
            'email'             => $cr['email'],
            'contactType'       => 'customer support',
            'availableLanguage' => ['Arabic', 'English'],
            'areaServed'        => 'SA',
        ]],
    ];

    $website = [
        '@type'    => 'WebSite',
        '@id'      => $home . '#website',
        'url'      => $home,
        'name'     => $cr['brand_en'],
        'inLanguage' => ['ar-SA', 'en'],
        'publisher' => ['@id' => $home . '#organization'],
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => $home . '?s={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $webpage = [
        '@type'      => 'WebPage',
        '@id'        => trailingslashit( ( is_front_page() || is_home() ) ? $home : home_url( add_query_arg( null, null ) ) ) . '#webpage',
        'url'        => ( is_front_page() || is_home() ) ? $home : home_url( add_query_arg( null, null ) ),
        'name'       => wp_get_document_title(),
        'isPartOf'   => ['@id' => $home . '#website'],
        'about'      => ['@id' => $home . '#organization'],
        'inLanguage' => is_rtl() ? 'ar-SA' : 'en',
    ];

    $graph = [
        '@context' => 'https://schema.org',
        '@graph'   => [ $store, $website, $webpage ],
    ];

    $graph = apply_filters('neogen_org_jsonld_graph', $graph, $cr);
    $json = wp_json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json) {
        echo "\n" . '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n";
    }
}, 5);

/**
 * Custom routes — /legal/ + the seven info pages from ng_info_pages().
 * One rewrite rule per slug, all driven by the neogen_page query var.
 * Rewrite cache flushed exactly once per theme version (keyed option).
 */
add_action('init', function () {
    $slugs = array_merge(['legal'], array_keys(ng_info_pages()));
    foreach ($slugs as $slug) {
        // v1.31.0: yield to a real published WP page if one exists at
        // this slug — lets the operator edit the content via wp-admin.
        // /legal/ stays virtual since it's auto-derived from ng_cr().
        if ($slug !== 'legal') {
            $real = get_page_by_path($slug, OBJECT, 'page');
            if ($real instanceof WP_Post && $real->post_status === 'publish') {
                continue;
            }
        }
        add_rewrite_rule('^' . preg_quote($slug, '#') . '/?$', 'index.php?neogen_page=' . $slug, 'top');
    }

    $flag = 'neogen_rewrites_flushed_' . str_replace('.', '_', NEOGEN_THEME_VERSION);
    if (!get_option($flag)) {
        flush_rewrite_rules(false);
        update_option($flag, 1, true);
    }
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'neogen_page';
    return $vars;
});

add_filter('template_include', function ($template) {
    if (is_admin()) { return $template; }

    // 404 — operator-console "ROUTE NOT FOUND" page.
    if (is_404()) {
        $tpl = NG_THEME_ASSET_DIR . '/templates/404.php';
        if (file_exists($tpl)) {
            if (!defined('NG_RENDER_404')) { define('NG_RENDER_404', true); }
            return $tpl;
        }
    }

    // Single blog post — editorial reading template (post type `post` only).
    if (is_singular('post')) {
        $tpl = NG_THEME_ASSET_DIR . '/templates/single-post.php';
        if (file_exists($tpl)) {
            if (!defined('NG_RENDER_POST')) { define('NG_RENDER_POST', true); }
            return $tpl;
        }
    }

    // Empty search — themed "no results" surface. Non-empty searches
    // fall through so Blocksy / default search.php renders normally.
    if (is_search()) {
        global $wp_query;
        if ($wp_query && (int) $wp_query->found_posts === 0) {
            $tpl = NG_THEME_ASSET_DIR . '/templates/search.php';
            if (file_exists($tpl)) {
                if (!defined('NG_RENDER_SEARCH_EMPTY')) { define('NG_RENDER_SEARCH_EMPTY', true); }
                return $tpl;
            }
        }
    }

    $page = get_query_var('neogen_page');
    if (!$page) { return $template; }

    if ($page === 'legal') {
        $legal = NG_THEME_ASSET_DIR . '/templates/legal.php';
        if (!file_exists($legal)) { return $template; }
        if (!defined('NG_RENDER_LEGAL_PAGE')) {
            define('NG_RENDER_LEGAL_PAGE', true);
        }
        return $legal;
    }

    $info = ng_info_pages();
    if (isset($info[$page])) {
        $tpl = NG_THEME_ASSET_DIR . '/templates/info-page.php';
        if (!file_exists($tpl)) { return $template; }
        if (!defined('NG_RENDER_INFO_PAGE')) {
            define('NG_RENDER_INFO_PAGE', $page);
        }
        return $tpl;
    }

    return $template;
}, 98);

/**
 * Route WooCommerce template parts to our overrides. Keeps all
 * deployable code inside mu-plugins/ (the known-reliable deploy
 * target) instead of themes/blocksy-child/woocommerce/.
 *
 * Only `content-product.php` is overridden at this stage — the
 * shop-archive loop card. Single-product stays Blocksy-default
 * until explicitly themed.
 */
add_filter('wc_get_template_part', function ($template, $slug, $name) {
    $map = [
        'content|product'        => 'content-product.php',
        'content|single-product' => 'content-single-product.php',
    ];
    $key = $slug . '|' . $name;
    if (isset($map[$key])) {
        $override = NG_THEME_ASSET_DIR . '/templates/woocommerce/' . $map[$key];
        if (file_exists($override)) {
            return $override;
        }
    }
    return $template;
}, 10, 3);

/**
 * v1.34.3: drop WC's default loop thumbnail hook.
 *
 * Our content-product.php override renders the product image inline
 * (with brand polish + LED badge) before do_action(
 * 'woocommerce_before_shop_loop_item_title' ). WC ships
 * woocommerce_template_loop_product_thumbnail hooked there at prio 10,
 * which was printing a duplicate image stacked above ours on every
 * archive (shop / product_cat / tag / search). Remove it once.
 */
add_action( 'init', function () {
    remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
}, 20 );

/**
 * Route full Woo template paths (not template_parts) to our overrides.
 * Used for checkout/thankyou.php etc.
 */
add_filter('wc_get_template', function ($template, $template_name, $args, $template_path, $default_path) {
    $overrides = [
        'checkout/thankyou.php',
        'cart/cart.php',
        'cart/cart-empty.php',
        'cart/cart-totals.php',
        'emails/email-header.php',
        'emails/email-footer.php',
        'emails/email-styles.php',
        'emails/customer-processing-order.php',
        'myaccount/navigation.php',
        'myaccount/dashboard.php',
        'myaccount/orders.php',
        'myaccount/form-login.php',
        'checkout/form-checkout.php',
    ];
    if (in_array($template_name, $overrides, true)) {
        $candidate = NG_THEME_ASSET_DIR . '/templates/woocommerce/' . $template_name;
        if (file_exists($candidate)) {
            return $candidate;
        }
    }
    return $template;
}, 10, 5);

/**
 * Bilingual title field on the Woo product editor.
 * Writes to _ng_ar_title — the meta key our content-product.php and
 * content-single-product.php templates both read (falls back to the
 * English title when empty).
 */
add_action('woocommerce_product_options_general_product_data', function () {
    global $post;
    woocommerce_wp_text_input([
        'id'          => '_ng_ar_title',
        'label'       => __('Arabic title', 'neogen'),
        'placeholder' => get_post_meta($post->ID, '_ng_ar_title', true) ?: '',
        'desc_tip'    => true,
        'description' => __('Shown above the English title on shop and single-product pages. Leave blank to reuse the English title.', 'neogen'),
        'value'       => get_post_meta($post->ID, '_ng_ar_title', true),
    ]);
});

add_action('woocommerce_process_product_meta', function ($post_id) {
    if (!current_user_can('edit_post', $post_id)) { return; }
    $val = isset($_POST['_ng_ar_title']) ? sanitize_text_field((string) wp_unslash($_POST['_ng_ar_title'])) : '';
    if ($val === '') {
        delete_post_meta($post_id, '_ng_ar_title');
    } else {
        update_post_meta($post_id, '_ng_ar_title', $val);
    }
});

/**
 * Category archive header — styled intro block above the product loop
 * on /product-category/<slug>/ pages. Uses the term's description
 * verbatim (admins can enter AR + EN there).
 */
add_action('woocommerce_archive_description', function () {
    if (!is_product_taxonomy()) { return; }
    $term = get_queried_object();
    if (!$term || empty($term->term_id)) { return; }

    $count = (int) $term->count;
    $desc  = term_description($term->term_id, $term->taxonomy);
    ?>
    <header class="ng-cat-header">
      <div class="ng-cat-kicker">
        <span class="led on"></span>
        <span>الفئة / <?php echo esc_html( $term->taxonomy === 'product_cat' ? 'رف' : 'وسم' ); ?></span>
        <span class="sep"></span>
        <span><?php echo esc_html(sprintf(_n('%d SKU', '%d SKUs', $count, 'neogen'), $count)); ?></span>
      </div>
      <h1 class="ng-cat-h1"><?php echo esc_html( ng_ar_label( $term->name ) ); ?></h1>
      <?php if ($desc) : ?>
        <div class="ng-cat-desc"><?php echo wp_kses_post($desc); ?></div>
      <?php endif; ?>
    </header>
    <?php
}, 5);

/**
 * Swap the front-page template for our branded one. The template itself
 * guards against direct-require via the NG_RENDER_FRONT_PAGE sentinel.
 */
add_filter('template_include', function ($template) {
    if (is_admin() || !is_front_page()) {
        return $template;
    }
    $front = NG_THEME_ASSET_DIR . '/templates/front-page.php';
    if (!file_exists($front)) {
        return $template;
    }
    if (!defined('NG_RENDER_FRONT_PAGE')) {
        define('NG_RENDER_FRONT_PAGE', true);
    }
    return $front;
}, 99);

/**
 * Fallback shortcode for when the front page is configured to display a
 * specific static page that still renders through Blocksy's content
 * area — content editors can drop [neogen_home_sections] into that page.
 */
add_shortcode('neogen_home_sections', function () {
    $front = NG_THEME_ASSET_DIR . '/templates/front-page.php';
    if (!file_exists($front)) { return ''; }
    if (!defined('NG_RENDER_FRONT_PAGE')) {
        define('NG_RENDER_FRONT_PAGE', true);
    }
    // The template calls get_header()/get_footer() — those are already in
    // progress when a shortcode runs, so we need the body markup only.
    // Parse out everything between the first <header class="ng-hero"> and
    // the closing </section> of the voice band.
    ob_start();
    include $front;
    $html = ob_get_clean();
    // Strip header/footer that the template emitted (we're inside content).
    $start = strpos($html, '<header class="ng-hero"');
    $end   = strrpos($html, '</section>');
    if ($start === false || $end === false) {
        return $html;
    }
    return substr($html, $start, $end - $start + strlen('</section>'));
});

/**
 * Sitewide sysbar + top nav injected right after <body>.
 * Blocksy's own header is hidden by CSS in neogen.css.
 */
add_action('wp_body_open', function () {
    if (is_admin()) { return; }
    // Phase 2b: respect the Blocksy handoff toggle. When ON, this
    // injector no-ops and Blocksy's header builder takes over.
    if ( function_exists('ng_blocksy_chrome_handoff') && ng_blocksy_chrome_handoff() ) { return; }

    $home = home_url('/');
    // Defensive resolution — if WC page options are unset/misconfigured
    // and the Woo helpers fall back to home, force the canonical paths
    // so the header icons never silently link to the storefront root.
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : '';
    if ( $shop === '' || untrailingslashit( $shop ) === untrailingslashit( $home ) ) {
        $shop = home_url('/shop/');
    }
    $cart = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '';
    if ( $cart === '' || untrailingslashit( $cart ) === untrailingslashit( $home ) ) {
        $cart = home_url('/cart/');
    }
    $acct = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '';
    if ( $acct === '' || untrailingslashit( $acct ) === untrailingslashit( $home ) ) {
        $acct = home_url('/my-account/');
    }
    // Search target — go straight to /?s= so the icon click lands on a search box.
    $search = home_url('/?s=');

    $cart_count = 0;
    if (function_exists('WC') && WC() && WC()->cart) {
        $cart_count = (int) WC()->cart->get_cart_contents_count();
    }

    // Top 5 live product categories for the nav (cached, 1h TTL).
    $cats = ng_top_product_cats(5);

    // Queue seed — a plausible in-range number; nudged by JS client-side.
    $queue_seed = 14;
    ?>
<div class="ng-sysbar" aria-label="حالة النظام">
  <span class="led" aria-hidden="true"></span>
  <span>الساعة <b id="ng-clock">00:00:00</b> الرياض</span>
  <span class="sep"></span>
  <span>المخزون <b class="cyan">مباشر</b></span>
  <span class="sep hide-sm"></span>
  <span class="hide-sm">في الانتظار <b id="ng-queue"><?php echo esc_html( $queue_seed ); ?></b> طلب</span>
  <span class="sep hide-sm"></span>
  <span class="hide-sm">شحن 2-5 أيام · الرياض · جدة · الدمام</span>
  <span class="spacer"></span>
  <span>الضريبة <b>15%</b> شاملة</span>
  <span class="sep hide-sm"></span>
  <span class="hide-sm">عربي</span>
</div>

<nav class="ng-topnav" aria-label="القائمة الرئيسية">
  <a class="ng-lockup" href="<?php echo esc_url( $home ); ?>" aria-label="الصفحة الرئيسية NeoGen Store">
    <img class="ng-lockup-mark" src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/logo/ng-mark.png' ); ?>" alt="" width="64" height="50" decoding="async">
    <span class="sep"></span>
    <span class="wordmark"><span class="neo">NEO</span><span class="gen">GEN</span></span>
  </a>
  <div class="ng-nav-cats">
    <?php foreach ( $cats as $term ) :
        $link = get_term_link( $term );
        if ( is_wp_error( $link ) ) { continue; }
    ?>
      <a href="<?php echo esc_url( $link ); ?>"><span class="dot"></span><?php echo esc_html( ng_ar_label( $term->name ) ); ?></a>
    <?php endforeach; ?>
  </div>
  <div class="ng-nav-tools">
    <a class="ng-nav-tool" href="<?php echo esc_url( $search ); ?>" aria-label="البحث في المتجر" rel="search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <span class="tool-label">البحث</span>
    </a>
    <a class="ng-nav-tool" href="<?php echo esc_url( $acct ); ?>" aria-label="الحساب">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.5-6 8-6s7 2 8 6"/></svg>
      <span class="tool-label">الحساب</span>
    </a>
    <a class="ng-nav-tool" href="<?php echo esc_url( $cart ); ?>" aria-label="السلة">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 5h3l2 12h10l2-8H7"/><circle cx="10" cy="20" r="1.2"/><circle cx="17" cy="20" r="1.2"/></svg>
      <span class="tool-label">السلة</span>
      <span class="count<?php echo $cart_count > 0 ? '' : ' is-empty'; ?>"><?php echo esc_html( $cart_count ); ?></span>
    </a>
  </div>
</nav>
    <?php
});

/**
 * Live cart-count update via Woo AJAX fragments.
 */
add_filter('woocommerce_add_to_cart_fragments', function ($fragments) {
    if (!function_exists('WC') || !WC() || !WC()->cart) { return $fragments; }
    $count = (int) WC()->cart->get_cart_contents_count();
    $fragments['.ng-nav-tools a[aria-label="السلة"] .count'] =
        '<span class="count' . ($count > 0 ? '' : ' is-empty') . '">' . esc_html($count) . '</span>';
    return $fragments;
});

/**
 * Sitewide footer injected on wp_footer. Blocksy's original is hidden by CSS.
 */
add_action('wp_footer', function () {
    if (is_admin()) { return; }
    // Phase 2b: respect the Blocksy handoff toggle. When ON, this
    // footer no-ops and Blocksy's footer builder takes over.
    if ( function_exists('ng_blocksy_chrome_handoff') && ng_blocksy_chrome_handoff() ) { return; }

    $home = home_url('/');
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : $home;
    $cats = ng_top_product_cats(6);
    $year = date_i18n('Y');
    ?>
<footer class="ng-footer">
  <!-- Trust badges row (v1.28.0) -->
  <div class="ng-foot-trust" aria-label="ضمانات المتجر">
    <div class="ng-foot-trust-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
        <rect x="5" y="11" width="14" height="9" rx="1"/>
        <path d="M8 11V8a4 4 0 0 1 8 0v3"/>
      </svg>
      <span class="ng-foot-trust-label">دفع آمن</span>
      <span class="ng-foot-trust-sub">Mada · Apple Pay · STC Pay · Tabby</span>
    </div>
    <div class="ng-foot-trust-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
        <path d="M3 7h11v9H3z"/>
        <path d="M14 10h4l3 3v3h-7"/>
        <circle cx="7" cy="18" r="2"/>
        <circle cx="17" cy="18" r="2"/>
      </svg>
      <span class="ng-foot-trust-label">شحن سريع</span>
      <span class="ng-foot-trust-sub">2-5 أيام داخل المملكة</span>
    </div>
    <div class="ng-foot-trust-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
        <path d="M3 12a9 9 0 1 0 3-6.7"/>
        <path d="M3 4v5h5"/>
      </svg>
      <span class="ng-foot-trust-label">إرجاع 14 يوم</span>
      <span class="ng-foot-trust-sub">بدون أسئلة</span>
    </div>
    <div class="ng-foot-trust-item">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
        <path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/>
        <path d="m9 12 2 2 4-4"/>
      </svg>
      <span class="ng-foot-trust-label">ضمان 12 شهر</span>
      <span class="ng-foot-trust-sub">على كل المنتجات</span>
    </div>
  </div>

  <!-- Newsletter capture (v1.28.0) -->
  <div class="ng-foot-newsletter">
    <div class="ng-foot-newsletter-inner">
      <div class="ng-foot-newsletter-text">
        <h3>عروض المشغّلين · مباشرة لبريدك</h3>
        <p>منتجات جديدة، تخفيضات حصرية، ونصائح تقنية مختصرة. لا سبام — يمكنك إلغاء الاشتراك في أي وقت.</p>
      </div>
      <form class="ng-foot-newsletter-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" novalidate>
        <input type="hidden" name="action" value="ng_newsletter_subscribe">
        <?php wp_nonce_field( 'ng_newsletter', 'ng_newsletter_nonce' ); ?>
        <label class="ng-foot-newsletter-field">
          <span class="screen-reader-text">بريدك الإلكتروني</span>
          <input type="email" name="email" placeholder="بريدك الإلكتروني" autocomplete="email" required>
        </label>
        <button type="submit" class="btn btn-primary">
          اشترك
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </button>
      </form>
    </div>
  </div>

  <div class="ng-foot-inner">
    <div class="ng-foot-col ng-foot-brand">
      <a class="ng-lockup" href="<?php echo esc_url( $home ); ?>" style="margin-bottom:4px;">
        <img class="ng-lockup-mark" src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/logo/ng-mark.png' ); ?>" alt="" width="48" height="37" decoding="async">
        <span class="sep" style="height:20px;"></span>
        <span class="wordmark" style="font-size:24px;"><span class="neo">NEO</span><span class="gen">GEN</span></span>
      </a>
      <p>متجر تقني سعودي. نختار المنتجات بعناية، نوضح المواصفات بدون مبالغة، ونبني تجربة شراء تناسب المستخدم التقني الذي يعرف ما يحتاجه.</p>
    </div>
    <div class="ng-foot-col">
      <h4>// الكتالوج</h4>
      <ul>
        <?php if ( !empty( $cats ) ) :
            foreach ( $cats as $term ) :
                $link = get_term_link( $term );
                if ( is_wp_error( $link ) ) { continue; }
        ?>
          <li><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( ng_ar_label( $term->name ) ); ?></a></li>
        <?php   endforeach;
        else : ?>
          <li><a href="<?php echo esc_url( $shop ); ?>">تصفّح المتجر</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="ng-foot-col">
      <h4>// الدعم</h4>
      <ul>
        <li><a href="<?php echo esc_url( home_url( '/my-account/orders/' ) ); ?>">تتبّع الطلب</a></li>
        <li><a href="<?php echo esc_url( home_url( '/returns/' ) ); ?>">الإرجاع · 14 يوم</a></li>
        <li><a href="<?php echo esc_url( home_url( '/warranty/' ) ); ?>">الضمان · 12 شهر</a></li>
        <li><a href="<?php echo esc_url( home_url( '/shipping/' ) ); ?>">الشحن · 2-5 أيام</a></li>
        <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">تواصل معنا</a></li>
      </ul>
    </div>
    <div class="ng-foot-col">
      <h4>// معلومات</h4>
      <ul>
        <li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">من نحن</a></li>
        <li><a href="<?php echo esc_url( home_url( '/shipping/' ) ); ?>">سياسة الشحن</a></li>
        <li><a href="<?php echo esc_url( home_url( '/returns/' ) ); ?>">سياسة الاسترجاع</a></li>
        <li><a href="<?php echo esc_url( home_url( '/warranty/' ) ); ?>">سياسة الضمان</a></li>
        <li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">الشروط والأحكام</a></li>
        <li><a href="<?php echo esc_url( home_url( '/usage/' ) ); ?>">سياسة الاستخدام</a></li>
        <li><a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">سياسة الخصوصية</a></li>
        <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">تواصل معنا</a></li>
        <li><a href="<?php echo esc_url( home_url( '/legal/' ) ); ?>">هوية المنشأة</a></li>
      </ul>
    </div>
    <?php $cr_foot = ng_cr(); ?>
    <div class="ng-foot-col">
      <h4>// المتجر</h4>
      <ul>
        <li>سجل تجاري · <?php echo esc_html( $cr_foot['cr'] ); ?></li>
        <?php foreach ( $cr_foot['regulatory'] as $r ) : ?>
          <li><?php echo esc_html( $r['authority_ar'] ?? strtoupper( $r['key'] ) ); ?> · <?php echo esc_html( $r['number'] ); ?></li>
        <?php endforeach; ?>
        <li>الضريبة · 15% شاملة</li>
        <li>الرياض · جدة · الدمام</li>
        <li>عربي</li>
      </ul>
    </div>
  </div>
  <div class="ng-foot-pay" aria-label="الدفع والشحن">
    <span class="ng-foot-pay-label">// الدفع · الشحن</span>
    <div class="ng-foot-pay-row">
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/pay/mada.svg' ); ?>"      width="42" height="18" alt="Mada"      loading="lazy">
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/pay/apple-pay.svg' ); ?>" width="42" height="18" alt="Apple Pay" loading="lazy">
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/pay/stcpay.svg' ); ?>"    width="42" height="18" alt="STC Pay"   loading="lazy">
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/pay/tabby.svg' ); ?>"     width="42" height="18" alt="Tabby"     loading="lazy">
      <span class="ng-foot-pay-sep"></span>
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/ship/smsa.svg' ); ?>"     width="46" height="18" alt="SMSA"      loading="lazy">
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/ship/aramex.svg' ); ?>"   width="46" height="18" alt="Aramex"    loading="lazy">
    </div>
  </div>
  <?php $cr = ng_cr(); ?>
  <div class="ng-disclosure" role="complementary" aria-label="هوية المنشأة">
    <div class="ng-disclosure-en">
      <span><?php echo esc_html( $cr['entity_type_ar'] ?? $cr['entity_type'] ); ?></span>
      <span class="sep"></span>
      <span><?php echo esc_html($cr['owner']); ?></span>
      <span class="sep"></span>
      <span>سجل تجاري <b><?php echo esc_html($cr['cr']); ?></b></span>
      <span class="sep"></span>
      <span class="ok"><?php echo esc_html( $cr['status_ar'] ?? $cr['status'] ); ?></span>
      <span class="sep"></span>
      <span><?php echo esc_html($cr['phone_mobile']); ?></span>
    </div>
    <a class="ng-disclosure-link" href="<?php echo esc_url( home_url('/legal/') ); ?>">
      هوية المنشأة
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
    </a>
  </div>
  <div class="ng-foot-bottom">
    <span>© <?php echo esc_html( $year ); ?> <b><?php echo esc_html($cr['brand_en']); ?></b> · جميع الحقوق محفوظة</span>
    <span>الرياض · جدة · الدمام</span>
    <span>NEOGEN.STORE</span>
  </div>
</footer>
    <?php
}, 5);
