<?php
/**
 * NeoGen override for the checkout-page wrapper.
 *
 * IMPORTANT — minimal-risk override. We only restructure the OUTER
 * 2-column layout (customer details left, order review aside right)
 * and ADD .ng-checkout-* class wrappers for theming. We do NOT
 * override review-order.php or payment.php — those have gateway-
 * injected hooks (Mada / Apple Pay / STC / Tabby) that must render
 * with their default markup so payment JS keeps working.
 *
 * Every Woo do_action() call is preserved verbatim so plugins that
 * inject anything (login form, coupon row, fields, payment methods,
 * place-order button) still register.
 *
 * @var WC_Checkout $checkout
 */

defined('ABSPATH') || exit;

// If the cart is empty, Woo bails before this template loads.
do_action('woocommerce_before_checkout_form', $checkout);

// If checkout registration is disabled and not logged in, the user
// cannot checkout — Woo's notice handles that case.
if (!$checkout->is_registration_enabled() && $checkout->is_registration_required() && !is_user_logged_in()) {
    echo esc_html(apply_filters('woocommerce_checkout_must_be_logged_in_message', __('You must be logged in to checkout.', 'woocommerce')));
    return;
}
?>

<section class="ng-checkout">

  <header class="ng-checkout-head">
    <div class="ng-checkout-kicker">
      <span class="led on" aria-hidden="true"></span>
      <span>02 · CHECKOUT · COMMIT ORDER</span>
    </div>
    <h1 class="ng-checkout-h1">
      <span class="ar">إتمام الطلب</span>
      <span class="en">CHECKOUT</span>
    </h1>
  </header>

  <form name="checkout" method="post" class="checkout woocommerce-checkout ng-checkout-form" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data" aria-label="<?php esc_attr_e('Checkout', 'woocommerce'); ?>">

    <div class="ng-checkout-body">

      <?php if ($checkout->get_checkout_fields()) : ?>
        <div class="ng-checkout-details">
          <?php do_action('woocommerce_checkout_before_customer_details'); ?>

          <div class="col2-set" id="customer_details">
            <div class="col-1">
              <?php do_action('woocommerce_checkout_billing'); ?>
            </div>
            <div class="col-2">
              <?php do_action('woocommerce_checkout_shipping'); ?>
            </div>
          </div>

          <?php do_action('woocommerce_checkout_after_customer_details'); ?>
        </div>
      <?php endif; ?>

      <aside class="ng-checkout-aside">
        <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>

        <div class="ng-checkout-review-head">
          <span>// ORDER LEDGER</span>
          <span><?php esc_html_e('Review', 'woocommerce'); ?></span>
        </div>

        <h3 id="order_review_heading" class="screen-reader-text"><?php esc_html_e('Your order', 'woocommerce'); ?></h3>

        <?php do_action('woocommerce_checkout_before_order_review'); ?>

        <div id="order_review" class="woocommerce-checkout-review-order ng-checkout-review">
          <?php do_action('woocommerce_checkout_order_review'); ?>
        </div>

        <?php do_action('woocommerce_checkout_after_order_review'); ?>
      </aside>

    </div>

  </form>

</section>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>
