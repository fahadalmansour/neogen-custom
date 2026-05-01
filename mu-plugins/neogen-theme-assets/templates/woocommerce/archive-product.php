<?php
/**
 * NeoGen shop/category archive template.
 *
 * Mirrors the JSX shop.jsx + category.jsx design:
 * - Dark indigo hero bar with category name + product count
 * - Subcategory tab strip (sticky)
 * - Two-column layout: filter sidebar + product grid
 * - Sort + view controls + pagination
 *
 * Routed by WooCommerce template_include for all product archive
 * pages (shop, product_cat, product_tag, search).
 *
 * @version 1.0.0 (NeoGen)
 */

defined('ABSPATH') || exit;

get_header('shop');

$is_shop     = is_shop();
$is_cat      = is_product_category();
$is_tag      = is_product_tag();
$is_search   = is_search() && isset($_GET['post_type']) && $_GET['post_type'] === 'product';

// Page title + count
if ($is_cat || $is_tag) {
    $term        = get_queried_object();
    $title_ar    = get_term_meta($term->term_id, '_ng_ar_label', true) ?: $term->name;
    $title_en    = $term->name;
    $description = $term->description;
} elseif ($is_search) {
    $title_ar = 'نتائج البحث';
    $title_en = 'Search Results';
    $description = '';
} else {
    $title_ar = 'المتجر';
    $title_en = 'Shop';
    $description = 'منتجات مختارة عبر 6 فئات.';
}

$total = wc_get_loop_prop('total', 0);
if (!$total) {
    global $wp_query;
    $total = (int) ($wp_query->found_posts ?? 0);
}

// Subcategories (for category pages)
$subcats = [];
if ($is_cat && isset($term)) {
    $subcats = get_terms([
        'taxonomy'   => 'product_cat',
        'parent'     => $term->term_id,
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ]);
    if (is_wp_error($subcats)) $subcats = [];
}

// Top-level cats for shop hero tabs
$top_cats = ng_top_product_cats(6);

do_action('woocommerce_before_main_content');
?>

<!-- HERO BAR -->
<div class="ng-shop-hero" dir="rtl">
  <div class="ng-shop-hero-inner">
    <div class="ng-shop-crumbs">
      <?php
      $crumbs = woocommerce_breadcrumb(['wrap_before' => '', 'wrap_after' => '', 'before' => '', 'after' => '', 'delimiter' => '<span style="opacity:.4;margin:0 6px">·</span>', 'home' => 'الرئيسية']);
      ?>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap;">
      <div>
        <h1>
          <?php echo esc_html($title_ar); ?>
          <span style="font-family:var(--font-wordmark);font-size:.45em;font-weight:400;color:rgba(255,255,255,0.4);margin-inline-start:12px;">· <?php echo esc_html($title_en); ?></span>
        </h1>
        <p class="ng-shop-hero-meta">
          <?php if ($total) : ?>
            <?php echo esc_html(number_format_i18n($total)); ?> منتج
          <?php endif; ?>
          <?php if ($description) : ?>
            <span style="opacity:.5;margin:0 8px">·</span>
            <?php echo esc_html(wp_strip_all_tags($description)); ?>
          <?php endif; ?>
        </p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php foreach ($top_cats as $cat) :
          $cat_ar = get_term_meta($cat->term_id, '_ng_ar_label', true) ?: $cat->name;
          $active = ($is_cat && isset($term) && $term->term_id === $cat->term_id);
        ?>
          <a href="<?php echo esc_url(get_term_link($cat)); ?>"
             class="ng-chip ng-chip--solid"
             style="<?php echo $active ? 'background:var(--accent);color:var(--indigo-deep);border-color:var(--accent);' : ''; ?>">
            <?php echo esc_html($cat_ar); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($subcats)) : ?>
<!-- SUBCATEGORY TABS -->
<div class="ng-shop-subcats">
  <div class="ng-shop-subcats-inner">
    <a href="<?php echo esc_url(get_term_link($term)); ?>"
       class="<?php echo empty($_GET['subcat']) ? 'current' : ''; ?>">الكل</a>
    <?php foreach ($subcats as $sub) :
      $sub_ar = get_term_meta($sub->term_id, '_ng_ar_label', true) ?: $sub->name;
    ?>
      <a href="<?php echo esc_url(get_term_link($sub)); ?>"><?php echo esc_html($sub_ar); ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- MAIN CONTENT -->
<div class="ng-shop-wrap" dir="rtl">

  <!-- FILTER SIDEBAR -->
  <aside class="ng-shop-sidebar">
    <div class="ng-shop-filters">
      <div class="ng-shop-filter-head">
        <span>الفلاتر · FILTERS</span>
        <a href="<?php echo esc_url(remove_query_arg(['min_price','max_price','filter_cat','orderby'])); ?>"
           style="color:var(--accent-deep);font-size:11px;text-decoration:none;">مسح الكل</a>
      </div>

      <?php
      // Price filter
      $min_price = wc_get_min_max_price_meta_query(['min_price' => 0, 'max_price' => 99999]);
      $price_ranges = [
        ['label' => 'أقل من 200 ريال', 'min' => 0,    'max' => 200],
        ['label' => '200–500 ريال',    'min' => 200,  'max' => 500],
        ['label' => '500–1500 ريال',   'min' => 500,  'max' => 1500],
        ['label' => '1500–5000 ريال',  'min' => 1500, 'max' => 5000],
        ['label' => 'أكثر من 5000 ريال','min'=> 5000, 'max' => 99999],
      ];
      ?>
      <details class="ng-shop-filter-group" open>
        <summary>السعر <span style="color:var(--dim);">−</span></summary>
        <div class="ng-filter-items">
          <?php foreach ($price_ranges as $r) : ?>
            <?php
            $active_min = isset($_GET['min_price']) ? (int) $_GET['min_price'] : null;
            $active_max = isset($_GET['max_price']) ? (int) $_GET['max_price'] : null;
            $checked    = ($active_min === $r['min'] && $active_max === $r['max']) ? ' checked' : '';
            $url        = add_query_arg(['min_price' => $r['min'], 'max_price' => $r['max']]);
            ?>
            <label>
              <input type="checkbox" onchange="window.location.href='<?php echo esc_url($url); ?>'"<?php echo $checked; ?>>
              <?php echo esc_html($r['label']); ?>
            </label>
          <?php endforeach; ?>
        </div>
      </details>

      <?php
      // Brand filter — product_cat children named after brands, or product_tag
      $brand_terms = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => true, 'number' => 10, 'orderby' => 'count', 'order' => 'DESC']);
      if (!is_wp_error($brand_terms) && !empty($brand_terms)) :
      ?>
      <details class="ng-shop-filter-group" open>
        <summary>العلامة التجارية <span style="color:var(--dim);">−</span></summary>
        <div class="ng-filter-items">
          <?php foreach ($brand_terms as $bt) :
            $filter_val = isset($_GET['filter_tag']) ? $_GET['filter_tag'] : '';
            $checked = (strpos($filter_val, $bt->slug) !== false) ? ' checked' : '';
            $url = add_query_arg('filter_tag', $bt->slug);
          ?>
            <label>
              <input type="checkbox" onchange="window.location.href='<?php echo esc_url($url); ?>'"<?php echo $checked; ?>>
              <?php echo esc_html($bt->name); ?>
              <span style="color:var(--dim);font-family:var(--f-mono);font-size:10px;">(<?php echo (int)$bt->count; ?>)</span>
            </label>
          <?php endforeach; ?>
        </div>
      </details>
      <?php endif; ?>

      <details class="ng-shop-filter-group" open>
        <summary>الحالة <span style="color:var(--dim);">−</span></summary>
        <div class="ng-filter-items">
          <?php foreach (['متوفر' => 'instock', 'تخفيض' => 'sale', 'جديد في آخر 30 يوم' => 'new'] as $label => $val) :
            $checked = (isset($_GET['filter_status']) && $_GET['filter_status'] === $val) ? ' checked' : '';
            $url = add_query_arg('filter_status', $val);
          ?>
            <label>
              <input type="checkbox" onchange="window.location.href='<?php echo esc_url($url); ?>'"<?php echo $checked; ?>>
              <?php echo esc_html($label); ?>
            </label>
          <?php endforeach; ?>
        </div>
      </details>

      <?php if (dynamic_sidebar('woocommerce-widget-area')) : ?>
      <?php endif; ?>
    </div>
  </aside>

  <!-- PRODUCT GRID -->
  <main>
    <div class="ng-shop-toolbar">
      <div class="woocommerce-result-count">
        <?php woocommerce_result_count(); ?>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <?php woocommerce_catalog_ordering(); ?>
        <div style="display:flex;border:1px solid var(--rule);border-radius:var(--r-1);overflow:hidden;">
          <button style="padding:9px 12px;background:var(--indigo);color:#fff;border:none;cursor:pointer;font-size:13px;" title="شبكة">⊞</button>
          <button style="padding:9px 12px;background:transparent;color:var(--ink-4);border:none;border-inline-start:1px solid var(--rule);cursor:pointer;font-size:13px;" title="قائمة">≡</button>
        </div>
      </div>
    </div>

    <?php
    // v1.38.0 — Active filter chip bar. Reads the same query args that
    // the sidebar checkboxes write, surfaces them as removable chips.
    $ng_active_filters = [];
    if ( isset( $_GET['min_price'] ) || isset( $_GET['max_price'] ) ) {
        $minp = isset( $_GET['min_price'] ) ? (int) $_GET['min_price'] : 0;
        $maxp = isset( $_GET['max_price'] ) ? (int) $_GET['max_price'] : 0;
        $label = '';
        if ( $maxp >= 99999 ) {
            $label = 'أكثر من ' . number_format_i18n( $minp ) . ' ريال';
        } elseif ( $minp === 0 && $maxp > 0 ) {
            $label = 'أقل من ' . number_format_i18n( $maxp ) . ' ريال';
        } elseif ( $minp > 0 || $maxp > 0 ) {
            $label = number_format_i18n( $minp ) . '–' . number_format_i18n( $maxp ) . ' ريال';
        }
        if ( $label !== '' ) {
            $ng_active_filters[] = [
                'label'  => $label,
                'remove' => esc_url( remove_query_arg( [ 'min_price', 'max_price' ] ) ),
            ];
        }
    }
    if ( ! empty( $_GET['filter_tag'] ) ) {
        $tag_slug = sanitize_text_field( wp_unslash( $_GET['filter_tag'] ) );
        $tag_term = get_term_by( 'slug', $tag_slug, 'product_tag' );
        if ( $tag_term ) {
            $ng_active_filters[] = [
                'label'  => $tag_term->name,
                'remove' => esc_url( remove_query_arg( 'filter_tag' ) ),
            ];
        }
    }
    if ( ! empty( $_GET['filter_status'] ) ) {
        $status_map = [ 'instock' => 'متوفر', 'sale' => 'تخفيض', 'new' => 'جديد' ];
        $status_key = sanitize_text_field( wp_unslash( $_GET['filter_status'] ) );
        if ( isset( $status_map[ $status_key ] ) ) {
            $ng_active_filters[] = [
                'label'  => $status_map[ $status_key ],
                'remove' => esc_url( remove_query_arg( 'filter_status' ) ),
            ];
        }
    }
    if ( ! empty( $ng_active_filters ) ) :
    ?>
    <nav class="ng-active-filters" aria-label="الفلاتر النشطة">
      <span class="label">الفلاتر النشطة:</span>
      <?php foreach ( $ng_active_filters as $f ) : ?>
        <a class="chip chip-solid" style="padding:6px 10px;font-size:11px;text-decoration:none;display:inline-flex;gap:6px;align-items:center;" href="<?php echo $f['remove']; // already escaped ?>">
          <?php echo esc_html( $f['label'] ); ?>
          <span aria-hidden="true">✕</span>
        </a>
      <?php endforeach; ?>
      <?php
      // Clear-all link only if more than one filter is active.
      if ( count( $ng_active_filters ) > 1 ) :
        $clear_all = esc_url( remove_query_arg( [ 'min_price', 'max_price', 'filter_tag', 'filter_status' ] ) );
      ?>
        <a href="<?php echo $clear_all; ?>" style="font-size:11px;color:var(--accent-deep);text-decoration:none;font-family:var(--font-mono);text-transform:uppercase;letter-spacing:.07em;">مسح الكل</a>
      <?php endif; ?>
      <span class="count"><?php echo esc_html( number_format_i18n( (int) $total ) ); ?> منتج</span>
    </nav>
    <?php endif; ?>

    <?php if (woocommerce_product_loop()) : ?>

      <?php do_action('woocommerce_before_shop_loop'); ?>

      <ul class="ng-shop-grid products">
        <?php woocommerce_product_loop_start(); ?>
        <?php while (have_posts()) : the_post(); ?>
          <?php wc_get_template_part('content', 'product'); ?>
        <?php endwhile; ?>
        <?php woocommerce_product_loop_end(); ?>
      </ul>

      <?php do_action('woocommerce_after_shop_loop'); ?>

      <div class="ng-shop-pagination">
        <?php woocommerce_pagination(); ?>
      </div>

    <?php else : ?>

      <!-- Empty state -->
      <div style="text-align:center;padding:80px 32px;background:var(--surface);border:1px solid var(--rule);border-radius:var(--r-2);">
        <div style="font-family:var(--f-mono);font-size:48px;color:var(--dim);margin-bottom:16px;">∅</div>
        <div style="font-weight:700;font-size:20px;color:var(--indigo);margin-bottom:8px;">لا توجد منتجات مطابقة</div>
        <div style="font-size:14px;color:var(--ink-4);margin-bottom:24px;">جرّب تعديل الفلاتر أو تصفّح فئة أخرى.</div>
        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="ng-btn">عرض كل المنتجات</a>
      </div>

      <?php do_action('woocommerce_no_products_found'); ?>

    <?php endif; ?>
  </main>

</div>

<?php
do_action('woocommerce_after_main_content');
get_footer('shop');
