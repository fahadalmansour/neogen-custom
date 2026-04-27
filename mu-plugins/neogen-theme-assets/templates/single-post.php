<?php
/**
 * NeoGen single-post template — editorial reading layout.
 *
 * Routed via template_include in mu-plugins/neogen-theme.php when
 * is_singular('post') is true. Guarded by NG_RENDER_POST sentinel.
 */

defined('ABSPATH') || exit;
if (!defined('NG_RENDER_POST')) return;
if (!function_exists('get_header')) return;

get_header();

if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();
        $post_id     = get_the_ID();
        $title       = get_the_title();
        $excerpt     = get_the_excerpt();
        $author      = get_the_author();
        $published   = get_the_date( 'Y-m-d' );
        $reading_min = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 ) );
        $cats        = get_the_category();
        $cat         = ! empty( $cats ) ? $cats[0] : null;
        $thumb_url   = has_post_thumbnail() ? get_the_post_thumbnail_url( $post_id, 'large' ) : '';
?>

<article class="ng-post" itemscope itemtype="https://schema.org/Article">

  <header class="ng-post-head">
    <div class="ng-post-kicker">
      <span class="led on" aria-hidden="true"></span>
      <span>// مقال · مدوّنة</span>
      <?php if ( $cat ) : $link = get_category_link( $cat->term_id ); ?>
        <span class="sep"></span>
        <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $cat->name ); ?></a>
      <?php endif; ?>
    </div>

    <h1 class="ng-post-h1" itemprop="headline"><?php echo esc_html( $title ); ?></h1>

    <?php if ( $excerpt ) : ?>
      <p class="ng-post-lede"><?php echo esc_html( $excerpt ); ?></p>
    <?php endif; ?>

    <div class="ng-post-meta">
      <span class="k">الكاتب</span><span class="v"><?php echo esc_html( $author ); ?></span>
      <span class="sep"></span>
      <span class="k">نُشر</span><span class="v"><?php echo esc_html( $published ); ?></span>
      <span class="sep"></span>
      <span class="k">قراءة</span><span class="v"><?php echo esc_html( $reading_min ); ?> دقيقة</span>
    </div>
  </header>

  <?php if ( $thumb_url ) : ?>
    <figure class="ng-post-figure">
      <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="eager" fetchpriority="high">
    </figure>
  <?php endif; ?>

  <div class="ng-post-body" itemprop="articleBody">
    <?php the_content(); ?>
  </div>

  <footer class="ng-post-foot">
    <?php
    $tags = get_the_tags();
    if ( ! empty( $tags ) ) : ?>
      <div class="ng-post-tags">
        <span class="k">// الوسوم</span>
        <?php foreach ( $tags as $t ) : ?>
          <a href="<?php echo esc_url( get_tag_link( $t->term_id ) ); ?>"># <?php echo esc_html( $t->name ); ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="ng-post-cta">
      <a class="btn btn-primary" href="<?php echo esc_url( function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/') ); ?>">
        تصفّح المتجر
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
      </a>
      <a class="btn btn-ghost" href="<?php echo esc_url( home_url('/blog/') ); ?>">المدوّنة</a>
    </div>
  </footer>

</article>

<?php
    endwhile;
endif;

get_footer();
