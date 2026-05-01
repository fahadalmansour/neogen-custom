<?php
/**
 * Phase 1 · Homepage hero rotating gallery + trust strip.
 *
 * Faithful recreation of homepage.jsx (lines 24–172) in PHP/HTML.
 * Included from templates/front-page.php when ng_redesign_active('homepage')
 * returns true; otherwise the legacy <header class="ng-hero"> renders.
 *
 * Variables consumed (already prepared by front-page.php):
 *   $picks          array<WC_Product>  diversified product picks pool
 *   $top_categories array<WP_Term>     fallback for thumbs when picks short
 *   $shop_url       string             /shop permalink
 *   $contact_url    string             /contact permalink
 *   $whatsapp_url   string             wa.me link (may be empty)
 *
 * No globals defined here — this file is pure markup + tightly scoped
 * locals. Exits on direct access.
 */

defined('ABSPATH') || exit;
if ( ! function_exists( 'ng_redesign_active' ) || ! ng_redesign_active( 'homepage' ) ) { return; }

// Build hero product list — up to 5, mirroring the design's heroProducts array.
$ngrd_hero_products = [];
$ngrd_seed = [];
if ( ! empty( $picks ) ) {
    foreach ( $picks as $hp ) {
        if ( count( $ngrd_seed ) >= 5 ) { break; }
        if ( $hp instanceof WC_Product ) { $ngrd_seed[] = $hp; }
    }
}
// Fallback: latest published products when picks pool is short.
if ( count( $ngrd_seed ) < 5 && function_exists( 'wc_get_products' ) ) {
    $needed = 5 - count( $ngrd_seed );
    $existing_ids = array_map( function ( $p ) { return $p->get_id(); }, $ngrd_seed );
    $more = wc_get_products( [
        'status'  => 'publish',
        'limit'   => $needed,
        'orderby' => 'date',
        'order'   => 'DESC',
        'exclude' => $existing_ids ?: [ 0 ],
    ] );
    foreach ( (array) $more as $p ) {
        if ( $p instanceof WC_Product ) { $ngrd_seed[] = $p; }
    }
}

foreach ( $ngrd_seed as $hp ) {
    $hp_id   = $hp->get_id();
    $hp_sku  = $hp->get_sku() ?: ( 'NG-' . $hp_id );
    $hp_en   = $hp->get_name();
    $hp_ar   = (string) get_post_meta( $hp_id, '_ng_ar_title', true );
    if ( $hp_ar === '' ) {
        $hp_ar = function_exists( 'ng_ar_label' ) ? ng_ar_label( $hp_en ) : $hp_en;
    }
    if ( function_exists( 'ng_gift_card_clean_product_name' ) ) {
        $hp_en = ng_gift_card_clean_product_name( $hp_en );
        $hp_ar = ng_gift_card_clean_product_name( $hp_ar );
    }

    // Category chip — prefer Arabic term meta when present.
    $hp_cat_name = '';
    $hp_cats = get_the_terms( $hp_id, 'product_cat' );
    if ( ! empty( $hp_cats ) && ! is_wp_error( $hp_cats ) ) {
        $hp_cat_name = (string) get_term_meta( $hp_cats[0]->term_id, '_ng_ar_label', true );
        if ( $hp_cat_name === '' ) { $hp_cat_name = $hp_cats[0]->name; }
    }

    // Image — real featured image, gift-card override, or empty.
    $hp_img_html  = '';
    $hp_thumb_html = '';
    $hp_img_id = (int) $hp->get_image_id();
    if ( $hp_img_id ) {
        $hp_img_html   = wp_get_attachment_image( $hp_img_id, 'large',     false, [ 'alt' => esc_attr( $hp_en ), 'loading' => 'eager', 'decoding' => 'async' ] );
        $hp_thumb_html = wp_get_attachment_image( $hp_img_id, 'thumbnail', false, [ 'alt' => '',                  'loading' => 'lazy',  'decoding' => 'async' ] );
    }
    if ( function_exists( 'ng_gift_card_image_html' ) ) {
        $g = ng_gift_card_image_html( $hp, 'large', $hp_en, null, [ 'loading' => 'eager' ] );
        if ( $g ) { $hp_img_html = $g; }
    }

    $ngrd_hero_products[] = [
        'id'        => $hp_id,
        'sku'       => $hp_sku,
        'ar'        => $hp_ar,
        'en'        => $hp_en,
        'cat'       => $hp_cat_name,
        'price'     => $hp->get_price_html(),
        'perm'      => get_permalink( $hp_id ),
        'img'       => $hp_img_html,
        'thumb'     => $hp_thumb_html,
        'cta_url'   => $hp->is_type( 'simple' ) && $hp->is_in_stock() ? $hp->add_to_cart_url() : get_permalink( $hp_id ),
        'cta_label' => $hp->is_type( 'simple' ) && $hp->is_in_stock() ? 'أضف للسلة' : 'عرض',
    ];
}

$ngrd_hero_total = count( $ngrd_hero_products );

// Trust-strip data — pulls live CR# when available, falls back to the
// known constant. Five cells matching the design exactly.
$ngrd_cr_number = '7053130576';
if ( function_exists( 'ng_cr' ) ) {
    $ngrd_cr = ng_cr();
    if ( ! empty( $ngrd_cr['number'] ) ) { $ngrd_cr_number = (string) $ngrd_cr['number']; }
} elseif ( defined( 'NG_CR' ) ) {
    $ngrd_cr_number = (string) NG_CR;
}
$ngrd_trust = [
    [ 'k' => 'السجل التجاري', 'v' => $ngrd_cr_number ],
    [ 'k' => 'الضريبة',        'v' => '15% شاملة' ],
    [ 'k' => 'الشحن',          'v' => '2–5 أيام عمل' ],
    [ 'k' => 'الإرجاع',        'v' => '14 يوم' ],
    [ 'k' => 'الضمان',         'v' => '12 شهر' ],
];

// GCC ship-to badges.
$ngrd_ships = [
    [ 'flag' => '🇸🇦', 'code' => 'KSA' ],
    [ 'flag' => '🇦🇪', 'code' => 'UAE' ],
    [ 'flag' => '🇰🇼', 'code' => 'KW'  ],
    [ 'flag' => '🇧🇭', 'code' => 'BH'  ],
    [ 'flag' => '🇴🇲', 'code' => 'OM'  ],
    [ 'flag' => '🇶🇦', 'code' => 'QA'  ],
];
?>
<header class="ngrd-hero" dir="rtl">
  <div class="ngrd-hero__inner">
    <div class="ngrd-hero__grid">

      <!-- Left text panel -->
      <div class="ngrd-hero__main">
        <div class="ngrd-hero__kicker">
          <span class="dot" aria-hidden="true"></span>
          <?php echo esc_html__( 'متجر تقني سعودي · معتمد · 2026', 'neogen' ); ?>
        </div>
        <h1 class="ngrd-hero__h1">
          جيل<br>
          <span class="ngrd-accent">النِّقلة</span><br>
          التقنية.
        </h1>
        <div class="ngrd-hero__rule" aria-hidden="true"></div>
        <p class="ngrd-hero__copy">
          وحدات مختارة لمحترفي الشبكات، الهوم لاب، البيوت الذكية، والألعاب.
          مواصفات بدون مبالغة. شحن من المملكة لكل دول الخليج.
        </p>
        <div class="ngrd-hero__ctas">
          <a class="ngrd-btn" href="<?php echo esc_url( $shop_url ); ?>">
            تصفّح المتجر
            <span style="font-family:var(--font-wordmark);">→</span>
          </a>
          <a class="ngrd-btn ngrd-btn--ghost" href="#ng-service">ابنِ جهازك</a>
        </div>
        <div class="ngrd-hero__ships">
          <span class="label">يشحن إلى:</span>
          <?php foreach ( $ngrd_ships as $s ) : ?>
            <span class="country">
              <span class="flag" aria-hidden="true"><?php echo esc_html( $s['flag'] ); ?></span>
              <?php echo esc_html( $s['code'] ); ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Right gallery panel -->
      <?php if ( $ngrd_hero_total > 0 ) : ?>
        <div class="ngrd-hero__gallery" data-ngrd-hero-gallery>
          <div class="ngrd-hero__card">
            <?php foreach ( $ngrd_hero_products as $i => $hp ) : ?>
              <div class="ngrd-hero__slide" data-ngrd-hero-slide<?php echo $i === 0 ? '' : ' hidden'; ?>>
                <?php if ( $hp['cat'] !== '' ) : ?>
                  <div class="ngrd-hero__cat"><span class="ngrd-chip ngrd-chip--sky"><?php echo esc_html( $hp['cat'] ); ?></span></div>
                <?php endif; ?>
                <a class="ngrd-hero__media" href="<?php echo esc_url( $hp['perm'] ); ?>" aria-label="<?php echo esc_attr( $hp['en'] ); ?>">
                  <?php if ( $hp['img'] ) {
                      echo $hp['img']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_get_attachment_image returns safe HTML
                  } else { ?>
                    <span class="ngrd-r1-ph__label"><?php echo esc_html( strtoupper( $hp['sku'] ) ); ?></span>
                  <?php } ?>
                </a>
                <div class="ngrd-hero__info">
                  <div class="sku"><?php echo esc_html( strtoupper( $hp['sku'] ) ); ?></div>
                  <h3><a href="<?php echo esc_url( $hp['perm'] ); ?>"><?php echo esc_html( $hp['ar'] ); ?></a></h3>
                  <?php if ( trim( $hp['en'] ) !== '' && trim( $hp['en'] ) !== trim( $hp['ar'] ) ) : ?>
                    <span class="en"><?php echo esc_html( $hp['en'] ); ?></span>
                  <?php endif; ?>
                  <div class="row">
                    <div class="ngrd-price"><?php echo wp_kses_post( $hp['price'] ); ?></div>
                    <a class="ngrd-btn ngrd-btn--sm" href="<?php echo esc_url( $hp['cta_url'] ); ?>"><?php echo esc_html( $hp['cta_label'] ); ?> +</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            <div class="ngrd-hero__progress" aria-hidden="true">
              <span data-ngrd-hero-progress style="width:<?php echo esc_attr( (int) round( ( 1 / max( 1, $ngrd_hero_total ) ) * 100 ) ); ?>%;"></span>
            </div>
          </div>

          <div class="ngrd-hero__thumbs" role="tablist" aria-label="معرض المنتجات">
            <?php foreach ( $ngrd_hero_products as $i => $hp ) : ?>
              <button type="button" data-ngrd-hero-thumb role="tab" aria-label="<?php echo esc_attr( $hp['ar'] ); ?>"<?php echo $i === 0 ? ' aria-current="true"' : ''; ?>>
                <?php if ( $hp['thumb'] ) {
                    echo $hp['thumb']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_get_attachment_image returns safe HTML
                } ?>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="ngrd-hero__dots" aria-hidden="true">
            <?php foreach ( $ngrd_hero_products as $i => $hp ) : ?>
              <button type="button" data-ngrd-hero-dot<?php echo $i === 0 ? ' aria-current="true"' : ''; ?>></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>

    <!-- Trust strip -->
    <div class="ngrd-hero__trust" aria-label="ضمانات المتجر">
      <?php foreach ( $ngrd_trust as $cell ) : ?>
        <div>
          <span class="k"><?php echo esc_html( $cell['k'] ); ?></span>
          <span class="v"><?php echo esc_html( $cell['v'] ); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</header>
