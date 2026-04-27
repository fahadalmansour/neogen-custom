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
$published_products = (int) wp_count_posts('product')->publish;

// Top 5 product categories — transient-cached helper from neogen-theme.php.
$top_categories = function_exists('ng_top_product_cats') ? ng_top_product_cats(5) : [];

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
            if (count($picks) >= 4) break;
            $slug = $primary_cat_slug($p);
            if ($slug !== '' && in_array($slug, $picks_cats, true)) continue;
            $picks[] = $p;
            $picks_cats[]  = $slug;
            $picks_added[] = $p->get_id();
        }
    }

    // Pass 2 — fill from latest in NEW categories
    if (count($picks) < 4) {
        $fill = wc_get_products([
            'status'  => 'publish',
            'limit'   => 24,
            'orderby' => 'date',
            'order'   => 'DESC',
            'exclude' => $picks_added,
        ]);
        foreach ((array) $fill as $p) {
            if (count($picks) >= 4) break;
            $slug = $primary_cat_slug($p);
            if ($slug !== '' && in_array($slug, $picks_cats, true)) continue;
            $picks[] = $p;
            $picks_cats[]  = $slug;
            $picks_added[] = $p->get_id();
        }
    }

    // Pass 3 — last resort, drop diversity rule and pad to 4
    if (count($picks) < 4) {
        $pad = wc_get_products([
            'status'  => 'publish',
            'limit'   => 4 - count($picks),
            'orderby' => 'date',
            'order'   => 'DESC',
            'exclude' => $picks_added,
        ]);
        foreach ((array) $pad as $p) {
            if (count($picks) >= 4) break;
            $picks[] = $p;
            $picks_added[] = $p->get_id();
        }
    }
}
$picks = array_slice($picks, 0, 4);

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

  <div class="ng-hero-inner">
    <div class="ng-hero-main">
      <div class="ng-kicker">
        <span></span>
        <?php echo esc_html__( 'متجر تقني سعودي · معتمد', 'neogen' ); ?>
      </div>
      <h1 class="ng-hero-h1">جيل التقنية <br> <span class="accent">القادم</span>.</h1>

      <div class="ng-hero-wordmark" aria-hidden="true">
        <img class="ng-lockup-mark" src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/logo/ng-mark.png' ); ?>" alt="" width="80" height="62" decoding="async">
        <span class="sep"></span>
        <span class="wordmark"><span class="neo">NEO</span><span class="gen">GEN</span></span>
        <span class="store">STORE</span>
      </div>

      <p class="ng-hero-copy">
        متجر تقني سعودي لمحترفي الشبكات، الهوم لاب، البيوت الذكية، والألعاب.
        منتجات مختارة، مواصفات بدون مبالغة، شحن من داخل المملكة.
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
    </div>

    <?php
    /*
     * Hero side visual — three-tier cascade:
     *   1. Explicit ng_hero_side_image_id (admin override) — wins.
     *   2. CSS-only auto-collage of the four diversified picks
     *      (gift-cards can only ever fill 1 of 4 slots thanks to the
     *      primary-cat dedup in front-page picks logic). Falls back
     *      gracefully on each missing image.
     *   3. Top category thumbnails (last resort) so the panel is never
     *      empty as long as the catalog has at least one category.
     */
    $ng_hero_side_id = (int) get_option('ng_hero_side_image_id');
    ?>
    <?php if ( $ng_hero_side_id ) : ?>
      <aside class="ng-hero-side" aria-hidden="true">
        <?php echo wp_get_attachment_image( $ng_hero_side_id, 'large', false, [
            'loading'       => 'eager',
            'fetchpriority' => 'high',
            'decoding'      => 'async',
        ] ); ?>
      </aside>
    <?php else :
        // Auto-collage from the diversified picks (or category thumbs as fallback)
        $collage_imgs = [];
        foreach ( (array) $picks as $cp ) {
            if ( count($collage_imgs) >= 4 ) break;
            if ( ! $cp instanceof WC_Product ) continue;
            $cp_img_id = (int) $cp->get_image_id();
            if ( $cp_img_id ) {
                $collage_imgs[] = wp_get_attachment_image( $cp_img_id, 'medium', false, [
                    'loading'  => 'eager',
                    'decoding' => 'async',
                    'alt'      => '',
                ] );
            }
        }
        if ( count($collage_imgs) < 4 && ! empty( $top_categories ) ) {
            foreach ( $top_categories as $tc ) {
                if ( count($collage_imgs) >= 4 ) break;
                $tc_id = (int) get_term_meta( $tc->term_id, 'thumbnail_id', true );
                if ( $tc_id ) {
                    $collage_imgs[] = wp_get_attachment_image( $tc_id, 'medium', false, [
                        'loading'  => 'eager',
                        'decoding' => 'async',
                        'alt'      => '',
                    ] );
                }
            }
        }
        if ( ! empty( $collage_imgs ) ) :
    ?>
      <aside class="ng-hero-side ng-hero-collage" aria-hidden="true">
        <?php foreach ( $collage_imgs as $tile ) : ?>
          <span class="ng-hero-tile"><?php echo $tile; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <?php endforeach; ?>
      </aside>
    <?php endif; endif; ?>
  </div>

  <div class="ng-hero-meta" aria-hidden="true">
    <span class="ng-chip"><span class="dot"></span><strong>سجل تجاري</strong> 7053130576</span>
    <span class="ng-chip"><strong>الضريبة</strong> 15% شاملة</span>
    <span class="ng-chip"><strong>الشحن</strong> 2-5 أيام عمل</span>
    <span class="ng-chip"><strong>الإرجاع</strong> 14 يوم</span>
    <span class="ng-chip"><strong>الضمان</strong> 12 شهر</span>
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
     CATEGORIES — RACK UNITS
     ============================================================ -->
<?php if (!empty($top_categories)) : ?>
<section class="ng-section" id="ng-cat">
  <div class="ng-container">
    <div class="ng-section-head reveal">
      <div>
        <div class="ng-section-label">01 · <b>الفئات</b></div>
        <h2 class="ng-section-h">فئاتنا <span class="accent">الـ <?php echo esc_html( count( $top_categories ) ); ?></span><br>المختارة.</h2>
        <div class="ng-section-ar">فئات مختارة. كل فئة لعمل تقني واضح.</div>
      </div>
      <p class="ng-section-note">
        كل فئة مهيّأة لنوع مشغّل محدّد. أعداد المنتجات تُسحب مباشرةً من الكتالوج — إذا لم يكن المنتج نافعًا في شبكة جادة، أو هوم لاب، أو بيت ذكي، أو إعداد ألعاب، أو خدمة تنفيذ — فلا نحمله.
      </p>
    </div>

    <div class="ng-rack">
      <?php foreach ($top_categories as $i => $term) :
          $slug     = $term->slug;
          $icon     = isset($category_icons[$slug]) ? $category_icons[$slug] : $fallback_icon;
          $ar_name  = trim((string) $term->description);
          if ($ar_name === '') { $ar_name = function_exists('ng_ar_label') ? ng_ar_label( $term->name ) : $term->name; }
          $link     = get_term_link($term);
          $link     = is_wp_error($link) ? '#' : $link;
          $led      = $led_patterns[$i % count($led_patterns)];
          $rack_id  = sprintf('%02d · رف %s', $i + 1, $rack_letter($i));
      ?>
      <a class="ng-rack-unit reveal" href="<?php echo esc_url( $link ); ?>">
        <span class="ng-rack-id"><?php echo esc_html( $rack_id ); ?></span>
        <span class="ng-rack-led" aria-hidden="true"><?php echo $led; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <?php
            $thumb_id  = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
            $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
            if ( $thumb_url ) :
        ?>
          <span class="ng-rack-photo">
            <?php echo wp_get_attachment_image( $thumb_id, 'medium', false, [
                'loading'  => 'lazy',
                'decoding' => 'async',
                'alt'      => esc_attr( sprintf( __( '%s category', 'neogen' ), $term->name ) ),
            ] ); ?>
          </span>
        <?php else : ?>
          <span class="ng-rack-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <?php endif; ?>
        <span class="ng-rack-title">
          <span class="ar"><?php echo esc_html( $ar_name ); ?></span>
        </span>
        <span class="ng-rack-desc">
          <?php
          /*
           * AR-first card description. Three sources, in priority order:
           *   1. Manual override map (slug → short Arabic line) — keeps
           *      the homepage tight regardless of what's in the term
           *      description in WP admin.
           *   2. The term description itself, but only if it's Arabic
           *      AND under 100 chars.
           *   3. Generic Arabic fallback line.
           */
          $copy_map = apply_filters('neogen_homepage_cat_copy', [
              'hardware'    => 'أجهزة وتجميعات PC مختارة — معالجات، لوحات، تخزين، تبريد.',
              'gift-cards'  => 'بطاقات رقمية ومفاتيح برامج — تفعيل فوري.',
              'networking'  => 'شبكات واتصالات — راوتر، سويتش، نقاط وصول، ألياف.',
              'smart-home'  => 'أتمتة المنزل الذكي — Aqara · Shelly · Home Assistant.',
              'gaming'      => 'ألعاب وإكسسوارات — يدّات، شاشات، صوت، كيبل.',
              'gaming-2'    => 'ألعاب وإكسسوارات — يدّات، شاشات، صوت، كيبل.',
              'homelab'     => 'هوم لاب — رفوف، سيرفرات، تخزين شبكة، NAS.',
              'storage'     => 'تخزين — أقراص NVMe وSSD وHDD ومحطات NAS.',
          ]);

          $ar_label = function_exists('ng_ar_label') ? ng_ar_label( $term->name ) : $term->name;
          if ( isset( $copy_map[ $slug ] ) ) {
              echo esc_html( $copy_map[ $slug ] );
          } else {
              $desc_raw    = trim( (string) $term->description );
              $is_english  = $desc_raw !== '' && ! preg_match('/[\x{0600}-\x{06FF}]/u', $desc_raw);
              $is_too_long = mb_strlen($desc_raw) > 100;
              if ( $desc_raw === '' || $desc_raw === $ar_name || $is_english || $is_too_long ) {
                  echo esc_html( sprintf( __( 'تشكيلة %s مختارة — شحن من المملكة، ضمان 12 شهر.', 'neogen' ), $ar_label ) );
              } else {
                  echo esc_html( $desc_raw );
              }
          }
          ?>
        </span>
        <span class="ng-rack-count"><b><?php echo esc_html( (int) $term->count ); ?></b> منتج</span>
        <span class="ng-rack-link">تصفّح <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
      </a>
      <?php endforeach; ?>
    </div>
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
        <div class="ng-section-label">02 · <b>المختارات</b></div>
        <h2 class="ng-section-h">متوفّرة. <span class="accent">بمواصفات.</span><br>جاهزة للشحن.</h2>
        <div class="ng-section-ar">مختارات المشغّلين. جاهزة للشحن.</div>
      </div>
      <p class="ng-section-note">
        وحدات مختارة من الكتالوج — اخترناها للموثوقية، وقابلية الإصلاح، وتوفّر القطع داخل المملكة. كل بطاقة تعرض الرمز، والمواصفات، وفعلًا واحدًا أساسيًا. لا أزرار خادعة.
      </p>
    </div>

    <div class="ng-product-grid">
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
          $img     = $img_id ? wp_get_attachment_image($img_id, 'medium_large', false, ['class' => 'ng-product-img', 'alt' => esc_attr($name_en)]) : '';
          $has_gift_img = false;
          if (function_exists('ng_gift_card_image_html')) {
              $gift_img = ng_gift_card_image_html($product, 'medium_large', $name_en, null, ['class' => 'ng-product-img']);
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
                  'medium_large',
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
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     BRANDS STRIP — vendors
     ============================================================ -->
<?php
$ng_brand_ids = (array) get_option('ng_brand_logo_ids', []);
$ng_brand_ids = array_values( array_filter( array_map('intval', $ng_brand_ids) ) );
if ( ! empty( $ng_brand_ids ) ) :
?>
<section class="ng-section ng-brands">
  <div class="ng-container">
    <div class="ng-section-head">
      <div class="ng-section-kicker">
        <span></span>
        02·ب · <b>علامات موثّقة</b>
      </div>
      <div class="ng-section-titles">
        <h2 class="ng-section-en">محمولة. مشحونة. مدعومة.</h2>
        <div class="ng-section-ar">العلامات التي نحملها — كل واحدة بمواصفات نعرفها.</div>
      </div>
    </div>
    <div class="ng-brands-row">
      <?php foreach ( $ng_brand_ids as $bid ) : ?>
        <div class="ng-brand-tile">
          <?php echo wp_get_attachment_image( $bid, 'medium', false, [
              'loading'  => 'lazy',
              'decoding' => 'async',
              'alt'      => '',
          ] ); ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============================================================
     SERVICE STRIP — "اكتب لنا مواصفاتك"
     ============================================================ -->
<section class="ng-section" id="ng-service">
  <div class="ng-service-strip reveal">
    <div class="ng-service-inner">
      <div class="ng-service-text">
        <div class="kicker-s"><span style="width:7px;height:7px;background:var(--signal);display:inline-block;box-shadow:0 0 12px var(--signal);"></span>تنفيذ مخصّص · 03 · مكتب الخدمة</div>
        <div class="ar">اكتب لنا مواصفاتك. نرجع لك بخطة واضحة.</div>
        <p>
          شبكة لمكتب، تركيبة هوم لاب، بيت ذكي كامل، أو محطة ألعاب تنافسية — اشرح لنا الاحتياج، ونرد عليك بمخطط تنفيذ مفصّل، قائمة مكوّنات، وتقدير زمن الشحن والتركيب.
        </p>
      </div>
      <div class="ng-service-cta">
        <a class="btn btn-primary" href="<?php echo esc_url( $contact_url ); ?>">
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

<!-- ============================================================
     VOICE BAND
     ============================================================ -->
<section class="ng-voice">
  <div class="ng-voice-bg" aria-hidden="true">
    <svg viewBox="-50 -50 100 100">
      <path d="M0 -44 L9 -26 L35 -35 L26 -9 L44 0 L26 9 L35 35 L9 26 L0 44 L-9 26 L-35 35 L-26 9 L-44 0 L-26 -9 L-35 -35 L-9 -26 Z"></path>
    </svg>
  </div>
  <div class="ng-voice-inner">
    <div class="ng-voice-text">
      <div class="ng-voice-kicker">// 04 · صوت العلامة</div>
      <div class="ng-voice-ar">التقنية.<br>كما <span class="accent">يجب</span> أن تكون.</div>
      <div class="ng-voice-en">
        تقنية
        <span class="sep"></span>
        كما يجب
        <span class="sep"></span>
        شحن من المملكة
      </div>
    </div>
    <?php if ( $ng_voice_id = (int) get_option('ng_voice_image_id') ) : ?>
      <div class="ng-voice-photo">
        <?php echo wp_get_attachment_image( $ng_voice_id, 'large', false, [
            'loading'  => 'lazy',
            'decoding' => 'async',
        ] ); ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php
get_footer();
