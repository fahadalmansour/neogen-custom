<?php
/**
 * Plugin Name: NeoGen Brand Imagery
 * Description: Two Media Library pickers (hero + voice) feeding the homepage. Tools -> Brand Imagery.
 * Version: 1.9.0
 * Author: Fahad Almansour
 */

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_management_page(
        'Brand Imagery',
        'Brand Imagery',
        'manage_options',
        'neogen-brand-imagery',
        'ng_brand_imagery_render'
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'tools_page_neogen-brand-imagery') return;
    wp_enqueue_media();
});

add_action('admin_post_ng_brand_imagery_save', function () {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
    check_admin_referer('ng_brand_imagery_save');

    update_option('ng_hero_image_id',  (int) ($_POST['ng_hero_image_id']  ?? 0));
    update_option('ng_voice_image_id', (int) ($_POST['ng_voice_image_id'] ?? 0));

    wp_safe_redirect(add_query_arg(
        'saved',
        '1',
        admin_url('tools.php?page=neogen-brand-imagery')
    ));
    exit;
});

function ng_brand_imagery_render() {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
    $hero_id  = (int) get_option('ng_hero_image_id', 0);
    $voice_id = (int) get_option('ng_voice_image_id', 0);
    $hero_src  = $hero_id  ? wp_get_attachment_image_url($hero_id,  'medium') : '';
    $voice_src = $voice_id ? wp_get_attachment_image_url($voice_id, 'medium') : '';
    $saved = !empty($_GET['saved']);
    ?>
    <div class="wrap">
      <h1>NeoGen Brand Imagery</h1>
      <p>Pick the photos that render behind the homepage hero and inside the brand voice band. Use full-bleed editorial photography, 1600px+ on the long edge.</p>

      <?php if ($saved) : ?>
        <div class="notice notice-success is-dismissible"><p>Saved.</p></div>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ng_brand_imagery_save'); ?>
        <input type="hidden" name="action" value="ng_brand_imagery_save">

        <h2>Hero background</h2>
        <p>Renders behind the hero text. A cream gradient is overlaid for legibility.</p>
        <div class="ng-bi-row">
          <div class="ng-bi-preview" id="ng-bi-hero-preview" style="<?php echo $hero_src ? 'background-image:url(' . esc_url($hero_src) . ')' : ''; ?>"></div>
          <input type="hidden" id="ng_hero_image_id" name="ng_hero_image_id" value="<?php echo (int) $hero_id; ?>">
          <p>
            <button type="button" class="button" data-ng-pick="hero">Choose / Replace</button>
            <button type="button" class="button" data-ng-clear="hero">Clear</button>
          </p>
        </div>

        <h2>Voice band photo</h2>
        <p>Renders next to the brand-voice typography. 4:3 aspect crops cleanly.</p>
        <div class="ng-bi-row">
          <div class="ng-bi-preview" id="ng-bi-voice-preview" style="<?php echo $voice_src ? 'background-image:url(' . esc_url($voice_src) . ')' : ''; ?>"></div>
          <input type="hidden" id="ng_voice_image_id" name="ng_voice_image_id" value="<?php echo (int) $voice_id; ?>">
          <p>
            <button type="button" class="button" data-ng-pick="voice">Choose / Replace</button>
            <button type="button" class="button" data-ng-clear="voice">Clear</button>
          </p>
        </div>

        <?php submit_button('Save imagery'); ?>
      </form>
    </div>

    <style>
      .ng-bi-row { margin: 12px 0 28px; }
      .ng-bi-preview { width: 320px; height: 180px; border: 1px solid #c3c4c7; background: #f0f0f1 50% 50% / cover no-repeat; border-radius: 4px; }
    </style>

    <script>
      (function () {
        if (!window.wp || !window.wp.media) return;
        var frames = {};
        function open(slug) {
          if (frames[slug]) { frames[slug].open(); return; }
          frames[slug] = wp.media({
            title: 'Select image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
          });
          frames[slug].on('select', function () {
            var att = frames[slug].state().get('selection').first().toJSON();
            document.getElementById('ng_' + slug + '_image_id').value = att.id;
            var src = (att.sizes && (att.sizes.medium || att.sizes.thumbnail)) ? (att.sizes.medium || att.sizes.thumbnail).url : att.url;
            var p = document.getElementById('ng-bi-' + slug + '-preview');
            if (p) p.style.backgroundImage = 'url(' + src + ')';
          });
          frames[slug].open();
        }
        document.querySelectorAll('[data-ng-pick]').forEach(function (b) {
          b.addEventListener('click', function () { open(b.dataset.ngPick); });
        });
        document.querySelectorAll('[data-ng-clear]').forEach(function (b) {
          b.addEventListener('click', function () {
            var slug = b.dataset.ngClear;
            document.getElementById('ng_' + slug + '_image_id').value = 0;
            var p = document.getElementById('ng-bi-' + slug + '-preview');
            if (p) p.style.backgroundImage = '';
          });
        });
      })();
    </script>
    <?php
}
