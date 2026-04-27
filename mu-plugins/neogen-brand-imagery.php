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

    update_option('ng_hero_image_id',      (int) ($_POST['ng_hero_image_id']      ?? 0));
    update_option('ng_hero_side_image_id', (int) ($_POST['ng_hero_side_image_id'] ?? 0));
    update_option('ng_voice_image_id',     (int) ($_POST['ng_voice_image_id']     ?? 0));

    $brand_ids_raw = (string) ($_POST['ng_brand_logo_ids'] ?? '');
    $brand_ids = array_values(array_filter(array_map('intval', explode(',', $brand_ids_raw))));
    update_option('ng_brand_logo_ids', $brand_ids);

    wp_safe_redirect(add_query_arg(
        'saved',
        '1',
        admin_url('tools.php?page=neogen-brand-imagery')
    ));
    exit;
});

function ng_brand_imagery_render() {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
    $hero_id      = (int) get_option('ng_hero_image_id',      0);
    $hero_side_id = (int) get_option('ng_hero_side_image_id', 0);
    $voice_id     = (int) get_option('ng_voice_image_id',     0);
    $brand_ids    = (array) get_option('ng_brand_logo_ids', []);
    $brand_ids    = array_values(array_filter(array_map('intval', $brand_ids)));

    $hero_src      = $hero_id      ? wp_get_attachment_image_url($hero_id,      'medium') : '';
    $hero_side_src = $hero_side_id ? wp_get_attachment_image_url($hero_side_id, 'medium') : '';
    $voice_src     = $voice_id     ? wp_get_attachment_image_url($voice_id,     'medium') : '';
    $saved = !empty($_GET['saved']);
    ?>
    <div class="wrap">
      <h1>NeoGen Brand Imagery</h1>
      <p>Pick the photos that render across the homepage. Use full-bleed editorial photography for hero/voice (1600px+ on the long edge); brand logos can be PNG or SVG (transparent background, ≥ 200px tall).</p>

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

        <h2>Hero side panel</h2>
        <p>Renders alongside the hero text as a split-screen photo. Square or 4:5 portrait crops cleanly.</p>
        <div class="ng-bi-row">
          <div class="ng-bi-preview" id="ng-bi-hero_side-preview" style="<?php echo $hero_side_src ? 'background-image:url(' . esc_url($hero_side_src) . ')' : ''; ?>"></div>
          <input type="hidden" id="ng_hero_side_image_id" name="ng_hero_side_image_id" value="<?php echo (int) $hero_side_id; ?>">
          <p>
            <button type="button" class="button" data-ng-pick="hero_side">Choose / Replace</button>
            <button type="button" class="button" data-ng-clear="hero_side">Clear</button>
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

        <h2>Brand logos strip</h2>
        <p>Vendor logos rendered between Operator Picks and Service Strip. Pick all the brand marks you carry — order in the picker becomes order on the page.</p>
        <div class="ng-bi-row">
          <input type="hidden" id="ng_brand_logo_ids" name="ng_brand_logo_ids" value="<?php echo esc_attr(implode(',', $brand_ids)); ?>">
          <div id="ng-bi-brand-preview" class="ng-bi-multi"></div>
          <p>
            <button type="button" class="button" id="ng-bi-brand-pick">Choose / Replace</button>
            <button type="button" class="button" id="ng-bi-brand-clear">Clear all</button>
          </p>
        </div>

        <?php submit_button('Save imagery'); ?>
      </form>
    </div>

    <style>
      .ng-bi-row { margin: 12px 0 28px; }
      .ng-bi-preview { width: 320px; height: 180px; border: 1px solid #c3c4c7; background: #f0f0f1 50% 50% / cover no-repeat; border-radius: 4px; }
      .ng-bi-multi { display: flex; flex-wrap: wrap; gap: 10px; min-height: 60px; padding: 8px; border: 1px dashed #c3c4c7; border-radius: 4px; background: #f6f7f7; }
      .ng-bi-multi img { height: 48px; width: auto; max-width: 120px; object-fit: contain; background: #fff; border: 1px solid #dcdcde; border-radius: 3px; padding: 4px; }
    </style>

    <script>
      (function () {
        if (!window.wp || !window.wp.media) return;
        var frames = {};

        function openSingle(slug) {
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
          b.addEventListener('click', function () { openSingle(b.dataset.ngPick); });
        });
        document.querySelectorAll('[data-ng-clear]').forEach(function (b) {
          b.addEventListener('click', function () {
            var slug = b.dataset.ngClear;
            document.getElementById('ng_' + slug + '_image_id').value = 0;
            var p = document.getElementById('ng-bi-' + slug + '-preview');
            if (p) p.style.backgroundImage = '';
          });
        });

        var brandPreview = document.getElementById('ng-bi-brand-preview');
        var brandInput   = document.getElementById('ng_brand_logo_ids');

        function renderBrandPreview(items) {
          brandPreview.innerHTML = '';
          items.forEach(function (it) {
            var im = document.createElement('img');
            im.src = it.src;
            im.alt = it.title || '';
            brandPreview.appendChild(im);
          });
        }

        // initial render: query attachment data for already-saved IDs
        (function () {
          var ids = (brandInput.value || '').split(',').map(function (s) { return parseInt(s, 10); }).filter(Boolean);
          if (!ids.length) return;
          wp.media.attachment.prototype.constructor;
          var collection = ids.map(function (id) {
            var a = wp.media.attachment(id);
            a.fetch();
            return a;
          });
          // best-effort: paint when each fetch resolves
          collection.forEach(function (a) {
            a.fetch().done(function () {
              var d = a.toJSON();
              var im = document.createElement('img');
              var src = (d.sizes && (d.sizes.thumbnail || d.sizes.medium)) ? (d.sizes.thumbnail || d.sizes.medium).url : d.url;
              im.src = src; im.alt = d.title || '';
              brandPreview.appendChild(im);
            });
          });
        })();

        document.getElementById('ng-bi-brand-pick').addEventListener('click', function () {
          var frame = wp.media({
            title: 'Select brand logos',
            button: { text: 'Use these logos' },
            multiple: true,
            library: { type: 'image' }
          });
          // pre-select existing
          var existingIds = (brandInput.value || '').split(',').map(function (s) { return parseInt(s, 10); }).filter(Boolean);
          frame.on('open', function () {
            var sel = frame.state().get('selection');
            existingIds.forEach(function (id) {
              var att = wp.media.attachment(id);
              att.fetch();
              sel.add(att ? [att] : []);
            });
          });
          frame.on('select', function () {
            var sel = frame.state().get('selection').toJSON();
            brandInput.value = sel.map(function (a) { return a.id; }).join(',');
            renderBrandPreview(sel.map(function (a) {
              var src = (a.sizes && (a.sizes.thumbnail || a.sizes.medium)) ? (a.sizes.thumbnail || a.sizes.medium).url : a.url;
              return { src: src, title: a.title };
            }));
          });
          frame.open();
        });

        document.getElementById('ng-bi-brand-clear').addEventListener('click', function () {
          brandInput.value = '';
          brandPreview.innerHTML = '';
        });
      })();
    </script>
    <?php
}
