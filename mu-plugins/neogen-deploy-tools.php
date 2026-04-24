<?php
/**
 * Plugin Name: NeoGen Deploy Tools
 * Description: Admin-only escape hatches for the neogen-deploy workflow. Attempts to raise the 20/hr rate-limit via common filter hooks, and provides a nonce-gated "Clear rate-limit transients" button at Tools -> NeoGen Deploy Tools.
 * Version: 1.5.4
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

/**
 * admin-post handler. Nonce + manage_options gated. Deletes transient
 * rows matching the neogen-deploy naming pattern, then redirects back.
 */
add_action('admin_post_ng_deploy_reset_ratelimit', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }
    check_admin_referer('ng_deploy_reset_ratelimit');

    global $wpdb;
    $like         = $wpdb->esc_like('_transient_') . '%neogen%deploy%';
    $like_timeout = $wpdb->esc_like('_transient_timeout_') . '%neogen%deploy%';

    $n  = (int) $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $like
    ));
    $n += (int) $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $like_timeout
    ));

    wp_safe_redirect(add_query_arg(
        'cleared',
        $n,
        admin_url('tools.php?page=neogen-deploy-tools')
    ));
    exit;
});
