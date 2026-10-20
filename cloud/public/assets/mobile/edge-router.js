/* SafirCharge — تشخیص مرکز / لبه محلی و مسیریابی API */
(function () {
  'use strict';

  var cfg = window.SC_CONNECTIVITY || {};
  var candidates = cfg.candidates || [];
  var STORAGE_KEY = 'sc_backend_choice_v1';
  var ready = null;

  function timeoutSignal(ms) {
    if (typeof AbortSignal !== 'undefined' && AbortSignal.timeout) {
      return AbortSignal.timeout(ms);
    }
    var ctrl = new AbortController();
    setTimeout(function () { ctrl.abort(); }, ms);
    return ctrl.signal;
  }

  function probe(candidate) {
    var url = candidate.base + candidate.healthPath;
    return fetch(url, {
      method: 'GET',
      mode: 'cors',
      credentials: 'omit',
      signal: timeoutSignal(cfg.timeoutMs || 2500),
      headers: { Accept: 'application/json' },
    })
      .then(function (r) {
        if (!r.ok) return null;
        return r.json().then(function (body) {
          return Object.assign({}, candidate, { health: body });
        }).catch(function () {
          return Object.assign({}, candidate);
        });
      })
      .catch(function () { return null; });
  }

  function setBadge(backend) {
    var el = document.getElementById('scBackendBadge');
    if (!el) return;
    el.textContent = backend.kind === 'edge' ? 'محلی' : 'ابری';
    el.className = 'sc-backend-badge ' + (backend.kind === 'edge' ? 'edge' : 'cloud');
    el.hidden = false;
  }

  function applyBackend(backend) {
    window.SC_ACTIVE_BACKEND = backend;
    try { localStorage.setItem(STORAGE_KEY, backend.id); } catch (e) {}
    document.body.setAttribute('data-sc-backend', backend.kind);
    setBadge(backend);
    document.dispatchEvent(new CustomEvent('sc:backend-changed', { detail: backend }));
  }

  function showPicker(available) {
    var overlay = document.getElementById('scBackendPicker');
    if (!overlay) {
      applyBackend(available.find(function (c) { return c.kind === 'cloud'; }) || available[0]);
      return;
    }
    var list = overlay.querySelector('[data-backend-list]');
    list.innerHTML = available.map(function (c) {
      var sub = c.kind === 'edge'
        ? (c.wifiSsid ? 'WiFi: ' + c.wifiSsid : c.base)
        : 'دادهٔ مرکزی · همیشه به‌روز';
      return '<button type="button" class="sc-bp-opt" data-id="' + c.id + '">' +
        '<span class="sc-bp-kind">' + (c.kind === 'edge' ? 'لبه' : 'ابر') + '</span>' +
        '<span class="sc-bp-main"><b>' + c.label + '</b><small>' + sub + '</small></span>' +
        '</button>';
    }).join('');
    overlay.classList.add('open');
    list.querySelectorAll('.sc-bp-opt').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var picked = available.find(function (c) { return c.id === btn.getAttribute('data-id'); });
        overlay.classList.remove('open');
        if (picked) applyBackend(picked);
      });
    });
  }

  function chooseBackend(available) {
    if (available.length === 0) {
      var cloud = candidates.find(function (c) { return c.kind === 'cloud'; });
      if (cloud) applyBackend(cloud);
      return;
    }
    if (available.length === 1) {
      applyBackend(available[0]);
      return;
    }

    var edges = available.filter(function (c) { return c.kind === 'edge'; });
    var clouds = available.filter(function (c) { return c.kind === 'cloud'; });

    if (edges.length === 1 && clouds.length === 0) {
      applyBackend(edges[0]);
      return;
    }
    if (clouds.length === 1 && edges.length === 0) {
      applyBackend(clouds[0]);
      return;
    }

    if (cfg.askWhenMultiple) {
      var saved = null;
      try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) {}
      var savedHit = saved && available.find(function (c) { return c.id === saved; });
      if (savedHit) {
        applyBackend(savedHit);
        return;
      }
      showPicker(available);
      return;
    }

    var preferEdge = edges.length === 1 ? edges[0] : null;
    applyBackend(preferEdge || clouds[0] || available[0]);
  }

  function discover() {
    return Promise.all(candidates.map(probe)).then(function (results) {
      return results.filter(Boolean);
    });
  }

  function init() {
    if (!ready) {
      ready = discover().then(chooseBackend);
    }
    return ready;
  }

  window.scEnsureBackend = init;

  function edgeSessionToken() {
    if (cfg.edgeSessionToken) return cfg.edgeSessionToken;
    try { return localStorage.getItem('sc_edge_session_token'); } catch (e) { return null; }
  }

  function buildEdgeAuthHeaders(extra) {
    var headers = Object.assign({
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    }, extra || {});
    if (cfg.userUuid && cfg.edgeToken) {
      headers['X-SC-User-UUID'] = cfg.userUuid;
      headers['X-SC-Edge-Token'] = cfg.edgeToken;
    } else {
      var session = edgeSessionToken();
      if (session) headers['Authorization'] = 'Bearer ' + session;
    }
    return headers;
  }

  function buildHeaders(options) {
    options = options || {};
    var backend = window.SC_ACTIVE_BACKEND || candidates.find(function (c) { return c.kind === 'cloud'; });
    var headers = Object.assign({
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    }, options.headers || {});
    if (backend && backend.kind === 'edge') {
      return Object.assign(headers, buildEdgeAuthHeaders(options.headers));
    }
    return headers;
  }

  function edgeCandidates(available) {
    var edges = (available || []).filter(function (c) { return c.kind === 'edge'; });
    if (edges.length) return edges;
    return candidates.filter(function (c) { return c.kind === 'edge'; });
  }

  function fetchAnyEdge(path, options) {
    options = options || {};
    return init().then(function () {
      return discover();
    }).then(function (available) {
      var edges = edgeCandidates(available);
      var idx = 0;
      function attempt() {
        if (idx >= edges.length) return Promise.resolve(null);
        var edge = edges[idx++];
        var url = edge.base + path;
        return fetch(url, Object.assign({}, options, {
          method: options.method || 'GET',
          headers: buildEdgeAuthHeaders(options.headers),
        })).then(function (r) {
          if (!r.ok) return attempt();
          return r.json().then(function (data) {
            return { backend: edge, data: data };
          });
        }).catch(function () { return attempt(); });
      }
      return attempt();
    });
  }

  function postAnyEdge(path, body, options) {
    options = options || {};
    return init().then(function () {
      return discover();
    }).then(function (available) {
      var edges = edgeCandidates(available);
      var idx = 0;
      function attempt() {
        if (idx >= edges.length) return Promise.resolve(null);
        var edge = edges[idx++];
        var url = edge.base + path;
        return fetch(url, Object.assign({}, options, {
          method: 'POST',
          headers: Object.assign(buildEdgeAuthHeaders(options.headers), {
            'Content-Type': 'application/json',
          }),
          body: JSON.stringify(body || {}),
        })).then(function (r) {
          return r.json().then(function (data) {
            if (r.ok || r.status === 202 || r.status === 422) {
              return {
                backend: edge,
                ok: r.ok,
                status: r.status,
                data: data,
                json: function () { return Promise.resolve(data); },
              };
            }
            return attempt();
          });
        }).catch(function () { return attempt(); });
      }
      return attempt();
    });
  }

  window.scFetch = function (cloudPath, edgePath, options) {
    options = options || {};
    var backend = window.SC_ACTIVE_BACKEND || candidates.find(function (c) { return c.kind === 'cloud'; });
    var path = backend && backend.kind === 'edge' ? edgePath : cloudPath;
    var url = backend && backend.kind === 'edge' ? backend.base + path : path;
    return init().then(function () {
      var active = window.SC_ACTIVE_BACKEND || backend;
      if (active.kind === 'edge') {
        url = active.base + edgePath;
      } else {
        url = cloudPath;
      }
      return fetch(url, Object.assign({}, options, { headers: buildHeaders(options) }));
    });
  };

  window.scPost = function (cloudPath, edgePath, body, options) {
    options = options || {};
    return init().then(function () {
      var active = window.SC_ACTIVE_BACKEND || candidates.find(function (c) { return c.kind === 'cloud'; });
      var url = active.kind === 'edge' ? active.base + edgePath : cloudPath;
      var headers = buildHeaders(options);
      headers['Content-Type'] = headers['Content-Type'] || 'application/json';
      var csrf = document.querySelector('meta[name="csrf-token"]');
      if (active.kind === 'cloud' && csrf) {
        headers['X-CSRF-TOKEN'] = csrf.getAttribute('content');
      }
      return fetch(url, Object.assign({}, options, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(body || {}),
      }));
    });
  };

  window.scFetchAnyEdge = fetchAnyEdge;
  window.scPostAnyEdge = postAnyEdge;

  window.scOpenBackendPicker = function () {
    discover().then(showPicker);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
