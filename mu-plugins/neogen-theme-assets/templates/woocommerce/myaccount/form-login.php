<?php
/**
 * NeoGen override for /my-account/ login + register form.
 *
 * Preserves all Woo nonces, hidden inputs, action names exactly so
 * authentication keeps working. Themed wrapper only.
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_customer_login_form'); ?>

<section class="ng-account-auth">

  <div class="ng-account-auth-bg" aria-hidden="true">
    <svg viewBox="-50 -50 100 100">
      <path d="M0 -44 L9 -26 L35 -35 L26 -9 L44 0 L26 9 L35 35 L9 26 L0 44 L-9 26 L-35 35 L-26 9 L-44 0 L-26 -9 L-35 -35 L-9 -26 Z"/>
    </svg>
  </div>

  <div class="ng-account-auth-inner u-columns col2-set" id="customer_login">

    <div class="ng-account-auth-card u-column1 col-1">
      <div class="ng-account-kicker">
        <span class="led on" aria-hidden="true"></span>
        <span>// AUTHENTICATE</span>
      </div>
      <h2 class="ng-account-h2">
        <span class="ar">تسجيل الدخول</span>
        <span class="en">LOG IN</span>
      </h2>

      <form class="woocommerce-form woocommerce-form-login login ng-account-form" method="post">

        <?php do_action('woocommerce_login_form_start'); ?>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
          <label for="username"><?php esc_html_e('Username or email address', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
          <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo (!empty($_POST['username'])) ? esc_attr(wp_unslash($_POST['username'])) : ''; ?>" required aria-required="true" />
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
          <label for="password"><?php esc_html_e('Password', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
          <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
        </p>

        <?php do_action('woocommerce_login_form'); ?>

        <p class="form-row">
          <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
            <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e('Remember me', 'woocommerce'); ?></span>
          </label>
          <?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>
          <button type="submit" class="woocommerce-button button woocommerce-form-login__submit ng-auth-submit" name="login" value="<?php esc_attr_e('Log in', 'woocommerce'); ?>"><?php esc_html_e('Log in', 'woocommerce'); ?></button>
        </p>

        <p class="woocommerce-LostPassword lost_password">
          <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Lost your password?', 'woocommerce'); ?></a>
        </p>

        <?php do_action('woocommerce_login_form_end'); ?>

      </form>
    </div>

    <?php if ('yes' === get_option('woocommerce_enable_myaccount_registration')) : ?>

    <div class="ng-account-auth-card u-column2 col-2">
      <div class="ng-account-kicker">
        <span class="led on" aria-hidden="true"></span>
        <span>// PROVISION ACCOUNT</span>
      </div>
      <h2 class="ng-account-h2">
        <span class="ar">حساب جديد</span>
        <span class="en">REGISTER</span>
      </h2>

      <form method="post" class="woocommerce-form woocommerce-form-register register ng-account-form" <?php do_action('woocommerce_register_form_tag'); ?>>

        <?php do_action('woocommerce_register_form_start'); ?>

        <?php if ('no' === get_option('woocommerce_registration_generate_username')) : ?>
          <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="reg_username"><?php esc_html_e('Username', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo (!empty($_POST['username'])) ? esc_attr(wp_unslash($_POST['username'])) : ''; ?>" required aria-required="true" />
          </p>
        <?php endif; ?>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
          <label for="reg_email"><?php esc_html_e('Email address', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
          <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo (!empty($_POST['email'])) ? esc_attr(wp_unslash($_POST['email'])) : ''; ?>" required aria-required="true" />
        </p>

        <?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>
          <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="reg_password"><?php esc_html_e('Password', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
          </p>
        <?php else : ?>
          <p><?php esc_html_e('A link to set a new password will be sent to your email address.', 'woocommerce'); ?></p>
        <?php endif; ?>

        <?php do_action('woocommerce_register_form'); ?>

        <p class="woocommerce-form-row form-row">
          <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
          <button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit ng-auth-submit" name="register" value="<?php esc_attr_e('Register', 'woocommerce'); ?>"><?php esc_html_e('Register', 'woocommerce'); ?></button>
        </p>

        <?php do_action('woocommerce_register_form_end'); ?>

      </form>
    </div>

    <?php endif; ?>

  </div>

</section>

<?php do_action('woocommerce_after_customer_login_form'); ?>
