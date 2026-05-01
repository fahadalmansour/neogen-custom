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

/* ============================================================
   === Redesign Phase 1 (v1.38.0) JS modules ===================
   Hero gallery, PDP tabs, addons filter, shop filter collapse,
   carrier picker. All modules early-return if their root DOM
   element is absent so a single neogen.js can serve every page.
   ============================================================ */
(function () {
  'use strict';

  var prefersReducedMotion = (function () {
    try { return window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
    catch (e) { return false; }
  })();

  // ----- Homepage hero gallery: auto-advance + thumb / dot click -----
  function heroGallery() {
    var root = document.querySelector('[data-ng-hero-gallery]');
    if (!root) { return; }
    var slides   = Array.prototype.slice.call(root.querySelectorAll('[data-ng-hero-slide]'));
    var thumbs   = Array.prototype.slice.call(root.querySelectorAll('[data-ng-hero-thumb]'));
    var dots     = Array.prototype.slice.call(root.querySelectorAll('[data-ng-hero-dot]'));
    var progress = root.querySelector('[data-ng-hero-progress]');
    var total    = slides.length;
    if (total < 2) { return; }
    var idx = 0;
    var timer = null;
    function show(i) {
      idx = ((i % total) + total) % total;
      slides.forEach(function (el, j) { el.hidden = j !== idx; });
      thumbs.forEach(function (el, j) {
        if (j === idx) { el.setAttribute('aria-current', 'true'); }
        else { el.removeAttribute('aria-current'); }
      });
      dots.forEach(function (el, j) {
        if (j === idx) { el.setAttribute('aria-current', 'true'); }
        else { el.removeAttribute('aria-current'); }
      });
      if (progress) { progress.style.width = (((idx + 1) / total) * 100) + '%'; }
    }
    function start() {
      if (prefersReducedMotion) { return; }
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
  }

  // ----- PDP tabs: click + keyboard nav, ARIA-correct -----
  function pdpTabs() {
    var lists = document.querySelectorAll('[data-ng-pdp-tabs]');
    if (!lists.length) { return; }
    Array.prototype.forEach.call(lists, function (list) {
      var tabs   = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
      var panels = tabs.map(function (t) {
        var id = t.getAttribute('aria-controls');
        return id ? document.getElementById(id) : null;
      });
      function activate(i) {
        tabs.forEach(function (t, j) {
          var on = i === j;
          t.setAttribute('aria-selected', on ? 'true' : 'false');
          t.tabIndex = on ? 0 : -1;
          if (panels[j]) {
            panels[j].dataset.active = on ? 'true' : 'false';
            panels[j].setAttribute('aria-hidden', on ? 'false' : 'true');
          }
        });
      }
      tabs.forEach(function (t, i) {
        t.addEventListener('click', function () { activate(i); });
        t.addEventListener('keydown', function (e) {
          var k = e.key, n = tabs.length, ni = i;
          if (k === 'ArrowRight' || k === 'ArrowLeft') {
            // Honour RTL: in dir=rtl, ArrowRight visually means "previous"
            var dir = (document.documentElement.getAttribute('dir') === 'rtl') ? -1 : 1;
            ni = (i + (k === 'ArrowRight' ? dir : -dir) + n) % n;
          } else if (k === 'Home') { ni = 0; }
          else if (k === 'End')  { ni = n - 1; }
          else { return; }
          e.preventDefault();
          activate(ni);
          tabs[ni].focus();
        });
      });
    });
  }

  // ----- PDP add-ons filter (chip toggles addon visibility by data-type) -----
  function addonsFilter() {
    var groups = document.querySelectorAll('[data-ng-addons-filter]');
    if (!groups.length) { return; }
    Array.prototype.forEach.call(groups, function (group) {
      var buttons = Array.prototype.slice.call(group.querySelectorAll('button[data-filter]'));
      var grid    = document.querySelector(group.getAttribute('data-target') || '[data-ng-addons-grid]');
      if (!grid) { return; }
      var cards   = Array.prototype.slice.call(grid.querySelectorAll('[data-type]'));
      buttons.forEach(function (b) {
        b.addEventListener('click', function () {
          var f = b.getAttribute('data-filter') || 'all';
          buttons.forEach(function (other) {
            other.setAttribute('aria-pressed', other === b ? 'true' : 'false');
          });
          cards.forEach(function (c) {
            var t = c.getAttribute('data-type');
            c.hidden = !(f === 'all' || t === f);
          });
        });
      });
    });
  }

  // ----- Shop filters: collapsible groups -----
  function shopFiltersCollapse() {
    var heads = document.querySelectorAll('.ng-filter-head');
    if (!heads.length) { return; }
    Array.prototype.forEach.call(heads, function (h) {
      h.addEventListener('click', function () {
        var group = h.closest('.ng-filter-group');
        if (!group) { return; }
        var collapsed = group.dataset.collapsed === 'true';
        group.dataset.collapsed = collapsed ? 'false' : 'true';
        h.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
      });
    });
  }

  // ----- Checkout carrier picker: click anywhere on card to select -----
  function carrierPicker() {
    var cards = document.querySelectorAll('.ng-carrier-card');
    if (!cards.length) { return; }
    Array.prototype.forEach.call(cards, function (card) {
      card.addEventListener('click', function (e) {
        var radio = card.querySelector('input[type="radio"]');
        if (!radio) { return; }
        // Avoid double-trigger when the click already came from the radio.
        if (e.target !== radio) { radio.checked = true; }
        var name = radio.name;
        var siblings = name ? document.querySelectorAll('input[type="radio"][name="' + name + '"]') : [];
        Array.prototype.forEach.call(siblings, function (r) {
          var sibCard = r.closest('.ng-carrier-card');
          if (sibCard) { sibCard.dataset.active = r.checked ? 'true' : 'false'; }
        });
        // WC checkout uses change events to update totals — fire it.
        try {
          var ev = new Event('change', { bubbles: true });
          radio.dispatchEvent(ev);
        } catch (err) { /* ignore */ }
      });
    });
  }

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    try { heroGallery();          } catch (e) { /* no-op */ }
    try { pdpTabs();              } catch (e) { /* no-op */ }
    try { addonsFilter();         } catch (e) { /* no-op */ }
    try { shopFiltersCollapse();  } catch (e) { /* no-op */ }
    try { carrierPicker();        } catch (e) { /* no-op */ }
  });
})();
/* === End Redesign Phase 1 JS ============================== */
