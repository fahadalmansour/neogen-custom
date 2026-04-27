<?php
/**
 * Plugin Name: NeoGen Merchant
 * Description: Google Merchant Center product feed at /feed/google-merchant/, Schema.org Product+Offer JSON-LD on single product pages, and Google Site Verification meta tag.
 * Version: 1.12.0
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

/* ====================================================================
 *  GOOGLE SITE VERIFICATION META TAG
 *  Stored as option `ng_google_site_verification`. Edit in
 *  Tools → NeoGen Merchant.
 * ==================================================================== */

add_action('wp_head', function () {
    $code = trim( (string) get_option('ng_google_site_verification', '') );
    if ( $code === '' ) return;
    // Strip a full meta-tag paste: keep only the content="..." value
    if ( preg_match('/content=["\']([^"\']+)["\']/i', $code, $m) ) {
        $code = $m[1];
    }
    if ( $code !== '' ) {
        echo "\n" . '<meta name="google-site-verification" content="' . esc_attr( $code ) . '">' . "\n";
    }
}, 1);

/* ====================================================================
 *  ADMIN PAGE — Tools → NeoGen Merchant
 * ==================================================================== */

add_action('admin_menu', function () {
    add_management_page(
        'NeoGen Merchant',
        'NeoGen Merchant',
        'manage_options',
        'neogen-merchant',
        'ng_merchant_render_admin'
    );
});

add_action('admin_post_ng_merchant_save', function () {
    if ( ! current_user_can('manage_options') ) wp_die('nope');
    check_admin_referer('ng_merchant_save');

    update_option('ng_google_site_verification', sanitize_text_field( (string) ($_POST['ng_google_site_verification'] ?? '') ));
    update_option('ng_default_brand',           sanitize_text_field( (string) ($_POST['ng_default_brand']           ?? 'NeoGen Store') ));
    update_option('ng_default_condition',       sanitize_text_field( (string) ($_POST['ng_default_condition']       ?? 'new') ));
    update_option('ng_google_product_category', sanitize_text_field( (string) ($_POST['ng_google_product_category'] ?? '') ));

    // Bust feed cache so changes are visible immediately
    delete_transient('ng_merchant_feed_xml');

    wp_safe_redirect( add_query_arg('saved', '1', admin_url('tools.php?page=neogen-merchant')) );
    exit;
});

function ng_merchant_render_admin() {
    if ( ! current_user_can('manage_options') ) wp_die('nope');
    $verify   = (string) get_option('ng_google_site_verification', '');
    $brand    = (string) get_option('ng_default_brand', 'NeoGen Store');
    $cond     = (string) get_option('ng_default_condition', 'new');
    $google_cat = (string) get_option('ng_google_product_category', '');
    $feed_url = home_url('/feed/google-merchant/');
    $saved    = ! empty( $_GET['saved'] );
    ?>
    <div class="wrap">
      <h1>NeoGen Merchant</h1>
      <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p>Saved.</p></div>
      <?php endif; ?>

      <h2>Google Site Verification</h2>
      <p>Paste the entire <code>&lt;meta name="google-site-verification" …&gt;</code> tag from Merchant Center, or just the <code>content=…</code> value. The tag is rendered in <code>&lt;head&gt;</code> on every page.</p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ng_merchant_save'); ?>
        <input type="hidden" name="action" value="ng_merchant_save">

        <table class="form-table">
          <tr>
            <th scope="row"><label for="ng_google_site_verification">Verification tag or code</label></th>
            <td>
              <input type="text" id="ng_google_site_verification" name="ng_google_site_verification"
                     value="<?php echo esc_attr( $verify ); ?>" class="regular-text" style="font-family: ui-monospace, monospace;">
              <p class="description">e.g. <code>&lt;meta name="google-site-verification" content="abc123…"&gt;</code> or just <code>abc123…</code></p>
            </td>
          </tr>
        </table>

        <h2>Product feed defaults</h2>
        <p>Used to fill missing per-product fields when the Woo product itself does not specify them.</p>
        <table class="form-table">
          <tr>
            <th scope="row"><label for="ng_default_brand">Default brand</label></th>
            <td><input type="text" id="ng_default_brand" name="ng_default_brand" value="<?php echo esc_attr( $brand ); ?>" class="regular-text"></td>
          </tr>
          <tr>
            <th scope="row"><label for="ng_default_condition">Default condition</label></th>
            <td>
              <select id="ng_default_condition" name="ng_default_condition">
                <option value="new"        <?php selected($cond, 'new'); ?>>new</option>
                <option value="refurbished" <?php selected($cond, 'refurbished'); ?>>refurbished</option>
                <option value="used"       <?php selected($cond, 'used'); ?>>used</option>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="ng_google_product_category">Google product category (optional)</label></th>
            <td>
              <input type="text" id="ng_google_product_category" name="ng_google_product_category"
                     value="<?php echo esc_attr( $google_cat ); ?>" class="regular-text">
              <p class="description">Numeric ID or full taxonomy string from <a href="https://support.google.com/merchants/answer/6324436" target="_blank" rel="noopener">Google's product taxonomy</a>. Leave empty to skip.</p>
            </td>
          </tr>
        </table>

        <?php submit_button('Save'); ?>
      </form>

      <hr>
      <h2>Feed URL</h2>
      <p>Submit this URL inside Merchant Center → <em>Products → Feeds → Add primary feed → Scheduled fetch</em>:</p>
      <p><code style="user-select:all; padding:8px; background:#f0f0f1; display:inline-block;"><?php echo esc_html( $feed_url ); ?></code></p>
      <p>
        <a class="button" href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener">Open feed</a>
        <a class="button" href="<?php echo esc_url( add_query_arg('ng_merchant_flush_cache', '1', $feed_url) ); ?>" target="_blank" rel="noopener">Open feed (skip cache)</a>
      </p>
      <p class="description">The feed is cached for 1 hour. Saving above and changing any product invalidates the cache automatically.</p>
    </div>
    <?php
}

/* ====================================================================
 *  PRODUCT SCHEMA — Schema.org Product + Offer on single product pages
 * ==================================================================== */

add_action('wp_head', function () {
    if ( ! function_exists('is_product') || ! is_product() ) return;
    global $product;
    if ( ! $product ) {
        $product = wc_get_product( get_queried_object_id() );
    }
    if ( ! $product || ! $product instanceof WC_Product ) return;

    $price = $product->get_price();
    if ( $price === '' ) return;

    $img_id  = $product->get_image_id();
    $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';

    $brand     = (string) $product->get_meta('_ng_brand');
    if ( $brand === '' ) {
        $brands = wp_get_post_terms( $product->get_id(), 'product_brand', ['fields' => 'names'] );
        if ( ! is_wp_error($brands) && ! empty($brands) ) $brand = $brands[0];
    }
    if ( $brand === '' ) $brand = (string) get_option('ng_default_brand', 'NeoGen Store');

    $gtin = (string) $product->get_meta('_ng_gtin');
    $mpn  = (string) $product->get_meta('_ng_mpn');
    if ( $mpn === '' ) $mpn = (string) $product->get_sku();

    $availability = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

    $offer = [
        '@type'          => 'Offer',
        'url'            => get_permalink( $product->get_id() ),
        'priceCurrency'  => get_woocommerce_currency(),
        'price'          => (string) $price,
        'availability'   => $availability,
        'itemCondition'  => 'https://schema.org/' . ucfirst( (string) get_option('ng_default_condition', 'new') ) . 'Condition',
        'priceValidUntil' => gmdate('Y-m-d', strtotime('+30 days')),
        'seller'         => [ '@id' => trailingslashit( home_url('/') ) . '#organization' ],
    ];

    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        '@id'         => get_permalink( $product->get_id() ) . '#product',
        'name'        => $product->get_name(),
        'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
        'sku'         => $product->get_sku(),
        'mpn'         => $mpn,
        'brand'       => [ '@type' => 'Brand', 'name' => $brand ],
        'image'       => $img_url,
        'url'         => get_permalink( $product->get_id() ),
        'offers'      => $offer,
    ];
    if ( $gtin !== '' ) {
        $schema['gtin'] = $gtin;
    }

    $rating_count = (int) $product->get_rating_count();
    $avg_rating   = (float) $product->get_average_rating();
    if ( $rating_count > 0 && $avg_rating > 0 ) {
        $schema['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => number_format( $avg_rating, 1, '.', '' ),
            'reviewCount' => $rating_count,
        ];
    }

    $json = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( $json ) {
        echo "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }
}, 6);

/* ====================================================================
 *  GOOGLE MERCHANT FEED at /feed/google-merchant/
 * ==================================================================== */

add_action('init', function () {
    add_rewrite_rule( '^feed/google-merchant/?$', 'index.php?ng_merchant_feed=1', 'top' );
    add_rewrite_tag( '%ng_merchant_feed%', '([0-1])' );
});

// One-shot flush after activation/version change
add_action('init', function () {
    if ( get_option('ng_merchant_rules_flushed_v1') !== '1' ) {
        flush_rewrite_rules(false);
        update_option('ng_merchant_rules_flushed_v1', '1');
    }
}, 99);

add_action('template_redirect', function () {
    if ( (int) get_query_var('ng_merchant_feed') !== 1 ) return;
    if ( ! function_exists('wc_get_products') ) {
        status_header(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "WooCommerce not available.\n";
        exit;
    }

    $skip_cache = isset($_GET['ng_merchant_flush_cache']) && current_user_can('manage_options');
    $cached = $skip_cache ? false : get_transient('ng_merchant_feed_xml');
    if ( $cached !== false ) {
        nocache_headers();
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow', true);
        echo $cached;
        exit;
    }

    $xml = ng_merchant_build_feed();
    set_transient('ng_merchant_feed_xml', $xml, HOUR_IN_SECONDS);

    nocache_headers();
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex, follow', true);
    echo $xml;
    exit;
});

// Bust the feed cache when any product changes
add_action('save_post_product',         function () { delete_transient('ng_merchant_feed_xml'); });
add_action('woocommerce_update_product', function () { delete_transient('ng_merchant_feed_xml'); });
add_action('deleted_post',              function () { delete_transient('ng_merchant_feed_xml'); });

function ng_merchant_build_feed() {
    $brand_default = (string) get_option('ng_default_brand', 'NeoGen Store');
    $cond_default  = (string) get_option('ng_default_condition', 'new');
    $google_cat    = (string) get_option('ng_google_product_category', '');
    $currency      = get_woocommerce_currency();
    $title         = get_bloginfo('name') . ' — Product feed';
    $home          = home_url('/');

    $products = wc_get_products([
        'status'  => 'publish',
        'limit'   => -1,
        'type'    => ['simple', 'variable'],
        'visibility' => 'visible',
    ]);

    $items = '';
    foreach ( (array) $products as $product ) {
        if ( ! $product instanceof WC_Product ) continue;

        if ( $product->is_type('variable') ) {
            // Emit each variation as a separate offer
            foreach ( $product->get_available_variations() as $v ) {
                $variation = wc_get_product( (int) $v['variation_id'] );
                if ( $variation instanceof WC_Product_Variation ) {
                    $items .= ng_merchant_render_item( $variation, $product, $brand_default, $cond_default, $google_cat, $currency );
                }
            }
        } else {
            $items .= ng_merchant_render_item( $product, null, $brand_default, $cond_default, $google_cat, $currency );
        }
    }

    $self = home_url('/feed/google-merchant/');
    $now  = gmdate('D, d M Y H:i:s') . ' GMT';

    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n"
        . '<channel>' . "\n"
        . '  <title>' . esc_html($title) . '</title>' . "\n"
        . '  <link>' . esc_url($home) . '</link>' . "\n"
        . '  <description>Google Merchant Center product feed</description>' . "\n"
        . '  <atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="' . esc_url($self) . '" rel="self" type="application/rss+xml" />' . "\n"
        . '  <pubDate>' . esc_html($now) . '</pubDate>' . "\n"
        . $items
        . '</channel>' . "\n"
        . '</rss>' . "\n";
}

function ng_merchant_render_item( $product, $parent, $brand_default, $cond_default, $google_cat, $currency ) {
    $id     = $product->get_id();
    $sku    = (string) $product->get_sku();
    $title  = $product->get_name();
    if ( $parent && trim($title) === '' ) $title = $parent->get_name();
    $desc   = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
    if ( $desc === '' && $parent ) {
        $desc = wp_strip_all_tags( $parent->get_short_description() ?: $parent->get_description() );
    }
    $desc   = mb_substr( trim($desc), 0, 5000 );
    $link   = $parent ? get_permalink( $parent->get_id() ) . '?variation_id=' . $id : get_permalink( $id );

    $img_id  = $product->get_image_id();
    if ( ! $img_id && $parent ) $img_id = $parent->get_image_id();
    $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';

    $additional_imgs = [];
    $gallery_ids = $product->get_gallery_image_ids();
    if ( empty($gallery_ids) && $parent ) $gallery_ids = $parent->get_gallery_image_ids();
    foreach ( array_slice($gallery_ids, 0, 10) as $gid ) {
        $u = wp_get_attachment_image_url( (int) $gid, 'large' );
        if ( $u ) $additional_imgs[] = $u;
    }

    $price = $product->get_price();
    if ( $price === '' || ! is_numeric($price) ) return '';

    $availability = $product->is_in_stock() ? 'in stock' : 'out of stock';

    $brand = (string) $product->get_meta('_ng_brand');
    if ( $brand === '' && $parent ) $brand = (string) $parent->get_meta('_ng_brand');
    if ( $brand === '' ) {
        $brands = wp_get_post_terms( $parent ? $parent->get_id() : $id, 'product_brand', ['fields' => 'names'] );
        if ( ! is_wp_error($brands) && ! empty($brands) ) $brand = $brands[0];
    }
    if ( $brand === '' ) $brand = $brand_default;

    $gtin = (string) $product->get_meta('_ng_gtin');
    $mpn  = (string) $product->get_meta('_ng_mpn');
    if ( $mpn === '' ) $mpn = $sku;

    $cat_path = '';
    $cat_terms = get_the_terms( $parent ? $parent->get_id() : $id, 'product_cat' );
    if ( ! is_wp_error($cat_terms) && ! empty($cat_terms) ) {
        $cat_path = $cat_terms[0]->name;
    }

    $price_str = number_format( (float) $price, 2, '.', '' ) . ' ' . $currency;

    $sale = $product->get_sale_price();
    $sale_price_xml = '';
    if ( is_numeric($sale) && (float) $sale > 0 && (float) $sale < (float) $product->get_regular_price() ) {
        $sale_price_xml = '    <g:sale_price>' . esc_html( number_format( (float) $sale, 2, '.', '' ) . ' ' . $currency ) . '</g:sale_price>' . "\n";
    }

    $additional_xml = '';
    foreach ( $additional_imgs as $u ) {
        $additional_xml .= '    <g:additional_image_link>' . esc_url($u) . '</g:additional_image_link>' . "\n";
    }

    $google_cat_xml = $google_cat !== '' ? '    <g:google_product_category>' . esc_html($google_cat) . '</g:google_product_category>' . "\n" : '';
    $gtin_xml       = $gtin !== ''       ? '    <g:gtin>' . esc_html($gtin) . '</g:gtin>' . "\n" : '';
    $weight = (float) $product->get_weight();
    $weight_xml = $weight > 0 ? '    <g:shipping_weight>' . esc_html( $weight . ' kg' ) . '</g:shipping_weight>' . "\n" : '';

    $feed_id = $sku !== '' ? $sku : ('id-' . $id);

    return '  <item>' . "\n"
        . '    <g:id>' . esc_html($feed_id) . '</g:id>' . "\n"
        . '    <g:title>' . esc_html($title) . '</g:title>' . "\n"
        . '    <g:description>' . esc_html($desc) . '</g:description>' . "\n"
        . '    <g:link>' . esc_url($link) . '</g:link>' . "\n"
        . ( $img_url ? '    <g:image_link>' . esc_url($img_url) . '</g:image_link>' . "\n" : '' )
        . $additional_xml
        . '    <g:availability>' . esc_html($availability) . '</g:availability>' . "\n"
        . '    <g:price>' . esc_html($price_str) . '</g:price>' . "\n"
        . $sale_price_xml
        . '    <g:condition>' . esc_html($cond_default) . '</g:condition>' . "\n"
        . '    <g:brand>' . esc_html($brand) . '</g:brand>' . "\n"
        . '    <g:mpn>' . esc_html($mpn) . '</g:mpn>' . "\n"
        . $gtin_xml
        . ( $cat_path !== '' ? '    <g:product_type>' . esc_html($cat_path) . '</g:product_type>' . "\n" : '' )
        . $google_cat_xml
        . $weight_xml
        . '    <g:identifier_exists>' . ( ( $gtin !== '' || $mpn !== '' ) ? 'yes' : 'no' ) . '</g:identifier_exists>' . "\n"
        . '  </item>' . "\n";
}
