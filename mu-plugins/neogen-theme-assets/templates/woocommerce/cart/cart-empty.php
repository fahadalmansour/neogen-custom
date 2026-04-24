<?php
/**
 * NeoGen override for empty-cart state.
 *
 * Routed via wc_get_template filter map. Replaces Woo's default
 * "Your cart is currently empty" notice with an operator-console
 * "NO ITEMS IN QUEUE" surface plus suggestion strip.
 */

defined('ABSPATH') || exit;

$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
$home_url = home_url('/');
?>

<section class="ng-cart ng-cart--empty">

  <div class="ng-cart-empty-bg" aria-hidden="true">
    <svg viewBox="-50 -50 100 100">
      <path d="M0 -44 L9 -26 L35 -35 L26 -9 L44 0 L26 9 L35 35 L9 26 L0 44 L-9 26 L-35 35 L-26 9 L-44 0 L-26 -9 L-35 -35 L-9 -26 Z"/>
    </svg>
  </div>

  <div class="ng-cart-empty-inner">
    <div class="ng-cart-kicker">
      <span class="led" aria-hidden="true"></span>
      <span>QUEUE STATUS</span>
      <span class="sep"></span>
      <span class="alert">EMPTY</span>
    </div>

    <h1 class="ng-cart-empty-h1">
      <span class="ar">السلة فارغة</span>
      <span class="en">NO ITEMS IN QUEUE</span>
    </h1>

    <p class="ng-cart-empty-lede">
      <span class="ar">لم تضف أي منتج للسلة بعد. تصفح الرفوف لاختيار ما يناسبك.</span>
      <span class="en">Nothing in the cart yet. Browse the racks to pick your gear.</span>
    </p>

    <?php do_action('woocommerce_cart_is_empty'); ?>

    <div class="ng-cart-empty-ctas">
      <a class="btn btn-primary" href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect', $shop_url)); ?>">
        <?php esc_html_e('Browse the shop', 'woocommerce'); ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
      </a>
      <a class="btn btn-ghost" href="<?php echo esc_url($home_url); ?>">RETURN HOME</a>
    </div>
  </div>

  <?php
  // Recommendations strip — based on recently-viewed cookie. Even
  // an empty cart visitor probably has a few products in their recent
  // history, so offer them next-picks here too.
  echo do_shortcode('[neogen_recommendations limit="4" title_ar="مقترحات لك" title_en="OPERATOR · NEXT PICKS"]');
  ?>

</section>
