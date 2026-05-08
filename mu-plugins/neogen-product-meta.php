<?php
/**
 * Plugin Name: NeoGen Product Meta
 * Description: Per-product Arabic-title meta box on the WC product edit screen. Saves _ng_ar_title which is read by templates/front-page.php and templates/woocommerce/content-product.php to render an Arabic-first product title alongside (or instead of) the canonical English name.
 * Version: 1.20.3
 * Author: Fahad Almansour
 *
 * Bulk import: 'Meta: _ng_ar_title' is one of the four meta columns
 * appended to all product CSVs in v1.20.1 (see
 * /Users/fahadalmansour/ngs/Products/*.csv). The admin UI here is the
 * per-product editor companion.
 */

defined('ABSPATH') || exit;

/* ---------------------------------------------------------------------
 * Meta box on the product edit screen
 * ------------------------------------------------------------------- */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'ng-product-ar-title',
        'NeoGen — العنوان العربي / Arabic title',
        'ng_product_ar_title_meta_box',
        'product',
        'side',
        'default'
    );
});

function ng_product_ar_title_meta_box($post) {
    wp_nonce_field('ng_product_ar_title_save', 'ng_product_ar_title_nonce');
    $value = (string) get_post_meta($post->ID, '_ng_ar_title', true);
    ?>
    <p>
        <label for="ng_product_ar_title" style="display:block;font-weight:600;margin-bottom:4px;">
            العنوان العربي / Arabic title
        </label>
        <input type="text" id="ng_product_ar_title" name="ng_product_ar_title"
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr__( 'مثال: لوحة مفاتيح ميكانيكية لاسلكية', 'neogen' ); ?>"
               style="width:100%;direction:rtl;text-align:right;font-family:'Tajawal',sans-serif;">
        <span class="description" style="font-size:11px;color:#666;display:block;margin-top:4px;">
            Shown on the homepage Operator Picks card and shop loop card. If empty, the product title is cleaned via <code>ng_ar_label()</code> instead.
        </span>
    </p>
    <p style="font-size:11px;color:#666;margin:0;">
        Bulk import: column <code>Meta: _ng_ar_title</code> on the WC CSV.
    </p>
    <?php
}

add_action('save_post_product', function ($post_id) {
    /*
     * REST / block-editor saves don't carry the nonce — bail without
     * touching meta so a Gutenberg save doesn't accidentally clear a
     * value set elsewhere (CSV import, direct meta edit).
     */
    if (!isset($_POST['ng_product_ar_title_nonce'])
        || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ng_product_ar_title_nonce'] ) ), 'ng_product_ar_title_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $val = isset($_POST['ng_product_ar_title'])
        ? sanitize_text_field( wp_unslash( $_POST['ng_product_ar_title'] ) )
        : '';

    if ($val === '') {
        delete_post_meta($post_id, '_ng_ar_title');
    } else {
        update_post_meta($post_id, '_ng_ar_title', $val);
    }
});
