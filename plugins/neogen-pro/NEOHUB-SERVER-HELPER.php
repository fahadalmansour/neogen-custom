<?php
/**
 * NeoHub.dev — Licensing & Update Server Helper
 * 
 * Upload this file to the root of your WordPress installation at neohub.dev
 * (or include it in your theme's functions.php or a custom plugin).
 */

defined('ABSPATH') || exit;

// ── Configuration (Move to wp-config.php for better security) ────────────
if (!defined('NEOHUB_GITHUB_PAT'))   define('NEOHUB_GITHUB_PAT',   'YOUR_GITHUB_PERSONAL_ACCESS_TOKEN');
if (!defined('NEOHUB_GITHUB_REPO'))  define('NEOHUB_GITHUB_REPO',  'your-github-username/neogen-pro');
if (!defined('NEOHUB_GITHUB_OWNER')) define('NEOHUB_GITHUB_OWNER', 'your-github-username');

// ── REST API Endpoints ──────────────────────────────────────────────────
add_action('rest_api_init', function () {
    
    // 1. Verify License Endpoint
    register_rest_route('neogen-licensing/v1', '/verify', [
        'methods'  => 'POST',
        'callback' => 'neohub_handle_license_verify',
        'permission_callback' => '__return_true',
    ]);

    // 2. Update Check Endpoint
    register_rest_route('neogen-licensing/v1', '/update-check', [
        'methods'  => 'POST',
        'callback' => 'neohub_handle_update_check',
        'permission_callback' => '__return_true',
    ]);

    // 3. Download Proxy Endpoint
    register_rest_route('neogen-licensing/v1', '/download', [
        'methods'  => 'GET',
        'callback' => 'neohub_handle_secure_download',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Handle License Verification
 */
function neohub_handle_license_verify($request) {
    $params = $request->get_params();
    $key    = sanitize_text_field($params['key'] ?? '');
    
    // TODO: Connect this to your real license database (e.g. WooCommerce Orders)
    $is_valid = (strpos($key, 'NH-') === 0); // Temporary: NH- keys are valid

    if ($is_valid) {
        return [
            'status'  => 'active',
            'plan'    => 'Pro Suite',
            'expires' => '2027-05-01',
            'message' => 'License is active.'
        ];
    }

    return ['status' => 'invalid', 'message' => 'The license key provided is invalid.'];
}

/**
 * Handle Update Check
 */
function neohub_handle_update_check($request) {
    $params = $request->get_params();
    $key    = sanitize_text_field($params['key'] ?? '');

    // Validate license before returning update info
    if (strpos($key, 'NH-') !== 0) {
        return new WP_Error('unauthorized', 'Valid license required for updates.', ['status' => 403]);
    }

    // Hit GitHub API to get latest release
    $api_url = "https://api.github.com/repos/" . NEOHUB_GITHUB_REPO . "/releases/latest";
    $response = wp_remote_get($api_url, [
        'headers' => [
            'Authorization' => 'token ' . NEOHUB_GITHUB_PAT,
            'User-Agent'    => 'NeoHub-Server'
        ]
    ]);

    if (is_wp_error($response)) return $response;

    $release = json_decode(wp_remote_retrieve_body($response));
    if (empty($release) || !isset($release->tag_name)) {
        return new WP_Error('error', 'Could not fetch release from GitHub.');
    }

    $version = ltrim($release->tag_name, 'v');

    return [
        'new_version' => $version,
        'package'     => rest_url('neogen-licensing/v1/download?key=' . $key), // Secure Proxy URL
        'tested'      => '6.5',
        'requires'    => '6.0',
        'last_updated' => $release->published_at,
        'sections'    => [
            'description'  => $release->body,
            'changelog'    => 'View full details at neohub.dev/changelog',
            'installation' => 'Upload the zip file via WordPress dashboard.'
        ]
    ];
}

/**
 * Secure Download Proxy
 * Fetches the zip from GitHub and streams it to the client.
 */
function neohub_handle_secure_download($request) {
    $key = sanitize_text_field($request->get_param('key'));

    // 1. Final Security Check
    if (strpos($key, 'NH-') !== 0) {
        wp_die('Unauthorized: Invalid License Key.');
    }

    // 2. Get latest release zipball URL from GitHub
    $repo_url = "https://api.github.com/repos/" . NEOHUB_GITHUB_REPO . "/zipball/main";
    
    // 3. Setup redirect/proxy to GitHub zip
    // Note: To be truly secure and hide the PAT, we proxy the body:
    $response = wp_remote_get($repo_url, [
        'timeout' => 300,
        'headers' => [
            'Authorization' => 'token ' . NEOHUB_GITHUB_PAT,
            'User-Agent'    => 'NeoHub-Server'
        ]
    ]);

    if (is_wp_error($response)) wp_die('Download error from GitHub.');

    $zip_content = wp_remote_retrieve_body($response);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="neohub-pro.zip"');
    header('Content-Length: ' . strlen($zip_content));
    echo $zip_content;
    exit;
}
