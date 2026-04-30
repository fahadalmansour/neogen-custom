<?php
/**
 * Plugin Name: NeoGen REST Content
 * Description: REST surface for n8n + Ollama-driven AR content backfill (titles, descriptions, imperial→metric). Auth: WP application password tied to a dedicated 'n8n-bot' user with shop_manager role.
 * Version: 1.37.4
 *
 * v1.37.0 — first cut. Routes:
 *   GET  /wp-json/neogen/v1/products?missing=ar_title&limit=20
 *   GET  /wp-json/neogen/v1/products?missing=ar_description&limit=20
 *   POST /wp-json/neogen/v1/products/{id}/ar-title
 *   POST /wp-json/neogen/v1/products/{id}/ar-description
 *
 * Truth-rule guards:
 *   - Every write must include `source` field starting with 'ollama-' or 'manual'.
 *   - Pre-write snapshot stamped to `_ng_<field>_pre_n8n` once per product
 *     (so a single rollback recovers the human-edited / dictionary-extracted
 *     value if n8n overwrites it).
 *   - Audit log: last 100 calls in option `_ng_rest_log`.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {

    register_rest_route( 'neogen/v1', '/products', array(
        'methods'             => 'GET',
        'callback'            => 'ng_rest_list_products',
        'permission_callback' => 'ng_rest_permission_check',
        'args'                => array(
            'missing' => array(
                'required'          => true,
                'type'              => 'string',
                'enum'              => array( 'ar_title', 'ar_description' ),
                'description'       => 'Which content field is missing.',
            ),
            'limit' => array(
                'default'           => 20,
                'type'              => 'integer',
                'minimum'           => 1,
                'maximum'           => 100,
            ),
        ),
    ) );

    register_rest_route( 'neogen/v1', '/products/(?P<id>\d+)/ar-title', array(
        'methods'             => 'POST',
        'callback'            => 'ng_rest_set_ar_title',
        'permission_callback' => 'ng_rest_permission_check',
        'args'                => array(
            'id' => array( 'required' => true, 'type' => 'integer' ),
        ),
    ) );

    register_rest_route( 'neogen/v1', '/products/(?P<id>\d+)/ar-description', array(
        'methods'             => 'POST',
        'callback'            => 'ng_rest_set_ar_description',
        'permission_callback' => 'ng_rest_permission_check',
        'args'                => array(
            'id' => array( 'required' => true, 'type' => 'integer' ),
        ),
    ) );
} );

/**
 * Auth — must be authenticated AND have manage_woocommerce capability.
 * Application passwords on a `shop_manager` user satisfy this.
 */
function ng_rest_permission_check( $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_Error( 'rest_forbidden', 'Authentication required.', array( 'status' => 401 ) );
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return new WP_Error( 'rest_forbidden', 'Insufficient privileges.', array( 'status' => 403 ) );
    }
    return true;
}

/**
 * GET /products?missing=ar_title|ar_description&limit=N.
 */
function ng_rest_list_products( $request ) {
    global $wpdb;
    $missing = $request->get_param( 'missing' );
    $limit   = (int) $request->get_param( 'limit' );

    $meta_key = ( $missing === 'ar_title' ) ? '_ng_ar_title' : '_ng_ar_description';

    $sql = $wpdb->prepare(
        "SELECT p.ID, p.post_title, p.post_content
           FROM {$wpdb->posts} p
          WHERE p.post_type = 'product'
            AND p.post_status IN ('publish','draft')
            AND p.ID NOT IN (
                SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = %s AND meta_value <> ''
            )
          ORDER BY p.ID DESC
          LIMIT %d",
        $meta_key,
        $limit
    );
    $rows = $wpdb->get_results( $sql );

    $items = array();
    foreach ( $rows as $r ) {
        $items[] = array(
            'id'       => (int) $r->ID,
            'title_en' => $r->post_title,
            // Truncate body to 2k chars — enough for description prompts, avoids massive payloads.
            'body_en'  => mb_substr( wp_strip_all_tags( (string) $r->post_content ), 0, 2000 ),
            'sku'      => get_post_meta( $r->ID, '_sku', true ),
        );
    }

    return new WP_REST_Response( array(
        'count' => count( $items ),
        'items' => $items,
    ), 200 );
}

/**
 * POST /products/<id>/ar-title  body: { ar_title, source }
 */
function ng_rest_set_ar_title( $request ) {
    return ng_rest_set_ar_field( $request, 'ar_title', '_ng_ar_title', '_ng_ar_title_source' );
}

/**
 * POST /products/<id>/ar-description  body: { ar_description, source }
 */
function ng_rest_set_ar_description( $request ) {
    return ng_rest_set_ar_field( $request, 'ar_description', '_ng_ar_description', '_ng_ar_description_source' );
}

/**
 * Shared writer for ar_title / ar_description.
 */
function ng_rest_set_ar_field( $request, $field_name, $meta_key, $source_key ) {
    $id   = (int) $request->get_param( 'id' );
    $body = $request->get_json_params() ?: $request->get_body_params();

    $value  = isset( $body[ $field_name ] ) ? trim( (string) $body[ $field_name ] ) : '';
    $source = isset( $body['source'] )      ? trim( (string) $body['source'] )      : '';

    if ( $value === '' ) {
        return new WP_Error( 'invalid_value', "$field_name is empty.", array( 'status' => 400 ) );
    }
    if ( $source === '' || ( strpos( $source, 'ollama-' ) !== 0 && $source !== 'manual' ) ) {
        return new WP_Error(
            'invalid_source',
            "source must be 'manual' or start with 'ollama-' (got: '$source')",
            array( 'status' => 400 )
        );
    }

    // Output sanity: must contain Arabic codepoints.
    if ( ! preg_match( '/[\x{0600}-\x{06FF}]/u', $value ) ) {
        return new WP_Error( 'invalid_value', "$field_name has no Arabic characters.", array( 'status' => 422 ) );
    }
    // v1.37.4: reject script bleed — Cyrillic / Greek / CJK / Hangul /
    // Devanagari. The first AR-content sweep had ~60 products with
    // Cyrillic letters mixed mid-word and Korean / Chinese characters
    // dropped in. This validator stops it at the door for every future
    // write (n8n W1/W2, bulk scripts, manual REST calls alike).
    if ( preg_match( '/[\x{0400}-\x{04FF}\x{0370}-\x{03FF}\x{4E00}-\x{9FFF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}\x{0900}-\x{097F}]/u', $value, $m ) ) {
        return new WP_Error(
            'script_bleed',
            "$field_name contains forbidden script (Cyrillic/Greek/CJK/Hangul/Devanagari). Sample: '" . esc_html( $m[0] ) . "'.",
            array( 'status' => 422 )
        );
    }
    // Strip stray leading "Translation:" / "Sure, here is" fluff that LLMs sometimes prepend.
    $value = preg_replace( '/^(translation\s*:|sure[,!]?\s*[a-z\s]*:|here\s+is[^:]*:)\s*/iu', '', $value );
    $value = trim( $value, " \t\n\r\0\x0B\"'`" );

    $product = get_post( $id );
    if ( ! $product || $product->post_type !== 'product' ) {
        return new WP_Error( 'not_found', 'Product not found.', array( 'status' => 404 ) );
    }

    // v1.37.1 — respect manual edits. If the existing source is 'manual',
    // refuse to overwrite from an LLM source. Manual edits are sacred.
    $existing_source = (string) get_post_meta( $id, $source_key, true );
    if ( $existing_source === 'manual' && strpos( $source, 'ollama-' ) === 0 ) {
        return new WP_Error(
            'manual_lock',
            "Field $field_name is locked to a manual edit; LLM source rejected. Use source=manual to override deliberately.",
            array( 'status' => 409 )
        );
    }

    // Backup current value once so rollback is possible.
    $pre_key = "_ng_{$field_name}_pre_n8n";
    if ( ! get_post_meta( $id, $pre_key, true ) ) {
        $current = (string) get_post_meta( $id, $meta_key, true );
        update_post_meta( $id, $pre_key, $current );
    }

    update_post_meta( $id, $meta_key,    $value );
    update_post_meta( $id, $source_key,  $source );
    update_post_meta( $id, "{$meta_key}_at", current_time( 'c' ) );

    ng_rest_log( $id, $field_name, $source, mb_substr( $value, 0, 80 ) );

    nocache_headers();
    header( 'X-NeoGen-Last-Modified-By: n8n' );

    return new WP_REST_Response( array(
        'id'         => $id,
        'field'      => $field_name,
        'value'      => $value,
        'source'     => $source,
        'snapshot'   => get_post_meta( $id, $pre_key, true ),
        'updated_at' => current_time( 'c' ),
    ), 200 );
}

/**
 * Append to the rolling audit log (last 100 calls).
 */
function ng_rest_log( $id, $field, $source, $excerpt ) {
    $log = (array) get_option( '_ng_rest_log', array() );
    array_unshift( $log, array(
        'ts'      => current_time( 'c' ),
        'pid'     => $id,
        'field'   => $field,
        'source'  => $source,
        'sample'  => $excerpt,
        'user'    => get_current_user_id(),
    ) );
    $log = array_slice( $log, 0, 100 );
    update_option( '_ng_rest_log', $log, false );
}
