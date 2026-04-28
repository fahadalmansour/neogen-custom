<?php
/**
 * Plugin Name: NeoGen Catalog Import
 * Description: One-click admin tool that imports the bundled AliExpress dropshipping catalog (100 SKUs, prepped CSV at mu-plugins/neogen-theme-assets/data/woocommerce_import_aliexpress.csv) into WooCommerce. Idempotent (skips by SKU). Sideloads featured images from the CSV's URL column. Maps category names to existing live terms.
 * Version: 1.0.0
 * Author: Fahad Almansour
 *
 * Why this plugin exists
 * ----------------------
 * WC's built-in Products → Import requires the operator to upload the
 * CSV manually each time. That works once, but the source CSV in this
 * project lives in the repo (deployed via NeoGen Deploy), so it makes
 * sense to wire a Tools page that just reads it from disk and runs
 * the import in batches. Re-running picks up where it left off.
 *
 * What it does
 * ------------
 * 1. Reads `NG_CATALOG_CSV_PATH`.
 * 2. For each row not already imported (matched by SKU):
 *    - Creates a WC_Product_Simple
 *    - Sideloads the URL in the `Images` column → wp_attachment →
 *      sets as featured image
 *    - Maps the row's `Categories` (pipe-separated, exact display
 *      name) to existing product_cat terms; creates missing terms
 *      as a fallback
 *    - Sets price, stock_status, descriptions, tax status, visibility,
 *      published/draft per the CSV
 * 3. Persists a cursor on the option `ng_catalog_import_cursor` so
 *    repeated clicks resume — useful when a single PHP request hits
 *    a host time limit.
 *
 * Idempotency: a row whose SKU already resolves to a WC product is
 * reported as `skipped` and never modified. Safe to re-run.
 *
 * Rollback: trash the imported products from Products admin (bulk
 * action). Optionally delete the sideloaded attachments from Media
 * Library. No DB schema changes are made.
 */

defined('ABSPATH') || exit;

if ( ! defined('NG_CATALOG_CSV_PATH') ) {
    define(
        'NG_CATALOG_CSV_PATH',
        __DIR__ . '/neogen-theme-assets/data/woocommerce_import_aliexpress.csv'
    );
}

const NG_CATALOG_CURSOR_OPTION = 'ng_catalog_import_cursor';
const NG_CATALOG_DEFAULT_BATCH = 25;

/* =====================================================================
 * CSV reader
 * ===================================================================== */

function ng_catalog_read_csv() {
    if ( ! file_exists(NG_CATALOG_CSV_PATH) ) return [];
    $rows = [];
    $f = fopen(NG_CATALOG_CSV_PATH, 'r');
    if ( ! $f ) return [];
    $hdr = fgetcsv($f);
    if ( ! $hdr ) { fclose($f); return []; }
    while ( ($r = fgetcsv($f)) !== false ) {
        if ( count($r) !== count($hdr) ) {
            // Pad / trim to match header length.
            $r = array_pad(array_slice($r, 0, count($hdr)), count($hdr), '');
        }
        $rows[] = array_combine($hdr, $r);
    }
    fclose($f);
    return $rows;
}

/* =====================================================================
 * Import runner
 * ===================================================================== */

function ng_catalog_import_run( $batch_size = NG_CATALOG_DEFAULT_BATCH ) {
    if ( ! class_exists('WC_Product_Simple')
        || ! function_exists('wc_get_product_id_by_sku') ) {
        return ['error' => 'WooCommerce is not active.'];
    }

    @set_time_limit(0);
    @ignore_user_abort(true);
    @ini_set('memory_limit', '512M');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $rows = ng_catalog_read_csv();
    if ( empty($rows) ) {
        return ['error' => 'CSV missing or empty: ' . NG_CATALOG_CSV_PATH];
    }

    $cursor = (int) get_option(NG_CATALOG_CURSOR_OPTION, 0);
    $end = min( $cursor + (int) $batch_size, count($rows) );
    $report = [
        'created'    => [],
        'skipped'    => [],
        'errors'     => [],
        'cursor'     => $cursor,
        'next'       => $cursor,
        'total_rows' => count($rows),
    ];

    for ( $i = $cursor; $i < $end; $i++ ) {
        $row = $rows[$i];
        $sku = trim( (string) ($row['SKU'] ?? '') );
        if ( $sku === '' ) {
            $report['errors'][] = ['index' => $i, 'reason' => 'empty SKU'];
            continue;
        }

        $existing_id = wc_get_product_id_by_sku($sku);
        if ( $existing_id > 0 ) {
            $report['skipped'][] = [
                'sku'        => $sku,
                'product_id' => (int) $existing_id,
                'reason'     => 'sku exists',
            ];
            continue;
        }

        try {
            $product = new WC_Product_Simple();
            $product->set_name( trim( (string) ($row['Name'] ?? '') ) );
            $product->set_sku($sku);
            $product->set_status( $row['Published'] === '1' ? 'publish' : 'draft' );
            $vis = trim( (string) ($row['Visibility in catalog'] ?? '') );
            if ( $vis !== '' && $vis !== 'hidden' ) $product->set_catalog_visibility($vis);
            elseif ( $vis === 'hidden' )            $product->set_catalog_visibility('hidden');

            $product->set_short_description( (string) ($row['Short description'] ?? '') );
            $product->set_description( (string) ($row['Description'] ?? '') );
            $product->set_tax_status( $row['Tax status'] ?: 'taxable' );
            $product->set_manage_stock( false );
            $product->set_stock_status( $row['In stock?'] === '1' ? 'instock' : 'outofstock' );

            $price = trim( (string) ($row['Regular price'] ?? '') );
            if ( $price !== '' ) $product->set_regular_price($price);

            $product->set_virtual(false);
            $product->set_sold_individually( ! empty($row['Sold individually?']) && $row['Sold individually?'] === '1' );
            $product->set_featured( ! empty($row['Is featured?']) && $row['Is featured?'] === '1' );
            $product->set_backorders( $row['Backorders allowed?'] === '1' ? 'yes' : 'notify' );

            // Categories: pipe-separated display names, exact match → existing term;
            // create only if missing (we shouldn't need to — CSV is pre-mapped).
            $cat_ids = [];
            $cats = array_filter( array_map( 'trim',
                explode( '|', (string) ($row['Categories'] ?? '') ) ) );
            foreach ( $cats as $cat_name ) {
                $term = get_term_by('name', $cat_name, 'product_cat');
                if ( $term && ! is_wp_error($term) ) {
                    $cat_ids[] = (int) $term->term_id;
                } else {
                    $created = wp_insert_term($cat_name, 'product_cat');
                    if ( ! is_wp_error($created) ) $cat_ids[] = (int) $created['term_id'];
                }
            }
            if ( $cat_ids ) $product->set_category_ids($cat_ids);

            $product_id = (int) $product->save();

            // Sideload featured image AFTER save (needs a parent post id).
            $img_url = trim( (string) ($row['Images'] ?? '') );
            if ( $img_url !== '' && filter_var($img_url, FILTER_VALIDATE_URL) ) {
                $attach_id = media_sideload_image($img_url, $product_id, $product->get_name(), 'id');
                if ( ! is_wp_error($attach_id) ) {
                    set_post_thumbnail($product_id, (int) $attach_id);
                }
            }

            // Stamp source for future reconciliation.
            update_post_meta($product_id, '_ng_imported_via', 'neogen-catalog-import');
            update_post_meta($product_id, '_ng_import_csv_row', $i);

            $report['created'][] = [
                'sku'        => $sku,
                'product_id' => $product_id,
                'name'       => $row['Name'] ?? '',
            ];
        } catch ( Throwable $e ) {
            $report['errors'][] = ['sku' => $sku, 'reason' => $e->getMessage()];
        }
    }

    update_option(NG_CATALOG_CURSOR_OPTION, $end, false);
    $report['next'] = $end;
    return $report;
}

/* =====================================================================
 * Pre-flight: count how many SKUs in the CSV already exist
 * ===================================================================== */

function ng_catalog_preflight_counts() {
    if ( ! function_exists('wc_get_product_id_by_sku') ) return null;
    $rows = ng_catalog_read_csv();
    $existing = $missing = 0;
    foreach ( $rows as $r ) {
        $sku = trim( (string) ($r['SKU'] ?? '') );
        if ( $sku === '' ) continue;
        if ( wc_get_product_id_by_sku($sku) > 0 ) $existing++;
        else                                       $missing++;
    }
    return [
        'rows'     => count($rows),
        'existing' => $existing,
        'missing'  => $missing,
    ];
}

/* =====================================================================
 * Admin page
 * ===================================================================== */

add_action('admin_menu', function () {
    add_management_page(
        'NeoGen Catalog Import',
        'NeoGen Catalog Import',
        'manage_woocommerce',
        'neogen-catalog-import',
        'ng_catalog_import_render'
    );
});

function ng_catalog_import_render() {
    if ( ! current_user_can('manage_woocommerce') ) wp_die('forbidden');

    $report = null;

    // Reset cursor → start over from row 0
    if ( isset($_POST['ng_catalog_reset'])
        && check_admin_referer('ng_catalog_run', 'ng_catalog_nonce') ) {
        delete_option(NG_CATALOG_CURSOR_OPTION);
        echo '<div class="notice notice-success is-dismissible"><p>Cursor reset to 0.</p></div>';
    }

    // Run a batch
    if ( isset($_POST['ng_catalog_run'])
        && check_admin_referer('ng_catalog_run', 'ng_catalog_nonce') ) {
        $batch = max(1, min(100, (int) ($_POST['batch_size'] ?? NG_CATALOG_DEFAULT_BATCH)));
        $report = ng_catalog_import_run($batch);
    }

    $cursor   = (int) get_option(NG_CATALOG_CURSOR_OPTION, 0);
    $rows     = ng_catalog_read_csv();
    $total    = count($rows);
    $remaining = max(0, $total - $cursor);
    $preflight = ng_catalog_preflight_counts();

    ?>
    <div class="wrap">
      <h1>NeoGen Catalog Import</h1>
      <p>One-click import of the AliExpress dropshipping catalog. CSV bundled at <code><?php echo esc_html(NG_CATALOG_CSV_PATH); ?></code>. Idempotent — re-running skips any SKU that already resolves to a WC product, so it's safe to click multiple times.</p>

      <h2>Status</h2>
      <table class="widefat striped" style="max-width:760px;">
        <tbody>
          <tr><th>CSV path</th><td><code><?php echo esc_html(NG_CATALOG_CSV_PATH); ?></code></td></tr>
          <tr><th>CSV present</th><td><?php echo file_exists(NG_CATALOG_CSV_PATH)
              ? '<strong style="color:#22C55E;">yes — ' . (int) filesize(NG_CATALOG_CSV_PATH) . ' bytes</strong>'
              : '<strong style="color:#EF4444;">missing</strong>'; ?></td></tr>
          <tr><th>Total rows</th><td><?php echo (int) $total; ?></td></tr>
          <tr><th>Cursor (next row to process)</th><td><?php echo (int) $cursor; ?> / <?php echo (int) $total; ?> · remaining = <strong><?php echo (int) $remaining; ?></strong></td></tr>
          <?php if ( $preflight ) : ?>
          <tr><th>Pre-flight: SKUs already in catalog</th><td><strong><?php echo (int) $preflight['existing']; ?></strong> will be skipped · <strong><?php echo (int) $preflight['missing']; ?></strong> will be newly created</td></tr>
          <?php endif; ?>
          <tr><th>WooCommerce</th><td><?php echo class_exists('WooCommerce') ? '<strong style="color:#22C55E;">active</strong>' : '<strong style="color:#EF4444;">NOT active</strong>'; ?></td></tr>
        </tbody>
      </table>

      <?php if ( is_array($report) ) : ?>
        <?php
          $c = count($report['created']  ?? []);
          $s = count($report['skipped']  ?? []);
          $e = count($report['errors']   ?? []);
        ?>
        <div class="notice notice-<?php echo $e > 0 ? 'warning' : 'success'; ?> is-dismissible" style="margin-top:1.5em;">
          <p><strong>Batch run complete:</strong>
            <?php echo (int) $c; ?> created ·
            <?php echo (int) $s; ?> skipped ·
            <?php echo (int) $e; ?> errors ·
            advanced from <?php echo (int) ($report['cursor'] ?? 0); ?> → <?php echo (int) ($report['next'] ?? 0); ?> of <?php echo (int) ($report['total_rows'] ?? 0); ?>
          </p>
        </div>

        <?php if ( ! empty($report['created']) ) : ?>
        <h3>Created (<?php echo (int) $c; ?>)</h3>
        <table class="widefat striped" style="max-width:1080px;">
          <thead><tr><th>SKU</th><th>Product</th><th>Name</th></tr></thead>
          <tbody>
          <?php foreach ( $report['created'] as $r ) : ?>
            <tr>
              <td><code><?php echo esc_html($r['sku']); ?></code></td>
              <td><a href="<?php echo esc_url( admin_url('post.php?post=' . $r['product_id'] . '&action=edit') ); ?>" target="_blank">#<?php echo (int) $r['product_id']; ?> · edit</a></td>
              <td><?php echo esc_html($r['name']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>

        <?php if ( ! empty($report['skipped']) ) : ?>
        <h3>Skipped (<?php echo (int) $s; ?>)</h3>
        <table class="widefat striped" style="max-width:1080px;">
          <thead><tr><th>SKU</th><th>Existing product</th><th>Reason</th></tr></thead>
          <tbody>
          <?php foreach ( $report['skipped'] as $r ) : ?>
            <tr>
              <td><code><?php echo esc_html($r['sku']); ?></code></td>
              <td><a href="<?php echo esc_url( admin_url('post.php?post=' . $r['product_id'] . '&action=edit') ); ?>" target="_blank">#<?php echo (int) $r['product_id']; ?></a></td>
              <td><?php echo esc_html($r['reason']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>

        <?php if ( ! empty($report['errors']) ) : ?>
        <h3 style="color:#c14a1a;">Errors (<?php echo (int) $e; ?>)</h3>
        <table class="widefat striped" style="max-width:1080px;">
          <thead><tr><th>SKU / index</th><th>Reason</th></tr></thead>
          <tbody>
          <?php foreach ( $report['errors'] as $r ) : ?>
            <tr>
              <td><code><?php echo esc_html($r['sku'] ?? ('row ' . ($r['index'] ?? '?'))); ?></code></td>
              <td><?php echo esc_html($r['reason']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      <?php endif; ?>

      <h2 style="margin-top:2em;">Run</h2>
      <form method="post" onsubmit="return confirm('Run a batch now? This will create products + sideload images. Re-runnable; safe to repeat.');">
        <?php wp_nonce_field('ng_catalog_run', 'ng_catalog_nonce'); ?>
        <p>
          <label>Batch size: <input type="number" name="batch_size" value="<?php echo (int) NG_CATALOG_DEFAULT_BATCH; ?>" min="1" max="100" style="width:80px;"></label>
          <span style="color:#64748B;margin-inline-start:.6em;">A whole 100-row run can take several minutes (image sideload). Smaller batches are safer on hosts with PHP timeouts.</span>
        </p>
        <p>
          <button type="submit" name="ng_catalog_run" value="1" class="button button-primary">Run next batch</button>
          <button type="submit" name="ng_catalog_reset" value="1" class="button" onclick="return confirm('Reset cursor to row 0? Idempotency is by SKU so this only re-iterates the rows; existing products are still skipped.');" style="margin-inline-start:1em;">Reset cursor</button>
        </p>
      </form>

      <h2 style="margin-top:2em;">Notes</h2>
      <ol style="max-width:760px;">
        <li>Re-running is safe: SKU is the dedupe key. Existing products are reported under <em>Skipped</em>, never modified.</li>
        <li>Each created product carries post-meta <code>_ng_imported_via=neogen-catalog-import</code> + <code>_ng_import_csv_row=N</code> so you can find this batch later.</li>
        <li>Image sideload pulls from the URL in the CSV's <code>Images</code> column. Hosts other than alicdn (Amazon CDN, e-commerce mirrors) work too — WC fetches and caches locally.</li>
        <li>Categories are pipe-separated bilingual display names (matched to live <code>product_cat</code> terms). The CSV is pre-mapped, so no new terms should be created during import; if any are, that's a sign the live category was renamed since the CSV was prepped.</li>
        <li>Rollback: bulk-trash imported products from <em>Products</em>; optionally delete sideloaded attachments from <em>Media</em>.</li>
      </ol>
    </div>
    <?php
}
