<?php
/**
 * Plugin Name: NeoGen SEO
 * Description: Security headers, /llms.txt, robots.txt AI-crawler policy, and homepage meta-description override.
 * Version: 1.10.2
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
