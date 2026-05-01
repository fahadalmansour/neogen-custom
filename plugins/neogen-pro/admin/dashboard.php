<?php
/**
 * NeoGen Pro — Dashboard Homepage.
 */

defined('ABSPATH') || exit;
if (!current_user_can('manage_options')) wp_die(__('Unauthorized', 'neogen-pro'));

$license = NeoGen_Pro_License::instance();
$is_active = $license->is_active();
$modules = get_option('neogen_pro_modules', ['seo' => true, 'commerce' => true, 'theme' => true]);

?>
<div class="wrap" style="max-width:960px;font-family:system-ui,sans-serif;">
    <div style="display:flex;align-items:center;gap:20px;margin:24px 0 32px;">
        <h1 style="margin:0;font-size:28px;font-weight:800;color:#1A2B4B;">NeoGen Pro Dashboard</h1>
        <span style="font-family:monospace;background:#E2E8F0;padding:4px 10px;border-radius:6px;font-size:12px;">v<?php echo esc_html(NEOGEN_PRO_VERSION); ?></span>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
        <!-- Left Column: Status & Welcome -->
        <div>
            <div style="background:#fff;border-radius:12px;padding:32px;border:1px solid #E2E8F0;margin-bottom:24px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);">
                <h2 style="margin:0 0 16px;font-size:20px;">Welcome to NeoGen Store Suite</h2>
                <p style="color:#64748B;line-height:1.6;font-size:15px;">Your store is now equipped with advanced SEO, performance optimizations, and theme enhancements. Use the modules below to tailor your experience.</p>
                
                <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:16px;margin-top:32px;">
                    <div style="background:#F8FAFC;padding:20px;border-radius:10px;text-align:center;border:1px solid #F1F5F9;">
                        <div style="font-size:11px;text-transform:uppercase;color:#94A3B8;letter-spacing:0.05em;margin-bottom:8px;">License</div>
                        <div style="font-weight:700;color:<?php echo $is_active ? '#22C55E' : '#EF4444'; ?>;">
                            <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                        </div>
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
                        <span>🚀 Run SEO Migration</span>
                    </a>
                    <a href="<?php echo admin_url('options-general.php?page=neogen-seo-sitemap'); ?>" style="text-decoration:none;background:#F1F5F9;padding:16px;border-radius:8px;color:#1A2B4B;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:500;">
                        <span>🗺 View Sitemaps</span>
                    </a>
                    <a href="<?php echo admin_url('options-general.php?page=neogen-coming-soon'); ?>" style="text-decoration:none;background:#F1F5F9;padding:16px;border-radius:8px;color:#1A2B4B;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:500;">
                        <span>🚧 Coming Soon Settings</span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=neogen-pro-settings'); ?>" style="text-decoration:none;background:#F1F5F9;padding:16px;border-radius:8px;color:#1A2B4B;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:500;">
                        <span>⚙️ Manage License</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Column: Info & Resources -->
        <div>
            <div style="background:#1A2B4B;color:#fff;border-radius:12px;padding:28px;margin-bottom:24px;">
                <h3 style="margin:0 0 12px;font-size:16px;color:#fff;">Documentation</h3>
                <p style="font-size:13px;opacity:0.8;line-height:1.5;margin-bottom:20px;">Learn how to make the most of NeoGen Pro features.</p>
                <a href="https://neogen.store/docs/" target="_blank" style="display:inline-block;background:#fff;color:#1A2B4B;text-decoration:none;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;">Browse Docs</a>
            </div>

            <div style="background:#fff;border-radius:12px;padding:28px;border:1px solid #E2E8F0;">
                <h3 style="margin:0 0 16px;font-size:15px;color:#1A2B4B;">Need Support?</h3>
                <p style="font-size:13px;color:#64748B;line-height:1.5;margin-bottom:20px;">Contact our team for technical assistance or custom integration requests.</p>
                <a href="https://wa.me/966XXXXXXXXX" target="_blank" style="display:block;text-align:center;background:#22C55E;color:#fff;text-decoration:none;padding:12px;border-radius:8px;font-size:14px;font-weight:600;">Chat on WhatsApp</a>
            </div>
        </div>
    </div>
</div>
