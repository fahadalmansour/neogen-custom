# NeoGen Child theme (Blocksy child)

This is the source of truth for the **NeoGen Child** WordPress theme. On deploy it is rsynced into `wp-content/themes/blocksy-child/` on the live server by the second-target deployer at `mu-plugins/neogen-theme-deploy.php`.

## Status: Phase 1 (bootstrap, no behavior change)

Activating this child does **not** change anything visually. All site customization continues to flow through `mu-plugins/neogen-custom/` exactly as before. The point of Phase 1 is to be standing on a child theme so future phases (color/type/header/footer migration to Blocksy editor; selective template hand-off to Site Editor) attach to the child instead of patching mu-plugins.

## Contents

- `style.css` — child theme header (`Template: blocksy`).
- `functions.php` — enqueues parent + child stylesheet. Deliberately empty otherwise.
- `theme.json` — brand palette + font families + spacing scale + layout sizes, mirroring `mu-plugins/neogen-theme-assets/neogen.css` `:root` tokens. Inactive until Phase 2 wires the existing CSS to consume `var(--wp--preset--*)` instead of the local custom-property names.
- `README.md` — this file.

## How it gets to the server

The `mu-plugins/neogen-theme-deploy.php` mu-plugin runs on every wp-admin page load (gated by capability check + content-hash gate). When the contents of `wp-content/mu-plugins/neogen-custom/themes/blocksy-child/` change, it copies the directory into `wp-content/themes/blocksy-child/` via `copy_dir()`. **You do not need to upload a zip.**

Order of operations on first activation:
1. Click **Pull Latest** at `tools.php?page=neogen-deploy`. New mu-plugin file lands.
2. Load any wp-admin page once (e.g. dashboard). The mu-plugin syncs the child theme into place.
3. Go to **Appearance → Themes**, find "NeoGen Child", click **Activate**.
4. Reload the storefront. Visually identical (because Phase 1 is a no-op).

## Adding overrides later

Phase 2/3 will introduce template overrides here at standard WP child-theme paths (e.g. `themes/blocksy-child/woocommerce/cart/cart-empty.php`). Don't add overrides ad-hoc until the planning step for that phase has happened, otherwise the mu-plugin routing will keep winning and the override will appear dead.
