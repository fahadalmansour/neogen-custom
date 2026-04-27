---
description: Bump VERSION, the plugin header, and the NEOGEN_CUSTOM_VERSION constant atomically
argument-hint: <x.y.z>
---

Bump the deployed version in all three required locations. Run as a single atomic change.

Steps:

1. Validate `$ARGUMENTS` matches semver `^\d+\.\d+\.\d+$`. If not, stop and ask.
2. Read the current state:
   - `VERSION` file contents (trimmed)
   - `Version:` header line in `mu-plugins/neogen-site-custom.php`
   - `NEOGEN_CUSTOM_VERSION` constant value in `mu-plugins/neogen-site-custom.php`
3. If the three current values are **not** all equal, report the mismatch and stop. Do not bump — the user needs to fix the drift first.
4. If new version === current version, report "already at $ARGUMENTS" and stop.
5. Apply the bump to all three:
   - Overwrite `VERSION` with `$ARGUMENTS\n`
   - Replace the `Version:` header line: `* Version: <old>` → `* Version: $ARGUMENTS`
   - Replace the constant: `define('NEOGEN_CUSTOM_VERSION', '<old>')` → `define('NEOGEN_CUSTOM_VERSION', '$ARGUMENTS')`
6. Run `git diff --stat` and show the three-file diff to the user.
7. Do **not** commit. Do **not** push. Tell the user the next step is `git commit -m "bump to $ARGUMENTS"` then push + WP admin **Pull Latest**, and that deploy success is confirmed when the admin-bar badge reads `🚀 NG $ARGUMENTS`.
