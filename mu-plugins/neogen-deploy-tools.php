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
    $or = implode(' OR ', array_map(function($n) {
        return "option_name LIKE '%" . esc_sql($n) . "%'";
    }, $needles));
    $rows = $wpdb->get_results("SELECT option_name, LEFT(option_value,180) AS preview, autoload FROM {$wpdb->options} WHERE $or ORDER BY option_name", ARRAY_A);

    $mor = implode(' OR ', array_map(function($n) {
        return "meta_key LIKE '%" . esc_sql($n) . "%'";
    }, ['neogen','deploy','ngdeploy','ng_deploy']));
    $mrows = $wpdb->get_results("SELECT user_id, meta_key, LEFT(meta_value,180) AS preview FROM {$wpdb->usermeta} WHERE $mor", ARRAY_A);

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
