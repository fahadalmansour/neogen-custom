#!/usr/bin/env node
/**
 * Phase 2 — authenticated scrape using a saved session state.
 *
 * Loads the session file produced by save-auth-state.js, hits a list of
 * distributor product / search URLs, extracts SKU / wholesale price /
 * stock, and emits JSON to stdout.
 *
 * Configure via env vars:
 *   AUTH_FILE          — path to *.auth.json from Phase 1 (required)
 *   TARGETS_FILE       — path to a newline-separated text file of URLs
 *                        OR JSON array of {url, sku} objects. (required)
 *   PRICE_SELECTOR     — CSS selector for the wholesale price element
 *   STOCK_SELECTOR     — CSS selector for stock / availability text
 *   SKU_SELECTOR       — optional, defaults to scraping page title / h1
 *   THROTTLE_MS        — pause between requests (default 4000)
 *   HEADLESS           — "0" to debug visibly (default headless)
 *
 * Output JSON shape:
 *   [{ url, sku, title, price_text, price_sar, stock_text, captured_at, error? }, …]
 *
 * If a request returns the login page (session expired), the row is
 * tagged { error: "session_expired" } so you know to rerun Phase 1.
 */

const { chromium } = require('playwright');
const fs   = require('fs');
const path = require('path');

const AUTH_FILE      = process.env.AUTH_FILE;
const TARGETS_FILE   = process.env.TARGETS_FILE;
const PRICE_SELECTOR = process.env.PRICE_SELECTOR || '[itemprop="price"], .price, [class*="price"]';
const STOCK_SELECTOR = process.env.STOCK_SELECTOR || '[class*="stock"], [class*="availab"]';
const SKU_SELECTOR   = process.env.SKU_SELECTOR   || '[itemprop="sku"], .sku, [data-sku]';
const THROTTLE_MS    = parseInt( process.env.THROTTLE_MS || '4000', 10 );
const HEADLESS       = process.env.HEADLESS !== '0';

if ( ! AUTH_FILE || ! fs.existsSync( AUTH_FILE ) ) {
  console.error('ERROR: AUTH_FILE missing. Run save-auth-state.js first.');
  process.exit(1);
}
if ( ! TARGETS_FILE || ! fs.existsSync( TARGETS_FILE ) ) {
  console.error('ERROR: TARGETS_FILE missing.');
  process.exit(1);
}

const raw = fs.readFileSync( TARGETS_FILE, 'utf8' ).trim();
let targets;
try {
  targets = JSON.parse( raw );
  if ( ! Array.isArray( targets ) ) throw new Error('not array');
} catch {
  targets = raw.split('\n').map(s => s.trim()).filter(Boolean).map(url => ({ url }));
}

const sleep = ms => new Promise(r => setTimeout(r, ms));
const sarRe = /([0-9]{1,3}(?:[, ]?[0-9]{3})*(?:\.[0-9]{1,2})?)\s*(?:SAR|ر\.?س|﷼)/i;

(async () => {
  const browser = await chromium.launch({ headless: HEADLESS });
  const context = await browser.newContext({ storageState: AUTH_FILE });
  const page    = await context.newPage();
  const out     = [];

  for ( let i = 0; i < targets.length; i++ ) {
    const t = targets[i];
    process.stderr.write(`[${i+1}/${targets.length}] ${t.url}\n`);
    try {
      const r = await page.goto( t.url, { waitUntil: 'domcontentloaded', timeout: 30000 } );
      const status = r ? r.status() : null;
      const url    = page.url();
      // Detect session expiry (redirected to /login or shows "Sign in")
      if ( /login|signin|sign-in/i.test( url ) ) {
        out.push({ ...t, error: 'session_expired', resolved_url: url });
        continue;
      }
      await page.waitForTimeout( 1500 );
      const data = await page.evaluate( ({ pSel, sSel, kSel }) => {
        const txt = el => el ? (el.innerText || el.textContent || '').trim() : null;
        const price = txt( document.querySelector( pSel ) );
        const stock = txt( document.querySelector( sSel ) );
        const sku   = txt( document.querySelector( kSel ) )
                    || document.querySelector('h1')?.innerText?.trim();
        const title = document.title;
        return { price_text: price, stock_text: stock, sku, title };
      }, { pSel: PRICE_SELECTOR, sSel: STOCK_SELECTOR, kSel: SKU_SELECTOR });

      const m = data.price_text && data.price_text.match( sarRe );
      const price_sar = m ? parseFloat( m[1].replace(/[, ]/g, '') ) : null;

      out.push({ ...t, ...data, price_sar, status, captured_at: new Date().toISOString() });
    } catch (e) {
      out.push({ ...t, error: String(e).slice(0, 200) });
    }
    await sleep( THROTTLE_MS );
  }

  await browser.close();
  console.log( JSON.stringify( out, null, 2 ) );
})().catch( e => { console.error(e); process.exit(1); } );
