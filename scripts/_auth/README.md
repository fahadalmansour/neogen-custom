# Distributor auth-bridge scrape

Two-phase pattern for pulling **live, authenticated** wholesale pricing from
distributor B2B portals (Mindware, Asbis ME, Almasa, Logicom, Despec, etc.)
without trying to automate login forms (which trigger CAPTCHAs / 2FA).

## Phase 1 — manually log in once, save the session

Run on your laptop. Browser opens visibly so you can log in by hand and
clear any 2FA / CAPTCHA. The script then dumps cookies + localStorage to a
local JSON file.

```bash
PORTAL_URL='https://shop.asbisme.ae/login' \
AUTH_FILE='asbis.auth.json' \
READY_SELECTOR='[data-test="account-dashboard"], .user-menu' \
node scripts/_auth/save-auth-state.js
```

If the distributor portal has no obvious post-login element, omit
`READY_SELECTOR` — the script falls back to a 90-second wait, plenty of
time to log in and accept any prompts.

`.gitignore` excludes every `*.auth.json` file. **Never commit one** —
they're literal session bearers.

## Phase 2 — authenticated scrape

Once the auth file exists, hit a list of distributor product or search
URLs and extract SKU + wholesale price + stock for each.

```bash
# Make a target list (one URL per line, or JSON [{url, sku}, …])
cat > /tmp/asbis-targets.txt <<EOF
https://shop.asbisme.ae/products/ubiquiti-udm-pro
https://shop.asbisme.ae/products/synology-ds1621
https://shop.asbisme.ae/products/caldigit-ts4
EOF

AUTH_FILE='asbis.auth.json' \
TARGETS_FILE='/tmp/asbis-targets.txt' \
PRICE_SELECTOR='.product-price, [data-price]' \
STOCK_SELECTOR='.stock-status, [data-stock]' \
node scripts/_auth/scrape-with-state.js > /tmp/asbis-prices.json
```

Adjust `PRICE_SELECTOR` and `STOCK_SELECTOR` per portal — every distributor
markup is different. Inspect the page in DevTools, find the element, copy
its CSS selector.

If a row comes back with `"error": "session_expired"`, the cookies are
stale — rerun Phase 1.

## Phase 3 — feed into a NeoGen reprice

Once you have a confirmed-cost JSON, hand it back to the agent and ask for
a reprice. The agent will:
1. Match each row to a NeoGen SKU (by exact SKU or fuzzy product name).
2. Compute landed cost (wholesale × 1.05 freight × 1.15 VAT for hardware).
3. Compute target retail at your margin floor.
4. Generate a `scripts/neogen-reprice-from-quote-<distributor>-<date>.php`
   that backs up current prices and applies the new ones, with the
   wholesale source stamped into post meta for full traceability.

This pattern is the only way to do pricing on this site honestly — the
"REAL" columns in `data/financials/NeoGen_Supplier_Research_v2.xlsx` get
populated, the Amazon.sa-derived estimates get retired, and every
catalog price has a real source.
