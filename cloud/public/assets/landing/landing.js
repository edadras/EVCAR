(function () {
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fa = function (n) { return Number(n).toLocaleString('fa-IR'); };

  var nav = document.getElementById('nav');
  var heroBg = document.getElementById('heroBg');
  var ticking = false;
  function onScroll() {
    var y = window.scrollY;
    nav.classList.toggle('scrolled', y > 40);
    if (!reduce && heroBg && y < window.innerHeight) heroBg.style.transform = 'translate3d(0,' + (y * 0.3) + 'px,0)';
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();

  function countUp(el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0;
    if (reduce || target < 2) { el.textContent = fa(target); return; }
    var t0 = null, dur = 1600 + Math.min(target, 2000) / 2;
    function step(t) {
      if (!t0) t0 = t;
      var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 4);
      el.textContent = fa(Math.round(target * e));
      if (p < 1) requestAnimationFrame(step);
    }
    el.textContent = fa(0);
    requestAnimationFrame(step);
  }

  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (!en.isIntersecting) return;
      en.target.classList.add('in');
      en.target.querySelectorAll('[data-count]').forEach(countUp);
      io.unobserve(en.target);
    });
  }, { threshold: 0.18, rootMargin: '0px 0px -40px 0px' });
  document.querySelectorAll('.rv, #steps').forEach(function (el) { io.observe(el); });

  var sparks = document.getElementById('sparks');
  if (sparks && !reduce) {
    for (var i = 0; i < 18; i++) {
      var s = document.createElement('span');
      s.className = 'spark';
      s.style.left = (Math.random() * 100) + '%';
      s.style.bottom = (Math.random() * 30 - 5) + '%';
      s.style.animationDelay = (Math.random() * 7) + 's';
      s.style.animationDuration = (5 + Math.random() * 5) + 's';
      if (Math.random() > .6) s.style.background = '#38bdf8', s.style.boxShadow = '0 0 10px 2px rgba(56,189,248,.8)';
      sparks.appendChild(s);
    }
  }

  var ring = document.getElementById('fcRing'), pct = document.getElementById('fcPct');
  if (ring && !reduce) {
    var p = 64;
    setInterval(function () {
      p = p >= 100 ? 64 : p + 1;
      ring.style.setProperty('--p', p);
      pct.textContent = fa(p) + '٪';
    }, 900);
  }

  var phones = document.getElementById('phones');
  if (phones && !reduce && window.matchMedia('(hover: hover)').matches) {
    var p1 = phones.querySelector('.p1');
    phones.addEventListener('mousemove', function (e) {
      var r = phones.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      p1.style.transform = 'rotateY(' + (x * 16) + 'deg) rotateX(' + (-y * 12) + 'deg) translateZ(30px)';
    });
    phones.addEventListener('mouseleave', function () { p1.style.transform = ''; });
  }

  var mapEl = document.getElementById('lmap');
  var data = window.LANDING_MAP || { own: [], ext: [], ops: [] };
  function initMap() {
    if (!window.L || mapEl._init) return;
    mapEl._init = true;
    var map = L.map(mapEl, { scrollWheelZoom: false, attributionControl: false, zoomControl: true }).setView([32.4, 53.7], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);
    var canvas = L.canvas({ padding: .5 });
    var counts = {};
    data.ext.forEach(function (s) {
      var op = data.ops[s[2]] || ['', '#64748b'];
      counts[s[2]] = (counts[s[2]] || 0) + 1;
      L.circleMarker([s[0], s[1]], { renderer: canvas, radius: 4.5, weight: 1.5, color: '#fff', fillColor: op[1], fillOpacity: .9 })
        .bindTooltip(op[0], { direction: 'top' }).addTo(map);
    });
    var qIcon = L.divIcon({ className: '', html: '<div class="q-pin"></div>', iconSize: [34, 34], iconAnchor: [17, 17] });
    var pts = [];
    data.own.forEach(function (s) {
      pts.push([s[0], s[1]]);
      L.marker([s[0], s[1]], { icon: qIcon, zIndexOffset: 1000 }).bindTooltip(s[2], { direction: 'top', offset: [0, -16] }).addTo(map);
    });
    data.ext.forEach(function (s) { pts.push([s[0], s[1]]); });
    if (pts.length > 1) map.fitBounds(pts, { padding: [40, 40], maxZoom: 9 });
    map.on('click', function () { map.scrollWheelZoom.enable(); });

    var legend = document.getElementById('legend');
    data.ops.map(function (o, i) { return [o, counts[i] || 0]; })
      .sort(function (a, b) { return b[1] - a[1]; })
      .forEach(function (row) {
        var el = document.createElement('span');
        el.innerHTML = '<i style="background:' + row[0][1] + '"></i>' + row[0][0] + ' (' + fa(row[1]) + ')';
        legend.appendChild(el);
      });
    setTimeout(function () { map.invalidateSize(); }, 300);
  }
  if (mapEl) {
    new IntersectionObserver(function (en, obs) {
      if (en[0].isIntersecting) { initMap(); obs.disconnect(); }
    }, { rootMargin: '300px' }).observe(mapEl);
  }
})();
