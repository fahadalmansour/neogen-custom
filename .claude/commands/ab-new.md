---
description: Scaffold a new A/B experiment snippet using the neogen_ab primitive
argument-hint: <experiment-key>
---

Scaffold a new experiment snippet at `plugins/neogen-snippets/ab-$ARGUMENTS.php` wired to the `neogen_ab_*` primitive in `mu-plugins/neogen-ab.php`.

Rules:

1. Validate `$ARGUMENTS` — single token, `[a-z0-9_]+` only (underscores fine, dashes fine, no spaces/uppercase/extension). The key will be used both as the file slug and as the literal `$experiment_key` string passed to `neogen_ab_bucket`.
2. If `plugins/neogen-snippets/ab-$ARGUMENTS.php` already exists, stop — do not overwrite.
3. Ask the user:
   - **Variants** (comma-separated slugs, default: `control,treatment`)
   - **Where does exposure happen?** (filter/action hook where the user sees the variant — e.g. `the_title`, `woocommerce_before_single_product`, `wp_footer`). If unsure, scaffold with a clearly-marked TODO.
   - **Where does conversion happen?** (hook fired on the success event — typically `woocommerce_thankyou`, `woocommerce_order_status_completed`, or a form submit). If the user doesn't know yet, leave the conversion block commented out with a TODO.
4. Write the file with this shape, filling in the user's answers:

   ```php
   <?php
   /**
    * Snippet: A/B — <experiment_key>
    * Auto-loaded by plugins/neogen-snippets/neogen-snippets.php
    * Toggle via WP admin → Plugins → NeoGen Snippets.
    * Ships DISABLED. Activate by flipping the constant below.
    */

   defined('ABSPATH') || exit;

   const NEOGEN_AB_<UPPER_KEY>_ENABLED = false;

   if (!NEOGEN_AB_<UPPER_KEY>_ENABLED) return;
   if (!function_exists('neogen_ab_bucket')) return;

   add_<action|filter>('<exposure_hook>', function (/* hook args */) {
       $variant = neogen_ab_bucket('<experiment_key>', [<variants>]);
       neogen_ab_expose('<experiment_key>', $variant);

       // TODO: return / render the variant
   });

   // Conversion:
   // add_action('<conversion_hook>', function () {
   //     $variant = neogen_ab_bucket('<experiment_key>', [<variants>]);
   //     neogen_ab_convert('<experiment_key>', $variant, ['source' => '<conversion_hook>']);
   // });
   ```

   Substitute `<UPPER_KEY>` = uppercase of the key with hyphens replaced by underscores.

5. Remind the user:
   - The snippet is inert until they flip the constant.
   - PDPL/GDPR consent gating is their call — the primitive does **not** handle consent.
   - Events land at `wp-content/uploads/neogen-ab.log` as JSON lines; analysis is grep/awk, not a dashboard.
   - After activating, bump the version (`/bump-version`) and deploy normally.

Do not commit.
