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

  <?php if ( ! empty( $attr_rows ) ) : ?>
  <section class="ng-single-attrs">
    <div class="ng-section-head">
      <div>
        <div class="ng-section-label">02 · <b>المواصفات الفنية</b></div>
        <h2 class="ng-section-h">المواصفات <span class="accent">الكاملة</span>.</h2>
      </div>
    </div>
    <dl class="ng-single-attr-table">
      <?php foreach ( $attr_rows as $row ) : ?>
        <div class="ng-single-attr-row">
          <dt><?php echo esc_html( $row['label'] ); ?></dt>
          <dd><?php echo esc_html( $row['value'] ); ?></dd>
        </div>
      <?php endforeach; ?>
    </dl>
  </section>
  <?php endif; ?>

  <?php if ($product->get_description()) : ?>
  <section class="ng-single-description">
    <div class="ng-section-head">
      <div>
        <div class="ng-section-label">03 · <b>تفاصيل المنتج</b></div>
        <h2 class="ng-section-h">ما <span class="accent">داخل</span> الوحدة.</h2>
      </div>
    </div>
    <div class="ng-single-desc-body">
      <?php
      // wp_kses_post strips <script>/<style>/etc from the raw stored
      // content (defence against malicious or compromised shop_manager
      // accounts that hold unfiltered_html on single-site WP). Then
      // apply the_content filter chain so autop, shortcodes, embeds,
      // and Gutenberg blocks still render normally.
      echo apply_filters('the_content', wp_kses_post($product->get_description()));
      ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="ng-single-related">
    <?php
    /**
     * Related products — renders via Woo's internal loop which calls
     * content-product.php, which our mu-plugin routes to the
     * .ng-product override. So related cards inherit the design.
     */
    woocommerce_output_related_products();
    ?>
  </section>

</article>
<?php
do_action('woocommerce_after_single_product');
