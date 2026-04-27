<?php
/**
 * NeoGen override for WooCommerce's thank-you / order-received page.
 *
 * Routed here by the wc_get_template filter in neogen-theme.php.
 *
 * @var WC_Order|bool $order  Passed in by Woo.
 * @version 8.1.0  // upstream WC 10.7.0
 */

defined('ABSPATH') || exit;

/** @var WC_Order|false $order */
if (!isset($order) || !$order instanceof WC_Order) {
    // Mimic Woo's early-exit so downstream "no order" path still works.
    ?>
    <section class="ng-thankyou ng-thankyou--blank">
      <div class="ng-thankyou-inner">
        <div class="ng-kicker"><span></span>NO ORDER CONTEXT</div>
        <h1 class="ng-thankyou-h1"><span class="ar">لم نجد طلبك.</span><span class="en">WE COULDN'T LOCATE YOUR ORDER.</span></h1>
        <p class="ng-thankyou-lede">Your session may have expired, or the order-received link was opened outside its intended context.</p>
        <a class="btn btn-primary" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/')); ?>">GO TO MY ACCOUNT</a>
      </div>
    </section>
    <?php
    return;
}

$status       = $order->get_status();          // pending | on-hold | processing | completed | cancelled | refunded | failed
$order_id_h   = $order->get_order_number();
$order_date   = wc_format_datetime($order->get_date_created(), 'd M Y · H:i');
$currency     = $order->get_currency();
$payment_t    = $order->get_payment_method_title();
$items        = $order->get_items();
$items_count  = 0;
foreach ($items as $it) { $items_count += (int) $it->get_quantity(); }

$ship_addr    = trim($order->get_formatted_shipping_address());
$bill_addr    = trim($order->get_formatted_billing_address());
$addr         = $ship_addr ?: $bill_addr;

// Pipeline — maps order status to lit nodes. Mapping:
//   pending / on-hold / failed  -> 1 lit   (RECEIVED only)
//   processing                  -> 2 lit   (+ PAYMENT)
//   completed                   -> 4 lit   (+ PACKING + SHIPPED)
//   cancelled / refunded        -> all dim, plus a RED alert overlay
$pipeline = [
    ['RECEIVED',  'تم استلام الطلب'],
    ['PAYMENT',   'الدفع'],
    ['PACKING',   'التجهيز'],
    ['SHIPPED',   'الشحن'],
];
$lit = 1;
$alert = false;
switch ($status) {
    case 'processing':             $lit = 2; break;
    case 'completed':              $lit = 4; break;
    case 'cancelled':
    case 'refunded':
    case 'failed':                 $lit = 0; $alert = true; break;
    default:                       $lit = 1; break;
}

$status_copy = [
    'received'   => ['AR' => 'تم استلام الطلب بنجاح.', 'EN' => 'TRANSMISSION RECEIVED.'],
    'processing' => ['AR' => 'الدفع مؤكد. جاري تجهيز الطلب.', 'EN' => 'PAYMENT CLEARED. ORDER QUEUED FOR PACKING.'],
    'completed'  => ['AR' => 'تم الشحن. تابع رقم الشحنة من حسابك.', 'EN' => 'PACKAGE ROUTED. TRACK FROM YOUR ACCOUNT.'],
    'failed'     => ['AR' => 'لم يكتمل الدفع.', 'EN' => 'PAYMENT DID NOT COMPLETE.'],
    'cancelled'  => ['AR' => 'تم إلغاء الطلب.', 'EN' => 'ORDER CANCELLED.'],
    'refunded'   => ['AR' => 'تم استرداد المبلغ.', 'EN' => 'AMOUNT REFUNDED.'],
];
$copy_key = in_array($status, ['completed','processing','failed','cancelled','refunded'], true) ? $status : 'received';
$copy     = $status_copy[$copy_key];

$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/');
$orders_url  = $order->get_view_order_url();

/*
 * Upstream WC fires woocommerce_before_thankyou here. Plugins like
 * Stripe/Tabby/STC analytics and conversion-pixel injectors hook in.
 * Skipping it would silently break payment-status hand-off banners
 * and conversion tracking.
 */
do_action( 'woocommerce_before_thankyou', $order->get_id() );
?>

<section class="ng-thankyou<?php echo $alert ? ' ng-thankyou--alert' : ''; ?>">

  <div class="ng-thankyou-bg" aria-hidden="true">
    <svg viewBox="-50 -50 100 100">
      <path d="M0 -44 L9 -26 L35 -35 L26 -9 L44 0 L26 9 L35 35 L9 26 L0 44 L-9 26 L-35 35 L-26 9 L-44 0 L-26 -9 L-35 -35 L-9 -26 Z"/>
    </svg>
  </div>

  <div class="ng-thankyou-inner">

    <div class="ng-thankyou-kicker">
      <span class="led <?php echo $alert ? 'warn' : 'on'; ?>"></span>
      <span>ORDER <?php echo esc_html(sprintf('#%s', $order_id_h)); ?> <span class="sep"></span> <?php echo esc_html(strtoupper($order_date)); ?> UTC+3</span>
    </div>

    <h1 class="ng-thankyou-h1">
      <span class="ar"><?php echo esc_html($copy['AR']); ?></span>
      <span class="en"><?php echo esc_html($copy['EN']); ?></span>
    </h1>

    <!-- PIPELINE -->
    <ol class="ng-pipeline" aria-label="Order pipeline">
      <?php foreach ($pipeline as $i => $node) :
          $state = $i < $lit ? 'on' : 'off';
          if ($i === $lit - 1) { $state = 'active'; }
          if ($alert) { $state = 'off'; }
      ?>
      <li class="ng-pipeline-node ng-pipeline-node--<?php echo esc_attr($state); ?>" style="--d: <?php echo 120 + ($i * 180); ?>ms;">
        <span class="dot" aria-hidden="true"></span>
        <span class="lbl">
          <span class="ar"><?php echo esc_html($node[1]); ?></span>
          <span class="en"><?php echo esc_html($node[0]); ?></span>
        </span>
      </li>
      <?php endforeach; ?>
    </ol>

    <div class="ng-thankyou-body">

      <!-- ORDER LEDGER -->
      <div class="ng-ledger">
        <div class="ng-ledger-head">
          <span>// ORDER LEDGER</span>
          <span><?php echo esc_html(sprintf(_n('%d unit', '%d units', $items_count, 'neogen'), $items_count)); ?></span>
        </div>

        <table class="ng-ledger-table">
          <thead>
            <tr>
              <th class="col-sku">SKU</th>
              <th class="col-item">ITEM</th>
              <th class="col-qty">QTY</th>
              <th class="col-amt">AMOUNT</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $item_id => $item) :
              $p     = $item->get_product();
              $sku   = $p ? $p->get_sku() : '';
              if (!$sku && $p) { $sku = 'NG-' . $p->get_id(); }
              $title = $item->get_name();
              $qty   = (int) $item->get_quantity();
              $total = $order->get_line_subtotal($item, true, true);
          ?>
            <tr>
              <td class="col-sku"><?php echo esc_html(strtoupper((string) $sku)); ?></td>
              <td class="col-item"><?php echo wp_kses_post($title); ?></td>
              <td class="col-qty">× <?php echo esc_html((string) $qty); ?></td>
              <td class="col-amt"><?php echo wp_kses_post(wc_price($total, ['currency' => $currency])); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <?php foreach ($order->get_order_item_totals() as $key => $total) : ?>
            <tr class="row-<?php echo esc_attr($key); ?>">
              <td colspan="3" class="col-lbl"><?php echo esc_html($total['label']); ?></td>
              <td class="col-amt"><?php echo wp_kses_post($total['value']); ?></td>
            </tr>
            <?php endforeach; ?>
          </tfoot>
        </table>
      </div>

      <!-- BRIEF ASIDE -->
      <aside class="ng-thankyou-brief">
        <div class="ng-brief-head">
          <span>// BRIEF</span>
          <span>LIVE</span>
        </div>

        <div class="ng-brief-row">
          <span class="k">Order</span>
          <span class="v">#<?php echo esc_html($order_id_h); ?></span>
          <span class="t"><?php echo esc_html(strtoupper($status)); ?></span>
        </div>
        <div class="ng-brief-row">
          <span class="k">Payment</span>
          <span class="v"><?php echo esc_html($payment_t ?: '—'); ?></span>
          <span class="t">OK</span>
        </div>
        <div class="ng-brief-row">
          <span class="k">ETA</span>
          <span class="v">2-5 business days</span>
          <span class="t">KSA</span>
        </div>
        <?php if ($addr) : ?>
        <div class="ng-brief-row ng-brief-row--stacked">
          <span class="k">Routing</span>
          <span class="v"><?php echo wp_kses_post($addr); ?></span>
        </div>
        <?php endif; ?>

        <div class="ng-thankyou-cta">
          <a class="btn btn-primary" href="<?php echo esc_url($orders_url); ?>">
            VIEW ORDER DETAILS
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </a>
          <a class="btn btn-ghost" href="<?php echo esc_url($shop_url); ?>">RETURN TO SHOP</a>
        </div>
      </aside>

    </div>

    <!-- VOICE CLOSE -->
    <div class="ng-thankyou-voice">
      TECHNOLOGY
      <span class="sep"></span>
      AS IT SHOULD BE
      <span class="sep"></span>
      SHIPPED FROM KSA
    </div>

  </div>
</section>
<?php
/*
 * Upstream WC fires these two at end-of-template. The gateway-specific
 * action runs payment-method-aware code (Stripe analytics, etc.); the
 * generic woocommerce_thankyou is THE conversion-pixel hook —
 * GA4 enhanced ecommerce, Google Ads conversion, Tabby/STC analytics
 * and any third-party order-followup plugin hooks here. Skipping it
 * would silently break conversion tracking on the live site.
 */
do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
do_action( 'woocommerce_thankyou', $order->get_id() );
