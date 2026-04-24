<?php
/**
 * NeoGen empty-search template.
 *
 * Routed via template_include only when is_search() AND
 * $wp_query->found_posts === 0. Non-empty search results fall
 * through to the default Blocksy search.php.
 */

defined('ABSPATH') || exit;
if (!defined('NG_RENDER_SEARCH_EMPTY')) return;
if (!function_exists('get_header')) return;

$term = trim((string) get_search_query());

get_header();
?>

<main class="ng-search-empty ng-legal-page">

  <section class="ng-legal-hero">
    <div class="ng-legal-bg" aria-hidden="true">
      <svg viewBox="-50 -50 100 100">
        <path d="M0 -44 L9 -26 L35 -35 L26 -9 L44 0 L26 9 L35 35 L9 26 L0 44 L-9 26 L-35 35 L-26 9 L-44 0 L-26 -9 L-35 -35 L-9 -26 Z"/>
      </svg>
    </div>
    <div class="ng-legal-inner">
      <div class="ng-legal-kicker">
        <span class="led warn" aria-hidden="true"></span>
        <span>SEARCH · NO MATCHES</span>
      </div>

      <h1 class="ng-legal-h1 ng-search-empty-h1">
        <span class="ar">لا توجد نتائج</span>
        <span class="en">NO RESULTS FOR <code><?php echo esc_html($term); ?></code></span>
      </h1>

      <p class="ng-legal-lede">
        <?php
        printf(
          esc_html__('We couldn\'t find anything matching %s. Try a broader keyword, browse a rack, or send us a brief.', 'neogen'),
          '<code>' . esc_html($term) . '</code>'
        );
        ?>
      </p>

      <form role="search" method="get" class="ng-search-empty-form" action="<?php echo esc_url(home_url('/')); ?>">
        <label class="screen-reader-text" for="s"><?php esc_html_e('Search again', 'neogen'); ?></label>
        <input type="search" id="s" name="s" value="<?php echo esc_attr($term); ?>" placeholder="<?php esc_attr_e('Try a different keyword...', 'neogen'); ?>" />
        <button type="submit"><?php esc_html_e('Search', 'neogen'); ?> →</button>
      </form>
    </div>
  </section>

  <section class="ng-legal-section">
    <div class="ng-legal-inner">

      <?php
      $cats = function_exists('ng_top_product_cats') ? ng_top_product_cats(5) : [];
      if (!empty($cats)) :
      ?>
      <div class="ng-404-cats">
        <div class="ng-404-cats-head">
          <span>// BROWSE BY RACK</span>
          <span><?php echo esc_html(count($cats)); ?> CATEGORIES</span>
        </div>
        <ul>
          <?php foreach ($cats as $t) :
              $link = get_term_link($t);
              if (is_wp_error($link)) { continue; }
          ?>
          <li>
            <a href="<?php echo esc_url($link); ?>">
              <span class="dot"></span>
              <span class="name"><?php echo esc_html($t->name); ?></span>
              <span class="count"><?php echo esc_html((int) $t->count); ?> SKUs</span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php echo do_shortcode('[neogen_recommendations limit="4" title_ar="مقترحات لك" title_en="OPERATOR · NEXT PICKS"]'); ?>

      <p class="ng-search-empty-contact">
        <?php
        $cr = function_exists('ng_cr') ? ng_cr() : ['email' => '', 'phone_mobile' => ''];
        printf(
          esc_html__('Looking for something specific we don\'t list? Send a brief: %1$s or %2$s.', 'neogen'),
          $cr['email'] ? '<a href="mailto:' . esc_attr($cr['email']) . '">' . esc_html($cr['email']) . '</a>' : '',
          $cr['phone_mobile'] ? '<a href="tel:' . esc_attr(preg_replace('/\s+/', '', $cr['phone_mobile'])) . '">' . esc_html($cr['phone_mobile']) . '</a>' : ''
        );
        ?>
      </p>

    </div>
  </section>

</main>

<?php
get_footer();
