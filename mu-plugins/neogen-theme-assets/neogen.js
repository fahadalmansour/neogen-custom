/* NeoGen Theme — sysbar clock, cursor glow, scroll reveal.
   Ported from preview IIFEs. No deps, no build step. */
(function () {
  'use strict';

  // --- UTC clock + queue drift -------------------------------------------
  (function clock() {
    var el = document.getElementById('ng-clock');
    var queue = document.getElementById('ng-queue');
    if (!el) return;
    var pad = function (n) { return String(n).padStart(2, '0'); };
    function tick() {
      var d = new Date();
      el.textContent = pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds());
    }
    tick();
    setInterval(tick, 1000);
    if (queue) {
      var n = parseInt(queue.textContent, 10);
      if (isNaN(n)) n = 14;
      setInterval(function () {
        var delta = Math.random() < 0.5 ? -1 : 1;
        n = Math.max(8, Math.min(22, n + delta));
        queue.textContent = n;
      }, 4200);
    }
  })();

  // --- Cursor-reactive hero glow -----------------------------------------
  (function glow() {
    var mm = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (mm.matches) return;
    var root = document.documentElement;
    var raf = 0;
    var tx = 50, ty = 50, cx = 50, cy = 50;
    window.addEventListener('mousemove', function (e) {
      tx = (e.clientX / window.innerWidth) * 100;
      ty = (e.clientY / window.innerHeight) * 100;
      if (!raf) raf = requestAnimationFrame(update);
    }, { passive: true });
    function update() {
      cx += (tx - cx) * 0.12;
      cy += (ty - cy) * 0.12;
      root.style.setProperty('--mx', cx.toFixed(2) + '%');
      root.style.setProperty('--my', cy.toFixed(2) + '%');
      raf = Math.abs(tx - cx) > 0.1 || Math.abs(ty - cy) > 0.1 ? requestAnimationFrame(update) : 0;
    }
  })();

  // --- Strip stale brand-version stamps -------------------------------
  // "v6.0", "إصدار مقفل", "دليل العلامة 1.1 · مطبَّق" — none of these are
  // emitted by this codebase any more, but they still surface on live
  // HTML, injected by an upstream layer (Blocksy / a stale block /
  // a saved widget). Scrub them at runtime so shoppers don't see
  // implementation noise. Belt-and-suspenders: rip this out once the
  // upstream emitter is located.
  (function scrubVersionLabels() {
    var BLACKLIST = [/\bv6\.0\b/i, /إصدار\s+مقفل/, /دليل\s+العلامة/];
    function scrub(root) {
      if (!root) return;
      var w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
      var hits = [];
      while (w.nextNode()) {
        var t = w.currentNode.textContent || '';
        for (var i = 0; i < BLACKLIST.length; i++) {
          if (BLACKLIST[i].test(t)) { hits.push(w.currentNode); break; }
        }
      }
      hits.forEach(function (n) {
        var p = n.parentElement;
        if (p && p.children.length === 0) {
          p.style.display = 'none';
        } else {
          n.textContent = '';
        }
      });
    }
    function run() { scrub(document.body); }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
      run();
    }
    // One late re-scan covers content that hydrates after first paint.
    setTimeout(run, 1500);
  })();

  // --- Demote cart/offcanvas H1 → H2 ------------------------------------
  // Blocksy renders the empty-cart heading as <h1>السلة فارغة</h1> inside
  // the offcanvas panel. Even when the panel is closed it sits in the DOM
  // and bots/screen-readers count it as a page-level H1. We replace any
  // H1 that lives inside the cart/offcanvas containers with an H2. Runs
  // on DOMContentLoaded and again whenever Blocksy refreshes the panel.
  (function demoteOffcanvasH1() {
    var SELECTORS = '#woo-cart-panel h1, .ct-cart-content h1, #offcanvas h1, .ct-panel h1';
    function demote() {
      document.querySelectorAll(SELECTORS).forEach(function (h) {
        var h2 = document.createElement('h2');
        h2.className = h.className;
        for (var i = 0; i < h.attributes.length; i++) {
          var a = h.attributes[i];
          if (a.name !== 'class') h2.setAttribute(a.name, a.value);
        }
        h2.innerHTML = h.innerHTML;
        h.parentNode.replaceChild(h2, h);
      });
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', demote, { once: true });
    } else {
      demote();
    }
    // Re-run after WC ajax fragments refresh (Blocksy re-injects panel).
    document.addEventListener('wc_fragments_refreshed', demote);
    document.addEventListener('updated_cart_totals', demote);
  })();

  // --- IntersectionObserver scroll reveal --------------------------------
  (function reveal() {
    var els = document.querySelectorAll('.reveal');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) {
      els.forEach(function (e) { e.classList.add('in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry, i) {
        if (entry.isIntersecting) {
          entry.target.style.transitionDelay = (i % 5) * 60 + 'ms';
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.14, rootMargin: '0px 0px -80px 0px' });
    els.forEach(function (e) { io.observe(e); });
  })();
})();
