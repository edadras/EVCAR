/* SafirCharge — جستجو، فیلتر و autocomplete مشترک پنل ادمین */
(function () {
  "use strict";

  var FA = "۰۱۲۳۴۵۶۷۸۹".split("");

  function normalize(text) {
    if (!text) return "";
    return String(text)
      .toLowerCase()
      .replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); })
      .replace(/\s+/g, " ")
      .trim();
  }

  function debounce(fn, ms) {
    var t;
    return function () {
      var args = arguments;
      var self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, ms);
    };
  }

  function apiBase() {
    var b = document.body.getAttribute("data-admin-base") || "/admin";
    return b.replace(/\/$/, "");
  }

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute("content") : "";
  }

  function localDatetime(dt) {
    var pad = function (n) { return String(n).padStart(2, "0"); };
    return dt.getFullYear() + "-" + pad(dt.getMonth() + 1) + "-" + pad(dt.getDate()) +
      "T" + pad(dt.getHours()) + ":" + pad(dt.getMinutes()) + ":00";
  }

  function parseDigits(value) {
    if (value == null) return "";
    return String(value)
      .replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); })
      .replace(/[^\d.]/g, "");
  }

  function postJson(url, body) {
    return fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": csrf(),
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.text().then(function (text) {
        var data = {};
        if (text) {
          try { data = JSON.parse(text); } catch (e) {
            if (r.status === 419) throw { message: "نشست منقضی شده — صفحه را رفرش کنید." };
            throw { message: "پاسخ نامعتبر از سرور (" + r.status + ")" };
          }
        }
        if (!r.ok) {
          var msg = data.message || (data.errors && Object.values(data.errors).flat().join(" ")) || "خطای سرور";
          throw { message: msg, errors: data.errors };
        }
        return data;
      });
    });
  }

  /** فیلتر ردیف‌های جدول بر اساس input جستجو */
  function bindTableSearch(input, tbodySelector, extraMatch) {
    var inputEl = typeof input === "string" ? document.querySelector(input) : input;
    var tbody = document.querySelector(tbodySelector);
    if (!inputEl || !tbody) return;

    inputEl.addEventListener("input", debounce(function () {
      var q = normalize(inputEl.value);
      tbody.querySelectorAll("tr").forEach(function (tr) {
        var text = normalize(tr.getAttribute("data-search") || tr.textContent);
        var ok = !q || text.indexOf(q) !== -1;
        if (ok && extraMatch) ok = extraMatch(tr);
        tr.style.display = ok ? "" : "none";
      });
    }, 200));
  }

  /** فیلتر آیتم‌های لیست (مثل ایستگاه) */
  function bindListSearch(input, itemSelector, getText) {
    var inputEl = typeof input === "string" ? document.querySelector(input) : input;
    if (!inputEl) return;

    inputEl.addEventListener("input", debounce(function () {
      var q = normalize(inputEl.value);
      document.querySelectorAll(itemSelector).forEach(function (el) {
        var text = normalize(getText ? getText(el) : el.textContent);
        el.style.display = !q || text.indexOf(q) !== -1 ? "" : "none";
      });
    }, 200));
  }

  /** سگمنت + جستجو روی tbody */
  function bindSegRows(segId, tbodySelector, segMatch) {
    var cur = 0;
    document.querySelectorAll("#" + segId + " button").forEach(function (b, idx) {
      b.addEventListener("click", function () {
        document.querySelectorAll("#" + segId + " button").forEach(function (x) { x.classList.remove("active"); });
        b.classList.add("active");
        cur = idx;
        apply();
      });
    });

    function apply() {
      document.querySelectorAll(tbodySelector + " tr").forEach(function (tr) {
        var segOk = !segMatch || segMatch(tr, cur);
        var q = "";
        var searchInput = document.querySelector("[data-search-for='" + tbodySelector.replace("#", "") + "']");
        if (searchInput) q = normalize(searchInput.value);
        var text = normalize(tr.getAttribute("data-search") || tr.textContent);
        var searchOk = !q || text.indexOf(q) !== -1;
        tr.style.display = segOk && searchOk ? "" : "none";
      });
    }

    return { refresh: apply };
  }

  /** autocomplete کاربر از API */
  function bindUserAutocomplete(inputEl, opts) {
    if (!inputEl) return;
    opts = opts || {};
    var hidden = opts.hiddenInput ? document.querySelector(opts.hiddenInput) : null;
    var box = document.createElement("div");
    box.className = "ac-dropdown";
    box.style.cssText = "position:absolute;z-index:200;background:var(--surface);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow-md);max-height:220px;overflow:auto;display:none;min-width:260px;";
    inputEl.parentElement.style.position = "relative";
    inputEl.parentElement.appendChild(box);

    function pick(user) {
      inputEl.value = user.name;
      if (hidden) hidden.value = user.id;
      box.style.display = "none";
      if (opts.onSelect) opts.onSelect(user);
    }

    inputEl.addEventListener("input", debounce(function () {
      var q = inputEl.value.trim();
      if (hidden) hidden.value = "";
      if (q.length < 1) { box.style.display = "none"; return; }

      fetch(apiBase() + "/api/users?q=" + encodeURIComponent(q), {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var users = data.users || [];
          if (!users.length) {
            box.innerHTML = '<div style="padding:12px;color:var(--muted);font-size:13px">نتیجه‌ای یافت نشد</div>';
          } else {
            box.innerHTML = users.map(function (u) {
              return '<button type="button" class="ac-item" data-id="' + u.id + '" style="display:block;width:100%;text-align:right;padding:10px 14px;border:none;background:none;font-family:inherit;cursor:pointer">' +
                '<div class="fw7">' + u.name + '</div><div class="fs12 muted">' + u.phone + '</div></button>';
            }).join("");
            box.querySelectorAll(".ac-item").forEach(function (btn) {
              btn.addEventListener("click", function () {
                pick(users.filter(function (u) { return String(u.id) === btn.getAttribute("data-id"); })[0]);
              });
            });
          }
          box.style.display = "block";
        });
    }, 250));

    document.addEventListener("click", function (e) {
      if (!box.contains(e.target) && e.target !== inputEl) box.style.display = "none";
    });
  }

  /** جستجوی سراسری تاپ‌بار */
  function bindGlobalSearch(inputEl, panelEl) {
    if (!inputEl || !panelEl) return;

    function render(results) {
      if (!results.length) {
        panelEl.innerHTML = '<div style="padding:14px;color:var(--muted);font-size:13px">نتیجه‌ای یافت نشد</div>';
        return;
      }
      var icons = { user: "users", station: "station", session: "bolt" };
      panelEl.innerHTML = results.map(function (r) {
        return '<a href="' + r.url + '" class="gs-item" style="display:flex;gap:10px;padding:10px 14px;text-decoration:none;color:inherit;border-bottom:1px solid var(--border-2)">' +
          '<span style="opacity:.7">' + (window.icon ? window.icon(icons[r.type] || "search") : "") + '</span>' +
          '<span><div class="fw7 fs13">' + r.label + '</div><div class="muted fs12">' + (r.meta || "") + '</div></span></a>';
      }).join("");
    }

    inputEl.addEventListener("input", debounce(function () {
      var q = inputEl.value.trim();
      if (q.length < 2) { panelEl.style.display = "none"; return; }

      fetch(apiBase() + "/api/search?q=" + encodeURIComponent(q), {
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          render(data.results || []);
          panelEl.style.display = "block";
        });
    }, 300));

    inputEl.addEventListener("focus", function () {
      if (inputEl.value.trim().length >= 2) panelEl.style.display = "block";
    });

    document.addEventListener("click", function (e) {
      if (!panelEl.contains(e.target) && e.target !== inputEl) panelEl.style.display = "none";
    });
  }

  window.AdminUI = {
    normalize: normalize,
    debounce: debounce,
    apiBase: apiBase,
    csrf: csrf,
    localDatetime: localDatetime,
    parseDigits: parseDigits,
    postJson: postJson,
    bindTableSearch: bindTableSearch,
    bindListSearch: bindListSearch,
    bindSegRows: bindSegRows,
    bindUserAutocomplete: bindUserAutocomplete,
    bindGlobalSearch: bindGlobalSearch,
  };
})();
