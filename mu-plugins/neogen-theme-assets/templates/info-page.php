<?php
/**
 * NeoGen shared info-page template — renders any page from the
 * ng_info_pages() registry (about, terms, privacy, returns,
 * warranty, shipping, contact).
 *
 * Routed via template_include in mu-plugins/neogen-theme.php when
 * neogen_page query var matches a registry slug. NG_RENDER_INFO_PAGE
 * carries the slug.
 *
 * Pages with `draft => true` show a top-of-page "PENDING LEGAL
 * REVIEW" banner. Each draft section can show an inline pending
 * marker via the .ng-pending span included in its body.
 *
 * Real (non-draft) copy can be appended via:
 *   add_action('neogen_info_extra_<slug>', fn($cr, $page) => echo …)
 */

defined('ABSPATH') || exit;
if (!defined('NG_RENDER_INFO_PAGE')) return;
if (!function_exists('get_header')) return;

$slug  = NG_RENDER_INFO_PAGE;
$pages = ng_info_pages();
if (!isset($pages[$slug])) return;

$page = $pages[$slug];
$cr   = ng_cr();

get_header();
?>

<main class="ng-legal-page ng-info-page ng-info-page--<?php echo esc_attr($slug); ?>">

  <section class="ng-legal-hero ng-info-hero">
    <div class="ng-legal-bg" aria-hidden="true">
      <svg viewBox="-50 -50 100 100">
        <path d="M0 -44 L9 -26 L35 -35 L26 -9 L44 0 L26 9 L35 35 L9 26 L0 44 L-9 26 L-35 35 L-26 9 L-44 0 L-26 -9 L-35 -35 L-9 -26 Z"/>
      </svg>
    </div>
    <div class="ng-legal-inner">
      <div class="ng-legal-kicker">
        <span class="led<?php echo !empty($page['draft']) ? ' warn' : ' on'; ?>" aria-hidden="true"></span>
        <span><?php echo esc_html($page['kicker']); ?></span>
      </div>
      <h1 class="ng-legal-h1">
        <span class="ar"><?php echo esc_html($page['h1_ar']); ?></span>
        <span class="en"><?php echo esc_html($page['h1_en']); ?></span>
      </h1>
      <?php if (!empty($page['lede_ar'])) : ?>
      <p class="ng-legal-lede ng-legal-lede--ar"><?php echo wp_kses_post($page['lede_ar']); ?></p>
      <?php endif; ?>
      <?php if (!empty($page['lede_en'])) : ?>
      <p class="ng-legal-lede"><?php echo wp_kses_post($page['lede_en']); ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!empty($page['draft'])) : ?>
  <div class="ng-info-banner" role="note">
    <div class="ng-legal-inner">
      <span class="ng-info-banner-led" aria-hidden="true"></span>
      <div class="ng-info-banner-text">
        <strong>DRAFT — PENDING LEGAL REVIEW</strong>
        <span>The body text on this page is a placeholder structure and is <em>not</em> authoritative. Final wording is being prepared by legal counsel before publication.</span>
        <span class="ar">هذه الصفحة مسودة قيد المراجعة القانونية. النصوص الواردة ليست نهائية.</span>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <section class="ng-legal-section ng-info-section">
    <div class="ng-legal-inner">

      <?php foreach ($page['sections'] as $section) : ?>
      <article class="ng-info-block">
        <div class="ng-info-block-head">
          <span class="ng-info-block-kicker"><?php echo esc_html($section['kicker_en']); ?></span>
          <h2 class="ng-info-block-h">
            <?php if (!empty($section['h_ar'])) : ?>
              <span class="ar"><?php echo esc_html($section['h_ar']); ?></span>
            <?php endif; ?>
            <span class="en"><?php echo esc_html($section['h_en']); ?></span>
          </h2>
        </div>
        <div class="ng-info-block-body">
          <?php foreach ($section['body'] as $para) : ?>
            <p><?php echo wp_kses_post($para); ?></p>
          <?php endforeach; ?>
        </div>
      </article>
      <?php endforeach; ?>

      <?php
      /**
       * Extension hook — real legal copy arrives here per page.
       * Example:
       *   add_action('neogen_info_extra_terms', function ($cr, $page) {
       *       echo '<article class="ng-info-block">…lawyer-supplied HTML…</article>';
       *   }, 10, 2);
       */
      do_action('neogen_info_extra_' . $slug, $cr, $page);
      ?>

      <div class="ng-legal-voice">
        TECHNOLOGY
        <span class="sep"></span>
        AS IT SHOULD BE
        <span class="sep"></span>
        SHIPPED FROM KSA
      </div>

    </div>
  </section>

</main>

<?php
get_footer();
