<?php
/**
 * Plugin Name: NeoGen Theme Deploy
 * Description: Second deploy target — keeps wp-content/themes/blocksy-child/ in sync with this repo's themes/blocksy-child/. Runs on admin_init, gated by a content-hash so it only does work when source files actually change.
 * Version: 1.20.11
 * Author: Fahad Almansour
 *
 * Why this exists
 * ---------------
 * The `neogen-deploy` plugin (server-side, not in this repo) only clones
 * the repo into wp-content/mu-plugins/neogen-custom/. It does NOT copy
 * anything to wp-content/themes/. The Blocksy child theme cannot be
 * activated unless it physically exists at wp-content/themes/blocksy-child/.
 *
 * This mu-plugin closes that gap by syncing the child theme directory
 * from the cloned repo into the themes folder on every admin page load
 * where the source-directory hash has changed since the last sync.
 *
 * Limitations (Phase 1, accepted)
 * --------------------------------
 *   - Files removed from source are NOT removed from destination.
 *     Manual cleanup required if you rename or delete child-theme files.
 *   - Sync triggers on the NEXT admin page load after Pull-Latest, not
 *     on Pull-Latest itself (the mu-plugin file isn't included in the
 *     same request that pulls it).
 *   - WP_Filesystem must be in 'direct' mode. Hosts that require FTP
 *     credentials will skip the sync; check error_log if WP_DEBUG is on.
 */

defined('ABSPATH') || exit;

add_action('admin_init', function () {
    if ( ! current_user_can('manage_options') ) return;

    $src = WPMU_PLUGIN_DIR . '/neogen-custom/themes/blocksy-child';
    $dst = WP_CONTENT_DIR . '/themes/blocksy-child';

    if ( ! is_dir($src) ) return;

    $src_hash = ng_theme_deploy_dir_hash($src);
    if ( ! $src_hash ) return;

    $stored = get_option('ng_theme_deploy_synced_hash');
    if ( $stored === $src_hash ) return;

    $lock = 'ng_theme_deploy_lock';
    if ( get_transient($lock) ) return;
    set_transient($lock, 1, 30);

    require_once ABSPATH . 'wp-admin/includes/file.php';
    if ( ! WP_Filesystem() ) {
        delete_transient($lock);
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('[ng_theme_deploy] WP_Filesystem unavailable — skipping sync');
        }
        return;
    }

    if ( ! is_dir($dst) ) {
        wp_mkdir_p($dst);
    }

    $result = copy_dir($src, $dst);
    delete_transient($lock);

    if ( is_wp_error($result) ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('[ng_theme_deploy] copy_dir failed: ' . $result->get_error_message());
        }
        return;
    }

    update_option('ng_theme_deploy_synced_hash', $src_hash, false);
    update_option('ng_theme_deploy_synced_at',   gmdate('c'),  false);

    if ( defined('WP_DEBUG') && WP_DEBUG ) {
        error_log('[ng_theme_deploy] synced themes/blocksy-child → ' . $dst);
    }
});

/**
 * Cheap directory-state hash: pathname + mtime per file, sorted, md5.
 * Returns false on filesystem error.
 */
function ng_theme_deploy_dir_hash($dir) {
    $files = [];
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ( $it as $file ) {
            if ( $file->isFile() ) {
                $files[] = $file->getPathname() . ':' . $file->getMTime();
            }
        }
    } catch (Throwable $e) {
        return false;
    }
    if ( empty($files) ) return false;
    sort($files);
    return md5(implode('|', $files));
}

/**
 * Admin notice on the deploy page only — surfaces last sync timestamp
 * so the operator can confirm the child theme is current after Pull-Latest.
 */
add_action('admin_notices', function () {
    if ( ! current_user_can('manage_options') ) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ( ! $screen || $screen->id !== 'tools_page_neogen-deploy' ) return;

    $synced_at = get_option('ng_theme_deploy_synced_at');
    if ( ! $synced_at ) {
        echo '<div class="notice notice-warning"><p><strong>NeoGen Child theme:</strong> not yet synced into <code>wp-content/themes/blocksy-child/</code>. Reload any admin page to trigger the sync.</p></div>';
        return;
    }
    echo '<div class="notice notice-info"><p><strong>NeoGen Child theme:</strong> last synced ' . esc_html($synced_at) . ' (UTC). Activate from Appearance → Themes if not already active.</p></div>';
});
