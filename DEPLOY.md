# DEPLOY — NeoGen Custom code workflow

How to edit WordPress files on **neogen.store** from your Mac using the NeoGen Deploy plugin.

## Architecture

```
                   ┌─────────────────────────┐
                   │ Mac (VS Code)           │
                   │ /Users/fahadalmansour/  │
                   │   ngs/neogen-custom/    │
                   └───────────┬─────────────┘
                               │ git push
                               ▼
                   ┌─────────────────────────┐
                   │ GitHub private repo     │
                   │ fahadalmansour/         │
                   │   neogen-custom         │
                   └───────────┬─────────────┘
                               │ git pull (triggered by WP admin)
                               ▼
                   ┌─────────────────────────┐
                   │ blazr.net WP server     │
                   │ /var/www/ngs1/          │
                   │   wp-content/mu-plugins/│
                   │     neogen-custom/      │
                   │     neogen-custom-      │
                   │       loader.php  ←─── auto-generated
                   └─────────────────────────┘
```

## Daily workflow

1. **Edit locally** in VS Code. Files live at:
   - `mu-plugins/*.php` — always-active PHP (hooks, filters, admin bar items)
   - `themes/blocksy-child/**` — child-theme overlay (template parts, CSS)
   - `plugins/neogen-snippets/*.php` — toggle-able snippets

2. **Bump version** (optional but recommended for audit trail):
   ```bash
   # Update VERSION file + the plugin header version string
   echo "1.0.2" > VERSION
   # Edit mu-plugins/neogen-site-custom.php → update `Version:` line + NEOGEN_CUSTOM_VERSION constant
   ```

3. **Commit + push**:
   ```bash
   git add -A
   git commit -m "Add WhatsApp float button to product pages"
   git push
   ```

4. **Deploy** via WP admin:
   - Go to https://neogen.store/wp-admin/tools.php?page=neogen-deploy
   - Click **Pull Latest**
   - Wait 2-5 seconds
   - Check admin bar shows the new version: `🚀 NG x.x.x`

## Rollback

Something broke on live? Go back one commit:

1. Go to https://neogen.store/wp-admin/tools.php?page=neogen-deploy
2. Click **Rollback · −1 commit**
3. Confirm
4. Admin bar shows the previous version.

For rollback beyond 1 step: do it locally.

```bash
cd /Users/fahadalmansour/ngs/neogen-custom
git revert HEAD --no-edit   # create inverse commit
git push
```
Then click **Pull Latest** in WP admin.

## Writing theme overrides

Blocksy Child overlay pattern: copy the parent theme file you want to change into `themes/blocksy-child/` at the same relative path, edit, commit, deploy.

Example: override the single-product template
1. Copy `wp-content/themes/blocksy/woocommerce/single-product.php` → `themes/blocksy-child/woocommerce/single-product.php`
2. Edit
3. Push
4. **Note**: This plugin clones into `mu-plugins/neogen-custom/` — Blocksy won't automatically overlay the theme. For full theme-override workflow, ask about setting up a second deploy target at `themes/blocksy-child/`.

## Writing toggleable snippets

1. Create a new `.php` file in `plugins/neogen-snippets/` (alongside `neogen-snippets.php`)
2. Use WP hooks directly, e.g. `add_filter('the_content', ...)`
3. Commit + deploy

The loader picks up every `*.php` file automatically.

## Safety caveats

- **All deploys go to production.** There's no staging.
- **PHP syntax-check runs before every pull** — broken commits refuse to deploy.
- **Rate limit**: 20 deploys/hour per admin user.
- **Rollback only goes back 1 commit** via the UI. For deeper rollback, revert locally + push + pull.
- **The PAT has read+write** on this repo. If it leaks, rotate it immediately:
  https://github.com/settings/personal-access-tokens
- **Never commit secrets**. `.gitignore` blocks `.env`, `*.key`, `*.sql`, etc. but human vigilance is the last line.

## Teardown — when you get real SSH from blazr

Once blazr enables SSH + you set up VS Code Remote-SSH:

1. Stop using the **Pull Latest** button.
2. WP admin → Plugins → **NeoGen Deploy** → Deactivate → Delete.
3. The deployed code at `wp-content/mu-plugins/neogen-custom/` stays intact — you now edit it directly via SSH.
4. The loader file `wp-content/mu-plugins/neogen-custom-loader.php` also stays — it keeps loading your code.
5. Revoke the GitHub PAT at https://github.com/settings/personal-access-tokens.

You keep the repo. You keep the code. You just stop using this plugin as the deploy mechanism.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| "PAT not set" in plugin UI | Settings not saved / encryption key changed | Re-paste PAT + click Commit config |
| "Syntax check failed" | A `.php` file in the commit has a parse error | Fix locally + push again |
| Admin bar doesn't show `🚀 NG x.x.x` | Loader not installed, or mu-plugin file missing | Click **Pull Latest** once to trigger loader regeneration |
| "Rate limited" | 20+ deploys in the last hour | Wait; check the rate-limit readout in the Telemetry panel |
| `git` errors in output pane | PAT expired / repo permission revoked | Rotate PAT + re-save |
| Fatal error after deploy | Broken PHP snuck past syntax check (rare) | Click **Rollback · −1 commit** immediately |

## Deploy log

Server log is at `wp-content/uploads/neogen-deploy.log` (JSON lines).
WP admin → Tools → NeoGen Deploy shows the last 30 entries at the bottom of the page.

## Version history

| Plugin | Date | Change |
|---|---|---|
| 1.0.0 | 2026-04-21 | Initial release |
| 1.0.1 | 2026-04-22 | Auto-install `<target>-loader.php` in `mu-plugins/` on every clone/pull |
