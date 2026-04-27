---
description: Scaffold a WooCommerce action or filter hook in the right overlay target
argument-hint: <action|filter> <hook-name>
---

Scaffold a WooCommerce hook. Arguments: `<action|filter> <hook-name>` (e.g. `action woocommerce_new_order` or `filter woocommerce_add_to_cart_fragments`).

Steps:

1. Parse `$ARGUMENTS` into `<kind>` (`action` or `filter`) and `<hook>`. Reject anything else.
2. **Do not guess** whether the hook name exists. If you're not 100% sure it's a real WooCommerce hook, say so and ask the user to confirm before scaffolding.
3. Ask the user: **always-on or toggleable?**
   - *Always-on* → goes into `mu-plugins/neogen-site-custom.php` (append at the bottom, before the closing `}` of any wrapping block if present).
   - *Toggleable* → new file `plugins/neogen-snippets/<hook>.php` (sanitize hook name: replace `_` with `-`).
4. Scaffold based on `<kind>`:

   Action:
   ```php
   add_action('<hook>', function (/* args depend on hook — verify in Woo docs */) {
       // TODO: implement
   });
   ```

   Filter (must return a value):
   ```php
   add_filter('<hook>', function ($value /*, extra args */) {
       // TODO: implement
       return $value;
   }, 10, /* argcount — verify in Woo docs */ 1);
   ```

5. For toggleable: include the full snippet header + ABSPATH guard + the `return;` inert-until-ready gate (same shape as `/new-snippet`).
6. Remind the user that:
   - Hook argument counts and names must be verified against WooCommerce source; `// TODO: verify` is not optional.
   - If changing always-on behaviour, bump the version (`/bump-version`).
   - Deploy is `git push` + WP admin **Pull Latest**, never automated.

Do not commit.
