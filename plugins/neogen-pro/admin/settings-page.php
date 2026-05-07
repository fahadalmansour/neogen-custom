<?php
/**
 * NeoGen Pro — Admin settings page.
 * License activation, status display, and plugin info.
 */

defined('ABSPATH') || exit;
if (!current_user_can('manage_options')) wp_die(__('Unauthorized', 'neogen-pro'));

$license = NeoHub_Pro_License::instance();
$message = '';
$error   = '';

// Handle form submissions
if (isset($_POST['neogen_pro_action']) && check_admin_referer('neogen_pro_license_nonce')) {
    $action = sanitize_key($_POST['neogen_pro_action']);

    if ($action === 'activate') {
        $key    = sanitize_text_field($_POST['neogen_pro_key'] ?? '');
        $result = $license->activate($key);
        if (is_wp_error($result)) {
            $error = $result->get_error_message();
        } else {
            $message = __('License activated successfully.', 'neogen-pro');
        }

    } elseif ($action === 'deactivate') {
        $license->deactivate();
        $message = __('License deactivated. Features are now disabled.', 'neogen-pro');
    } elseif ($action === 'update_modules') {
        $modules = [
            'seo'      => isset($_POST['module_seo']),
            'commerce' => isset($_POST['module_commerce']),
            'theme'    => isset($_POST['module_theme']),
        ];
        update_option('neogen_pro_modules', $modules);
        $message = __('Module settings updated.', 'neogen-pro');
    }
}

$status    = get_option(NeoHub_Pro_License::OPTION_STATUS, 'inactive');
$key       = $license->get_key();
$data      = $license->get_data();
$is_active = $license->is_active();
$modules   = get_option('neogen_pro_modules', ['seo' => true, 'commerce' => true, 'theme' => true]);

$status_color = match($status) {
    'active'   => '#22C55E',
    'expired'  => '#F59E0B',
    default    => '#EF4444',
};
$status_label = match($status) {
    'active'   => 'نشط · Active',
    'expired'  => 'منتهي · Expired',
    'invalid'  => 'غير صالح · Invalid',
    default    => 'غير مفعّل · Inactive',
};
?>
<div class="wrap" style="max-width:720px;font-family:system-ui,sans-serif;">

  <div style="display:flex;align-items:center;gap:16px;margin-bottom:32px;padding-bottom:20px;border-bottom:1px solid #e2e8f0;">
    <div style="font-weight:700;font-size:22px;color:#1A2B4B;">NeoGen Pro</div>
    <span style="font-family:monospace;font-size:11px;padding:3px 8px;border:1px solid #e2e8f0;border-radius:4px;color:#64748B;">v<?php echo esc_html(NEOGEN_PRO_VERSION); ?></span>
    <span style="margin-inline-start:auto;font-family:monospace;font-size:12px;padding:4px 10px;border-radius:4px;background:<?php echo esc_attr($status_color); ?>22;color:<?php echo esc_attr($status_color); ?>;border:1px solid <?php echo esc_attr($status_color); ?>44;">
      ● <?php echo esc_html($status_label); ?>
    </span>
  </div>

  <?php if ($message) : ?>
    <div class="notice notice-success inline" style="margin-bottom:20px;"><p><?php echo esc_html($message); ?></p></div>
  <?php endif; ?>
  <?php if ($error) : ?>
    <div class="notice notice-error inline" style="margin-bottom:20px;"><p><?php echo esc_html($error); ?></p></div>
  <?php endif; ?>

  <!-- License activation form -->
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:28px;margin-bottom:24px;">
    <h2 style="margin:0 0 20px;font-size:16px;color:#1A2B4B;">
      <?php $is_active ? esc_html_e('License Details', 'neogen-pro') : esc_html_e('Activate License', 'neogen-pro'); ?>
    </h2>

    <?php if ($is_active) : ?>
      <!-- Active state — show info + deactivate button -->
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <?php
        $rows = [
          'Plan'    => $data['plan']     ?? '—',
          'Expires' => $data['expires']  ?? '—',
          'Site'    => home_url(),
          'Key'     => substr($key, 0, 8) . str_repeat('•', max(0, strlen($key) - 12)) . substr($key, -4),
        ];
        foreach ($rows as $label => $val) : ?>
        <tr style="border-bottom:1px solid #f1f5f9;">
          <td style="padding:10px 0;color:#64748B;font-family:monospace;font-size:11px;text-transform:uppercase;letter-spacing:0.06em;width:120px;"><?php echo esc_html($label); ?></td>
          <td style="padding:10px 0;color:#1A2B4B;font-weight:600;"><?php echo esc_html($val); ?></td>
        </tr>
        <?php endforeach; ?>
      </table>

      <form method="post" style="margin-top:20px;">
        <?php wp_nonce_field('neogen_pro_license_nonce'); ?>
        <input type="hidden" name="neogen_pro_action" value="deactivate">
        <button type="submit" class="button button-secondary"
                onclick="return confirm('<?php esc_attr_e('Deactivate this license for this site?', 'neogen-pro'); ?>')">
          <?php esc_html_e('Deactivate License', 'neogen-pro'); ?>
        </button>
      </form>

    <?php else : ?>
      <!-- Inactive state — activation form -->
      <p style="color:#64748B;font-size:13px;margin:0 0 16px;">
        <?php esc_html_e('Enter your NeoGen Pro license key to unlock all features.', 'neogen-pro'); ?>
        <a href="https://neohub.dev/pricing/" target="_blank" rel="noopener" style="color:#0284C7;">
          <?php esc_html_e('Purchase a license →', 'neogen-pro'); ?>
        </a>
      </p>
      <form method="post">
        <?php wp_nonce_field('neogen_pro_license_nonce'); ?>
        <input type="hidden" name="neogen_pro_action" value="activate">
        <div style="display:flex;gap:8px;">
          <input type="text" name="neogen_pro_key"
                 value="<?php echo esc_attr($key); ?>"
                 placeholder="NGPRO-XXXX-XXXX-XXXX-XXXX"
                 style="flex:1;font-family:monospace;padding:8px 12px;border:1px solid #e2e8f0;border-radius:4px;font-size:13px;letter-spacing:0.04em;"
                 required>
          <button type="submit" class="button button-primary">
            <?php esc_html_e('Activate', 'neogen-pro'); ?>
          </button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($is_active) : ?>
  <!-- Modules Configuration -->
  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:28px;margin-bottom:24px;">
    <h2 style="margin:0 0 20px;font-size:16px;color:#1A2B4B;"><?php esc_html_e('Module Configuration', 'neogen-pro'); ?></h2>
    <form method="post">
      <?php wp_nonce_field('neogen_pro_license_nonce'); ?>
      <input type="hidden" name="neogen_pro_action" value="update_modules">
      
      <div style="display:flex;flex-direction:column;gap:16px;margin-bottom:24px;">
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
          <input type="checkbox" name="module_seo" <?php checked($modules['seo'] ?? true); ?> style="margin-top:4px;">
          <div>
            <div style="font-weight:600;color:#1A2B4B;"><?php esc_html_e('SEO Suite', 'neogen-pro'); ?></div>
            <div style="font-size:12px;color:#64748B;"><?php esc_html_e('Advanced SEO engine, automated sitemaps, and content meta-boxes.', 'neogen-pro'); ?></div>
          </div>
        </label>

        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
          <input type="checkbox" name="module_commerce" <?php checked($modules['commerce'] ?? true); ?> style="margin-top:4px;">
          <div>
            <div style="font-weight:600;color:#1A2B4B;"><?php esc_html_e('Commerce Enhancements', 'neogen-pro'); ?></div>
            <div style="font-size:12px;color:#64748B;"><?php esc_html_e('Gift cards, product videos, merchant tools, and recommendations.', 'neogen-pro'); ?></div>
          </div>
        </label>

        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
          <input type="checkbox" name="module_theme" <?php checked($modules['theme'] ?? true); ?> style="margin-top:4px;">
          <div>
            <div style="font-weight:600;color:#1A2B4B;"><?php esc_html_e('Theme Features', 'neogen-pro'); ?></div>
            <div style="font-size:12px;color:#64748B;"><?php esc_html_e('A/B testing, coming soon mode, brand imagery, and custom templates.', 'neogen-pro'); ?></div>
          </div>
        </label>
      </div>

      <button type="submit" class="button button-primary">
        <?php esc_html_e('Save Module Settings', 'neogen-pro'); ?>
      </button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Features list -->
  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:24px;">
    <h3 style="margin:0 0 16px;font-size:14px;color:#1A2B4B;font-family:monospace;text-transform:uppercase;letter-spacing:0.08em;">Included Features</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
      <?php foreach ([
        'Brand CSS tokens (neogen.css)',
        'WooCommerce shop/archive template',
        'Product card (bilingual AR/EN)',
        'Single product (PDP) template',
        'Cart + checkout templates',
        'My Account templates',
        'Info pages: about, contact, FAQ',
        'Shipping, returns, warranty, terms',
        'WhatsApp float button',
        'RTL/LTR bilingual layout',
        'Blocksy child theme overrides',
        'WooCommerce HPOS compatible',
      ] as $f) : ?>
        <div style="font-size:12px;color:#334155;display:flex;align-items:center;gap:8px;">
          <?php echo function_exists('ng_icon') ? ng_icon('check', 14) : ''; ?>
          <span><?php echo esc_html($f); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>
