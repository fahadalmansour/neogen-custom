<?php
/**
 * NeoGen Pro — Premium Updater.
 *
 * Checks for updates against the NeoGen licensing API and handles
 * secure downloads for authorized license holders.
 */

defined('ABSPATH') || exit;

class NeoHub_Pro_Updater {

    private $slug;
    private $plugin_file;
    private $version;
    private $api_url = 'https://neohub.dev/wp-json/neogen-licensing/v1/update-check';

    public function __construct($file, $version) {
        $this->plugin_file = $file;
        $this->slug        = plugin_basename($file);
        $this->version     = $version;

        // Hook into WordPress update lifecycle
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
    }

    /**
     * Check with the NeoGen API for available updates.
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $license = NeoGen_Pro_License::instance();
        if (!$license->is_active()) {
            return $transient;
        }

        $remote = $this->request_api();

        if ($remote && version_compare($this->version, $remote->new_version, '<')) {
            $res = new stdClass();
            $res->slug          = 'neogen-pro';
            $res->plugin        = $this->slug;
            $res->new_version   = $remote->new_version;
            $res->tested        = $remote->tested;
            $res->package       = $remote->package; // Secure download URL from API
            $res->icons         = (array) ($remote->icons ?? []);
            $res->banners       = (array) ($remote->banners ?? []);
            
            $transient->response[$this->slug] = $res;
        }

        return $transient;
    }

    /**
     * Provide details for the plugin information popup.
     */
    public function plugin_info($res, $action, $args) {
        if ($action !== 'plugin_information') return $res;
        if (!isset($args->slug) || $args->slug !== 'neogen-pro') return $res;

        $remote = $this->request_api();
        if (!$remote) return $res;

        $res = new stdClass();
        $res->name           = 'NeoGen Pro';
        $res->slug           = 'neogen-pro';
        $res->version        = $remote->new_version;
        $res->tested         = $remote->tested;
        $res->requires       = $remote->requires;
        $res->author         = '<a href="https://neohub.dev">NeoGen Store</a>';
        $res->homepage       = 'https://neohub.dev/pro/';
        $res->download_link  = $remote->package;
        $res->trunk          = $remote->package;
        $res->last_updated   = $remote->last_updated;
        
        $res->sections = [
            'description'  => $remote->sections->description  ?? '',
            'changelog'    => $remote->sections->changelog    ?? '',
            'installation' => $remote->sections->installation ?? '',
        ];

        return $res;
    }

    /**
     * Perform API request to NeoGen licensing server.
     */
    private function request_api() {
        $license = NeoGen_Pro_License::instance();
        
        $response = wp_remote_post($this->api_url, [
            'timeout' => 15,
            'body'    => [
                'action'  => 'update_check',
                'key'     => $license->get_key(),
                'site'    => home_url(),
                'version' => $this->version,
                'slug'    => 'neogen-pro',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        return is_object($body) ? $body : false;
    }

    /**
     * Fix directory naming after installation if needed.
     */
    public function post_install($true, $hooks, $result) {
        // Standard WP installation usually handles this correctly,
        // but can be used for cleanup if the zip root is named differently.
        return $result;
    }
}
