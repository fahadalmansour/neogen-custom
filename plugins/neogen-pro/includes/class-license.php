<?php
/**
 * NeoGen Pro — License manager.
 *
 * Validates a per-site license key against the NeoGen licensing API
 * (hosted at neohub.dev/wp-json/neogen-licensing/v1/verify).
 * Results are cached in a 24-hour transient so validation doesn't
 * add a remote call to every page load.
 *
 * Option keys:
 *   neogen_pro_license_key    — the raw key entered by the user
 *   neogen_pro_license_status — 'active' | 'inactive' | 'expired' | 'invalid'
 *   neogen_pro_license_data   — array from last successful API response
 */

defined('ABSPATH') || exit;

class NeoHub_Pro_License {

    private static ?self $instance = null;

    const OPTION_KEY    = 'neogen_pro_license_key';
    const OPTION_STATUS = 'neogen_pro_license_status';
    const OPTION_DATA   = 'neogen_pro_license_data';
    const API_URL       = 'https://neohub.dev/wp-json/neogen-licensing/v1/verify';
    const CACHE_TTL     = DAY_IN_SECONDS;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /** True when the stored status is 'active'. Does not hit the API. */
    public function is_active(): bool {
        return get_option(self::OPTION_STATUS) === 'active';
    }

    /** Return stored license key (may be empty). */
    public function get_key(): string {
        return (string) get_option(self::OPTION_KEY, '');
    }

    /**
     * Activate a key for this site. Hits the licensing API and persists
     * the result. Returns true on success, WP_Error on failure.
     */
    public function activate(string $key): true|\WP_Error {
        $key = sanitize_text_field($key);

        if (empty($key)) {
            return new \WP_Error('empty_key', __('License key cannot be empty.', 'neogen-pro'));
        }

        $response = wp_remote_post(self::API_URL, [
            'timeout' => 15,
            'body'    => [
                'action'  => 'activate',
                'key'     => $key,
                'site'    => home_url(),
                'product' => NEOGEN_PRO_SLUG,
                'version' => NEOGEN_PRO_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || !isset($body['status'])) {
            return new \WP_Error('bad_response', __('Unexpected response from licensing server.', 'neogen-pro'));
        }

        update_option(self::OPTION_KEY,    $key,           false);
        update_option(self::OPTION_STATUS, $body['status'], false);
        update_option(self::OPTION_DATA,   $body,           false);
        delete_transient('neogen_pro_license_cache');

        if ($body['status'] !== 'active') {
            $msg = $body['message'] ?? __('License activation failed.', 'neogen-pro');
            return new \WP_Error('license_' . $body['status'], $msg);
        }

        return true;
    }

    /**
     * Deactivate the current key for this site.
     */
    public function deactivate(): void {
        $key = $this->get_key();
        if ($key) {
            wp_remote_post(self::API_URL, [
                'timeout' => 10,
                'body'    => [
                    'action'  => 'deactivate',
                    'key'     => $key,
                    'site'    => home_url(),
                    'product' => NEOGEN_PRO_SLUG,
                ],
            ]);
        }
        update_option(self::OPTION_STATUS, 'inactive', false);
        delete_transient('neogen_pro_license_cache');
    }

    /**
     * Re-check the stored key against the API (called on daily cron).
     * Silent — only updates stored status.
     */
    public function refresh(): void {
        $key = $this->get_key();
        if (!$key) return;

        $cached = get_transient('neogen_pro_license_cache');
        if ($cached === 'active') return;

        $response = wp_remote_post(self::API_URL, [
            'timeout' => 10,
            'body'    => [
                'action'  => 'verify',
                'key'     => $key,
                'site'    => home_url(),
                'product' => NEOGEN_PRO_SLUG,
            ],
        ]);

        if (is_wp_error($response)) return;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body) || !isset($body['status'])) return;

        update_option(self::OPTION_STATUS, $body['status'], false);
        update_option(self::OPTION_DATA,   $body,           false);

        if ($body['status'] === 'active') {
            set_transient('neogen_pro_license_cache', 'active', self::CACHE_TTL);
        }
    }

    public function on_activate(): void {
        // Schedule daily refresh.
        if (!wp_next_scheduled('neogen_pro_license_refresh')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'neogen_pro_license_refresh');
        }
        add_action('neogen_pro_license_refresh', [$this, 'refresh']);
    }

    public static function admin_notice_inactive(): void {
        $url = admin_url('options-general.php?page=neogen-pro');
        echo '<div class="notice notice-warning"><p>';
        printf(
            /* translators: %s = settings URL */
            wp_kses(__('<strong>NeoGen Pro</strong> requires a valid license key. <a href="%s">Activate your license →</a>', 'neogen-pro'), ['strong' => [], 'a' => ['href' => []]]),
            esc_url($url)
        );
        echo '</p></div>';
    }

    /** Return stored license data array (plan, expiry, seats, etc.) */
    public function get_data(): array {
        return (array) get_option(self::OPTION_DATA, []);
    }
}
