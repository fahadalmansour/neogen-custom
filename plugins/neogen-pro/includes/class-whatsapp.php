<?php
/**
 * NeoGen Pro — WhatsApp float button.
 * Renders a sticky WhatsApp CTA using the brand phone number from ng_cr().
 */

defined('ABSPATH') || exit;

class NeoGen_Pro_WhatsApp {

    public static function init(): void {
        add_action('wp_footer', [self::class, 'render'], 99);
    }

    public static function render(): void {
        if (!function_exists('ng_cr')) return;
        $cr    = ng_cr();
        $phone = preg_replace('/[^0-9]/', '', $cr['phone_mobile'] ?? '');
        if (!$phone) return;

        $msg = urlencode('مرحباً! أحتاج مساعدة بخصوص طلب من نيوجن ستور.');
        $url = "https://wa.me/{$phone}?text={$msg}";
        ?>
        <a href="<?php echo esc_url($url); ?>"
           class="ng-wa-float"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="تواصل عبر واتساب"
           style="position:fixed;inset-block-end:28px;inset-inline-start:28px;z-index:9999;
                  width:56px;height:56px;border-radius:50%;
                  background:#25D366;box-shadow:0 4px 16px rgba(37,211,102,.45);
                  display:flex;align-items:center;justify-content:center;
                  transition:transform .2s,box-shadow .2s;"
           onmouseover="this.style.transform='scale(1.08)';this.style.boxShadow='0 8px 24px rgba(37,211,102,.55)'"
           onmouseout="this.style.transform='';this.style.boxShadow='0 4px 16px rgba(37,211,102,.45)'">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="#fff" aria-hidden="true">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
            <path d="M12 0C5.373 0 0 5.373 0 12c0 2.124.554 4.122 1.527 5.852L.057 23.7c-.073.39.246.73.636.666l5.951-1.461A11.946 11.946 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.794 9.794 0 01-5.004-1.374l-.359-.214-3.733.917.956-3.641-.233-.374A9.777 9.777 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/>
          </svg>
        </a>
        <?php
    }
}
