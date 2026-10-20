/* SafirCharge — باز کردن خودکار صفحه تأیید ورود (ANPR pending) */
(function () {
  'use strict';

  var cfg = window.SC_CONNECTIVITY || {};
  var routes = window.SC_ROUTES || {};
  var api = window.SC_API || {};
  if (!cfg.userUuid || !routes.arrival) return;

  function routePath(href) {
    try {
      var a = document.createElement('a');
      a.href = href;
      return a.pathname;
    } catch (e) {
      return href;
    }
  }

  var arrivalPath = routes.arrival;
  var arrivalRoute = routePath(arrivalPath);
  var here = window.location.pathname;
  if (here.indexOf(arrivalRoute) >= 0) return;
  if (here.indexOf('/login') >= 0) return;

  var pollMs = 8000;
  var busy = false;

  function shouldRedirect(data) {
    return data && data.pending === true && (data.auto_start_status === 'pending' || !data.auto_start_status);
  }

  function fetchArrivalStatus() {
    var edgePath = api.arrivalEdge || '/api/v1/app/arrival';
    if (window.scFetchAnyEdge) {
      return window.scFetchAnyEdge(edgePath).then(function (hit) {
        if (hit && hit.data && shouldRedirect(hit.data)) {
          return hit.data;
        }
        if (api.arrivalCloud && window.scFetch) {
          return window.scFetch(api.arrivalCloud, '', { method: 'GET' }).then(function (r) {
            return r.ok ? r.json() : null;
          });
        }
        return hit && hit.data ? hit.data : null;
      });
    }
    if (window.scFetch) {
      return window.scFetch(api.arrivalCloud || '', edgePath, { method: 'GET' }).then(function (r) {
        return r.ok ? r.json() : null;
      });
    }
    return Promise.resolve(null);
  }

  function poll() {
    if (busy) return;
    busy = true;
    window.scEnsureBackend().then(fetchArrivalStatus).then(function (data) {
      if (shouldRedirect(data)) {
        window.location.href = arrivalPath;
      }
    }).catch(function () {})
      .finally(function () { busy = false; });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(poll, 1200);
      setInterval(poll, pollMs);
    });
  } else {
    setTimeout(poll, 1200);
    setInterval(poll, pollMs);
  }
})();
