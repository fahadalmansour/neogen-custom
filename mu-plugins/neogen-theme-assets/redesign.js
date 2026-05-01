/* ============================================================
   NeoGen Redesign — namespaced JS
   ============================================================
   Source of truth: /tmp/neogen-design/neogen-store/project/*.jsx
   Plan:           /Users/fahadalmansour/.claude/plans/fetch-this-design-file-kind-pizza.md

   All modules attach to window.NGRD. Each module:
     - Early-returns when its root selector is absent
     - Is progressive enhancement only — server markup must be
       fully usable WITHOUT this JS running
     - Honors prefers-reduced-motion where applicable

   Phase 0 ships only the registry + shared utilities. Each
   subsequent phase appends its module in a clearly-marked block
   at the bottom of this file.
   ============================================================ */

(function () {
  'use strict';

  if (window.NGRD) { return; }

  var NGRD = (window.NGRD = {});

  NGRD.prefersReducedMotion = (function () {
    try { return window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
    catch (e) { return false; }
  })();

  NGRD.ready = function (fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  };

  NGRD.q  = function (sel, root) { return (root || document).querySelector(sel); };
  NGRD.qa = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /**
   * Wrap a module entry-point so a thrown error in one module never
   * breaks the registration of the next.
   */
  NGRD.safe = function (name, fn) {
    return function () {
      try { fn(); }
      catch (e) {
        if (window.console && console.error) {
          console.error('[NGRD] module "' + name + '" failed:', e);
        }
      }
    };
  };

  /**
   * Module registry — phases append entries here. Bootstrap runs them
   * all on DOMContentLoaded; each module no-ops when its root is absent
   * so this is safe to call on every page.
   */
  NGRD.modules = {};

  NGRD.register = function (name, fn) {
    NGRD.modules[name] = NGRD.safe(name, fn);
  };

  NGRD.ready(function () {
    var keys = Object.keys(NGRD.modules);
    for (var i = 0; i < keys.length; i++) {
      NGRD.modules[keys[i]]();
    }
  });

  /* ----- Phase modules appended below by later commits ----- */
  /* === Phase 1 · NGRD.heroGallery       ==================== */
  /* === Phase 3 · NGRD.pdpTabs           ==================== */
  /* === Phase 3 · NGRD.addonsFilter      ==================== */
  /* === Phase 6 · NGRD.giftRegionFilter  ==================== */
  /* === Phase 8 · NGRD.quickView         ==================== */
  /* === Phase 9 · NGRD.accountTabs       ==================== */
  /* === Phase 9 · NGRD.copyGiftKey       ==================== */
  /* === Phase 10 · NGRD.authMode         ==================== */
  /* === Phase 10 · NGRD.consentMaster    ==================== */
  /* === Phase 11 · NGRD.trackingSearch   ==================== */
  /* === Phase 13 · NGRD.compareList      ==================== */
  /* === Phase 14 · NGRD.notifToasts      ==================== */
})();

/* ============================================================
   Phase 1 · NGRD.heroGallery
   Source: /tmp/neogen-design/neogen-store/project/homepage.jsx
           (lines 13-16 useEffect interval, 121-149 thumbs/dots)
   Auto-advances every 4s, pauses on hover, respects
   prefers-reduced-motion.
   ============================================================ */
(function () {
  if (!window.NGRD) { return; }
  NGRD.register('heroGallery', function () {
    var root = document.querySelector('[data-ngrd-hero-gallery]');
    if (!root) { return; }
    var slides   = NGRD.qa('[data-ngrd-hero-slide]', root);
    var thumbs   = NGRD.qa('[data-ngrd-hero-thumb]',  root);
    var dots     = NGRD.qa('[data-ngrd-hero-dot]',    root);
    var progress = root.querySelector('[data-ngrd-hero-progress]');
    var total    = slides.length;
    if (total < 2) { return; }
    var idx   = 0;
    var timer = null;

    function show(i) {
      idx = ((i % total) + total) % total;
      slides.forEach(function (el, j) { el.hidden = j !== idx; });
      thumbs.forEach(function (el, j) {
        if (j === idx) { el.setAttribute('aria-current', 'true'); }
        else           { el.removeAttribute('aria-current'); }
      });
      dots.forEach(function (el, j) {
        if (j === idx) { el.setAttribute('aria-current', 'true'); }
        else           { el.removeAttribute('aria-current'); }
      });
      if (progress) {
        progress.style.width = (((idx + 1) / total) * 100) + '%';
      }
    }
    function start() {
      if (NGRD.prefersReducedMotion) { return; }
      stop();
      timer = window.setInterval(function () { show(idx + 1); }, 4000);
    }
    function stop() { if (timer) { window.clearInterval(timer); timer = null; } }

    thumbs.forEach(function (b, i) {
      b.addEventListener('click', function () { show(i); start(); });
    });
    dots.forEach(function (b, i) {
      b.addEventListener('click', function () { show(i); start(); });
    });
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    window.addEventListener('pagehide', stop);

    show(0);
    start();
  });
})();
