<?php
/**
 * Plugin Name: NeoGen SEO
 * Description: Security headers, /llms.txt, robots.txt AI-crawler policy, and homepage meta-description override.
 * Version: 1.12.2
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

/**
 * Security headers — conservative set safe for a WooCommerce site.
 * CSP intentionally omitted; layer it at Cloudflare or origin where
 * payment-gateway domains can be vetted without crashing checkout.
 */
add_action('send_headers', function () {
    if ( is_admin() ) return;
    if ( ! headers_sent() ) {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header('Permissions-Policy: interest-cohort=(), browsing-topics=()');
        if ( is_ssl() ) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
});

/**
 * /ads.txt — IAB authorized digital sellers manifest for AdSense.
 * Publisher ID is set in admin Tools → NeoGen Merchant; falls back
 * to the AdSense client connected via Site Kit if available.
 */
add_action('init', function () {
    if ( ! isset($_SERVER['REQUEST_URI']) ) return;
    $path = strtok( (string) $_SERVER['REQUEST_URI'], '?' );
    if ( $path !== '/ads.txt' && $path !== '/ads.txt/' ) return;

    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');

    // Read AdSense client ID from Site Kit option, fallback to a stored override.
    $sitekit_settings = get_option('googlesitekit_adsense_settings', []);
    $client_id = '';
    if ( is_array($sitekit_settings) && ! empty($sitekit_settings['clientID']) ) {
        $client_id = (string) $sitekit_settings['clientID'];
    }
    if ( $client_id === '' ) {
        $client_id = (string) get_option('ng_adsense_client_id', '');
    }
    if ( $client_id === '' ) {
        // No publisher configured — emit a comment so crawlers see we tried.
        echo "# ads.txt placeholder — no AdSense publisher configured yet.\n";
        exit;
    }
    // Strip 'ca-' prefix if Site Kit stored it that way (clientID is 'ca-pub-…')
    $pub = preg_replace('/^ca-/i', '', $client_id);

    echo "# ads.txt — neogen.store · auto-generated\n";
    echo "google.com, " . $pub . ", DIRECT, f08c47fec0942fa0\n";
    exit;
}, 1);

/**
 * /llms.txt — minimal LLM-readable site index. Intercepts the request
 * before WordPress hits 404 and emits text/plain.
 */
add_action('init', function () {
    if ( ! isset($_SERVER['REQUEST_URI']) ) return;
    $path = strtok( (string) $_SERVER['REQUEST_URI'], '?' );
    if ( $path !== '/llms.txt' && $path !== '/llms.txt/' ) return;

    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex, follow', true);

    $home = rtrim( home_url('/'), '/' );

    echo "# NeoGen Store\n";
    echo "# Saudi tech retail · networking · homelab · smart home · gaming\n";
    echo "# Updated: " . gmdate('Y-m-d') . "\n";
    echo "\n";
    echo "## Identity\n";
    echo "Name: NeoGen Store\n";
    echo "Name (AR): نيوجين ستور\n";
    echo "URL: $home/\n";
    echo "Country: Saudi Arabia\n";
    echo "Languages: ar-SA, en\n";
    echo "\n";
    echo "## Primary URLs\n";
    echo "Home: $home/\n";
    echo "Shop: $home/shop/\n";

    if ( taxonomy_exists('product_cat') && function_exists('ng_top_product_cats') ) {
        echo "\n## Categories\n";
        $cats = ng_top_product_cats(12);
        foreach ( (array) $cats as $term ) {
            $link = get_term_link($term);
            if ( ! is_wp_error($link) ) {
                echo $term->name . ': ' . $link . "\n";
            }
        }
    }

    echo "\n## Information\n";
    foreach ( ['about', 'shipping', 'returns', 'warranty', 'privacy', 'terms', 'contact'] as $slug ) {
        echo ucfirst($slug) . ": $home/$slug/\n";
    }
    echo "Legal disclosure: $home/legal/\n";
    echo "\n";
    echo "## Notes\n";
    echo "- Single-merchant Saudi e-commerce, CR 7053130576.\n";
    echo "- Catalog is curated; SKUs are vetted, not drop-shipped.\n";
    echo "- Payment: Mada, Apple Pay, STC Pay, Tabby.\n";
    echo "- Shipping: Riyadh, Jeddah, Dammam (2-5 business days).\n";

    exit;
}, 1);

/**
 * robots.txt — explicit AI crawler policy.
 *
 * Allow citation/search crawlers (ChatGPT-User, PerplexityBot,
 * FacebookBot retrieving on-demand for share previews); block
 * training-only crawlers (GPTBot, ClaudeBot, anthropic-ai, Google
 * Extended training, CCBot, Bytespider). Common-sense default.
 */
add_filter('robots_txt', function ($output, $public) {
    if ( ! $public ) return $output; // respect "discourage search engines" setting

    $rules  = "\n# AI crawler policy — explicit per neogen.store\n";
    $rules .= "User-agent: GPTBot\nDisallow: /\n\n";
    $rules .= "User-agent: ClaudeBot\nDisallow: /\n\n";
    $rules .= "User-agent: anthropic-ai\nDisallow: /\n\n";
    $rules .= "User-agent: Google-Extended\nDisallow: /\n\n";
    $rules .= "User-agent: CCBot\nDisallow: /\n\n";
    $rules .= "User-agent: Bytespider\nDisallow: /\n\n";
    $rules .= "User-agent: Amazonbot\nDisallow: /\n\n";
    $rules .= "User-agent: ChatGPT-User\nAllow: /\n\n";
    $rules .= "User-agent: PerplexityBot\nAllow: /\n\n";
    $rules .= "User-agent: FacebookBot\nAllow: /\n\n";
    return $output . $rules;
}, 10, 2);

/**
 * Homepage meta description — overrides any plugin-emitted description
 * with a 120-155 char canonical sentence covering brand, geography,
 * verticals, fulfilment, and warranty. Only on the front page.
 */
add_filter('document_title_parts', function ($parts) { return $parts; });

add_action('wp_head', function () {
    if ( ! ( is_front_page() || is_home() ) ) return;

    $desc_ar = 'NeoGen Store — متجر تقني سعودي للشبكات، الهوم لاب، البيوت الذكية، والألعاب. شحن من داخل المملكة، ضمان 12 شهر، إرجاع 14 يوم.';
    // 145 chars (AR is denser; ~145 visual chars matches the 120-155 latin target).

    echo "\n<!-- NeoGen SEO: canonical home description -->\n";
    echo '<meta name="description" content="' . esc_attr( $desc_ar ) . '">' . "\n";
}, 1);

/**
 * Strip any duplicate meta description tags that other plugins emit
 * AFTER ours. Runs late on wp_head; uses output buffering window.
 *
 * Disabled by default — enable only if Rank Math etc. fights us.
 * Toggle by defining NG_SEO_DEDUP_DESC in wp-config.
 */
if ( defined('NG_SEO_DEDUP_DESC') && NG_SEO_DEDUP_DESC ) {
    add_action('wp_head', function () {
        if ( ! ( is_front_page() || is_home() ) ) return;
        ob_start();
    }, 0);
    add_action('wp_head', function () {
        if ( ! ( is_front_page() || is_home() ) ) return;
        $html = ob_get_clean();
        // keep first description, drop subsequent
        $count = 0;
        $html = preg_replace_callback(
            '#<meta\s+name=["\']description["\'][^>]*>#i',
            function ($m) use (&$count) {
                $count++;
                return $count === 1 ? $m[0] : '';
            },
            $html
        );
        echo $html;
    }, 9999);
}

/* =====================================================================
 * v1.10.3 — Force-fix every code-reachable SEO finding
 * ===================================================================== */

/**
 * Universal legacy-host rewriter. Used by nav menus, post content,
 * widgets, and Rank Math sitemap output.
 */
function ng_seo_rewrite_legacy_host($x) {
    if ( is_string($x) ) {
        return preg_replace('#https?://(?:www\.)?ngs1\.blazr\.net#i', 'https://neogen.store', $x);
    }
    if ( is_array($x) ) {
        foreach ( $x as $i => $item ) {
            if ( is_object($item) && isset($item->url) ) {
                $x[$i]->url = preg_replace('#https?://(?:www\.)?ngs1\.blazr\.net#i', 'https://neogen.store', $item->url);
            } elseif ( is_string($item) ) {
                $x[$i] = ng_seo_rewrite_legacy_host($item);
            } elseif ( is_array($item) ) {
                $x[$i] = ng_seo_rewrite_legacy_host($item);
            }
        }
    }
    return $x;
}

/**
 * A. Rewrite stale ngs1.blazr.net host in every output surface.
 */
add_filter('the_content',           'ng_seo_rewrite_legacy_host', 1);
add_filter('widget_text_content',   'ng_seo_rewrite_legacy_host', 1);
add_filter('widget_text',           'ng_seo_rewrite_legacy_host', 1);
add_filter('wp_get_nav_menu_items', 'ng_seo_rewrite_legacy_host', 1);

add_filter('wp_nav_menu_objects', function ($items) {
    if ( ! is_array($items) ) return $items;
    foreach ( $items as $item ) {
        if ( ! empty($item->url) ) {
            $item->url = preg_replace(
                '#https?://(?:www\.)?ngs1\.blazr\.net#i',
                'https://neogen.store',
                $item->url
            );
        }
        if ( empty( trim( wp_strip_all_tags( (string) $item->title ) ) ) && ! empty( $item->url ) ) {
            $slug = trim( parse_url( $item->url, PHP_URL_PATH ) ?? '', '/' );
            $label = $slug !== '' ? ucwords( str_replace( ['-', '_'], ' ', $slug ) ) : 'Link';
            $item->classes[] = 'ng-empty-anchor-fixed';
            $item->attr_title = $label;
            // wp_nav_menu uses $item->aria_label if set
            if ( ! isset( $item->aria_label ) || $item->aria_label === '' ) {
                $item->aria_label = $label;
            }
        }
    }
    return $items;
}, 99);

/**
 * B. Force-correct Rank Math entity data — strip demo.local, drop
 * Person:admin and homepage Article nodes, rebrand stale names.
 */
add_filter('rank_math/json_ld', function ($data, $jsonld) {
    if ( ! is_array($data) || empty($data) ) return $data;
    $is_home = is_front_page() || is_home();

    foreach ( $data as $key => $node ) {
        if ( ! is_array($node) ) continue;

        if ( isset($node['url']) && stripos($node['url'], 'demo.local') !== false ) {
            unset($data[$key]);
            continue;
        }
        if ( isset($node['@type']) && $node['@type'] === 'Person'
            && isset($node['name']) && strtolower($node['name']) === 'admin' ) {
            unset($data[$key]);
            continue;
        }
        if ( $is_home && isset($node['@type'])
            && in_array($node['@type'], ['Article', 'BlogPosting', 'NewsArticle'], true) ) {
            unset($data[$key]);
            continue;
        }
        // Drop Rank Math's Organization on home — local Store node is canonical
        if ( $is_home && isset($node['@type']) && $node['@type'] === 'Organization' ) {
            unset($data[$key]);
            continue;
        }
        if ( isset($node['name']) && (
                stripos($node['name'], 'بلازر') !== false ||
                stripos($node['name'], 'blazr')  !== false
        ) ) {
            $data[$key]['name'] = 'NeoGen Store';
            $data[$key]['alternateName'] = 'نيوجين ستور';
        }
        if ( isset($node['sameAs']) && is_array($node['sameAs']) ) {
            $data[$key]['sameAs'] = array_values(array_filter(
                $node['sameAs'],
                function ($u) { return stripos((string) $u, 'demo.local') === false; }
            ));
            if ( empty($data[$key]['sameAs']) ) unset($data[$key]['sameAs']);
        }
    }

    return array_values( array_filter($data) );
}, 99, 2);

add_filter('rank_math/frontend/description', function ($d) {
    if ( is_front_page() || is_home() ) {
        return 'NeoGen Store — متجر تقني سعودي للشبكات، الهوم لاب، البيوت الذكية، والألعاب. شحن من داخل المملكة، ضمان 12 شهر، إرجاع 14 يوم.';
    }
    return $d;
}, 99);

add_filter('rank_math/frontend/canonical', function ($c) {
    if ( is_string($c) ) {
        return preg_replace('#https?://(?:www\.)?ngs1\.blazr\.net#i', 'https://neogen.store', $c);
    }
    return $c;
}, 99);

add_filter('rank_math/opengraph/facebook/site_name', function () { return 'NeoGen Store'; });
add_filter('rank_math/opengraph/facebook/og_locale', function () { return 'ar_SA'; });

/**
 * C. Author-display rewrite — global override of "admin".
 */
add_filter('the_author', function ($name) {
    return strtolower((string) $name) === 'admin' ? 'NeoGen Store' : $name;
}, 1);
add_filter('get_the_author_display_name', function ($name) {
    return strtolower((string) $name) === 'admin' ? 'NeoGen Store' : $name;
}, 1);
foreach ( ['user_nicename', 'first_name', 'nickname'] as $field ) {
    add_filter( "the_author_{$field}", function ($name) {
        return strtolower((string) $name) === 'admin' ? 'NeoGen Store' : $name;
    }, 1 );
}

/**
 * F. Rank Math sitemap output — rewrite cached legacy URLs at flight.
 */
add_filter('rank_math/sitemap/build_index', 'ng_seo_rewrite_legacy_host', 1);
add_filter('rank_math/sitemap/output',      'ng_seo_rewrite_legacy_host', 1);
add_filter('rank_math/sitemap/locations', function ($locs) {
    if ( is_array($locs) ) return array_map('ng_seo_rewrite_legacy_host', $locs);
    return ng_seo_rewrite_legacy_host($locs);
});
