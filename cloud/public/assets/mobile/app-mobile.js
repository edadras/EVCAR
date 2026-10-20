/* ============================================================
   SafirCharge — اپ مشتری: نوار وضعیت + ناوبری پایین + آیکون‌ها
   body[data-tab] تب فعال را مشخص می‌کند.
   ============================================================ */
(function(){
  "use strict";
  var I={
    home:'<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
    map:'<path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3zM9 3v15M15 6v15"/>',
    wallet:'<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M16 12h.01M3 9h18"/>',
    user:'<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
    bolt:'<path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/>',
    scan:'<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/>',
    qr:'<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h3v3h-3zM20 14v6M17 20h3"/>',
    bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
    chevron:'<path d="M15 18l-6-6 6-6"/>',
    back:'<path d="M9 18l6-6-6-6"/>',
    plus:'<path d="M12 5v14M5 12h14"/>',
    minus:'<path d="M5 12h14"/>',
    clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    calendar:'<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    leaf:'<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/>',
    plug:'<path d="M9 2v6M15 2v6M6 8h12v3a6 6 0 0 1-12 0zM12 17v5"/>',
    pin:'<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    search:'<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    nav:'<path d="M3 11l19-9-9 19-2-8-8-2z"/>',
    star:'<path d="M12 2l3 7 7 .5-5.5 4.5 2 7-6.5-4-6.5 4 2-7L2 9.5 9 9z"/>',
    card:'<path d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zM2 10h20"/>',
    chip:'<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>',
    coins:'<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18M7 6h1v4M16.71 13.88l.7.71-2.82 2.82"/>',
    gift:'<path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
    alert:'<path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/>',chart:'<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',receipt:'<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1V2l-2 1-2-1-2 1-2-1-2 1-2-1zM8 7h8M8 11h8M8 15h5"/>',
    settings:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    headset:'<path d="M3 14v-2a9 9 0 0 1 18 0v2M21 14a2 2 0 0 1-2 2h-1v-5h1a2 2 0 0 1 2 2zM3 14a2 2 0 0 0 2 2h1v-5H5a2 2 0 0 0-2 2zM21 16v1a4 4 0 0 1-4 4h-5"/>',
    shield:'<path d="M12 2l8 4v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/>',
    key:'<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.5 12.5 21 2M16 7l3 3M14 9l3 3"/>',
    logout:'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    car:'<path d="M5 13l2-5h10l2 5M3 13h18v5H3zM6 18v2M18 18v2M6.5 13.5h.01M17.5 13.5h.01"/>',
    filter:'<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
    share:'<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5L8.6 10.5"/>',
    check:'<path d="M20 6 9 17l-5-5"/>',
    snow:'<path d="M12 2v20M4 6l16 12M20 6L4 18"/>',
    download:'<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    eye:'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>'
  };
  function svg(n){return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'+(I[n]||'')+'</svg>';}
  window.icon=svg;

  window.plate=function(p){
    var cls="plate"+(p.size?" "+p.size:"");
    return '<span class="'+cls+'"><span class="pl-flag"><i></i><i></i><i></i></span>'+
      '<span class="pl-main"><span>'+p.d2+'</span><span class="pl-let">'+(p['let']||p.letter)+'</span><span>'+p.d3+'</span></span>'+
      '<span class="pl-prov"><b>'+p.prov+'</b><small>ایران</small></span></span>';
  };

  // تم/رنگ از حافظه (مشترک با پنل مدیریت)
  var theme=localStorage.getItem("cn-theme")||"light";
  document.documentElement.setAttribute("data-theme",theme);
  var acc=localStorage.getItem("cn-accent");
  var rgbMap={"#0E6BA8":"14, 107, 168","#16A34A":"22, 163, 74","#F97316":"249, 115, 22","#7C3AED":"124, 58, 237","#0891B2":"8, 145, 178"};
  if(acc&&rgbMap[acc]){document.documentElement.style.setProperty("--accent",acc);document.documentElement.style.setProperty("--accent-rgb",rgbMap[acc]);}

  var R=window.SC_ROUTES||{};
  var TABS=[
    {id:"home",t:"پیشخان",ic:"home",href:R.home||"/app"},
    {id:"map",t:"نقشه",ic:"map",href:R.map||"/app/map"},
    {id:"scan",t:"",ic:"qr",href:R.scan||"/app/scan",fab:true},
    {id:"wallet",t:"کیف پول",ic:"wallet",href:R.wallet||"/app/wallet"},
    {id:"account",t:"حساب",ic:"user",href:R.account||"/app/account"}
  ];

  function build(){
    var body=document.body;
    var tab=body.getAttribute("data-tab");
    var noTab=body.hasAttribute("data-no-tab");
    var dark=body.hasAttribute("data-dark-status");

    // نوار وضعیت سیستم‌عامل گوشی نمایش داده می‌شود؛ نوار جعلی اضافه نمی‌کنیم.

    // ناو پایین
    if(!noTab){
      var phone=document.querySelector(".phone");
      var tb='<nav class="tabbar">'+TABS.map(function(t){
        if(t.fab) return '<a class="tab-fab" href="'+t.href+'" aria-label="اسکن">'+svg(t.ic)+'</a>';
        return '<a class="tab'+(t.id===tab?' active':'')+'" href="'+t.href+'">'+svg(t.ic)+'<span>'+t.t+'</span></a>';
      }).join('')+'</nav>';
      phone.insertAdjacentHTML("beforeend",tb);
    }

    // آیکون‌های data-ic
    document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=svg(el.getAttribute('data-ic'));});
  }

  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",build);
  else build();
})();
