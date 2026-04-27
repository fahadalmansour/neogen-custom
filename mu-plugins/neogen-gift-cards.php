<?php
/**
 * Plugin Name: NeoGen Gift Card Assets
 * Description: Local LikeCard-derived clean gift-card artwork and promo-copy cleanup.
 * Version: 1.0.0
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

if (!defined('NG_THEME_ASSET_DIR')) {
    define('NG_THEME_ASSET_DIR', __DIR__ . '/neogen-theme-assets');
}
if (!defined('NG_THEME_ASSET_URL')) {
    $ng_gift_asset_rel = str_replace(
        wp_normalize_path(WP_CONTENT_DIR),
        '',
        wp_normalize_path(NG_THEME_ASSET_DIR)
    );
    define('NG_THEME_ASSET_URL', content_url($ng_gift_asset_rel));
}

/**
 * Local clean card artwork copied from matching LikeCard product/category pages.
 * Avoid promotional bonus artwork from temporary offer products.
 */
function ng_gift_card_asset_map() {
    $map = [
        'apple' => [
            'files'    => ['apple.webp'],
            'keywords' => ['apple', 'itunes', 'آبل', 'ابل', 'ايتونز'],
        ],
        'google-play' => [
            'files'    => ['google-play.webp', 'googleplay.webp'],
            'keywords' => ['google play', 'googleplay', 'قوقل بلاي', 'جوجل بلاي'],
        ],
        'playstation' => [
            'files'    => ['playstation.webp', 'psn.webp'],
            'keywords' => ['playstation', 'play station', 'psn', 'بلايستيشن', 'بلاي ستيشن'],
        ],
        'steam' => [
            'files'    => ['steam.webp'],
            'keywords' => ['steam', 'ستيم'],
        ],
        'xbox' => [
            'files'    => ['xbox.webp', 'game-pass.webp'],
            'keywords' => ['xbox', 'game pass', 'اكس بوكس', 'إكس بوكس', 'قيم باس', 'جيم باس'],
        ],
        'amazon' => [
            'files'    => ['amazon.webp', 'amazon-prime.webp', 'prime.webp'],
            'keywords' => ['amazon', 'prime', 'أمازون', 'امازون'],
        ],
        'kaspersky' => [
            'files'    => ['kaspersky.webp'],
            'keywords' => ['kaspersky', 'كاسبرسكي'],
        ],
        'adobe' => [
            'files'    => ['adobe.webp', 'creative-cloud.webp'],
            'keywords' => ['adobe', 'creative cloud', 'أدوبي', 'ادوبي'],
        ],
        'office' => [
            'files'    => ['office.webp', 'office2024.webp', 'microsoft-office.webp'],
            'keywords' => ['office', 'office 2024', 'microsoft office', 'أوفيس', 'اوفس'],
        ],
        'windows' => [
            'files'    => ['windows.webp', 'windows11.webp', 'windows-11.webp'],
            'keywords' => ['windows', 'windows 11', 'ويندوز'],
        ],
        'youtube' => [
            'files'    => ['youtube.webp', 'youtube-premium.webp', 'youtube-music.webp'],
            'keywords' => ['youtube', 'youtube premium', 'youtube music', 'يوتيوب', 'يوتيوب بريميوم'],
        ],

        // -------------------- Wallet / telco (KSA) — high-specificity keys
        // (e.g. 'stc-pay' before 'stc') so generic stc doesn't shadow stc-pay.
        'stc-pay' => [
            'files'    => ['stc-pay.webp', 'stcpay.webp'],
            'keywords' => ['stc pay', 'stcpay', 'اس تي سي باي', 'إس تي سي باي'],
        ],
        'stc' => [
            'files'    => ['stc.webp', 'sawa.webp'],
            'keywords' => ['stc', 'سوا', 'اس تي سي', 'إس تي سي'],
        ],
        'mobily' => [
            'files'    => ['mobily.webp'],
            'keywords' => ['mobily', 'موبايلي'],
        ],
        'zain' => [
            'files'    => ['zain.webp'],
            'keywords' => ['zain', 'زين'],
        ],
        'careem' => [
            'files'    => ['careem.webp'],
            'keywords' => ['careem', 'كريم'],
        ],

        // -------------------- Wallet / telco — UAE
        'etisalat' => [
            'files'    => ['etisalat.webp', 'etisalat-uae.webp'],
            'keywords' => ['etisalat', 'اتصالات', 'إتصالات'],
        ],
        'du' => [
            'files'    => ['du.webp', 'du-uae.webp'],
            'keywords' => ['du uae', 'du mobile', 'دو'],
        ],

        // -------------------- Wallet / telco — Bahrain
        'batelco' => [
            'files'    => ['batelco.webp'],
            'keywords' => ['batelco', 'بتلكو'],
        ],

        // -------------------- Wallet / telco — Oman
        'omantel' => [
            'files'    => ['omantel.webp'],
            'keywords' => ['omantel', 'عمانتل'],
        ],

        // -------------------- Wallet / telco — Qatar / Kuwait / Oman (Ooredoo Group)
        'ooredoo' => [
            'files'    => ['ooredoo.webp'],
            'keywords' => ['ooredoo', 'اوريدو', 'أوريدو'],
        ],

        // -------------------- GCC marketplaces / retail
        'talabat' => [
            'files'    => ['talabat.webp'],
            'keywords' => ['talabat', 'طلبات'],
        ],
        'carrefour' => [
            'files'    => ['carrefour.webp', 'carrefour-maf.webp'],
            'keywords' => ['carrefour', 'كارفور'],
        ],
        'sharaf-dg' => [
            'files'    => ['sharaf-dg.webp'],
            'keywords' => ['sharaf dg', 'sharaf', 'شرف دي جي'],
        ],
        'lulu' => [
            'files'    => ['lulu.webp', 'lulu-hypermarket.webp'],
            'keywords' => ['lulu', 'lulu hypermarket', 'لولو'],
        ],
        'x-cite' => [
            'files'    => ['x-cite.webp', 'xcite.webp', 'alghanim.webp'],
            'keywords' => ['x-cite', 'xcite', 'alghanim', 'اكس سايت', 'إكس سايت', 'الغانم'],
        ],
        'virgin-megastore' => [
            'files'    => ['virgin-megastore.webp', 'virgin.webp'],
            'keywords' => ['virgin megastore', 'virgin', 'فيرجن'],
        ],

        // -------------------- Streaming / audio
        'netflix' => [
            'files'    => ['netflix.webp'],
            'keywords' => ['netflix', 'نتفلكس', 'نتفليكس'],
        ],
        'shahid' => [
            'files'    => ['shahid.webp', 'shahid-vip.webp'],
            'keywords' => ['shahid', 'shahid vip', 'شاهد'],
        ],
        'spotify' => [
            'files'    => ['spotify.webp'],
            'keywords' => ['spotify', 'سبوتيفاي', 'سبوتفاي'],
        ],
        'anghami' => [
            'files'    => ['anghami.webp'],
            'keywords' => ['anghami', 'أنغامي', 'انغامي'],
        ],
        'disney-plus' => [
            'files'    => ['disney-plus.webp', 'disney.webp'],
            'keywords' => ['disney+', 'disney plus', 'ديزني بلس', 'ديزني+'],
        ],

        // -------------------- Console / store credit
        'nintendo-eshop' => [
            'files'    => ['nintendo-eshop.webp', 'nintendo.webp', 'eshop.webp'],
            'keywords' => ['nintendo', 'eshop', 'switch eshop', 'نينتندو', 'اي شوب'],
        ],

        // -------------------- Game top-ups (in-game currency)
        'pubg' => [
            'files'    => ['pubg.webp', 'pubg-uc.webp'],
            'keywords' => ['pubg', 'uc', 'ببجي', 'يوسي'],
        ],
        'free-fire' => [
            'files'    => ['free-fire.webp', 'freefire.webp', 'garena.webp'],
            'keywords' => ['free fire', 'freefire', 'garena', 'فري فاير', 'جارينا', 'قارينا'],
        ],
        'roblox' => [
            'files'    => ['roblox.webp', 'robux.webp'],
            'keywords' => ['roblox', 'robux', 'روبلوكس', 'روبكس'],
        ],
        'razer-gold' => [
            'files'    => ['razer-gold.webp', 'razer.webp'],
            'keywords' => ['razer gold', 'razer pin', 'رايزر قولد', 'ريزر قولد'],
        ],
        'discord-nitro' => [
            'files'    => ['discord-nitro.webp', 'discord.webp'],
            'keywords' => ['discord nitro', 'discord', 'ديسكورد'],
        ],
        'fortnite' => [
            'files'    => ['fortnite.webp', 'v-bucks.webp'],
            'keywords' => ['fortnite', 'v-bucks', 'vbucks', 'فورتنايت'],
        ],
        'minecraft' => [
            'files'    => ['minecraft.webp', 'minecoins.webp'],
            'keywords' => ['minecraft', 'minecoins', 'ماين كرافت', 'مايكنرافت'],
        ],

        // -------------------- Marketplaces (KSA / global)
        'noon' => [
            'files'    => ['noon.webp'],
            'keywords' => ['noon', 'نون'],
        ],
        'jarir' => [
            'files'    => ['jarir.webp'],
            'keywords' => ['jarir', 'jarir bookstore', 'جرير'],
        ],
        'ebay' => [
            'files'    => ['ebay.webp'],
            'keywords' => ['ebay', 'إيباي', 'ايباي'],
        ],

        // -------------------- Social / utility credits — specific before generic
        'snapchat-plus' => [
            'files'    => ['snapchat-plus.webp', 'snapchat.webp'],
            'keywords' => ['snapchat+', 'snapchat plus', 'سناب شات بلس', 'سنابشات بلس'],
        ],
        'tiktok-coins' => [
            'files'    => ['tiktok-coins.webp', 'tiktok.webp'],
            'keywords' => ['tiktok', 'tik tok', 'tiktok coins', 'تيك توك'],
        ],

        // -------------------- Prepaid debit
        'visa-prepaid' => [
            'files'    => ['visa-prepaid.webp', 'visa.webp'],
            'keywords' => ['visa prepaid', 'visa gift', 'فيزا'],
        ],
        'mastercard-prepaid' => [
            'files'    => ['mastercard-prepaid.webp', 'mastercard.webp'],
            'keywords' => ['mastercard prepaid', 'mastercard gift', 'ماستركارد', 'ماستر كارد'],
        ],
    ];

    /*
     * Filter so user-side snippets can register more brands without
     * editing this file. ng_gift_card_existing_file() guards against
     * missing webp files so registering a slot ahead of art is safe.
     */
    return apply_filters('ng_gift_card_asset_map', $map);
}

function ng_gift_card_asset_dir() {
    return trailingslashit(NG_THEME_ASSET_DIR) . 'img/gift-cards';
}

function ng_gift_card_asset_url_base() {
    return trailingslashit(NG_THEME_ASSET_URL) . 'img/gift-cards';
}

function ng_gift_card_existing_file($asset) {
    $files = [];
    if (!empty($asset['files']) && is_array($asset['files'])) {
        $files = $asset['files'];
    } elseif (!empty($asset['file'])) {
        $files = [(string) $asset['file']];
    }

    foreach ($files as $file) {
        $file = basename((string) $file);
        if ($file !== '' && file_exists(ng_gift_card_asset_dir() . '/' . $file)) {
            return $file;
        }
    }

    return '';
}

function ng_gift_card_parent_product($product) {
    if (!is_object($product) || !method_exists($product, 'get_parent_id') || !function_exists('wc_get_product')) {
        return null;
    }

    $parent_id = (int) $product->get_parent_id();
    if ($parent_id <= 0) {
        return null;
    }

    $parent = wc_get_product($parent_id);
    return $parent instanceof WC_Product ? $parent : null;
}

function ng_gift_card_normalize_match_text($text) {
    $text = strtolower((string) $text);
    $text = str_replace(['-', '_'], ' ', $text);
    return preg_replace('/\s+/u', ' ', $text);
}

function ng_gift_card_match_text($product, $parent = null) {
    $chunks = [];

    foreach ([$product, $parent] as $candidate) {
        if (!is_object($candidate) || !method_exists($candidate, 'get_id')) {
            continue;
        }

        $id = (int) $candidate->get_id();
        if (method_exists($candidate, 'get_name')) {
            $chunks[] = (string) $candidate->get_name();
        }
        if (method_exists($candidate, 'get_sku')) {
            $chunks[] = (string) $candidate->get_sku();
        }
        if ($id > 0) {
            $chunks[] = (string) get_post_field('post_name', $id);
            $terms = get_the_terms($id, 'product_cat');
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    $chunks[] = (string) $term->slug;
                    $chunks[] = (string) $term->name;
                }
            }
        }
    }

    return ng_gift_card_normalize_match_text(implode(' ', array_filter($chunks)));
}

function ng_gift_card_is_candidate_product($product, $parent = null) {
    $haystack = ng_gift_card_match_text($product, $parent);
    if ($haystack === '') {
        return false;
    }

    if (strpos($haystack, 'gift cards') !== false || strpos($haystack, 'gift card') !== false) {
        return true;
    }
    if (preg_match('/\bgc\b/u', $haystack) || preg_match('/\bgc\s+/u', $haystack)) {
        return true;
    }

    return strpos($haystack, 'بطاقة') !== false || strpos($haystack, 'card') !== false;
}

function ng_gift_card_asset_for_product($product, $parent = null) {
    if (!$parent) {
        $parent = ng_gift_card_parent_product($product);
    }

    /*
     * Per-product explicit override: read _ng_gift_card_brand meta on the
     * product (or its parent for variants) and short-circuit the keyword
     * scan if it points at a registered slot key. This is the runtime
     * side of the CSV column 'Meta: _ng_gift_card_brand'.
     */
    $map = ng_gift_card_asset_map();
    foreach ([$product, $parent] as $candidate) {
        if (!is_object($candidate) || !method_exists($candidate, 'get_id')) continue;
        $id = (int) $candidate->get_id();
        if ($id <= 0) continue;
        $forced = (string) get_post_meta($id, '_ng_gift_card_brand', true);
        $forced = strtolower(trim($forced));
        if ($forced === '' || !isset($map[$forced])) continue;

        $asset = $map[$forced];
        $file  = ng_gift_card_existing_file($asset);
        if ($file === '') break;          // override declared but no art on disk → fall through to keyword scan
        $asset['key']  = $forced;
        $asset['file'] = $file;
        $asset['matched_via'] = 'override';
        return $asset;
    }

    if (!ng_gift_card_is_candidate_product($product, $parent)) {
        return null;
    }

    $haystack = ng_gift_card_match_text($product, $parent);
    foreach ($map as $key => $asset) {
        foreach ($asset['keywords'] as $keyword) {
            if (strpos($haystack, ng_gift_card_normalize_match_text($keyword)) !== false) {
                $file = ng_gift_card_existing_file($asset);
                if ($file === '') {
                    continue 2;
                }
                $asset['key']  = $key;
                $asset['file'] = $file;
                $asset['matched_via'] = 'keyword:' . $keyword;
                return $asset;
            }
        }
    }

    return null;
}

function ng_gift_card_image_url($product, $parent = null) {
    $asset = ng_gift_card_asset_for_product($product, $parent);
    if (!$asset || empty($asset['file'])) {
        return '';
    }

    return ng_gift_card_asset_url_base() . '/' . rawurlencode($asset['file']);
}

function ng_gift_card_clean_product_name($text) {
    $text = (string) $text;
    if ($text === '') {
        return $text;
    }
    $original = $text;

    $patterns = [
        '/\s*(?:[-–—|,:;]\s*)?(?:with\s+)?(?:a\s+)?chance\s+(?:to|of|in)\s+(?:win|winning|get|getting|receive|receiving|earn|earning)\b[^|،,;.\n\r<]*/iu',
        '/\s*(?:[-–—|,:;]\s*)?(?:for\s+)?(?:a\s+)?chance\s+to\s+(?:get|win)\b[^|،,;.\n\r<]*/iu',
        '/\s*(?:[-–—|,:;]\s*)?to\s+chance\s+in\s+getting\b[^|،,;.\n\r<]*/iu',
        '/\b(?:buy\s+and\s+win|win\s*\$?\d+)\b[^|،,;.\n\r<]*/iu',
        '/\bteam\s+of\s+the\s+year\s*[-–—:]\s*win\s*\$?\d+[^\|،,;.\n\r<]*/iu',
        '/\s*(?:[-–—|,:;،]\s*)?(?:مع\s+)?(?:فرصة|الفرصة)\s+(?:للفوز|فوز|للربح|ربح|للحصول|الحصول)\b[^|،,;.\n\r<]*/u',
        '/\s*(?:[-–—|,:;،]\s*)?(?:اربح|فز|فرصة\s+ربح)\b[^|،,;.\n\r<]*/u',
    ];

    $text = preg_replace($patterns, '', $text);
    if ($text === null || $text === $original) {
        return $original;
    }
    $text = preg_replace('/\s+([|،,;:.])/u', '$1', $text);
    $text = preg_replace('/([|،,;:])\s*([|،,;:])+/u', '$1', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = preg_replace('/\s*[-–—|,:;،]\s*$/u', '', $text);

    return trim((string) $text);
}

function ng_gift_card_image_html($product, $size = 'woocommerce_thumbnail', $alt = '', $parent = null, $attr = []) {
    $url = ng_gift_card_image_url($product, $parent);
    if ($url === '') {
        return '';
    }

    if ($alt === '' && is_object($product) && method_exists($product, 'get_name')) {
        $alt = (string) $product->get_name();
    }

    $attr = is_array($attr) ? $attr : [];
    $class = 'ng-gift-card-img';
    if (!empty($attr['class'])) {
        $class = trim((string) $attr['class'] . ' ' . $class);
    }

    $html_attr = [
        'src'      => esc_url($url),
        'class'    => esc_attr($class),
        'alt'      => esc_attr(ng_gift_card_clean_product_name($alt)),
        'width'    => '400',
        'height'   => '225',
        'loading'  => $attr['loading'] ?? 'lazy',
        'decoding' => $attr['decoding'] ?? 'async',
    ];

    $parts = [];
    foreach ($html_attr as $name => $value) {
        $parts[] = $name . '="' . esc_attr((string) $value) . '"';
    }

    return '<img ' . implode(' ', $parts) . '>';
}

add_filter('woocommerce_product_get_name', 'ng_gift_card_clean_product_name', 20);
add_filter('woocommerce_product_variation_get_name', 'ng_gift_card_clean_product_name', 20);
add_filter('woocommerce_product_get_description', 'ng_gift_card_clean_product_name', 20);
add_filter('woocommerce_product_get_short_description', 'ng_gift_card_clean_product_name', 20);
add_filter('woocommerce_product_variation_get_description', 'ng_gift_card_clean_product_name', 20);
add_filter('woocommerce_product_title', 'ng_gift_card_clean_product_name', 20);
add_filter('woocommerce_cart_item_name', 'ng_gift_card_clean_product_name', 20);
add_filter('woocommerce_order_item_name', 'ng_gift_card_clean_product_name', 20);

add_filter('the_title', function ($title, $post_id = null) {
    if ($post_id && get_post_type($post_id) === 'product') {
        return ng_gift_card_clean_product_name($title);
    }
    return $title;
}, 20, 2);

add_filter('woocommerce_product_get_image', function ($image, $product, $size, $attr) {
    $gift_image = ng_gift_card_image_html($product, $size, '', ng_gift_card_parent_product($product), $attr);
    return $gift_image !== '' ? $gift_image : $image;
}, 20, 4);

add_filter('woocommerce_product_get_gallery_image_ids', function ($image_ids, $product) {
    return ng_gift_card_image_url($product, ng_gift_card_parent_product($product)) !== '' ? [] : $image_ids;
}, 20, 2);

add_filter('post_thumbnail_html', function ($html, $post_id, $post_thumbnail_id, $size, $attr) {
    if (!$post_id || get_post_type($post_id) !== 'product' || !function_exists('wc_get_product')) {
        return $html;
    }

    $product = wc_get_product($post_id);
    if (!$product instanceof WC_Product) {
        return $html;
    }

    $gift_image = ng_gift_card_image_html($product, $size, '', null, is_array($attr) ? $attr : []);
    return $gift_image !== '' ? $gift_image : $html;
}, 20, 5);

add_filter('woocommerce_single_product_image_thumbnail_html', function ($html, $post_thumbnail_id) {
    global $product;
    if (!is_object($product)) {
        return $html;
    }

    $url = ng_gift_card_image_url($product);
    if ($url === '') {
        return $html;
    }

    $alt = method_exists($product, 'get_name') ? ng_gift_card_clean_product_name($product->get_name()) : '';
    $img = ng_gift_card_image_html($product, 'large', $alt, null, ['class' => 'wp-post-image']);
    if ($img === '') {
        return $html;
    }

    return '<div data-thumb="' . esc_url($url) . '" data-thumb-alt="' . esc_attr($alt) . '" class="woocommerce-product-gallery__image"><a href="' . esc_url($url) . '">' . $img . '</a></div>';
}, 20, 2);

add_filter('woocommerce_cart_item_thumbnail', function ($thumbnail, $cart_item) {
    $product = $cart_item['data'] ?? null;
    $gift_image = ng_gift_card_image_html($product, 'woocommerce_thumbnail', '', ng_gift_card_parent_product($product), ['class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail']);
    return $gift_image !== '' ? $gift_image : $thumbnail;
}, 20, 2);

add_action('init', function () {
    if (get_option('ng_gift_cards_assets_cache_busted_v1') === '1') {
        return;
    }
    delete_transient('ng_merchant_feed_xml');
    delete_transient('ng_merchant_feed_tsv');
    update_option('ng_gift_cards_assets_cache_busted_v1', '1', false);
}, 20);
