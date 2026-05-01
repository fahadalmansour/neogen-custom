<?php
/**
 * NeoGen Theme — front-page template.
 *
 * Rendered when the site front page is requested. Injected via the
 * `template_include` filter in mu-plugins/neogen-theme.php, which sets
 * NG_RENDER_FRONT_PAGE before returning this path.
 *
 * Guard: if this file is accidentally required by the mu-plugin loader
 * at boot (before WP template stack is ready), the constant will not be
 * defined and we bail out cleanly.
 */

defined('ABSPATH') || exit;
if (!defined('NG_RENDER_FRONT_PAGE')) return;
if (!function_exists('get_header')) return;

get_header();

// WhatsApp URL — derive from CR phone if NG_WHATSAPP_URL is not defined.
// Strip non-digits, drop a leading +, build wa.me/<digits>.
$whatsapp_url = defined('NG_WHATSAPP_URL') ? NG_WHATSAPP_URL : '';
if ($whatsapp_url === '' && function_exists('ng_cr')) {
    $cr_data = ng_cr();
    $tel     = isset($cr_data['phone_mobile']) ? $cr_data['phone_mobile'] : '';
    $digits  = preg_replace('/\D/', '', (string) $tel);
    if ($digits !== '') {
        $whatsapp_url = 'https://wa.me/' . $digits;
    }
}
$has_whatsapp = $whatsapp_url !== '' && $whatsapp_url !== '#';
$contact_url  = function_exists('wc_get_page_permalink') ? home_url('/contact/') : home_url('/');
$shop_url     = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');

// Catalog counts (for the hero systems-brief aside).
// wp_count_posts() returns a stdClass without a `publish` property on
// fresh installs (no products yet) or when the `product` CPT isn't
// registered, so guard before reading it.
$product_counts     = function_exists('wc_get_page_permalink') ? wp_count_posts('product') : null;
$published_products = ( is_object($product_counts) && isset($product_counts->publish) ) ? (int) $product_counts->publish : 0;

// Top 6 product categories for the photo-led mosaic. Bypasses the
// neogen_top_cats_exclude_slugs filter (which hides homelab from
// top-cat lists by default) so the rack matches the front-page
// $copy_map keys exactly: smart-home, gaming, homelab, networking,
// hardware, gift-cards.
$top_categories = array();
if ( taxonomy_exists( 'product_cat' ) ) {
    $rack_terms = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'parent'     => 0,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => 7,
    ) );
    if ( ! is_wp_error( $rack_terms ) ) {
        $rack_terms = array_values( array_filter( $rack_terms, function ( $t ) {
            return $t->slug !== 'uncategorized';
        } ) );
        $top_categories = array_slice( $rack_terms, 0, 6 );
    }
}

// Operator Picks — diversified across distinct top-level categories so
// gift-cards don't monopolize the row. Strategy:
//   1. Featured products grouped by primary category — keep 1 per cat.
//   2. If still <4, fill from latest products in OTHER categories than
//      what's already represented.
//   3. As a last resort, drop the diversity rule and pad with latest.
$picks         = [];
$picks_cats    = [];   // primary category slugs we've already used
$picks_added   = [];   // product IDs added (dedup)

// Primary-cat resolver — Rank Math → Yoast → first term (alphabetical).
// Defined in mu-plugins/neogen-theme.php as ng_primary_product_cat_slug().
$primary_cat_slug = function ($product) {
    if (!$product instanceof WC_Product) return '';
    if (function_exists('ng_primary_product_cat_slug')) {
        return ng_primary_product_cat_slug($product);
    }
    $terms = get_the_terms($product->get_id(), 'product_cat');
    if (is_wp_error($terms) || empty($terms)) return '';
    return (string) $terms[0]->slug;
};

if (function_exists('wc_get_products')) {
    $featured_ids = function_exists('wc_get_featured_product_ids') ? wc_get_featured_product_ids() : [];

    // Pass 1 — featured, one per primary cat
    if (!empty($featured_ids)) {
        $featured = wc_get_products([
            'status'  => 'publish',
            'include' => array_slice($featured_ids, 0, 24),
            'limit'   => 24,
        ]);
        foreach ((array) $featured as $p) {
            if (count($picks) >= 12) break;
            $slug = $primary_cat_slug($p);
            if ($slug !== '' && in_array($slug, $picks_cats, true)) continue;
            $picks[] = $p;
            $picks_cats[]  = $slug;
            $picks_added[] = $p->get_id();
        }
    }

    // Pass 2 — fill from latest in NEW categories
    if (count($picks) < 12) {
        $fill = wc_get_products([
            'status'  => 'publish',
            'limit'   => 24,
            'orderby' => 'date',
            'order'   => 'DESC',
            'exclude' => $picks_added,
        ]);
        foreach ((array) $fill as $p) {
            if (count($picks) >= 12) break;
            $slug = $primary_cat_slug($p);
            if ($slug !== '' && in_array($slug, $picks_cats, true)) continue;
            $picks[] = $p;
            $picks_cats[]  = $slug;
            $picks_added[] = $p->get_id();
        }
    }

    // Pass 3 — last resort, drop diversity rule and pad to 12
    if (count($picks) < 12) {
        $pad = wc_get_products([
            'status'  => 'publish',
            'limit'   => max(0, 12 - count($picks)),
            'orderby' => 'date',
            'order'   => 'DESC',
            'exclude' => $picks_added,
        ]);
        foreach ((array) $pad as $p) {
            if (count($picks) >= 12) break;
            $picks[] = $p;
            $picks_added[] = $p->get_id();
        }
    }
}
// Horizontal scroll deck — render up to 12 picks; native overflow-scroll
// lets users swipe / drag, paired with discoverable arrow buttons. Below
// the inner-break thresholds (count($picks) >= 4) we'd cap fetching too
// early; bumped to 12 in those branches above as well (v1.25.0).
$picks = array_slice($picks, 0, 12);

// Ticker — latest 7 with SKUs.
$ticker_products = [];
if (function_exists('wc_get_products')) {
    $ticker_products = wc_get_products([
        'status'  => 'publish',
        'limit'   => 7,
        'orderby' => 'date',
        'order'   => 'DESC',
    ]);
    $ticker_products = is_array($ticker_products) ? $ticker_products : [];
}

// Category icons, keyed by slug. Filterable so content ops can extend.
$category_icons = apply_filters('neogen_theme_category_icons', [
    'networking' => '<svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="3"/><path d="M16 13V5M16 19v8M13 16H5M19 16h8M10 10l-4-4M22 22l4 4M10 22l-4 4M22 10l4-4"/></svg>',
    'network'    => '<svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="3"/><path d="M16 13V5M16 19v8M13 16H5M19 16h8M10 10l-4-4M22 22l4 4M10 22l-4 4M22 10l4-4"/></svg>',
    'homelab'    => '<svg viewBox="0 0 32 32"><rect x="5" y="6" width="22" height="6" rx="1"/><rect x="5" y="14" width="22" height="6" rx="1"/><rect x="5" y="22" width="22" height="4" rx="1"/><circle cx="9" cy="9" r="0.8" fill="currentColor"/><circle cx="9" cy="17" r="0.8" fill="currentColor"/></svg>',
    'storage'    => '<svg viewBox="0 0 32 32"><rect x="5" y="6" width="22" height="6" rx="1"/><rect x="5" y="14" width="22" height="6" rx="1"/><rect x="5" y="22" width="22" height="4" rx="1"/><circle cx="9" cy="9" r="0.8" fill="currentColor"/><circle cx="9" cy="17" r="0.8" fill="currentColor"/></svg>',
    'smart-home' => '<svg viewBox="0 0 32 32"><path d="M4 15 16 5l12 10M7 13v14h18V13"/><path d="M13 27v-7h6v7"/></svg>',
    'smart'      => '<svg viewBox="0 0 32 32"><path d="M4 15 16 5l12 10M7 13v14h18V13"/><path d="M13 27v-7h6v7"/></svg>',
    'gaming'     => '<svg viewBox="0 0 32 32"><rect x="3" y="10" width="26" height="14" rx="7"/><path d="M10 17h4M12 15v4"/><circle cx="22" cy="17" r="1.2" fill="currentColor"/><circle cx="20" cy="19" r="1.2" fill="currentColor"/></svg>',
    'services'   => '<svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="10"/><path d="M16 10v6l4 2"/></svg>',
    'service'    => '<svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="10"/><path d="M16 10v6l4 2"/></svg>',
]);
$fallback_icon = '<svg viewBox="0 0 32 32"><path d="M16 3 29 10v12l-13 7-13-7V10z"/></svg>';

// LED patterns used on each rack row (cycled).
$led_patterns = [
    '<span class="l on"></span><span class="l cyan"></span><span class="l"></span>',
    '<span class="l on"></span><span class="l"></span><span class="l"></span>',
    '<span class="l on"></span><span class="l"></span><span class="l warn"></span>',
    '<span class="l on"></span><span class="l cyan"></span><span class="l"></span>',
    '<span class="l cyan"></span><span class="l"></span><span class="l"></span>',
];

// Rack labels (A-E, F, G...).
$rack_letter = function ($i) {
    return chr(65 + ($i % 26));
};

// ============================================================
// Reusable product-card + product-deck renderers (v1.27.0)
// ============================================================
// Picks, New Arrivals, Deals, and Gift Cards all share the same
// .ng-product / .ng-product-grid--deck markup. Card-rendering logic
// (sku/title/stock-badge/image/specs/price/CTA) lives in this closure
// once. The deck renderer wraps a list of products in the section +
// arrows + scroll container.

$ng_render_product_card = function ($product) {
    if (!$product instanceof WC_Product) { return; }
    $id      = $product->get_id();
    $sku     = $product->get_sku();
    if (!$sku) { $sku = 'NG-' . $id; }
    $name_en = $product->get_name();
    $name_ar = get_post_meta($id, '_ng_ar_title', true);
    if (!$name_ar) { $name_ar = function_exists('ng_ar_label') ? ng_ar_label($name_en) : $name_en; }
    if (function_exists('ng_gift_card_clean_product_name')) {
        $name_en = ng_gift_card_clean_product_name($name_en);
        $name_ar = ng_gift_card_clean_product_name($name_ar);
    }
    $perm    = get_permalink($id);

    // Stock badge.
    $stock_qty  = $product->get_stock_quantity();
    $tag_class  = '';
    $tag_label  = '';
    $created_ts = get_post_time('U', true, $id);
    $is_new     = $created_ts && (time() - $created_ts) < 30 * DAY_IN_SECONDS;
    if (is_numeric($stock_qty) && $stock_qty !== null && (int) $stock_qty > 0 && (int) $stock_qty < 5) {
        $tag_class = 'hot';
        $tag_label = 'مخزون منخفض · ' . (int) $stock_qty;
    } elseif ($is_new) {
        $tag_class = 'new';
        $tag_label = 'جديد';
    } elseif (is_numeric($stock_qty) && (int) $stock_qty >= 5) {
        $tag_class = '';
        $tag_label = 'متوفّر · ' . (int) $stock_qty;
    } elseif ($product->is_in_stock()) {
        $tag_class = '';
        $tag_label = 'متوفّر';
    } else {
        $tag_class = 'hot';
        $tag_label = 'نفد';
    }

    // Image.
    $img_id       = $product->get_image_id();
    $img          = $img_id ? wp_get_attachment_image($img_id, 'large', false, ['class' => 'ng-product-img', 'alt' => esc_attr($name_en)]) : '';
    $has_gift_img = false;
    if (function_exists('ng_gift_card_image_html')) {
        $gift_img = ng_gift_card_image_html($product, 'large', $name_en, null, ['class' => 'ng-product-img']);
        if ($gift_img) { $img = $gift_img; $has_gift_img = true; }
    }
    $gallery_ids  = $product->get_gallery_image_ids();
    $img_alt_html = '';
    if (!empty($gallery_ids) && !$has_gift_img) {
        $img_alt_html = wp_get_attachment_image((int) $gallery_ids[0], 'large', false, [
            'class' => 'ng-product-img-alt', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async',
        ]);
    }

    // Specs (≤4 attributes/tags).
    $specs = [];
    foreach ($product->get_attributes() as $attr) {
        if (!$attr instanceof WC_Product_Attribute) { continue; }
        $vals = $attr->is_taxonomy()
            ? wp_get_post_terms($id, $attr->get_name(), ['fields' => 'names'])
            : $attr->get_options();
        if (!empty($vals) && !is_wp_error($vals)) {
            $specs[] = is_array($vals) ? reset($vals) : $vals;
        }
        if (count($specs) >= 4) { break; }
    }
    if (count($specs) < 4) {
        $tag_terms = wp_get_post_terms($id, 'product_tag', ['fields' => 'names']);
        if (!is_wp_error($tag_terms)) {
            foreach ($tag_terms as $t) { $specs[] = $t; if (count($specs) >= 4) { break; } }
        }
    }

    // Price.
    $price_raw  = $product->get_price();
    $price_html = $product->get_price_html();
    $cta_url    = $product->is_type('simple') && $product->is_in_stock()
        ? esc_url($product->add_to_cart_url())
        : esc_url($perm);
    $cta_label  = $product->is_type('simple') && $product->is_in_stock() ? 'أضف للسلة' : 'عرض';
    ?>
    <article class="ng-product reveal">
      <div class="ng-product-head">
        <span class="sku"><?php echo esc_html(strtoupper($sku)); ?></span>
        <?php if ($tag_label) : ?>
          <span class="tag <?php echo esc_attr($tag_class); ?>"><?php echo esc_html($tag_label); ?></span>
        <?php endif; ?>
      </div>
      <a class="ng-product-media<?php echo $img_alt_html ? ' has-alt' : ''; ?>" href="<?php echo esc_url($perm); ?>" aria-label="<?php echo esc_attr($name_en); ?>">
        <?php if ($img_alt_html) { echo $img_alt_html; } ?>
        <?php if ($img) :
            echo $img;
        else : ?>
          <svg class="placeholder" viewBox="0 0 200 120" fill="none" stroke="currentColor" stroke-width="1.4">
            <rect x="30" y="20" width="140" height="80" rx="6"/>
            <circle cx="100" cy="60" r="18"/>
            <path d="M100 46v28M86 60h28"/>
          </svg>
        <?php endif; ?>
      </a>
      <div class="ng-product-title"><div class="ar"><?php echo esc_html($name_ar); ?></div></div>
      <?php if (!empty($specs)) : ?>
      <div class="ng-product-specs">
        <?php foreach ($specs as $s) : ?><span class="s"><?php echo esc_html($s); ?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="ng-product-foot">
        <div class="ng-product-price">
          <?php if (is_numeric($price_raw)) : ?>
            <div class="amount"><?php echo esc_html(number_format_i18n((float) $price_raw, 0)); ?> <small>SAR</small></div>
          <?php else : ?>
            <div class="amount"><?php echo wp_kses_post($price_html); ?></div>
          <?php endif; ?>
          <div class="inc">شامل الضريبة · شحن 2-5 أيام</div>
        </div>
        <a class="ng-product-cta" href="<?php echo esc_url($cta_url); ?>"<?php echo $product->is_type('simple') && $product->is_in_stock() ? ' data-product_id="' . esc_attr($id) . '"' : ''; ?>>
          <?php echo esc_html($cta_label); ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5v14"/></svg>
        </a>
      </div>
    </article>
    <?php
};

$ng_render_product_deck = function ($products, $args) use ($ng_render_product_card) {
    if (empty($products)) { return; }
    $a = wp_parse_args($args, [
        'id'         => '',
        'band_class' => '',
        'label_html' => '',
        'h2_html'    => '',
        'subhead'    => '',
        'note'       => '',
    ]);
    ?>
    <section class="ng-section <?php echo esc_attr($a['band_class']); ?>"<?php if ($a['id']) echo ' id="' . esc_attr($a['id']) . '"'; ?>>
      <div class="ng-container">
        <div class="ng-section-head reveal">
          <div>
            <?php if ($a['label_html']) : ?><div class="ng-section-label"><?php echo wp_kses_post($a['label_html']); ?></div><?php endif; ?>
            <?php if ($a['h2_html'])    : ?><h2 class="ng-section-h"><?php echo wp_kses_post($a['h2_html']); ?></h2><?php endif; ?>
            <?php if ($a['subhead'])    : ?><div class="ng-section-ar"><?php echo esc_html($a['subhead']); ?></div><?php endif; ?>
          </div>
          <?php if ($a['note']) : ?><p class="ng-section-note"><?php echo esc_html($a['note']); ?></p><?php endif; ?>
        </div>
        <div class="ng-deck-wrap">
          <button class="ng-deck-arrow ng-deck-arrow--prev" type="button" aria-label="السابق" data-direction="prev" data-disabled="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <button class="ng-deck-arrow ng-deck-arrow--next" type="button" aria-label="التالي" data-direction="next">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
          </button>
          <div class="ng-product-grid ng-product-grid--deck">
            <?php foreach ($products as $p) { $ng_render_product_card($p); } ?>
          </div>
        </div>
      </div>
    </section>
    <?php
};

// Fetch product lists for the new e-commerce sections.
$ng_new_arrivals = function_exists('wc_get_products') ? wc_get_products([
    'status'  => 'publish', 'limit' => 12,
    'orderby' => 'date',    'order' => 'DESC',
]) : [];

$ng_deals = [];
if (function_exists('wc_get_products')) {
    $ng_deals = wc_get_products([
        'status'     => 'publish', 'limit' => 12,
        'meta_query' => [[
            'key'     => '_sale_price', 'value' => 0,
            'compare' => '>', 'type' => 'NUMERIC',
        ]],
        'orderby'    => 'date', 'order' => 'DESC',
    ]);
}

$ng_gift_cards = function_exists('wc_get_products') ? wc_get_products([
    'status'   => 'publish', 'limit' => 12,
    'category' => ['gift-cards'],
    'orderby'  => 'date', 'order' => 'DESC',
]) : [];
?>

<!-- ============================================================
     HERO
     ============================================================ -->
<header class="ng-hero">
  <?php
      // Use ONLY the explicitly-configured hero image. Auto-falling back to
      // a category thumbnail or first product image used to surface a
      // random gift-card visual on the homepage, which made the store
      // read like an Amazon reseller. If unset, the hero is brand-only
      // (NG mark + wordmark + scan/backdrop SVGs) — that's the intended
      // first signal.
      $ng_hero_id = (int) get_option('ng_hero_image_id');
  ?>
  <?php if ( $ng_hero_id ) : ?>
    <div class="ng-hero-photo" aria-hidden="true">
      <?php echo wp_get_attachment_image( $ng_hero_id, 'full', false, [
          'loading'       => 'eager',
          'fetchpriority' => 'high',
          'decoding'      => 'async',
      ] ); ?>
    </div>
  <?php endif; ?>
  <div class="ng-hero-backdrop" aria-hidden="true">
    <svg viewBox="-50 -50 100 100">
      <circle r="48"></circle>
      <circle r="40"></circle>
      <circle r="32"></circle>
      <path d="M0 -44 L9 -26 L35 -35 L26 -9 L44 0 L26 9 L35 35 L9 26 L0 44 L-9 26 L-35 35 L-26 9 L-44 0 L-26 -9 L-35 -35 L-9 -26 Z"></path>
    </svg>
  </div>
  <div class="ng-hero-scan" aria-hidden="true"></div>

  <?php
  // v1.38.0 — Redesign Phase 1: hero rotating product gallery.
  // Source: /tmp/neogen-design/neogen-store/project/homepage.jsx (lines 4–172).
  // Pulls up to 5 products from the picks pool calculated above so the
  // hero showcases real, in-stock, diversified inventory. Categories are
  // surfaced as small chips on each slide.
  $ng_hero_products = [];
  if ( ! empty( $picks ) ) {
      foreach ( $picks as $hp ) {
          if ( count( $ng_hero_products ) >= 5 ) { break; }
          if ( ! $hp instanceof WC_Product ) { continue; }
          $hp_id      = $hp->get_id();
          $hp_sku     = $hp->get_sku();
          if ( ! $hp_sku ) { $hp_sku = 'NG-' . $hp_id; }
          $hp_name_en = $hp->get_name();
          $hp_name_ar = (string) get_post_meta( $hp_id, '_ng_ar_title', true );
          if ( $hp_name_ar === '' ) {
              $hp_name_ar = function_exists( 'ng_ar_label' ) ? ng_ar_label( $hp_name_en ) : $hp_name_en;
          }
          if ( function_exists( 'ng_gift_card_clean_product_name' ) ) {
              $hp_name_en = ng_gift_card_clean_product_name( $hp_name_en );
              $hp_name_ar = ng_gift_card_clean_product_name( $hp_name_ar );
          }
          $hp_cat_name = '';
          $hp_cats = get_the_terms( $hp_id, 'product_cat' );
          if ( ! empty( $hp_cats ) && ! is_wp_error( $hp_cats ) ) {
              $hp_cat_name = (string) get_term_meta( $hp_cats[0]->term_id, '_ng_ar_label', true );
              if ( $hp_cat_name === '' ) { $hp_cat_name = $hp_cats[0]->name; }
          }
          $hp_img_id = (int) $hp->get_image_id();
          $hp_img    = $hp_img_id
              ? wp_get_attachment_image( $hp_img_id, 'large', false, [ 'alt' => esc_attr( $hp_name_en ), 'loading' => 'eager', 'decoding' => 'async' ] )
              : '';
          if ( function_exists( 'ng_gift_card_image_html' ) ) {
              $g = ng_gift_card_image_html( $hp, 'large', $hp_name_en, null, [ 'loading' => 'eager' ] );
              if ( $g ) { $hp_img = $g; }
          }
          $ng_hero_products[] = [
              'id'        => $hp_id,
              'sku'       => $hp_sku,
              'ar'        => $hp_name_ar,
              'en'        => $hp_name_en,
              'cat'       => $hp_cat_name,
              'price'     => $hp->get_price_html(),
              'perm'      => get_permalink( $hp_id ),
              'img'       => $hp_img,
              'cta_url'   => $hp->is_type( 'simple' ) && $hp->is_in_stock() ? $hp->add_to_cart_url() : get_permalink( $hp_id ),
              'cta_label' => $hp->is_type( 'simple' ) && $hp->is_in_stock() ? 'أضف للسلة' : 'عرض',
          ];
      }
  }
  $ng_hero_total = count( $ng_hero_products );
  ?>

  <div class="ng-hero-inner">
    <div class="ng-hero-grid">
      <div class="ng-hero-main">
        <div class="ng-kicker">
          <span></span>
          <?php echo esc_html__( 'متجر تقني سعودي · معتمد · 2026', 'neogen' ); ?>
        </div>
        <h1 class="ng-hero-h1">جيل التقنية<br><span class="accent">&#160;القادم</span>.</h1>

        <div class="ng-hero-wordmark" aria-hidden="true">
          <img class="ng-lockup-mark" src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/logo/ng-mark.png' ); ?>" alt="" width="80" height="62" decoding="async">
          <span class="sep"></span>
          <span class="wordmark"><span class="neo">NEO</span><span class="gen">GEN</span></span>
          <span class="store">STORE</span>
        </div>

        <p class="ng-hero-copy">
          وحدات مختارة لمحترفي الشبكات، الهوم لاب، البيوت الذكية، والألعاب.
          مواصفات بدون مبالغة. شحن من المملكة لكل دول الخليج.
        </p>

        <div class="ng-hero-sub">// تقنية متخصصة · مهيّأة للمشغّلين · شحن من المملكة //</div>

        <div class="ng-hero-ctas">
          <a class="btn btn-primary" href="<?php echo esc_url( $shop_url ); ?>">
            تصفح المتجر
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </a>
          <a class="btn btn-ghost" href="#ng-service">
            <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/icons/build-rig.svg' ); ?>" width="20" height="20" alt="" class="ng-icon-mono">
            ابنِ جهازك
          </a>
        </div>

        <div style="display:flex;gap:10px;align-items:center;margin-top:28px;flex-wrap:wrap;">
          <span class="mono-up" style="color:var(--dim);font-size:9px;">يشحن إلى:</span>
          <?php foreach ( [ [ '🇸🇦', 'KSA' ], [ '🇦🇪', 'UAE' ], [ '🇰🇼', 'KW' ], [ '🇧🇭', 'BH' ], [ '🇴🇲', 'OM' ], [ '🇶🇦', 'QA' ] ] as $f ) : ?>
            <span style="display:flex;align-items:center;gap:4px;font-family:var(--font-mono);font-size:11px;color:var(--ink-4);">
              <span style="font-size:16px;"><?php echo esc_html( $f[0] ); ?></span> <?php echo esc_html( $f[1] ); ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ( $ng_hero_total > 0 ) : ?>
        <div class="ng-hero-gallery" data-ng-hero-gallery>
          <div class="ng-hero-gallery-card">
            <?php foreach ( $ng_hero_products as $i => $hp ) : ?>
              <div class="ng-hero-gallery-slide" data-ng-hero-slide<?php echo $i === 0 ? '' : ' hidden'; ?>>
                <?php if ( $hp['cat'] !== '' ) : ?>
                  <div class="ng-hero-gallery-cat"><span class="chip chip-sky" style="font-size:10px;"><?php echo esc_html( $hp['cat'] ); ?></span></div>
                <?php endif; ?>
                <a class="ng-hero-gallery-media" href="<?php echo esc_url( $hp['perm'] ); ?>" aria-label="<?php echo esc_attr( $hp['en'] ); ?>">
                  <?php if ( $hp['img'] ) {
                      echo $hp['img']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_get_attachment_image safe HTML
                  } else { ?>
                    <span class="ng-r1-ph-label"><?php echo esc_html( strtoupper( $hp['sku'] ) ); ?></span>
                  <?php } ?>
                </a>
                <div class="ng-hero-gallery-info">
                  <div class="sku"><?php echo esc_html( strtoupper( $hp['sku'] ) ); ?></div>
                  <h3><a href="<?php echo esc_url( $hp['perm'] ); ?>" style="color:inherit;text-decoration:none;"><?php echo esc_html( $hp['ar'] ); ?></a></h3>
                  <span class="en"><?php echo esc_html( $hp['en'] ); ?></span>
                  <div class="row">
                    <div class="price"><?php echo wp_kses_post( $hp['price'] ); ?></div>
                    <a class="btn btn-sm" href="<?php echo esc_url( $hp['cta_url'] ); ?>" style="font-size:12px;padding:7px 12px;border-radius:var(--r-1);"><?php echo esc_html( $hp['cta_label'] ); ?> +</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <div class="ng-hero-gallery-progress"><span data-ng-hero-progress style="width:<?php echo esc_attr( (int) round( ( 1 / max( 1, $ng_hero_total ) ) * 100 ) ); ?>%;"></span></div>
          </div>

          <div class="ng-hero-thumbs" role="tablist" aria-label="معرض المنتجات">
            <?php foreach ( $ng_hero_products as $i => $hp ) : ?>
              <button type="button" data-ng-hero-thumb role="tab" aria-label="<?php echo esc_attr( $hp['ar'] ); ?>"<?php echo $i === 0 ? ' aria-current="true"' : ''; ?>>
                <?php if ( $hp['img'] ) {
                    // Reuse the markup; for thumbs we want a smaller image — simple <img> from same attachment id
                    $thumb = wp_get_attachment_image( (int) get_post_thumbnail_id( $hp['id'] ) ?: 0, 'thumbnail', false, [ 'alt' => '', 'loading' => 'lazy' ] );
                    if ( $thumb ) {
                        echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                } ?>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="ng-hero-dots" aria-hidden="true">
            <?php foreach ( $ng_hero_products as $i => $hp ) : ?>
              <button type="button" data-ng-hero-dot<?php echo $i === 0 ? ' aria-current="true"' : ''; ?>></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else : ?>
        <?php if ( $ng_hero_id ) : ?>
          <aside class="ng-hero-side" aria-hidden="true">
            <?php echo wp_get_attachment_image( $ng_hero_id, 'large', false, [
                'loading'       => 'eager',
                'fetchpriority' => 'high',
                'decoding'      => 'async',
            ] ); ?>
          </aside>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php
    // Trust strip — 5 cells, replaces the old chip row. Pulls live data
    // where possible (CR via NG_CR / ng_cr() if present) and falls back
    // to safe defaults.
    $cr_number = '7053130576';
    if ( function_exists( 'ng_cr' ) ) {
        $cr = ng_cr();
        if ( ! empty( $cr['number'] ) ) { $cr_number = (string) $cr['number']; }
    } elseif ( defined( 'NG_CR' ) ) {
        $cr_number = (string) NG_CR;
    }
    $ng_trust_cells = [
        [ 'k' => 'السجل التجاري', 'v' => $cr_number ],
        [ 'k' => 'الضريبة',       'v' => '15% شاملة' ],
        [ 'k' => 'الشحن',         'v' => '2–5 أيام عمل' ],
        [ 'k' => 'الإرجاع',       'v' => '14 يوم' ],
        [ 'k' => 'الضمان',        'v' => '12 شهر' ],
    ];
    ?>
    <div class="ng-trust-strip" aria-label="ضمانات المتجر">
      <?php foreach ( $ng_trust_cells as $cell ) : ?>
        <div>
          <span class="k"><?php echo esc_html( $cell['k'] ); ?></span>
          <span class="v"><?php echo esc_html( $cell['v'] ); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</header>

<!-- ============================================================
     SKU TICKER
     ============================================================ -->
<?php if (!empty($ticker_products)) : ?>
<section class="ng-ticker" aria-hidden="true">
  <div class="ng-ticker-track">
    <?php
    $render_ticker = function () use ($ticker_products) {
        foreach ($ticker_products as $p) {
            $sku   = $p->get_sku();
            if (!$sku) { $sku = 'NG-' . $p->get_id(); }
            $name  = $p->get_name();
            $stock = $p->get_stock_quantity();
            $stock_label = ($stock === null || $stock === '') ? 'IN' : (int) $stock;
            echo '<span class="sku">' . esc_html(strtoupper($sku)) . '</span>';
            echo '<span>' . esc_html(strtoupper(wp_trim_words($name, 6, ''))) . '</span>';
            echo '<span>STOCK ' . esc_html($stock_label) . '</span>';
            echo '<span class="dot">·</span>';
        }
    };
    // Render twice for seamless loop (matches preview).
    $render_ticker();
    $render_ticker();
    ?>
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     OPERATOR PICKS — real products, real images
     ============================================================ -->
<?php if (!empty($picks)) : ?>
<section class="ng-section ng-band-dark">
  <div class="ng-container">
    <div class="ng-section-head reveal">
      <div>
        <div class="ng-section-label">01 · <b>المختارات</b></div>
        <h2 class="ng-section-h">متوفّرة. <span class="accent">بمواصفات.</span><br>&#160;جاهزة للشحن.</h2>
        <div class="ng-section-ar">مختارات المشغّلين. جاهزة للشحن.</div>
      </div>
      <p class="ng-section-note">
        وحدات مختارة من الكتالوج — اخترناها للموثوقية، وقابلية الإصلاح، وتوفّر القطع داخل المملكة. كل بطاقة تعرض الرمز، والمواصفات، وفعلًا واحدًا أساسيًا. لا أزرار خادعة.
      </p>
    </div>

    <div class="ng-deck-wrap">
      <button class="ng-deck-arrow ng-deck-arrow--prev" type="button" aria-label="<?php echo esc_attr__('السابق', 'neogen'); ?>" data-direction="prev" data-disabled="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <button class="ng-deck-arrow ng-deck-arrow--next" type="button" aria-label="<?php echo esc_attr__('التالي', 'neogen'); ?>" data-direction="next">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      </button>
      <div class="ng-product-grid ng-product-grid--deck">
      <?php foreach ($picks as $product) :
          if (!$product instanceof WC_Product) { continue; }
          $id        = $product->get_id();
          $sku       = $product->get_sku();
          if (!$sku) { $sku = 'NG-' . $id; }
          $name_en   = $product->get_name();
          $name_ar   = get_post_meta($id, '_ng_ar_title', true);
          if (!$name_ar) { $name_ar = function_exists('ng_ar_label') ? ng_ar_label( $name_en ) : $name_en; }
          if (function_exists('ng_gift_card_clean_product_name')) {
              $name_en = ng_gift_card_clean_product_name($name_en);
              $name_ar = ng_gift_card_clean_product_name($name_ar);
          }
          $perm      = get_permalink($id);

          // Tag / stock badge
          $stock_qty = $product->get_stock_quantity();
          $tag_class = '';
          $tag_label = '';
          $created_ts = get_post_time('U', true, $id);
          $is_new    = $created_ts && (time() - $created_ts) < 30 * DAY_IN_SECONDS;
          if (is_numeric($stock_qty) && $stock_qty !== null && (int) $stock_qty > 0 && (int) $stock_qty < 5) {
              $tag_class = 'hot';
              $tag_label = 'مخزون منخفض · ' . (int) $stock_qty;
          } elseif ($is_new) {
              $tag_class = 'new';
              $tag_label = 'جديد';
          } elseif (is_numeric($stock_qty) && (int) $stock_qty >= 5) {
              $tag_class = '';
              $tag_label = 'متوفّر · ' . (int) $stock_qty;
          } elseif ($product->is_in_stock()) {
              $tag_class = '';
              $tag_label = 'متوفّر';
          } else {
              $tag_class = 'hot';
              $tag_label = 'نفد';
          }

          // Image — real product featured image, or fallback SVG
          $img_id  = $product->get_image_id();
          $img     = $img_id ? wp_get_attachment_image($img_id, 'large', false, ['class' => 'ng-product-img', 'alt' => esc_attr($name_en)]) : '';
          $has_gift_img = false;
          if (function_exists('ng_gift_card_image_html')) {
              $gift_img = ng_gift_card_image_html($product, 'large', $name_en, null, ['class' => 'ng-product-img']);
              if ($gift_img) {
                  $img = $gift_img;
                  $has_gift_img = true;
              }
          }

          // Hover image — first gallery image (if any)
          $gallery_ids = $product->get_gallery_image_ids();
          $img_alt_html = '';
          if ( ! empty( $gallery_ids ) && ! $has_gift_img ) {
              $img_alt_html = wp_get_attachment_image(
                  (int) $gallery_ids[0],
                  'large',
                  false,
                  [
                      'class'    => 'ng-product-img-alt',
                      'alt'      => '',
                      'loading'  => 'lazy',
                      'decoding' => 'async',
                  ]
              );
          }

          // Specs — up to 4 attributes / tags
          $specs = [];
          $attrs = $product->get_attributes();
          foreach ($attrs as $attr) {
              if (!$attr instanceof WC_Product_Attribute) { continue; }
              $vals = $attr->is_taxonomy()
                  ? wp_get_post_terms($id, $attr->get_name(), ['fields' => 'names'])
                  : $attr->get_options();
              if (!empty($vals) && !is_wp_error($vals)) {
                  $specs[] = is_array($vals) ? reset($vals) : $vals;
              }
              if (count($specs) >= 4) { break; }
          }
          if (count($specs) < 4) {
              $tag_terms = wp_get_post_terms($id, 'product_tag', ['fields' => 'names']);
              if (!is_wp_error($tag_terms)) {
                  foreach ($tag_terms as $t) {
                      $specs[] = $t;
                      if (count($specs) >= 4) { break; }
                  }
              }
          }

          // Price — prefer raw amount + SAR unit so we can style the unit differently
          $price_raw  = $product->get_price();
          $price_html = $product->get_price_html();

          // Add-to-cart URL — for simple products, use the native ?add-to-cart=ID so Woo handles it.
          $cta_url = $product->is_type('simple') && $product->is_in_stock()
              ? esc_url( $product->add_to_cart_url() )
              : esc_url( $perm );
          $cta_label = $product->is_type('simple') && $product->is_in_stock() ? 'أضف للسلة' : 'عرض';
      ?>
      <article class="ng-product reveal">
        <div class="ng-product-head">
          <span class="sku"><?php echo esc_html( strtoupper( $sku ) ); ?></span>
          <?php if ( $tag_label ) : ?>
            <span class="tag <?php echo esc_attr( $tag_class ); ?>"><?php echo esc_html( $tag_label ); ?></span>
          <?php endif; ?>
        </div>

        <a class="ng-product-media<?php echo $img_alt_html ? ' has-alt' : ''; ?>" href="<?php echo esc_url( $perm ); ?>" aria-label="<?php echo esc_attr( $name_en ); ?>">
          <?php if ( $img_alt_html ) {
              echo $img_alt_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          } ?>
          <?php if ( $img ) :
              echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_get_attachment_image returns safe HTML.
          else : ?>
            <svg class="placeholder" viewBox="0 0 200 120" fill="none" stroke="currentColor" stroke-width="1.4">
              <rect x="30" y="20" width="140" height="80" rx="6"/>
              <circle cx="100" cy="60" r="18"/>
              <path d="M100 46v28M86 60h28"/>
            </svg>
          <?php endif; ?>
        </a>

        <div class="ng-product-title">
          <div class="ar"><?php echo esc_html( $name_ar ); ?></div>
        </div>

        <?php if ( !empty( $specs ) ) : ?>
        <div class="ng-product-specs">
          <?php foreach ( $specs as $s ) : ?>
            <span class="s"><?php echo esc_html( $s ); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="ng-product-foot">
          <div class="ng-product-price">
            <?php if ( is_numeric( $price_raw ) ) : ?>
              <div class="amount"><?php echo esc_html( number_format_i18n( (float) $price_raw, 0 ) ); ?> <small>SAR</small></div>
            <?php else : ?>
              <div class="amount"><?php echo wp_kses_post( $price_html ); ?></div>
            <?php endif; ?>
            <div class="inc">شامل الضريبة · شحن 2-5 أيام</div>
          </div>
          <a class="ng-product-cta" href="<?php echo esc_url( $cta_url ); ?>"<?php echo $product->is_type('simple') && $product->is_in_stock() ? ' data-product_id="' . esc_attr($id) . '"' : ''; ?>>
            <?php echo esc_html( $cta_label ); ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5v14"/></svg>
          </a>
        </div>
      </article>
      <?php endforeach; ?>
      </div><!-- /.ng-product-grid--deck -->
    </div><!-- /.ng-deck-wrap -->
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     CATEGORIES — PHOTO-LED MOSAIC (v1.26.0)
     ============================================================ -->
<?php if (!empty($top_categories)) : ?>
<?php
// Per-request cache for category photo lookup. Initialized once per
// pageview so each category resolves its (thumbnail | first-product
// image) at most once.
$ng_cat_photo_cache = array();

// AR copy per slug.
$ng_cat_copy_map = apply_filters('neogen_homepage_cat_copy', array(
    'hardware'    => 'أجهزة وتجميعات PC مختارة — معالجات، لوحات، تخزين، تبريد.',
    'gift-cards'  => 'بطاقات رقمية ومفاتيح برامج — تفعيل فوري.',
    'networking'  => 'شبكات واتصالات — راوتر، سويتش، نقاط وصول، ألياف.',
    'smart-home'  => 'أتمتة المنزل الذكي — Aqara · Shelly · Home Assistant.',
    'gaming'      => 'ألعاب وإكسسوارات — يدّات، شاشات، صوت، كيبل.',
    'gaming-2'    => 'ألعاب وإكسسوارات — يدّات، شاشات، صوت، كيبل.',
    'homelab'     => 'هوم لاب — رفوف، سيرفرات، تخزين شبكة، NAS.',
    'storage'     => 'تخزين — أقراص NVMe وSSD وHDD ومحطات NAS.',
));
?>
<section class="ng-section" id="ng-cat">
  <div class="ng-container">
    <div class="ng-section-head reveal">
      <div>
        <div class="ng-section-label">02 · <b>الفئات</b></div>
        <h2 class="ng-section-h">فئاتنا <span class="accent">الـ <?php echo esc_html( count( $top_categories ) ); ?></span><br>&#160;المختارة.</h2>
        <div class="ng-section-ar">فئات مختارة. كل فئة لعمل تقني واضح.</div>
      </div>
      <p class="ng-section-note">
        كل فئة مهيّأة لنوع مشغّل محدّد. أعداد المنتجات تُسحب مباشرةً من الكتالوج — إذا لم يكن المنتج نافعًا في شبكة جادة، أو هوم لاب، أو بيت ذكي، أو إعداد ألعاب، أو خدمة تنفيذ — فلا نحمله.
      </p>
    </div>

    <div class="ng-rack ng-rack--mosaic">
      <?php foreach ($top_categories as $i => $term) :
          $slug    = $term->slug;
          $ar_name = function_exists('ng_ar_label') ? ng_ar_label( $term->name ) : $term->name;
          $link    = get_term_link($term);
          $link    = is_wp_error($link) ? '#' : $link;

          // Photo: prefer category thumbnail, fall back to first product image.
          if ( ! isset( $ng_cat_photo_cache[ $term->term_id ] ) ) {
              $thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
              if ( ! $thumb_id && function_exists( 'wc_get_products' ) ) {
                  $first = wc_get_products( array(
                      'category' => array( $term->slug ),
                      'limit'    => 1,
                      'status'   => 'publish',
                      'orderby'  => 'date',
                      'order'    => 'DESC',
                  ) );
                  if ( ! empty( $first ) && $first[0] instanceof WC_Product ) {
                      $thumb_id = (int) $first[0]->get_image_id();
                  }
              }
              $ng_cat_photo_cache[ $term->term_id ] = $thumb_id;
          }
          $thumb_id = (int) $ng_cat_photo_cache[ $term->term_id ];

          // AR description: copy_map override → Arabic term description (≤100 chars) → generic fallback.
          if ( isset( $ng_cat_copy_map[ $slug ] ) ) {
              $desc = $ng_cat_copy_map[ $slug ];
          } else {
              $desc_raw    = trim( (string) $term->description );
              $is_english  = $desc_raw !== '' && ! preg_match('/[\x{0600}-\x{06FF}]/u', $desc_raw);
              $is_too_long = mb_strlen($desc_raw) > 100;
              if ( $desc_raw === '' || $is_english || $is_too_long ) {
                  $desc = sprintf( 'تشكيلة %s مختارة — شحن من المملكة، ضمان 12 شهر.', $ar_name );
              } else {
                  $desc = $desc_raw;
              }
          }
      ?>
      <a class="ng-cat-card reveal" href="<?php echo esc_url( $link ); ?>" aria-label="<?php echo esc_attr( $ar_name ); ?>">
        <span class="ng-cat-photo" aria-hidden="true">
          <?php if ( $thumb_id ) : ?>
            <?php echo wp_get_attachment_image( $thumb_id, 'medium_large', false, array(
                'loading'  => 'lazy',
                'decoding' => 'async',
                'alt'      => '',
                'class'    => 'ng-cat-img',
            ) ); ?>
          <?php endif; ?>
        </span>
        <span class="ng-cat-overlay" aria-hidden="true"></span>
        <span class="ng-cat-count" dir="ltr"><b><?php echo esc_html( (int) $term->count ); ?></b> منتج</span>
        <span class="ng-cat-body">
          <span class="ng-cat-title"><?php echo esc_html( $ar_name ); ?></span>
          <span class="ng-cat-desc"><?php echo esc_html( $desc ); ?></span>
          <span class="ng-cat-cta">تصفّح <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     NEW ARRIVALS — horizontal scroll deck (v1.27.0)
     ============================================================ -->
<?php $ng_render_product_deck($ng_new_arrivals, [
    'id'         => 'ng-new-arrivals',
    'label_html' => '03 · <b>وصلت حديثاً</b>',
    'h2_html'    => 'آخر <span class="accent">الإضافات</span><br>&#160;إلى الكتالوج.',
    'subhead'    => 'أحدث المنتجات على الرفوف.',
    'note'       => 'منتجات أُضيفت مؤخرًا — اختبرناها قبل الإضافة، وثبتنا توفّر القطع داخل المملكة.',
]); ?>

<!-- ============================================================
     DEALS — horizontal scroll deck (sale-priced products only)
     ============================================================ -->
<?php $ng_render_product_deck($ng_deals, [
    'id'         => 'ng-deals',
    'band_class' => 'ng-band-light',
    'label_html' => '04 · <b>تخفيضات</b>',
    'h2_html'    => 'عروض <span class="accent">حالية</span><br>&#160;على المخزون.',
    'subhead'    => 'أسعار تخفيض حقيقية. لا أرقام مفبركة.',
    'note'       => 'منتجات بتخفيض حالي. السعر يُحدّث مباشرةً من الكتالوج — يختفي العرض عند انتهاء التخفيض.',
]); ?>

<!-- ============================================================
     GIFT CARDS — GCC · US · UK
     ============================================================ -->
<?php $ng_render_product_deck($ng_gift_cards, [
    'id'         => 'ng-gift-cards',
    'label_html' => '05 · <b>بطاقات الهدايا</b>',
    'h2_html'    => 'بطاقات رقمية.<br><span class="accent">GCC · US · UK</span>',
    'subhead'    => 'تسليم فوري — Apple · Google Play · Steam · Netflix · Adobe.',
    'note'       => 'بطاقات معتمدة من المتجر للسعودية ودول الخليج، الولايات المتحدة، والمملكة المتحدة. اختر منطقة التفعيل عند إكمال الشراء.',
]); ?>

<!-- ============================================================
     BRANDS STRIP — vendors (marquee fallback when no logos uploaded)
     ============================================================ -->
<?php
$ng_brand_ids = (array) get_option('ng_brand_logo_ids', []);
$ng_brand_ids = array_values( array_filter( array_map('intval', $ng_brand_ids) ) );

// Text-based brand chips for the marquee (used when no logo IDs exist
// or as a supplement). Pulled from the curated 24 products' real
// vendor list.
$ng_brand_chips = apply_filters('neogen_brand_chips', [
    'ASUS ROG', 'Ubiquiti', 'MinisForum', 'Hubitat', 'TP-Link',
    'NZXT', 'Corsair', 'Gigabyte AORUS', 'Beelink', 'HyperX',
    'Elgato', 'DJI', 'Roborock', 'JSAUX', 'SwitchBot',
    'Apple', 'Adobe', 'Kaspersky',
]);
?>
<section class="ng-section ng-brands">
  <div class="ng-container">
    <div class="ng-section-head reveal">
      <div class="ng-section-kicker">
        <span></span>
        06 · <b>علامات موثّقة</b>
      </div>
      <div class="ng-section-titles">
        <h2 class="ng-section-en">محمولة. مشحونة. مدعومة.</h2>
        <div class="ng-section-ar">العلامات التي نحملها — كل واحدة بمواصفات نعرفها.</div>
      </div>
    </div>
    <?php if ( ! empty( $ng_brand_ids ) ) : ?>
      <div class="ng-brands-row">
        <?php foreach ( $ng_brand_ids as $bid ) : ?>
          <div class="ng-brand-tile">
            <?php echo wp_get_attachment_image( $bid, 'medium', false, [
                'loading'  => 'lazy', 'decoding' => 'async', 'alt' => '',
            ] ); ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else : ?>
      <div class="ng-brands-marquee" aria-hidden="true">
        <div class="ng-brands-marquee-track">
          <?php for ($mq = 0; $mq < 2; $mq++) : ?>
            <?php foreach ( $ng_brand_chips as $brand ) : ?>
              <span class="ng-brand-chip"><?php echo esc_html( $brand ); ?></span>
            <?php endforeach; ?>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ============================================================
     SERVICE STRIP — 3 build-to-spec cards (v1.28.0)
     ============================================================ -->
<section class="ng-section" id="ng-service">
  <div class="ng-container">
    <div class="ng-section-head reveal">
      <div>
        <div class="ng-section-label">07 · <b>مكتب الخدمة</b></div>
        <h2 class="ng-section-h">تنفيذ <span class="accent">مخصّص.</span><br>&#160;من الفكرة إلى التشغيل.</h2>
        <div class="ng-section-ar">اشرح لنا الاحتياج، نرد بخطة عمل واضحة.</div>
      </div>
      <p class="ng-section-note">شبكة، هوم لاب، أو بيت ذكي — نختار المكوّنات، نشحن داخل المملكة، ونركّب بالموقع. كل عرض يصل خلال يوم عمل.</p>
    </div>

    <div class="ng-service-grid">
      <article class="ng-service-card reveal">
        <span class="ng-service-icon" aria-hidden="true">
          <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6">
            <circle cx="16" cy="16" r="3"/>
            <path d="M16 13V5M16 19v8M13 16H5M19 16h8M10 10l-4-4M22 22l4 4M10 22l-4 4M22 10l4-4"/>
          </svg>
        </span>
        <h3 class="ng-service-title">شبكة مكتبية</h3>
        <p class="ng-service-copy">شبكات للمكاتب والمشاريع — راوتر، سويتشات، نقاط وصول، ألياف، وكابلات معتمدة.</p>
        <ul class="ng-service-bullets">
          <li>تخطيط وتركيب كامل بالموقع</li>
          <li>أجهزة MikroTik · Ubiquiti · TP-Link Omada</li>
          <li>دعم بعد التشغيل</li>
        </ul>
        <a class="btn btn-primary ng-service-cta-btn" href="<?php echo esc_url( $contact_url . '?type=network' ); ?>">
          اطلب عرضًا
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>
      </article>

      <article class="ng-service-card reveal">
        <span class="ng-service-icon" aria-hidden="true">
          <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6">
            <rect x="5" y="6" width="22" height="6" rx="1"/>
            <rect x="5" y="14" width="22" height="6" rx="1"/>
            <rect x="5" y="22" width="22" height="4" rx="1"/>
            <circle cx="9" cy="9" r="0.8" fill="currentColor"/>
            <circle cx="9" cy="17" r="0.8" fill="currentColor"/>
          </svg>
        </span>
        <h3 class="ng-service-title">هوم لاب</h3>
        <p class="ng-service-copy">سيرفرات، تخزين شبكي NAS، رفوف 12U/24U، ومحطات Proxmox / TrueNAS جاهزة.</p>
        <ul class="ng-service-bullets">
          <li>اختيار المكوّنات حسب الحمل</li>
          <li>MinisForum · Synology · Asustor · Dell</li>
          <li>تركيب وتثبيت برمجي</li>
        </ul>
        <a class="btn btn-primary ng-service-cta-btn" href="<?php echo esc_url( $contact_url . '?type=homelab' ); ?>">
          اطلب عرضًا
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>
      </article>

      <article class="ng-service-card reveal">
        <span class="ng-service-icon" aria-hidden="true">
          <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.6">
            <path d="M4 15 16 5l12 10M7 13v14h18V13"/>
            <path d="M13 27v-7h6v7"/>
          </svg>
        </span>
        <h3 class="ng-service-title">بيت ذكي</h3>
        <p class="ng-service-copy">أتمتة الإضاءة والمناخ والأمن — تكامل Apple HomeKit, Matter, Home Assistant, Aqara.</p>
        <ul class="ng-service-bullets">
          <li>تخطيط الأجهزة لكل غرفة</li>
          <li>Aqara · SwitchBot · Hubitat · Shelly</li>
          <li>تركيب وضبط المشاهد</li>
        </ul>
        <a class="btn btn-primary ng-service-cta-btn" href="<?php echo esc_url( $contact_url . '?type=smart-home' ); ?>">
          اطلب عرضًا
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>
      </article>
    </div>

    <div class="ng-service-foot reveal">
      <p>أو راسلنا مباشرةً — نرد خلال يوم عمل.</p>
      <div class="ng-service-foot-ctas">
        <a class="btn btn-ghost" href="<?php echo esc_url( $contact_url ); ?>">
          <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/icons/spec-brief.svg' ); ?>" width="20" height="20" alt="" class="ng-icon-mono">
          أرسل المواصفات
        </a>
        <?php if ( $has_whatsapp ) : ?>
          <a class="btn btn-ghost" href="<?php echo esc_url( $whatsapp_url ); ?>" rel="noopener noreferrer" target="_blank" aria-label="مراسلتنا عبر واتساب">
            <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/icons/whatsapp.svg' ); ?>" width="20" height="20" alt="" class="ng-icon-mono">
            واتساب
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php
get_footer();
