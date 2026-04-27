<?php
/**
 * NeoGen override for /my-account/ dashboard landing.
 *
 * @var WP_User $current_user
 */

defined('ABSPATH') || exit;
?>

<section class="ng-account-dashboard">

  <div class="ng-account-greeting">
    <div class="ng-account-kicker">
      <span class="led on" aria-hidden="true"></span>
      <span>// المشغّل · مُسجّل الدخول</span>
    </div>
    <h1 class="ng-account-h1">
      <span class="ar">مرحبًا، <?php echo esc_html($current_user->display_name); ?></span>
    </h1>
    <p class="ng-account-lede">
      <?php
      printf(
        /* translators: 1: user display name 2: logout url */
        wp_kses(__('من هذه اللوحة يمكنك مراجعة طلباتك، إدارة عناوين الشحن، تعديل تفاصيل الحساب، وتسجيل الخروج. هل تريد تسجيل الخروج من حساب %1$s؟ <a href="%2$s">تسجيل الخروج</a>.', 'neogen'), ['a' => ['href' => []]]),
        '<strong>' . esc_html($current_user->display_name) . '</strong>',
        esc_url(wc_logout_url())
      );
      ?>
    </p>
  </div>

  <?php
  // Quick stats row — order count + last order
  $orders_count = function_exists('wc_get_customer_order_count') ? wc_get_customer_order_count( $current_user->ID ) : 0;
  $recent = function_exists('wc_get_orders') ? wc_get_orders([
      'customer_id' => $current_user->ID,
      'limit'       => 1,
      'orderby'     => 'date',
      'order'       => 'DESC',
      'status'      => array_keys( wc_get_order_statuses() ),
  ]) : [];
  $last_order = ! empty( $recent ) ? reset( $recent ) : null;
  ?>
  <div class="ng-account-stats">
    <div class="ng-account-stat">
      <span class="k">إجمالي الطلبات</span>
      <span class="v"><?php echo esc_html( (int) $orders_count ); ?></span>
    </div>
    <?php if ( $last_order ) : ?>
    <div class="ng-account-stat">
      <span class="k">آخر طلب</span>
      <span class="v">#<?php echo esc_html( $last_order->get_order_number() ); ?> · <?php echo esc_html( wc_get_order_status_name( $last_order->get_status() ) ); ?></span>
    </div>
    <div class="ng-account-stat">
      <span class="k">تاريخ آخر طلب</span>
      <span class="v"><?php echo esc_html( wp_date( 'Y-m-d', $last_order->get_date_created() ? $last_order->get_date_created()->getTimestamp() : 0 ) ); ?></span>
    </div>
    <?php endif; ?>
  </div>

  <div class="ng-account-tiles">
    <a class="ng-account-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">
      <div class="k">01</div>
      <div class="lbl">
        <span class="ar">طلباتي</span>
      </div>
    </a>
    <a class="ng-account-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>">
      <div class="k">02</div>
      <div class="lbl">
        <span class="ar">العناوين</span>
      </div>
    </a>
    <a class="ng-account-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>">
      <div class="k">03</div>
      <div class="lbl">
        <span class="ar">تفاصيل الحساب</span>
      </div>
    </a>
    <a class="ng-account-tile" href="<?php echo esc_url(wc_logout_url()); ?>">
      <div class="k">04</div>
      <div class="lbl">
        <span class="ar">تسجيل الخروج</span>
      </div>
    </a>
  </div>

  <?php
  /**
   * Woo runs woocommerce_account_dashboard hook here. Plugins
   * (loyalty points, store credit, etc.) register on it.
   */
  do_action('woocommerce_account_dashboard');

  /**
   * Deprecated but still used by some plugins.
   */
  do_action('woocommerce_before_my_account');
  do_action('woocommerce_after_my_account');
  ?>

</section>
