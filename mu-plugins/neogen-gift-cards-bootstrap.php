<?php
/**
 * Plugin Name: NeoGen Gift Cards Bootstrap
 * Description: Admin tool that auto-creates one WooCommerce product per brand for every gift-card webp shipped under mu-plugins/neogen-theme-assets/img/gift-cards/. Idempotent (skips by SKU). Creates drafts; the operator publishes after pricing.
 * Version: 1.0.0
 * Author: Fahad Almansour
 *
 * What this plugin owns
 * ---------------------
 * - Admin page Tools → NeoGen Gift Cards · Bootstrap.
 * - On submit, scans `ng_gift_card_asset_map()` (defined in
 *   neogen-gift-cards.php) for every slot whose webp exists on disk
 *   and creates a draft WC product per slot:
 *     - Title:   "<Arabic title> · <English title>"
 *     - Slug:    `<slot>-gift-card`
 *     - SKU:     `gc-<slot>` (used as the idempotency key)
 *     - Type:    simple, virtual = true, manage_stock = false
 *     - Image:   webp sideloaded to wp-content/uploads/ + set as
 *                featured image. Original file in mu-plugins is
 *                left untouched (the runtime matcher in
 *                neogen-gift-cards.php still serves the in-repo
 *                webp; this attachment is for the admin Set Image
 *                picker and any code that reads attachment_id).
 *     - Category: 'Gift Cards' product_cat term; created if missing.
 *     - Meta `_ng_gift_card_brand` = slot key, so the runtime
 *                matcher's per-product override resolves
 *                deterministically without keyword scan.
 *
 * What it does NOT do
 * -------------------
 * - Does not set prices. Operator fills regular_price before
 *   publishing. The product stays in draft until then.
 * - Does not publish. Drafts only.
 * - Does not touch existing products with the same SKU. If
 *   `gc-<slot>` already resolves to a product, that slot is
 *   reported as `skipped` and the existing product is untouched.
 * - Does not delete or update sideloaded attachments on re-run.
 *
 * Rollback
 * --------
 * Bulk-delete the drafts from Products admin, then optionally
 * remove the 'Gift Cards' product_cat term and the sideloaded
 * attachments from the Media Library.
 */

defined('ABSPATH') || exit;

/**
 * Display titles per slot. Slot keys must match
 * ng_gift_card_asset_map() in neogen-gift-cards.php.
 */
function ng_gift_card_bootstrap_titles() {
    return [
        'apple'       => ['en' => 'Apple Gift Card',           'ar' => 'بطاقة آبل'],
        'google-play' => ['en' => 'Google Play Gift Card',     'ar' => 'بطاقة قوقل بلاي'],
        'playstation' => ['en' => 'PlayStation Gift Card',     'ar' => 'بطاقة بلايستيشن'],
        'steam'       => ['en' => 'Steam Gift Card',           'ar' => 'بطاقة ستيم'],
        'xbox'        => ['en' => 'Xbox Gift Card',            'ar' => 'بطاقة إكس بوكس'],
        'amazon'      => ['en' => 'Amazon Gift Card',          'ar' => 'بطاقة أمازون'],
        'kaspersky'   => ['en' => 'Kaspersky Subscription',    'ar' => 'اشتراك كاسبرسكي'],
        'adobe'       => ['en' => 'Adobe Gift Card',           'ar' => 'بطاقة أدوبي'],
        'office'      => ['en' => 'Microsoft Office Key',      'ar' => 'مفتاح أوفيس'],
        'windows'     => ['en' => 'Microsoft Windows Key',     'ar' => 'مفتاح ويندوز'],
        'youtube'     => ['en' => 'YouTube Premium Gift Card', 'ar' => 'بطاقة يوتيوب بريميوم'],
    ];
}

/**
 * Idempotently ensure the 'Gift Cards' product_cat term exists.
 * Returns the term object on success, null on failure.
 */
function ng_gift_card_bootstrap_ensure_category() {
    if ( ! taxonomy_exists('product_cat') ) return null;

    $term = get_term_by('slug', 'gift-cards', 'product_cat');
    if ( $term && ! is_wp_error($term) ) return $term;

    $result = wp_insert_term('بطاقات هدايا', 'product_cat', [
        'slug'        => 'gift-cards',
        'description' => 'بطاقات هدايا رقمية · شحن فوري بالبريد الإلكتروني · ضمان رسمي.',
    ]);
    if ( is_wp_error($result) ) return null;
    return get_term((int) $result['term_id'], 'product_cat');
}

/**
 * Sideload a webp from the in-repo gift-cards directory into
 * wp-content/uploads/ and register it as a wp_attachment.
 *
 * @return int|WP_Error attachment_id on success.
 */
function ng_gift_card_bootstrap_sideload($slot, $src_path, $title) {
    if ( ! file_exists($src_path) ) {
        return new WP_Error('no_file', 'Source webp missing: ' . $src_path);
    }

    // Cache the attach_id on a sitewide option so re-runs are O(1).
    $cache_key = 'ng_gc_attach_id_' . $slot;
    $cached    = (int) get_option($cache_key, 0);
    if ( $cached > 0 && get_post_status($cached) === 'inherit' ) {
        return $cached;
    }

    $bytes = @file_get_contents($src_path);
    if ( $bytes === false ) {
        return new WP_Error('read_failed', 'Could not read ' . $src_path);
    }

    $filename = 'gift-card-' . sanitize_file_name(basename($src_path));
    $upload   = wp_upload_bits($filename, null, $bytes);
    if ( ! empty($upload['error']) ) {
        return new WP_Error('upload_failed', $upload['error']);
    }

    $filetype   = wp_check_filetype($upload['file']);
    $attachment = [
        'guid'           => $upload['url'],
        'post_mime_type' => $filetype['type'] ?: 'image/webp',
        'post_title'     => $title,
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attach_id = wp_insert_attachment($attachment, $upload['file']);
    if ( is_wp_error($attach_id) ) return $attach_id;

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $metadata);

    update_option($cache_key, (int) $attach_id, false);
    return (int) $attach_id;
}

/**
 * Run the bootstrap. Returns a structured report keyed
 * created / skipped / errors.
 */
function ng_gift_card_bootstrap_run() {
    if ( ! class_exists('WC_Product_Simple') || ! function_exists('wc_get_product_id_by_sku') ) {
        return ['error' => 'WooCommerce is not active. Activate it before running this tool.'];
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $cat = ng_gift_card_bootstrap_ensure_category();
    if ( ! $cat ) {
        return ['error' => 'Could not create or find the Gift Cards product_cat term.'];
    }

    $report = ['created' => [], 'skipped' => [], 'errors' => []];

    $titles = ng_gift_card_bootstrap_titles();
    $map    = function_exists('ng_gift_card_asset_map') ? ng_gift_card_asset_map() : [];

    foreach ( $titles as $slot => $names ) {
        $sku = 'gc-' . $slot;

        $existing_id = wc_get_product_id_by_sku($sku);
        if ( $existing_id > 0 ) {
            $report['skipped'][] = compact('slot', 'sku') + [
                'product_id' => (int) $existing_id,
                'reason'     => 'sku exists',
            ];
            continue;
        }

        if ( empty($map[$slot]) ) {
            $report['errors'][] = compact('slot', 'sku') + ['reason' => 'slot not in asset map'];
            continue;
        }
        $file = function_exists('ng_gift_card_existing_file')
            ? ng_gift_card_existing_file($map[$slot])
            : '';
        if ( $file === '' ) {
            $report['errors'][] = compact('slot', 'sku') + ['reason' => 'no webp on disk'];
            continue;
        }

        $src_path  = ng_gift_card_asset_dir() . '/' . $file;
        $attach_id = ng_gift_card_bootstrap_sideload($slot, $src_path, $names['en']);
        if ( is_wp_error($attach_id) ) {
            $report['errors'][] = compact('slot', 'sku') + ['reason' => $attach_id->get_error_message()];
            continue;
        }

        try {
            $product = new WC_Product_Simple();
            $product->set_name( $names['ar'] . ' · ' . $names['en'] );
            $product->set_slug( $slot . '-gift-card' );
            $product->set_status('draft');
            $product->set_sku($sku);
            $product->set_virtual(true);
            $product->set_manage_stock(false);
            $product->set_stock_status('instock');
            $product->set_short_description( sprintf(
                'بطاقة هدايا %s · يتم إرسال الكود فور إتمام الطلب.',
                $names['ar']
            ) );
            $product->set_description( sprintf(
                "بطاقة %s الرقمية · شحن فوري على البريد الإلكتروني · ضمان رسمي.\n\n%s digital gift card · code delivered to your inbox right after checkout · official manufacturer warranty.",
                $names['ar'],
                $names['en']
            ) );
            $product->set_image_id( (int) $attach_id );
            $product->set_category_ids([ (int) $cat->term_id ]);
            $product_id = (int) $product->save();

            update_post_meta($product_id, '_ng_gift_card_brand', $slot);

            // Seed default region list for slots whose store splits by
            // country. Apple is the canonical case: GCC + US + UK.
            // Idempotent — never overwrites if meta already exists.
            $region_seeds = [
                'apple' => ['SA','AE','BH','OM','QA','KW','US','GB'],
            ];
            if (isset($region_seeds[$slot])
                && get_post_meta($product_id, '_ng_gc_regions', true) === '') {
                update_post_meta($product_id, '_ng_gc_regions', $region_seeds[$slot]);
            }

            $report['created'][] = compact('slot', 'sku') + [
                'product_id' => $product_id,
                'attach_id'  => (int) $attach_id,
            ];
        } catch ( Throwable $e ) {
            $report['errors'][] = compact('slot', 'sku') + ['reason' => $e->getMessage()];
        }
    }

    return $report;
}

/**
 * Tools → NeoGen Gift Cards · Bootstrap admin page.
 */
add_action('admin_menu', function () {
    add_management_page(
        'NeoGen Gift Cards · Bootstrap',
        'NeoGen Gift Cards',
        'manage_woocommerce',
        'neogen-gift-cards-bootstrap',
        'ng_gift_card_bootstrap_render'
    );
});

function ng_gift_card_bootstrap_render() {
    if ( ! current_user_can('manage_woocommerce') ) wp_die('forbidden');

    $report = null;
    if ( isset($_POST['ng_gc_bootstrap_nonce'])
        && wp_verify_nonce( $_POST['ng_gc_bootstrap_nonce'], 'ng_gc_bootstrap_run' ) ) {
        $report = ng_gift_card_bootstrap_run();
    }

    $titles = ng_gift_card_bootstrap_titles();
    $map    = function_exists('ng_gift_card_asset_map') ? ng_gift_card_asset_map() : [];

    ?>
    <div class="wrap">
      <h1>NeoGen Gift Cards · Bootstrap</h1>
      <p>One-click tool that auto-creates a draft WooCommerce product for every brand whose webp ships under <code>mu-plugins/neogen-theme-assets/img/gift-cards/</code>. Each product is virtual, manage_stock = false, status = <strong>draft</strong>, with the matching webp sideloaded to <code>wp-content/uploads/</code> and set as featured image. The runtime matcher in <code>neogen-gift-cards.php</code> still serves the in-repo art on the storefront; this attachment is for the admin <em>Set Image</em> picker and any code that reads <code>attachment_id</code>.</p>
      <p><strong>Idempotency:</strong> products are keyed by SKU <code>gc-&lt;slot&gt;</code>. Re-running the tool is safe — existing products are skipped, not modified.</p>

      <?php if ( is_array($report) && isset($report['error']) ) : ?>
        <div class="notice notice-error is-dismissible"><p><strong>Error:</strong> <?php echo esc_html($report['error']); ?></p></div>
      <?php elseif ( is_array($report) ) : ?>
        <?php
          $c = count($report['created']);
          $s = count($report['skipped']);
          $e = count($report['errors']);
        ?>
        <div class="notice notice-<?php echo $e > 0 ? 'warning' : 'success'; ?> is-dismissible">
          <p><strong>Run complete:</strong> <?php echo (int) $c; ?> created · <?php echo (int) $s; ?> skipped · <?php echo (int) $e; ?> errors.</p>
        </div>

        <?php if ( $c > 0 ) : ?>
        <h2 style="margin-top:1.5em;">Created</h2>
        <table class="widefat striped" style="max-width:760px;">
          <thead><tr><th>Slot</th><th>SKU</th><th>Product</th><th>Attachment</th></tr></thead>
          <tbody>
          <?php foreach ( $report['created'] as $row ) : ?>
            <tr>
              <td><code><?php echo esc_html($row['slot']); ?></code></td>
              <td><code><?php echo esc_html($row['sku']); ?></code></td>
              <td><a href="<?php echo esc_url( admin_url('post.php?post=' . $row['product_id'] . '&action=edit') ); ?>" target="_blank">#<?php echo (int) $row['product_id']; ?> ·  edit</a></td>
              <td><a href="<?php echo esc_url( admin_url('upload.php?item=' . $row['attach_id']) ); ?>" target="_blank">#<?php echo (int) $row['attach_id']; ?></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>

        <?php if ( $s > 0 ) : ?>
        <h2 style="margin-top:1.5em;">Skipped</h2>
        <table class="widefat striped" style="max-width:760px;">
          <thead><tr><th>Slot</th><th>SKU</th><th>Existing product</th><th>Reason</th></tr></thead>
          <tbody>
          <?php foreach ( $report['skipped'] as $row ) : ?>
            <tr>
              <td><code><?php echo esc_html($row['slot']); ?></code></td>
              <td><code><?php echo esc_html($row['sku']); ?></code></td>
              <td><a href="<?php echo esc_url( admin_url('post.php?post=' . $row['product_id'] . '&action=edit') ); ?>" target="_blank">#<?php echo (int) $row['product_id']; ?></a></td>
              <td><?php echo esc_html($row['reason']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>

        <?php if ( $e > 0 ) : ?>
        <h2 style="margin-top:1.5em;">Errors</h2>
        <table class="widefat striped" style="max-width:760px;">
          <thead><tr><th>Slot</th><th>SKU</th><th>Reason</th></tr></thead>
          <tbody>
          <?php foreach ( $report['errors'] as $row ) : ?>
            <tr>
              <td><code><?php echo esc_html($row['slot']); ?></code></td>
              <td><code><?php echo esc_html($row['sku']); ?></code></td>
              <td><?php echo esc_html($row['reason']); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      <?php endif; ?>

      <h2 style="margin-top:1.5em;">Slots that will run</h2>
      <table class="widefat striped" style="max-width:760px;">
        <thead><tr><th>Slot</th><th>Title (AR · EN)</th><th>Webp</th><th>SKU</th></tr></thead>
        <tbody>
        <?php foreach ( $titles as $slot => $names ) :
          $file = ! empty($map[$slot]) && function_exists('ng_gift_card_existing_file')
            ? ng_gift_card_existing_file($map[$slot])
            : '';
          $sku  = 'gc-' . $slot;
        ?>
          <tr>
            <td><code><?php echo esc_html($slot); ?></code></td>
            <td><?php echo esc_html($names['ar'] . ' · ' . $names['en']); ?></td>
            <td><?php echo $file !== '' ? '<code>' . esc_html($file) . '</code>' : '<em>missing</em>'; ?></td>
            <td><code><?php echo esc_html($sku); ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <form method="post" style="margin-top:2em;">
        <?php wp_nonce_field('ng_gc_bootstrap_run', 'ng_gc_bootstrap_nonce'); ?>
        <p>
          <button type="submit" class="button button-primary">Create draft products</button>
          <span style="margin-inline-start:.8em;color:#64748B;">Idempotent · safe to re-run · creates drafts only.</span>
        </p>
      </form>

      <h2 style="margin-top:2em;">After running</h2>
      <ol style="max-width:760px;">
        <li>Open each draft product, set the <strong>regular price</strong> (and any variations / denominations).</li>
        <li>(Optional) Set <strong>tax class</strong> if the digital VAT rule differs from the storefront default.</li>
        <li>Verify the featured image renders. The runtime matcher in <code>neogen-gift-cards.php</code> will continue to serve the in-repo art on the storefront thanks to the <code>_ng_gift_card_brand</code> override meta this tool sets.</li>
        <li>Click <strong>Publish</strong> when ready.</li>
      </ol>

      <h2 style="margin-top:2em;">Rollback</h2>
      <p>Trash the drafts from Products admin (bulk action). Optionally delete the sideloaded attachments from Media Library and remove the <code>gift-cards</code> product_cat term. The webps in the repo are untouched and the runtime matcher remains active.</p>
    </div>
    <?php
}
