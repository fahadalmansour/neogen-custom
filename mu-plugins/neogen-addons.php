<?php
/**
 * Plugin Name: NeoGen Add-ons & Replacements
 * Description: Per-product Add-ons & Replacements list (upgrades, consumables, spare parts) shown on the PDP under the Works Best With section. Stored as JSON in the _ng_addons post meta. Empty value = section hidden. Mirrors the conventions of neogen-product-meta.php and neogen-product-video.php.
 * Version: 1.38.0
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

/**
 * Addon type catalogue. Keys persist; labels render in Arabic on the
 * front end and English in the metabox UI to keep the admin readable
 * for operators who don't read Arabic.
 */
function ng_addons_types() {
    return [
        'upgrade'    => [ 'ar' => 'ترقية',     'en' => 'Upgrade' ],
        'consumable' => [ 'ar' => 'استهلاكي',  'en' => 'Consumable' ],
        'spare'      => [ 'ar' => 'قطعة غيار', 'en' => 'Spare part' ],
    ];
}

/* ---------------------------------------------------------------------
 * Read helper — returns a normalized array of {sku, type, title} rows.
 * ------------------------------------------------------------------- */

function ng_get_addons( $product_id ) {
    $raw = get_post_meta( (int) $product_id, '_ng_addons', true );
    if ( empty( $raw ) ) {
        return [];
    }
    if ( is_string( $raw ) ) {
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }
        $raw = $decoded;
    }
    if ( ! is_array( $raw ) ) {
        return [];
    }
    $valid_types = array_keys( ng_addons_types() );
    $out = [];
    foreach ( $raw as $row ) {
        if ( ! is_array( $row ) ) { continue; }
        $sku   = isset( $row['sku'] )   ? trim( (string) $row['sku'] )   : '';
        $type  = isset( $row['type'] )  ? (string) $row['type']          : '';
        $title = isset( $row['title'] ) ? trim( (string) $row['title'] ) : '';
        if ( ! in_array( $type, $valid_types, true ) ) { $type = 'spare'; }
        if ( $sku === '' && $title === '' ) { continue; }
        $out[] = [
            'sku'   => $sku,
            'type'  => $type,
            'title' => $title,
        ];
    }
    return $out;
}

/* ---------------------------------------------------------------------
 * Resolver — given a SKU string, find the matching WC product (if any).
 * Used by the PDP renderer to enrich a row with image, link, and price.
 * ------------------------------------------------------------------- */

function ng_addons_resolve_product( $sku ) {
    $sku = trim( (string) $sku );
    if ( $sku === '' ) { return null; }
    if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) { return null; }
    $id = wc_get_product_id_by_sku( $sku );
    if ( ! $id ) { return null; }
    $p = wc_get_product( $id );
    return $p instanceof WC_Product ? $p : null;
}

/* ---------------------------------------------------------------------
 * Renderer — outputs the Add-ons & Replacements grid.
 * Returns an empty string when the product has no addons stored, so
 * the caller can simply `echo ng_render_addons($product_id)` without
 * a wrapping section.
 * ------------------------------------------------------------------- */

function ng_render_addons( $product_id ) {
    $rows = ng_get_addons( $product_id );
    if ( empty( $rows ) ) { return ''; }

    $types = ng_addons_types();

    ob_start();
    ?>
    <section class="ng-addons" aria-labelledby="ng-addons-heading-<?php echo (int) $product_id; ?>">
      <div class="head">
        <div>
          <div class="ng-section-label">B · <b>الإضافات والاستبدال</b> · ADD-ONS &amp; REPLACEMENTS</div>
          <h2 id="ng-addons-heading-<?php echo (int) $product_id; ?>" class="ng-section-h" style="font-size:20px;margin:8px 0 0;">ملحقات، قطع غيار، وترقيات لجهازك</h2>
        </div>
        <div class="ng-addons-filter" data-ng-addons-filter data-target="#ng-addons-grid-<?php echo (int) $product_id; ?>">
          <button type="button" data-filter="all" aria-pressed="true">الكل</button>
          <?php foreach ( $types as $key => $label ) : ?>
            <button type="button" data-filter="<?php echo esc_attr( $key ); ?>" aria-pressed="false"><?php echo esc_html( $label['ar'] ); ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="ng-addons-grid" id="ng-addons-grid-<?php echo (int) $product_id; ?>">
        <?php foreach ( $rows as $row ) :
          $resolved = ng_addons_resolve_product( $row['sku'] );
          $title_ar = $row['title'];
          $title_en = '';
          $perm     = '';
          $price    = '';
          $img_html = '';
          if ( $resolved instanceof WC_Product ) {
              $name_en  = $resolved->get_name();
              $name_ar  = (string) get_post_meta( $resolved->get_id(), '_ng_ar_title', true );
              if ( $name_ar === '' ) { $name_ar = $name_en; }
              if ( $title_ar === '' ) { $title_ar = $name_ar; }
              $title_en = ( $title_ar !== $name_en ) ? $name_en : '';
              $perm     = get_permalink( $resolved->get_id() );
              $price    = $resolved->get_price_html();
              $img_id   = $resolved->get_image_id();
              if ( $img_id ) {
                  $img_html = wp_get_attachment_image( $img_id, 'woocommerce_thumbnail', false, [ 'alt' => esc_attr( $name_en ) ] );
              }
          }
          $type      = $row['type'];
          $type_ar   = isset( $types[ $type ]['ar'] ) ? $types[ $type ]['ar'] : '';
        ?>
          <article class="ng-addon-card" data-type="<?php echo esc_attr( $type ); ?>">
            <a class="media" href="<?php echo $perm ? esc_url( $perm ) : '#'; ?>" aria-hidden="<?php echo $perm ? 'false' : 'true'; ?>">
              <?php if ( $img_html ) {
                  echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — wp_get_attachment_image returns safe HTML
              } else { ?>
                <span class="ng-r1-ph-label"><?php echo esc_html( $row['sku'] ?: $type_ar ); ?></span>
              <?php } ?>
            </a>
            <div class="body">
              <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <?php if ( $row['sku'] ) : ?>
                  <span class="sku"><?php echo esc_html( strtoupper( $row['sku'] ) ); ?></span>
                <?php endif; ?>
                <span class="ng-addon-type-badge" data-type="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type_ar ); ?></span>
              </div>
              <div class="ar"><?php echo esc_html( $title_ar !== '' ? $title_ar : $row['sku'] ); ?></div>
              <?php if ( $title_en ) : ?>
                <div class="en"><?php echo esc_html( $title_en ); ?></div>
              <?php endif; ?>
              <div class="foot">
                <div class="price"><?php echo $price ? wp_kses_post( $price ) : '&nbsp;'; ?></div>
                <?php if ( $perm ) : ?>
                  <a class="btn btn-sm" href="<?php echo esc_url( $perm ); ?>" style="font-size:11px;padding:5px 9px;">عرض ←</a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
    return ob_get_clean();
}

/* ---------------------------------------------------------------------
 * Meta box — repeater UI on the product edit screen.
 * ------------------------------------------------------------------- */

add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'ng-product-addons',
        'NeoGen — Add-ons & Replacements',
        'ng_product_addons_meta_box',
        'product',
        'normal',
        'default'
    );
} );

function ng_product_addons_meta_box( $post ) {
    wp_nonce_field( 'ng_product_addons_save', 'ng_product_addons_nonce' );
    $rows  = ng_get_addons( $post->ID );
    $types = ng_addons_types();

    if ( empty( $rows ) ) { $rows = [ [ 'sku' => '', 'type' => 'upgrade', 'title' => '' ] ]; }
    ?>
    <p style="margin:0 0 12px;font-size:12px;color:#666;">
      Each row links to another product by SKU. Title is optional — if empty and the SKU resolves to a product, the product's own title is used.
      Empty rows are dropped on save. Leave the whole list empty to hide the section on the PDP.
    </p>
    <table class="widefat striped" id="ng-addons-table" style="max-width:880px;">
      <thead>
        <tr>
          <th style="width:30%;">SKU</th>
          <th style="width:20%;">Type</th>
          <th>Title (Arabic, optional)</th>
          <th style="width:60px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $rows as $i => $row ) : ?>
          <tr>
            <td>
              <input type="text" name="ng_addons[<?php echo (int) $i; ?>][sku]"
                     value="<?php echo esc_attr( $row['sku'] ); ?>"
                     style="width:100%;font-family:monospace;" placeholder="NG-XXX-001">
            </td>
            <td>
              <select name="ng_addons[<?php echo (int) $i; ?>][type]" style="width:100%;">
                <?php foreach ( $types as $key => $label ) : ?>
                  <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $row['type'], $key ); ?>>
                    <?php echo esc_html( $label['en'] ); ?> · <?php echo esc_html( $label['ar'] ); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <input type="text" name="ng_addons[<?php echo (int) $i; ?>][title]"
                     value="<?php echo esc_attr( $row['title'] ); ?>"
                     style="width:100%;direction:rtl;text-align:right;font-family:Tajawal,sans-serif;"
                     placeholder="">
            </td>
            <td>
              <button type="button" class="button ng-addons-remove">×</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="margin:10px 0 0;">
      <button type="button" class="button button-secondary" id="ng-addons-add">+ Add row</button>
    </p>
    <script>
    (function () {
      var table = document.getElementById('ng-addons-table');
      if (!table) return;
      var tbody = table.querySelector('tbody');
      function reindex() {
        var rows = tbody.querySelectorAll('tr');
        Array.prototype.forEach.call(rows, function (tr, i) {
          var fields = tr.querySelectorAll('input,select');
          Array.prototype.forEach.call(fields, function (f) {
            f.name = f.name.replace(/ng_addons\[\d+\]/, 'ng_addons[' + i + ']');
          });
        });
      }
      document.getElementById('ng-addons-add').addEventListener('click', function () {
        var first = tbody.querySelector('tr');
        if (!first) return;
        var clone = first.cloneNode(true);
        Array.prototype.forEach.call(clone.querySelectorAll('input'), function (i) { i.value = ''; });
        Array.prototype.forEach.call(clone.querySelectorAll('select'), function (s) { s.selectedIndex = 0; });
        tbody.appendChild(clone);
        reindex();
      });
      tbody.addEventListener('click', function (e) {
        if (!e.target.classList.contains('ng-addons-remove')) return;
        var rows = tbody.querySelectorAll('tr');
        if (rows.length <= 1) {
          var only = rows[0];
          Array.prototype.forEach.call(only.querySelectorAll('input'), function (i) { i.value = ''; });
        } else {
          e.target.closest('tr').remove();
        }
        reindex();
      });
    })();
    </script>
    <?php
}

add_action( 'save_post_product', function ( $post_id ) {
    if ( ! isset( $_POST['ng_product_addons_nonce'] )
         || ! wp_verify_nonce( $_POST['ng_product_addons_nonce'], 'ng_product_addons_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

    $valid_types = array_keys( ng_addons_types() );
    $rows_raw    = isset( $_POST['ng_addons'] ) && is_array( $_POST['ng_addons'] ) ? wp_unslash( $_POST['ng_addons'] ) : [];

    $clean = [];
    foreach ( $rows_raw as $r ) {
        if ( ! is_array( $r ) ) { continue; }
        $sku   = isset( $r['sku'] )   ? sanitize_text_field( $r['sku'] )   : '';
        $type  = isset( $r['type'] )  ? sanitize_key( $r['type'] )         : '';
        $title = isset( $r['title'] ) ? sanitize_text_field( $r['title'] ) : '';
        if ( ! in_array( $type, $valid_types, true ) ) { $type = 'spare'; }
        $sku   = trim( $sku );
        $title = trim( $title );
        if ( $sku === '' && $title === '' ) { continue; }
        $clean[] = [
            'sku'   => $sku,
            'type'  => $type,
            'title' => $title,
        ];
    }

    if ( empty( $clean ) ) {
        delete_post_meta( $post_id, '_ng_addons' );
    } else {
        update_post_meta( $post_id, '_ng_addons', wp_json_encode( $clean ) );
    }
} );
