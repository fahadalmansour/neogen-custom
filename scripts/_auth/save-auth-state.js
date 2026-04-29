#!/usr/bin/env node
/**
 * Phase 1 — Manual login → save session state.
 *
 * Run this ONCE per distributor on your own machine:
 *   PORTAL_URL=https://b2b.mindware.com/login \
 *   AUTH_FILE=mindware_auth.json \
 *   READY_SELECTOR='#user-dashboard, .dashboard-welcome, [data-test="account-home"]' \
 *   node scripts/_auth/save-auth-state.js
 *
 * The script opens a real Chromium window. You log in (handle 2FA / CAPTCHA
 * yourself), then it waits for READY_SELECTOR to appear (proof of login),
 * dumps cookies + localStorage to AUTH_FILE, and exits.
 *
 * The auth file is a literal session bearer — anyone with the file can
 * impersonate you. Do NOT commit it. .gitignore already excludes
 * scripts/_auth/*.auth.json.
 *
 * Distributor portals to onboard (open Asbis ME first per plan):
 *   - Mindware            https://www.mindware.com/         (B2B login TBD)
 *   - Asbis ME            https://shop.asbisme.ae/login
 *   - Almasa eDistribution https://b2b.almasa.com.sa/
 *   - Logicom Online      https://logicom.net/online/
 *   - Despec              https://www.despec.com/
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const PORTAL_URL     = process.env.PORTAL_URL;
const AUTH_FILE      = process.env.AUTH_FILE     || 'distributor_auth.json';
const READY_SELECTOR = process.env.READY_SELECTOR;            // optional — falls back to a 90s wait
const WAIT_MS        = parseInt( process.env.WAIT_MS || '90000', 10 );

if ( ! PORTAL_URL ) {
  console.error('ERROR: set PORTAL_URL to the distributor login page.');
  process.exit(1);
}
if ( ! AUTH_FILE.endsWith('.auth.json') && ! AUTH_FILE.endsWith('_auth.json') ) {
  console.error('ERROR: AUTH_FILE must end in .auth.json (gitignore guard).');
  process.exit(1);
}

(async () => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext({
    locale: 'en-SA',
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    viewport: { width: 1366, height: 900 },
  });
  const page = await context.newPage();

  console.log(`opening ${PORTAL_URL} — log in manually, complete 2FA / CAPTCHA in the visible window`);
  await page.goto( PORTAL_URL, { waitUntil: 'domcontentloaded' });

  if ( READY_SELECTOR ) {
    console.log(`waiting for selector: ${READY_SELECTOR} (max ${WAIT_MS / 1000}s)`);
    try {
      await page.waitForSelector( READY_SELECTOR, { timeout: WAIT_MS });
      console.log('login detected via READY_SELECTOR');
    } catch (e) {
      console.error(`ERROR: READY_SELECTOR not seen within ${WAIT_MS}ms — login failed?`);
      await browser.close();
      process.exit(2);
    }
  } else {
    console.log(`no READY_SELECTOR — sleeping ${WAIT_MS / 1000}s. Press Ctrl+C if done sooner.`);
    await page.waitForTimeout( WAIT_MS );
  }

  const out = path.resolve( AUTH_FILE );
  await context.storageState({ path: out });
  console.log(`session saved → ${out}`);
  console.log('this file is a session bearer — keep it secret, never commit.');
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
