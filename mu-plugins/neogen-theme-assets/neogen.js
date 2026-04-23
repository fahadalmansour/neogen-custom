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
