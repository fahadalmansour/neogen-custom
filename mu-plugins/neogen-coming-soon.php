<?php
/**
 * Plugin Name: NeoGen Coming Soon
 * Description: Toggleable storefront-wide coming-soon / maintenance page. Shows a branded operator-console holding page to public visitors; admins (and editors+) keep full access. Bypasses /wp-admin, /wp-login, REST API, AJAX, sitemaps, llms.txt, ads.txt, robots.txt, and cron so SEO/ops never breaks.
 * Version: 1.20.6
 * Author: Fahad Almansour
 *
 * Toggle: Tools → NeoGen Coming Soon
 * Option keys:
 *   ng_coming_soon_enabled   '1' or '' (off)
 *   ng_coming_soon_launch_at ISO-8601 string (optional countdown target)
 *   ng_coming_soon_message   custom AR message (optional)
 *
 * The holding page is rendered inline (no theme template needed) so it
 * stays online even if the active theme breaks. Sends 503 + Retry-After
 * so search crawlers don't index the holding page as the real site.
 */

defined('ABSPATH') || exit;

/* ---------------------------------------------------------------
 * Admin: Tools → NeoGen Coming Soon
 * ------------------------------------------------------------- */

add_action('admin_menu', function () {
    add_management_page(
        'NeoGen Coming Soon',
        'NeoGen Coming Soon',
        'manage_options',
        'neogen-coming-soon',
        'ng_coming_soon_admin_render'
    );
});

function ng_coming_soon_admin_render() {
    if (!current_user_can('manage_options')) wp_die('forbidden');

    if (isset($_POST['ng_coming_soon_save']) && check_admin_referer('ng_coming_soon_save')) {
        $enabled   = isset($_POST['ng_cs_enabled']) ? '1' : '';
        $launch_at = isset($_POST['ng_cs_launch_at']) ? sanitize_text_field(wp_unslash($_POST['ng_cs_launch_at'])) : '';
        $message   = isset($_POST['ng_cs_message']) ? wp_kses_post(wp_unslash($_POST['ng_cs_message'])) : '';
        update_option('ng_coming_soon_enabled',   $enabled,   false);
        update_option('ng_coming_soon_launch_at', $launch_at, false);
        update_option('ng_coming_soon_message',   $message,   false);
        echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
    }

    $enabled   = (string) get_option('ng_coming_soon_enabled', '');
    $launch_at = (string) get_option('ng_coming_soon_launch_at', '');
    $message   = (string) get_option('ng_coming_soon_message', '');
    ?>
    <div class="wrap">
      <h1>NeoGen Coming Soon</h1>
      <p>
        When enabled, public visitors see a branded holding page (HTTP 503).
        Admins and editors keep full storefront access. Search-engine and
        deploy hooks (<code>/wp-admin</code>, REST, AJAX, sitemaps,
        <code>/llms.txt</code>, <code>/ads.txt</code>, <code>/robots.txt</code>, cron)
        always bypass.
      </p>

      <form method="post">
        <?php wp_nonce_field('ng_coming_soon_save'); ?>
        <input type="hidden" name="ng_coming_soon_save" value="1">

        <table class="form-table" role="presentation">
          <tbody>
            <tr>
              <th scope="row">
                <label for="ng_cs_enabled">Enable coming-soon mode</label>
              </th>
              <td>
                <label>
                  <input type="checkbox" id="ng_cs_enabled" name="ng_cs_enabled" value="1" <?php checked($enabled, '1'); ?>>
                  Show holding page to public visitors
                </label>
                <?php if ($enabled === '1') : ?>
                  <p style="color:#b32d2e;font-weight:600;margin-top:6px;"><strong>Warning:</strong> Currently LIVE on the public storefront.</p>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label for="ng_cs_launch_at">Launch at (optional)</label>
              </th>
              <td>
                <input type="datetime-local" id="ng_cs_launch_at" name="ng_cs_launch_at"
                       value="<?php echo esc_attr($launch_at); ?>"
                       style="width:280px;">
                <p class="description">If set, the holding page shows a live countdown to this date/time (browser local time).</p>
              </td>
            </tr>
            <tr>
              <th scope="row">
                <label for="ng_cs_message">Custom Arabic message (optional)</label>
              </th>
              <td>
                <textarea id="ng_cs_message" name="ng_cs_message" rows="3"
                          style="width:100%;max-width:560px;direction:rtl;text-align:right;font-family:'Tajawal',sans-serif;"
                          placeholder="مثال: نعمل على إطلاق التحديث الكبير. سنعود قريبًا."><?php echo esc_textarea($message); ?></textarea>
                <p class="description">Falls back to a default Arabic line if empty.</p>
              </td>
            </tr>
          </tbody>
        </table>

        <?php submit_button('Save'); ?>
      </form>

      <hr>
      <h2>Preview the holding page</h2>
      <p>Open the storefront in an incognito window — admins always see the live site, public visitors will see the holding page when enabled.</p>
      <p><a class="button button-secondary" href="<?php echo esc_url(home_url('/?ng_coming_soon_preview=1')); ?>" target="_blank" rel="noopener">Preview holding page</a> (works for admins regardless of toggle state)</p>
    </div>
    <?php
}

/* ---------------------------------------------------------------
 * Front-end gate
 * ------------------------------------------------------------- */

/**
 * Decide whether the current request should be served the holding
 * page. Bypasses every operational endpoint so search/deploy/admin
 * never breaks even when coming-soon is on.
 */
function ng_coming_soon_should_show() {
    // Admin preview override — always shows holding page for admins.
    if (isset($_GET['ng_coming_soon_preview']) && current_user_can('manage_options')) {
        return true;
    }

    // Toggle off → never show.
    if (get_option('ng_coming_soon_enabled') !== '1') return false;

    // Logged-in editors+ get the live site.
    if (is_user_logged_in() && current_user_can('edit_posts')) return false;

    // Bypass admin, login, REST, AJAX, cron, file requests.
    if (is_admin()) return false;
    if (defined('DOING_AJAX')   && DOING_AJAX)   return false;
    if (defined('DOING_CRON')   && DOING_CRON)   return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    if (defined('WP_CLI')       && WP_CLI)       return false;

    $req = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $path = strtok($req, '?');
    if ($path === false || $path === '') return false;

    // Explicit bypass list — anything search/deploy/ops needs.
    $bypass_prefixes = [
        '/wp-admin',
        '/wp-login',
        '/wp-cron.php',
        '/wp-json',
        '/feed/',           // RSS + the merchant feed
        '/sitemap',         // sitemap.xml + sitemap_index.xml
        '/robots.txt',
        '/llms.txt',
        '/ads.txt',
        '/.well-known',
        '/wc-api',
        '/wp-content',      // direct asset hits — never gate static files
        '/wp-includes',
    ];
    foreach ($bypass_prefixes as $p) {
        if (strpos($path, $p) === 0) return false;
    }

    return true;
}

add_action('template_redirect', function () {
    if (!ng_coming_soon_should_show()) return;
    ng_coming_soon_render();
    exit;
}, 0);

/**
 * Inline-render the holding page. No theme template, no queries —
 * stays online even if the catalog/theme is mid-break.
 */
function ng_coming_soon_render() {
    nocache_headers();
    status_header(503);
    header('Retry-After: 86400');                  // 24h hint for crawlers
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');     // don't index holding page

    $launch_at = (string) get_option('ng_coming_soon_launch_at', '');
    $message   = (string) get_option('ng_coming_soon_message', '');
    $message   = trim($message) !== '' ? $message : 'نعمل على إطلاق نسخة جديدة من المتجر. سنعود قريبًا.';

    $base = defined('NG_THEME_ASSET_URL')
        ? NG_THEME_ASSET_URL
        : content_url('/mu-plugins/neogen-custom/neogen-theme-assets');

    $logo_url   = $base . '/img/logo/ng-mark.png';
    $favicon_ico = $base . '/icons/favicon.ico';
    $favicon_svg = $base . '/icons/favicon.svg';
    $apple_icon  = $base . '/icons/apple-touch-icon.png';

    $cr = function_exists('ng_cr') ? ng_cr() : ['cr' => '7053130576', 'phone_mobile' => '+966570131122', 'email' => 'contact@neogen.store'];
    $tel    = preg_replace('/\D/', '', (string) ($cr['phone_mobile'] ?? ''));
    $wa_url = $tel !== '' ? 'https://wa.me/' . $tel : '';
    $email  = (string) ($cr['email'] ?? 'contact@neogen.store');

    ?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>قريبًا · NEOGEN STORE</title>
<meta name="description" content="<?php echo esc_attr($message); ?>">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#38BDF8">
<link rel="icon" href="<?php echo esc_url($favicon_ico); ?>" sizes="any">
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url($favicon_svg); ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url($apple_icon); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;700&family=IBM+Plex+Mono:wght@400;500&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body {
    min-height: 100vh;
    background: #F8FAFC;
    color: #0F172A;
    font-family: 'Tajawal', system-ui, sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background-image:
      radial-gradient(circle at 20% 0%, rgba(56,189,248,0.08), transparent 50%),
      radial-gradient(circle at 80% 100%, rgba(56,189,248,0.06), transparent 60%);
  }
  .ng-cs-grid {
    position: fixed;
    inset: 0;
    pointer-events: none;
    background-image:
      linear-gradient(rgba(56,189,248,0.05) 1px, transparent 1px),
      linear-gradient(90deg, rgba(56,189,248,0.05) 1px, transparent 1px);
    background-size: 40px 40px;
    z-index: 0;
  }
  .ng-cs-card {
    position: relative;
    z-index: 1;
    max-width: 720px;
    width: 100%;
    background: #ffffff;
    border: 1px solid #38BDF8;
    border-radius: 8px;
    padding: 56px 44px;
    box-shadow: 0 30px 60px rgba(15,23,42,0.06);
  }
  .ng-cs-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.18em;
    color: #38BDF8;
    text-transform: uppercase;
    margin-bottom: 22px;
  }
  .ng-cs-kicker .led {
    width: 7px; height: 7px; border-radius: 50%;
    background: #38BDF8;
    box-shadow: 0 0 10px #38BDF8;
    animation: ng-cs-pulse 2.4s ease-in-out infinite;
  }
  @keyframes ng-cs-pulse {
    0%,100% { opacity: 1; }
    50%     { opacity: 0.35; }
  }
  .ng-cs-mark {
    display: block;
    width: 110px;
    height: auto;
    margin-bottom: 28px;
  }
  .ng-cs-h1 {
    font-family: 'Tajawal', sans-serif;
    font-size: 44px;
    font-weight: 700;
    line-height: 1.2;
    margin: 0 0 8px;
    color: #0F172A;
  }
  .ng-cs-h1 .accent { color: #38BDF8; }
  .ng-cs-en {
    font-family: 'Chakra Petch', sans-serif;
    font-size: 14px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #334155;
    margin-bottom: 22px;
  }
  .ng-cs-en .neo { color: #0F172A; font-weight: 300; }
  .ng-cs-en .gen { color: #38BDF8; font-weight: 700; }
  .ng-cs-msg {
    font-size: 18px;
    line-height: 1.7;
    color: #334155;
    margin: 0 0 28px;
  }
  .ng-cs-countdown {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 0 0 28px;
    direction: ltr;
  }
  .ng-cs-cell {
    flex: 1;
    min-width: 70px;
    padding: 14px 8px;
    background: #EEF2F6;
    border: 1px solid #CBD5E1;
    border-radius: 4px;
    text-align: center;
  }
  .ng-cs-num {
    font-family: 'Chakra Petch', sans-serif;
    font-size: 32px;
    font-weight: 700;
    color: #38BDF8;
    line-height: 1;
  }
  .ng-cs-lbl {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #334155;
    margin-top: 6px;
  }
  .ng-cs-cta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
  }
  .ng-cs-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 4px;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-decoration: none;
    transition: transform 0.2s ease;
  }
  .ng-cs-btn:hover { transform: translateY(-1px); }
  .ng-cs-btn-pri {
    background: #38BDF8;
    color: #ffffff;
  }
  .ng-cs-btn-ghost {
    background: transparent;
    color: #0F172A;
    border: 1px solid #0F172A;
  }
  .ng-cs-foot {
    padding-top: 24px;
    border-top: 1px solid #EEF2F6;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #6a6a6a;
  }
  .ng-cs-foot b { color: #0F172A; }
  @media (max-width: 560px) {
    .ng-cs-card { padding: 36px 24px; }
    .ng-cs-h1 { font-size: 32px; }
    .ng-cs-num { font-size: 26px; }
  }
</style>
</head>
<body>
<div class="ng-cs-grid" aria-hidden="true"></div>

<main class="ng-cs-card" role="main">

  <div class="ng-cs-kicker">
    <span class="led" aria-hidden="true"></span>
    <span>SYSTEM · MAINTENANCE</span>
  </div>

  <img class="ng-cs-mark" src="<?php echo esc_url($logo_url); ?>" alt="NEOGEN STORE" decoding="async">

  <h1 class="ng-cs-h1">قريبًا · <span class="accent">NEOGEN</span>.</h1>

  <div class="ng-cs-en">
    <span class="neo">NEO</span><span class="gen">GEN</span> &middot; STORE &middot; KSA
  </div>

  <p class="ng-cs-msg"><?php echo wp_kses_post($message); ?></p>

  <?php if ($launch_at !== '') : ?>
  <div class="ng-cs-countdown" aria-label="Countdown" data-launch="<?php echo esc_attr($launch_at); ?>">
    <div class="ng-cs-cell"><div class="ng-cs-num" data-d>--</div><div class="ng-cs-lbl">DAYS</div></div>
    <div class="ng-cs-cell"><div class="ng-cs-num" data-h>--</div><div class="ng-cs-lbl">HRS</div></div>
    <div class="ng-cs-cell"><div class="ng-cs-num" data-m>--</div><div class="ng-cs-lbl">MIN</div></div>
    <div class="ng-cs-cell"><div class="ng-cs-num" data-s>--</div><div class="ng-cs-lbl">SEC</div></div>
  </div>
  <script>
  (function() {
    var c = document.querySelector('.ng-cs-countdown');
    if (!c) return;
    var target = new Date(c.getAttribute('data-launch'));
    if (isNaN(target.getTime())) return;
    var d = c.querySelector('[data-d]'), h = c.querySelector('[data-h]'),
        m = c.querySelector('[data-m]'), s = c.querySelector('[data-s]');
    function pad(n){ return (n < 10 ? '0' : '') + n; }
    function tick() {
      var diff = target - new Date();
      if (diff <= 0) { d.textContent='00';h.textContent='00';m.textContent='00';s.textContent='00';return; }
      var sec = Math.floor(diff/1000);
      d.textContent = pad(Math.floor(sec/86400));
      h.textContent = pad(Math.floor((sec%86400)/3600));
      m.textContent = pad(Math.floor((sec%3600)/60));
      s.textContent = pad(sec%60);
    }
    tick(); setInterval(tick, 1000);
  })();
  </script>
  <?php endif; ?>

  <div class="ng-cs-cta">
    <a class="ng-cs-btn ng-cs-btn-pri" href="mailto:<?php echo esc_attr($email); ?>?subject=NeoGen%20launch%20notification">
      اشترك للإشعار &rsaquo;
    </a>
    <?php if ($wa_url !== '') : ?>
    <a class="ng-cs-btn ng-cs-btn-ghost" href="<?php echo esc_url($wa_url); ?>" target="_blank" rel="noopener noreferrer">
      تواصل عبر واتساب
    </a>
    <?php endif; ?>
  </div>

  <div class="ng-cs-foot">
    <span>سجل تجاري <b><?php echo esc_html($cr['cr'] ?? '7053130576'); ?></b></span>
    <span>الرياض · جدة · الدمام</span>
    <span>NEOGEN.STORE</span>
  </div>

</main>
</body>
</html>
    <?php
}

/* ---------------------------------------------------------------
 * Admin bar — show a red badge when coming-soon is enabled so we
 * never forget it's on.
 * ------------------------------------------------------------- */

add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;
    if (get_option('ng_coming_soon_enabled') !== '1') return;
    $wp_admin_bar->add_node([
        'id'    => 'neogen-coming-soon-on',
        'title' => 'COMING-SOON ON',
        'href'  => admin_url('tools.php?page=neogen-coming-soon'),
        'meta'  => [
            'title' => 'NeoGen coming-soon mode is currently shown to public visitors',
            'class' => 'ng-cs-badge-on',
        ],
    ]);
}, 110);

add_action('admin_head', function () {
    if (get_option('ng_coming_soon_enabled') !== '1') return;
    echo '<style>#wpadminbar .ng-cs-badge-on > .ab-item { background: #b32d2e !important; color: #fff !important; }</style>';
});

add_action('wp_head', function () {
    if (!is_admin_bar_showing()) return;
    if (get_option('ng_coming_soon_enabled') !== '1') return;
    if (!current_user_can('manage_options')) return;
    echo '<style>#wpadminbar .ng-cs-badge-on > .ab-item { background: #b32d2e !important; color: #fff !important; }</style>';
});
