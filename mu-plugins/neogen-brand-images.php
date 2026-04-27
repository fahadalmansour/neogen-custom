<?php
/**
 * Plugin Name: NeoGen Brand Image Packs
 * Description: Generalized brand-image matcher (extends the gift-cards pattern) for other WC categories — networking, smart-home, gaming, software, accessories. Drop branded webp/png files into mu-plugins/neogen-theme-assets/img/brands/<pack>/ and the matcher swaps them onto matching products.
 * Version: 1.0.0
 * Author: Fahad Almansour
 *
 * Pack directory: mu-plugins/neogen-theme-assets/img/brands/<pack-slug>/
 * Filename → brand-key (filename without extension, lowercase, hyphenated).
 * Category-pack mapping is filterable via `ng_brand_image_packs`.
 *
 * NOTE: This plugin loads AFTER neogen-gift-cards.php so the gift-cards
 * matcher takes precedence on its own slugs (priority lower than 20).
 */

defined('ABSPATH') || exit;

if (!defined('NG_THEME_ASSET_DIR')) {
    define('NG_THEME_ASSET_DIR', __DIR__ . '/neogen-theme-assets');
}
if (!defined('NG_THEME_ASSET_URL')) {
    $ng_brand_asset_rel = str_replace(
        wp_normalize_path(WP_CONTENT_DIR),
        '',
        wp_normalize_path(NG_THEME_ASSET_DIR)
    );
    define('NG_THEME_ASSET_URL', content_url($ng_brand_asset_rel));
}

/**
 * Pack registry: maps WC product_cat slugs → brand-image pack subdir + keyword map.
 * Filterable so user-side snippets can register more without editing this file.
 *
 * Each pack entry:
 *   'pack'     => subdirectory under img/brands/
 *   'cat_slug' => product_cat slug (or array of slugs) the pack matches
 *   'brands'   => [ 'brand-key' => ['file' => 'brand-key.webp', 'keywords' => [...]] ]
 *
 * Drop NEW webp/png artwork into the pack subdir matching the file key.
 * No artwork is fabricated here — matcher returns null when the file is
 * missing and WC falls back to its own thumbnail.
 */
function ng_brand_image_packs() {
    $packs = [
        'networking' => [
            'pack'     => 'networking',
            'cat_slug' => ['networking', 'shabkat', 'الشبكات'],
            'brands'   => [
                'mikrotik' => ['file' => 'mikrotik.webp', 'keywords' => ['mikrotik', 'routerboard', 'ميكروتك']],
                'ubiquiti' => ['file' => 'ubiquiti.webp', 'keywords' => ['ubiquiti', 'unifi', 'يوبي', 'يونيفاي']],
                'tp-link'  => ['file' => 'tp-link.webp',  'keywords' => ['tp-link', 'tp link', 'tplink', 'تي بي لينك']],
                'cisco'    => ['file' => 'cisco.webp',    'keywords' => ['cisco', 'سيسكو']],
                'aruba'    => ['file' => 'aruba.webp',    'keywords' => ['aruba', 'أروبا']],
                'netgear'  => ['file' => 'netgear.webp',  'keywords' => ['netgear', 'نتجير']],
            ],
        ],
        'smart-home' => [
            'pack'     => 'smart-home',
            'cat_slug' => ['smart-home', 'home-automation', 'البيوت-الذكية', 'بيوت-ذكية'],
            'brands'   => [
                'aqara'         => ['file' => 'aqara.webp',         'keywords' => ['aqara', 'اكارا', 'أكارا']],
                'philips-hue'   => ['file' => 'philips-hue.webp',   'keywords' => ['philips hue', 'hue', 'فيليبس هيو']],
                'shelly'        => ['file' => 'shelly.webp',        'keywords' => ['shelly', 'شيلي']],
                'sonoff'        => ['file' => 'sonoff.webp',        'keywords' => ['sonoff', 'سونوف']],
                'tuya'          => ['file' => 'tuya.webp',          'keywords' => ['tuya', 'توا', 'تويا']],
                'home-assistant'=> ['file' => 'home-assistant.webp','keywords' => ['home assistant', 'hassio', 'هوم اسستنت']],
                'ikea-tradfri'  => ['file' => 'ikea-tradfri.webp',  'keywords' => ['tradfri', 'ikea smart', 'ايكيا']],
            ],
        ],
        'gaming' => [
            'pack'     => 'gaming',
            'cat_slug' => ['gaming', 'الألعاب', 'gamepad', 'console-accessories'],
            'brands'   => [
                '8bitdo'  => ['file' => '8bitdo.webp',  'keywords' => ['8bitdo', '8 bitdo', '8بت دو']],
                'razer'   => ['file' => 'razer.webp',   'keywords' => ['razer', 'ريزر']],
                'logitech'=> ['file' => 'logitech.webp','keywords' => ['logitech', 'لوجيتك']],
                'corsair' => ['file' => 'corsair.webp', 'keywords' => ['corsair', 'كورسير']],
                'sony'    => ['file' => 'sony.webp',    'keywords' => ['sony', 'dualsense', 'dualshock', 'سوني']],
                'xbox'    => ['file' => 'xbox.webp',    'keywords' => ['xbox', 'اكس بوكس', 'إكس بوكس']],
                'nintendo'=> ['file' => 'nintendo.webp','keywords' => ['nintendo', 'switch', 'نينتندو']],
            ],
        ],
        'software' => [
            'pack'     => 'software',
            'cat_slug' => ['software', 'البرمجيات', 'license-keys', 'مفاتيح-تفعيل'],
            'brands'   => [
                'microsoft'    => ['file' => 'microsoft.webp',   'keywords' => ['microsoft', 'office', 'windows', 'مايكروسوفت', 'وندوز']],
                'kaspersky'    => ['file' => 'kaspersky.webp',   'keywords' => ['kaspersky', 'كاسبرسكي']],
                'norton'       => ['file' => 'norton.webp',      'keywords' => ['norton', 'نورتون']],
                'mcafee'       => ['file' => 'mcafee.webp',      'keywords' => ['mcafee', 'مكافي']],
                'adobe'        => ['file' => 'adobe.webp',       'keywords' => ['adobe', 'photoshop', 'ادوبي', 'أدوبي']],
                'autodesk'     => ['file' => 'autodesk.webp',    'keywords' => ['autodesk', 'autocad', 'fusion 360', 'اوتوديسك']],
                'jetbrains'    => ['file' => 'jetbrains.webp',   'keywords' => ['jetbrains', 'phpstorm', 'webstorm', 'pycharm', 'rider']],
                'bitdefender'  => ['file' => 'bitdefender.webp', 'keywords' => ['bitdefender', 'بت ديفندر', 'بيت ديفندر']],
                'eset'         => ['file' => 'eset.webp',        'keywords' => ['eset', 'nod32', 'ايست']],
            ],
        ],
        'storage' => [
            'pack'     => 'storage',
            'cat_slug' => ['storage', 'nas', 'التخزين'],
            'brands'   => [
                'synology'  => ['file' => 'synology.webp',  'keywords' => ['synology', 'سينولوجي']],
                'qnap'      => ['file' => 'qnap.webp',      'keywords' => ['qnap', 'كيو ناب']],
                'truenas'   => ['file' => 'truenas.webp',   'keywords' => ['truenas', 'true nas', 'ترو ناس']],
                'wd'        => ['file' => 'wd.webp',        'keywords' => ['western digital', 'wd red', 'wd blue', 'وسترن ديجيتال']],
                'seagate'   => ['file' => 'seagate.webp',   'keywords' => ['seagate', 'سيجيت']],
                'samsung'   => ['file' => 'samsung.webp',   'keywords' => ['samsung ssd', 'samsung evo', 'samsung pro', 'سامسونج']],
                'kingston'  => ['file' => 'kingston.webp',  'keywords' => ['kingston', 'كينجستون']],
                'crucial'   => ['file' => 'crucial.webp',   'keywords' => ['crucial', 'كروشال']],
            ],
        ],
        'accessories' => [
            'pack'     => 'accessories',
            'cat_slug' => ['accessories', 'الملحقات', 'cables'],
            'brands'   => [
                'anker'   => ['file' => 'anker.webp',   'keywords' => ['anker', 'انكر', 'أنكر']],
                'belkin'  => ['file' => 'belkin.webp',  'keywords' => ['belkin', 'بيلكن']],
                'ugreen'  => ['file' => 'ugreen.webp',  'keywords' => ['ugreen', 'يوغرين', 'يو غرين']],
                'baseus'  => ['file' => 'baseus.webp',  'keywords' => ['baseus', 'باسيوس']],
            ],
        ],
    ];

    return apply_filters('ng_brand_image_packs', $packs);
}

/**
 * Lowercase + collapse separators for substring matching.
 */
function ng_brand_image_normalize($text) {
    $text = strtolower((string) $text);
    $text = str_replace(['-', '_'], ' ', $text);
    return preg_replace('/\s+/u', ' ', $text);
}

/**
 * Build the haystack of name+sku+slug+cat-terms for a product (and optional parent).
 */
function ng_brand_image_haystack($product, $parent = null) {
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

    return ng_brand_image_normalize(implode(' ', array_filter($chunks)));
}

/**
 * Find the matching brand asset for a product, walking through every pack.
 * Returns ['file' => 'foo.webp', 'pack' => 'gaming', 'key' => '8bitdo'] or null.
 */
function ng_brand_image_match($product, $parent = null) {
    $haystack = ng_brand_image_haystack($product, $parent);
    if ($haystack === '') {
        return null;
    }

    foreach (ng_brand_image_packs() as $pack_slug => $pack) {
        // optional category gate: if cat_slug is set, require at least one cat hit
        if (!empty($pack['cat_slug'])) {
            $cats = (array) $pack['cat_slug'];
            $cat_hit = false;
            foreach ($cats as $c) {
                if (strpos($haystack, ng_brand_image_normalize($c)) !== false) {
                    $cat_hit = true;
                    break;
                }
            }
            if (!$cat_hit) continue;
        }

        foreach ($pack['brands'] as $brand_key => $brand) {
            foreach ((array) ($brand['keywords'] ?? []) as $kw) {
                if (strpos($haystack, ng_brand_image_normalize($kw)) !== false) {
                    return [
                        'file' => $brand['file'],
                        'pack' => $pack['pack'],
                        'key'  => $brand_key,
                    ];
                }
            }
        }
    }

    return null;
}

/**
 * Resolve a matched asset to a URL — but only if the file actually exists.
 * Returns '' otherwise so WC keeps its own thumbnail.
 */
function ng_brand_image_url($product, $parent = null) {
    $match = ng_brand_image_match($product, $parent);
    if (!$match) return '';

    $rel  = '/img/brands/' . $match['pack'] . '/' . $match['file'];
    $disk = NG_THEME_ASSET_DIR . $rel;
    if (!file_exists($disk)) {
        return '';
    }
    return NG_THEME_ASSET_URL . $rel;
}

function ng_brand_image_html($product, $alt = '', $parent = null, $attr = []) {
    $url = ng_brand_image_url($product, $parent);
    if ($url === '') return '';

    if ($alt === '' && is_object($product) && method_exists($product, 'get_name')) {
        $alt = (string) $product->get_name();
    }

    $attr  = is_array($attr) ? $attr : [];
    $class = 'ng-brand-img';
    if (!empty($attr['class'])) {
        $class = trim((string) $attr['class'] . ' ' . $class);
    }

    $html_attr = [
        'src'      => esc_url($url),
        'class'    => esc_attr($class),
        'alt'      => esc_attr($alt),
        'width'    => '400',
        'height'   => '225',
        'loading'  => $attr['loading']  ?? 'lazy',
        'decoding' => $attr['decoding'] ?? 'async',
    ];

    $parts = [];
    foreach ($html_attr as $name => $value) {
        $parts[] = $name . '="' . esc_attr((string) $value) . '"';
    }
    return '<img ' . implode(' ', $parts) . '>';
}

/**
 * WC integration — runs at priority 30 so gift-cards (priority 20) wins
 * for its own products. If gift-cards already swapped, we don't touch.
 */
add_filter('woocommerce_product_get_image', function ($image, $product, $size, $attr) {
    // Gift-cards filter at priority 20 already replaced the image if it matched.
    // Heuristic: if the IMG src contains the gift-cards path, skip.
    if (is_string($image) && strpos($image, '/img/gift-cards/') !== false) {
        return $image;
    }

    $brand_image = ng_brand_image_html($product, '', null, $attr);
    return $brand_image !== '' ? $brand_image : $image;
}, 30, 4);

add_filter('woocommerce_single_product_image_thumbnail_html', function ($html, $post_thumbnail_id) {
    if (is_string($html) && strpos($html, '/img/gift-cards/') !== false) {
        return $html;
    }

    global $product;
    if (!is_object($product)) return $html;

    $url = ng_brand_image_url($product);
    if ($url === '') return $html;

    $alt = method_exists($product, 'get_name') ? $product->get_name() : '';
    $img = ng_brand_image_html($product, $alt, null, ['class' => 'wp-post-image']);
    if ($img === '') return $html;

    return '<div data-thumb="' . esc_url($url) . '" data-thumb-alt="' . esc_attr($alt) . '" class="woocommerce-product-gallery__image"><a href="' . esc_url($url) . '">' . $img . '</a></div>';
}, 30, 2);

add_filter('woocommerce_cart_item_thumbnail', function ($thumbnail, $cart_item) {
    if (is_string($thumbnail) && strpos($thumbnail, '/img/gift-cards/') !== false) {
        return $thumbnail;
    }
    $product = $cart_item['data'] ?? null;
    $brand_image = ng_brand_image_html($product, '', null, ['class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail']);
    return $brand_image !== '' ? $brand_image : $thumbnail;
}, 30, 2);
