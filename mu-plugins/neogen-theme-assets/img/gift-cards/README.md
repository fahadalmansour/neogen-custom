# Gift-Card Brand Artwork

Drop clean brand-authentic webp files here. The matcher in
`mu-plugins/neogen-gift-cards.php` swaps WooCommerce product thumbnails on
shop, single-product, and cart pages whenever a product's name / SKU /
category contains a registered keyword.

Missing files fall back to the WC default thumbnail silently
(`ng_gift_card_existing_file()` in `neogen-gift-cards.php` walks the file
list per slot and only emits an `<img>` when at least one webp exists on
disk). So registering a new slot ahead of art is safe.

## Image spec

- **Aspect:** 16:9 (existing files are ~400×225)
- **Format:** webp, quality 80–90 (best size/quality trade-off)
- **Background:** brand-authentic — match what's already shipped (e.g.
  `apple.webp` is white with the multicolor logo, `steam.webp` is a dark
  navy gradient with the Steam wordmark)
- **No promo overlays** — keep timeless, no "$5 free", no holiday graphics

## Filename → slot key

The matcher's first-match-wins resolver tries each filename in the
slot's `files` list in order. The conventional name is `<slot-key>.webp`
but legacy aliases are accepted as fallback (e.g. `googleplay.webp` →
google-play slot, `psn.webp` → playstation slot).

### Currently shipped (11 webp present)

`adobe.webp`, `amazon.webp`, `apple.webp`, `google-play.webp`,
`kaspersky.webp`, `office.webp`, `playstation.webp`, `steam.webp`,
`windows.webp`, `xbox.webp`, `youtube.webp`

### Registered slots awaiting art

**Wallet / telco (KSA):** `stc-pay.webp`, `stc.webp`, `mobily.webp`,
`zain.webp`, `careem.webp`

**Wallet / telco (GCC):** `etisalat.webp`, `du.webp` (UAE),
`batelco.webp` (Bahrain), `omantel.webp` (Oman), `ooredoo.webp`
(Qatar / Kuwait / Oman group)

**GCC marketplaces / retail:** `talabat.webp`, `carrefour.webp`,
`sharaf-dg.webp`, `lulu.webp`, `x-cite.webp`,
`virgin-megastore.webp`

**Streaming / audio:** `netflix.webp`, `shahid.webp`, `spotify.webp`,
`anghami.webp`, `disney-plus.webp`

**Console / store credit:** `nintendo-eshop.webp`

**Game top-ups:** `pubg.webp`, `free-fire.webp`, `roblox.webp`,
`razer-gold.webp`, `discord-nitro.webp`, `fortnite.webp`,
`minecraft.webp`

**Marketplaces:** `noon.webp`, `jarir.webp`, `ebay.webp`

**Social / utility:** `snapchat-plus.webp`, `tiktok-coins.webp`

**Prepaid debit:** `visa-prepaid.webp`, `mastercard-prepaid.webp`

## Adding a new brand

Edit `ng_gift_card_asset_map()` in `mu-plugins/neogen-gift-cards.php`,
or register from a snippet via the new filter:

```php
add_filter('ng_gift_card_asset_map', function ($map) {
    $map['my-brand'] = [
        'files'    => ['my-brand.webp'],
        'keywords' => ['my brand', 'علامتي'],
    ];
    return $map;
});
```

Order matters: high-specificity keys (e.g. `stc-pay`) must come BEFORE
more general ones (e.g. `stc`) so the generic key doesn't shadow the
specific one. The matcher returns first-hit.

## Smoke test

After editing `ng_gift_card_asset_map()` (adding slots, reordering,
renaming the schema) run the smoke test from the repo root:

```bash
php tests/test-gift-card-matcher.php
```

Expected: `OK — 14 passed, 0 failed` and exit 0. The harness stubs
WP/WC functions and exercises spelling + ordering edge cases
(`stc-pay` beating `stc`, AR transliterations for PUBG / Free Fire /
Netflix, schema-shape `'files'` array). Keyword changes that break
existing matches will fail loudly here instead of silently in
production.
