<?php
/**
 * NeoGen override for /my-account/orders/.
 *
 * @var WC_Order[] $customer_orders
 * @var bool       $has_orders
 * @version 9.5.0 (NeoGen reconciled against upstream WC 10.7.0)
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_account_orders', $has_orders);
?>

<section class="ng-account-orders">

  <header class="ng-account-section-head">
    <div class="ng-account-kicker">
      <span class="led on" aria-hidden="true"></span>
      <span>// ORDERS · <?php echo esc_html((int) $customer_orders->total); ?> TOTAL</span>
    </div>
    <h2 class="ng-account-h2">
      <span class="ar">طلباتي</span>
      <span class="en">ORDER LOG</span>
    </h2>
  </header>

  <?php if ($has_orders) : ?>

    <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table ng-account-orders-table">
      <thead>
        <tr>
          <?php foreach (wc_get_account_orders_columns() as $column_id => $column_name) : ?>
            <th class="woocommerce-orders-table__header woocommerce-orders-table__header-<?php echo esc_attr($column_id); ?>">
              <span class="nobr"><?php echo esc_html($column_name); ?></span>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>

      <tbody>
        <?php foreach ($customer_orders->orders as $customer_order) :
          $order      = wc_get_order($customer_order);
          $item_count = $order->get_item_count() - $order->get_item_count_refunded();
        ?>
          <tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr($order->get_status()); ?> order">
            <?php foreach (wc_get_account_orders_columns() as $column_id => $column_name) : ?>
              <td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-<?php echo esc_attr($column_id); ?>" data-title="<?php echo esc_attr($column_name); ?>">
                <?php if (has_action('woocommerce_my_account_my_orders_column_' . $column_id)) :
                  do_action('woocommerce_my_account_my_orders_column_' . $column_id, $order);
                elseif ('order-number' === $column_id) : ?>
                  <a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="ng-order-num">#<?php echo esc_html($order->get_order_number()); ?></a>
                <?php elseif ('order-date' === $column_id) : ?>
                  <time datetime="<?php echo esc_attr($order->get_date_created()->date('c')); ?>"><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></time>
                <?php elseif ('order-status' === $column_id) : ?>
                  <span class="ng-order-status ng-order-status--<?php echo esc_attr($order->get_status()); ?>">
                    <span class="led" aria-hidden="true"></span>
                    <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                  </span>
                <?php elseif ('order-total' === $column_id) : ?>
                  <?php
                  printf(
                    /* translators: 1: formatted order total 2: total order items */
                    esc_html(_n('%1$s for %2$s item', '%1$s for %2$s items', $item_count, 'woocommerce')),
                    wp_kses_post($order->get_formatted_order_total()),
                    esc_html($item_count)
                  );
                  ?>
                <?php elseif ('order-actions' === $column_id) : ?>
                  <?php
                  $actions = wc_get_account_orders_actions($order);
                  if (!empty($actions)) {
                    foreach ($actions as $key => $action) {
                      echo '<a href="' . esc_url($action['url']) . '" class="woocommerce-button button ng-order-btn ' . sanitize_html_class($key) . '">' . esc_html($action['name']) . '</a>';
                    }
                  }
                  ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php do_action('woocommerce_before_account_orders_pagination'); ?>

    <?php if (1 < $customer_orders->max_num_pages) : ?>
      <div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination ng-account-pagination">
        <?php if (1 !== $current_page) : ?>
          <a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button" href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page - 1)); ?>">← <?php esc_html_e('Previous', 'woocommerce'); ?></a>
        <?php endif; ?>
        <?php if (intval($customer_orders->max_num_pages) !== $current_page) : ?>
          <a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button" href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page + 1)); ?>"><?php esc_html_e('Next', 'woocommerce'); ?> →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php else : ?>

    <div class="ng-account-empty">
      <div class="ng-account-empty-led" aria-hidden="true"></div>
      <h3>
        <span class="ar">لا توجد طلبات بعد</span>
        <span class="en">NO ORDERS YET</span>
      </h3>
      <p><?php esc_html_e('Once you place an order, it will appear here.', 'woocommerce'); ?></p>
      <a class="btn btn-primary" href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))); ?>">
        BROWSE THE SHOP
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
      </a>
    </div>

  <?php endif; ?>

</section>

<?php do_action('woocommerce_after_account_orders', $has_orders); ?>
