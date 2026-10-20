/* ============================================================
   SafirCharge — چرم مشترک: سایدبار، توربار، تم و رنگ تاکیدی
   هر صفحه body[data-page] و [data-title]/[data-crumb] دارد.
   ============================================================ */
(function () {
  "use strict";

  /* ---------- آیکون‌ها (Lucide-style, stroke) ---------- */
  var I = {
    grid: '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
    activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
    map: '<path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3zM9 3v15M15 6v15"/>',
    bolt: '<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/>',
    wallet: '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M16 12h.01M3 9h18"/>',
    coins: '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18M7 6h1v4M16.71 13.88l.7.71-2.82 2.82"/>',
    receipt: '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1V2l-2 1-2-1-2 1-2-1-2 1-2-1zM8 7h8M8 11h8M8 15h5"/>',
    card: '<path d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zM2 10h20"/>',
    chip: '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    headset: '<path d="M3 14v-2a9 9 0 0 1 18 0v2M21 14a2 2 0 0 1-2 2h-1v-5h1a2 2 0 0 1 2 2zM3 14a2 2 0 0 0 2 2h1v-5H5a2 2 0 0 0-2 2zM21 16v1a4 4 0 0 1-4 4h-5"/>',
    alert: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/>',
    sliders: '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
    chart: '<path d="M3 3v18h18M7 16l4-5 3 3 5-7"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>',
    sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    filter: '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
    dots: '<circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/>',
    station: '<path d="M3 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M3 21h12M7 9h2M14 8l3 3v7a2 2 0 0 0 2 2 2 2 0 0 0 2-2v-9l-3-3"/>',
    refresh: '<path d="M3 12a9 9 0 0 1 15-6.7L21 8M21 3v5h-5M21 12a9 9 0 0 1-15 6.7L3 16M3 21v-5h5"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    box: '<path d="M21 8V16a2 2 0 0 1-1 1.73l-7 4a2 2 0 0 1-2 0l-7-4A2 2 0 0 1 3 16V8a2 2 0 0 1 1-1.73l7-4a2 2 0 0 1 2 0l7 4A2 2 0 0 1 21 8z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>',
    wrench: '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.3L3 18v3h3l6.4-6.3a4 4 0 0 0 5.3-5.4l-2.8 2.8-2.1-2.1z"/>',
    truck: '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7M5.5 19a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM18.5 19a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/>',
    car: '<path d="M5 13l2-5h10l2 5M3 13h18v5H3zM6 18v2M18 18v2M6.5 13.5h.01M17.5 13.5h.01"/>',
    plug: '<path d="M9 2v6M15 2v6M6 8h12v3a6 6 0 0 1-12 0zM12 17v5"/>',
    leaf: '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/>',
    qr: '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h3v3h-3zM20 14v7M17 20h4"/>',
    camera: '<path d="M3 8a2 2 0 0 1 2-2h2l1.5-2h7L19 6h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="12.5" r="3.5"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    pin: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    edit: '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    building: '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/>',
    shield: '<path d="M12 2l8 4v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/>',
    eye: '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
    scan: '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    key: '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.5 12.5 21 2M16 7l3 3M14 9l3 3"/>',
    gift: '<path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
    bars: '<path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="7"/><rect x="12" y="6" width="3" height="11"/><rect x="17" y="13" width="3" height="4"/>',
  };
  function svg(name, w) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' + (I[name] || '') + '</svg>';
  }
  window.icon = svg;

  /* ---------- رندر پلاک ایرانی ---------- */
  // plate({d2:'۱۲', let:'ب', d3:'۳۴۵', prov:'۱۱', size:''})
  window.plate = function (p) {
    var cls = "plate" + (p.size ? " " + p.size : "");
    return '<span class="' + cls + '">' +
      '<span class="pl-flag"><i></i><i></i><i></i></span>' +
      '<span class="pl-main"><span>' + p.d2 + '</span><span class="pl-let">' + (p['let'] || p.letter) + '</span><span>' + p.d3 + '</span></span>' +
      '<span class="pl-prov"><b>' + p.prov + '</b><small>ایران</small></span>' +
    '</span>';
  };

  /* ---------- پایهٔ URL پنل ادمین (Laravel routes) ---------- */
  function adminBase() {
    var b = document.body.getAttribute("data-admin-base") || "/admin";
    return b.replace(/\/$/, "");
  }
  function adminUrl(path) {
    var base = adminBase();
    if (!path) return base;
    return base + "/" + path.replace(/^\//, "");
  }

  /* ---------- پیکربندی ناوبری ---------- */
  var NAV = [
    { group: "مدیریت شبکه", items: [
      { id: "dashboard", t: "داشبورد و مانیتورینگ", ic: "grid", path: "", dot: true },
      { id: "monitoring", t: "مانیتورینگ زنده شبکه", ic: "activity", path: "monitoring", dot: true },
      { id: "alerts", t: "هشدارهای عملیاتی", ic: "bell", path: "alerts" },
      { id: "insights", t: "تحلیل، پیش‌بینی و خروجی", ic: "bars", path: "insights" },
      { id: "stations", t: "ایستگاه‌ها و نقشه", ic: "map", path: "stations" },
      { id: "sessions", t: "جلسات شارژ", ic: "bolt", path: "sessions", stat: "sessions" },
      { id: "reservations", t: "رزرو شارژ", ic: "calendar", path: "reservations" },
      { id: "anpr", t: "تشخیص پلاک (ANPR)", ic: "car", path: "anpr" },
    ]},
    { group: "مالی و پرداخت", items: [
      { id: "wallet", t: "کیف پول کاربران", ic: "wallet", path: "wallet" },
      { id: "finance", t: "مدیریت مالی", ic: "chart", path: "finance" },
      { id: "reports", t: "گزارش‌ها و تحلیل", ic: "bars", path: "reports" },
      { id: "tariffs", t: "تعرفه‌ها و RFID", ic: "sliders", path: "tariffs" },
    ]},
    { group: "عملیات", items: [
      { id: "users", t: "کاربران و سازمان‌ها", ic: "users", path: "users" },
      { id: "partners", t: "شرکا و مالکان شارژر", ic: "building", path: "partners" },
      { id: "support", t: "پشتیبانی و خطاها", ic: "headset", path: "support", stat: "support" },
    ]},
    { group: "انبار و دارایی", items: [
      { id: "inventory", t: "انبار و دستگاه‌ها", ic: "box", path: "inventory" },
      { id: "repairs", t: "تعمیرات و نگهداری", ic: "wrench", path: "repairs" },
    ]},
    { group: "سامانه و ارتباطات", items: [
      { id: "ev-catalog", t: "کاتالوگ خودرو برقی", ic: "car", path: "ev-catalog" },
      { id: "sync", t: "همگام‌سازی و آفلاین", ic: "refresh", path: "sync" },
      { id: "notifications", t: "اعلان‌ها", ic: "bell", path: "notifications", stat: "notifications" },
      { id: "campaigns", t: "طرح‌ها و کمپین‌ها", ic: "gift", path: "campaigns" },
      { id: "team", t: "کاربران مدیر و دسترسی", ic: "shield", path: "team" },
    ]},
  ];

  /* ---------- رنگ‌های تاکیدی ---------- */
  var ACCENTS = [
    { name: "آبی", v: "#0E6BA8", rgb: "14, 107, 168" },
    { name: "سبز", v: "#16A34A", rgb: "22, 163, 74" },
    { name: "نارنجی", v: "#F97316", rgb: "249, 115, 22" },
    { name: "بنفش", v: "#7C3AED", rgb: "124, 58, 237" },
    { name: "فیروزه‌ای", v: "#0891B2", rgb: "8, 145, 178" },
  ];

  /* ---------- اعمال تنظیمات ذخیره‌شده ---------- */
  function applyTheme(t) {
    document.documentElement.setAttribute("data-theme", t);
    localStorage.setItem("cn-theme", t);
  }
  function applyAccent(a) {
    document.documentElement.style.setProperty("--accent", a.v);
    document.documentElement.style.setProperty("--accent-rgb", a.rgb);
    localStorage.setItem("cn-accent", a.v);
  }
  var savedTheme = localStorage.getItem("cn-theme") || "light";
  var savedAccent = localStorage.getItem("cn-accent");
  applyTheme(savedTheme);
  if (savedAccent) {
    var a = ACCENTS.filter(function (x) { return x.v === savedAccent; })[0] || ACCENTS[0];
    applyAccent(a);
  }

  /* ---------- ساخت چرم ---------- */
  function build() {
    var body = document.body;
    var page = body.getAttribute("data-page");
    var title = body.getAttribute("data-title") || "";
    var crumb = body.getAttribute("data-crumb") || "SafirCharge";

    var stats = {};
    try { stats = JSON.parse(body.getAttribute("data-nav") || "{}") || {}; } catch (e) {}
    var faNum = function (n) { return String(n).replace(/\d/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹"[d]; }); };
    var navHTML = NAV.map(function (g) {
      var items = g.items.map(function (it) {
        if (it.stat) it.badge = stats[it.stat] ? faNum(stats[it.stat]) : "";
        var extra = it.badge ? '<span class="badge-mini tnum">' + it.badge + '</span>'
          : (it.dot ? '<span class="badge-dot"></span>' : '');
        return '<a class="nav-link' + (it.id === page ? ' active' : '') + '" href="' + adminUrl(it.path) + '">' +
          svg(it.ic) + '<span>' + it.t + '</span>' + extra + '</a>';
      }).join('');
      return '<div class="nav-group"><div class="nav-group-label">' + g.group + '</div>' + items + '</div>';
    }).join('');

    var sidebar =
      '<aside class="sidebar" id="sidebar">' +
        '<div class="brand">' +
          '<div class="brand-mark">' + svg("bolt") + '</div>' +
          '<div><div class="brand-name">SafirCharge</div><div class="brand-sub">سامانه مدیریت شارژ</div></div>' +
        '</div>' +
        '<nav class="nav">' + navHTML + '</nav>' +
        '<div class="sidebar-foot">' +
          '<div class="station-card">' +
            '<div class="sc-top"><span class="pulse"></span><span>وضعیت شبکه</span></div>' +
            '<div class="sc-meta">' + (stats.chargers ? faNum(stats.online || 0) + ' از ' + faNum(stats.chargers) + ' شارژر آنلاین · ' + faNum(Math.round(100 * (stats.online || 0) / stats.chargers)) + '٪ در دسترس' : 'شارژری ثبت نشده') + '<br>به‌روزرسانی: هر ۳۰ ثانیه</div>' +
          '</div>' +
        '</div>' +
      '</aside>';

    var accentDots = ACCENTS.map(function (x) {
      return '<button class="acc-dot" data-acc="' + x.v + '" title="' + x.name + '" style="background:' + x.v + '"></button>';
    }).join('');

    var topbar =
      '<header class="topbar">' +
        '<button class="icon-btn only-mobile" id="menuBtn" style="display:none">' + svg("grid") + '</button>' +
        '<div class="page-title"><span class="crumb">' + crumb + '</span>' + title + '</div>' +
        '<div class="search" style="position:relative"><input id="globalSearch" placeholder="جستجوی شارژر، کاربر، تراکنش…" autocomplete="off">' + svg("search") +
          '<div id="globalSearchResults" style="display:none;position:absolute;top:calc(100% + 6px);inset-inline:0;background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow-md);max-height:320px;overflow:auto;z-index:300"></div></div>' +
        '<div class="topbar-actions">' +
          '<div class="acc-picker">' + accentDots + '</div>' +
          '<button class="icon-btn" id="themeBtn">' + svg(savedTheme === "dark" ? "sun" : "moon") + '</button>' +
          '<button class="icon-btn" id="bellBtn" onclick="location.href=&quot;' + adminUrl('notifications') + '&quot;">' + svg("bell") + '<span class="dot"></span></button>' +
          '<div class="profile-wrap">' +
            '<button class="user-chip" id="userChip"><span class="av">ر.ک</span><span><span class="u-name">رضا کاظمی</span><span class="u-role">مدیر شبکه</span></span></button>' +
            '<div class="profile-menu" id="profileMenu">' +
              '<div class="pm-head"><span class="av" style="width:40px;height:40px;border-radius:12px">ر.ک</span><div><div class="fw7">رضا کاظمی</div><div class="fs12 muted">reza@safircharge.ir</div></div></div>' +
              '<a class="pm-item" href="' + adminUrl('team') + '">' + svg("shield") + '<span>نقش و دسترسی من</span><span class="tag" style="margin-inline-start:auto">مدیر کل</span></a>' +
              '<a class="pm-item" href="' + adminUrl('notifications') + '">' + svg("bell") + '<span>اعلان‌ها</span></a>' +
              '<a class="pm-item" href="' + adminUrl('settings') + '">' + svg("settings") + '<span>تنظیمات سامانه</span></a>' +
              '<div class="pm-sep"></div>' +
              '<button class="pm-item pm-theme" id="pmTheme">' + svg(savedTheme === "dark" ? "sun" : "moon") + '<span>تغییر تم روشن/تیره</span></button>' +
              '<button type="button" class="pm-item pm-danger" id="logoutBtn">' + svg("logout") + '<span>خروج از حساب</span></button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</header>';

    // قرار دادن چرم
    var shell = document.querySelector(".app");
    var main = document.querySelector(".main");
    if (shell) shell.insertAdjacentHTML("beforeend", sidebar);
    if (main) main.insertAdjacentHTML("afterbegin", topbar);

    // رویدادها
    var themeBtn = document.getElementById("themeBtn");
    themeBtn.addEventListener("click", function () {
      var cur = document.documentElement.getAttribute("data-theme");
      var next = cur === "dark" ? "light" : "dark";
      applyTheme(next);
      themeBtn.innerHTML = svg(next === "dark" ? "sun" : "moon");
    });

    document.querySelectorAll(".acc-dot").forEach(function (d) {
      d.addEventListener("click", function () {
        var v = d.getAttribute("data-acc");
        var obj = ACCENTS.filter(function (x) { return x.v === v; })[0];
        applyAccent(obj);
        markAccent();
      });
    });
    function markAccent() {
      var cur = localStorage.getItem("cn-accent") || ACCENTS[0].v;
      document.querySelectorAll(".acc-dot").forEach(function (d) {
        d.classList.toggle("on", d.getAttribute("data-acc") === cur);
      });
    }
    markAccent();

    var menuBtn = document.getElementById("menuBtn");

    // منوی پروفایل
    var userChip = document.getElementById("userChip");
    var profileMenu = document.getElementById("profileMenu");
    if (userChip) {
      userChip.addEventListener("click", function (e) {
        e.stopPropagation();
        profileMenu.classList.toggle("open");
      });
      document.addEventListener("click", function () { profileMenu.classList.remove("open"); });
      profileMenu.addEventListener("click", function (e) { e.stopPropagation(); });
      var pmTheme = document.getElementById("pmTheme");
      if (pmTheme) pmTheme.addEventListener("click", function () {
        var cur = document.documentElement.getAttribute("data-theme");
        var next = cur === "dark" ? "light" : "dark";
        applyTheme(next);
        pmTheme.innerHTML = svg(next === "dark" ? "sun" : "moon") + '<span>تغییر تم روشن/تیره</span>';
        if (themeBtn) themeBtn.innerHTML = svg(next === "dark" ? "sun" : "moon");
      });
      var logoutBtn = document.getElementById("logoutBtn");
      if (logoutBtn) logoutBtn.addEventListener("click", function () {
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var form = document.createElement("form");
        form.method = "POST";
        form.action = adminUrl("logout");
        if (csrf) {
          var token = document.createElement("input");
          token.type = "hidden";
          token.name = "_token";
          token.value = csrf.getAttribute("content");
          form.appendChild(token);
        }
        document.body.appendChild(form);
        form.submit();
      });
    }
    if (menuBtn) {
      if (window.innerWidth <= 760) menuBtn.style.display = "grid";
      menuBtn.addEventListener("click", function () {
        document.getElementById("sidebar").classList.toggle("open");
      });
    }

    if (window.AdminUI) {
      window.AdminUI.bindGlobalSearch(
        document.getElementById("globalSearch"),
        document.getElementById("globalSearchResults")
      );
    }
  }

  /* ---------- کمک‌رسان رسم نمودار خطی SVG ---------- */
  window.linePath = function (vals, w, h, pad) {
    pad = pad || 6;
    var max = Math.max.apply(null, vals), min = Math.min.apply(null, vals);
    var rng = (max - min) || 1;
    var step = (w - pad * 2) / (vals.length - 1);
    return vals.map(function (v, i) {
      var x = pad + i * step;
      var y = pad + (h - pad * 2) * (1 - (v - min) / rng);
      return (i ? "L" : "M") + x.toFixed(1) + " " + y.toFixed(1);
    }).join(" ");
  };
  window.areaPath = function (vals, w, h, pad) {
    pad = pad || 6;
    var lp = window.linePath(vals, w, h, pad);
    return lp + " L" + (w - pad) + " " + (h - pad) + " L" + pad + " " + (h - pad) + " Z";
  };

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", build);
  else build();
})();
