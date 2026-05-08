<?php
/**
 * Plugin Name: NeoGen SEO
 * Description: Security headers, /llms.txt, robots.txt AI-crawler policy, and homepage meta-description override.
 * Version: 1.20.9
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

/**
 * Security headers — conservative set safe for a WooCommerce site.
 *
 * CSP runs in Report-Only mode by default so it can ride alongside
 * WooCommerce/Mada/Apple Pay/STC Pay/Tabby checkout without breaking
 * payment redirects. Define NG_CSP_ENFORCE in wp-config.php after a
 * clean reporting window to flip to enforcement.
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

        // Content-Security-Policy — broad allowances tuned for WooCommerce +
        // common payment gateways used by this store. Report-only first.
        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self' https://*.mada.com.sa https://*.checkout.com https://*.tabby.ai https://*.stcpay.com.sa https://*.paypal.com",
            "script-src 'self' 'unsafe-inline' https://*.googletagmanager.com https://*.google-analytics.com https://*.googleadservices.com https://*.googlesyndication.com https://*.doubleclick.net https://*.gstatic.com https://*.tabby.ai https://*.checkout.com https://*.stcpay.com.sa https://*.applepay.cdn-apple.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://*.gstatic.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://*.tabby.ai https://*.checkout.com https://*.stcpay.com.sa",
            "frame-src 'self' https://*.youtube.com https://*.youtube-nocookie.com https://*.tabby.ai https://*.checkout.com https://*.stcpay.com.sa https://*.applepay.cdn-apple.com",
            "media-src 'self' blob: https:",
            "upgrade-insecure-requests",
        ]);
        // Default: enforced. To revert to report-only without a code change,
        // add `define('NG_CSP_ENFORCE', false);` to wp-config.php.
        $csp_header = ( defined('NG_CSP_ENFORCE') && NG_CSP_ENFORCE === false )
            ? 'Content-Security-Policy-Report-Only: ' . $csp
            : 'Content-Security-Policy: ' . $csp;
        header($csp_header);
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
    // Strict shape check — only emit a real publisher line if the value
    // matches Google's pub-NNNNNNNNNNNNNNNN format. Anything else
    // means the option was tampered with or Site Kit changed shape.
    if ( ! preg_match('/^pub-\d+$/', $pub) ) {
        echo "# ads.txt — malformed AdSense publisher ID, refusing to serve.\n";
        echo "# Set ng_adsense_client_id option to a valid 'pub-NNNNNNNNNNNNNNNN' value.\n";
        exit;
    }

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
 * robots.txt — explicit ALLOW for citation crawlers.
 *
 * Cloudflare's Managed Content block (emitted at the top of /robots.txt)
 * already handles the disallow list for training crawlers (GPTBot,
 * ClaudeBot, CCBot, Google-Extended, Bytespider, Amazonbot,
 * Applebot-Extended, meta-externalagent). Our filter only needs to add
 * the explicit Allow lines for the citation/share crawlers Cloudflare
 * does NOT cover (ChatGPT-User, PerplexityBot, FacebookBot, anthropic-ai).
 */
add_filter('robots_txt', function ($output, $public) {
    if ( ! $public ) return $output; // respect "discourage search engines" setting

    $rules  = "\n# Citation / share crawlers — explicit allow (per neogen.store)\n";
    $rules .= "User-agent: ChatGPT-User\nAllow: /\n\n";
    $rules .= "User-agent: PerplexityBot\nAllow: /\n\n";
    $rules .= "User-agent: FacebookBot\nAllow: /\n\n";
    // anthropic-ai is not in Cloudflare's managed list yet — keep the
    // explicit disallow until it lands there.
    $rules .= "User-agent: anthropic-ai\nDisallow: /\n\n";
    return $output . $rules;
}, 10, 2);

/**
 * Homepage canonical description string. Single source of truth — used
 * by Rank Math via the existing rank_math/frontend/description filter
 * (line ~306 below) AND by our fallback emitter when Rank Math is off.
 *
 * 145 chars (AR is denser; ~145 visual chars matches the 120-155 latin target).
 */
function ng_home_description_ar() {
    return 'NeoGen Store — متجر تقني سعودي للشبكات، الهوم لاب، البيوت الذكية، والألعاب. شحن من داخل المملكة، ضمان 12 شهر، إرجاع 14 يوم.';
}

/**
 * Homepage <title> — fix the duplicate-brand bug. WP's document_title
 * combines page-title + site-name + tagline, so the homepage with
 * Rank Math active was emitting:
 *   "الرئيسية - NeoGen Store - NeoGen Store | جيل التقنية القادم"
 * Replace with a single canonical title on the front page.
 */
function ng_home_title_ar() {
    return 'NeoGen Store · متجر تقني سعودي للشبكات والهوم لاب والبيوت الذكية';
}

add_filter('document_title_parts', function ($parts) {
    if ( is_front_page() || is_home() ) {
        // WP joins these with " - "; collapsing to one part avoids dups.
        return [ 'title' => ng_home_title_ar() ];
    }
    return $parts;
}, 99);

add_filter('rank_math/frontend/title', function ($title) {
    if ( is_front_page() || is_home() ) {
        return ng_home_title_ar();
    }
    return $title;
}, 99);

/**
 * Homepage robots — strip 'nofollow' and 'noimageindex' that Rank Math
 * was emitting on the front page. Public storefront homepage must be
 * fully indexable.
 */
add_filter('rank_math/frontend/robots', function ($robots) {
    if ( ! ( is_front_page() || is_home() ) ) return $robots;
    return [
        'index'             => 'index',
        'follow'            => 'follow',
        'max-snippet'       => 'max-snippet:-1',
        'max-video-preview' => 'max-video-preview:-1',
        'max-image-preview' => 'max-image-preview:large',
    ];
}, 99);

/**
 * Belt-and-suspenders: surgically rewrite the homepage <meta name="robots">
 * tag at flight, in case Rank Math's filter pipeline reintroduces
 * `nofollow` / `noimageindex` after our filter runs (observed in live
 * audit 2026-04-27). Buffers wp_head output and rewrites once.
 */
add_action('wp_head', function () {
    if ( ! ( is_front_page() || is_home() ) ) return;
    ob_start();
}, 0);
add_action('wp_head', function () {
    if ( ! ( is_front_page() || is_home() ) ) return;
    $html = ob_get_clean();
    if ( ! is_string($html) || $html === '' ) { echo $html; return; }

    $clean_robots = '<meta name="robots" content="index, follow, max-snippet:-1, max-video-preview:-1, max-image-preview:large">';

    // Replace any existing robots meta(s); collapse to a single canonical line.
    $count = 0;
    $html = preg_replace_callback(
        '#<meta\s+name=["\']robots["\'][^>]*>#i',
        function () use ($clean_robots, &$count) {
            $count++;
            return $count === 1 ? $clean_robots : '';
        },
        $html
    );
    if ( $count === 0 ) {
        // Nothing emitted upstream — inject our canonical tag.
        $html = $clean_robots . "\n" . $html;
    }
    echo $html;
}, PHP_INT_MAX);

/**
 * Direct meta description / robots — emit ONLY when Rank Math is not
 * active (to avoid the duplicate-meta bug). Rank Math is canonical when
 * present.
 */
add_action('wp_head', function () {
    if ( ! ( is_front_page() || is_home() ) ) return;
    if ( class_exists('RankMath') ) return; // Rank Math will emit via filters above.

    echo "\n<!-- NeoGen SEO: canonical home description (no Rank Math) -->\n";
    echo '<meta name="description" content="' . esc_attr( ng_home_description_ar() ) . '">' . "\n";
    echo '<meta name="robots" content="index, follow, max-snippet:-1, max-video-preview:-1, max-image-preview:large">' . "\n";
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
        // Drop every Person node — this is a single-merchant storefront,
        // not an author-driven publication. The /author/admin/ Person that
        // Rank Math emits has no real profile page and only confuses E-E-A-T
        // signals. Removed unconditionally rather than name-matched.
        if ( isset($node['@type']) && $node['@type'] === 'Person' ) {
            unset($data[$key]);
            continue;
        }
        if ( isset($node['@id']) && stripos((string) $node['@id'], '/author/') !== false ) {
            unset($data[$key]);
            continue;
        }
        if ( $is_home && isset($node['@type'])
            && in_array($node['@type'], ['Article', 'BlogPosting', 'NewsArticle'], true) ) {
            unset($data[$key]);
            continue;
        }
        // Drop EVERY merchant-entity node Rank Math emits. The canonical
        // Store node is emitted from neogen-theme.php directly into wp_head
        // (outside Rank Math's filter pipeline), so it is unaffected here.
        // Rank Math's copy uses the slogan ("جيل التقنية القادم") as the
        // entity name and the same `#organization` @id, which produced a
        // duplicate-graph + name-conflict warning in the 2026-04-27 audit.
        // Also drop WebSite + WebPage — neogen-theme.php emits those with
        // proper inLanguage:[ar-SA, en], which Rank Math omits.
        $drop_types = ['Organization', 'ElectronicsStore', 'Store', 'LocalBusiness', 'OnlineStore', 'WebSite', 'WebPage'];
        if ( isset($node['@type']) ) {
            $node_types = is_array($node['@type']) ? $node['@type'] : [ $node['@type'] ];
            foreach ( $node_types as $t ) {
                if ( in_array($t, $drop_types, true) ) {
                    unset($data[$key]);
                    continue 2;
                }
            }
        }
        if ( isset($node['name']) && (
                stripos($node['name'], 'بلازر') !== false ||
                stripos($node['name'], 'blazr')  !== false ||
                stripos($node['name'], 'جيل التقنية') !== false
        ) ) {
            $data[$key]['name'] = 'NeoGen Store';
            $data[$key]['alternateName'] = 'نيوجين ستور';
        }
        // ImageObject / Organization logo — force absolute URL.
        foreach (['image', 'logo', 'thumbnailUrl'] as $img_key) {
            if ( isset($node[$img_key]) ) {
                if ( is_string($node[$img_key]) && $node[$img_key] !== '' && $node[$img_key][0] === '/' ) {
                    $data[$key][$img_key] = home_url( $node[$img_key] );
                } elseif ( is_array($node[$img_key]) && isset($node[$img_key]['url'])
                    && is_string($node[$img_key]['url']) && $node[$img_key]['url'] !== ''
                    && $node[$img_key]['url'][0] === '/' ) {
                    $data[$key][$img_key]['url'] = home_url( $node[$img_key]['url'] );
                }
            }
        }
        // slogan should never duplicate the brand name
        if ( isset($node['slogan']) && isset($node['name'])
            && trim( (string) $node['slogan'] ) === trim( (string) $node['name'] ) ) {
            unset( $data[$key]['slogan'] );
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
        return ng_home_description_ar();
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
 * Strip Rank Math's "Written by / Reading time" Twitter card meta tags
 * on the homepage. They are article-style metadata that reads as
 * "Written by NeoGen Store · 2 minutes" on a storefront homepage —
 * misleading. Tags are emitted via Rank Math's twitter_card output, not
 * wp_head directly, so we filter the buffered head to remove them.
 *
 * Surgical: only on front_page / home, only those four meta lines.
 */
add_action('wp_head', function () {
    if ( ! ( is_front_page() || is_home() ) ) return;
    ob_start();
}, 1);
add_action('wp_head', function () {
    if ( ! ( is_front_page() || is_home() ) ) return;
    $html = ob_get_clean();
    if ( ! is_string($html) || $html === '' ) { echo $html; return; }
    $html = preg_replace(
        '#\s*<meta\s+name=["\']twitter:(?:label|data)[12]["\'][^>]*>#i',
        '',
        $html
    );
    echo $html;
}, PHP_INT_MAX - 1);

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

/* =====================================================================
 * v1.19.0 — Open Graph + Twitter Card image emission
 * Static OG image lives at neogen-theme-assets/img/social/.
 * If Rank Math is active, feed our path through its og_image filter so
 * we don't emit duplicate <meta> tags. Otherwise emit directly.
 * ===================================================================== */

function ng_og_image_url() {
    $base   = content_url('mu-plugins/neogen-custom/neogen-theme-assets/img/social');
    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $is_ar  = strpos((string) $locale, 'ar') === 0;
    return $base . ($is_ar ? '/og-default-ar.png' : '/og-default-en.png');
}

function ng_twitter_image_url() {
    $base   = content_url('mu-plugins/neogen-custom/neogen-theme-assets/img/social');
    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $is_ar  = strpos((string) $locale, 'ar') === 0;
    return $base . ($is_ar ? '/twitter-card-ar.png' : '/twitter-card-en.png');
}

// Rank Math integration — replace, don't duplicate
add_filter('rank_math/opengraph/facebook/og_image',          'ng_og_image_url', 99);
add_filter('rank_math/opengraph/facebook/og_image_secure_url','ng_og_image_url', 99);
add_filter('rank_math/opengraph/twitter/twitter_image',      'ng_twitter_image_url', 99);
add_filter('rank_math/opengraph/facebook/og_image_width',    function () { return 1200; }, 99);
add_filter('rank_math/opengraph/facebook/og_image_height',   function () { return 630;  }, 99);

// Direct emission only when Rank Math is NOT around
add_action('wp_head', function () {
    if ( class_exists('RankMath') ) return;

    $og  = esc_url( ng_og_image_url() );
    $tw  = esc_url( ng_twitter_image_url() );
    $url = esc_url( is_singular() ? get_permalink() : home_url('/') );

    echo "\n<!-- NeoGen OG/Twitter -->\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:site_name" content="NEOGEN STORE">' . "\n";
    echo '<meta property="og:locale" content="ar_SA">' . "\n";
    echo '<meta property="og:locale:alternate" content="en_US">' . "\n";
    echo '<meta property="og:url" content="' . $url . '">' . "\n";
    echo '<meta property="og:image" content="' . $og . '">' . "\n";
    echo '<meta property="og:image:width" content="1200">' . "\n";
    echo '<meta property="og:image:height" content="630">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:image" content="' . $tw . '">' . "\n";
}, 5);
