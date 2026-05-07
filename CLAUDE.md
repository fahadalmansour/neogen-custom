# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

Deployable custom code for the **neogen.store** WordPress/WooCommerce site (live, no staging). This repo is **not** a standalone WordPress install — it holds three overlays that a companion WP plugin (`neogen-deploy`, not in this repo) pulls onto the live server at `/var/www/ngs1/wp-content/`.

There is no build, no tests (except `tests/test-gift-card-matcher.php` run manually via WP-CLI), no package manager, and no local runtime. Changes reach production only via `git push` → WP admin → **Pull Latest** button. Editing and pushing without clicking that button does nothing to the site.

## Deploy flow (read this before changing anything)

1. Edit → `git commit` → `git push` to `origin/main`.
2. Admin clicks **Pull Latest** at `https://neogen.store/wp-admin/tools.php?page=neogen-deploy`.
3. The deploy plugin: runs `php -l` on every changed `.php` file, refuses to deploy on syntax error, otherwise copies into the live WP tree and regenerates `wp-content/mu-plugins/neogen-custom-loader.php`.
4. Success is confirmed by the admin-bar badge `🚀 NG <version>` on the live site.

Rollback = **Rollback −1 commit** button in the same admin page, or `git revert HEAD && git push` locally then re-deploy. There is no deeper UI rollback.

**Before pushing**, run a local syntax check:
```bash
find . -name '*.php' -not -path './agent-skills/*' -exec php -l {} \;
```

## Versioning convention

Every user-visible deploy should bump the version in **two places together**:
- `VERSION` (plain text file, single line)
- `mu-plugins/neogen-site-custom.php` — both the `Version:` header and the `NEOGEN_CUSTOM_VERSION` constant

The admin-bar badge reads `NEOGEN_CUSTOM_VERSION`; if it doesn't update after a deploy, the constant wasn't bumped.

Use `/bump-version <x.y.z>` to update both places in one step.

## Three overlay targets — when to use which

| Target | Path in this repo | Deployed to | Use when |
|---|---|---|---|
| **mu-plugin** | `mu-plugins/*.php` | `wp-content/mu-plugins/` | Always-on hooks/filters (order processing, SEO, admin bar, global filters). Cannot be disabled from WP admin. |
| **Snippet** | `plugins/neogen-snippets/*.php` | `wp-content/plugins/neogen-snippets/` | Toggleable features. Drop a new `.php` file — the loader (`neogen-snippets.php`) auto-`require_once`s every `.php` in the directory. The whole bundle is enabled/disabled from WP admin → Plugins. |
| **Child theme** | `themes/blocksy-child/**` | `wp-content/themes/blocksy-child/` | Blocksy template overrides (mirror parent file's relative path). `neogen-theme-deploy.php` handles syncing on `admin_init`; verify before relying on it for new files. |

**Default choice**: start with a snippet (toggleable, lower risk). Promote to mu-plugin only if it must always run regardless of plugin state.

## Available Claude slash commands

| Command | What it does |
|---|---|
| `/bump-version <x.y.z>` | Updates `VERSION` + `neogen-site-custom.php` header + constant |
| `/deploy-check` | Syntax-checks all PHP + verifies version consistency before push |
| `/new-snippet <slug>` | Scaffolds a new file in `plugins/neogen-snippets/` |
| `/ab-new <slug>` | Scaffolds a new A/B test using `neogen_ab_bucket()` |
| `/woo-hook <hook> [always\|toggle]` | Scaffolds a WooCommerce hook in mu-plugins or snippets |
| `/theme-override <path>` | Creates a Blocksy child theme override at the correct relative path |

## Plugin policy

- **Prefer mu-plugins or snippets over installing new WP plugins.** Each new plugin is a maintenance liability.
- If a plugin is genuinely needed: free tier only, listed on wordpress.org, actively maintained (flag if last update > 12 months).
- Never suggest the **Code Snippets** plugin — it has crashed the site twice.

## SEO phase map

The in-house SEO stack replaced Rank Math across four phases:

| File | Phase | What it does |
|---|---|---|
| `neogen-seo-engine.php` | R1 | Emits `<title>`, description, canonical, OG, JSON-LD |
| `neogen-seo-metabox.php` | R2 | Per-post override UI + Rank Math data migrator |
| `neogen-seo-cutover.php` | R4 | Toggle: in-house ON, Rank Math OFF |
| `neogen-sitemap.php` | R4 | Forces WP core sitemap; 301-redirects Rank Math sitemap URLs |

All four are active mu-plugins. Rank Math is disabled.

## Redesign phase gating

`neogen-redesign.php` exposes `ng_redesign_active($phase)` and adds `.ngrd-on` body class. New UI work targeting the redesign should be gated behind this function so it can be toggled without a rollback. Check the current phase constants in that file before adding phase-gated markup.

## Brand tokens

- **Rationale / decisions**: `NeoGen Store — Brand Tokens v2.0.md` at repo root (color choices, shadow reasoning, typography).
- **Deployed source of truth**: `:root` block in `mu-plugins/neogen-theme-assets/neogen.css`. When in doubt about a token value, read this file.
- `themes/blocksy-child/theme.json` mirrors the same palette and font families for Site Editor compatibility.

## Scripts (one-time operations)

`scripts/` contains PHP and Python files that are **not deployed** — they are run manually via WP-CLI or browser for one-off data operations (repricing, Arabic title generation via Ollama, gift-card imports). Do not add them to the deploy overlay.

## Safety constraints baked into this project

- **All deploys are production.** There is no dev/staging environment in this repo or the deploy pipeline. Treat every push as live-ready.
- `.gitignore` blocks `.env*`, `*.key`, `*.pem`, `*.sql*`, `wp-config.php`, `*.secret`. Do not add paths that would defeat these.
- Deploy is rate-limited to 20/hr per admin.
- The server-side deploy plugin holds a GitHub PAT with read+write on this repo; never commit one here.
- Site is bilingual EN/AR — all user-facing strings must work in both directions (RTL for Arabic).

## Operations

Full operations contract: `~/sites/_docs/neogen-custom/` (`README.md`, `STACK.md`, `HOSTING.md`, `DEPLOY.md`, `AGENT.md`, `AUTOMATION.md`, `RUNBOOK.md`).

Owning Claude agent: **`wp-woo-standards-auditor`**.

CI: `.github/workflows/claude-ops.yml` (PHP -l + WPCS + secrets scan).

The Notion mirror lives in the **NeoTech Sites & Repos** database.
