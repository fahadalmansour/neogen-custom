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
 * @version 9.4.0 (NeoGen reconciled against upstream WC 10.7.0)
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
      <span>02 · إتمام الطلب · تأكيد</span>
    </div>
    <div class="ng-checkout-head-row" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:24px;">
      <h1 class="ng-checkout-h1">
        <span class="ar">إتمام الطلب</span>
      </h1>
      <?php
      // v1.38.0 — 3-step progress indicator (Cart → Shipping → Payment).
      // Step 02 (shipping/checkout) is the active step here.
      ?>
      <nav class="ng-stepper" aria-label="مراحل الشراء">
        <span class="ng-step-pill" style="opacity:.4;">
          <span class="n">01</span><span class="l">السلة</span>
        </span>
        <span class="sep" aria-hidden="true"></span>
        <span class="ng-step-pill" aria-current="step">
          <span class="n">02</span><span class="l">الشحن</span>
        </span>
        <span class="sep" aria-hidden="true"></span>
        <span class="ng-step-pill" style="opacity:.4;">
          <span class="n">03</span><span class="l">الدفع</span>
        </span>
      </nav>
    </div>
    <div class="ng-checkout-pay-strip" aria-label="طرق الدفع المتاحة">
      <span class="ng-checkout-pay-label">// طرق الدفع</span>
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/pay/mada.svg' ); ?>"      width="42" height="18" alt="مدى" loading="lazy">
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/pay/apple-pay.svg' ); ?>" width="42" height="18" alt="Apple Pay" loading="lazy">
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/pay/stcpay.svg' ); ?>"    width="42" height="18" alt="STC Pay" loading="lazy">
      <img src="<?php echo esc_url( NG_THEME_ASSET_URL . '/img/pay/tabby.svg' ); ?>"     width="42" height="18" alt="Tabby" loading="lazy">
    </div>

    <?php
    // v1.38.0 — informational carrier-options preview. Shows the four
    // shipping options NeoGen offers across the GCC. The actual carrier
    // selection still happens in WooCommerce's native shipping-methods
    // radio (rendered inside review-order.php / order_review action) so
    // we don't break WC's AJAX cart-update or payment-gateway flows.
    $ng_carriers = [
        [ 'logo' => '📦', 'name' => 'Aramex Standard', 'price' => '25 SAR', 'eta' => '2–5 أيام عمل',  'rating' => '4.7★', 'features' => [ 'تتبع مباشر', 'SMS تنبيهات' ] ],
        [ 'logo' => '⚡', 'name' => 'SMSA Express',    'price' => '35 SAR', 'eta' => '1–2 يوم عمل',   'rating' => '4.8★', 'features' => [ 'تسليم سريع', 'تتبع مباشر', 'واتساب' ] ],
        [ 'logo' => '🌍', 'name' => 'DHL Express GCC', 'price' => '75 SAR', 'eta' => '1–3 أيام خليج', 'rating' => '4.9★', 'features' => [ 'شحن GCC', 'تتبع دقيق', 'مضمون' ] ],
        [ 'logo' => '🏠', 'name' => 'نفس اليوم',       'price' => '55 SAR', 'eta' => 'اليوم قبل 9م',  'rating' => '4.6★', 'features' => [ 'الرياض فقط', 'تحديد الوقت' ], 'badge' => 'الرياض فقط' ],
    ];
    ?>
    <div class="ng-carriers-preview" style="margin-top:24px;">
      <div class="mono-up" style="color:var(--ink-4);margin-bottom:12px;">شركات الشحن المتاحة · CARRIERS</div>
      <div class="ng-carrier-picker" role="list" aria-label="معاينة شركات الشحن">
        <?php foreach ( $ng_carriers as $i => $c ) : ?>
          <div class="ng-carrier-card" role="listitem"<?php echo $i === 0 ? ' data-active="true"' : ''; ?>>
            <span class="radio" aria-hidden="true"></span>
            <span class="logo" aria-hidden="true"><?php echo esc_html( $c['logo'] ); ?></span>
            <div class="body">
              <div class="row">
                <div class="name">
                  <?php echo esc_html( $c['name'] ); ?>
                  <?php if ( ! empty( $c['badge'] ) ) : ?>
                    <span class="chip chip-sale" style="font-size:8px;"><?php echo esc_html( $c['badge'] ); ?></span>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="price"><?php echo esc_html( $c['price'] ); ?></div>
                  <div class="eta"><?php echo esc_html( $c['eta'] ); ?></div>
                </div>
              </div>
              <div class="rating"><?php echo esc_html( $c['rating'] ); ?></div>
              <div class="features">
                <?php foreach ( $c['features'] as $f ) : ?>
                  <span class="f"><?php echo esc_html( $f ); ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="font-size:11px;color:var(--ink-4);margin:10px 0 0;font-family:var(--font-mono);">
        // اختر طريقة الشحن من نموذج الطلب أدناه — تُحسب الأسعار بناءً على المدينة والوزن.
      </p>
    </div>
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
          <span>// سجل الطلب</span>
          <span>مراجعة</span>
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
