<?php
/**
 * Plugin Name: NeoGen Theme
 * Description: Sitewide visual skin for neogen.store. Tokens + logo system follow Brand Kit v1.1; layout follows Homepage Preview v1. Includes header/footer, front-page template, and Woo archive/single overrides.
 * Version: 1.1.5
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

if (!defined('NEOGEN_THEME_VERSION')) {
    define('NEOGEN_THEME_VERSION', '1.1.5');
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
    echo '<meta name="theme-color" content="#050505">' . "\n";
}, 2);

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
    if ($slug === 'content' && $name === 'product') {
        $override = NG_THEME_ASSET_DIR . '/templates/woocommerce/content-product.php';
        if (file_exists($override)) {
            return $override;
        }
    }
    return $template;
}, 10, 3);

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

    $home = home_url('/');
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : $home;
    $cart = function_exists('wc_get_cart_url') ? wc_get_cart_url() : $home;
    $acct = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : $home;

    $cart_count = 0;
    if (function_exists('WC') && WC() && WC()->cart) {
        $cart_count = (int) WC()->cart->get_cart_contents_count();
    }

    // Top 5 live product categories for the nav.
    $cats = [];
    if (taxonomy_exists('product_cat')) {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => 0,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 5,
        ]);
        if (!is_wp_error($terms)) { $cats = $terms; }
    }

    // Queue seed — a plausible in-range number; nudged by JS client-side.
    $queue_seed = 14;
    ?>
<div class="ng-sysbar" aria-label="System status">
  <span class="led" aria-hidden="true"></span>
  <span>SYS <b id="ng-clock">00:00:00</b> UTC</span>
  <span class="sep"></span>
  <span>STOCK SYNC <b class="cyan">LIVE</b></span>
  <span class="sep hide-sm"></span>
  <span class="hide-sm">QUEUE <b id="ng-queue"><?php echo esc_html( $queue_seed ); ?></b> ORDERS</span>
  <span class="sep hide-sm"></span>
  <span class="hide-sm">SHIP 2-5D / RIYADH · JEDDAH · DAMMAM</span>
  <span class="spacer"></span>
  <span>VAT <b>15%</b> INCLUDED</span>
  <span class="sep hide-sm"></span>
  <span class="hide-sm">AR · EN</span>
</div>

<nav class="ng-topnav" aria-label="Primary">
  <a class="ng-lockup" href="<?php echo esc_url( $home ); ?>" aria-label="NeoGen Store home">
    <span class="mono">N<span class="g">G</span></span>
    <span class="sep"></span>
    <span class="wordmark"><span class="neo">NEO</span><span class="gen">GEN</span></span>
  </a>
  <div class="ng-nav-cats">
    <?php foreach ( $cats as $term ) :
        $link = get_term_link( $term );
        if ( is_wp_error( $link ) ) { continue; }
    ?>
      <a href="<?php echo esc_url( $link ); ?>"><span class="dot"></span><?php echo esc_html( $term->name ); ?></a>
    <?php endforeach; ?>
  </div>
  <div class="ng-nav-tools">
    <a class="ng-nav-tool" href="<?php echo esc_url( $shop ); ?>" aria-label="Search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <span class="tool-label">Search</span>
    </a>
    <a class="ng-nav-tool" href="<?php echo esc_url( $acct ); ?>" aria-label="Account">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.5-6 8-6s7 2 8 6"/></svg>
      <span class="tool-label">Account</span>
    </a>
    <a class="ng-nav-tool" href="<?php echo esc_url( $cart ); ?>" aria-label="Cart">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 5h3l2 12h10l2-8H7"/><circle cx="10" cy="20" r="1.2"/><circle cx="17" cy="20" r="1.2"/></svg>
      <span class="tool-label">Cart</span>
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
    $fragments['.ng-nav-tools a[aria-label="Cart"] .count'] =
        '<span class="count' . ($count > 0 ? '' : ' is-empty') . '">' . esc_html($count) . '</span>';
    return $fragments;
});

/**
 * Sitewide footer injected on wp_footer. Blocksy's original is hidden by CSS.
 */
add_action('wp_footer', function () {
    if (is_admin()) { return; }
    $home = home_url('/');
    $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : $home;
    $cats = [];
    if (taxonomy_exists('product_cat')) {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => 0,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => 6,
        ]);
        if (!is_wp_error($terms)) { $cats = $terms; }
    }
    $year = date_i18n('Y');
    ?>
<footer class="ng-footer">
  <div class="ng-foot-inner">
    <div class="ng-foot-col ng-foot-brand">
      <a class="ng-lockup" href="<?php echo esc_url( $home ); ?>" style="margin-bottom:4px;">
        <span class="mono" style="font-size:24px;">N<span class="g">G</span></span>
        <span class="sep" style="height:20px;"></span>
        <span class="wordmark" style="font-size:24px;"><span class="neo">NEO</span><span class="gen">GEN</span></span>
      </a>
      <p>متجر تقني سعودي. نختار المنتجات بعناية، نوضح المواصفات بدون مبالغة، ونبني تجربة شراء تناسب المستخدم التقني الذي يعرف ما يحتاجه.</p>
    </div>
    <div class="ng-foot-col">
      <h4>// CATALOG</h4>
      <ul>
        <?php if ( !empty( $cats ) ) :
            foreach ( $cats as $term ) :
                $link = get_term_link( $term );
                if ( is_wp_error( $link ) ) { continue; }
        ?>
          <li><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $term->name ); ?></a></li>
        <?php   endforeach;
        else : ?>
          <li><a href="<?php echo esc_url( $shop ); ?>">Browse shop</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <div class="ng-foot-col">
      <h4>// SUPPORT</h4>
      <ul>
        <li><a href="<?php echo esc_url( home_url( '/my-account/orders/' ) ); ?>">Order tracking</a></li>
        <li><a href="<?php echo esc_url( home_url( '/returns/' ) ); ?>">Returns · 14 days</a></li>
        <li><a href="<?php echo esc_url( home_url( '/warranty/' ) ); ?>">Warranty · 12 months</a></li>
        <li><a href="<?php echo esc_url( home_url( '/shipping/' ) ); ?>">Shipping · 2-5D</a></li>
        <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact · WhatsApp</a></li>
      </ul>
    </div>
    <div class="ng-foot-col">
      <h4>// STORE</h4>
      <ul>
        <li>CR · 7053130576</li>
        <li>VAT · 15% INCLUDED</li>
        <li>RIYADH · JEDDAH · DAMMAM</li>
        <li>MADA · APPLE PAY · STC · TABBY</li>
        <li>AR · EN</li>
      </ul>
    </div>
  </div>
  <div class="ng-foot-bottom">
    <span>© <?php echo esc_html( $year ); ?> <b>NEOGEN STORE</b> · ALL RIGHTS RESERVED</span>
    <span>BRAND KIT v1.1 · APPLIED</span>
    <span>NEOGEN.STORE</span>
  </div>
</footer>
    <?php
}, 5);
