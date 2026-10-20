/* ============================================================
   SafirCharge — پنل شرکا (مالکان شارژر)
   چرم مشترک: سایدبار سبک، توربار
   ============================================================ */
(function () {
  "use strict";
  var I = window.__SC_ICONS || null;
  // آیکون‌ها (همان مجموعهٔ پنل مدیر، زیرمجموعه)
  var IC = {
    grid: '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
    station: '<path d="M3 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M3 21h12M7 9h2M14 8l3 3v7a2 2 0 0 0 2 2 2 2 0 0 0 2-2v-9l-3-3"/>',
    chart: '<path d="M3 3v18h18M7 16l4-5 3 3 5-7"/>',
    receipt: '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1V2l-2 1-2-1-2 1-2-1-2 1-2-1zM8 7h8M8 11h8M8 15h5"/>',
    coins: '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18M7 6h1v4M16.71 13.88l.7.71-2.82 2.82"/>',
    bolt: '<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/>',
    headset: '<path d="M3 14v-2a9 9 0 0 1 18 0v2M21 14a2 2 0 0 1-2 2h-1v-5h1a2 2 0 0 1 2 2zM3 14a2 2 0 0 0 2 2h1v-5H5a2 2 0 0 0-2 2zM21 16v1a4 4 0 0 1-4 4h-5"/>',
    bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>',
    moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    plug: '<path d="M9 2v6M15 2v6M6 8h12v3a6 6 0 0 1-12 0zM12 17v5"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    bars: '<path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/>',
    key: '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.5 12.5 21 2M16 7l3 3M14 9l3 3"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
  };
  function svg(name) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' + (IC[name] || '') + '</svg>'; }
  window.icon = svg;

  var NAV = [
    { id: "dashboard", t: "داشبورد", ic: "grid", href: "dashboard.html" },
    { id: "stations", t: "ایستگاه‌ها و دستگاه‌ها", ic: "station", href: "stations.html" },
    { id: "reports", t: "آمار و گزارش", ic: "chart", href: "reports.html" },
    { id: "payments", t: "درآمد و پرداختی‌ها", ic: "coins", href: "payments.html" },
    { id: "support", t: "پشتیبانی", ic: "headset", href: "support.html" },
  ];

  // تم
  var savedTheme = localStorage.getItem("cn-theme") || "light";
  var savedAccent = localStorage.getItem("cn-accent");
  document.documentElement.setAttribute("data-theme", savedTheme);
  if (savedAccent) {
    document.documentElement.style.setProperty("--accent", savedAccent);
    var rgbMap = { "#0E6BA8": "14, 107, 168", "#16A34A": "22, 163, 74", "#F97316": "249, 115, 22", "#7C3AED": "124, 58, 237", "#0891B2": "8, 145, 178" };
    if (rgbMap[savedAccent]) document.documentElement.style.setProperty("--accent-rgb", rgbMap[savedAccent]);
  }

  function build() {
    var body = document.body;
    var page = body.getAttribute("data-page");
    var title = body.getAttribute("data-title") || "";
    var crumb = body.getAttribute("data-crumb") || "پنل شرکا";

    var navHTML = NAV.map(function (it) {
      return '<a class="nav-link' + (it.id === page ? ' active' : '') + '" href="' + it.href + '">' +
        svg(it.ic) + '<span>' + it.t + '</span></a>';
    }).join('');

    var sidebar =
      '<aside class="sidebar" id="sidebar">' +
        '<div class="brand">' +
          '<div class="brand-mark">' + svg("bolt") + '</div>' +
          '<div><div class="brand-name">SafirCharge</div><div class="brand-sub">پنل شرکا</div></div>' +
        '</div>' +
        '<nav class="nav"><div class="nav-group">' + navHTML + '</div></nav>' +
        '<div class="sidebar-foot">' +
          '<div class="station-card">' +
            '<div class="sc-top"><span class="pulse"></span><span>پارکینگ پاسداران</span></div>' +
            '<div class="sc-meta">۸ دستگاه فعال · مدل اشتراک ۷۰٪<br>تسویهٔ بعدی: ۱ دی ۱۴۰۳</div>' +
          '</div>' +
          '<a href="login.html" class="logout-link">' + svg("logout") + ' خروج</a>' +
        '</div>' +
      '</aside>';

    var topbar =
      '<header class="topbar">' +
        '<div class="page-title"><span class="crumb">' + crumb + '</span>' + title + '</div>' +
        '<div class="topbar-actions">' +
          '<button class="icon-btn" id="themeBtn">' + svg(savedTheme === "dark" ? "sun" : "moon") + '</button>' +
          '<button class="icon-btn"><span></span>' + svg("bell") + '<span class="dot"></span></button>' +
          '<div class="profile-wrap">' +
            '<button class="user-chip" id="userChip"><span class="av">پ.پ</span><span><span class="u-name">پارکینگ پاسداران</span><span class="u-role">شریک میزبان</span></span></button>' +
            '<div class="profile-menu" id="profileMenu">' +
              '<div class="pm-head"><span class="av" style="width:40px;height:40px;border-radius:12px">پ.پ</span><div><div class="fw7">پارکینگ پاسداران</div><div class="fs12 muted">PARTNER‑۱۰۲۴</div></div></div>' +
              '<a class="pm-item" href="profile.html">' + svg("settings") + '<span>پروفایل و حساب</span></a>' +
              '<a class="pm-item" href="payments.html">' + svg("coins") + '<span>درآمد و پرداختی‌ها</span></a>' +
              '<a class="pm-item" href="support.html">' + svg("headset") + '<span>پشتیبانی</span></a>' +
              '<div class="pm-sep"></div>' +
              '<button class="pm-item pm-theme" id="pmTheme">' + svg(savedTheme === "dark" ? "sun" : "moon") + '<span>تغییر تم روشن/تیره</span></button>' +
              '<a class="pm-item pm-danger" href="login.html">' + svg("logout") + '<span>خروج از حساب</span></a>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</header>';

    body.classList.add("app");
    body.insertAdjacentHTML("afterbegin", sidebar);
    var main = document.querySelector(".main");
    if (main) main.insertAdjacentHTML("afterbegin", topbar);

    var themeBtn = document.getElementById("themeBtn");
    themeBtn.addEventListener("click", function () {
      var cur = document.documentElement.getAttribute("data-theme");
      var next = cur === "dark" ? "light" : "dark";
      document.documentElement.setAttribute("data-theme", next);
      localStorage.setItem("cn-theme", next);
      themeBtn.innerHTML = svg(next === "dark" ? "sun" : "moon");
    });

    // منوی پروفایل
    var userChip = document.getElementById("userChip");
    var profileMenu = document.getElementById("profileMenu");
    if (userChip) {
      userChip.addEventListener("click", function (e) { e.stopPropagation(); profileMenu.classList.toggle("open"); });
      document.addEventListener("click", function () { profileMenu.classList.remove("open"); });
      profileMenu.addEventListener("click", function (e) { e.stopPropagation(); });
      var pmTheme = document.getElementById("pmTheme");
      if (pmTheme) pmTheme.addEventListener("click", function () {
        var cur = document.documentElement.getAttribute("data-theme");
        var next = cur === "dark" ? "light" : "dark";
        document.documentElement.setAttribute("data-theme", next);
        localStorage.setItem("cn-theme", next);
        pmTheme.innerHTML = svg(next === "dark" ? "sun" : "moon") + '<span>تغییر تم روشن/تیره</span>';
        themeBtn.innerHTML = svg(next === "dark" ? "sun" : "moon");
      });
    }
  }

  window.linePath = function (vals, w, h, pad) {
    pad = pad || 6;
    var max = Math.max.apply(null, vals), min = Math.min.apply(null, vals);
    var rng = (max - min) || 1, step = (w - pad * 2) / (vals.length - 1);
    return vals.map(function (v, i) {
      var x = pad + i * step, y = pad + (h - pad * 2) * (1 - (v - min) / rng);
      return (i ? "L" : "M") + x.toFixed(1) + " " + y.toFixed(1);
    }).join(" ");
  };
  window.areaPath = function (vals, w, h, pad) {
    pad = pad || 6;
    return window.linePath(vals, w, h, pad) + " L" + (w - pad) + " " + (h - pad) + " L" + pad + " " + (h - pad) + " Z";
  };

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", build);
  else build();
})();
