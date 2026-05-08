/* NeoGen Theme — sysbar clock, cursor glow, scroll reveal.
   Ported from preview IIFEs. No deps, no build step. */
(function () {
  'use strict';

  // --- Riyadh clock + queue drift ----------------------------------------
  (function clock() {
    var el = document.getElementById('ng-clock');
    var queue = document.getElementById('ng-queue');
    if (!el) return;
    function tick() {
      el.textContent = new Date().toLocaleTimeString('en-GB', {
        timeZone: 'Asia/Riyadh',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        hour12: false
      });
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
  // CSS keeps .reveal at opacity:1 by default so the page is readable
  // without JS (crawlers, slow networks, prefers-reduced-motion users).
  // We opt in to the animation by adding .ng-reveal-ready on <html>,
  // which activates the .reveal{opacity:0; transform:translateY(24px)}
  // rule scoped under that ancestor. IO then toggles .in to fade in.
  (function reveal() {
    var els = document.querySelectorAll('.reveal');
    if (!els.length) return;
    document.documentElement.classList.add('ng-reveal-ready');
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

  // --- Horizontal product deck — arrow controls (v1.25.0) ----------------
  // Wires .ng-deck-arrow buttons inside .ng-deck-wrap to scrollBy() on the
  // sibling .ng-product-grid--deck. Disables the prev arrow at scrollLeft
  // 0 and the next arrow at scrollEnd, via data-disabled (CSS handles the
  // visual). Native swipe / drag still works on touch.
  (function deckArrows() {
    document.querySelectorAll('.ng-deck-wrap').forEach(function (wrap) {
      var deck = wrap.querySelector('.ng-product-grid--deck');
      if (!deck) return;
      var prev = wrap.querySelector('.ng-deck-arrow--prev');
      var next = wrap.querySelector('.ng-deck-arrow--next');
      function step(d) {
        deck.scrollBy({ left: d * Math.max(280, deck.clientWidth * 0.7), behavior: 'smooth' });
      }
      if (prev) prev.addEventListener('click', function () { step(-1); });
      if (next) next.addEventListener('click', function () { step( 1); });
      function update() {
        var atStart = deck.scrollLeft <= 1;
        var atEnd = deck.scrollLeft >= deck.scrollWidth - deck.clientWidth - 1;
        if (prev) prev.dataset.disabled = atStart ? 'true' : 'false';
        if (next) next.dataset.disabled = atEnd ? 'true' : 'false';
      }
      deck.addEventListener('scroll', update, { passive: true });
      window.addEventListener('resize', update);
      update();
    });
  })();
})();
