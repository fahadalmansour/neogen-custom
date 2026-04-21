# neogen-custom

Deployable custom code for **neogen.store**. Pulled by the `neogen-deploy` WordPress plugin into the live site.

## Repository layout

```
.
├── mu-plugins/               → copied to wp-content/mu-plugins/ on deploy (auto-active)
│   └── neogen-site-custom.php
├── themes/
│   └── blocksy-child/        → overlaid onto wp-content/themes/blocksy-child/ on deploy
├── plugins/
│   └── neogen-snippets/      → copied to wp-content/plugins/neogen-snippets/ on deploy
├── DEPLOY.md                 → workflow docs
└── VERSION                   → incremented on every deploy
```

## Workflow

1. Edit files here locally in VS Code.
2. Commit + push to this repo.
3. Open `https://neogen.store/wp-admin/tools.php?page=neogen-deploy` → click **Pull Latest**.
4. Plugin pulls the new HEAD, runs `php -l` on changed `.php` files, shows diff, deploys.

## Rollback

In WP admin → NeoGen Deploy → click **Rollback** (reverts to previous commit).
Or locally: `git revert HEAD && git push` → deploy again.

## Safety rules

- Never commit secrets (DB creds, API keys). `.gitignore` blocks common patterns.
- Every `.php` file is syntax-checked before deploy; broken commits refuse to deploy.
- Deploys are rate-limited (20/hr) and admin-only in WP admin.
- All deploys logged with commit hash + timestamp.
