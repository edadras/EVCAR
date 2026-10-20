(function () {
  'use strict';

  var cfg = window.SC_NAV || {};
  var center = cfg.mapCenter || { lat: 35.6892, lng: 51.389 };
  var origin = { lat: center.lat, lng: center.lng, label: 'تهران (پیش‌فرض)' };
  var destination = null;
  var pickMode = null;
  var routeLayers = [];
  var previewLayers = [];
  var previewMarkers = [];
  var chargerMarkers = [];
  var originMarker = null;
  var destMarker = null;
  var lastResult = null;
  var previewTimer = null;
  var previewSeq = 0;

  var mapEl = document.getElementById('navMap');
  if (!mapEl) return;

  var map = L.map('navMap', { zoomControl: false, attributionControl: false }).setView([center.lat, center.lng], 11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

  function segmentColor(ratio) {
    if (ratio > 1.35) return '#E24B4A';
    if (ratio > 1.12) return '#EF9F27';
    if (ratio < 0.75) return '#1D9E75';
    if (ratio < 0.92) return '#5DCAA5';
    return '#378ADD';
  }

  function socColor(soc) {
    if (soc < 15) return '#E24B4A';
    if (soc < 30) return '#EF9F27';
    if (soc >= 60) return '#1D9E75';
    return '#378ADD';
  }

  function fa(n) {
    return Number(n).toLocaleString('fa-IR', { maximumFractionDigits: 1 });
  }

  function setHint(msg) {
    var el = document.getElementById('navHint');
    if (!el) return;
    if (msg) {
      el.textContent = msg;
      el.hidden = false;
    } else {
      el.textContent = '';
      el.hidden = true;
    }
  }

  function setPickMode(mode) {
    pickMode = mode;
    document.getElementById('navDestBtn').style.background = mode === 'dest' ? 'var(--accent-soft)' : '';
    document.getElementById('navOriginBtn').style.background = mode === 'origin' ? 'var(--accent-soft)' : '';
    if (mode === 'dest') setHint('روی نقشه بزنید تا مقصد ثبت شود');
    else if (mode === 'origin') setHint('روی نقشه بزنید تا مبدا ثبت شود');
    else setHint('');
  }

  function setOrigin(lat, lng, label) {
    origin = { lat: lat, lng: lng, label: label || 'مبدا' };
    document.getElementById('originLabel').value = origin.label;
    if (originMarker) map.removeLayer(originMarker);
    originMarker = L.circleMarker([lat, lng], {
      radius: 9, color: '#fff', fillColor: '#378ADD', fillOpacity: 1, weight: 3,
    }).addTo(map).bindPopup('مبدا');
    updateSwapButton();
    scheduleRoutePreview();
  }

  function setDestination(lat, lng, label) {
    destination = { lat: lat, lng: lng, label: label || 'مقصد' };
    document.getElementById('destLabel').value = destination.label;
    if (destMarker) map.removeLayer(destMarker);
    destMarker = L.marker([lat, lng], {
      icon: L.divIcon({
        className: '',
        html: '<div style="background:var(--green);color:#fff;width:28px;height:28px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:grid;place-items:center;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,.25)"><span style="transform:rotate(45deg)">●</span></div>',
        iconSize: [28, 28], iconAnchor: [14, 28],
      }),
    }).addTo(map).bindPopup('مقصد');
    updateSwapButton();
    scheduleRoutePreview();
  }

  function updateSwapButton() {
    var btn = document.getElementById('swapEndpoints');
    if (btn) btn.disabled = !destination;
  }

  function fitEndpointsOnMap() {
    if (!origin) return;
    var bounds = L.latLngBounds([[origin.lat, origin.lng]]);
    if (destination) bounds.extend([destination.lat, destination.lng]);
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [48, 48], maxZoom: 12 });
    }
  }

  function swapOriginDestination() {
    if (!destination) return;

    var prevOrigin = { lat: origin.lat, lng: origin.lng, label: origin.label };
    var prevDest = { lat: destination.lat, lng: destination.lng, label: destination.label };

    if (lastResult) {
      clearRoute();
      lastResult = null;
      previewSeq++;
      document.getElementById('navForm').hidden = false;
      document.getElementById('navResults').hidden = true;
      if (window.navSheetSnap) window.navSheetSnap('mid');
    } else {
      clearPreview();
      previewSeq++;
    }

    setPickMode(null);
    setOrigin(prevDest.lat, prevDest.lng, prevDest.label);
    setDestination(prevOrigin.lat, prevOrigin.lng, prevOrigin.label);
    fitEndpointsOnMap();
  }

  function clearPreview() {
    previewLayers.forEach(function (l) { map.removeLayer(l); });
    previewLayers = [];
    previewMarkers.forEach(function (m) { map.removeLayer(m); });
    previewMarkers = [];
    var hint = document.getElementById('routePreviewHint');
    if (hint) {
      hint.textContent = '';
      hint.hidden = true;
    }
  }

  function clearRoute() {
    routeLayers.forEach(function (l) { map.removeLayer(l); });
    routeLayers = [];
    chargerMarkers.forEach(function (m) { map.removeLayer(m); });
    chargerMarkers = [];
    clearPreview();
  }

  function setPreviewHint(msg, danger) {
    var el = document.getElementById('routePreviewHint');
    if (!el) return;
    if (msg) {
      el.textContent = msg;
      el.hidden = false;
      el.classList.toggle('nav-preview-danger', !!danger);
    } else {
      el.textContent = '';
      el.hidden = true;
      el.classList.remove('nav-preview-danger');
    }
  }

  function scheduleRoutePreview() {
    if (!destination || !origin || !cfg.previewUrl || lastResult) return;
    clearTimeout(previewTimer);
    previewTimer = setTimeout(fetchRoutePreview, 450);
  }

  function drawPreviewOnMap(data) {
    clearPreview();
    var poly = (data.route && data.route.polyline) || [];
    if (poly.length < 2) return;

    var line = L.polyline(poly.map(function (p) { return [p.lat, p.lng]; }), {
      color: '#378ADD',
      weight: 4,
      opacity: 0.55,
      dashArray: '8 10',
    }).addTo(map);
    previewLayers.push(line);

    (data.chargers || []).forEach(function (ch) {
      var mk = L.circleMarker([ch.lat, ch.lng], {
        radius: 8, color: '#fff', fillColor: '#EF9F27', fillOpacity: 0.95, weight: 2,
      }).addTo(map).bindPopup('<b>⚡ ' + ch.name + '</b><br>کیلومتر ' + fa(ch.route_km) + ' از مبدا');
      previewMarkers.push(mk);
    });

    var bounds = L.latLngBounds(poly.map(function (p) { return [p.lat, p.lng]; }));
    if (originMarker) bounds.extend(originMarker.getLatLng());
    if (destMarker) bounds.extend(destMarker.getLatLng());
    map.fitBounds(bounds.pad(0.12));
  }

  function fetchRoutePreview() {
    if (!destination || !origin || !cfg.previewUrl) return;
    var seq = ++previewSeq;
    setPreviewHint('در حال بارگذاری مسیر و ایستگاه‌ها…');

    fetch(cfg.previewUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': cfg.csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(buildPreviewPayload()),
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json().then(function (j) {
          if (!r.ok) throw new Error(j.message || 'خطا');
          return j;
        });
      })
      .then(function (data) {
        if (seq !== previewSeq || lastResult) return;
        drawPreviewOnMap(data);
        var dir = data.route && data.route.direction ? data.route.direction + ' · ' : '';
        var stationMsg;
        var danger = false;
        var vehicleHint = data.battery_kwh
          ? 'باتری ' + fa(data.battery_kwh) + ' کیلووات‌ساعت'
          : '';
        if (data.range_km_on_start > 0) {
          vehicleHint += (vehicleHint ? ' · ' : '') + 'برد حدود ' + fa(data.range_km_on_start) + ' کیلومتر';
        }
        if (!data.needs_charge) {
          stationMsg = (vehicleHint ? vehicleHint + ' — ' : '') + 'با شارژ فعلی احتمالاً بدون توقف به مقصد می‌رسید.';
        } else if ((data.charger_count || 0) > 0) {
          stationMsg = fa(data.charger_count) + ' ایستگاه مناسب وقتی شارژ کم شود';
          if (data.first_charge_km != null) {
            stationMsg += ' (اولین توقف حدود کیلومتر ' + fa(data.first_charge_km) + ')';
          }
          stationMsg += ' — نارنجی روی نقشه.';
        } else {
          danger = true;
          stationMsg = (vehicleHint ? vehicleHint + ' — ' : '') + '⛔ به شارژ نیاز دارید — ایستگاه SafirCharge در محل نیاز پیدا نشد';
          if (data.estimated_charge_stops > 1) {
            stationMsg += ' (حدود ' + fa(data.estimated_charge_stops) + ' توقف لازم است)';
          } else if (data.estimated_charge_stops === 1) {
            stationMsg += ' (حداقل ۱ توقف لازم است)';
          }
          if (data.first_charge_km != null) {
            stationMsg += ' · اولین نیاز: کیلومتر ' + fa(data.first_charge_km);
          }
          if (data.corridor_charger_count > 0) {
            stationMsg += ' · ' + fa(data.corridor_charger_count) + ' ایستگاه در جای دیگر مسیر است';
          }
        }
        setPreviewHint(
          dir + fa(data.route.distance_km) + ' کیلومتر · حدود ' + fa(data.route.duration_min) + ' دقیقه — ' + stationMsg,
          danger
        );
      })
      .catch(function () {
        if (seq !== previewSeq) return;
        setPreviewHint('');
      });
  }

  function drawRoute(data) {
    clearRoute();
    var segs = data.segments || [];
    var poly = data.route.polyline || [];
    if (poly.length < 2) return;

    var segIdx = 0;
    var cumKm = 0;
    for (var i = 1; i < poly.length; i++) {
      var d = haversine(poly[i - 1].lat, poly[i - 1].lng, poly[i].lat, poly[i].lng);
      cumKm += d;
      while (segIdx < segs.length && cumKm > segs[segIdx].to_km) segIdx++;
      var ratio = segs[segIdx] ? segs[segIdx].ratio : 1;
      var line = L.polyline(
        [[poly[i - 1].lat, poly[i - 1].lng], [poly[i].lat, poly[i].lng]],
        { color: segmentColor(ratio), weight: 6, opacity: 0.9, className: 'sc-route-seg' }
      ).addTo(map);
      routeLayers.push(line);
    }

    var plannedUuids = {};
    (data.charge_plan || []).forEach(function (stop) {
      if (stop.station_uuid) plannedUuids[stop.station_uuid] = stop;
    });

    (data.charge_plan || []).forEach(function (stop, i) {
      if (stop.lat == null || stop.lng == null) return;
      var mk = L.circleMarker([stop.lat, stop.lng], {
        radius: 10, color: '#fff', fillColor: '#EF9F27', fillOpacity: 1, weight: 3,
      }).addTo(map).bindPopup(
        '<b>⚡ توقف ' + fa(i + 1) + ': ' + stop.name + '</b><br>' + (stop.reason || 'شارژ در مسیر')
      );
      mk.on('click', function () { if (stop.url) window.location.href = stop.url; });
      chargerMarkers.push(mk);
    });

    (data.chargers || []).forEach(function (ch) {
      if (plannedUuids[ch.uuid]) return;
      var mk = L.circleMarker([ch.lat, ch.lng], {
        radius: 6, color: '#fff', fillColor: '#1D9E75', fillOpacity: 0.85, weight: 2,
      }).addTo(map).bindPopup(
        '<b>' + ch.name + '</b><br>کیلومتر ' + fa(ch.route_km) + '<br>' + fa(ch.free_ports) + ' کانکتور آزاد'
      );
      mk.on('click', function () { if (ch.url) window.location.href = ch.url; });
      chargerMarkers.push(mk);
    });

    var bounds = L.latLngBounds(poly.map(function (p) { return [p.lat, p.lng]; }));
    if (originMarker) bounds.extend(originMarker.getLatLng());
    if (destMarker) bounds.extend(destMarker.getLatLng());
    map.fitBounds(bounds.pad(0.12));
  }

  function haversine(lat1, lng1, lat2, lng2) {
    var R = 6371;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLng = (lng2 - lng1) * Math.PI / 180;
    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function chartWidth(canvas) {
    if (!canvas) return 0;
    var el = canvas.parentElement;
    var w = el ? el.clientWidth : 0;
    if (w < 80) {
      var sheet = document.getElementById('navSheet');
      w = sheet ? sheet.clientWidth - 48 : 0;
    }
    if (w < 80) {
      w = Math.min(document.documentElement.clientWidth, 480) - 48;
    }
    return Math.max(200, w - 24);
  }

  function setupCanvas(canvas, cssH) {
    var w = chartWidth(canvas);
    var dpr = window.devicePixelRatio || 1;
    canvas.style.width = '100%';
    canvas.style.height = cssH + 'px';
    canvas.width = Math.round(w * dpr);
    canvas.height = Math.round(cssH * dpr);
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    return { ctx: ctx, w: w, h: cssH };
  }

  function chartMuted() {
    return getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#888';
  }

  function chartBounds(w, h) {
    return { left: 34, right: w - 8, top: 10, bottom: h - 22 };
  }

  function kmToX(km, maxKm, bounds) {
    return bounds.left + (km / maxKm) * (bounds.right - bounds.left);
  }

  function drawRouteEndpoints(ctx, bounds, maxKm, originLabel, destLabel) {
    var muted = chartMuted();
    var x0 = bounds.left;
    var x1 = bounds.right;

    ctx.save();
    ctx.setLineDash([4, 4]);
    ctx.lineWidth = 1;
    ctx.strokeStyle = '#378ADD';
    ctx.globalAlpha = 0.45;
    ctx.beginPath();
    ctx.moveTo(x0, bounds.top);
    ctx.lineTo(x0, bounds.bottom);
    ctx.stroke();
    ctx.strokeStyle = '#1D9E75';
    ctx.beginPath();
    ctx.moveTo(x1, bounds.top);
    ctx.lineTo(x1, bounds.bottom);
    ctx.stroke();
    ctx.restore();

    ctx.fillStyle = muted;
    ctx.font = '10px Tahoma, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('● ' + (originLabel || 'مبدا'), x0, bounds.bottom + 14);
    ctx.fillText((destLabel || 'مقصد') + ' ●', x1, bounds.bottom + 14);
    ctx.textAlign = 'left';
    ctx.fillText('۰ کیلومتر', bounds.left, bounds.bottom + 14);
    ctx.textAlign = 'right';
    ctx.fillText(fa(maxKm) + ' کیلومتر', bounds.right, bounds.bottom + 14);
  }

  function drawElevationChart(profile, segments, route) {
    var canvas = document.getElementById('elevChart');
    if (!canvas || !profile.length) return;
    var setup = setupCanvas(canvas, 140);
    var ctx = setup.ctx;
    var w = setup.w;
    var h = setup.h;
    var bounds = chartBounds(w, h);
    var maxElev = Math.max.apply(null, profile.map(function (p) { return p.elev_m; })) || 1;
    var minElev = Math.min.apply(null, profile.map(function (p) { return p.elev_m; })) || 0;
    var maxKm = profile[profile.length - 1].km || 1;
    if (maxKm <= 0) maxKm = 1;
    var elevSpan = Math.max(1, maxElev - minElev);
    var originLabel = route && route.origin_label ? route.origin_label : 'مبدا';
    var destLabel = route && route.destination_label ? route.destination_label : 'مقصد';

    ctx.clearRect(0, 0, w, h);

    ctx.fillStyle = chartMuted();
    ctx.font = '10px Tahoma, sans-serif';
    ctx.textAlign = 'right';
    ctx.fillText(fa(Math.round(minElev)) + ' م', bounds.left - 4, bounds.bottom);
    ctx.fillText(fa(Math.round(maxElev)) + ' م', bounds.left - 4, bounds.top + 10);

    if (segments.length) {
      var maxKwh = Math.max.apply(null, segments.map(function (s) { return s.consumption_kwh; })) || 0.01;
      segments.forEach(function (s) {
        var x1 = kmToX(s.from_km, maxKm, bounds);
        var x2 = kmToX(s.to_km, maxKm, bounds);
        var bh = (s.consumption_kwh / maxKwh) * 22;
        ctx.fillStyle = segmentColor(s.ratio);
        ctx.globalAlpha = 0.35;
        ctx.fillRect(x1, bounds.bottom - bh, Math.max(2, x2 - x1), bh);
        ctx.globalAlpha = 1;
      });
    }

    ctx.strokeStyle = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#378ADD';
    ctx.lineWidth = 2.5;
    ctx.lineJoin = 'round';
    ctx.beginPath();
    profile.forEach(function (p, i) {
      var x = kmToX(p.km, maxKm, bounds);
      var y = bounds.bottom - ((p.elev_m - minElev) / elevSpan) * (bounds.bottom - bounds.top);
      if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
    });
    ctx.stroke();

    var xStart = kmToX(0, maxKm, bounds);
    var xEnd = kmToX(maxKm, maxKm, bounds);
    var yStart = bounds.bottom - ((profile[0].elev_m - minElev) / elevSpan) * (bounds.bottom - bounds.top);
    var yEnd = bounds.bottom - ((profile[profile.length - 1].elev_m - minElev) / elevSpan) * (bounds.bottom - bounds.top);
    [{ x: xStart, y: yStart, c: '#378ADD' }, { x: xEnd, y: yEnd, c: '#1D9E75' }].forEach(function (pt) {
      ctx.fillStyle = pt.c;
      ctx.beginPath();
      ctx.arc(pt.x, pt.y, 4, 0, Math.PI * 2);
      ctx.fill();
      ctx.strokeStyle = '#fff';
      ctx.lineWidth = 2;
      ctx.stroke();
    });

    drawRouteEndpoints(ctx, bounds, maxKm, originLabel, destLabel);
  }

  function drawSocChart(profile) {
    var canvas = document.getElementById('socChart');
    if (!canvas || !profile || profile.length < 2) return;
    var setup = setupCanvas(canvas, 56);
    var ctx = setup.ctx;
    var w = setup.w;
    var h = setup.h;
    var bounds = chartBounds(w, h);
    var maxKm = profile[profile.length - 1].km || 1;
    if (maxKm <= 0) maxKm = 1;

    ctx.clearRect(0, 0, w, h);

    ctx.fillStyle = '#E24B4A';
    ctx.globalAlpha = 0.12;
    var lowY = bounds.bottom - (15 / 100) * (bounds.bottom - bounds.top);
    ctx.fillRect(bounds.left, lowY, bounds.right - bounds.left, bounds.bottom - lowY);
    ctx.globalAlpha = 1;

    for (var i = 1; i < profile.length; i++) {
      var x1 = kmToX(profile[i - 1].km, maxKm, bounds);
      var x2 = kmToX(profile[i].km, maxKm, bounds);
      var y1 = bounds.bottom - (profile[i - 1].soc / 100) * (bounds.bottom - bounds.top);
      var y2 = bounds.bottom - (profile[i].soc / 100) * (bounds.bottom - bounds.top);
      ctx.strokeStyle = socColor(profile[i].soc);
      ctx.lineWidth = 4;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(x1, y1);
      ctx.lineTo(x2, y2);
      ctx.stroke();
    }

    drawRouteEndpoints(ctx, bounds, maxKm, null, null);
  }

  function renderChartDataNote(data) {
    var el = document.getElementById('chartDataNote');
    if (!el) return;
    var sources = data.data_sources || [];
    if (!sources.length) {
      el.innerHTML = '';
      el.hidden = true;
      return;
    }
    var tagLabel = { real: 'واقعی', estimated: 'تخمینی', fallback: 'جایگزین' };
    el.hidden = false;
    el.innerHTML = '<div class="fw7 mb4">منبع داده‌های نمودار</div>' +
      sources.map(function (s) {
        return '<div class="ds-row"><span class="ds-tag ' + s.kind + '">' + (tagLabel[s.kind] || s.kind) + '</span>' +
          '<span><strong>' + s.label + ':</strong> ' + s.detail + '</span></div>';
      }).join('');
  }

  function renderChargerList(chargers, noChargerWarning, summary) {
    var el = document.getElementById('chargerList');
    var s = summary || {};
    if (!chargers || !chargers.length) {
      var title = s.stations_missing
        ? '⛔ به شارژ نیاز دارید — ایستگاه در محل نیاز نیست'
        : '⚠ ایستگاه شارژ در مسیر نیست';
      el.innerHTML = '<div class="card card-p" style="border:2px solid #E24B4A;background:#fde8e8;color:#E24B4A;font-size:13px;line-height:1.55;padding:12px">' +
        '<div class="fw8 mb4">' + title + '</div>' +
        (noChargerWarning || 'هیچ ایستگاه SafirCharge در محدوده نیاز به شارژ یافت نشد. بدون توقف به مقصد نمی‌رسید.') +
        (s.estimated_charge_stops > 1 ? '<div class="mt6">حدود ' + fa(s.estimated_charge_stops) + ' توقف شارژ لازم است.</div>' : '') +
        '</div>';
      return;
    }
    el.innerHTML = chargers.map(function (ch, i) {
      return '<a class="nav-plan-item" href="' + (ch.url || '#') + '">' +
        '<div class="pi">' + fa(i + 1) + '</div>' +
        '<div style="flex:1"><div class="fw7">' + ch.name + '</div>' +
        '<div class="fs12 muted mt4">کیلومتر ' + fa(ch.route_km) + ' از مبدا · ' + fa(ch.max_power_kw) + ' کیلووات</div></div>' +
        '<span class="pill pill-green">' + fa(ch.free_ports) + ' آزاد</span></a>';
    }).join('');
  }

  function renderVerdict(data) {
    var v = data.verdict || {};
    var status = v.status || (data.summary && data.summary.needs_charge ? 'needs_charge' : 'ok');
    var warnings = (v.warnings || []).map(function (w) {
      return '<div class="nv-warn">⚠ ' + w + '</div>';
    }).join('');
    var el = document.getElementById('navVerdict');
    if (!el) return;
    el.className = 'nav-verdict ' + status;
    el.innerHTML =
      '<div class="nv-title">' + (v.title || 'نتیجه تحلیل') + '</div>' +
      '<div class="nv-msg">' + (v.message || '') + '</div>' +
      warnings;
  }

  function renderResults(data) {
    lastResult = data;
    clearPreview();
    var s = data.summary || {};
    var srcLabel = { api: 'سرور پیشرفته', lite: 'حالت ابری', lite_fallback: 'حالت ابری (جایگزین)' };
    var src = srcLabel[data.source] || data.source;
    var warn = data.api_error ? '<div class="ns-warn">' + data.api_error + '</div>' : '';
    var direction = data.route && data.route.direction ? data.route.direction : '';

    renderVerdict(data);

    document.getElementById('navSummary').innerHTML =
      '<div class="row between mb8"><span class="fw8">خلاصه مسیر</span><span class="pill pill-accent">' + src + '</span></div>' +
      (direction ? '<div class="ns-row"><span>جهت</span><strong>' + direction + '</strong></div>' : '') +
      '<div class="ns-row"><span>مسافت</span><strong>' + fa(data.route.distance_km) + ' کیلومتر · ' + fa(data.route.duration_min) + ' دقیقه</strong></div>' +
      (data.route.mode === 'via_chargers'
        ? '<div class="ns-row"><span>مسیر</span><strong style="color:var(--orange)">عبور از ' + fa((data.charge_plan || []).length) + ' ایستگاه شارژ' +
          (data.route.detour_km > 0 ? ' (+' + fa(data.route.detour_km) + ' کیلومتر)' : '') + '</strong></div>'
        : '') +
      '<div class="ns-row"><span>خودرو و باتری</span><strong>' + (data.vehicle && data.vehicle.name ? data.vehicle.name + ' · ' : '') +
        fa(s.battery_kwh) + ' کیلووات‌ساعت</strong></div>' +
      (s.range_km_on_start > 0
        ? '<div class="ns-row"><span>برد (همین مصرف مسیر)</span><strong>حدود ' + fa(s.range_km_on_start) + ' کیلومتر تا رزرو ' + fa(s.reserve_soc || 10) + '٪' +
          (s.usable_kwh_on_start ? ' · ' + fa(s.usable_kwh_on_start) + ' kWh در دسترس' : '') +
          '</strong></div>'
        : '') +
      (function () {
        var base = s.consumption_base_kwh100;
        var mul = s.consumption_multiplier;
        var extra = '';
        if (base && mul && mul !== 1) {
          extra = ' (پایه ' + fa(base) + ' × ' + fa(mul) + ')';
        } else if (base) {
          extra = ' (پایه ' + fa(base) + ')';
        }
        return '<div class="ns-row"><span>مصرف این مسیر</span><strong>' + fa(s.consumption_per_100km || 0) +
          ' کیلووات‌ساعت در ۱۰۰ کیلومتر' + extra + ' · کل ' + fa(s.total_kwh) + '</strong></div>';
      })() +
      '<div class="ns-row"><span>شارژ بدون توقف</span><strong>' + fa(s.start_soc) + '٪ → ' + fa(s.arrival_soc) + '٪ در مقصد</strong></div>' +
      (s.charge_stops > 0 && s.arrival_soc_planned !== s.arrival_soc
        ? '<div class="ns-row"><span>با توقف شارژ پیشنهادی</span><strong>' + fa(s.start_soc) + '٪ → ' + fa(s.arrival_soc_planned) + '٪ در مقصد</strong></div>'
        : '') +
      '<div class="ns-row"><span>هدف در مقصد</span><strong>' + fa(s.target_soc) + '٪</strong></div>' +
      '<div class="ns-row"><span>کمینه شارژ در مسیر</span><strong style="color:' + socColor(s.min_soc_on_route || s.arrival_soc) + '">' + fa(s.min_soc_on_route || s.arrival_soc) + '٪</strong></div>' +
      (s.needs_charge_trip && s.estimated_charge_stops > 0
        ? '<div class="ns-row"><span>توقف شارژ لازم</span><strong>حدود ' + fa(s.estimated_charge_stops) + ' بار</strong></div>'
        : '') +
      '<div class="ns-row"><span>ایستگاه در محدوده شارژ</span><strong>' + fa(s.charger_count || 0) + ' ایستگاه' +
        (s.needs_charge_trip ? '' : ' (نیاز به توقف نیست)') + '</strong></div>' +
      (s.corridor_charger_count > s.charger_count
        ? '<div class="ns-row"><span>نزدیک مسیر (غیرضروری)</span><strong class="muted">' + fa(s.corridor_charger_count) + ' ایستگاه</strong></div>'
        : '') +
      (s.first_charge_km != null && s.needs_charge_trip
        ? '<div class="ns-row"><span>اولین توقف تقریبی</span><strong>کیلومتر ' + fa(s.first_charge_km) + '</strong></div>'
        : '') +
      warn;

    var planEl = document.getElementById('chargePlan');
    var plan = data.charge_plan || [];
    if (!plan.length) {
      planEl.innerHTML = '<div class="card card-p muted fs13">' +
        (s.needs_charge ? 'با شارژ فعلی به هدف مقصد نمی‌رسید — ایستگاه مناسب در مسیر پیدا نشد.' : 'با شارژ فعلی بدون توقف اضافه به مقصد می‌رسید.') +
        '</div>';
    } else {
      planEl.innerHTML = plan.map(function (p) {
        return '<a class="nav-plan-item" href="' + (p.url || '#') + '">' +
          '<div class="pi">' + (window.icon ? window.icon('plug') : '⚡') + '</div>' +
          '<div style="flex:1"><div class="fw7">' + p.name + '</div>' +
          '<div class="fs12 muted mt4">کیلومتر ' + fa(p.km) + ' · ' + p.reason + '</div></div>' +
          '<span class="pill pill-accent">→ ' + fa(p.charge_to_soc || s.target_soc) + '٪</span></a>';
      }).join('');
    }

    var noChargerMsg = (data.verdict && data.verdict.status === 'no_chargers')
      ? data.verdict.message
      : null;
    renderChargerList(data.chargers, noChargerMsg, s);

    var segEl = document.getElementById('segmentList');
    var segs = data.segments || [];
    var segHtml = segs.slice(0, 25).map(function (seg) {
      var socFrom = seg.soc_before != null ? seg.soc_before : s.start_soc;
      return '<div class="nav-seg">' +
        '<div class="bar" style="background:' + segmentColor(seg.ratio) + '"></div>' +
        '<div style="flex:1"><div class="fw7">کیلومتر ' + fa(seg.from_km) + ' تا ' + fa(seg.to_km) + '</div>' +
        '<div class="muted mt4">ارتفاع ' + fa(seg.delta_h_m) + ' م · مصرف ' + fa(seg.consumption_kwh) + ' کیلووات‌ساعت</div>' +
        '<div class="fs12 mt4" style="color:' + socColor(seg.soc_after) + '">شارژ: ' + fa(socFrom) + '٪ → ' + fa(seg.soc_after) + '٪</div></div>' +
        '<span class="pill" style="background:' + segmentColor(seg.ratio) + '22;color:' + segmentColor(seg.ratio) + '">×' + fa(seg.ratio) + '</span></div>';
    }).join('');
    if (segs.length > 25) {
      var last = segs[segs.length - 1];
      segHtml += '<div class="card card-p muted fs12 center-col">… ' + fa(segs.length - 25) + ' بخش دیگر · پایان مسیر: ' + fa(last.soc_after) + '٪</div>';
    }
    segEl.innerHTML = segHtml;

    drawRoute(data);

    document.getElementById('navForm').hidden = true;
    document.getElementById('navResults').hidden = false;
    if (window.navSheetSnap) window.navSheetSnap('mid');

    renderChartDataNote(data);
    redrawCharts(data);
  }

  function redrawCharts(data) {
    if (!data) return;
    var draw = function () {
      var route = data.route || {};
      drawSocChart(data.soc_profile || []);
      drawElevationChart(data.elevation_profile || [], data.segments || [], route);
    };
    requestAnimationFrame(function () {
      requestAnimationFrame(draw);
    });
  }

  function toggleCustomVehicle() {
    var sel = document.getElementById('vehicleSelect').value;
    var show = sel === 'preset:custom';
    document.getElementById('customVehicle').hidden = !show;
  }

  function buildPreviewPayload() {
    var payload = {
      origin: { lat: origin.lat, lng: origin.lng },
      destination: { lat: destination.lat, lng: destination.lng },
      current_soc: parseInt(document.getElementById('socRange').value, 10),
      target_soc: parseInt(document.getElementById('targetSocRange').value, 10),
    };
    var sel = document.getElementById('vehicleSelect').value;
    if (sel.indexOf('user:') === 0) {
      payload.vehicle_uuid = sel.slice(5);
    } else {
      payload.vehicle_preset = sel.slice(7);
      if (sel === 'preset:custom') {
        payload.battery_kwh = parseFloat(document.getElementById('customBattery').value);
        payload.consumption_kwh100 = parseFloat(document.getElementById('customConsumption').value);
      }
    }
    return payload;
  }

  function buildPayload() {
    if (!destination) throw new Error('مقصد را انتخاب کنید.');
    var sel = document.getElementById('vehicleSelect').value;
    var payload = {
      origin: { lat: origin.lat, lng: origin.lng, label: origin.label },
      destination: { lat: destination.lat, lng: destination.lng, label: destination.label },
      current_soc: parseInt(document.getElementById('socRange').value, 10),
      target_soc: parseInt(document.getElementById('targetSocRange').value, 10),
    };
    if (sel.indexOf('user:') === 0) {
      payload.vehicle_uuid = sel.slice(5);
    } else {
      payload.vehicle_preset = sel.slice(7);
      if (sel === 'preset:custom') {
        payload.battery_kwh = parseFloat(document.getElementById('customBattery').value);
        payload.consumption_kwh100 = parseFloat(document.getElementById('customConsumption').value);
      }
    }
    return payload;
  }

  document.getElementById('socRange').addEventListener('input', function (e) {
    document.getElementById('socOut').textContent = fa(e.target.value) + '٪';
    scheduleRoutePreview();
  });
  document.getElementById('targetSocRange').addEventListener('input', function (e) {
    document.getElementById('targetSocOut').textContent = fa(e.target.value) + '٪';
    scheduleRoutePreview();
  });
  document.getElementById('vehicleSelect').addEventListener('change', function () {
    toggleCustomVehicle();
    scheduleRoutePreview();
  });

  document.getElementById('swapEndpoints').addEventListener('click', swapOriginDestination);

  document.getElementById('useMyLoc').addEventListener('click', locateMe);
  document.getElementById('navLocBtn').addEventListener('click', locateMe);

  function locateMe() {
    if (!navigator.geolocation) {
      alert('موقعیت‌یاب در دسترس نیست');
      return;
    }
    navigator.geolocation.getCurrentPosition(function (pos) {
      setOrigin(pos.coords.latitude, pos.coords.longitude, 'موقعیت من');
      map.setView([pos.coords.latitude, pos.coords.longitude], 13);
    }, function () {
      alert('دسترسی به موقعیت رد شد');
    });
  }

  document.getElementById('navDestBtn').addEventListener('click', function () {
    setPickMode(pickMode === 'dest' ? null : 'dest');
  });
  document.getElementById('navOriginBtn').addEventListener('click', function () {
    setPickMode(pickMode === 'origin' ? null : 'origin');
  });

  map.on('click', function (e) {
    if (pickMode === 'dest') {
      setPickMode(null);
      setDestination(e.latlng.lat, e.latlng.lng, 'مقصد انتخاب‌شده');
    } else if (pickMode === 'origin') {
      setPickMode(null);
      setOrigin(e.latlng.lat, e.latlng.lng, 'مبدا انتخاب‌شده');
    }
  });

  function geocode(q, target) {
    if (!q) return;
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ir&q=' + encodeURIComponent(q))
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res[0]) {
          alert((target === 'origin' ? 'مبدا' : 'مقصد') + ' یافت نشد');
          return;
        }
        var lat = parseFloat(res[0].lat);
        var lng = parseFloat(res[0].lon);
        var label = res[0].display_name.split(',')[0];
        if (target === 'origin') {
          setOrigin(lat, lng, label);
        } else {
          setDestination(lat, lng, label);
        }
        map.setView([lat, lng], 12);
      })
      .catch(function () { alert('جستجو ناموفق بود'); });
  }

  document.getElementById('originLabel').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); geocode((this.value || '').trim(), 'origin'); }
  });
  document.getElementById('destLabel').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); geocode((this.value || '').trim(), 'dest'); }
  });

  document.querySelectorAll('.nav-pr').forEach(function (btn) {
    btn.addEventListener('click', function () {
      try {
        var route = JSON.parse(btn.getAttribute('data-route'));
        setOrigin(route.origin.lat, route.origin.lng, route.origin.label);
        setDestination(route.destination.lat, route.destination.lng, route.destination.label);
        scheduleRoutePreview();
        map.setView([route.origin.lat, route.origin.lng], 8);
      } catch (err) { /* ignore */ }
    });
  });

  document.getElementById('analyzeBtn').addEventListener('click', function () {
    var sheet = document.getElementById('navSheet');
    var btn = document.getElementById('analyzeBtn');
    try {
      var payload = buildPayload();
    } catch (err) {
      alert(err.message);
      return;
    }
    sheet.classList.add('nav-loading');
    btn.disabled = true;
    btn.textContent = 'در حال تحلیل…';

    fetch(cfg.analyzeUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': cfg.csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
    })
      .then(function (r) {
        return r.json().then(function (j) {
          if (!r.ok) throw new Error(j.message || 'خطا در تحلیل مسیر');
          return j;
        });
      })
      .then(renderResults)
      .catch(function (err) {
        alert(err.message || 'خطا در ارتباط با سرور');
      })
      .finally(function () {
        sheet.classList.remove('nav-loading');
        btn.disabled = false;
        btn.innerHTML = '<span data-ic="nav"></span> تحلیل مسیر و مصرف انرژی';
        if (window.initIcons) window.initIcons();
      });
  });

  document.getElementById('navReset').addEventListener('click', function () {
    clearRoute();
    lastResult = null;
    previewSeq++;
    document.getElementById('navForm').hidden = false;
    document.getElementById('navResults').hidden = true;
    if (window.navSheetSnap) window.navSheetSnap('mid');
    destination = null;
    document.getElementById('destLabel').value = '';
    if (destMarker) { map.removeLayer(destMarker); destMarker = null; }
    updateSwapButton();
    setPickMode(null);
  });

  updateSwapButton();

  window.addEventListener('resize', function () {
    if (!lastResult) return;
    redrawCharts(lastResult);
  });

  function initSheetDrag() {
    var sheet = document.getElementById('navSheet');
    var zone = document.getElementById('sheetDragZone');
    if (!sheet || !zone) return;

    var dragging = false;
    var startY = 0;
    var startH = 0;
    var dragMoved = false;

    function snapPoints() {
      var h = window.innerHeight;
      return {
        min: 72,
        mid: Math.round(h * 0.48),
        max: Math.round(h * 0.76),
      };
    }

    function applySheetHeight(h, animate) {
      var pts = snapPoints();
      h = Math.max(pts.min, Math.min(pts.max, h));
      sheet.style.transition = animate ? 'height .22s ease,max-height .22s ease' : 'none';
      sheet.style.height = h + 'px';
      sheet.style.maxHeight = h + 'px';
      document.documentElement.style.setProperty('--nav-sheet-h', h + 'px');
      sheet.classList.toggle('collapsed', h <= pts.min + 6);
      if (animate) {
        setTimeout(function () { map.invalidateSize({ animate: false }); }, 230);
      } else {
        map.invalidateSize({ animate: false });
      }
      return h;
    }

    function snapToNearest() {
      var h = sheet.getBoundingClientRect().height;
      var pts = snapPoints();
      var target = pts.mid;
      if (h < (pts.min + pts.mid) * 0.45) target = pts.min;
      else if (h > (pts.mid + pts.max) * 0.55) target = pts.max;
      applySheetHeight(target, true);
    }

    window.navSheetSnap = function (which) {
      var pts = snapPoints();
      var map = { min: pts.min, mid: pts.mid, max: pts.max };
      applySheetHeight(map[which] || pts.mid, true);
    };

    applySheetHeight(snapPoints().mid, false);

    function pointerDown(clientY) {
      dragging = true;
      dragMoved = false;
      startY = clientY;
      startH = sheet.getBoundingClientRect().height;
      sheet.style.transition = 'none';
    }

    function pointerMove(clientY) {
      if (!dragging) return;
      var delta = startY - clientY;
      if (Math.abs(delta) > 4) dragMoved = true;
      applySheetHeight(startH + delta, false);
    }

    function pointerUp() {
      if (!dragging) return;
      dragging = false;
      if (!dragMoved) {
        var pts = snapPoints();
        var h = sheet.getBoundingClientRect().height;
        applySheetHeight(h <= pts.min + 16 ? pts.mid : pts.min, true);
      } else {
        snapToNearest();
      }
    }

    zone.addEventListener('touchstart', function (e) {
      pointerDown(e.touches[0].clientY);
    }, { passive: true });
    zone.addEventListener('touchmove', function (e) {
      pointerMove(e.touches[0].clientY);
      if (dragging) e.preventDefault();
    }, { passive: false });
    zone.addEventListener('touchend', pointerUp);

    zone.addEventListener('mousedown', function (e) {
      pointerDown(e.clientY);
      e.preventDefault();
    });
    window.addEventListener('mousemove', function (e) {
      if (dragging) pointerMove(e.clientY);
    });
    window.addEventListener('mouseup', pointerUp);

    window.addEventListener('resize', function () {
      if (!dragging) applySheetHeight(sheet.getBoundingClientRect().height, false);
    });
  }

  toggleCustomVehicle();
  initSheetDrag();
  setOrigin(center.lat, center.lng, 'تهران (پیش‌فرض)');
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function (pos) {
      setOrigin(pos.coords.latitude, pos.coords.longitude, 'موقعیت من');
    }, function () { /* keep default */ });
  }
})();
