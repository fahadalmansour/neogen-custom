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
      <span>// OPERATOR · AUTHENTICATED</span>
    </div>
    <h1 class="ng-account-h1">
      <span class="ar">مرحباً، <?php echo esc_html($current_user->display_name); ?></span>
      <span class="en">HELLO, <?php echo esc_html(strtoupper($current_user->display_name)); ?></span>
    </h1>
    <p class="ng-account-lede">
      <?php
      printf(
        /* translators: 1: user display name 2: logout url */
        wp_kses(__('From this console you can review your orders, manage shipping addresses, edit account details, and log out. Not %1$s? <a href="%2$s">Log out</a>.', 'woocommerce'), ['a' => ['href' => []]]),
        '<strong>' . esc_html($current_user->display_name) . '</strong>',
        esc_url(wc_logout_url())
      );
      ?>
    </p>
  </div>

  <div class="ng-account-tiles">
    <a class="ng-account-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">
      <div class="k">01</div>
      <div class="lbl">
        <span class="ar">طلباتي</span>
        <span class="en">ORDERS</span>
      </div>
    </a>
    <a class="ng-account-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>">
      <div class="k">02</div>
      <div class="lbl">
        <span class="ar">العناوين</span>
        <span class="en">ADDRESSES</span>
      </div>
    </a>
    <a class="ng-account-tile" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>">
      <div class="k">03</div>
      <div class="lbl">
        <span class="ar">تفاصيل الحساب</span>
        <span class="en">ACCOUNT</span>
      </div>
    </a>
    <a class="ng-account-tile" href="<?php echo esc_url(wc_logout_url()); ?>">
      <div class="k">04</div>
      <div class="lbl">
        <span class="ar">تسجيل الخروج</span>
        <span class="en">LOG OUT</span>
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
