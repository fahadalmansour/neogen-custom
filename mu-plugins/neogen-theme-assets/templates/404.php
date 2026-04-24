<?php
/**
 * NeoGen 404 page — operator-console "ROUTE NOT FOUND".
 *
 * Routed via template_include in mu-plugins/neogen-theme.php when
 * is_404() is true. Guarded by NG_RENDER_404 sentinel so direct
 * require at mu-plugin boot is a clean no-op.
 */

defined('ABSPATH') || exit;
if (!defined('NG_RENDER_404')) return;
if (!function_exists('get_header')) return;

status_header(404);
nocache_headers();

get_header();
?>

<main class="ng-404 ng-legal-page">

  <section class="ng-legal-hero ng-404-hero">
    <div class="ng-legal-bg" aria-hidden="true">
      <svg viewBox="-50 -50 100 100">
        <path d="M0 -44 L9 -26 L35 -35 L26 -9 L44 0 L26 9 L35 35 L9 26 L0 44 L-9 26 L-35 35 L-26 9 L-44 0 L-26 -9 L-35 -35 L-9 -26 Z"/>
      </svg>
    </div>
    <div class="ng-legal-inner">
      <div class="ng-legal-kicker">
        <span class="led warn" aria-hidden="true"></span>
        <span>STATUS · 404 · ROUTE NOT FOUND</span>
      </div>

      <h1 class="ng-legal-h1 ng-404-h1">
        <span class="ar">المسار غير موجود</span>
        <span class="en">ROUTE NOT FOUND</span>
      </h1>

      <p class="ng-legal-lede">
        <?php
        $req = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field((string) wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if ($req !== '') {
          printf(
            esc_html__('The URL %s does not match any page on this server. Try one of the routes below.', 'neogen'),
            '<code>' . esc_html(wp_strip_all_tags($req)) . '</code>'
          );
        } else {
          esc_html_e('The page you requested does not exist on this server.', 'neogen');
        }
        ?>
      </p>
    </div>
  </section>

  <section class="ng-legal-section ng-404-section">
    <div class="ng-legal-inner">

      <div class="ng-404-tiles">
        <a class="ng-404-tile" href="<?php echo esc_url(home_url('/')); ?>">
          <div class="k">01</div>
          <div class="lbl">
            <span class="ar">الرئيسية</span>
            <span class="en">HOME</span>
          </div>
        </a>
        <a class="ng-404-tile" href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/')); ?>">
          <div class="k">02</div>
          <div class="lbl">
            <span class="ar">المتجر</span>
            <span class="en">SHOP</span>
          </div>
        </a>
        <a class="ng-404-tile" href="<?php echo esc_url(home_url('/contact/')); ?>">
          <div class="k">03</div>
          <div class="lbl">
            <span class="ar">تواصل معنا</span>
            <span class="en">CONTACT</span>
          </div>
        </a>
        <a class="ng-404-tile" href="<?php echo esc_url(home_url('/legal/')); ?>">
          <div class="k">04</div>
          <div class="lbl">
            <span class="ar">هوية المنشأة</span>
            <span class="en">LEGAL</span>
          </div>
        </a>
      </div>

      <?php
      // Top categories — surface the "racks" so a lost visitor can pivot.
      $cats = function_exists('ng_top_product_cats') ? ng_top_product_cats(5) : [];
      if (!empty($cats)) :
      ?>
      <div class="ng-404-cats">
        <div class="ng-404-cats-head">
          <span>// BROWSE BY RACK</span>
          <span><?php echo esc_html(count($cats)); ?> CATEGORIES</span>
        </div>
        <ul>
          <?php foreach ($cats as $term) :
              $link = get_term_link($term);
              if (is_wp_error($link)) { continue; }
          ?>
          <li>
            <a href="<?php echo esc_url($link); ?>">
              <span class="dot"></span>
              <span class="name"><?php echo esc_html($term->name); ?></span>
              <span class="count"><?php echo esc_html((int) $term->count); ?> SKUs</span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php
      // Recommendations from recently-viewed cookie.
      echo do_shortcode('[neogen_recommendations limit="4" title_ar="مقترحات لك" title_en="OPERATOR · NEXT PICKS"]');
      ?>

    </div>
  </section>

</main>

<?php
get_footer();
