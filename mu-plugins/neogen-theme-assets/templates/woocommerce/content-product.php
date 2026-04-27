<?php
/**
 * NeoGen override for WooCommerce's loop product card.
 *
 * Routed here by the wc_get_template_part filter in
 * mu-plugins/neogen-theme.php. Rendered once per product in archive,
 * category, tag, search, and [products] shortcode loops.
 *
 * Emits the same .ng-product markup used by the homepage Operator
 * Picks card so archive + homepage read as one visual system.
 *
 * Standard outer hooks are fired so plugins that wrap the whole
 * card (wishlist icons, swatches, etc.) still register. Inner
 * hooks (image/title/price/cart inside the default card) are NOT
 * called — we render those ourselves.
 *
 * @var WC_Product $product
 * @version 9.4.0 (NeoGen reconciled against upstream WC 10.7.0)
 */

defined('ABSPATH') || exit;

global $product;

if (empty($product) || !$product->is_visible()) {
    return;
}

$id      = $product->get_id();
$sku     = $product->get_sku();
if (!$sku) { $sku = 'NG-' . $id; }
$name_en = $product->get_name();
$name_ar = get_post_meta($id, '_ng_ar_title', true);
if (!$name_ar) { $name_ar = $name_en; }
if (function_exists('ng_gift_card_clean_product_name')) {
    $name_en = ng_gift_card_clean_product_name($name_en);
    $name_ar = ng_gift_card_clean_product_name($name_ar);
}
$perm    = get_permalink($id);

// Stock / freshness tag.
$stock_qty  = $product->get_stock_quantity();
$created_ts = get_post_time('U', true, $id);
$is_new     = $created_ts && (time() - $created_ts) < 30 * DAY_IN_SECONDS;

$tag_class = '';
$tag_label = '';
if (is_numeric($stock_qty) && (int) $stock_qty > 0 && (int) $stock_qty < 5) {
    $tag_class = 'hot';
    $tag_label = 'مخزون منخفض · ' . (int) $stock_qty;
} elseif ($is_new) {
    $tag_class = 'new';
    $tag_label = 'جديد';
} elseif (is_numeric($stock_qty) && (int) $stock_qty >= 5) {
    $tag_label = 'متوفّر · ' . (int) $stock_qty;
} elseif ($product->is_in_stock()) {
    $tag_label = 'متوفّر';
} else {
    $tag_class = 'hot';
    $tag_label = 'نفد';
}

// Featured image — real first, SVG placeholder fallback.
$img_id = $product->get_image_id();
$img    = $img_id
    ? wp_get_attachment_image($img_id, 'woocommerce_thumbnail', false, [
        'class' => 'ng-product-img',
        'alt'   => esc_attr($name_en),
    ])
    : '';
if (function_exists('ng_gift_card_image_html')) {
    $gift_img = ng_gift_card_image_html($product, 'woocommerce_thumbnail', $name_en, null, ['class' => 'ng-product-img']);
    if ($gift_img) {
        $img = $gift_img;
    }
}

// Up to four spec chips — prefer product attributes, pad with tags.
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
        foreach ($tag_terms as $t) {
            $specs[] = $t;
            if (count($specs) >= 4) { break; }
        }
    }
}

$price_raw  = $product->get_price();
$price_html = $product->get_price_html();

$cta_url   = $product->is_type('simple') && $product->is_in_stock()
    ? esc_url($product->add_to_cart_url())
    : esc_url($perm);
$cta_label = $product->is_type('simple') && $product->is_in_stock() ? 'أضف للسلة' : 'عرض';

// AR/EN dedup — if the cleaned Arabic title equals the cleaned English
// title (common for SKUs like "Steam 50 USD" where there's no Arabic
// translation), only emit the AR line. Otherwise show both stacked.
$show_en_title = trim((string) $name_en) !== '' && trim((string) $name_ar) !== trim((string) $name_en);

// Outer hook so plugins that wrap the card still run. Inner hooks
// deliberately skipped — we own the markup.
do_action('woocommerce_before_shop_loop_item');
?>
<li <?php wc_product_class('ng-product reveal', $product); ?>>
  <div class="ng-product-head">
    <span class="sku"><?php echo esc_html(strtoupper($sku)); ?></span>
    <?php if ($tag_label) : ?>
      <span class="tag <?php echo esc_attr($tag_class); ?>"><?php echo esc_html($tag_label); ?></span>
    <?php endif; ?>
  </div>

  <a class="ng-product-media" href="<?php echo esc_url($perm); ?>" aria-label="<?php echo esc_attr($name_en); ?>">
    <?php if ($img) :
        echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_get_attachment_image returns safe HTML.
    else : ?>
      <svg class="placeholder" viewBox="0 0 200 120" fill="none" stroke="currentColor" stroke-width="1.4">
        <rect x="30" y="20" width="140" height="80" rx="6"/>
        <circle cx="100" cy="60" r="18"/>
        <path d="M100 46v28M86 60h28"/>
      </svg>
    <?php endif; ?>
  </a>

  <?php
  /*
   * Upstream WC fires three title-position hooks. We don't render
   * Woo's default title (we have our own AR/EN markup below), but we
   * fire the hooks at the correct positions so 3rd-party plugins
   * that attach badges, ribbons, or annotations (Yoast SEO, WC GLA
   * compliance markers, sale-flash, conversion pixels) still get
   * their attach point. We intentionally do NOT fire
   * `woocommerce_shop_loop_item_title` itself, because that emits
   * the default <h2> which would duplicate our title block below.
   */
  do_action( 'woocommerce_before_shop_loop_item_title' );
  ?>
  <div class="ng-product-title">
    <div class="ar"><?php echo esc_html($name_ar); ?></div>
    <?php if ($show_en_title) : ?>
      <div class="en"><?php echo esc_html($name_en); ?></div>
    <?php endif; ?>
  </div>
  <?php do_action( 'woocommerce_after_shop_loop_item_title' ); ?>

  <?php if (!empty($specs)) : ?>
  <div class="ng-product-specs">
    <?php foreach ($specs as $s) : ?>
      <span class="s"><?php echo esc_html($s); ?></span>
    <?php endforeach; ?>
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
</li>
<?php
do_action('woocommerce_after_shop_loop_item');
