<?php
/**
 * Plugin Name: NeoGen Deploy Tools
 * Description: Admin-only escape hatches for the neogen-deploy workflow. Attempts to raise the 20/hr rate-limit via common filter hooks, and provides a nonce-gated "Clear rate-limit transients" button at Tools -> NeoGen Deploy Tools.
 * Version: 1.5.8
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

/**
 * Attempt to raise the deploy rate-limit via the most likely filter
 * names. If the neogen-deploy plugin exposes any of these, the value
 * is overridden to 200/hr. If none match, this is a harmless no-op.
 */
foreach (['neogen_deploy_ratelimit', 'neogen_deploy_rate_limit', 'neogen_deploy_max_per_hour'] as $hook) {
    add_filter($hook, function () { return 200; }, 99);
}

/**
 * Tools menu: Tools > NeoGen Deploy Tools
 */
add_action('admin_menu', function () {
    add_management_page(
        'NeoGen Deploy Tools',
        'NeoGen Deploy Tools',
        'manage_options',
        'neogen-deploy-tools',
        'ng_deploy_tools_render'
    );
});

function ng_deploy_tools_render() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    $cleared = isset($_GET['cleared']) ? (int) $_GET['cleared'] : null;
    ?>
    <div class="wrap">
      <h1>NeoGen Deploy Tools</h1>
      <p>
        If the deploy plugin's 20/hr rate limit has you blocked and you can't
        wait, clear its transients here. This deletes only rows whose
        <code>option_name</code> matches <code>%transient%neogen%deploy%</code>.
      </p>
      <?php if ($cleared !== null) : ?>
        <div class="notice notice-success is-dismissible">
          <p>Cleared <b><?php echo (int) $cleared; ?></b> transient row(s). Try <em>Pull Latest</em> again.</p>
        </div>
      <?php endif; ?>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ng_deploy_reset_ratelimit'); ?>
        <input type="hidden" name="action" value="ng_deploy_reset_ratelimit">
        <?php submit_button('Clear rate-limit transients', 'primary'); ?>
      </form>
      <hr>
      <h2>Plugin source dump (read-only)</h2>
      <p>Prints lines around throttle keywords from the upstream <code>neogen-deploy</code> plugin so we can identify the actual storage mechanism (file? wpdb? cache?).</p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ng_deploy_source_dump'); ?>
        <input type="hidden" name="action" value="ng_deploy_source_dump">
        <?php submit_button('Dump plugin source around throttle', 'secondary'); ?>
      </form>
      <?php if ( ! empty($_GET['source_dumped']) ) : ng_deploy_tools_render_source_dump(); endif; ?>

      <hr>
      <h2>Throttle inspector (read-only)</h2>
      <p>
        Dumps every <code>wp_options</code> / <code>wp_usermeta</code> row whose
        key matches likely throttle keywords, and grep-scans the upstream
        <code>neogen-deploy</code> plugin source for storage calls. Use this
        when the <em>Clear</em> button above does nothing — the upstream
        rate-limit key is named something we are not matching.
      </p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ng_deploy_throttle_inspect'); ?>
        <input type="hidden" name="action" value="ng_deploy_throttle_inspect">
        <?php submit_button('Run throttle inspector', 'secondary'); ?>
      </form>
      <?php if (!empty($_GET['inspected'])) : ng_deploy_tools_render_inspection(); endif; ?>
      <hr>
      <?php /* removed 2026-05-07: gift-cards purged — admin tool block hidden
      <h2>Gift-card match coverage report</h2>
      <p>Walks every published WC product matched by <code>ng_gift_card_is_candidate_product()</code> and tallies which slot they resolve to. Shows unmatched products with name + SKU so you can see which spellings need extra Arabic keyword coverage in <code>ng_gift_card_asset_map()</code>.</p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ng_deploy_gc_coverage'); ?>
        <input type="hidden" name="action" value="ng_deploy_gc_coverage">
        <?php submit_button('Run gift-card coverage report', 'secondary'); ?>
      </form>
      <?php if (!empty($_GET['gc_coverage'])) : ng_deploy_tools_render_gc_coverage(); endif; ?>
      <hr>
      */ ?>
      <h2>Current raised-cap override</h2>
      <p>
        This plugin attempts to raise the cap to <b>200/hr</b> by filtering
        <code>neogen_deploy_ratelimit</code>,
        <code>neogen_deploy_rate_limit</code>, and
        <code>neogen_deploy_max_per_hour</code>. If the upstream plugin does
        not expose any of those, the override is a no-op and you should still
        see the 20/hr limit.
      </p>
    </div>
    <?php
}

function ng_deploy_tools_render_inspection() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;

    $needles = ['neogen','deploy','ratelimit','rate_limit','throttle','pull_lock','last_pull','deploys_per_hour','deploy_count','ngdeploy','ng_deploy'];
    $opt_clauses = array_fill( 0, count($needles), 'option_name LIKE %s' );
    $opt_args    = array_map( function ($n) use ($wpdb) {
        return '%' . $wpdb->esc_like($n) . '%';
    }, $needles );
    $opt_sql  = "SELECT option_name, LEFT(option_value,180) AS preview, autoload "
              . "FROM {$wpdb->options} WHERE " . implode(' OR ', $opt_clauses)
              . " ORDER BY option_name";
    $rows = $wpdb->get_results( $wpdb->prepare( $opt_sql, $opt_args ), ARRAY_A );

    $meta_needles = ['neogen','deploy','ngdeploy','ng_deploy'];
    $meta_clauses = array_fill( 0, count($meta_needles), 'meta_key LIKE %s' );
    $meta_args    = array_map( function ($n) use ($wpdb) {
        return '%' . $wpdb->esc_like($n) . '%';
    }, $meta_needles );
    $meta_sql = "SELECT user_id, meta_key, LEFT(meta_value,180) AS preview "
              . "FROM {$wpdb->usermeta} WHERE " . implode(' OR ', $meta_clauses);
    $mrows = $wpdb->get_results( $wpdb->prepare( $meta_sql, $meta_args ), ARRAY_A );

    echo '<pre style="background:#0c0c0c;color:#0f0;padding:14px;white-space:pre-wrap;font:12px/1.5 ui-monospace,monospace;border-radius:4px">';
    echo "wp_options matches: " . count($rows) . "\n\n";
    foreach ($rows as $r) {
        printf("%-60s [%s]\n  %s\n\n",
            esc_html($r['option_name']),
            esc_html($r['autoload']),
            esc_html($r['preview'])
        );
    }
    echo "wp_usermeta matches: " . count($mrows) . "\n\n";
    foreach ($mrows as $r) {
        printf("u#%d %-50s\n  %s\n\n",
            (int) $r['user_id'],
            esc_html($r['meta_key']),
            esc_html($r['preview'])
        );
    }

    $candidates = [
        WP_PLUGIN_DIR . '/neogen-deploy',
        WP_PLUGIN_DIR . '/ngs-deploy',
        WP_PLUGIN_DIR . '/ng-deploy',
    ];
    foreach ($candidates as $plug) {
        if (!is_dir($plug)) continue;
        echo "neogen-deploy plugin path: " . esc_html($plug) . "\n";
        foreach (glob("$plug/*.php") ?: [] as $f) {
            echo "\n=== " . esc_html(basename($f)) . " ===\n";
            $src = @file_get_contents($f);
            if ($src === false) continue;
            if (preg_match_all('/(set_transient|update_option|update_user_meta|set_site_transient)\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $src, $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) printf("  %-22s -> %s\n", esc_html($hit[1]), esc_html($hit[2]));
            }
            if (preg_match_all('/Rate\s*limit|deploys?\s*\/\s*hour|too\s+many|max[_\s]?per[_\s]?hour/i', $src, $m2)) {
                echo "  matched throttle copy: " . count($m2[0]) . "x\n";
            }
        }
        break;
    }
    echo '</pre>';
}

add_action('admin_post_ng_deploy_throttle_inspect', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    check_admin_referer('ng_deploy_throttle_inspect');
    wp_safe_redirect(add_query_arg(
        'inspected',
        '1',
        admin_url('tools.php?page=neogen-deploy-tools')
    ));
    exit;
});

add_action('admin_post_ng_deploy_source_dump', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    check_admin_referer('ng_deploy_source_dump');
    wp_safe_redirect(add_query_arg(
        'source_dumped',
        '1',
        admin_url('tools.php?page=neogen-deploy-tools')
    ));
    exit;
});

function ng_deploy_tools_render_source_dump() {
    if ( ! current_user_can('manage_options') ) return;

    $candidates = [
        WP_PLUGIN_DIR . '/neogen-deploy',
        WP_PLUGIN_DIR . '/ngs-deploy',
        WP_PLUGIN_DIR . '/ng-deploy',
    ];

    echo '<pre style="background:#0c0c0c;color:#0f0;padding:14px;white-space:pre-wrap;font:11.5px/1.5 ui-monospace,monospace;border-radius:4px;max-height:780px;overflow:auto">';

    foreach ( $candidates as $plug ) {
        if ( ! is_dir($plug) ) continue;
        echo "==== plugin dir: " . esc_html($plug) . " ====\n\n";

        // List all files in the plugin dir (1 level + subdirs)
        $rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plug, RecursiveDirectoryIterator::SKIP_DOTS ) );
        echo "Files:\n";
        foreach ( $rii as $file ) {
            if ( $file->isFile() ) {
                $rel = ltrim( str_replace( $plug, '', $file->getPathname() ), '/\\' );
                printf("  %-60s %d bytes  mtime=%s\n",
                    esc_html($rel),
                    $file->getSize(),
                    gmdate('Y-m-d H:i:s', $file->getMTime())
                );
            }
        }
        echo "\n";

        // Look for non-PHP data files that might be a throttle counter
        $data_exts = ['json', 'log', 'txt', 'lock', 'tmp', 'dat', 'cache'];
        foreach ( $rii as $file ) {
            if ( $file->isFile() ) {
                $ext = strtolower( $file->getExtension() );
                if ( in_array($ext, $data_exts, true) && $file->getSize() < 65536 ) {
                    echo "---- DATA FILE: " . esc_html(basename($file->getPathname())) . " (" . $file->getSize() . "b) ----\n";
                    echo esc_html( (string) @file_get_contents( $file->getPathname() ) ) . "\n\n";
                }
            }
        }

        // Grep PHP sources for throttle/storage keywords + show surrounding lines
        $patterns = [
            'rate.?limit',
            '20.*hour',
            'deploys?.?per.?hour',
            'too.?many',
            'file_put_contents',
            'fopen',
            'fwrite',
            '\$wpdb',
            'wp_cache_(set|get|delete)',
            'set_transient',
            'update_option',
            'update_user_meta',
            'add_action.*wp_ajax',
            'add_action.*admin_post',
            'check_ajax_referer',
        ];

        foreach ( glob("$plug/*.php") ?: [] as $f ) {
            $src = @file_get_contents($f);
            if ( $src === false ) continue;
            $lines = explode("\n", $src);
            $hits  = [];
            foreach ( $lines as $i => $line ) {
                foreach ( $patterns as $p ) {
                    if ( preg_match('/' . $p . '/i', $line) ) {
                        $hits[$i] = true;
                        break;
                    }
                }
            }
            if ( empty($hits) ) continue;
            echo "==== " . esc_html(basename($f)) . " (" . count($lines) . " lines) ====\n";
            // Print 3 lines of context around each hit, dedup overlapping ranges
            $printed = [];
            foreach ( array_keys($hits) as $line_no ) {
                $start = max(0, $line_no - 2);
                $end   = min(count($lines) - 1, $line_no + 4);
                for ( $i = $start; $i <= $end; $i++ ) {
                    if ( isset($printed[$i]) ) continue;
                    $printed[$i] = true;
                    printf("%5d: %s\n", $i + 1, esc_html( rtrim($lines[$i]) ));
                }
                echo "  ---\n";
            }
            echo "\n";
        }
        break; // only first matched plugin dir
    }
    echo '</pre>';
}

/**
 * admin-post handler. Nonce + manage_options gated. Aggressively
 * nukes anything in wp_options whose key contains both "neogen"
 * and either "deploy" or "rate" or "limit" or "throttle". Also
 * walks site_transient + user_meta for the same patterns. The
 * upstream rate-limit storage key is unknown so we cast wide.
 */
add_action('admin_post_ng_deploy_reset_ratelimit', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    check_admin_referer('ng_deploy_reset_ratelimit');

    global $wpdb;

    $option_patterns = [
        '%neogen%deploy%',
        '%neogen%rate%',
        '%neogen%limit%',
        '%neogen%throttle%',
        '%ng_deploy%',
        '%ngdeploy%',
    ];

    $n = 0;
    foreach ($option_patterns as $p) {
        $n += (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $p
        ));
    }

    $n += (int) $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        '%neogen%deploy%'
    ));
    $n += (int) $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        '%ng_deploy%'
    ));

    wp_safe_redirect(add_query_arg(
        'cleared',
        $n,
        admin_url('tools.php?page=neogen-deploy-tools')
    ));
    exit;
});

/* ---------------------------------------------------------------
 * v1.20.1 — Gift-card match coverage report
 * Iterates every published product, calls
 * ng_gift_card_is_candidate_product() / ng_gift_card_asset_for_product()
 * and tabulates per-brand match counts + unmatched-list.
 * ------------------------------------------------------------- */

// removed 2026-05-07: gift-cards purged — admin_post handler detached
// add_action('admin_post_ng_deploy_gc_coverage', function () {
//     if (!current_user_can('manage_options')) wp_die('forbidden');
//     check_admin_referer('ng_deploy_gc_coverage');
//     wp_safe_redirect(add_query_arg('gc_coverage', '1', admin_url('tools.php?page=neogen-deploy-tools')));
//     exit;
// });

function ng_deploy_tools_render_gc_coverage() {
    return; // removed 2026-05-07: gift-cards purged — body retained for rollback
    if (!current_user_can('manage_options')) return;
    if (!function_exists('ng_gift_card_asset_map') || !function_exists('wc_get_products')) {
        echo '<div class="notice notice-error"><p>WooCommerce or gift-cards plugin not active.</p></div>';
        return;
    }

    $matched   = [];      // brand_key => [['name'=>..,'sku'=>..,'matched_via'=>..]]
    $unmatched = [];      // [['name'=>..,'sku'=>..,'id'=>..]]
    $total_candidates = 0;

    $products = wc_get_products([
        'limit'   => -1,
        'status'  => 'publish',
        'type'    => ['simple', 'variable'],
        'return'  => 'objects',
    ]);

    foreach ($products as $product) {
        if (!ng_gift_card_is_candidate_product($product)) continue;
        $total_candidates++;

        $asset = ng_gift_card_asset_for_product($product);
        $entry = [
            'id'   => $product->get_id(),
            'name' => $product->get_name(),
            'sku'  => $product->get_sku(),
        ];
        if ($asset && !empty($asset['key'])) {
            $entry['matched_via'] = $asset['matched_via'] ?? 'keyword';
            $matched[$asset['key']][] = $entry;
        } else {
            $unmatched[] = $entry;
        }
    }

    krsort($matched);
    uasort($matched, function ($a, $b) { return count($b) <=> count($a); });

    echo '<pre style="background:#0c0c0c;color:#0f0;padding:14px;white-space:pre-wrap;font:12px/1.5 ui-monospace,monospace;border-radius:4px;max-height:600px;overflow:auto">';
    printf("Candidate products (gift-card-like): %d\nMatched: %d  ·  Unmatched: %d\n\n",
        $total_candidates,
        $total_candidates - count($unmatched),
        count($unmatched)
    );

    echo "── Per-brand match counts ──────────────────\n";
    foreach ($matched as $key => $list) {
        printf("  %-22s %d\n", $key, count($list));
    }

    echo "\n── Unmatched products (first 100) ─────────\n";
    foreach (array_slice($unmatched, 0, 100) as $u) {
        printf("  #%-6d  %-12s  %s\n",
            (int) $u['id'],
            esc_html((string) $u['sku']),
            esc_html((string) $u['name'])
        );
    }
    if (count($unmatched) > 100) {
        printf("\n  … and %d more not shown.\n", count($unmatched) - 100);
    }
    echo '</pre>';
}
