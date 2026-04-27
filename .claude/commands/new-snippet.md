---
description: Scaffold a new toggleable snippet in plugins/neogen-snippets/
argument-hint: <slug>
---

Create a new snippet file at `plugins/neogen-snippets/$ARGUMENTS.php` if it doesn't already exist. The existing loader at `plugins/neogen-snippets/neogen-snippets.php` auto-requires every `*.php` in that directory — no registration needed.

Rules:

1. Validate `$ARGUMENTS` — must be a single token matching `[a-z0-9-]+`. If it has spaces, uppercase, or `.php`, stop and ask the user to correct it.
2. If `plugins/neogen-snippets/$ARGUMENTS.php` already exists, stop and tell the user — do not overwrite.
3. Write the file with this exact scaffold:

   ```php
   <?php
   /**
    * Snippet: $ARGUMENTS
    * Auto-loaded by plugins/neogen-snippets/neogen-snippets.php
    * Toggle via WP admin → Plugins → NeoGen Snippets.
    */

   defined('ABSPATH') || exit;

   // Snippet is inert until you add logic below.
   // Ship disabled: a deploy alone should never change site behaviour.
   return;

   // TODO: remove the `return;` above once your hook(s) are ready.
   // Example:
   // add_action('wp_footer', function () { /* ... */ });
   ```

4. Remind the user:
   - No version bump is required for adding an inert snippet, but bump when it goes live.
   - Deploy by `git push` + WP admin **Pull Latest**.

Do not edit `neogen-snippets.php` (the loader). Do not commit.
