<?php
/**
 * NeoGen override for the my-account sidebar nav.
 *
 * Routed via wc_get_template filter map. Each menu item carries its
 * Woo class names (woocommerce-MyAccount-navigation-link--<endpoint>,
 * is-active) plus our .ng-account-nav-* classes.
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_account_navigation');
?>
<nav class="woocommerce-MyAccount-navigation ng-account-nav" aria-label="Account navigation">
  <div class="ng-account-nav-head">
    <span class="led on" aria-hidden="true"></span>
    <span>// OPERATOR CONSOLE</span>
  </div>
  <ul>
    <?php foreach (wc_get_account_menu_items() as $endpoint => $label) : ?>
      <li class="woocommerce-MyAccount-navigation-link woocommerce-MyAccount-navigation-link--<?php echo esc_attr($endpoint); ?> <?php echo wc_is_current_account_menu_item($endpoint) ? 'is-active' : ''; ?>">
        <a href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>">
          <span class="dot" aria-hidden="true"></span>
          <span class="lbl"><?php echo esc_html($label); ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>
<?php
do_action('woocommerce_after_account_navigation');
