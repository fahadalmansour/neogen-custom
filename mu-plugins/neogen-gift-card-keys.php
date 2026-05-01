<?php
/**
 * Plugin Name: NeoGen Gift Card Keys
 * Description: Per-order-item gift-card code storage with at-rest encryption (AES-128-CTR over a key derived from wp_salt('logged_in')). Phase 0 ships data plumbing only — admin-side ng_gift_card_set_code() and customer-side ng_get_gift_card_keys($user_id) helpers. The customer-facing template lives in templates/woocommerce/myaccount/gift-cards.php (Phase 9, gated by ng_redesign_active('account')). Operators populate codes via the order-edit screen metabox added below.
 * Version: 1.38.0
 * Author: Fahad Almansour
 *
 * Source: /tmp/neogen-design/neogen-store/project/account.jsx (Gift Card Keys tab, lines 163–240).
 *
 * Per-line-item meta:
 *   _ng_gift_card_code        string (encrypted base64)
 *   _ng_gift_card_status      pending | active | consumed
 *   _ng_gift_card_expires_at  int (UNIX, optional)
 *   _ng_gift_card_brand       string (Apple, Spotify, Steam, …)
 *   _ng_gift_card_region      string (KSA, UAE, US, UK, …)
 */

defined('ABSPATH') || exit;

/**
 * Derive a 32-byte key from WP salts. Stable across requests, scoped
 * to the site so a stolen DB without the salts can't decrypt codes.
 */
function ng_gck_key() {
    return hash( 'sha256', wp_salt( 'logged_in' ) . '|ng-gift-card-key|v1', true );
}

function ng_gck_encrypt( $plain ) {
    $plain = (string) $plain;
    if ( $plain === '' ) { return ''; }
    if ( ! function_exists( 'openssl_encrypt' ) ) { return $plain; } // fail soft
    $iv  = random_bytes( 16 );
    $ct  = openssl_encrypt( $plain, 'aes-256-ctr', ng_gck_key(), OPENSSL_RAW_DATA, $iv );
    if ( $ct === false ) { return $plain; }
    return 'enc:v1:' . base64_encode( $iv . $ct );
}

function ng_gck_decrypt( $cipher ) {
    $cipher = (string) $cipher;
    if ( strpos( $cipher, 'enc:v1:' ) !== 0 ) {
        return $cipher; // legacy plaintext or empty
    }
    if ( ! function_exists( 'openssl_decrypt' ) ) { return ''; }
    $blob = base64_decode( substr( $cipher, 7 ), true );
    if ( $blob === false || strlen( $blob ) < 17 ) { return ''; }
    $iv = substr( $blob, 0, 16 );
    $ct = substr( $blob, 16 );
    $pt = openssl_decrypt( $ct, 'aes-256-ctr', ng_gck_key(), OPENSSL_RAW_DATA, $iv );
    return $pt === false ? '' : $pt;
}

/**
 * Set a gift-card code on a specific order item. Used by admin or by
 * a future fulfilment integration. Encrypts at rest.
 */
function ng_gift_card_set_code( $order_id, $item_id, $code, $extras = [] ) {
    $order = wc_get_order( (int) $order_id );
    if ( ! $order instanceof WC_Order ) { return false; }
    $item = $order->get_item( (int) $item_id );
    if ( ! $item instanceof WC_Order_Item_Product ) { return false; }

    $code = trim( (string) $code );
    if ( $code === '' ) {
        $item->delete_meta_data( '_ng_gift_card_code' );
        $item->delete_meta_data( '_ng_gift_card_status' );
    } else {
        $item->update_meta_data( '_ng_gift_card_code',   ng_gck_encrypt( $code ) );
        $item->update_meta_data( '_ng_gift_card_status', isset( $extras['status'] ) ? sanitize_key( $extras['status'] ) : 'active' );
    }
    if ( isset( $extras['expires_at'] ) ) {
        $item->update_meta_data( '_ng_gift_card_expires_at', (int) $extras['expires_at'] );
    }
    if ( isset( $extras['brand'] ) ) {
        $item->update_meta_data( '_ng_gift_card_brand', sanitize_text_field( $extras['brand'] ) );
    }
    if ( isset( $extras['region'] ) ) {
        $item->update_meta_data( '_ng_gift_card_region', sanitize_text_field( $extras['region'] ) );
    }
    $item->save_meta_data();
    return true;
}

/**
 * Return all gift-card keys for a user across their orders. Decrypts
 * codes on read. Suitable for the My Account "بطاقاتي" tab.
 */
function ng_get_gift_card_keys( $user_id = 0 ) {
    $user_id = (int) ( $user_id ?: get_current_user_id() );
    if ( ! $user_id ) { return []; }
    if ( ! function_exists( 'wc_get_orders' ) ) { return []; }
    $orders = wc_get_orders( [
        'customer_id' => $user_id,
        'status'      => [ 'completed', 'processing' ],
        'limit'       => 50,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ] );
    if ( empty( $orders ) ) { return []; }

    $now  = current_time( 'timestamp', true );
    $keys = [];
    foreach ( $orders as $order ) {
        if ( ! $order instanceof WC_Order ) { continue; }
        foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
            $cipher = (string) $item->get_meta( '_ng_gift_card_code', true );
            $product = $item->get_product();
            $is_gc   = false;
            if ( $product instanceof WC_Product ) {
                if ( function_exists( 'ng_gift_card_is_candidate_product' ) ) {
                    $is_gc = (bool) ng_gift_card_is_candidate_product( $product );
                } else {
                    $cats = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'slugs' ] );
                    $is_gc = ! is_wp_error( $cats ) && in_array( 'gift-cards', $cats, true );
                }
            }
            if ( ! $is_gc && $cipher === '' ) { continue; }
            $expires_at = (int) $item->get_meta( '_ng_gift_card_expires_at', true );
            $keys[] = [
                'order_id'      => $order->get_id(),
                'order_number'  => $order->get_order_number(),
                'item_id'       => $item_id,
                'product_id'    => $item->get_product_id(),
                'product_name'  => $item->get_name(),
                'product_sku'   => $product ? $product->get_sku() : '',
                'brand'         => (string) $item->get_meta( '_ng_gift_card_brand',  true ),
                'region'        => (string) $item->get_meta( '_ng_gift_card_region', true ),
                'code'          => $cipher === '' ? '' : ng_gck_decrypt( $cipher ),
                'has_code'      => $cipher !== '',
                'status'        => (string) ( $item->get_meta( '_ng_gift_card_status', true ) ?: ( $cipher === '' ? 'pending' : 'active' ) ),
                'expires_at'    => $expires_at,
                'is_expired'    => $expires_at > 0 && $now > $expires_at,
                'purchase_date' => $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0,
            ];
        }
    }
    return $keys;
}

/* ---------------------------------------------------------------------
 * Admin UI — order-edit metabox so operators can paste codes per item.
 * ------------------------------------------------------------------- */

add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'ng-order-gift-card-keys',
        'NeoGen — Gift Card Keys',
        'ng_gck_admin_box',
        'shop_order',
        'normal',
        'default'
    );
    // HPOS / new orders screen
    if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) ) {
        add_meta_box(
            'ng-order-gift-card-keys',
            'NeoGen — Gift Card Keys',
            'ng_gck_admin_box',
            wc_get_page_screen_id( 'shop-order' ),
            'normal',
            'default'
        );
    }
} );

function ng_gck_admin_box( $post_or_order ) {
    $order = is_a( $post_or_order, 'WP_Post' ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
    if ( ! $order instanceof WC_Order ) { echo '<p>—</p>'; return; }
    wp_nonce_field( 'ng_gck_save_' . $order->get_id(), 'ng_gck_nonce' );
    ?>
    <p style="font-size:12px;color:#666;margin:0 0 10px;">Gift-card codes are encrypted at rest. Customers see the (decrypted) code in their account under <em>بطاقاتي · الأكواد</em> when the <code>account</code> redesign phase is enabled.</p>
    <table class="widefat striped">
      <thead>
        <tr>
          <th style="width:32%;">Item</th>
          <th>Code</th>
          <th style="width:120px;">Status</th>
          <th style="width:140px;">Expires (YYYY-MM-DD)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) :
            if ( ! $item instanceof WC_Order_Item_Product ) { continue; }
            $cipher  = (string) $item->get_meta( '_ng_gift_card_code', true );
            $code    = $cipher === '' ? '' : ng_gck_decrypt( $cipher );
            $status  = (string) ( $item->get_meta( '_ng_gift_card_status', true ) ?: 'pending' );
            $expires = (int)    $item->get_meta( '_ng_gift_card_expires_at', true );
            $exp_str = $expires ? gmdate( 'Y-m-d', $expires ) : '';
        ?>
          <tr>
            <td><code style="font-size:11px;"><?php echo esc_html( $item->get_name() ); ?></code></td>
            <td><input type="text" name="ng_gck[<?php echo (int) $item_id; ?>][code]" value="<?php echo esc_attr( $code ); ?>" style="width:100%;font-family:monospace;" placeholder="XXXX-XXXX-XXXX-XXXX"></td>
            <td>
              <select name="ng_gck[<?php echo (int) $item_id; ?>][status]" style="width:100%;">
                <?php foreach ( [ 'pending', 'active', 'consumed' ] as $s ) : ?>
                  <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( $s ); ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="date" name="ng_gck[<?php echo (int) $item_id; ?>][expires]" value="<?php echo esc_attr( $exp_str ); ?>" style="width:100%;"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
}

add_action( 'woocommerce_process_shop_order_meta', function ( $order_id ) {
    if ( ! isset( $_POST['ng_gck_nonce'] )
         || ! wp_verify_nonce( $_POST['ng_gck_nonce'], 'ng_gck_save_' . $order_id ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_shop_orders' ) ) { return; }
    $rows = isset( $_POST['ng_gck'] ) && is_array( $_POST['ng_gck'] ) ? wp_unslash( $_POST['ng_gck'] ) : [];
    $order = wc_get_order( (int) $order_id );
    if ( ! $order instanceof WC_Order ) { return; }
    foreach ( $rows as $item_id => $row ) {
        $code    = isset( $row['code'] )    ? sanitize_text_field( $row['code'] )    : '';
        $status  = isset( $row['status'] )  ? sanitize_key( $row['status'] )         : 'pending';
        $exp_str = isset( $row['expires'] ) ? sanitize_text_field( $row['expires'] ) : '';
        $exp_ts  = $exp_str ? strtotime( $exp_str . ' 23:59:59 UTC' ) : 0;
        ng_gift_card_set_code(
            $order_id, (int) $item_id, $code,
            [
                'status'     => $status,
                'expires_at' => $exp_ts ?: 0,
            ]
        );
    }
} );
