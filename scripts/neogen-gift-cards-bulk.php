<?php
/**
 * Bulk-generate gift-card sub-categories + products inspired by LikeCard.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-gift-cards-bulk.php --skip-plugins=litespeed-cache --user=1
 *
 * Creates:
 *   - 4 sub-categories under product_cat 'gift-cards': game-cards,
 *     app-stores, subscriptions, software.
 *   - ~70 gift-card products spanning ~14 brands. Each brand has 1 to N
 *     denominations and 1 to N regional variants. SKU pattern:
 *     GC-<brand>-<region>-<denom> (e.g., GC-PSN-US-50).
 *   - For brands with an existing brand image already in WP media library
 *     (Apple, PSN, Xbox, Steam, Google Play, Netflix, Adobe, Kaspersky,
 *     Office, Windows), reuses that image as the featured image.
 *   - For new brands (Razer, Spotify, Disney+, Roblox, PUBG, Free Fire,
 *     Mobile Legends, PS Plus), generates a small SVG placeholder
 *     (brand name on a brand-colored background), uploads it to media,
 *     and uses it as the featured image.
 *
 * Idempotent: matches by SKU. Existing products are updated, not duplicated.
 *
 * USD → SAR conversion: $1 USD = 3.75 SAR with a 7% markup (operator margin).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
    WP_CLI::error( 'WooCommerce is not loaded.' );
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

// ---------- 1. Ensure sub-categories under gift-cards ----------
$gc_parent = get_term_by( 'slug', 'gift-cards', 'product_cat' );
if ( ! $gc_parent ) {
    WP_CLI::error( 'Parent term gift-cards not found.' );
}
$sub_cats = array(
    'game-cards'    => array( 'name' => 'بطاقات الألعاب',         'desc' => 'PlayStation · Xbox · Steam · Razer · ألعاب الجوال' ),
    'app-stores'   => array( 'name' => 'متاجر التطبيقات',          'desc' => 'Apple iTunes · Google Play' ),
    'subscriptions' => array( 'name' => 'اشتراكات رقمية',          'desc' => 'Netflix · Spotify · Disney+ · PlayStation Plus' ),
    'software'      => array( 'name' => 'مفاتيح برامج',            'desc' => 'Adobe · Kaspersky · Microsoft Office · Windows' ),
);
$sub_cat_ids = array();
foreach ( $sub_cats as $slug => $meta ) {
    $existing = get_term_by( 'slug', $slug, 'product_cat' );
    if ( $existing ) {
        wp_update_term( $existing->term_id, 'product_cat', array(
            'name'        => $meta['name'],
            'description' => $meta['desc'],
            'parent'      => $gc_parent->term_id,
        ) );
        $sub_cat_ids[ $slug ] = $existing->term_id;
    } else {
        $r = wp_insert_term( $meta['name'], 'product_cat', array(
            'slug'        => $slug,
            'parent'      => $gc_parent->term_id,
            'description' => $meta['desc'],
        ) );
        if ( ! is_wp_error( $r ) ) {
            $sub_cat_ids[ $slug ] = (int) $r['term_id'];
        }
    }
}
WP_CLI::log( 'Sub-categories ready: ' . implode( ', ', array_keys( $sub_cat_ids ) ) );

// ---------- 2. Brand → image-attachment-id resolver ----------
$resolve_attachment_by_filename = function ( $filename ) {
    global $wpdb;
    $like = '%' . $wpdb->esc_like( $filename );
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
          WHERE post_type='attachment' AND guid LIKE %s LIMIT 1",
        $like
    ) );
    return ! empty( $ids ) ? (int) $ids[0] : 0;
};

// ---------- 3. Generate SVG placeholder for new brands ----------
$svg_placeholder_id = function ( $brand_slug, $brand_name, $bg = '#0F172A', $fg = '#FFFFFF' ) {
    $upload = wp_upload_dir();
    $name   = 'gc-placeholder-' . $brand_slug . '.svg';
    $path   = $upload['basedir'] . '/' . $name;
    $url    = $upload['baseurl'] . '/' . $name;

    if ( ! file_exists( $path ) ) {
        $label = htmlspecialchars( $brand_name, ENT_QUOTES, 'UTF-8' );
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400" preserveAspectRatio="xMidYMid slice">'
            . '<rect width="600" height="400" fill="%1$s"/>'
            . '<rect x="20" y="20" width="560" height="360" fill="none" stroke="%2$s" stroke-opacity="0.3" stroke-width="2" rx="14"/>'
            . '<text x="300" y="200" font-family="Inter, system-ui, sans-serif" font-size="48" font-weight="700" fill="%2$s" text-anchor="middle" dominant-baseline="middle">%3$s</text>'
            . '<text x="300" y="270" font-family="Inter, system-ui, sans-serif" font-size="18" font-weight="400" fill="%2$s" fill-opacity="0.7" text-anchor="middle" dominant-baseline="middle">GIFT CARD</text>'
            . '</svg>',
            esc_attr( $bg ),
            esc_attr( $fg ),
            $label
        );
        file_put_contents( $path, $svg );
    }

    // Look up existing attachment first.
    global $wpdb;
    $like = '%' . $wpdb->esc_like( $name );
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND guid LIKE %s LIMIT 1",
        $like
    ) );
    if ( $existing ) { return (int) $existing; }

    $attachment = array(
        'guid'           => $url,
        'post_mime_type' => 'image/svg+xml',
        'post_title'     => $brand_name,
        'post_content'   => '',
        'post_status'    => 'inherit',
    );
    $att_id = wp_insert_attachment( $attachment, $path );
    if ( ! is_wp_error( $att_id ) && $att_id ) {
        // SVG metadata is minimal; skip wp_generate_attachment_metadata.
        return $att_id;
    }
    return 0;
};

// ---------- 4. Brand catalog ----------
// USD denominations; SAR price = denom * 3.75 * 1.07 (operator markup).
$rate = function ( $usd ) { return (int) round( $usd * 3.75 * 1.07 ); };

// Each brand defines: name_en, name_ar, sub-cat, image (filename or null
// for placeholder), placeholder_bg (if no image), denominations, regions,
// sku_prefix, currency_label.
$brands = array(

    // === GAME CARDS ===
    array(
        'slug' => 'playstation', 'name_en' => 'PlayStation Store', 'name_ar' => 'بلايستيشن ستور',
        'sub' => 'game-cards', 'image' => 'psn50.png', 'sku_prefix' => 'GC-PSN',
        'denoms' => array( 10, 25, 50, 100 ), 'regions' => array( 'US', 'KSA' ),
    ),
    array(
        'slug' => 'xbox', 'name_en' => 'Xbox Live', 'name_ar' => 'إكس بوكس لايف',
        'sub' => 'game-cards', 'image' => 'xbox-gp.png', 'sku_prefix' => 'GC-XBX',
        'denoms' => array( 10, 25, 50, 100 ), 'regions' => array( 'US', 'KSA', 'UK' ),
    ),
    array(
        'slug' => 'steam', 'name_en' => 'Steam Wallet', 'name_ar' => 'محفظة ستيم',
        'sub' => 'game-cards', 'image' => 'steam50.png', 'sku_prefix' => 'GC-STM',
        'denoms' => array( 5, 10, 20, 50, 100 ), 'regions' => array( 'Global' ),
    ),
    array(
        'slug' => 'razer-gold', 'name_en' => 'Razer Gold', 'name_ar' => 'رازر قولد',
        'sub' => 'game-cards', 'image' => null, 'placeholder_bg' => '#00B73B',
        'sku_prefix' => 'GC-RZR',
        'denoms' => array( 10, 25, 50, 100 ), 'regions' => array( 'Global' ),
    ),
    array(
        'slug' => 'roblox', 'name_en' => 'Roblox', 'name_ar' => 'روبلكس',
        'sub' => 'game-cards', 'image' => null, 'placeholder_bg' => '#00A2FF',
        'sku_prefix' => 'GC-RBX',
        'denoms' => array( 10, 25, 50, 100 ), 'regions' => array( 'US' ),
    ),
    array(
        'slug' => 'pubg-mobile', 'name_en' => 'PUBG Mobile UC', 'name_ar' => 'ببجي موبايل UC',
        'sub' => 'game-cards', 'image' => null, 'placeholder_bg' => '#F2A900',
        'sku_prefix' => 'GC-PBG', 'denom_label_unit' => ' UC',
        'denoms' => array( 60, 325, 660, 1800, 3850 ), 'regions' => array( 'Global' ),
    ),
    array(
        'slug' => 'free-fire', 'name_en' => 'Free Fire Diamonds', 'name_ar' => 'فري فاير الماس',
        'sub' => 'game-cards', 'image' => null, 'placeholder_bg' => '#E63946',
        'sku_prefix' => 'GC-FRF', 'denom_label_unit' => ' Diamonds',
        'denoms' => array( 100, 310, 1080, 2200 ), 'regions' => array( 'Global' ),
    ),
    array(
        'slug' => 'mobile-legends', 'name_en' => 'Mobile Legends Diamonds', 'name_ar' => 'موبايل ليجندز الماس',
        'sub' => 'game-cards', 'image' => null, 'placeholder_bg' => '#1E3A8A',
        'sku_prefix' => 'GC-MLB', 'denom_label_unit' => ' Diamonds',
        'denoms' => array( 86, 172, 706, 2195 ), 'regions' => array( 'Global' ),
    ),

    // === APP STORES ===
    array(
        'slug' => 'apple-itunes', 'name_en' => 'Apple iTunes / App Store', 'name_ar' => 'بطاقة آبل أيتونز',
        'sub' => 'app-stores', 'image' => 'apple.png', 'sku_prefix' => 'GC-APL',
        'denoms' => array( 10, 25, 50, 100 ), 'regions' => array( 'US', 'KSA' ),
    ),
    array(
        'slug' => 'google-play', 'name_en' => 'Google Play', 'name_ar' => 'قوقل بلاي',
        'sub' => 'app-stores', 'image' => 'google-play.png', 'sku_prefix' => 'GC-GP',
        'denoms' => array( 10, 25, 50, 100 ), 'regions' => array( 'US', 'KSA' ),
    ),

    // === SUBSCRIPTIONS ===
    array(
        'slug' => 'netflix', 'name_en' => 'Netflix', 'name_ar' => 'نتفلكس',
        'sub' => 'subscriptions', 'image' => 'netflix.png', 'sku_prefix' => 'GC-NFX',
        'denoms' => array( 25, 50, 100 ), 'regions' => array( 'US', 'KSA' ),
    ),
    array(
        'slug' => 'spotify', 'name_en' => 'Spotify Premium', 'name_ar' => 'سبوتيفاي بريميوم',
        'sub' => 'subscriptions', 'image' => null, 'placeholder_bg' => '#1DB954',
        'sku_prefix' => 'GC-SPF',
        'denoms' => array( 30, 60, 99 ), 'regions' => array( 'Global' ),
    ),
    array(
        'slug' => 'disney-plus', 'name_en' => 'Disney+', 'name_ar' => 'ديزني بلس',
        'sub' => 'subscriptions', 'image' => null, 'placeholder_bg' => '#0F1A2E',
        'sku_prefix' => 'GC-DSP',
        'denoms' => array( 25, 80 ), 'regions' => array( 'US' ),
    ),
    array(
        'slug' => 'playstation-plus', 'name_en' => 'PlayStation Plus', 'name_ar' => 'بلايستيشن بلس',
        'sub' => 'subscriptions', 'image' => null, 'placeholder_bg' => '#0070D1',
        'sku_prefix' => 'GC-PSP', 'denom_label_unit' => ' Months',
        'denoms' => array( 1, 3, 12 ), 'regions' => array( 'US', 'KSA' ),
    ),
);

// ---------- 5. Loop, create / update each (brand × region × denom) ----------
$created = 0;
$updated = 0;
$failed  = array();

foreach ( $brands as $brand ) {
    $img_id = 0;
    if ( ! empty( $brand['image'] ) ) {
        $img_id = $resolve_attachment_by_filename( $brand['image'] );
    }
    if ( ! $img_id ) {
        $bg = isset( $brand['placeholder_bg'] ) ? $brand['placeholder_bg'] : '#0F172A';
        $img_id = $svg_placeholder_id( $brand['slug'], $brand['name_en'], $bg, '#FFFFFF' );
    }

    foreach ( $brand['regions'] as $region ) {
        foreach ( $brand['denoms'] as $denom ) {
            $sku       = sprintf( '%s-%s-%d', $brand['sku_prefix'], strtoupper( $region ), (int) $denom );
            $unit      = isset( $brand['denom_label_unit'] ) ? $brand['denom_label_unit'] : null;
            $sar_price = $unit ? max( 5, (int) round( $denom * 0.04 ) ) : $rate( $denom );
            // For Diamonds/UC products, derive SAR roughly: $1 ≈ 25 of those units → 0.04 SAR/unit. Floor 5 SAR.

            $name_en = $unit
                ? sprintf( '%s %s%s (%s)', $brand['name_en'], (int) $denom, $unit, $region )
                : sprintf( '%s $%d (%s)', $brand['name_en'], (int) $denom, $region );
            $name_ar = $unit
                ? sprintf( '%s %s%s (%s)', $brand['name_ar'], (int) $denom, $unit, $region )
                : sprintf( '%s %d دولار (%s)', $brand['name_ar'], (int) $denom, $region );

            $existing_id = wc_get_product_id_by_sku( $sku );
            if ( $existing_id ) {
                $product = wc_get_product( $existing_id );
            } else {
                $product = new WC_Product_Simple();
            }

            $product->set_name( $name_en . ' | ' . $name_ar );
            $product->set_status( 'publish' );
            $product->set_sku( $sku );
            $product->set_short_description( sprintf( 'بطاقة %s — %s × $%d. تسليم فوري.', $brand['name_ar'], $region, (int) $denom ) );
            $product->set_description( sprintf(
                "<p>بطاقة <strong>%s</strong> بقيمة %s%s لمنطقة <strong>%s</strong>.</p>"
                . "<p>التسليم فوري عبر البريد الإلكتروني خلال دقائق من إتمام الدفع. الرمز يفعّل عبر التطبيق/المتجر الرسمي للبطاقة.</p>",
                esc_html( $brand['name_ar'] ),
                (int) $denom,
                $unit ? esc_html( $unit ) : ' دولار',
                esc_html( $region )
            ) );
            $product->set_regular_price( (string) $sar_price );
            $product->set_manage_stock( false );
            $product->set_stock_status( 'instock' );
            $product->set_tax_status( 'taxable' );
            $product->set_virtual( true );
            $product->set_sold_individually( false );
            $product->set_catalog_visibility( 'visible' );

            // Categories: gift-cards (parent) + sub-cat
            $cat_ids = array( $gc_parent->term_id );
            if ( isset( $sub_cat_ids[ $brand['sub'] ] ) ) {
                $cat_ids[] = $sub_cat_ids[ $brand['sub'] ];
            }
            $product->set_category_ids( $cat_ids );

            if ( $img_id ) {
                $product->set_image_id( $img_id );
            }

            $id = $product->save();
            if ( ! $id ) {
                $failed[] = $sku;
                continue;
            }

            // Region + brand metas — wires to the future region-tab filter.
            update_post_meta( $id, '_ng_gift_card_region', strtolower( $region ) );
            update_post_meta( $id, '_ng_gift_card_brand', $brand['slug'] );
            update_post_meta( $id, '_ng_gift_card_denom', (int) $denom );
            update_post_meta( $id, '_ng_gift_card_seed_version', '1.33.0' );

            if ( $existing_id ) { $updated++; } else { $created++; }
        }
    }
}

WP_CLI::log( '---' );
WP_CLI::log( "Created: $created" );
WP_CLI::log( "Updated: $updated" );
if ( $failed ) {
    WP_CLI::log( '---' );
    WP_CLI::log( 'Failed SKUs:' );
    foreach ( $failed as $f ) { WP_CLI::log( "  $f" ); }
}

global $wpdb;
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p
    JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
    JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
    JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
    WHERE p.post_type='product' AND p.post_status='publish'
      AND tt.taxonomy='product_cat' AND t.slug='gift-cards'" );
WP_CLI::log( "Total products in gift-cards (incl. sub-cats): $total" );
