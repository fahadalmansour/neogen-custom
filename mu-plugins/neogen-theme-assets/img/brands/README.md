# NeoGen Brand Image Packs

Drop clean brand artwork (webp preferred, png/jpg ok) into the matching subdir.
The matcher in `mu-plugins/neogen-brand-images.php` will swap WooCommerce
product thumbnails on the shop, single-product, and cart pages whenever a
product's name/SKU/category matches a registered keyword.

Same model as `gift-cards/` — you own the artwork, no fabrication.

## Expected filenames

Each filename = the brand-key declared in `ng_brand_image_packs()` + `.webp`.

### `networking/`
- `mikrotik.webp`, `ubiquiti.webp`, `tp-link.webp`, `cisco.webp`, `aruba.webp`, `netgear.webp`

### `smart-home/`
- `aqara.webp`, `philips-hue.webp`, `shelly.webp`, `sonoff.webp`, `tuya.webp`, `home-assistant.webp`, `ikea-tradfri.webp`

### `gaming/`
- `8bitdo.webp`, `razer.webp`, `logitech.webp`, `corsair.webp`, `sony.webp`, `xbox.webp`, `nintendo.webp`

### `software/`
- `microsoft.webp`, `kaspersky.webp`, `norton.webp`, `mcafee.webp`, `adobe.webp`, `autodesk.webp`, `jetbrains.webp`, `bitdefender.webp`, `eset.webp`

### `storage/`
- `synology.webp`, `qnap.webp`, `truenas.webp`, `wd.webp`, `seagate.webp`, `samsung.webp`, `kingston.webp`, `crucial.webp`

### `accessories/`
- `anker.webp`, `belkin.webp`, `ugreen.webp`, `baseus.webp`

## Image spec

- Aspect ratio: 16:9 (matches gift-cards) — 800×450 or 1200×675
- Format: webp at quality 80–90 (best size/quality trade-off)
- Background: solid brand color or clean studio shot (matches operator-console aesthetic)
- No bonus/promo overlays — keep it timeless

## Adding a new brand or pack

Edit `ng_brand_image_packs()` in `mu-plugins/neogen-brand-images.php`, or
register from a snippet:

```php
add_filter('ng_brand_image_packs', function ($packs) {
    $packs['my-pack'] = [
        'pack'     => 'my-pack',                 // subdir under img/brands/
        'cat_slug' => ['my-cat'],                 // product_cat slug(s)
        'brands'   => [
            'foo' => ['file' => 'foo.webp', 'keywords' => ['foo', 'فو']],
        ],
    ];
    return $packs;
});
```

The matcher only emits an `<img>` when the file actually exists on disk —
missing files silently fall back to the WP/WooCommerce default thumbnail.
