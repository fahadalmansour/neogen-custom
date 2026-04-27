---
description: Pre-push read-only gate — syntax-check PHP, scan for secrets, verify version sync
---

Run the full pre-push checklist. **Read-only**: no writes, no commits, no pushes. Report PASS or FAIL clearly, listing every failed check.

Steps:

1. **PHP syntax check.** Run `php -l` on every tracked `.php` file under `mu-plugins/`, `plugins/`, and `themes/blocksy-child/`. Anything that's not "No syntax errors detected" is a FAIL — show the offending file + error.

   ```bash
   find mu-plugins plugins themes/blocksy-child -type f -name '*.php' -print0 2>/dev/null | xargs -0 -n1 php -l
   ```

2. **Secret scan.** Grep for obvious secret patterns in tracked code:

   ```bash
   grep -RInE '(DB_PASSWORD|API_KEY|BEARER\s+[A-Za-z0-9]|AUTH_KEY|ghp_[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|xox[baprs]-[A-Za-z0-9-]{10,})' mu-plugins plugins themes 2>/dev/null
   ```

   Any match is a FAIL — show the file + line.

3. **Version sync check.** All three must be equal:
   - `VERSION` file contents (trimmed)
   - `Version:` header line in `mu-plugins/neogen-site-custom.php`
   - `NEOGEN_CUSTOM_VERSION` constant value in `mu-plugins/neogen-site-custom.php`

   Report the three values side-by-side. Mismatch = FAIL.

4. **Untracked / unstaged summary.** Run `git status --short` and show it — this is informational, not a FAIL signal, but the user should see what's about to ship.

5. **Final verdict:** Print `PASS` or `FAIL`. On FAIL, do not suggest pushing. On PASS, remind the user the next step is `git commit` → `git push` → WP admin **Pull Latest**, and that success is confirmed by the admin-bar badge `🚀 NG <version>`.

Do not modify any files. Do not run `git add`, `commit`, or `push`.
