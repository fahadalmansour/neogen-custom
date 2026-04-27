<?php
/**
 * Plugin Name: NeoGen Product Video
 * Description: Per-product video field on the WC product edit screen, rendered above the single-product summary on the storefront. Supports YouTube, Vimeo, and self-hosted mp4/webm. Optional poster image.
 * Version: 1.0.0
 * Author: Fahad Almansour
 *
 * Meta keys:
 *   _ng_product_video_url    — full URL (YouTube/Vimeo watch URL OR direct mp4/webm)
 *   _ng_product_video_poster — full URL to poster image (optional, used only for self-hosted video)
 *
 * Bulk import: drop "Meta: _ng_product_video_url" and "Meta: _ng_product_video_poster"
 * columns into your WooCommerce CSV import. WC's importer treats meta columns
 * automatically — no extra registration needed.
 */

defined('ABSPATH') || exit;

/* ---------------------------------------------------------------------
 * Admin meta box on the product edit screen
 * ------------------------------------------------------------------- */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'ng-product-video',
        'NeoGen — فيديو المنتج / Product video',
        'ng_product_video_meta_box',
        'product',
        'side',
        'default'
    );
});

function ng_product_video_meta_box($post) {
    wp_nonce_field('ng_product_video_save', 'ng_product_video_nonce');
    $url    = (string) get_post_meta($post->ID, '_ng_product_video_url', true);
    $poster = (string) get_post_meta($post->ID, '_ng_product_video_poster', true);
    ?>
    <p>
        <label for="ng_product_video_url" style="display:block;font-weight:600;margin-bottom:4px;">
            رابط الفيديو / Video URL
        </label>
        <input type="url" id="ng_product_video_url" name="ng_product_video_url"
               value="<?php echo esc_attr($url); ?>"
               placeholder="https://www.youtube.com/watch?v=…  or  https://example.com/clip.mp4"
               style="width:100%;direction:ltr;">
        <span class="description" style="font-size:11px;color:#666;display:block;margin-top:4px;">
            YouTube · Vimeo · MP4 · WebM
        </span>
    </p>
    <p>
        <label for="ng_product_video_poster" style="display:block;font-weight:600;margin-bottom:4px;">
            صورة الغلاف / Poster image (mp4/webm only)
        </label>
        <input type="url" id="ng_product_video_poster" name="ng_product_video_poster"
               value="<?php echo esc_attr($poster); ?>"
               placeholder="https://neogen.store/wp-content/uploads/…/poster.jpg"
               style="width:100%;direction:ltr;">
    </p>
    <p style="font-size:11px;color:#666;margin:0;">
        Bulk import: add <code>Meta: _ng_product_video_url</code> column to your WC CSV.
    </p>
    <?php
}

add_action('save_post_product', function ($post_id) {
    if (!isset($_POST['ng_product_video_nonce'])
        || !wp_verify_nonce($_POST['ng_product_video_nonce'], 'ng_product_video_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach (['ng_product_video_url' => '_ng_product_video_url',
             'ng_product_video_poster' => '_ng_product_video_poster'] as $field => $meta) {
        $val = isset($_POST[$field]) ? esc_url_raw(wp_unslash($_POST[$field])) : '';
        if ($val === '') {
            delete_post_meta($post_id, $meta);
        } else {
            update_post_meta($post_id, $meta, $val);
        }
    }
});

/* ---------------------------------------------------------------------
 * URL detection + embed renderer
 * ------------------------------------------------------------------- */

function ng_product_video_detect($url) {
    $url = trim((string) $url);
    if ($url === '') return null;

    // YouTube — watch / youtu.be / shorts / embed
    if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_\-]{6,})#i', $url, $m)) {
        return ['type' => 'youtube', 'id' => $m[1], 'url' => $url];
    }
    // Vimeo
    if (preg_match('#vimeo\.com/(?:video/)?(\d+)#i', $url, $m)) {
        return ['type' => 'vimeo', 'id' => $m[1], 'url' => $url];
    }
    // Self-hosted mp4 / webm / mov
    if (preg_match('#\.(mp4|webm|mov|m4v)(?:\?|$)#i', $url)) {
        return ['type' => 'file', 'id' => '', 'url' => $url];
    }
    return null;
}

function ng_product_video_html($url, $poster = '') {
    $info = ng_product_video_detect($url);
    if (!$info) return '';

    $allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';

    if ($info['type'] === 'youtube') {
        $src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($info['id']) . '?rel=0&modestbranding=1';
        return '<div class="ng-product-video ng-video-youtube"><iframe src="' . esc_url($src)
             . '" title="Product video" loading="lazy" allow="' . esc_attr($allow)
             . '" allowfullscreen></iframe></div>';
    }

    if ($info['type'] === 'vimeo') {
        $src = 'https://player.vimeo.com/video/' . rawurlencode($info['id']) . '?title=0&byline=0&portrait=0';
        return '<div class="ng-product-video ng-video-vimeo"><iframe src="' . esc_url($src)
             . '" title="Product video" loading="lazy" allow="' . esc_attr($allow)
             . '" allowfullscreen></iframe></div>';
    }

    // self-hosted
    $poster_attr = $poster !== '' ? ' poster="' . esc_url($poster) . '"' : '';
    return '<div class="ng-product-video ng-video-file"><video controls preload="metadata"'
         . $poster_attr . ' playsinline>'
         . '<source src="' . esc_url($info['url']) . '">'
         . '</video></div>';
}

/* ---------------------------------------------------------------------
 * Storefront — render above single-product summary
 * ------------------------------------------------------------------- */

add_action('woocommerce_before_single_product_summary', function () {
    if (!is_singular('product')) return;

    global $product;
    $id = is_object($product) ? $product->get_id() : get_the_ID();
    if (!$id) return;

    $url    = (string) get_post_meta($id, '_ng_product_video_url', true);
    if ($url === '') return;

    $poster = (string) get_post_meta($id, '_ng_product_video_poster', true);
    $html = ng_product_video_html($url, $poster);
    if ($html === '') return;

    echo '<section class="ng-product-video-wrap" aria-label="Product video">' . $html . '</section>';
}, 25);

/* ---------------------------------------------------------------------
 * Schema.org VideoObject — only when product has a video
 * Lets Google Merchant Center / Rich Results show video on the listing.
 * ------------------------------------------------------------------- */

add_action('wp_head', function () {
    if (!is_singular('product')) return;

    $id  = get_the_ID();
    $url = (string) get_post_meta($id, '_ng_product_video_url', true);
    if ($url === '') return;

    $info = ng_product_video_detect($url);
    if (!$info) return;

    $title = get_the_title($id);
    $desc  = get_post_field('post_excerpt', $id);
    if (!$desc) $desc = wp_trim_words((string) get_post_field('post_content', $id), 25, '…');

    $thumb = get_the_post_thumbnail_url($id, 'large');
    if (!$thumb) $thumb = home_url('/wp-content/mu-plugins/neogen-custom/neogen-theme-assets/img/social/og-default-ar.png');

    $embed = $info['type'] === 'youtube'
        ? 'https://www.youtube-nocookie.com/embed/' . $info['id']
        : ($info['type'] === 'vimeo' ? 'https://player.vimeo.com/video/' . $info['id'] : $url);

    $node = [
        '@context'     => 'https://schema.org',
        '@type'        => 'VideoObject',
        'name'         => $title,
        'description'  => $desc !== '' ? $desc : $title,
        'thumbnailUrl' => [$thumb],
        'uploadDate'   => get_the_date('c', $id),
        'contentUrl'   => $url,
        'embedUrl'     => $embed,
    ];

    echo "\n<!-- NeoGen Product Video schema -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}, 8);
