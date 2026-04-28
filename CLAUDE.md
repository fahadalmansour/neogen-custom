# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

Deployable custom code for the **neogen.store** WordPress/WooCommerce site (live, no staging). This repo is **not** a standalone WordPress install — it holds three overlays that a companion WP plugin (`neogen-deploy`, not in this repo) pulls onto the live server at `/var/www/ngs1/wp-content/`.

There is no build, no tests, no package manager, and no local runtime. Changes reach production only via `git push` → WP admin → **Pull Latest** button. Editing and pushing without clicking that button does nothing to the site.

## Deploy flow (read this before changing anything)

1. Edit → `git commit` → `git push` to `origin/main`.
2. Admin clicks **Pull Latest** at `https://neogen.store/wp-admin/tools.php?page=neogen-deploy`.
3. The deploy plugin: runs `php -l` on every changed `.php` file, refuses to deploy on syntax error, otherwise copies into the live WP tree and regenerates `wp-content/mu-plugins/neogen-custom-loader.php`.
4. Success is confirmed by the admin-bar badge `🚀 NG <version>` on the live site.

Rollback = **Rollback −1 commit** button in the same admin page, or `git revert HEAD && git push` locally then re-deploy. There is no deeper UI rollback.

## Versioning convention

Every user-visible deploy should bump the version in **two places together**:
- `VERSION` (plain text file, single line)
- `mu-plugins/neogen-site-custom.php` — both the `Version:` header and the `NEOGEN_CUSTOM_VERSION` constant

The admin-bar badge reads `NEOGEN_CUSTOM_VERSION`; if it doesn't update after a deploy, the constant wasn't bumped.

## Three overlay targets (they deploy to different places)

- `mu-plugins/*.php` → `wp-content/mu-plugins/` — **always active**, no toggle. Use for hooks/filters that must always run (admin bar, order hooks, global filters).
- `plugins/neogen-snippets/` → `wp-content/plugins/neogen-snippets/` — a single toggleable plugin whose loader (`neogen-snippets.php`) auto-`require_once`s **every other `*.php` file in the directory**. Drop a new file in to add a snippet; no registration needed. The whole bundle is enabled/disabled from WP admin → Plugins.
- `themes/blocksy-child/**` → overlaid onto the Blocksy child theme. Follow the standard WP child-theme override pattern (mirror the parent file's relative path). Note: per `DEPLOY.md`, the deploy plugin currently clones into `mu-plugins/neogen-custom/`; theme-overlay behaviour may require a second deploy target — verify before relying on it.

## Safety constraints baked into this project

- **All deploys are production.** There is no dev/staging environment in this repo or the deploy pipeline. Treat every push as live-ready.
- `.gitignore` blocks `.env*`, `*.key`, `*.pem`, `*.sql*`, `wp-config.php`, `*.secret`. Do not add paths that would defeat these.
- Deploy is rate-limited to 20/hr per admin.
- The server-side deploy plugin holds a GitHub PAT with read+write on this repo; never commit one here.

## Brand assets

The `NeoGen Store · Brand Kit v1.0*`, `NeoGen Store — Brand System v1.0*`, `NeoGen — Logo Size Kit*`, and `NeoGen — Monogram + Wordmark Variations*` HTML files + `_files/` folders at the repo root are static brand-spec exports. They are not deployed and are unrelated to the code overlays — ignore them when reasoning about runtime behaviour.

`NeoGen Store — Brand Tokens v2.0.md` (repo root) is the **canonical source of record for colour decisions** as of Phase R6 (2026-04-29). It supersedes the v1.0/v1.1 HTML files for palette / shadow / radius / border choices. Typography + brand voice rules in the v1.1 HTML still apply unchanged. When in doubt about a colour or shadow token, check `mu-plugins/neogen-theme-assets/neogen.css` `:root` block first (deployed source of truth), then v2.0.md for rationale.
