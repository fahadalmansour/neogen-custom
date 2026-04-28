<?php
/**
 * Plugin Name: NeoGen Launch Cleanup
 * Description: Admin tool to clean up demo content + duplicate products + duplicate page stubs before public launch. Trashes Hello-World posts, WC sample products, the duplicate page stubs left over from theme template imports, and 6 duplicate-product pairs from the 2026-04-28 audit. Surfaces a separate informational list for products with shared images that need manual re-imaging. Idempotent — re-running skips already-trashed items.
 * Version: 1.1.0
 * Author: Fahad Almansour
 *
 * Why this exists
 * ---------------
 * The 2026-04-28 launch-readiness audit (see chat) flagged ~16 items
 * that need to disappear before public traffic:
 *   - 3 Hello-World placeholder posts (ids 6440, 6457, 6458)
 *   - 4 WC sample-import demo products (Beanie / Belt / T-Shirt / Hoodie)
 *   - 9 duplicate page stubs (cart-2 / shop-2 / home-3 etc.)
 *   - 1 stale Custom CSS page (id 6082)
 *   - product_cat slug rename: gaming-2 → gaming (if free)
 *
 * Doing this by hand in WP admin is fiddly (Posts + Products + Pages
 * + Categories live in different screens, easy to miss one). This
 * tool batches them with safety checks: every target is matched on
 * BOTH id AND title/slug — if the title at the live id no longer
 * matches expectations, that row is reported "mismatched" and left
 * untouched, never silently trashed.
 *
 * Trashing is reversible from WP admin → Trash → Restore for 30
 * days. Permanent deletion is NOT performed by this tool.
 */

defined('ABSPATH') || exit;

/* =====================================================================
 * Targets
 * ===================================================================== */

function ng_launch_cleanup_targets() {
    return [
        'posts' => [
            'label'         => 'Hello-World placeholder posts',
            'sub'           => 'Public-indexed placeholder posts left over from a WP demo. SEO red flag.',
            'expected_type' => 'post',
            'items'         => [
                ['id' => 6440, 'expect_title_starts' => 'Hello World'],
                ['id' => 6457, 'expect_title_starts' => 'Hello World'],
                ['id' => 6458, 'expect_title_starts' => 'Hello World'],
            ],
        ],
        'demo_products' => [
            'label'         => 'WooCommerce sample-import demo products',
            'sub'           => 'Beanie / Belt / T-Shirt / Hoodie — placeholder products from the WC importer.',
            'expected_type' => 'product',
            'items'         => [
                ['id' => 6428, 'expect_title_starts' => 'Hoodie'],
                ['id' => 6429, 'expect_title_starts' => 'T-Shirt'],
                ['id' => 6431, 'expect_title_starts' => 'Belt'],
                ['id' => 6433, 'expect_title_starts' => 'Beanie'],
            ],
        ],
        'pages' => [
            'label'         => 'Duplicate page stubs from theme template imports',
            'sub'           => 'Stub pages with -2 / -3 slug suffixes that duplicate the canonical low-id page (which is preserved).',
            'expected_type' => 'page',
            'items'         => [
                ['id' => 6082, 'expect_slug' => 'custom-css'],
                ['id' => 6286, 'expect_slug' => 'terms-conditions-2'],
                ['id' => 6438, 'expect_slug' => 'checkout-2'],
                ['id' => 6445, 'expect_slug' => 'my-account-2'],
                ['id' => 6447, 'expect_slug' => 'cart-2'],
                ['id' => 6459, 'expect_slug' => 'terms-and-conditions'],
                ['id' => 6461, 'expect_slug' => 'refund-and-returns-policy'],
                ['id' => 6465, 'expect_slug' => 'shop-2'],
                ['id' => 6467, 'expect_slug' => 'home-3'],
            ],
        ],
        'dup_products' => [
            'label'         => 'Duplicate products (same product, two SKU schemes)',
            'sub'           => 'Audit on 2026-04-28 found 6 product pairs with byte-identical featured images and matching names. The newer SKU scheme (SH-/NT-/GM-XXX, higher IDs) appears to be a second-pass import that re-created products that already existed under the older NG-XXX scheme. Default: trash the duplicate (newer-SKU) row, keep the canonical (older-SKU) row. Untick any row you want to preserve instead.',
            'expected_type' => 'product',
            'items'         => [
                ['id' => 3228, 'expect_title_starts' => 'Elgato Stream Deck',  'note' => 'kept: id=3051 NG-MKR-005'],
                ['id' => 3218, 'expect_title_starts' => 'SteelSeries Arctis',  'note' => 'kept: id=3089 NG-GAM-006'],
                ['id' => 3164, 'expect_title_starts' => 'SwitchBot Curtain',   'note' => 'kept: id=3063 NG-SH-007'],
                ['id' => 3176, 'expect_title_starts' => 'Ubiquiti USW-Pro',    'note' => 'kept: id=3061 NG-ENT-010'],
                ['id' => 3173, 'expect_title_starts' => 'MikroTik CRS326',     'note' => 'kept: id=3058 NG-ENT-007'],
                ['id' => 3172, 'expect_title_starts' => 'MikroTik CRS305',     'note' => 'kept: id=3075 NG-NET-004'],
            ],
        ],
    ];
}

/**
 * Different products that mistakenly share an image. Surfaced as
 * a flagged list (NOT auto-trashed) — owner uploads a unique image
 * per row via the regular Products → Edit screen.
 */
function ng_launch_cleanup_shared_image_groups() {
    return [
        ['label' => '3-way Aqara group',           'ids' => [3166, 3144, 3042], 'note' => 'Hub M3 / Smart Lock U200 / Door Lock A100 — different devices, same packaging photo'],
        ['label' => 'Synology NAS (DS925+ vs DS225+)', 'ids' => [3182, 3181], 'note' => 'different bay counts, same photo'],
        ['label' => 'Ubiquiti UDM-Pro vs Cloud Gateway Ultra', 'ids' => [3083, 3039], 'note' => 'completely different routers, same photo'],
        ['label' => 'UniFi 6 Lite vs Long-Range', 'ids' => [3076, 3040], 'note' => 'two different APs, same photo'],
        ['label' => 'Razer mouse vs keyboard',     'ids' => [3091, 3087], 'note' => 'Basilisk V3 X (mouse) vs BlackWidow V4 75% (keyboard) — clearly wrong'],
        ['label' => 'Raspberry Pi 5 kit vs board', 'ids' => [3048, 3047], 'note' => 'Ultimate Starter Kit vs 8GB board — kit has more parts'],
        ['label' => 'Netgate 2100 MAX vs 4200 MAX', 'ids' => [3195, 3194], 'note' => 'different model tiers, same photo'],
    ];
}

/* =====================================================================
 * Probe — describe the live state of one target without changing it
 * ===================================================================== */

function ng_launch_cleanup_probe( $item, $expected_type ) {
    $post = get_post( (int) $item['id'] );
    if ( ! $post ) {
        return ['state' => 'missing', 'title' => '', 'slug' => '', 'status' => 'missing'];
    }
    if ( $post->post_type !== $expected_type ) {
        return ['state' => 'wrong-type', 'title' => $post->post_title, 'slug' => $post->post_name, 'status' => $post->post_status . ' (type=' . $post->post_type . ')'];
    }
    if ( isset($item['expect_title_starts'])
        && stripos( $post->post_title, $item['expect_title_starts'] ) !== 0 ) {
        return ['state' => 'mismatch', 'title' => $post->post_title, 'slug' => $post->post_name, 'status' => $post->post_status];
    }
    if ( isset($item['expect_slug'])
        && $post->post_name !== $item['expect_slug'] ) {
        return ['state' => 'mismatch', 'title' => $post->post_title, 'slug' => $post->post_name, 'status' => $post->post_status];
    }
    if ( $post->post_status === 'trash' ) {
        return ['state' => 'already-trashed', 'title' => $post->post_title, 'slug' => $post->post_name, 'status' => 'trash'];
    }
    return ['state' => 'ready', 'title' => $post->post_title, 'slug' => $post->post_name, 'status' => $post->post_status];
}

function ng_launch_cleanup_probe_term_rename() {
    $from = get_term_by('slug', 'gaming-2', 'product_cat');
    $to   = get_term_by('slug', 'gaming',   'product_cat');
    if ( ! $from ) return ['state' => 'missing',  'detail' => "no product_cat slug 'gaming-2' found"];
    if ( $to    ) return ['state' => 'conflict', 'detail' => "both 'gaming' and 'gaming-2' exist; manual merge required"];
    return ['state' => 'ready', 'detail' => "term '" . $from->name . "' (id " . $from->term_id . ") slug 'gaming-2' → 'gaming'"];
}

/* =====================================================================
 * Run
 * ===================================================================== */

function ng_launch_cleanup_run( $form ) {
    $report  = ['trashed' => 0, 'skipped' => 0, 'mismatched' => 0, 'rows' => []];
    $targets = ng_launch_cleanup_targets();
    $picked  = isset($form['ng_cleanup']) && is_array($form['ng_cleanup']) ? $form['ng_cleanup'] : [];

    foreach ( $targets as $group_key => $group ) {
        $ticked_ids = isset($picked[$group_key]) && is_array($picked[$group_key])
            ? array_map('intval', $picked[$group_key])
            : [];
        foreach ( $group['items'] as $item ) {
            $row = [
                'group'  => $group['label'],
                'id'     => (int) $item['id'],
                'detail' => '',
                'status' => '',
                'action' => '',
            ];
            $probe = ng_launch_cleanup_probe( $item, $group['expected_type'] );
            $row['detail'] = $probe['title'] !== '' ? $probe['title'] : '(no live record)';

            if ( ! in_array( (int) $item['id'], $ticked_ids, true ) ) {
                $row['status'] = 'skipped';
                $row['action'] = 'unticked by operator';
                $report['skipped']++;
            } elseif ( $probe['state'] === 'missing' ) {
                $row['status'] = 'skipped';
                $row['action'] = 'no live record at this id';
                $report['skipped']++;
            } elseif ( $probe['state'] === 'wrong-type' || $probe['state'] === 'mismatch' ) {
                $row['status'] = 'mismatched';
                $row['action'] = 'kept untouched — title/slug no longer matches expected';
                $report['mismatched']++;
            } elseif ( $probe['state'] === 'already-trashed' ) {
                $row['status'] = 'skipped';
                $row['action'] = 'already in trash';
                $report['skipped']++;
            } else {
                $trashed = wp_trash_post( (int) $item['id'] );
                if ( $trashed ) {
                    $row['status'] = 'trashed';
                    $row['action'] = 'wp_trash_post → trash (recoverable)';
                    $report['trashed']++;
                } else {
                    $row['status'] = 'failed';
                    $row['action'] = 'wp_trash_post returned false';
                }
            }
            $report['rows'][] = $row;
        }
    }

    // Term rename: gaming-2 → gaming
    if ( ! empty($form['ng_cleanup_rename_gaming']) ) {
        $row = [
            'group'  => 'Term rename',
            'id'     => 0,
            'detail' => 'product_cat slug gaming-2 → gaming',
            'status' => '',
            'action' => '',
        ];
        $probe = ng_launch_cleanup_probe_term_rename();
        if ( $probe['state'] === 'ready' ) {
            $from = get_term_by('slug', 'gaming-2', 'product_cat');
            $upd  = wp_update_term( (int) $from->term_id, 'product_cat', ['slug' => 'gaming'] );
            if ( is_wp_error($upd) ) {
                $row['status'] = 'failed';
                $row['action'] = $upd->get_error_message();
            } else {
                $row['id']     = (int) $from->term_id;
                $row['status'] = 'renamed';
                $row['action'] = "slug → 'gaming'";
                $report['trashed']++; // count it as a fix
            }
        } else {
            $row['status'] = 'skipped';
            $row['action'] = $probe['detail'];
            $report['skipped']++;
        }
        $report['rows'][] = $row;
    }

    return $report;
}

/* =====================================================================
 * Render
 * ===================================================================== */

add_action('admin_menu', function () {
    add_management_page(
        'NeoGen Launch Cleanup',
        'NeoGen Launch Cleanup',
        'manage_options',
        'neogen-launch-cleanup',
        'ng_launch_cleanup_render'
    );
});

function ng_launch_cleanup_render() {
    if ( ! current_user_can('manage_options') ) wp_die('forbidden');

    $report = null;
    if ( isset($_POST['ng_launch_cleanup_nonce'])
        && wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['ng_launch_cleanup_nonce']) ), 'ng_launch_cleanup_run' ) ) {
        $report = ng_launch_cleanup_run( wp_unslash($_POST) );
    }

    $targets     = ng_launch_cleanup_targets();
    $term_probe  = ng_launch_cleanup_probe_term_rename();

    ?>
    <div class="wrap">
      <h1>NeoGen Launch Cleanup</h1>
      <p>Pre-launch one-shot cleanup. Tick which items to trash, click Run. Trashing is recoverable from <em>WP admin → Trash → Restore</em> for 30 days. Items already trashed, missing, or whose title/slug no longer matches the expected pattern are skipped automatically — never silently mis-trashed.</p>

      <?php if ( is_array($report) ) : ?>
        <div class="notice notice-<?php echo $report['mismatched'] > 0 ? 'warning' : 'success'; ?> is-dismissible">
          <p><strong>Cleanup complete:</strong>
            <?php echo (int) $report['trashed']; ?> trashed ·
            <?php echo (int) $report['skipped']; ?> skipped ·
            <?php echo (int) $report['mismatched']; ?> mismatched (kept untouched)
          </p>
        </div>
        <?php if ( ! empty($report['rows']) ) : ?>
        <table class="widefat striped" style="max-width:1080px;">
          <thead><tr><th>Status</th><th>Group</th><th>ID</th><th>Detail</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach ( $report['rows'] as $r ) : ?>
            <tr>
              <td><strong><?php echo esc_html( $r['status'] ); ?></strong></td>
              <td><?php echo esc_html( $r['group'] ); ?></td>
              <td><?php echo (int) $r['id']; ?></td>
              <td><?php echo esc_html( $r['detail'] ); ?></td>
              <td><?php echo esc_html( $r['action'] ); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      <?php endif; ?>

      <form method="post" onsubmit="return confirm('Trash all ticked items? Recoverable from Trash for 30 days.');" style="margin-top:1.5em;">
        <?php wp_nonce_field('ng_launch_cleanup_run', 'ng_launch_cleanup_nonce'); ?>

        <?php foreach ( $targets as $group_key => $group ) : ?>
          <h2 style="margin-top:1.5em;"><?php echo esc_html( $group['label'] ); ?></h2>
          <p style="color:#475569;margin-top:0;"><?php echo esc_html( $group['sub'] ); ?></p>
          <table class="widefat striped" style="max-width:1080px;">
            <thead>
              <tr>
                <th style="width:40px;text-align:center;"><input type="checkbox" checked onclick="document.querySelectorAll('input[name^=&quot;ng_cleanup[<?php echo esc_attr($group_key); ?>]&quot;]:not(:disabled)').forEach(c=>c.checked=this.checked);"></th>
                <th>ID</th>
                <th>Live title</th>
                <th>Live slug</th>
                <th>Status</th>
                <th>Probe</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( $group['items'] as $item ) :
              $probe = ng_launch_cleanup_probe( $item, $group['expected_type'] );
              $disabled = in_array( $probe['state'], ['missing', 'already-trashed', 'mismatch', 'wrong-type'], true );
              $checked  = ! $disabled;
              $probe_label = [
                'ready'           => '<span style="color:#1f9d57;">ready</span>',
                'mismatch'        => '<span style="color:#c14a1a;">mismatch — kept</span>',
                'wrong-type'      => '<span style="color:#c14a1a;">wrong type — kept</span>',
                'missing'         => '<span style="color:#9a9a9a;">missing</span>',
                'already-trashed' => '<span style="color:#9a9a9a;">already trash</span>',
              ][ $probe['state'] ] ?? esc_html( $probe['state'] );
            ?>
              <tr>
                <td style="text-align:center;">
                  <input type="checkbox"
                         name="ng_cleanup[<?php echo esc_attr($group_key); ?>][]"
                         value="<?php echo (int) $item['id']; ?>"
                         <?php checked($checked); ?>
                         <?php disabled($disabled); ?>>
                </td>
                <td><?php echo (int) $item['id']; ?></td>
                <td><?php echo esc_html( $probe['title'] ); ?></td>
                <td><code><?php echo esc_html( $probe['slug'] ); ?></code></td>
                <td><?php echo esc_html( $probe['status'] ); ?></td>
                <td><?php echo $probe_label; // safe: hard-coded HTML map ?>
                  <?php if ( ! empty( $item['note'] ) ) : ?>
                    <br><small style="color:#64748B;"><?php echo esc_html( $item['note'] ); ?></small>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endforeach; ?>

        <h2 style="margin-top:1.5em;">Term rename</h2>
        <p style="color:#475569;margin-top:0;">Restore the original gaming category slug. The current <code>gaming-2</code> means the original <code>gaming</code> term was deleted and recreated, so any inbound links to <code>/product-category/gaming/</code> now 404.</p>
        <p>
          <label style="display:flex;gap:.6em;align-items:flex-start;">
            <input type="checkbox" name="ng_cleanup_rename_gaming" value="1"
              <?php checked( $term_probe['state'] === 'ready' ); ?>
              <?php disabled( $term_probe['state'] !== 'ready' ); ?>>
            <span>
              <strong>Rename product_cat slug <code>gaming-2</code> → <code>gaming</code></strong><br>
              <small><?php echo esc_html( $term_probe['detail'] ); ?></small>
            </span>
          </label>
        </p>

        <p style="margin-top:1.5em;">
          <button type="submit" class="button button-primary">Run cleanup</button>
          <span style="margin-inline-start:.8em;color:#64748B;">Idempotent · trashing is reversible · re-runnable.</span>
        </p>
      </form>

      <h2 style="margin-top:2em;">Different products that share an image (manual fix)</h2>
      <p style="color:#475569;margin-top:0;max-width:760px;">These groups are <em>different products</em> whose featured images happen to be byte-identical (the previous import pulled the same source file for related rows). Each row needs its own image — open the edit screen and upload a unique photo. Not auto-trashable.</p>
      <table class="widefat striped" style="max-width:1080px;">
        <thead><tr><th>Group</th><th>Product IDs</th><th>Why</th></tr></thead>
        <tbody>
        <?php foreach ( ng_launch_cleanup_shared_image_groups() as $g ) : ?>
          <tr>
            <td><strong><?php echo esc_html( $g['label'] ); ?></strong></td>
            <td>
              <?php foreach ( $g['ids'] as $pid ) :
                $p = get_post( (int) $pid );
                if ( ! $p ) { echo '<div><code>' . (int) $pid . '</code> · missing</div>'; continue; }
                $title = $p->post_title;
              ?>
                <div>
                  <a href="<?php echo esc_url( admin_url('post.php?post=' . $pid . '&action=edit') ); ?>" target="_blank">#<?php echo (int) $pid; ?> · <?php echo esc_html( mb_substr( $title, 0, 60 ) ); ?></a>
                </div>
              <?php endforeach; ?>
            </td>
            <td><?php echo esc_html( $g['note'] ); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:2em;">After running</h2>
      <ol style="max-width:760px;">
        <li>Verify the storefront: <code>curl -sS "https://neogen.store/wp-json/wp/v2/posts" | grep -ci "Hello World"</code> should return <code>0</code>.</li>
        <li>Check the shop archive — the four demo products (Beanie / Belt / T-Shirt / Hoodie) should be gone.</li>
        <li>If you re-shipped the redirect plan to fix the sitemap loop, confirm: <code>curl -sI https://neogen.store/wp-sitemap.xml | head -3</code> returns <code>200</code> (not <code>301</code>).</li>
        <li>If anything was reported <em>mismatched</em>, open the row's edit screen in WP admin and decide manually.</li>
      </ol>

      <h2 style="margin-top:2em;">Rollback</h2>
      <p>Open <em>Posts → Trash</em>, <em>Products → Trash</em>, <em>Pages → Trash</em>, tick the rows, click <strong>Restore</strong>. Term rename is reversible by editing the term in <em>Products → Categories</em> and changing the slug back.</p>
    </div>
    <?php
}
