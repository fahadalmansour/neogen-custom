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

$whatsapp_url = defined('NG_WHATSAPP_URL') ? NG_WHATSAPP_URL : '#';
$contact_url  = function_exists('wc_get_page_permalink') ? home_url('/contact/') : home_url('/');
$shop_url     = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');

// Catalog counts (for the hero systems-brief aside).
$published_products = (int) wp_count_posts('product')->publish;

// Top 5 product categories — transient-cached helper from neogen-theme.php.
$top_categories = function_exists('ng_top_product_cats') ? ng_top_product_cats(5) : [];

// Operator Picks — featured products, padded with latest if fewer than 4.
$featured_ids = function_exists('wc_get_featured_product_ids') ? wc_get_featured_product_ids() : [];
$picks        = [];
if (!empty($featured_ids) && function_exists('wc_get_products')) {
    $picks = wc_get_products([
        'status'  => 'publish',
        'include' => array_slice($featured_ids, 0, 4),
        'limit'   => 4,
    ]);
}
$picks = array_slice(is_array($picks) ? $picks : [], 0, 4);
if (count($picks) < 4 && function_exists('wc_get_products')) {
    $exclude = array_map(function ($p) { return $p->get_id(); }, $picks);
    $fill    = wc_get_products([
        'status'  => 'publish',
        'limit'   => 4 - count($picks),
        'orderby' => 'date',
        'order'   => 'DESC',
        'exclude' => $exclude,
    ]);
    $picks = array_merge($picks, is_array($fill) ? $fill : []);
}

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
        <?php echo esc_html( sprintf( __( 'SELECTION LOCKED / %s DROP', 'neogen' ), date_i18n( 'F Y' ) ) ); ?>
      </div>
      <h1 class="ng-hero-h1">جيل التقنية<br><span class="accent">القادم</span>.</h1>

      <div class="ng-hero-wordmark" aria-hidden="true">
        <span class="mono">N<span class="g">G</span></span>
        <span class="sep"></span>
        <span class="wordmark"><span class="neo">NEO</span><span class="gen">GEN</span></span>
        <span class="store">STORE</span>
      </div>

      <p class="ng-hero-copy">
        متجر تقني سعودي لمحترفي الشبكات، الهوم لاب، البيوت الذكية، والألعاب.
        منتجات مختارة، مواصفات بدون مبالغة، شحن من داخل المملكة.
      </p>

      <div class="ng-hero-sub">// SPECIALIZED TECH · ASSEMBLED FOR OPERATORS · SHIPPED FROM KSA //</div>

      <div class="ng-hero-ctas">
        <a class="btn btn-primary" href="<?php echo esc_url( $shop_url ); ?>">
          تصفح المتجر
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>
        <a class="btn btn-ghost" href="#ng-service">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 4v4h8V4m-8 16v-4h8v4M4 8h16v8H4z"/></svg>
          BUILD A RIG
        </a>
      </div>
    </div>

    <aside class="ng-hero-brief" aria-label="Systems brief">
      <div class="ng-brief-head">
        <span>// SYSTEMS BRIEF</span>
        <span><?php echo esc_html( strtoupper( date_i18n( 'M Y' ) ) ); ?></span>
      </div>

      <div class="ng-brief-row">
        <span class="k">Catalog</span>
        <span class="v"><?php echo esc_html( sprintf( _n( '%d active SKU', '%d active SKUs', $published_products, 'neogen' ), $published_products ) ); ?></span>
        <span class="t">LIVE</span>
      </div>
      <div class="ng-brief-row">
        <span class="k">Fulfilment</span>
        <span class="v">Riyadh · Jeddah · Dammam</span>
        <span class="t">2-5D</span>
      </div>
      <div class="ng-brief-row">
        <span class="k">Support</span>
        <span class="v">12-month warranty · AR</span>
        <span class="t">24/7</span>
      </div>
      <div class="ng-brief-row">
        <span class="k">Payment</span>
        <span class="v">Mada · Apple Pay · STC · Tabby</span>
        <span class="t">PCI</span>
      </div>
      <div class="ng-brief-row">
        <span class="k">Top cat</span>
        <span class="v"><?php
          if (!empty($top_categories)) {
              echo esc_html( $top_categories[0]->name . ' · ' . $top_categories[0]->count . ' SKUs' );
          } else {
              echo '—';
          }
        ?></span>
        <span class="t">OK</span>
      </div>

      <a class="ng-brief-cta" href="<?php echo esc_url( $shop_url ); ?>">OPEN CATALOG →</a>
    </aside>
  </div>

  <div class="ng-hero-meta" aria-hidden="true">
    <span class="ng-chip"><span class="dot"></span><strong>CR</strong> 7053130576</span>
    <span class="ng-chip"><strong>VAT</strong> 15% INCLUDED</span>
    <span class="ng-chip"><strong>SHIP</strong> 2-5 BUSINESS DAYS</span>
    <span class="ng-chip"><strong>RETURNS</strong> 14 DAYS</span>
    <span class="ng-chip"><strong>WARRANTY</strong> 12 MONTHS</span>
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
        <div class="ng-section-label">01 / <b>CATEGORIES</b></div>
        <h2 class="ng-section-h">The <span class="accent"><?php echo esc_html( count( $top_categories ) ); ?> Racks</span><br>We Stock</h2>
        <div class="ng-section-ar">فئات مختارة. كل فئة لعمل تقني واضح.</div>
      </div>
      <p class="ng-section-note">
        Each rack is curated for a specific operator profile. Live category counts are pulled from the catalog — if an SKU is not useful on a serious network, homelab, smart home, gaming setup, or service engagement, we do not carry it.
      </p>
    </div>

    <div class="ng-rack">
      <?php foreach ($top_categories as $i => $term) :
          $slug     = $term->slug;
          $icon     = isset($category_icons[$slug]) ? $category_icons[$slug] : $fallback_icon;
          $ar_name  = trim((string) $term->description);
          if ($ar_name === '') { $ar_name = $term->name; }
          $link     = get_term_link($term);
          $link     = is_wp_error($link) ? '#' : $link;
          $led      = $led_patterns[$i % count($led_patterns)];
          $rack_id  = sprintf('%02d · RACK %s', $i + 1, $rack_letter($i));
      ?>
      <a class="ng-rack-unit reveal" href="<?php echo esc_url( $link ); ?>">
        <span class="ng-rack-id"><?php echo esc_html( $rack_id ); ?></span>
        <span class="ng-rack-led" aria-hidden="true"><?php echo $led; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <span class="ng-rack-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
        <span class="ng-rack-title">
          <span class="ar"><?php echo esc_html( $ar_name ); ?></span>
          <span class="en"><?php echo esc_html( strtoupper( $term->name ) ); ?></span>
        </span>
        <span class="ng-rack-desc">
          <?php
          if ( $term->description && $term->description !== $ar_name ) {
              echo esc_html( $term->description );
          } else {
              echo esc_html( sprintf( __( 'Curated %s selection — shipped from KSA, 12-month warranty.', 'neogen' ), strtolower( $term->name ) ) );
          }
          ?>
        </span>
        <span class="ng-rack-count"><b><?php echo esc_html( (int) $term->count ); ?></b>SKUs</span>
        <span class="ng-rack-link">Browse <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
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
        <div class="ng-section-label">02 / <b>OPERATOR PICKS</b></div>
        <h2 class="ng-section-h">Stocked. <span class="accent">Specced.</span><br>Ready to ship.</h2>
        <div class="ng-section-ar">مختارات المشغّلين. جاهزة للشحن.</div>
      </div>
      <p class="ng-section-note">
        Featured units we keep moving — chosen for reliability, repairability, and parts availability inside the Kingdom. Every card shows SKU, live spec, and one primary action. No decoy buttons.
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
          if (!$name_ar) { $name_ar = $name_en; }
          $perm      = get_permalink($id);

          // Tag / stock badge
          $stock_qty = $product->get_stock_quantity();
          $tag_class = '';
          $tag_label = '';
          $created_ts = get_post_time('U', true, $id);
          $is_new    = $created_ts && (time() - $created_ts) < 30 * DAY_IN_SECONDS;
          if (is_numeric($stock_qty) && $stock_qty !== null && (int) $stock_qty > 0 && (int) $stock_qty < 5) {
              $tag_class = 'hot';
              $tag_label = 'LOW STOCK · ' . (int) $stock_qty;
          } elseif ($is_new) {
              $tag_class = 'new';
              $tag_label = 'NEW';
          } elseif (is_numeric($stock_qty) && (int) $stock_qty >= 5) {
              $tag_class = '';
              $tag_label = 'IN STOCK · ' . (int) $stock_qty;
          } elseif ($product->is_in_stock()) {
              $tag_class = '';
              $tag_label = 'IN STOCK';
          } else {
              $tag_class = 'hot';
              $tag_label = 'OUT';
          }

          // Image — real product featured image, or fallback SVG
          $img_id = $product->get_image_id();
          $img    = $img_id ? wp_get_attachment_image($img_id, 'medium_large', false, ['class' => 'ng-product-img', 'alt' => esc_attr($name_en)]) : '';

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
          $cta_label = $product->is_type('simple') && $product->is_in_stock() ? 'ADD' : 'VIEW';
      ?>
      <article class="ng-product reveal">
        <div class="ng-product-head">
          <span class="sku"><?php echo esc_html( strtoupper( $sku ) ); ?></span>
          <?php if ( $tag_label ) : ?>
            <span class="tag <?php echo esc_attr( $tag_class ); ?>"><?php echo esc_html( $tag_label ); ?></span>
          <?php endif; ?>
        </div>

        <a class="ng-product-media" href="<?php echo esc_url( $perm ); ?>" aria-label="<?php echo esc_attr( $name_en ); ?>">
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
          <div class="en"><?php echo esc_html( $name_en ); ?></div>
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
            <div class="inc">VAT INC / SHIP 2-5D</div>
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
     SERVICE STRIP — "اكتب لنا مواصفاتك"
     ============================================================ -->
<section class="ng-section" id="ng-service">
  <div class="ng-service-strip reveal">
    <div class="ng-service-inner">
      <div class="ng-service-text">
        <div class="kicker-s"><span style="width:7px;height:7px;background:var(--signal);display:inline-block;box-shadow:0 0 12px var(--signal);"></span>BESPOKE / 03 · SERVICE DESK</div>
        <div class="en">TELL US THE SHAPE OF THE PROBLEM.</div>
        <div class="ar">اكتب لنا مواصفاتك. نرجع لك بخطة واضحة.</div>
        <p>
          شبكة لمكتب، تركيبة هوم لاب، بيت ذكي كامل، أو محطة ألعاب تنافسية — اشرح لنا الاحتياج، ونرد عليك بمخطط تنفيذ مفصّل، قائمة مكوّنات، وتقدير زمن الشحن والتركيب.
        </p>
      </div>
      <div class="ng-service-cta">
        <a class="btn btn-primary" href="<?php echo esc_url( $contact_url ); ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16v12H4zM4 6l8 7 8-7"/></svg>
          SEND SPEC BRIEF
        </a>
        <a class="btn btn-ghost" href="<?php echo esc_url( $whatsapp_url ); ?>" rel="noopener noreferrer" target="_blank">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20.5 3.5 3.5 11l7 2.5L13 20.5z"/></svg>
          WHATSAPP
        </a>
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
    <div class="ng-voice-kicker">// 04 · BRAND VOICE</div>
    <div class="ng-voice-ar">التقنية.<br>كما <span class="accent">يجب</span> أن تكون.</div>
    <div class="ng-voice-en">
      TECHNOLOGY
      <span class="sep"></span>
      AS IT SHOULD BE
      <span class="sep"></span>
      SHIPPED FROM KSA
    </div>
  </div>
</section>

<?php
get_footer();
