<?php
/**
 * Plugin Name: NeoGen SEO Metabox
 * Description: Per-post/page/product SEO override UI + one-shot migrator from Rank Math post meta. Phase R2 of the Rank Math replacement plan. Writes _neogen_seo_{title,description,canonical,robots} which the engine in neogen-seo-engine.php reads first.
 * Version: 1.22.1
 * Author: Fahad Almansour
 *
 * Phase R2 contract
 * -----------------
 * - Adds a "NeoGen SEO" metabox to every public post type
 *   (post, page, product). Fields: SEO title, meta description,
 *   canonical URL, robots directive. Live char counters on title
 *   and description (50–60 / 120–155 chars are the SERP sweet spots).
 * - Saves to post-meta keys `_neogen_seo_*` — already consumed by
 *   the engine via NG_SEO_Engine::post_override().
 * - Tools → NeoGen SEO Migration page provides a one-shot button
 *   that scans every post for `rank_math_*` meta and copies it to
 *   `_neogen_seo_*` (idempotent: doesn't overwrite existing values).
 *   Logs to `wp-content/uploads/neogen-seo-migration.log`.
 * - WP-CLI command `wp neogen-seo migrate-rank-math` does the same.
 *
 * Block Editor compatibility: meta-boxes render at the bottom of the
 * post-edit screen by default. JS char counters work in both classic
 * and block editor contexts. Save flows through both POST (classic)
 * and the back-compat layer that the block editor ships meta-box
 * data through.
 */

defined('ABSPATH') || exit;

const NG_SEO_METABOX_NONCE = 'ng_seo_metabox_nonce';
const NG_SEO_MIGRATION_NONCE = 'ng_seo_migration_nonce';
const NG_SEO_MIGRATION_OPTION = 'ng_seo_migration_log';

/* =====================================================================
 * METABOX REGISTRATION
 * ===================================================================== */

add_action('add_meta_boxes', function () {
    $post_types = ['post', 'page', 'product'];
    /**
     * Filter the post types that show the NeoGen SEO metabox.
     */
    $post_types = apply_filters('ng_seo_metabox_post_types', $post_types);

    foreach ( $post_types as $pt ) {
        if ( ! post_type_exists($pt) ) continue;
        add_meta_box(
            'ng-seo-metabox',
            'NeoGen SEO',
            'ng_seo_metabox_render',
            $pt,
            'normal',
            'high',
            // Block-editor compat — metabox renders at bottom of editor.
            ['__block_editor_compatible_meta_box' => true]
        );
    }
});

function ng_seo_metabox_render( $post ) {
    wp_nonce_field( 'ng_seo_metabox_save', NG_SEO_METABOX_NONCE );

    $title       = (string) get_post_meta( $post->ID, '_neogen_seo_title', true );
    $description = (string) get_post_meta( $post->ID, '_neogen_seo_description', true );
    $canonical   = (string) get_post_meta( $post->ID, '_neogen_seo_canonical', true );
    $robots      = (string) get_post_meta( $post->ID, '_neogen_seo_robots', true );

    $robots_options = [
        ''                          => 'Default (index, follow)',
        'index, follow'             => 'index, follow',
        'noindex, follow'           => 'noindex, follow',
        'index, nofollow'           => 'index, nofollow',
        'noindex, nofollow'         => 'noindex, nofollow',
    ];

    ?>
    <style>
        .ng-seo-row { margin: 12px 0; display: flex; flex-direction: column; gap: 6px; }
        .ng-seo-row label { font-weight: 600; font-size: 13px; }
        .ng-seo-row input[type="text"], .ng-seo-row input[type="url"], .ng-seo-row textarea, .ng-seo-row select {
            width: 100%; padding: 6px 8px; box-sizing: border-box;
        }
        .ng-seo-row textarea { min-height: 64px; resize: vertical; }
        .ng-seo-counter { font-size: 12px; color: #555; }
        .ng-seo-counter.warn { color: #EF4444; }
        .ng-seo-counter.ok { color: #22C55E; }
        .ng-seo-help { font-size: 12px; color: #6a6a6a; }
        .ng-seo-fallback { font-size: 12px; color: #6a6a6a; padding: 6px 8px; background: #f6f7f7; border-left: 3px solid #ccc; margin-top: 4px; }
    </style>

    <div class="ng-seo-row">
        <label for="ng_seo_title">SEO title <span class="ng-seo-help">(target 50–60 chars; site name appended automatically when empty)</span></label>
        <input type="text" id="ng_seo_title" name="ng_seo_title"
               value="<?php echo esc_attr($title); ?>"
               maxlength="200"
               placeholder="<?php echo esc_attr__( 'Leave empty to use post title + site name', 'neogen' ); ?>">
        <span class="ng-seo-counter" id="ng_seo_title_counter"></span>
    </div>

    <div class="ng-seo-row">
        <label for="ng_seo_description">Meta description <span class="ng-seo-help">(target 120–155 chars)</span></label>
        <textarea id="ng_seo_description" name="ng_seo_description"
                  maxlength="320"
                  placeholder="<?php echo esc_attr__( 'Leave empty to auto-generate from excerpt or content', 'neogen' ); ?>"><?php echo esc_textarea($description); ?></textarea>
        <span class="ng-seo-counter" id="ng_seo_description_counter"></span>
    </div>

    <div class="ng-seo-row">
        <label for="ng_seo_canonical">Canonical URL <span class="ng-seo-help">(only set this when the page is a duplicate of another URL)</span></label>
        <input type="url" id="ng_seo_canonical" name="ng_seo_canonical"
               value="<?php echo esc_attr($canonical); ?>"
               placeholder="<?php echo esc_attr( get_permalink($post) ?: home_url('/') ); ?>">
    </div>

    <div class="ng-seo-row">
        <label for="ng_seo_robots">Robots directive</label>
        <select id="ng_seo_robots" name="ng_seo_robots">
            <?php foreach ( $robots_options as $val => $label ) : ?>
                <option value="<?php echo esc_attr($val); ?>" <?php selected($robots, $val); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="ng-seo-fallback">
        <strong>Fallback (when fields are empty):</strong> the engine generates output from the post title + excerpt + permalink. View-source on the live page (with <code>NG_SEO_PARITY</code> defined) to verify.
    </div>

    <script>
    (function () {
        var titleEl  = document.getElementById('ng_seo_title');
        var titleCtr = document.getElementById('ng_seo_title_counter');
        var descEl   = document.getElementById('ng_seo_description');
        var descCtr  = document.getElementById('ng_seo_description_counter');

        function update(input, counter, lo, hi) {
            var n = (input.value || '').length;
            counter.textContent = n + ' / ' + hi + ' chars';
            counter.classList.remove('ok', 'warn');
            if (n === 0) return; // empty = falls back to engine default; no signal
            if (n >= lo && n <= hi) counter.classList.add('ok');
            else counter.classList.add('warn');
        }
        if (titleEl && titleCtr) {
            update(titleEl, titleCtr, 50, 60);
            titleEl.addEventListener('input', function () { update(titleEl, titleCtr, 50, 60); });
        }
        if (descEl && descCtr) {
            update(descEl, descCtr, 120, 155);
            descEl.addEventListener('input', function () { update(descEl, descCtr, 120, 155); });
        }
    })();
    </script>
    <?php
}

/* =====================================================================
 * METABOX SAVE
 * ===================================================================== */

add_action('save_post', function ( $post_id, $post, $update ) {
    if ( wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) ) return;
    if ( ! isset($_POST[NG_SEO_METABOX_NONCE]) ) return; // metabox not present in this save (REST without it)
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[NG_SEO_METABOX_NONCE] ) ), 'ng_seo_metabox_save' ) ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    $fields = [
        '_neogen_seo_title'       => isset($_POST['ng_seo_title'])       ? wp_unslash( (string) $_POST['ng_seo_title'] )       : '',
        '_neogen_seo_description' => isset($_POST['ng_seo_description']) ? wp_unslash( (string) $_POST['ng_seo_description'] ) : '',
        '_neogen_seo_canonical'   => isset($_POST['ng_seo_canonical'])   ? wp_unslash( (string) $_POST['ng_seo_canonical'] )   : '',
        '_neogen_seo_robots'      => isset($_POST['ng_seo_robots'])      ? wp_unslash( (string) $_POST['ng_seo_robots'] )      : '',
    ];

    foreach ( $fields as $key => $val ) {
        $val = sanitize_text_field($val);
        if ( $key === '_neogen_seo_canonical' ) $val = esc_url_raw($val);
        if ( $key === '_neogen_seo_robots' ) {
            $allowed = ['', 'index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow'];
            if ( ! in_array($val, $allowed, true) ) $val = '';
        }
        if ( $val === '' ) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $val);
        }
    }
}, 10, 3);

/* =====================================================================
 * RANK MATH MIGRATION TOOL — Tools → NeoGen SEO Migration
 * ===================================================================== */

add_action('admin_menu', function () {
    add_management_page(
        'NeoGen SEO Migration',
        'NeoGen SEO Migration',
        'manage_options',
        'neogen-seo-migration',
        'ng_seo_migration_render'
    );
});

function ng_seo_migration_render() {
    if ( ! current_user_can('manage_options') ) wp_die('forbidden');

    $log = get_option(NG_SEO_MIGRATION_OPTION, []);

    $action_taken = '';
    if ( isset($_POST[NG_SEO_MIGRATION_NONCE])
        && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[NG_SEO_MIGRATION_NONCE] ) ), 'ng_seo_migrate' )
        && ! empty($_POST['ng_seo_run_migration']) ) {
        $result = ng_seo_migrate_rank_math_meta();
        $action_taken = sprintf(
            'Migrated %d post(s). Skipped %d already-migrated post(s). Errors: %d.',
            $result['migrated'],
            $result['skipped'],
            $result['errors']
        );
        $log = get_option(NG_SEO_MIGRATION_OPTION, []);
    }

    ?>
    <div class="wrap">
      <h1>NeoGen SEO Migration</h1>
      <p>One-shot migrator that copies <code>rank_math_*</code> post meta into <code>_neogen_seo_*</code> for every post on the site. The engine in <code>neogen-seo-engine.php</code> reads <code>_neogen_seo_*</code> first, so once migrated your existing per-post Rank Math customizations survive a Rank Math deactivation.</p>

      <?php if ( $action_taken ) : ?>
        <div class="notice notice-success is-dismissible"><p><strong>Done.</strong> <?php echo esc_html($action_taken); ?></p></div>
      <?php endif; ?>

      <h2>What it does</h2>
      <table class="widefat striped" style="max-width:720px;">
        <thead><tr><th>Rank Math meta key</th><th>→</th><th>NeoGen SEO meta key</th></tr></thead>
        <tbody>
          <tr><td><code>rank_math_title</code></td><td>→</td><td><code>_neogen_seo_title</code></td></tr>
          <tr><td><code>rank_math_description</code></td><td>→</td><td><code>_neogen_seo_description</code></td></tr>
          <tr><td><code>rank_math_canonical_url</code></td><td>→</td><td><code>_neogen_seo_canonical</code></td></tr>
          <tr><td><code>rank_math_robots</code> (array)</td><td>→</td><td><code>_neogen_seo_robots</code> (string)</td></tr>
        </tbody>
      </table>

      <h2>Idempotency</h2>
      <p>Safe to re-run. If a post already has a <code>_neogen_seo_*</code> value (set manually via the metabox or by a previous migration run), it is <strong>skipped</strong>, not overwritten. Posts with no <code>rank_math_*</code> meta are skipped entirely.</p>

      <form method="post" style="margin-top:1.5em;">
        <?php wp_nonce_field('ng_seo_migrate', NG_SEO_MIGRATION_NONCE); ?>
        <input type="hidden" name="ng_seo_run_migration" value="1">
        <button class="button button-primary" type="submit"
                onclick="return confirm('Run the Rank Math → NeoGen SEO migration? Idempotent and reversible (re-running won\\'t re-migrate already-migrated posts).');">
          Run migration now
        </button>
      </form>

      <?php if ( ! empty($log) ) : ?>
        <h2 style="margin-top:2em;">Last run log <small>(top 25)</small></h2>
        <pre style="background:#f6f7f7;padding:8px;font-size:12px;max-height:300px;overflow:auto;"><?php
          $log_slice = array_slice( (array) $log, -25 );
          foreach ( $log_slice as $entry ) echo esc_html( $entry ) . "\n";
        ?></pre>
        <p><small>Full log persisted in <code>wp_options</code> under <code><?php echo esc_html(NG_SEO_MIGRATION_OPTION); ?></code>.</small></p>
      <?php endif; ?>

      <h2 style="margin-top:2em;">WP-CLI</h2>
      <p>If you have wp-cli on the host, you can run the migration without loading wp-admin:</p>
      <pre style="background:#f6f7f7;padding:8px;">wp neogen-seo migrate-rank-math</pre>
    </div>
    <?php
}

/* =====================================================================
 * MIGRATION CORE — runs from the admin button OR from wp-cli
 * ===================================================================== */

function ng_seo_migrate_rank_math_meta() {
    global $wpdb;

    $migrated = 0;
    $skipped  = 0;
    $errors   = 0;
    $log      = [];

    $log[] = sprintf( '[%s] migration run started', gmdate('c') );

    // Find every post that has at least one rank_math_* meta key.
    $rows = $wpdb->get_results(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
         WHERE meta_key LIKE 'rank_math_%'
         ORDER BY post_id ASC"
    );
    if ( ! is_array($rows) ) {
        $log[] = sprintf( '[%s] db query failed', gmdate('c') );
        update_option(NG_SEO_MIGRATION_OPTION, $log, false);
        return [ 'migrated' => 0, 'skipped' => 0, 'errors' => 1 ];
    }

    $field_map = [
        'rank_math_title'         => '_neogen_seo_title',
        'rank_math_description'   => '_neogen_seo_description',
        'rank_math_canonical_url' => '_neogen_seo_canonical',
        // rank_math_robots is special — see below
    ];

    foreach ( $rows as $row ) {
        $post_id = (int) $row->post_id;
        if ( $post_id <= 0 ) continue;

        $any_migrated = false;
        $any_skipped  = false;

        foreach ( $field_map as $rm_key => $ng_key ) {
            $rm_val = (string) get_post_meta($post_id, $rm_key, true);
            if ( $rm_val === '' ) continue;
            $existing = (string) get_post_meta($post_id, $ng_key, true);
            if ( $existing !== '' ) {
                $any_skipped = true;
                continue;
            }
            $clean = sanitize_text_field( $rm_val );
            if ( $ng_key === '_neogen_seo_canonical' ) $clean = esc_url_raw($clean);
            if ( update_post_meta($post_id, $ng_key, $clean) !== false ) {
                $any_migrated = true;
            }
        }

        // rank_math_robots is stored as serialized array (e.g. ['index','follow']).
        // Convert to comma-separated string in our keys.
        $rm_robots = get_post_meta($post_id, 'rank_math_robots', true);
        if ( ! empty($rm_robots) && is_array($rm_robots) ) {
            $existing = (string) get_post_meta($post_id, '_neogen_seo_robots', true);
            if ( $existing === '' ) {
                $clean_robots = implode(', ', array_filter(array_map('sanitize_text_field', $rm_robots)));
                $allowed = ['', 'index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow'];
                if ( in_array($clean_robots, $allowed, true) ) {
                    update_post_meta($post_id, '_neogen_seo_robots', $clean_robots);
                    $any_migrated = true;
                }
            } else {
                $any_skipped = true;
            }
        }

        if ( $any_migrated ) {
            $migrated++;
            $log[] = sprintf( '[%s] migrated post %d', gmdate('c'), $post_id );
        } elseif ( $any_skipped ) {
            $skipped++;
        }
    }

    $log[] = sprintf(
        '[%s] migration run finished: %d migrated, %d skipped, %d errors',
        gmdate('c'),
        $migrated,
        $skipped,
        $errors
    );

    // Cap the log at 500 entries to avoid bloating wp_options.
    if ( count($log) > 500 ) {
        $log = array_slice($log, -500);
    }
    update_option(NG_SEO_MIGRATION_OPTION, $log, false);

    return [
        'migrated' => $migrated,
        'skipped'  => $skipped,
        'errors'   => $errors,
    ];
}

/* =====================================================================
 * WP-CLI INTEGRATION
 * ===================================================================== */

if ( defined('WP_CLI') && WP_CLI ) {
    WP_CLI::add_command('neogen-seo migrate-rank-math', function () {
        WP_CLI::log('Running Rank Math → NeoGen SEO migration…');
        $r = ng_seo_migrate_rank_math_meta();
        WP_CLI::success(sprintf(
            'migrated=%d skipped=%d errors=%d',
            $r['migrated'], $r['skipped'], $r['errors']
        ));
    });
}
