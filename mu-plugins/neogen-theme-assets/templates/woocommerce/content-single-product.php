<?php
/**
 * NeoGen override for WooCommerce single-product page body.
 *
 * Routed here by the wc_get_template_part filter in
 * mu-plugins/neogen-theme.php — called via wc_get_template_part('content','single-product').
 *
 * Layout:
 *   +--------------------------------------------+
 *   | ng-single-top (kicker + breadcrumb row)    |
 *   +--------------------------------------------+
 *   | gallery (60%) | summary (40%)              |
 *   |               | ng-single-brief aside      |
 *   +--------------------------------------------+
 *   | ng-single-description (flattened from tabs)|
 *   +--------------------------------------------+
 *   | related products (inherits .ng-product)    |
 *   +--------------------------------------------+
 * @version 3.6.0 (NeoGen reconciled against upstream WC 10.7.0)
 */

defined('ABSPATH') || exit;

global $product;
if (empty($product)) { return; }

do_action('woocommerce_before_single_product');

if (post_password_required()) {
    echo get_the_password_form();
    return;
}

$id         = $product->get_id();
$sku        = $product->get_sku();
if (!$sku) { $sku = 'NG-' . $id; }
$name_full  = $product->get_name();
$name_ar    = get_post_meta($id, '_ng_ar_title', true);
if (!$name_ar) {
    $name_ar = function_exists('ng_ar_label') ? ng_ar_label( $name_full ) : $name_full;
}
if (function_exists('ng_gift_card_clean_product_name')) {
    $name_full = ng_gift_card_clean_product_name($name_full);
    $name_ar   = ng_gift_card_clean_product_name($name_ar);
}
$price_html = $product->get_price_html();
$stock_qty  = $product->get_stock_quantity();

$stock_class = 'on';
$stock_label = 'متوفّر';
if (!$product->is_in_stock()) {
    $stock_class = 'warn';
    $stock_label = 'نفد';
} elseif (is_numeric($stock_qty) && (int) $stock_qty > 0 && (int) $stock_qty < 5) {
    $stock_class = 'warn';
    $stock_label = 'مخزون منخفض · ' . (int) $stock_qty;
} elseif (is_numeric($stock_qty)) {
    $stock_label = (int) $stock_qty . ' وحدة';
}

$cats = wp_get_post_terms($id, 'product_cat');
if (is_wp_error($cats)) { $cats = []; }
?>
<article id="product-<?php echo esc_attr($id); ?>" <?php wc_product_class('ng-single-product', $product); ?>>

  <header class="ng-single-top">
    <div class="ng-single-kicker">
      <span class="led on"></span>
      <span>وحدة المشغّل · التفاصيل</span>
      <?php if (!empty($cats)) : $first_cat = $cats[0]; $link = get_term_link($first_cat); $cat_name = function_exists('ng_ar_label') ? ng_ar_label($first_cat->name) : $first_cat->name; ?>
        <span class="sep"></span>
        <a href="<?php echo esc_url(is_wp_error($link) ? '#' : $link); ?>"><?php echo esc_html($cat_name); ?></a>
      <?php endif; ?>
      <span class="sep"></span>
      <span class="sku"><?php echo esc_html(strtoupper($sku)); ?></span>
    </div>

    <h1 class="ng-single-h1">
      <span class="ar"><?php echo esc_html($name_ar); ?></span>
    </h1>
  </header>

  <div class="ng-single-inner">

    <div class="ng-single-gallery">
      <?php
      /**
       * Gallery — Woo handles zoom, flexslider, lightbox JS. Wrapping div
       * is emitted by the function call below.
       */
      do_action('woocommerce_before_single_product_summary');
      ?>
    </div>

    <aside class="ng-single-summary">

      <div class="ng-single-price">
        <?php echo wp_kses_post($price_html); ?>
        <div class="inc">شامل الضريبة · شحن 2-5 أيام</div>
      </div>

      <div class="ng-single-stock ng-single-stock--<?php echo esc_attr($stock_class); ?>">
        <span class="led"></span>
        <span class="label"><?php echo esc_html($stock_label); ?></span>
      </div>

      <?php if ($product->get_short_description()) : ?>
      <div class="ng-single-excerpt">
        <?php echo wp_kses_post(wpautop($product->get_short_description())); ?>
      </div>
      <?php endif; ?>

      <div class="ng-single-cart">
        <?php
        /**
         * Native Woo add-to-cart form. Handles simple / variable /
         * grouped / external product types correctly. Styled via CSS
         * (.ng-single-cart .single_add_to_cart_button etc.).
         */
        woocommerce_template_single_add_to_cart();
        ?>
      </div>

      <div class="ng-single-brief" aria-label="بطاقة المواصفات">
        <div class="ng-brief-head">
          <span>// بطاقة المواصفات</span>
          <span><?php echo esc_html(strtoupper(date_i18n('M Y'))); ?></span>
        </div>
        <div class="ng-brief-row">
          <span class="k">الرمز</span>
          <span class="v"><?php echo esc_html(strtoupper($sku)); ?></span>
          <span class="t">مباشر</span>
        </div>
        <?php if (is_numeric($stock_qty)) : ?>
        <div class="ng-brief-row">
          <span class="k">المخزون</span>
          <span class="v"><?php echo esc_html((int) $stock_qty); ?> وحدة</span>
          <span class="t <?php echo $stock_qty < 5 ? 'warn' : ''; ?>"><?php echo $stock_qty < 5 ? 'منخفض' : 'متوفّر'; ?></span>
        </div>
        <?php endif; ?>
        <div class="ng-brief-row">
          <span class="k">الشحن</span>
          <span class="v">2-5 أيام · مدن المملكة</span>
          <span class="t">جاهز</span>
        </div>
        <div class="ng-brief-row">
          <span class="k">الضمان</span>
          <span class="v">12 شهر · دعم بالعربية</span>
          <span class="t">24/7</span>
        </div>
        <div class="ng-brief-row">
          <span class="k">الدفع</span>
          <span class="v">مدى · Apple Pay · STC · Tabby</span>
          <span class="t">PCI</span>
        </div>
      </div>

      <?php
      /**
       * Standard meta: categories, tags. Woo's default markup works
       * inside our aside with the CSS overrides below.
       */
      ?>
      <div class="ng-single-meta">
        <?php woocommerce_template_single_meta(); ?>
      </div>

    </aside>
  </div>

  <?php
  // Build a 2-column attributes table from product attributes.
  $attrs = $product->get_attributes();
  $attr_rows = [];
  if ( ! empty( $attrs ) ) {
      foreach ( $attrs as $attr ) {
          if ( ! $attr instanceof WC_Product_Attribute ) continue;
          if ( ! $attr->get_visible() ) continue;
          $vals = $attr->is_taxonomy()
              ? wp_get_post_terms( $id, $attr->get_name(), [ 'fields' => 'names' ] )
              : $attr->get_options();
          if ( is_wp_error( $vals ) || empty( $vals ) ) continue;
          $label = $attr->is_taxonomy()
              ? wc_attribute_label( $attr->get_name() )
              : $attr->get_name();
          $attr_rows[] = [
              'label' => $label,
              'value' => is_array( $vals ) ? implode( ' · ', $vals ) : (string) $vals,
          ];
      }
  }
  ?>

  <?php
  /**
   * v1.38.0 — Redesign Phase 1: PDP refresh.
   *
   * Render order below the gallery + summary:
   *   A. Works Best With  — 4 companion product cards with a generic
   *      "why compatible" hint, fed by ng_recommended_products() so we
   *      reuse the existing recommendation engine instead of building
   *      a parallel one.
   *   B. Add-ons & Replacements — driven by per-product _ng_addons
   *      meta (see mu-plugins/neogen-addons.php). Empty meta = section
   *      hidden, so this is a no-op for products without curated addons.
   *   C. Tabbed content — Description / Specs / Reviews / Shipping —
   *      the existing flat sections are preserved as tab panel bodies
   *      so all content remains discoverable; tabs just give the user
   *      a faster way to jump between them.
   *   D. Related products — kept (Woo's internal loop, themed via
   *      content-product.php override).
   */
  $ng_compat_default = 'يعمل بشكل أفضل عند تشغيله مع هذه الوحدة المختارة من نفس الفئة.';
  $ng_works_with     = function_exists( 'ng_recommended_products' )
      ? ng_recommended_products( $id, 4 )
      : [];
  $ng_addons_html    = function_exists( 'ng_render_addons' ) ? ng_render_addons( $id ) : '';
  ?>

  <?php if ( ! empty( $ng_works_with ) ) :
      // Suppress the auto-rec-strip in neogen-recommendations.php so we
      // don't double-render the same products below the article.
      if ( ! defined( 'NG_WORKS_BEST_RENDERED' ) ) { define( 'NG_WORKS_BEST_RENDERED', true ); }
  ?>
  <section class="ng-works-best-with" aria-labelledby="ng-works-heading-<?php echo (int) $id; ?>">
    <div class="head">
      <div>
        <div class="ng-section-label">A · <b>يعمل بشكل أفضل مع</b> · WORKS BEST WITH</div>
        <h2 id="ng-works-heading-<?php echo (int) $id; ?>" class="ng-section-h" style="font-size:20px;margin:8px 0 0;">الأجهزة الموصى بها للاستخدام مع هذا المنتج</h2>
      </div>
      <a class="more" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">كل التوافقات &rarr;</a>
    </div>
    <div class="ng-works-grid">
      <?php foreach ( $ng_works_with as $w ) :
          $w_id    = $w->get_id();
          $w_sku   = $w->get_sku() ?: 'NG-' . $w_id;
          $w_name  = $w->get_name();
          $w_ar    = (string) get_post_meta( $w_id, '_ng_ar_title', true );
          if ( $w_ar === '' ) { $w_ar = $w_name; }
          if ( function_exists( 'ng_gift_card_clean_product_name' ) ) {
              $w_name = ng_gift_card_clean_product_name( $w_name );
              $w_ar   = ng_gift_card_clean_product_name( $w_ar );
          }
          $w_show_en = trim( (string) $w_name ) !== '' && trim( (string) $w_ar ) !== trim( (string) $w_name );
          $w_perm    = get_permalink( $w_id );
          $w_price   = $w->get_price_html();
          $w_img_id  = $w->get_image_id();
          $w_img     = $w_img_id
              ? wp_get_attachment_image( $w_img_id, 'woocommerce_thumbnail', false, [ 'alt' => esc_attr( $w_name ) ] )
              : '';
      ?>
        <article class="ng-works-card">
          <a class="media" href="<?php echo esc_url( $w_perm ); ?>" aria-label="<?php echo esc_attr( $w_name ); ?>">
            <?php if ( $w_img ) {
                echo $w_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_get_attachment_image returns safe HTML
            } else { ?>
              <span class="ng-r1-ph-label"><?php echo esc_html( strtoupper( $w_sku ) ); ?></span>
            <?php } ?>
          </a>
          <div class="body">
            <div class="sku"><?php echo esc_html( strtoupper( $w_sku ) ); ?></div>
            <a class="ar" href="<?php echo esc_url( $w_perm ); ?>" style="color:inherit;text-decoration:none;display:block;"><?php echo esc_html( $w_ar ); ?></a>
            <?php if ( $w_show_en ) : ?>
              <span class="en"><?php echo esc_html( $w_name ); ?></span>
            <?php endif; ?>
            <div class="why"><?php echo esc_html( $ng_compat_default ); ?></div>
            <div class="foot">
              <div class="price"><?php echo wp_kses_post( $w_price ); ?></div>
              <a class="btn btn-sm" href="<?php echo esc_url( $w_perm ); ?>" style="font-size:11px;padding:6px 10px;">عرض ←</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ( $ng_addons_html ) {
      // ng_render_addons() returns escaped/structured HTML; output as-is.
      echo $ng_addons_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — internal-render trusted output.
  } ?>

  <?php
  /*
   * Tabbed content. Preserve existing description / attrs sections inside
   * tab panels so screen readers and search engines still find the same
   * markup; the tabs are a presentational layer over them.
   */
  $ng_has_attrs = ! empty( $attr_rows );
  $ng_has_desc  = (bool) $product->get_description();
  if ( $ng_has_attrs || $ng_has_desc ) :
  ?>
  <section class="ng-pdp-tabs">
    <div class="ng-pdp-tablist" role="tablist" aria-label="تفاصيل المنتج" data-ng-pdp-tabs>
      <?php if ( $ng_has_desc ) : ?>
        <button class="ng-pdp-tab" role="tab" id="ng-tab-desc-<?php echo (int) $id; ?>" aria-controls="ng-panel-desc-<?php echo (int) $id; ?>" aria-selected="true" tabindex="0" type="button">الوصف</button>
      <?php endif; ?>
      <?php if ( $ng_has_attrs ) : ?>
        <button class="ng-pdp-tab" role="tab" id="ng-tab-specs-<?php echo (int) $id; ?>" aria-controls="ng-panel-specs-<?php echo (int) $id; ?>" aria-selected="<?php echo $ng_has_desc ? 'false' : 'true'; ?>" tabindex="<?php echo $ng_has_desc ? '-1' : '0'; ?>" type="button">المواصفات الكاملة</button>
      <?php endif; ?>
      <?php if ( comments_open() || get_comments_number() ) : ?>
        <button class="ng-pdp-tab" role="tab" id="ng-tab-rev-<?php echo (int) $id; ?>" aria-controls="ng-panel-rev-<?php echo (int) $id; ?>" aria-selected="false" tabindex="-1" type="button">المراجعات<?php $rc = (int) get_comments_number( $id ); echo $rc ? ' (' . $rc . ')' : ''; ?></button>
      <?php endif; ?>
      <button class="ng-pdp-tab" role="tab" id="ng-tab-ship-<?php echo (int) $id; ?>" aria-controls="ng-panel-ship-<?php echo (int) $id; ?>" aria-selected="false" tabindex="-1" type="button">الشحن والإرجاع</button>
    </div>

    <?php if ( $ng_has_desc ) : ?>
    <div class="ng-pdp-panel ng-single-desc-body" id="ng-panel-desc-<?php echo (int) $id; ?>" role="tabpanel" aria-labelledby="ng-tab-desc-<?php echo (int) $id; ?>" data-active="true">
      <?php
      // v1.37.2: prefer Arabic body from _ng_ar_description when set.
      $ng_ar_desc = trim( (string) get_post_meta( $id, '_ng_ar_description', true ) );
      $ng_body    = $ng_ar_desc !== '' ? $ng_ar_desc : (string) $product->get_description();
      echo apply_filters( 'the_content', wp_kses_post( $ng_body ) );
      ?>
    </div>
    <?php endif; ?>

    <?php if ( $ng_has_attrs ) : ?>
    <div class="ng-pdp-panel" id="ng-panel-specs-<?php echo (int) $id; ?>" role="tabpanel" aria-labelledby="ng-tab-specs-<?php echo (int) $id; ?>" data-active="<?php echo $ng_has_desc ? 'false' : 'true'; ?>"<?php echo $ng_has_desc ? ' aria-hidden="true"' : ''; ?>>
      <dl class="ng-single-attr-table">
        <?php foreach ( $attr_rows as $row ) : ?>
          <div class="ng-single-attr-row">
            <dt><?php echo esc_html( $row['label'] ); ?></dt>
            <dd><?php echo esc_html( $row['value'] ); ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </div>
    <?php endif; ?>

    <?php if ( comments_open() || get_comments_number() ) : ?>
    <div class="ng-pdp-panel" id="ng-panel-rev-<?php echo (int) $id; ?>" role="tabpanel" aria-labelledby="ng-tab-rev-<?php echo (int) $id; ?>" data-active="false" aria-hidden="true">
      <?php comments_template(); ?>
    </div>
    <?php endif; ?>

    <div class="ng-pdp-panel" id="ng-panel-ship-<?php echo (int) $id; ?>" role="tabpanel" aria-labelledby="ng-tab-ship-<?php echo (int) $id; ?>" data-active="false" aria-hidden="true">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
        <div style="background:var(--surface);border:1px solid var(--rule);border-radius:var(--r-2);overflow:hidden;">
          <div style="padding:12px 16px;background:var(--surface-2);border-bottom:1px solid var(--rule);">
            <span class="mono-up" style="color:var(--ink-4);">الشحن والتوصيل</span>
          </div>
          <?php foreach ( [
              [ 'الرياض',   '1–2 يوم · Aramex' ],
              [ 'المملكة',  '2–5 أيام · SMSA' ],
              [ 'خليج GCC', '3–7 أيام · DHL' ],
              [ 'مجاني',    'فوق 500 SAR' ],
          ] as $i => $row ) : ?>
            <div style="display:grid;grid-template-columns:1fr 1.5fr;padding:12px 16px;<?php echo $i < 3 ? 'border-bottom:1px dashed var(--rule);' : ''; ?>">
              <span class="mono-up" style="color:var(--dim);font-size:9px;"><?php echo esc_html( $row[0] ); ?></span>
              <span style="font-size:13px;color:var(--ink-2);"><?php echo esc_html( $row[1] ); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="background:var(--surface);border:1px solid var(--rule);border-radius:var(--r-2);overflow:hidden;">
          <div style="padding:12px 16px;background:var(--surface-2);border-bottom:1px solid var(--rule);">
            <span class="mono-up" style="color:var(--ink-4);">الإرجاع والاستبدال</span>
          </div>
          <?php foreach ( [
              [ 'مدة الإرجاع', '14 يوم من الاستلام' ],
              [ 'الحالة',       'غير مستخدم · عبوة أصلية' ],
              [ 'التواصل',      'support@neogen.store' ],
              [ 'استرداد',      '5–7 أيام عمل' ],
          ] as $i => $row ) : ?>
            <div style="display:grid;grid-template-columns:1fr 1.5fr;padding:12px 16px;<?php echo $i < 3 ? 'border-bottom:1px dashed var(--rule);' : ''; ?>">
              <span class="mono-up" style="color:var(--dim);font-size:9px;"><?php echo esc_html( $row[0] ); ?></span>
              <span style="font-size:13px;color:var(--ink-2);"><?php echo esc_html( $row[1] ); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; // tabs wrapper ?>

  <section class="ng-single-related">
    <?php
    /**
     * Related products — Woo's internal loop, themed via the
     * content-product.php override.
     */
    woocommerce_output_related_products();
    ?>
  </section>

</article>
<?php
do_action('woocommerce_after_single_product');
