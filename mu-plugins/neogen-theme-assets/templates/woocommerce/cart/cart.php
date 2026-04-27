<?php
/**
 * NeoGen override for WooCommerce cart page.
 *
 * Routed via the wc_get_template filter map in mu-plugins/neogen-theme.php.
 *
 * Preserves every Woo-required class, hook, form action, nonce, and
 * input name so the AJAX update-cart / remove-item / coupon-apply
 * flow keeps working. Adds .ng-cart-* classes alongside for theming.
 *
 * @var WC_Cart $cart
 * @version 10.1.0 (NeoGen reconciled against upstream WC 10.7.0)
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_cart');
?>

<section class="ng-cart">
  <header class="ng-cart-head">
    <div class="ng-cart-kicker">
      <span class="led on" aria-hidden="true"></span>
      <span>01 · CART · ORDER QUEUE</span>
    </div>
    <h1 class="ng-cart-h1">
      <span class="ar">السلة</span>
      <span class="en">YOUR CART</span>
    </h1>
  </header>

  <div class="ng-cart-body">

    <form class="woocommerce-cart-form ng-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
      <?php do_action( 'woocommerce_before_cart_table' ); ?>

      <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents ng-cart-table" cellspacing="0">
        <thead>
          <tr>
            <th class="product-remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove item', 'woocommerce' ); ?></span></th>
            <th class="product-thumbnail"><span class="screen-reader-text"><?php esc_html_e( 'Thumbnail image', 'woocommerce' ); ?></span></th>
            <th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
            <th class="product-price"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
            <th class="product-quantity"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
            <th class="product-subtotal"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php do_action( 'woocommerce_before_cart_contents' ); ?>

          <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
            $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

            if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) :
              $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
              $sku = $_product->get_sku();
              if ( ! $sku ) { $sku = 'NG-' . $_product->get_id(); }
              $name_ar = get_post_meta( $_product->get_id(), '_ng_ar_title', true );
              if ( ! $name_ar ) { $name_ar = $_product->get_name(); }
          ?>
          <tr class="woocommerce-cart-form__cart-item ng-cart-row <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">

            <td class="product-remove">
              <?php
              echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'woocommerce_cart_item_remove_link',
                sprintf(
                  '<a href="%s" class="remove ng-cart-remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">×</a>',
                  esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                  /* translators: %s is the product name */
                  esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $_product->get_name() ) ) ),
                  esc_attr( $product_id ),
                  esc_attr( $sku )
                ),
                $cart_item_key
              );
              ?>
            </td>

            <td class="product-thumbnail">
              <?php
              $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );
              if ( ! $product_permalink ) {
                echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
              } else {
                printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $thumbnail ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
              }
              ?>
            </td>

            <td class="product-name" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
              <span class="ng-cart-sku"><?php echo esc_html( strtoupper( $sku ) ); ?></span>
              <?php if ( ! $product_permalink ) :
                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;';
              else : ?>
                <a class="ng-cart-name" href="<?php echo esc_url( $product_permalink ); ?>">
                  <span class="ar"><?php echo esc_html( $name_ar ); ?></span>
                  <span class="en"><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?></span>
                </a>
              <?php endif;
              do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
              echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
              if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
                echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
              }
              ?>
            </td>

            <td class="product-price" data-title="<?php esc_attr_e( 'Price', 'woocommerce' ); ?>">
              <?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </td>

            <td class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce' ); ?>">
              <?php
              if ( $_product->is_sold_individually() ) {
                $product_quantity = sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key );
              } else {
                $product_quantity = woocommerce_quantity_input(
                  [
                    'input_name'   => "cart[{$cart_item_key}][qty]",
                    'input_value'  => $cart_item['quantity'],
                    'max_value'    => $_product->get_max_purchase_quantity(),
                    'min_value'    => '0',
                    'product_name' => $_product->get_name(),
                  ],
                  $_product,
                  false
                );
              }
              echo apply_filters( 'woocommerce_cart_item_quantity', $product_quantity, $cart_item_key, $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
              ?>
            </td>

            <td class="product-subtotal" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
              <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </td>

          </tr>
          <?php endif; ?>
          <?php endforeach; ?>

          <?php do_action( 'woocommerce_cart_contents' ); ?>

          <tr class="ng-cart-actions">
            <td colspan="6" class="actions">
              <?php if ( wc_coupons_enabled() ) : ?>
                <div class="coupon ng-cart-coupon">
                  <label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
                  <input type="text" name="coupon_code" class="input-text ng-coupon-input" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" />
                  <button type="submit" class="button ng-coupon-button" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
                  <?php do_action( 'woocommerce_cart_coupon' ); ?>
                </div>
              <?php endif; ?>

              <button type="submit" class="button ng-update-cart" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>

              <?php do_action( 'woocommerce_cart_actions' ); ?>

              <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
            </td>
          </tr>

          <?php do_action( 'woocommerce_after_cart_contents' ); ?>
        </tbody>
      </table>

      <?php do_action( 'woocommerce_after_cart_table' ); ?>
    </form>

    <aside class="cart-collaterals ng-cart-aside">
      <?php
      /**
       * Upstream WC fires woocommerce_before_cart_collaterals before
       * the totals/coupons block — extension point for cart-side
       * promotion banners, BOGO widgets, etc.
       */
      do_action( 'woocommerce_before_cart_collaterals' );

      /**
       * Renders cart-totals.php (our themed override) and any
       * shipping calculator. Plugins that hook here (Tabby
       * estimators, etc.) still register normally.
       */
      do_action( 'woocommerce_cart_collaterals' );
      ?>
    </aside>

  </div>

  <?php
  // Recommendations strip — picks based on what's already in the
  // recently-viewed cookie (cart items naturally land there as the
  // customer browses), excluding products already in the cart.
  $exclude_id = 0;
  $cart_items = WC()->cart->get_cart();
  if ( ! empty( $cart_items ) ) {
    $first = reset( $cart_items );
    $exclude_id = isset( $first['product_id'] ) ? (int) $first['product_id'] : 0;
  }
  echo do_shortcode( '[neogen_recommendations limit="4" title_ar="مقترحات لسلتك" title_en="ALSO PICKED FOR YOUR CART" kicker="OPERATOR · CART NEXT PICKS"]' );
  ?>

</section>

<?php do_action( 'woocommerce_after_cart' ); ?>
