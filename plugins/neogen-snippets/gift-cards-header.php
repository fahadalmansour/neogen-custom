<?php
/**
 * Snippet: Gift Cards — Page Header Picker + Region Selector
 * Auto-loaded by plugins/neogen-snippets/neogen-snippets.php
 * Toggle via WP admin → Plugins → NeoGen Snippets.
 *
 * Two features in one file:
 *
 * 1. Page-header gift-card picker
 *    - Renders a responsive grid of every published WC product in the
 *      `gift-cards` product_cat. Each tile = brand image (resolved via
 *      ng_gift_card_image_url() in mu-plugins/neogen-gift-cards.php) +
 *      brand name, linking to the product permalink.
 *    - Auto-injected on the Gift Cards category archive via
 *      `woocommerce_before_main_content`.
 *    - Available as shortcode [neogen_gift_cards_header] for landing pages.
 *
 * 2. Region selector on PDP (Apple: GCC + US + UK)
 *    - Per-product post_meta `_ng_gc_regions` = array of ISO-3166-1 alpha-2
 *      country codes. When non-empty, a required <select> renders before
 *      the add-to-cart button. Cart, checkout, and order line items carry
 *      the chosen region as a "Region" label.
 *    - Admin metabox on the product edit screen lets the operator
 *      tick which regions a card supports.
 *    - No price changes per region in this phase.
 */

defined('ABSPATH') || exit;

// ---------------------------------------------------------------------
// Region catalogue — 8 codes covering GCC + US + UK. Bilingual labels
// rendered against site locale; admin metabox uses the EN label.
// ---------------------------------------------------------------------
function ng_gc_regions_catalogue() {
    return [
        'SA' => ['en' => 'Saudi Arabia',         'ar' => 'السعودية'],
        'AE' => ['en' => 'United Arab Emirates', 'ar' => 'الإمارات'],
        'BH' => ['en' => 'Bahrain',              'ar' => 'البحرين'],
        'OM' => ['en' => 'Oman',                 'ar' => 'عُمان'],
        'QA' => ['en' => 'Qatar',                'ar' => 'قطر'],
        'KW' => ['en' => 'Kuwait',               'ar' => 'الكويت'],
        'US' => ['en' => 'United States',        'ar' => 'الولايات المتحدة'],
        'GB' => ['en' => 'United Kingdom',       'ar' => 'المملكة المتحدة'],
    ];
}

function ng_gc_region_label($code) {
    $cat = ng_gc_regions_catalogue();
    if (!isset($cat[$code])) return $code;
    $is_ar = function_exists('is_rtl') && is_rtl();
    return $is_ar ? $cat[$code]['ar'] : $cat[$code]['en'];
}

function ng_gc_get_product_regions($product_id) {
    $raw = get_post_meta((int) $product_id, '_ng_gc_regions', true);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $raw = $decoded;
    }
    if (!is_array($raw)) return [];
    $cat = ng_gc_regions_catalogue();
    $out = [];
    foreach ($raw as $code) {
        $code = strtoupper(trim((string) $code));
        if (isset($cat[$code])) $out[] = $code;
    }
    return array_values(array_unique($out));
}

// ---------------------------------------------------------------------
// 1. Header picker — shortcode + auto-injection on the gift-cards archive
// ---------------------------------------------------------------------
function ng_gc_header_render() {
    if (!function_exists('wc_get_products') || !function_exists('ng_gift_card_image_url')) {
        return '';
    }

    // Admins see drafts too so they can preview the picker before publishing.
    $statuses = current_user_can('edit_products')
        ? ['publish', 'draft', 'pending']
        : ['publish'];

    $products = wc_get_products([
        'category' => ['gift-cards'],
        'status'   => $statuses,
        'limit'    => -1,
        'orderby'  => 'menu_order title',
        'order'    => 'ASC',
    ]);

    if (empty($products)) return '';

    $is_ar  = function_exists('is_rtl') && is_rtl();
    $title  = $is_ar ? 'بطاقات الهدايا' : 'Gift Cards';
    $sub    = $is_ar
        ? 'اختر بطاقة لعرض المنطقة المتوفرة (دول الخليج · الولايات المتحدة · المملكة المتحدة).'
        : 'Pick a card to see available regions (GCC · US · UK).';

    ob_start();
    ?>
    <section class="ng-gc-header" aria-labelledby="ng-gc-header-title" data-ng-gc-swiper>
      <header class="ng-gc-header__intro">
        <h2 id="ng-gc-header-title" class="ng-gc-header__title"><?php echo esc_html($title); ?></h2>
        <p class="ng-gc-header__sub"><?php echo esc_html($sub); ?></p>
      </header>
      <div class="ng-gc-header__viewport">
        <button type="button" class="ng-gc-header__nav ng-gc-header__nav--prev" data-ng-gc-prev aria-label="<?php echo esc_attr($is_ar ? 'السابق' : 'Previous'); ?>">‹</button>
        <button type="button" class="ng-gc-header__nav ng-gc-header__nav--next" data-ng-gc-next aria-label="<?php echo esc_attr($is_ar ? 'التالي' : 'Next'); ?>">›</button>
      <ul class="ng-gc-header__strip" role="list" data-ng-gc-track>
        <?php foreach ($products as $p):
            if (!$p instanceof WC_Product) continue;
            $url   = get_permalink($p->get_id());
            $name  = function_exists('ng_gift_card_clean_product_name')
                ? ng_gift_card_clean_product_name($p->get_name())
                : $p->get_name();
            $img   = ng_gift_card_image_url($p);
            // Fallback: use the product's own featured image if the
            // gift-card resolver returned nothing (e.g. product not in the
            // brand asset map, or _ng_gift_card_brand meta unset).
            if ($img === '') {
                $thumb_id = (int) $p->get_image_id();
                if ($thumb_id > 0) {
                    $src = wp_get_attachment_image_url($thumb_id, 'woocommerce_thumbnail');
                    if ($src) $img = $src;
                }
            }
            if ($img === '' && function_exists('wc_placeholder_img_src')) {
                $img = (string) wc_placeholder_img_src('woocommerce_thumbnail');
            }
            $regions  = ng_gc_get_product_regions($p->get_id());
            $is_draft = $p->get_status() !== 'publish';
        ?>
          <li class="ng-gc-header__tile<?php echo $is_draft ? ' ng-gc-header__tile--draft' : ''; ?>">
            <a class="ng-gc-header__link" href="<?php echo esc_url($url); ?>">
              <?php if ($img !== ''): ?>
                <img class="ng-gc-header__img" src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy" decoding="async" width="320" height="180">
              <?php else: ?>
                <span class="ng-gc-header__img ng-gc-header__img--placeholder" aria-hidden="true"></span>
              <?php endif; ?>
              <?php if ($is_draft): ?>
                <span class="ng-gc-header__badge"><?php echo esc_html($is_ar ? 'مسودة' : 'Draft'); ?></span>
              <?php endif; ?>
              <span class="ng-gc-header__name"><?php echo esc_html($name); ?></span>
              <?php if (!empty($regions)): ?>
                <span class="ng-gc-header__regions">
                  <?php foreach ($regions as $code): ?>
                    <span class="ng-gc-header__region" title="<?php echo esc_attr(ng_gc_region_label($code)); ?>"><?php echo esc_html($code); ?></span>
                  <?php endforeach; ?>
                </span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      </div>
    </section>
    <style>
      .ng-gc-header{margin:0 0 1.5rem;padding:1.25rem;border-radius:12px;background:#f6f9ff;}
      .ng-gc-header__intro{margin:0 0 1rem;}
      .ng-gc-header__title{margin:0 0 .25rem;font-size:1.25rem;}
      .ng-gc-header__sub{margin:0;color:#475569;font-size:.9rem;}
      .ng-gc-header__viewport{position:relative;}
      .ng-gc-header__strip{list-style:none;margin:0;padding:.25rem .25rem 1rem;display:flex;gap:.75rem;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;scroll-behavior:smooth;scrollbar-width:thin;-webkit-overflow-scrolling:touch;}
      .ng-gc-header__strip::-webkit-scrollbar{height:6px;}
      .ng-gc-header__strip::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px;}
      .ng-gc-header__tile{margin:0;flex:0 0 auto;width:160px;scroll-snap-align:start;}
      @media (min-width:640px){.ng-gc-header__tile{width:180px;}}
      .ng-gc-header__link{display:flex;flex-direction:column;align-items:center;gap:.4rem;padding:.6rem;border-radius:10px;background:#fff;text-decoration:none;color:inherit;border:1px solid #e2e8f0;transition:transform .15s ease,box-shadow .15s ease;height:100%;}
      .ng-gc-header__link:hover,.ng-gc-header__link:focus-visible{transform:translateY(-2px);box-shadow:0 6px 16px rgba(15,23,42,.08);}
      .ng-gc-header__img{display:block;width:100%;height:auto;aspect-ratio:16/9;object-fit:contain;border-radius:6px;background:#f1f5f9;}
      .ng-gc-header__img--placeholder{background:#e2e8f0;}
      .ng-gc-header__name{font-size:.85rem;font-weight:600;text-align:center;}
      .ng-gc-header__regions{display:flex;flex-wrap:wrap;gap:.2rem;justify-content:center;}
      .ng-gc-header__region{font-size:.65rem;padding:.1rem .35rem;border-radius:999px;background:#eef2ff;color:#3730a3;letter-spacing:.04em;}
      .ng-gc-header__tile--draft .ng-gc-header__link{border-style:dashed;border-color:#f59e0b;background:#fffbeb;}
      .ng-gc-header__badge{position:relative;font-size:.6rem;padding:.1rem .4rem;border-radius:4px;background:#f59e0b;color:#fff;text-transform:uppercase;letter-spacing:.05em;align-self:flex-start;}
      .ng-gc-header__nav{position:absolute;top:50%;transform:translateY(-50%);width:36px;height:36px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;font-size:1.25rem;line-height:1;cursor:pointer;box-shadow:0 4px 10px rgba(15,23,42,.08);z-index:2;display:none;}
      .ng-gc-header__nav:hover{background:#f1f5f9;}
      .ng-gc-header__nav--prev{inset-inline-start:-8px;}
      .ng-gc-header__nav--next{inset-inline-end:-8px;}
      @media (hover:hover) and (pointer:fine){.ng-gc-header__nav{display:block;}}
    </style>
    <script>
    (function(){
      if (window.__ngGcSwiperInit) return; window.__ngGcSwiperInit = true;
      function wire(root){
        var track = root.querySelector('[data-ng-gc-track]');
        var prev  = root.querySelector('[data-ng-gc-prev]');
        var next  = root.querySelector('[data-ng-gc-next]');
        if (!track || !prev || !next) return;
        var rtl = getComputedStyle(root).direction === 'rtl';
        function step(){ return Math.max(160, Math.round(track.clientWidth * 0.8)); }
        function scrollBy(dir){ track.scrollBy({left: (rtl ? -1 : 1) * dir * step(), behavior:'smooth'}); }
        prev.addEventListener('click', function(){ scrollBy(-1); });
        next.addEventListener('click', function(){ scrollBy(1); });
      }
      function init(){
        document.querySelectorAll('[data-ng-gc-swiper]').forEach(wire);
      }
      if (document.readyState !== 'loading') init();
      else document.addEventListener('DOMContentLoaded', init);
    })();
    </script>
    <?php
    return (string) ob_get_clean();
}

add_shortcode('neogen_gift_cards_header', 'ng_gc_header_render');

add_action('woocommerce_before_main_content', function () {
    if (!function_exists('is_product_category')) return;
    if (!is_product_category('gift-cards')) return;
    echo ng_gc_header_render(); // safe: render escapes per-field above
}, 20);

// ---------------------------------------------------------------------
// 2a. Region admin metabox on the product edit screen
// ---------------------------------------------------------------------
add_action('add_meta_boxes', function () {
    add_meta_box(
        'ng_gc_regions_box',
        'NeoGen · Gift Card Regions',
        'ng_gc_regions_metabox_render',
        'product',
        'side',
        'default'
    );
});

function ng_gc_regions_metabox_render($post) {
    wp_nonce_field('ng_gc_regions_save', 'ng_gc_regions_nonce');
    $current = ng_gc_get_product_regions($post->ID);
    $cat     = ng_gc_regions_catalogue();
    echo '<p style="margin-top:0;color:#475569;">Tick the regions this gift card supports. If none ticked, no region selector is shown on the product page.</p>';
    echo '<ul style="margin:0;padding:0;list-style:none;">';
    foreach ($cat as $code => $labels) {
        $checked = in_array($code, $current, true) ? 'checked' : '';
        printf(
            '<li style="margin:.2em 0;"><label><input type="checkbox" name="ng_gc_regions[]" value="%s" %s> <strong>%s</strong> — %s</label></li>',
            esc_attr($code),
            $checked,
            esc_html($code),
            esc_html($labels['en'])
        );
    }
    echo '</ul>';
}

add_action('save_post_product', function ($post_id) {
    if (!isset($_POST['ng_gc_regions_nonce'])) return;
    if (!wp_verify_nonce($_POST['ng_gc_regions_nonce'], 'ng_gc_regions_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_product', $post_id)) return;

    $cat   = ng_gc_regions_catalogue();
    $input = isset($_POST['ng_gc_regions']) && is_array($_POST['ng_gc_regions'])
        ? $_POST['ng_gc_regions'] : [];
    $clean = [];
    foreach ($input as $code) {
        $code = strtoupper(trim((string) $code));
        if (isset($cat[$code])) $clean[] = $code;
    }
    $clean = array_values(array_unique($clean));

    if (empty($clean)) {
        delete_post_meta($post_id, '_ng_gc_regions');
    } else {
        update_post_meta($post_id, '_ng_gc_regions', $clean);
    }
}, 10, 1);

// ---------------------------------------------------------------------
// 2b. Frontend region selector on PDP
// ---------------------------------------------------------------------
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    if (!$product instanceof WC_Product) return;

    $regions = ng_gc_get_product_regions($product->get_id());
    if (empty($regions)) return;

    $is_ar = function_exists('is_rtl') && is_rtl();
    $label = $is_ar ? 'اختر المنطقة' : 'Select region';
    $hint  = $is_ar ? 'متجر آبل يختلف بحسب المنطقة — اختر دولة البطاقة قبل الشراء.' : 'Apple Store differs per region — choose the card country before checkout.';
    ?>
    <div class="ng-gc-region-picker" style="margin:0 0 1rem;">
      <label for="ng_gc_region" style="display:block;font-weight:600;margin-bottom:.25rem;"><?php echo esc_html($label); ?> <span aria-hidden="true" style="color:#dc2626;">*</span></label>
      <select id="ng_gc_region" name="ng_gc_region" required style="width:100%;max-width:320px;padding:.5rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
        <option value=""><?php echo esc_html($is_ar ? '— اختر —' : '— Select —'); ?></option>
        <?php foreach ($regions as $code): ?>
          <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html(ng_gc_region_label($code) . ' (' . $code . ')'); ?></option>
        <?php endforeach; ?>
      </select>
      <small style="display:block;margin-top:.35rem;color:#64748b;"><?php echo esc_html($hint); ?></small>
    </div>
    <?php
}, 5);

// ---------------------------------------------------------------------
// 2c. Cart / checkout / order wiring
// ---------------------------------------------------------------------
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id) {
    $regions = ng_gc_get_product_regions($product_id);
    if (empty($regions)) return $passed;

    $picked = isset($_POST['ng_gc_region']) ? strtoupper(trim((string) $_POST['ng_gc_region'])) : '';
    if ($picked === '' || !in_array($picked, $regions, true)) {
        $is_ar = function_exists('is_rtl') && is_rtl();
        wc_add_notice(
            $is_ar ? 'الرجاء اختيار منطقة البطاقة قبل الإضافة إلى السلة.' : 'Please choose a card region before adding to cart.',
            'error'
        );
        return false;
    }
    return $passed;
}, 10, 2);

add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    $regions = ng_gc_get_product_regions($product_id);
    if (empty($regions)) return $cart_item_data;

    $picked = isset($_POST['ng_gc_region']) ? strtoupper(trim((string) $_POST['ng_gc_region'])) : '';
    if ($picked === '' || !in_array($picked, $regions, true)) {
        return $cart_item_data;
    }
    $cart_item_data['ng_gc_region'] = $picked;
    // Force unique cart line per region so two regions of the same card don't merge.
    $cart_item_data['unique_key'] = md5(microtime() . $picked);
    return $cart_item_data;
}, 10, 2);

add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (empty($cart_item['ng_gc_region'])) return $item_data;
    $is_ar = function_exists('is_rtl') && is_rtl();
    $item_data[] = [
        'key'     => $is_ar ? 'المنطقة' : 'Region',
        'value'   => ng_gc_region_label($cart_item['ng_gc_region']) . ' (' . esc_html($cart_item['ng_gc_region']) . ')',
        'display' => '',
    ];
    return $item_data;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (empty($values['ng_gc_region'])) return;
    $code  = strtoupper((string) $values['ng_gc_region']);
    $label = ng_gc_region_label($code) . ' (' . $code . ')';
    $item->add_meta_data('Region', $label, true);
    $item->add_meta_data('_ng_gc_region_code', $code, true);
}, 10, 4);
