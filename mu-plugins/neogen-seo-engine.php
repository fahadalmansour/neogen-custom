<?php
/**
 * Plugin Name: NeoGen SEO Engine
 * Description: In-house SEO emission for neogen.store (title, description, canonical, OG, Twitter, JSON-LD). Phase R1 of the Rank Math replacement plan — computes everything per surface but emits ONLY HTML parity comments (gated by NG_SEO_PARITY constant) so the live SEO surface is unchanged. Real emission + Rank Math suppression land in later phases.
 * Version: 1.22.0
 * Author: Fahad Almansour
 *
 * Phase R1 contract
 * -----------------
 * - This file is INERT by default. View-source on a page shows nothing
 *   from this engine unless the operator defines NG_SEO_PARITY in
 *   wp-config.php. With that flag on, every wp_head fires a block of
 *   `<!-- NG-SEO ... -->` comments documenting what THIS engine would
 *   emit for the current surface, so the operator can diff against
 *   Rank Math's actual meta tags in the same HTML.
 * - Existing emitters in mu-plugins/neogen-theme.php (Store JSON-LD)
 *   and mu-plugins/neogen-seo.php (OG image, Twitter strips, robots
 *   meta override) continue to fire as today. The engine ONLY shadows
 *   them. No risk of dual emission, no risk of dropped meta.
 * - Surface detection covers the 11 cases the audit catalogued:
 *   home, post, page, product, product_category, shop, search, 404,
 *   category, tag, archive (fallback). Per-post overrides via
 *   `_neogen_seo_*` post-meta keys are read here but not yet written
 *   anywhere (Phase R2 builds the metabox).
 *
 * Surfaces NOT yet covered (left intentionally for a later phase):
 *   - WP-core author archives (we don't ship author pages)
 *   - Custom post types beyond product/post/page
 *   - Date-based archives (low SEO value here)
 *
 * The engine is read-only with respect to WP state — no DB writes,
 * no transient mutations, no side effects beyond the parity comments.
 */

defined('ABSPATH') || exit;

if ( ! class_exists('NG_SEO_Engine') ) :

class NG_SEO_Engine {

    const VERSION = '1.22.0';
    const SITE_NAME = 'NeoGen Store';
    const SITE_NAME_AR = 'نيوجين ستور';
    const TITLE_SEPARATOR = ' | ';
    const DEFAULT_DESCRIPTION = 'NeoGen Store — متجر تقني سعودي للشبكات، الهوم لاب، البيوت الذكية، والألعاب. شحن من داخل المملكة، ضمان 12 شهر، إرجاع 14 يوم.';

    /**
     * Map the current request to one of the 11 known surfaces.
     */
    public static function surface() {
        if ( is_admin() || is_customize_preview() ) return 'admin';
        if ( is_404() ) return '404';
        if ( is_search() ) return 'search';
        if ( is_front_page() || is_home() ) return 'home';
        if ( function_exists('is_product') && is_product() ) return 'product';
        if ( function_exists('is_product_category') && is_product_category() ) return 'product_category';
        if ( function_exists('is_shop') && is_shop() ) return 'shop';
        if ( is_singular('post') ) return 'post';
        if ( is_singular('page') ) return 'page';
        if ( is_category() ) return 'category';
        if ( is_tag() ) return 'tag';
        if ( is_archive() ) return 'archive';
        if ( is_singular() ) return 'singular';
        return 'other';
    }

    /**
     * Read a per-post override stored in post-meta. Falls back to null
     * if not set. Phase R2 ships the metabox that writes these keys;
     * the engine reads them now so the contract is forward-compatible.
     */
    private static function post_override( $key, $post_id = null ) {
        if ( ! $post_id ) $post_id = get_the_ID();
        if ( ! $post_id ) return null;
        $val = get_post_meta( $post_id, '_neogen_seo_' . $key, true );
        if ( ! is_string($val) || $val === '' ) return null;
        return $val;
    }

    /* =====================================================================
     * TITLE
     * ===================================================================== */

    public static function title() {
        $surface = self::surface();
        $brand   = self::SITE_NAME;
        $sep     = self::TITLE_SEPARATOR;

        // Per-post override always wins.
        $override = self::post_override('title');
        if ( $override !== null ) return $override;

        switch ( $surface ) {
            case 'home':
                return 'NeoGen Store · متجر تقني سعودي للشبكات والهوم لاب والبيوت الذكية';

            case 'product':
            case 'post':
            case 'page':
            case 'singular':
                $t = get_the_title();
                return $t !== '' ? ( $t . $sep . $brand ) : $brand;

            case 'product_category':
            case 'category':
            case 'tag':
                $term = get_queried_object();
                $name = $term && isset($term->name) ? $term->name : '';
                return $name !== '' ? ( $name . $sep . $brand ) : $brand;

            case 'shop':
                return 'المتجر' . $sep . $brand;

            case 'search':
                $q = function_exists('get_search_query') ? get_search_query() : '';
                return $q !== ''
                    ? ( 'نتائج البحث: ' . $q . $sep . $brand )
                    : ( 'بحث' . $sep . $brand );

            case '404':
                return 'صفحة غير موجودة' . $sep . $brand;

            case 'archive':
                $title = function_exists('get_the_archive_title') ? wp_strip_all_tags( get_the_archive_title() ) : '';
                return $title !== '' ? ( $title . $sep . $brand ) : $brand;

            default:
                return $brand;
        }
    }

    /* =====================================================================
     * DESCRIPTION
     * ===================================================================== */

    public static function description() {
        $surface = self::surface();

        $override = self::post_override('description');
        if ( $override !== null ) return self::clamp_description($override);

        switch ( $surface ) {
            case 'home':
                return self::DEFAULT_DESCRIPTION;

            case 'product':
                if ( function_exists('wc_get_product') ) {
                    $product = wc_get_product( get_the_ID() );
                    if ( $product ) {
                        $short = $product->get_short_description();
                        if ( $short !== '' ) return self::clamp_description( wp_strip_all_tags($short) );
                        $long = $product->get_description();
                        if ( $long !== '' ) return self::clamp_description( wp_strip_all_tags($long) );
                    }
                }
                $title = get_the_title();
                return self::clamp_description( $title !== ''
                    ? sprintf('اشترِ %s من NeoGen Store. شحن سريع داخل المملكة، ضمان 12 شهر، إرجاع 14 يوم.', $title)
                    : self::DEFAULT_DESCRIPTION );

            case 'post':
            case 'page':
            case 'singular':
                $excerpt = get_the_excerpt();
                if ( $excerpt !== '' ) return self::clamp_description( wp_strip_all_tags($excerpt) );
                $content = get_post_field( 'post_content', get_the_ID() );
                if ( $content !== '' ) {
                    return self::clamp_description( wp_strip_all_tags( strip_shortcodes($content) ) );
                }
                return self::DEFAULT_DESCRIPTION;

            case 'product_category':
            case 'category':
            case 'tag':
                $term = get_queried_object();
                if ( $term && ! empty($term->description) ) {
                    return self::clamp_description( wp_strip_all_tags($term->description) );
                }
                $name = $term && isset($term->name) ? $term->name : '';
                return self::clamp_description( $name !== ''
                    ? sprintf('تصفح %s على NeoGen Store. منتجات مختارة بعناية، شحن سريع، ضمان رسمي.', $name)
                    : self::DEFAULT_DESCRIPTION );

            case 'shop':
                return 'تصفح كل منتجات NeoGen Store: شبكات، هوم لاب، بيوت ذكية، ألعاب، بطاقات رقمية. شحن من داخل المملكة.';

            case 'search':
                $q = function_exists('get_search_query') ? get_search_query() : '';
                return $q !== ''
                    ? sprintf('نتائج البحث عن "%s" في NeoGen Store.', $q)
                    : 'البحث في NeoGen Store.';

            case '404':
                return 'الصفحة المطلوبة غير موجودة. تصفح متجرنا للعثور على ما تحتاجه.';

            default:
                return self::DEFAULT_DESCRIPTION;
        }
    }

    private static function clamp_description( $text ) {
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        $text = trim($text);
        // 155 chars is the SERP-display sweet spot; over-clamp gracefully.
        if ( function_exists('mb_strlen') && mb_strlen($text) > 160 ) {
            $text = mb_substr($text, 0, 155) . '…';
        } elseif ( strlen($text) > 160 ) {
            $text = substr($text, 0, 155) . '…';
        }
        return $text;
    }

    /* =====================================================================
     * CANONICAL
     * ===================================================================== */

    public static function canonical() {
        $surface = self::surface();

        $override = self::post_override('canonical');
        if ( $override !== null ) return esc_url_raw($override);

        switch ( $surface ) {
            case 'home':
                return home_url('/');

            case 'product':
            case 'post':
            case 'page':
            case 'singular':
                $url = get_permalink();
                return $url ?: home_url('/');

            case 'product_category':
            case 'category':
            case 'tag':
                $term = get_queried_object();
                if ( $term && isset($term->term_id) ) {
                    $url = get_term_link( (int) $term->term_id );
                    if ( ! is_wp_error($url) ) return $url;
                }
                return home_url('/');

            case 'shop':
                if ( function_exists('wc_get_page_permalink') ) {
                    $shop = wc_get_page_permalink('shop');
                    if ( $shop ) return $shop;
                }
                return home_url('/shop/');

            case 'search':
                $q = function_exists('get_search_query') ? get_search_query() : '';
                return home_url( '/?s=' . rawurlencode($q) );

            case '404':
                // 404s shouldn't have a canonical; return empty.
                return '';

            case 'archive':
                $url = function_exists('get_post_type_archive_link') ? get_post_type_archive_link( get_post_type() ) : '';
                return $url ?: home_url('/');

            default:
                return home_url('/');
        }
    }

    /* =====================================================================
     * ROBOTS
     * ===================================================================== */

    public static function robots() {
        $surface = self::surface();

        $override = self::post_override('robots');
        if ( $override !== null ) return $override;

        // Defaults: index everything except search results and 404s.
        if ( $surface === 'search' || $surface === '404' ) {
            return 'noindex, follow';
        }
        return 'index, follow, max-snippet:-1, max-video-preview:-1, max-image-preview:large';
    }

    /* =====================================================================
     * OPEN GRAPH
     * ===================================================================== */

    public static function og_meta() {
        $surface = self::surface();
        $title   = self::title();
        $desc    = self::description();
        $url     = self::canonical();

        $type = 'website';
        if ( $surface === 'post' )        $type = 'article';
        elseif ( $surface === 'product' ) $type = 'product';

        $meta = [
            'og:type'        => $type,
            'og:title'       => $title,
            'og:description' => $desc,
            'og:url'         => $url ?: home_url('/'),
            'og:site_name'   => self::SITE_NAME,
            'og:locale'      => 'ar_SA',
            'og:locale:alternate' => 'en_US',
        ];

        // Image — surface-specific, with sensible fallback.
        $image = self::og_image_url();
        if ( $image !== '' ) {
            $meta['og:image']        = $image;
            $meta['og:image:width']  = 1200;
            $meta['og:image:height'] = 630;
        }

        // Article-specific meta when applicable.
        if ( $type === 'article' ) {
            $pub = function_exists('get_the_date') ? get_the_date('c') : '';
            $mod = function_exists('get_the_modified_date') ? get_the_modified_date('c') : '';
            if ( $pub ) $meta['article:published_time'] = $pub;
            if ( $mod ) $meta['article:modified_time']  = $mod;
        }

        // Product-specific meta when applicable.
        if ( $type === 'product' && function_exists('wc_get_product') ) {
            $product = wc_get_product( get_the_ID() );
            if ( $product ) {
                $price = $product->get_price();
                if ( $price !== '' ) {
                    $meta['product:price:amount']   = $price;
                    $meta['product:price:currency'] = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'SAR';
                }
                $availability = $product->is_in_stock() ? 'instock' : 'oos';
                $meta['product:availability'] = $availability;
            }
        }

        return $meta;
    }

    private static function og_image_url() {
        $surface = self::surface();
        $base    = defined('NG_THEME_ASSET_URL') ? NG_THEME_ASSET_URL : '';

        // Singular: featured image first.
        if ( in_array($surface, ['post', 'page', 'product', 'singular'], true) ) {
            if ( has_post_thumbnail() ) {
                $img = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
                if ( is_array($img) && ! empty($img[0]) ) return $img[0];
            }
        }

        // Product category: term image if set.
        if ( $surface === 'product_category' ) {
            $term = get_queried_object();
            if ( $term && isset($term->term_id) ) {
                $thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
                if ( $thumb_id ) {
                    $img = wp_get_attachment_image_src( $thumb_id, 'large' );
                    if ( is_array($img) && ! empty($img[0]) ) return $img[0];
                }
            }
        }

        // Sitewide fallback — locale-aware.
        if ( $base !== '' ) {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $ar     = strpos((string) $locale, 'ar') === 0;
            return $base . ( $ar ? '/img/social/og-default-ar.png' : '/img/social/og-default-en.png' );
        }

        return '';
    }

    /* =====================================================================
     * TWITTER CARD
     * ===================================================================== */

    public static function twitter_meta() {
        $title = self::title();
        $desc  = self::description();
        $img   = self::twitter_image_url();

        $meta = [
            'twitter:card'        => 'summary_large_image',
            'twitter:title'       => $title,
            'twitter:description' => $desc,
        ];
        if ( $img !== '' ) $meta['twitter:image'] = $img;

        return $meta;
    }

    private static function twitter_image_url() {
        $surface = self::surface();
        $base    = defined('NG_THEME_ASSET_URL') ? NG_THEME_ASSET_URL : '';

        if ( in_array($surface, ['post', 'page', 'product', 'singular'], true) ) {
            if ( has_post_thumbnail() ) {
                $img = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
                if ( is_array($img) && ! empty($img[0]) ) return $img[0];
            }
        }

        if ( $base !== '' ) {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            $ar     = strpos((string) $locale, 'ar') === 0;
            return $base . ( $ar ? '/img/social/twitter-card-ar.png' : '/img/social/twitter-card-en.png' );
        }
        return '';
    }

    /* =====================================================================
     * JSON-LD GRAPH
     *
     * R1 emits a complete graph in PARITY comments only. The actual
     * Store/WebSite/WebPage emission stays in mu-plugins/neogen-theme.php
     * unchanged. R4 will move that here and add suppression for Rank
     * Math's emission.
     * ===================================================================== */

    public static function json_ld_types() {
        $surface = self::surface();
        $types = ['Store', 'WebSite', 'WebPage'];

        switch ( $surface ) {
            case 'product':
                $types[] = 'Product';
                $types[] = 'Offer';
                $types[] = 'BreadcrumbList';
                break;
            case 'product_category':
                $types[] = 'CollectionPage';
                $types[] = 'ItemList';
                $types[] = 'BreadcrumbList';
                break;
            case 'shop':
                $types[] = 'CollectionPage';
                $types[] = 'BreadcrumbList';
                break;
            case 'post':
                $types[] = 'Article';
                $types[] = 'BreadcrumbList';
                break;
            case 'page':
                $types[] = 'BreadcrumbList';
                break;
            case 'search':
                // SearchAction already lives inside WebSite as a potentialAction.
                break;
            case '404':
                // Nothing extra.
                break;
        }
        return $types;
    }

    /* =====================================================================
     * PARITY COMMENT EMITTER (the only thing R1 actually emits)
     * ===================================================================== */

    public static function emit_parity_comments() {
        if ( is_admin() || is_customize_preview() ) return;
        if ( ! ( defined('NG_SEO_PARITY') && NG_SEO_PARITY ) ) return;

        try {
            $surface  = self::surface();
            $title    = self::title();
            $desc     = self::description();
            $canon    = self::canonical();
            $robots   = self::robots();
            $og       = self::og_meta();
            $tw       = self::twitter_meta();
            $ld_types = self::json_ld_types();

            echo "\n<!-- ===== NG-SEO PARITY v" . esc_html(self::VERSION) . " ===== -->\n";
            echo "<!-- NG-SEO surface:     " . esc_html($surface) . " -->\n";
            echo "<!-- NG-SEO title:       " . esc_html($title) . " -->\n";
            echo "<!-- NG-SEO description: " . esc_html($desc) . " -->\n";
            echo "<!-- NG-SEO canonical:   " . esc_html($canon) . " -->\n";
            echo "<!-- NG-SEO robots:      " . esc_html($robots) . " -->\n";
            foreach ( $og as $k => $v ) {
                echo '<!-- NG-SEO og: ' . esc_html($k) . ' = ' . esc_html((string) $v) . " -->\n";
            }
            foreach ( $tw as $k => $v ) {
                echo '<!-- NG-SEO tw: ' . esc_html($k) . ' = ' . esc_html((string) $v) . " -->\n";
            }
            echo '<!-- NG-SEO json_ld_types: ' . esc_html(implode(', ', $ld_types)) . " -->\n";
            echo "<!-- ===== /NG-SEO PARITY ===== -->\n\n";
        } catch ( Throwable $e ) {
            echo "\n<!-- NG-SEO PARITY ERROR: " . esc_html($e->getMessage()) . " -->\n";
        }
    }
}

endif; // class_exists guard

// Late priority so the comments land near the end of <head>, after
// Rank Math's actual meta tags, making side-by-side diffing easier.
add_action('wp_head', ['NG_SEO_Engine', 'emit_parity_comments'], 999);
