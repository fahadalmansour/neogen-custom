// NeoGen brand audit — headless fetch of https://neogen.store/
// Runs on pve1 (clean egress). Writes:
//   audit/homepage.html
//   audit/tokens.json
// Usage: node audit/fetch.mjs

import { chromium } from 'playwright';
import { writeFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const OUT_HTML   = resolve(__dirname, 'homepage.html');
const OUT_TOKENS = resolve(__dirname, 'tokens.json');
const URL = 'https://neogen.store/';

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({
  userAgent:
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
  viewport: { width: 1440, height: 900 },
  locale: 'en-US',
});
const page = await ctx.newPage();

let loadErr = null;
try {
  await page.goto(URL, { waitUntil: 'networkidle', timeout: 45000 });
} catch (e) {
  loadErr = String(e);
  // fallback: try domcontentloaded if networkidle times out
  try { await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 30000 }); } catch {}
}

const html = await page.content();
writeFileSync(OUT_HTML, html, 'utf8');

const tokens = await page.evaluate(() => {
  const out = {};
  const body = document.body;
  const bs = getComputedStyle(body);
  out.body = {
    fontFamily: bs.fontFamily,
    color: bs.color,
    backgroundColor: bs.backgroundColor,
    direction: bs.direction,
    lang: document.documentElement.getAttribute('lang') || null,
  };

  const h1 = document.querySelector('h1');
  if (h1) {
    const s = getComputedStyle(h1);
    out.h1 = {
      text: (h1.textContent || '').trim().slice(0, 140),
      fontFamily: s.fontFamily,
      fontWeight: s.fontWeight,
      color: s.color,
      fontSize: s.fontSize,
      letterSpacing: s.letterSpacing,
    };
  } else {
    out.h1 = null;
  }

  // find likely primary button
  const candidates = Array.from(document.querySelectorAll(
    'a.button, button.button, .wp-block-button__link, a.wp-block-button__link, .ct-button, a.ct-button, .single_add_to_cart_button, .add_to_cart_button, button[type="submit"]'
  ));
  out.primaryButton = null;
  for (const el of candidates) {
    const s = getComputedStyle(el);
    const bg = s.backgroundColor;
    const bgImg = s.backgroundImage;
    // skip transparent-bg things first; keep any with color or gradient
    if (bg !== 'rgba(0, 0, 0, 0)' || (bgImg && bgImg !== 'none')) {
      out.primaryButton = {
        selector: el.tagName.toLowerCase() + (el.className ? '.' + String(el.className).trim().split(/\s+/).join('.') : ''),
        text: (el.textContent || '').trim().slice(0, 60),
        backgroundColor: bg,
        backgroundImage: bgImg,
        color: s.color,
        fontFamily: s.fontFamily,
        fontWeight: s.fontWeight,
        fontSize: s.fontSize,
        borderColor: s.borderColor,
        borderRadius: s.borderRadius,
      };
      break;
    }
  }
  if (!out.primaryButton && candidates.length) {
    const el = candidates[0];
    const s = getComputedStyle(el);
    out.primaryButton = {
      selector: el.tagName.toLowerCase() + (el.className ? '.' + String(el.className).trim().split(/\s+/).join('.') : ''),
      text: (el.textContent || '').trim().slice(0, 60),
      backgroundColor: s.backgroundColor,
      backgroundImage: s.backgroundImage,
      color: s.color,
      fontFamily: s.fontFamily,
      fontWeight: s.fontWeight,
      fontSize: s.fontSize,
      borderColor: s.borderColor,
      borderRadius: s.borderRadius,
      note: 'no non-transparent candidate; first match returned',
    };
  }

  // all --* custom properties on :root (computed)
  const rootStyle = getComputedStyle(document.documentElement);
  const rootProps = {};
  // CSSStyleDeclaration is iterable of property names
  for (let i = 0; i < rootStyle.length; i++) {
    const prop = rootStyle[i];
    if (prop && prop.startsWith('--')) {
      rootProps[prop] = rootStyle.getPropertyValue(prop).trim();
    }
  }
  const rootEntries = Object.entries(rootProps);
  out.rootCustomProps_count = rootEntries.length;
  out.rootCustomProps_first30 = Object.fromEntries(rootEntries.slice(0, 30));
  out.rootCustomProps_accentLike = Object.fromEntries(
    rootEntries.filter(([k, v]) =>
      /accent|brand|primary|link|button|cta|electric|hot|cyan|blue/i.test(k) ||
      /#00d1ff|#0099cc|#66e4ff|0,\s*209,\s*255/i.test(v)
    )
  );
  out.rootCustomProps_warmMetallicHits = Object.fromEntries(
    rootEntries.filter(([, v]) => /#b8[0-9a-f]{4}|#d4af37|gold|brass|copper/i.test(v))
  );

  // logo detection
  const logoSelectors = [
    '.site-logo img', '.site-logo svg',
    '.custom-logo', 'img.custom-logo',
    '.site-title', '.site-branding img', '.site-branding svg',
    '.ct-header-logo img', '.ct-header-logo svg', '.ct-header-logo',
    'a.site-logo-container', 'header img[alt*="logo" i]', 'header img[alt*="NeoGen" i]',
  ];
  out.logo = { found: [] };
  for (const sel of logoSelectors) {
    const nodes = document.querySelectorAll(sel);
    nodes.forEach((n) => {
      const snapshot = {
        selector: sel,
        tag: n.tagName.toLowerCase(),
        outerHTMLSnippet: n.outerHTML.slice(0, 600),
      };
      if (n.tagName === 'IMG') {
        snapshot.src = n.getAttribute('src');
        snapshot.alt = n.getAttribute('alt');
        snapshot.width = n.getAttribute('width');
        snapshot.height = n.getAttribute('height');
      }
      out.logo.found.push(snapshot);
    });
  }
  // also search entire header for the text "NEOGEN" or "NeoGen"
  const header = document.querySelector('header') || document.body;
  const wordmarkHits = [];
  const walker = document.createTreeWalker(header, NodeFilter.SHOW_TEXT);
  let tn;
  while ((tn = walker.nextNode())) {
    const t = tn.nodeValue || '';
    if (/neogen/i.test(t) && t.trim().length < 80) {
      wordmarkHits.push({
        text: t.trim(),
        parentTag: tn.parentElement ? tn.parentElement.tagName.toLowerCase() : null,
        parentClass: tn.parentElement ? tn.parentElement.className : null,
      });
      if (wordmarkHits.length >= 6) break;
    }
  }
  out.logo.wordmarkTextHits = wordmarkHits;

  // scan all stylesheet + inline style text (best-effort, same-origin only) for deprecated warm-metallic tokens
  const warmRe = /#b8[0-9a-f]{4}\b|#d4af37\b|\bgold\b|\bbrass\b|\bcopper\b/gi;
  const warmHits = new Set();
  for (const ss of Array.from(document.styleSheets)) {
    try {
      const rules = ss.cssRules;
      if (!rules) continue;
      for (const r of Array.from(rules)) {
        const txt = r.cssText || '';
        const m = txt.match(warmRe);
        if (m) m.forEach((x) => warmHits.add(x.toLowerCase()));
      }
    } catch {
      // cross-origin stylesheet — skip silently
    }
  }
  // inline <style> blocks
  document.querySelectorAll('style').forEach((s) => {
    const m = (s.textContent || '').match(warmRe);
    if (m) m.forEach((x) => warmHits.add(x.toLowerCase()));
  });
  out.deprecatedWarmMetallicHits = Array.from(warmHits);

  // font-face families observed (best effort)
  const fontFamilies = new Set();
  for (const ss of Array.from(document.styleSheets)) {
    try {
      for (const r of Array.from(ss.cssRules || [])) {
        if (r.type === 5 /* @font-face */ && r.style) {
          const f = r.style.getPropertyValue('font-family');
          if (f) fontFamilies.add(f.replace(/['"]/g, '').trim());
        }
      }
    } catch {}
  }
  out.fontFacesLoaded = Array.from(fontFamilies);

  return out;
});

tokens._meta = {
  url: URL,
  loadError: loadErr,
  fetchedAt: new Date().toISOString(),
};

writeFileSync(OUT_TOKENS, JSON.stringify(tokens, null, 2), 'utf8');

await browser.close();
console.log('OK — wrote', OUT_HTML, 'and', OUT_TOKENS);
