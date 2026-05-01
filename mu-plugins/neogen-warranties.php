<?php
/**
 * Plugin Name: NeoGen Warranties
 * Description: Warranty tracker — captures warranty months per order line item at purchase time, sets the start date when the order completes, and exposes ng_get_warranties($user_id) for the Phase 9 account dashboard. Phase 0 ships data plumbing only — there is no customer-facing UI; render happens in templates/woocommerce/myaccount/warranties.php gated by ng_redesign_active('account').
 * Version: 1.38.0
 * Author: Fahad Almansour
 *
 * Source: /tmp/neogen-design/neogen-store/project/account.jsx (Warranties tab, lines 243–331)
 *
 * Per-product warranty months are read from product meta `_ng_warranty_months`
 * (default 12). Per-order-item meta:
 *   _ng_warranty_months     int    — captured at purchase
 *   _ng_warranty_starts_at  int    — UNIX timestamp, set on order completion
 */

defined('ABSPATH') || exit;

const NG_WARRANTY_DEFAULT_MONTHS = 12;

/**
 * Register a "Warranty (months)" field on the product general data tab.
 * Stored as `_ng_warranty_months`. 12 is the floor when blank.
 */
add_action( 'woocommerce_product_options_general_product_data', function () {
    woocommerce_wp_text_input( [
        'id'          => '_ng_warranty_months',
        'label'       => __( 'NeoGen warranty (months)', 'neogen' ),
        'placeholder' => (string) NG_WARRANTY_DEFAULT_MONTHS,
        'desc_tip'    => true,
        'description' => __( 'Months of warranty for this product. Leave blank for the 12-month default. Captured per line at purchase time and rendered in the customer account.', 'neogen' ),
        'type'        => 'number',
        'custom_attributes' => [ 'min' => '0', 'step' => '1' ],
    ] );
} );

add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
    if ( ! current_user_can( 'edit_product', $post_id ) ) { return; }
    if ( ! isset( $_POST['_ng_warranty_months'] ) ) {
        delete_post_meta( $post_id, '_ng_warranty_months' );
        return;
    }
    $val = trim( (string) wp_unslash( $_POST['_ng_warranty_months'] ) );
    if ( $val === '' ) {
        delete_post_meta( $post_id, '_ng_warranty_months' );
    } else {
        update_post_meta( $post_id, '_ng_warranty_months', max( 0, (int) $val ) );
    }
} );

/**
 * Capture warranty months at line-item creation time so editing the
 * product later doesn't change a customer's already-purchased warranty.
 *
 * Hook: woocommerce_checkout_create_order_line_item runs once per item
 * when the order is built from the cart.
 */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values, $order ) {
    if ( ! $item instanceof WC_Order_Item_Product ) { return; }
    $product = $item->get_product();
    if ( ! $product instanceof WC_Product ) { return; }
    $months = (int) get_post_meta( $product->get_id(), '_ng_warranty_months', true );
    if ( $months <= 0 ) { $months = NG_WARRANTY_DEFAULT_MONTHS; }
    $item->add_meta_data( '_ng_warranty_months', $months, true );
}, 10, 4 );

/**
 * Set the warranty start timestamp when the order completes (or when
 * the status transitions to 'completed' — typical for shipping-only
 * stores). Stored on each line item.
 */
add_action( 'woocommerce_order_status_completed', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order instanceof WC_Order ) { return; }
    $now = current_time( 'timestamp', true ); // UTC; render is per-locale
    foreach ( $order->get_items( 'line_item' ) as $item ) {
        if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
        if ( $item->get_meta( '_ng_warranty_starts_at', true ) ) { continue; } // idempotent
        if ( ! $item->meta_exists( '_ng_warranty_months' ) ) { continue; }
        $item->add_meta_data( '_ng_warranty_starts_at', $now, true );
        $item->save_meta_data();
    }
} );

/**
 * Return active warranties for a user as a normalized array. Each
 * row has enough data to render the warranty card from account.jsx
 * without further DB lookups.
 */
function ng_get_warranties( $user_id = 0 ) {
    $user_id = (int) ( $user_id ?: get_current_user_id() );
    if ( ! $user_id ) { return []; }
    if ( ! function_exists( 'wc_get_orders' ) ) { return []; }
    $orders = wc_get_orders( [
        'customer_id' => $user_id,
        'status'      => [ 'completed', 'processing' ], // processing = paid, awaiting ship
        'limit'       => 50,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ] );
    if ( empty( $orders ) ) { return []; }

    $now      = current_time( 'timestamp', true );
    $warranties = [];
    foreach ( $orders as $order ) {
        if ( ! $order instanceof WC_Order ) { continue; }
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
            $months = (int) $item->get_meta( '_ng_warranty_months', true );
            if ( $months <= 0 ) { continue; }
            $start  = (int) $item->get_meta( '_ng_warranty_starts_at', true );
            // For orders not yet completed (status=processing), use the
            // order date as a provisional start so the customer sees the
            // window even before fulfilment.
            if ( $start <= 0 ) {
                $date_obj = $order->get_date_created();
                $start    = $date_obj ? $date_obj->getTimestamp() : $now;
            }
            $end             = $start + ( $months * MONTH_IN_SECONDS );
            $remaining_secs  = max( 0, $end - $now );
            $remaining_days  = (int) round( $remaining_secs / DAY_IN_SECONDS );
            $total_days      = max( 1, (int) round( ( $months * MONTH_IN_SECONDS ) / DAY_IN_SECONDS ) );
            $progress_pct    = max( 0, min( 100, (int) round( ( $remaining_days / $total_days ) * 100 ) ) );
            $product         = $item->get_product();
            $warranties[] = [
                'order_id'        => $order->get_id(),
                'order_number'    => $order->get_order_number(),
                'order_key'       => $order->get_order_key(),
                'product_id'      => $item->get_product_id(),
                'product_name'    => $item->get_name(),
                'product_sku'     => $product ? $product->get_sku() : '',
                'product_image_id'=> $product ? $product->get_image_id() : 0,
                'months'          => $months,
                'starts_at'       => (int) $start,
                'ends_at'         => (int) $end,
                'remaining_days'  => $remaining_days,
                'total_days'      => $total_days,
                'progress_pct'    => $progress_pct,
                'is_expired'      => $remaining_secs === 0,
                'status_color'    => $progress_pct > 50 ? 'good' : ( $progress_pct > 20 ? 'warn' : 'sale' ),
                'purchase_date'   => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
            ];
        }
    }
    return $warranties;
}
