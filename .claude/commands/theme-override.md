---
description: Scaffold a Blocksy child-theme override mirroring a parent-theme file path
argument-hint: <relative-path-in-parent-theme>
---

Scaffold a child-theme override at `themes/blocksy-child/$ARGUMENTS` that mirrors the Blocksy parent theme's relative path.

Steps:

1. Validate `$ARGUMENTS` looks like a sane relative path (no leading `/`, no `..`, ends in `.php` or `.css`). If not, stop.
2. If `themes/blocksy-child/$ARGUMENTS` already exists, stop and tell the user — do not overwrite.
3. Create the parent directories under `themes/blocksy-child/` as needed, then create the file with this header-only scaffold:

   For `.php` files:
   ```php
   <?php
   /**
    * Child-theme override of: $ARGUMENTS
    *
    * Copied from the Blocksy parent to start. Edit below. Keep the relative path
    * identical to the parent so WP's template hierarchy picks this up.
    */

   defined('ABSPATH') || exit;

   // TODO: paste parent contents here, then edit.
   ```

   For `.css` files:
   ```css
   /* Child-theme override of: $ARGUMENTS */
   /* TODO: add overrides below. */
   ```

4. **Flag the deploy caveat explicitly** to the user:

   > `DEPLOY.md` notes that the current `neogen-deploy` plugin clones into `mu-plugins/neogen-custom/` — theme-overlay behaviour for `themes/blocksy-child/` may still need verification. Before relying on this override, confirm the deploy plugin actually rsyncs `themes/blocksy-child/` into `wp-content/themes/blocksy-child/`. If not, the override is a no-op on live.

5. Do not commit.
