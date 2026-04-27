<?php
/**
 * Plugin Name: NeoGen Merchant
 * Description: Google Merchant Center product feed at /feed/google-merchant/, Schema.org Product+Offer JSON-LD on single product pages, and Google Site Verification meta tag.
 * Version: 1.12.1
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
      <h2>Feed URLs</h2>
      <p>Pick whichever Merchant Center prefers — both contain the same data.</p>
      <p><strong>XML (RSS 2.0):</strong></p>
      <p><code style="user-select:all; padding:8px; background:#f0f0f1; display:inline-block;"><?php echo esc_html( $feed_url ); ?></code></p>
      <p><strong>TXT (tab-separated):</strong></p>
      <p><code style="user-select:all; padding:8px; background:#f0f0f1; display:inline-block;"><?php echo esc_html( home_url('/feed/google-merchant.txt') ); ?></code></p>
      <p>
        <a class="button" href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener">Open XML feed</a>
        <a class="button" href="<?php echo esc_url( home_url('/feed/google-merchant.txt') ); ?>" target="_blank" rel="noopener">Open TXT feed</a>
        <a class="button" href="<?php echo esc_url( add_query_arg('ng_merchant_flush_cache', '1', $feed_url) ); ?>" target="_blank" rel="noopener">XML (skip cache)</a>
        <a class="button" href="<?php echo esc_url( add_query_arg('ng_merchant_flush_cache', '1', home_url('/feed/google-merchant.txt')) ); ?>" target="_blank" rel="noopener">TXT (skip cache)</a>
      </p>
      <p class="description">Both feeds are cached for 1 hour. Saving this page and changing any product invalidate both caches automatically.</p>
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
    if ( function_exists('ng_gift_card_image_url') ) {
        $gift_img_url = ng_gift_card_image_url( $product );
        if ( $gift_img_url !== '' ) {
            $img_url = $gift_img_url;
        }
    }

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

    $schema_name = $product->get_name();
    $schema_desc = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
    if ( function_exists('ng_gift_card_clean_product_name') ) {
        $schema_name = ng_gift_card_clean_product_name( $schema_name );
        $schema_desc = ng_gift_card_clean_product_name( $schema_desc );
    }

    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        '@id'         => get_permalink( $product->get_id() ) . '#product',
        'name'        => $schema_name,
        'description' => $schema_desc,
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
    add_rewrite_rule( '^feed/google-merchant/?$',     'index.php?ng_merchant_feed=xml', 'top' );
    add_rewrite_rule( '^feed/google-merchant\.txt$',  'index.php?ng_merchant_feed=tsv', 'top' );
    add_rewrite_rule( '^feed/google-merchant\.tsv$',  'index.php?ng_merchant_feed=tsv', 'top' );
    add_rewrite_tag( '%ng_merchant_feed%', '(xml|tsv)' );
});

// One-shot flush after activation/route change
add_action('init', function () {
    if ( get_option('ng_merchant_rules_flushed_v2') !== '1' ) {
        flush_rewrite_rules(false);
        update_option('ng_merchant_rules_flushed_v2', '1');
    }
}, 99);

add_action('template_redirect', function () {
    $fmt = (string) get_query_var('ng_merchant_feed');
    if ( $fmt !== 'xml' && $fmt !== 'tsv' ) return;
    if ( ! function_exists('wc_get_products') ) {
        status_header(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo "WooCommerce not available.\n";
        exit;
    }

    $skip_cache = isset($_GET['ng_merchant_flush_cache']) && current_user_can('manage_options');
    $cache_key  = $fmt === 'tsv' ? 'ng_merchant_feed_tsv' : 'ng_merchant_feed_xml';
    $cached = $skip_cache ? false : get_transient($cache_key);

    if ( $cached === false ) {
        $items = ng_merchant_collect_items();
        $cached = $fmt === 'tsv'
            ? ng_merchant_render_tsv( $items )
            : ng_merchant_render_xml( $items );
        set_transient($cache_key, $cached, HOUR_IN_SECONDS);
    }

    nocache_headers();
    header('Content-Type: ' . ( $fmt === 'tsv' ? 'text/tab-separated-values' : 'application/xml' ) . '; charset=utf-8');
    header('X-Robots-Tag: noindex, follow', true);
    echo $cached;
    exit;
});

// Bust both feed caches when any product changes
add_action('save_post_product',          function () {
    delete_transient('ng_merchant_feed_xml');
    delete_transient('ng_merchant_feed_tsv');
});
add_action('woocommerce_update_product', function () {
    delete_transient('ng_merchant_feed_xml');
    delete_transient('ng_merchant_feed_tsv');
});
add_action('deleted_post', function () {
    delete_transient('ng_merchant_feed_xml');
    delete_transient('ng_merchant_feed_tsv');
});

/**
 * Walk all visible Woo products and produce a flat list of associative
 * arrays — one per simple product or variation. Used by both the XML
 * and TSV renderers.
 */
function ng_merchant_collect_items() {
    $brand_default = (string) get_option('ng_default_brand', 'NeoGen Store');
    $cond_default  = (string) get_option('ng_default_condition', 'new');
    $google_cat    = (string) get_option('ng_google_product_category', '');
    $currency      = get_woocommerce_currency();

    $products = wc_get_products([
        'status'     => 'publish',
        'limit'      => -1,
        'type'       => ['simple', 'variable'],
        'visibility' => 'visible',
    ]);

    $items = [];
    foreach ( (array) $products as $product ) {
        if ( ! $product instanceof WC_Product ) continue;

        if ( $product->is_type('variable') ) {
            foreach ( $product->get_available_variations() as $v ) {
                $variation = wc_get_product( (int) $v['variation_id'] );
                if ( $variation instanceof WC_Product ) {
                    $row = ng_merchant_item_data( $variation, $product, $brand_default, $cond_default, $google_cat, $currency );
                    if ( $row ) $items[] = $row;
                }
            }
        } else {
            $row = ng_merchant_item_data( $product, null, $brand_default, $cond_default, $google_cat, $currency );
            if ( $row ) $items[] = $row;
        }
    }

    return $items;
}

/**
 * Build the canonical attribute set for a single product/variation.
 * Returns null if the item lacks required data (price/link/etc.).
 */
function ng_merchant_item_data( $product, $parent, $brand_default, $cond_default, $google_cat, $currency ) {
    $id  = $product->get_id();
    $sku = (string) $product->get_sku();

    $title = $product->get_name();
    if ( $parent && trim($title) === '' ) $title = $parent->get_name();
    if ( function_exists('ng_gift_card_clean_product_name') ) {
        $title = ng_gift_card_clean_product_name( $title );
    }
    $title = mb_substr( trim($title), 0, 150 );

    $desc = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
    if ( $desc === '' && $parent ) {
        $desc = wp_strip_all_tags( $parent->get_short_description() ?: $parent->get_description() );
    }
    if ( function_exists('ng_gift_card_clean_product_name') ) {
        $desc = ng_gift_card_clean_product_name( $desc );
    }
    $desc = mb_substr( trim($desc), 0, 5000 );

    $link = $parent ? $product->get_permalink() : get_permalink( $id );
    if ( ! $link ) return null;

    $img_id  = $product->get_image_id();
    if ( ! $img_id && $parent ) $img_id = $parent->get_image_id();
    $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : '';
    $has_gift_card_image = false;
    if ( function_exists('ng_gift_card_image_url') ) {
        $gift_img_url = ng_gift_card_image_url( $product, $parent );
        if ( $gift_img_url !== '' ) {
            $img_url = $gift_img_url;
            $has_gift_card_image = true;
        }
    }

    $additional_imgs = [];
    if ( ! $has_gift_card_image ) {
        $gallery_ids = $product->get_gallery_image_ids();
        if ( empty($gallery_ids) && $parent ) $gallery_ids = $parent->get_gallery_image_ids();
        foreach ( array_slice($gallery_ids, 0, 10) as $gid ) {
            $u = wp_get_attachment_image_url( (int) $gid, 'large' );
            if ( $u ) $additional_imgs[] = $u;
        }
    }

    $price = $product->get_price();
    if ( $price === '' || ! is_numeric($price) ) return null;

    if ( $product->is_in_stock() ) {
        $availability = $product->is_on_backorder() ? 'backorder' : 'in_stock';
    } else {
        $availability = 'out_of_stock';
    }

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

    $sale_str = '';
    $sale     = $product->get_sale_price();
    if ( is_numeric($sale) && (float) $sale > 0 && (float) $sale < (float) $product->get_regular_price() ) {
        $sale_str = number_format( (float) $sale, 2, '.', '' ) . ' ' . $currency;
    }

    $weight     = (float) $product->get_weight();
    $weight_str = $weight > 0 ? ( $weight . ' kg' ) : '';

    $feed_id = $sku !== '' ? $sku : ( 'id-' . $id );

    $item_group_id = '';
    if ( $parent ) {
        $parent_sku = (string) $parent->get_sku();
        $item_group_id = $parent_sku !== '' ? $parent_sku : ( 'group-' . $parent->get_id() );
    }

    return [
        'id'                       => $feed_id,
        'title'                    => $title,
        'description'              => $desc,
        'link'                     => $link,
        'image_link'               => $img_url,
        'availability'             => $availability,
        'price'                    => $price_str,
        'sale_price'               => $sale_str,
        'brand'                    => $brand,
        'mpn'                      => $mpn,
        'gtin'                     => $gtin,
        'condition'                => $cond_default,
        'product_type'             => $cat_path,
        'google_product_category'  => $google_cat,
        'item_group_id'            => $item_group_id,
        'content_language'         => 'ar',
        'target_country'           => 'SA',
        'shipping_weight'          => $weight_str,
        'additional_image_link'    => implode(',', $additional_imgs),
    ];
}

/**
 * Render the collected item rows as Google Merchant XML feed.
 */
function ng_merchant_render_xml( $items ) {
    $title = get_bloginfo('name') . ' — Product feed';
    $home  = home_url('/');
    $self  = home_url('/feed/google-merchant/');
    $now   = gmdate('D, d M Y H:i:s') . ' GMT';

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n"
        . '<channel>' . "\n"
        . '  <title>' . esc_html($title) . '</title>' . "\n"
        . '  <link>' . esc_url($home) . '</link>' . "\n"
        . '  <description>Google Merchant Center product feed</description>' . "\n"
        . '  <atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="' . esc_url($self) . '" rel="self" type="application/rss+xml" />' . "\n"
        . '  <pubDate>' . esc_html($now) . '</pubDate>' . "\n";

    foreach ( $items as $r ) {
        $xml .= '  <item>' . "\n"
            . '    <g:id>' . esc_html($r['id']) . '</g:id>' . "\n"
            . '    <g:title>' . esc_html($r['title']) . '</g:title>' . "\n"
            . '    <g:description>' . esc_html($r['description']) . '</g:description>' . "\n"
            . '    <g:link>' . esc_url($r['link']) . '</g:link>' . "\n"
            . ( $r['image_link'] !== '' ? '    <g:image_link>' . esc_url($r['image_link']) . '</g:image_link>' . "\n" : '' );

        if ( $r['additional_image_link'] !== '' ) {
            foreach ( explode(',', $r['additional_image_link']) as $u ) {
                $u = trim($u);
                if ( $u !== '' ) {
                    $xml .= '    <g:additional_image_link>' . esc_url($u) . '</g:additional_image_link>' . "\n";
                }
            }
        }

        $xml .= '    <g:availability>' . esc_html($r['availability']) . '</g:availability>' . "\n"
            . '    <g:price>' . esc_html($r['price']) . '</g:price>' . "\n";

        if ( $r['sale_price'] !== '' ) {
            $xml .= '    <g:sale_price>' . esc_html($r['sale_price']) . '</g:sale_price>' . "\n";
        }

        $xml .= '    <g:condition>' . esc_html($r['condition']) . '</g:condition>' . "\n"
            . '    <g:brand>' . esc_html($r['brand']) . '</g:brand>' . "\n"
            . '    <g:mpn>' . esc_html($r['mpn']) . '</g:mpn>' . "\n";

        if ( $r['gtin'] !== '' ) {
            $xml .= '    <g:gtin>' . esc_html($r['gtin']) . '</g:gtin>' . "\n";
        }
        if ( $r['product_type'] !== '' ) {
            $xml .= '    <g:product_type>' . esc_html($r['product_type']) . '</g:product_type>' . "\n";
        }
        if ( $r['google_product_category'] !== '' ) {
            $xml .= '    <g:google_product_category>' . esc_html($r['google_product_category']) . '</g:google_product_category>' . "\n";
        }
        if ( $r['item_group_id'] !== '' ) {
            $xml .= '    <g:item_group_id>' . esc_html($r['item_group_id']) . '</g:item_group_id>' . "\n";
        }
        if ( $r['shipping_weight'] !== '' ) {
            $xml .= '    <g:shipping_weight>' . esc_html($r['shipping_weight']) . '</g:shipping_weight>' . "\n";
        }

        $xml .= '    <g:content_language>' . esc_html($r['content_language']) . '</g:content_language>' . "\n"
            . '    <g:target_country>' . esc_html($r['target_country']) . '</g:target_country>' . "\n"
            . '  </item>' . "\n";
    }

    $xml .= '</channel>' . "\n" . '</rss>' . "\n";
    return $xml;
}

/**
 * Render the collected item rows as Google Merchant TSV feed.
 * Tabs and newlines inside any cell are replaced with single spaces.
 */
function ng_merchant_render_tsv( $items ) {
    $cols = [
        'id', 'title', 'description', 'link', 'image_link', 'availability',
        'price', 'sale_price', 'brand', 'mpn', 'gtin', 'condition',
        'product_type', 'google_product_category', 'item_group_id',
        'content_language', 'target_country', 'shipping_weight',
        'additional_image_link',
    ];

    $clean = function ( $v ) {
        $v = (string) $v;
        // TSV cells must not contain tabs or newlines
        $v = str_replace( ["\t", "\r\n", "\r", "\n"], ' ', $v );
        // collapse repeated whitespace
        $v = preg_replace( '/\s+/u', ' ', $v );
        return trim($v);
    };

    $out = implode("\t", $cols) . "\n";
    foreach ( $items as $r ) {
        $row = [];
        foreach ( $cols as $c ) {
            $row[] = $clean( $r[$c] ?? '' );
        }
        $out .= implode("\t", $row) . "\n";
    }
    return $out;
}
