<?php
/**
 * One-off: convert the ng_info_pages() registry into real, editable WP
 * pages so the operator can edit them in wp-admin → Pages.
 *
 * Run via:
 *   wp eval-file /tmp/neogen-info-pages-seed.php --skip-plugins=litespeed-cache --user=1
 *
 * For each slug in ng_info_pages() (about, shipping, returns, warranty,
 * terms, usage, privacy, contact) plus a special-case for `contact`:
 *
 *   - If a published page already exists at that slug → update content only.
 *   - If a draft `privacy-policy` exists (id 3, WP default) → rename slug
 *     to `privacy`, publish, set content from registry.
 *   - Otherwise → wp_insert_post() a new page with that slug.
 *
 * Each page gets post_meta `_ng_info_page = 1` so the seeder can identify
 * its own work later.
 *
 * Re-running is idempotent — content gets re-written from the registry.
 * If the operator edits a page in wp-admin and DOESN'T want it overwritten,
 * just don't re-run this script.
 *
 * Note on /legal/: kept as a virtual route by neogen-theme.php since it's
 * auto-derived from ng_cr(). NOT part of this seeder.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ng_info_pages' ) ) {
    WP_CLI::error( 'ng_info_pages() is not defined — neogen-theme.php must load before running this script.' );
}

$pages = ng_info_pages();

/**
 * Render one info page from registry shape into HTML using the same
 * .ng-page-hero / .ng-page-section / etc. classes the rest of the site uses.
 * Bilingual: Arabic-first, English secondary.
 */
$render = function ( $slug, array $page ) {
    $kicker  = isset( $page['kicker'] )   ? $page['kicker']   : strtoupper( $slug );
    $h1_ar   = isset( $page['h1_ar'] )    ? $page['h1_ar']    : '';
    $h1_en   = isset( $page['h1_en'] )    ? $page['h1_en']    : '';
    $lede_ar = isset( $page['lede_ar'] )  ? $page['lede_ar']  : '';
    $lede_en = isset( $page['lede_en'] )  ? $page['lede_en']  : '';
    $is_draft = ! empty( $page['draft'] );
    $sections = isset( $page['sections'] ) && is_array( $page['sections'] ) ? $page['sections'] : array();

    ob_start();
    ?>
<section class="ng-page-hero ng-page-hero--info">
  <div class="ng-container">
    <div class="ng-page-hero-kicker"><?php echo wp_kses_post( $kicker ); ?></div>
    <h1 class="ng-page-hero-h1"><?php echo esc_html( $h1_ar ); ?></h1>
    <?php if ( $h1_en !== '' ) : ?><div class="ng-page-hero-h1-en"><?php echo esc_html( $h1_en ); ?></div><?php endif; ?>
    <?php if ( $lede_ar !== '' ) : ?><p class="ng-page-hero-copy"><?php echo esc_html( $lede_ar ); ?></p><?php endif; ?>
    <?php if ( $lede_en !== '' ) : ?><p class="ng-page-hero-copy ng-page-hero-copy--en"><?php echo esc_html( $lede_en ); ?></p><?php endif; ?>
    <?php if ( $is_draft ) : ?>
      <div class="ng-page-draft-flag">مسودة — بانتظار المراجعة القانونية النهائية</div>
    <?php endif; ?>
  </div>
</section>

<?php if ( ! empty( $sections ) ) : ?>
<section class="ng-page-section ng-page-section--info">
  <div class="ng-container">
    <?php foreach ( $sections as $i => $section ) :
        $kk_en = isset( $section['kicker_en'] ) ? $section['kicker_en'] : '';
        $h_en  = isset( $section['h_en'] )      ? $section['h_en']      : '';
        $h_ar  = isset( $section['h_ar'] )      ? $section['h_ar']      : '';
        $body  = isset( $section['body'] ) && is_array( $section['body'] ) ? $section['body'] : array();
    ?>
    <article class="ng-info-section">
      <header class="ng-info-section-head">
        <?php if ( $kk_en !== '' ) : ?><div class="ng-info-section-kicker"><?php echo esc_html( $kk_en ); ?></div><?php endif; ?>
        <?php if ( $h_ar !== '' )  : ?><h2 class="ng-info-section-h-ar"><?php echo esc_html( $h_ar ); ?></h2><?php endif; ?>
        <?php if ( $h_en !== '' )  : ?><div class="ng-info-section-h-en"><?php echo esc_html( $h_en ); ?></div><?php endif; ?>
      </header>
      <?php foreach ( $body as $para ) : ?>
        <p class="ng-info-section-body"><?php echo wp_kses_post( $para ); ?></p>
      <?php endforeach; ?>
    </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php
    return ob_get_clean();
};

$created = 0;
$updated = 0;
$failed  = array();

foreach ( $pages as $slug => $page ) {
    // Skip legal — kept virtual.
    if ( $slug === 'legal' ) { continue; }

    $title    = ! empty( $page['h1_ar'] ) ? $page['h1_ar'] : ucfirst( $slug );
    $content  = $render( $slug, $page );
    $existing = get_page_by_path( $slug, OBJECT, 'page' );

    // Special case: WP-default 'privacy-policy' (id 3, draft) → migrate to 'privacy'.
    if ( $slug === 'privacy' && ! $existing ) {
        $legacy = get_page_by_path( 'privacy-policy', OBJECT, 'page' );
        if ( $legacy ) {
            $existing = $legacy;
            // Rename slug to match the registry key.
            wp_update_post( array(
                'ID'        => $legacy->ID,
                'post_name' => 'privacy',
            ) );
        }
    }

    if ( $existing ) {
        $r = wp_update_post( array(
            'ID'           => $existing->ID,
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_name'    => $slug,
        ), true );
        if ( is_wp_error( $r ) ) {
            $failed[] = "$slug: " . $r->get_error_message();
            continue;
        }
        update_post_meta( $existing->ID, '_ng_info_page', 1 );
        update_post_meta( $existing->ID, '_ng_info_seed_version', '1.31.0' );
        $updated++;
        WP_CLI::log( sprintf( '  [updated] /%s/  →  id %d  (%d bytes)', $slug, $existing->ID, strlen( $content ) ) );
    } else {
        $r = wp_insert_post( array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
            'post_author'  => 1,
        ), true );
        if ( is_wp_error( $r ) ) {
            $failed[] = "$slug: " . $r->get_error_message();
            continue;
        }
        update_post_meta( $r, '_ng_info_page', 1 );
        update_post_meta( $r, '_ng_info_seed_version', '1.31.0' );
        $created++;
        WP_CLI::log( sprintf( '  [created] /%s/  →  id %d  (%d bytes)', $slug, $r, strlen( $content ) ) );
    }
}

// Flush rewrite rules so the new "yield to real pages" logic in
// neogen-theme.php takes effect immediately.
flush_rewrite_rules( false );

WP_CLI::log( '---' );
WP_CLI::log( "Created: $created" );
WP_CLI::log( "Updated: $updated" );
if ( $failed ) {
    WP_CLI::log( '---' );
    WP_CLI::log( 'Failures:' );
    foreach ( $failed as $f ) { WP_CLI::log( "  $f" ); }
}
WP_CLI::log( 'Rewrite rules flushed.' );
