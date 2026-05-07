<?php
/**
 * NeoGen Pro — Dashboard Homepage.
 */

defined('ABSPATH') || exit;
if (!current_user_can('manage_options')) wp_die(__('Unauthorized', 'neogen-pro'));

$license = NeoHub_Pro_License::instance();
$message = '';
$error   = '';

// Handle activation directly from dashboard
if (isset($_POST['neogen_pro_action']) && $_POST['neogen_pro_action'] === 'activate' && check_admin_referer('neogen_pro_dashboard_nonce')) {
    $key    = sanitize_text_field($_POST['neogen_pro_key'] ?? '');
    $result = $license->activate($key);
    if (is_wp_error($result)) {
        $error = $result->get_error_message();
    } else {
        $message = __('License activated successfully.', 'neogen-pro');
    }
}

$is_active = $license->is_active();
$modules = get_option('neogen_pro_modules', ['seo' => true, 'commerce' => true, 'theme' => true]);
$key = $license->get_key();

?>
<div class="wrap" style="max-width:960px;font-family:system-ui,sans-serif;">
    <div style="display:flex;align-items:center;gap:20px;margin:24px 0 32px;">
        <h1 style="margin:0;font-size:28px;font-weight:800;color:#1A2B4B;">NeoHub Pro Dashboard</h1>
        <span style="font-family:monospace;background:#E2E8F0;padding:4px 10px;border-radius:6px;font-size:12px;">v<?php echo esc_html(NEOGEN_PRO_VERSION); ?></span>
    </div>

    <?php if ($message) : ?>
        <div class="notice notice-success inline" style="margin-bottom:20px; border-radius:8px;"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>
    <?php if ($error) : ?>
        <div class="notice notice-error inline" style="margin-bottom:20px; border-radius:8px;"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <?php if (!$is_active) : ?>
        <!-- LOCKED STATE: PROMINENT ACTIVATION PROMPT -->
        <div style="background: linear-gradient(135deg, #1A2B4B 0%, #10192C 100%); border-radius: 16px; padding: 60px 40px; text-align: center; color: #fff; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);">
            <div style="margin-bottom: 24px;"><?php echo function_exists('ng_icon') ? ng_icon('lock', 48) : ''; ?></div>
            <h2 style="color: #fff; font-size: 28px; font-weight: 700; margin: 0 0 16px;"><?php esc_html_e('Enter your NeoGen Pro license key to unlock all features', 'neogen-pro'); ?></h2>
            <p style="color: #94A3B8; font-size: 16px; max-width: 500px; margin: 0 auto 32px; line-height: 1.6;">
                <?php esc_html_e('Unlock advanced SEO, theme customizer, bilingual product cards, and premium updates.', 'neogen-pro'); ?>
            </p>

            <form method="post" style="max-width: 500px; margin: 0 auto; display: flex; gap: 12px;">
                <?php wp_nonce_field('neogen_pro_dashboard_nonce'); ?>
                <input type="hidden" name="neogen_pro_action" value="activate">
                <input type="text" name="neogen_pro_key"
                       value="<?php echo esc_attr($key); ?>"
                       placeholder="NGPRO-XXXX-XXXX-XXXX-XXXX"
                       style="flex: 1; font-family: monospace; padding: 14px 18px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #fff; border-radius: 8px; font-size: 15px; outline: none;"
                       required>
                <button type="submit" class="button button-primary" style="padding: 10px 24px; height: auto; font-size: 15px; font-weight: 600; background: #0284C7; border: none; border-radius: 8px;">
                    <?php esc_html_e('Activate Now', 'neogen-pro'); ?>
                </button>
            </form>
            
            <div style="margin-top: 24px; font-size: 14px; color: #64748B;">
                <?php esc_html_e('Don\'t have a key?', 'neogen-pro'); ?> 
                <a href="https://neohub.dev/pricing/" target="_blank" style="color: #0284C7; text-decoration: none; font-weight: 600;"><?php esc_html_e('Purchase one here →', 'neogen-pro'); ?></a>
            </div>
        </div>
    <?php else : ?>
        <!-- ACTIVE STATE: DASHBOARD CONTENT -->
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
            <!-- Left Column: Status & Welcome -->
            <div>
                <div style="background:#fff;border-radius:12px;padding:32px;border:1px solid #E2E8F0;margin-bottom:24px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);">
                    <h2 style="margin:0 0 16px;font-size:20px;">Welcome to NeoGen Store Suite</h2>
                    <p style="color:#64748B;line-height:1.6;font-size:15px;">Your store is now equipped with advanced SEO, performance optimizations, and theme enhancements. Use the modules below to tailor your experience.</p>
                    
                    <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;margin-top:32px;">
                        <div style="background:#F8FAFC;padding:20px;border-radius:10px;text-align:center;border:1px solid #F1F5F9;">
                            <div style="font-size:11px;text-transform:uppercase;color:#94A3B8;letter-spacing:0.05em;margin-bottom:8px;">License</div>
                            <div style="font-weight:700;color:#22C55E;">Active</div>
                        </div>
                        <div style="background:#F8FAFC;padding:20px;border-radius:10px;text-align:center;border:1px solid #F1F5F9;">
                            <div style="font-size:11px;text-transform:uppercase;color:#94A3B8;letter-spacing:0.05em;margin-bottom:8px;">SEO Suite</div>
                            <div style="font-weight:700;"><?php echo !empty($modules['seo']) ? 'Enabled' : 'Disabled'; ?></div>
                        </div>
                        <div style="background:#F8FAFC;padding:20px;border-radius:10px;text-align:center;border:1px solid #F1F5F9;">
                            <div style="font-size:11px;text-transform:uppercase;color:#94A3B8;letter-spacing:0.05em;margin-bottom:8px;">Theme Suite</div>
                            <div style="font-weight:700;"><?php echo !empty($modules['theme']) ? 'Enabled' : 'Disabled'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- News / Quick Actions -->
                <div style="background:#fff;border-radius:12px;padding:32px;border:1px solid #E2E8F0;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);">
                    <h3 style="margin:0 0 20px;font-size:16px;color:#1A2B4B;">Quick Actions</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <a href="<?php echo admin_url('options-general.php?page=neogen-seo-migration'); ?>" style="text-decoration:none;background:#F1F5F9;padding:16px;border-radius:8px;color:#1A2B4B;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:500;">
                            <span>Run SEO Migration</span>
                        </a>
                        <a href="<?php echo admin_url('options-general.php?page=neogen-seo-sitemap'); ?>" style="text-decoration:none;background:#F1F5F9;padding:16px;border-radius:8px;color:#1A2B4B;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:500;">
                            <span>View Sitemaps</span>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=neogen-coming-soon'); ?>" style="text-decoration:none;background:#F1F5F9;padding:16px;border-radius:8px;color:#1A2B4B;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:500;">
                            <span>Coming Soon Settings</span>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=neogen-pro-settings'); ?>" style="text-decoration:none;background:#F1F5F9;padding:16px;border-radius:8px;color:#1A2B4B;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:500;">
                            <span>Manage License</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column: Info & Resources -->
            <div>
                <div style="background:#1A2B4B;color:#fff;border-radius:12px;padding:28px;margin-bottom:24px;">
                    <h3 style="margin:0 0 12px;font-size:16px;color:#fff;">Documentation</h3>
                    <p style="font-size:13px;opacity:0.8;line-height:1.5;margin-bottom:20px;">Learn how to make the most of NeoGen Pro features.</p>
                    <a href="https://neohub.dev/docs/" target="_blank" style="display:inline-block;background:#fff;color:#1A2B4B;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;">Browse Docs</a>
                </div>

                <div style="background:#fff;border-radius:12px;padding:28px;border:1px solid #E2E8F0;">
                    <h3 style="margin:0 0 16px;font-size:15px;color:#1A2B4B;">Need Support?</h3>
                    <p style="font-size:13px;color:#64748B;line-height:1.5;margin-bottom:20px;">Contact our team for technical assistance or custom integration requests.</p>
                    <a href="https://wa.me/966XXXXXXXXX" target="_blank" style="display:block;text-align:center;background:#22C55E;color:#fff;text-decoration:none;padding:12px;border-radius:8px;font-size:14px;font-weight:600;">Chat on WhatsApp</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
