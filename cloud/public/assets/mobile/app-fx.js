/* ============================================================
   QUAD EV — انیمیشن‌های اپ مشتری
   ظاهر شدن پلکانی کارت‌ها، موج دکمه‌ها، شمارندهٔ اعداد
   ============================================================ */
(function () {
  "use strict";
  if (!window.matchMedia || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

  var SEL = ".screen > *, .station, .ms-station, .lrow, .card, .card-lite, .qitem, .fx-banner, .fx-empty, .sheet > *, [data-fx]";
  var SKIP = ".tabbar, .appbar, .leaflet-container, .leaflet-container *, script, style, [hidden], .fx-no";
  var WINDOW_MS = 2500;
  var started = Date.now();
  var batch = 0, batchTimer = null;

  var io = "IntersectionObserver" in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (!e.isIntersecting) return;
      io.unobserve(e.target);
      e.target.style.setProperty("--fx-d", Math.min(batch++, 10) * 0.055 + "s");
      clearTimeout(batchTimer);
      batchTimer = setTimeout(function () { batch = 0; }, 120);
      e.target.classList.add("fx-on");
    });
  }, { rootMargin: "0px 0px -6% 0px", threshold: 0.04 }) : null;

  function mark(el) {
    if (!io || el.nodeType !== 1 || el.classList.contains("fx-rev") || el.matches(SKIP) || el.closest(".fx-rev:not(.fx-on)")) return;
    el.classList.add("fx-rev");
    io.observe(el);
  }

  function scan(root) {
    if (root.nodeType !== 1) return;
    if (root.matches && root.matches(SEL)) mark(root);
    root.querySelectorAll && root.querySelectorAll(SEL).forEach(mark);
  }

  function countUp(el) {
    var raw = (el.textContent || "").trim();
    var fa = "۰۱۲۳۴۵۶۷۸۹";
    var ascii = raw.replace(/[۰-۹]/g, function (d) { return fa.indexOf(d); }).replace(/[٬,]/g, "");
    var target = parseFloat(ascii);
    if (!isFinite(target) || target <= 0) return;
    var persian = /[۰-۹]/.test(raw), grouped = /[٬,]/.test(raw);
    var dur = 900, t0 = performance.now();
    function fmt(v) {
      var s = Math.round(v).toString();
      if (grouped) s = s.replace(/\B(?=(\d{3})+(?!\d))/g, "٬");
      return persian ? s.replace(/\d/g, function (d) { return fa[d]; }) : s;
    }
    (function step(now) {
      var p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
      el.textContent = p < 1 ? fmt(target * e) : raw;
      if (p < 1) requestAnimationFrame(step);
    })(t0);
  }

  function ripple(ev) {
    var b = ev.target.closest(".btn");
    if (!b || b.disabled) return;
    var r = b.getBoundingClientRect(), s = Math.max(r.width, r.height);
    var i = document.createElement("span");
    i.className = "fx-ripple";
    i.style.cssText = "width:" + s + "px;height:" + s + "px;left:" + (ev.clientX - r.left - s / 2) + "px;top:" + (ev.clientY - r.top - s / 2) + "px";
    b.appendChild(i);
    setTimeout(function () { i.remove(); }, 650);
  }

  function init() {
    document.querySelectorAll(".screen, .login-sheet, .sheet").forEach(scan);
    document.querySelectorAll("[data-countup]").forEach(countUp);
    document.addEventListener("pointerdown", ripple, { passive: true });

    if ("MutationObserver" in window) {
      var mo = new MutationObserver(function (muts) {
        if (Date.now() - started > WINDOW_MS) { mo.disconnect(); return; }
        muts.forEach(function (m) { m.addedNodes.forEach(scan); });
      });
      document.querySelectorAll(".screen, .sheet").forEach(function (s) { mo.observe(s, { childList: true, subtree: true }); });
      setTimeout(function () { mo.disconnect(); }, WINDOW_MS);
    }
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
