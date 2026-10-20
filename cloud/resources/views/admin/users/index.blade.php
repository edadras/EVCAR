@extends('admin.layouts.app', ['page' => 'users', 'title' => 'کاربران و سازمان‌ها', 'crumb' => 'عملیات'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="tabs mt-8" id="tabs" style="margin-bottom:18px">
      <button class="active" data-tab="users">کاربران</button>
      <button data-tab="org">حساب‌های سازمانی</button>
    </div>

    <!-- کاربران -->
    <section data-pane="users">
      <div class="kpi-grid" style="margin-bottom:16px">
        <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="users"></div><div><div class="ms-v tnum">{{ Persian::digits(number_format($kpis['total'])) }}</div><div class="ms-l">کل کاربران</div></div></div>
        <div class="mini-stat"><div class="ms-ic ic-green" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::digits(number_format($kpis['active_30d'])) }}</div><div class="ms-l">کاربر فعال (۳۰ روز)</div></div></div>
        <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="plus"></div><div><div class="ms-v tnum">{{ Persian::digits(number_format($kpis['new_month'])) }}</div><div class="ms-l">عضویت جدید این ماه</div></div></div>
        <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="coins"></div><div><div class="ms-v tnum">{{ Persian::digits($kpis['avg_balance_million']) }} <small>م</small></div><div class="ms-l">میانگین موجودی کیف پول</div></div></div>
      </div>

      <div class="card">
        <div class="card-head">
          <div><h3>فهرست کاربران</h3></div>
          <div class="head-actions">
            <div class="search" style="max-width:220px"><input id="userSearch" placeholder="جستجوی نام یا موبایل…" autocomplete="off" value="{{ request('q') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
            <div class="seg" id="roleSeg"><button class="active">همه</button><button>عادی</button><button>سازمانی</button></div>
            <a href="/admin/users/create" class="btn btn-sm btn-primary"><span class="bic" data-ic="plus"></span> کاربر جدید</a>
          </div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>کاربر</th><th>شماره موبایل</th><th>پلاک خودرو</th><th>نقش</th><th>شارژ خودکار</th><th>موجودی کیف پول</th><th>جلسات</th><th>وضعیت</th><th></th></tr></thead>
            <tbody id="userBody"></tbody>
          </table>
        </div>
        <div class="flex between items-center" style="padding:14px 18px;border-top:1px solid var(--border-2)">
          <span class="muted fs12">نمایش {{ Persian::digits($users->firstItem() ?? 0) }} تا {{ Persian::digits($users->lastItem() ?? 0) }} از {{ Persian::digits(number_format($users->total())) }}</span>
          <div class="flex gap-8">
            @if ($users->onFirstPage())<button class="btn btn-sm" disabled>قبلی</button>@else<a href="{{ $users->previousPageUrl() }}" class="btn btn-sm">قبلی</a>@endif
            <button class="btn btn-sm btn-primary">{{ Persian::digits($users->currentPage()) }}</button>
            @if ($users->hasMorePages())<a href="{{ $users->nextPageUrl() }}" class="btn btn-sm">بعدی</a>@else<button class="btn btn-sm" disabled>بعدی</button>@endif
          </div>
        </div>
      </div>
    </section>

    <!-- سازمان‌ها -->
    <section data-pane="org" style="display:none">
      <div class="grid g-3" id="orgCards"></div>
      <div class="card mt-16">
        <div class="card-head"><div><h3>نقش‌های سامانه</h3><div class="ch-sub">سطوح دسترسی و مدیریت</div></div></div>
        <div class="card-pad">
          <div class="grid g-3" id="roleCards"></div>
        </div>
      </div>
    </section>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-v small{font-size:12px;font-weight:600;color:var(--text-2)}.ms-l{font-size:12px;color:var(--muted)}

.org-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:18px;box-shadow:var(--shadow-sm)}
.org-top{display:flex;align-items:center;gap:12px}
.org-logo{width:46px;height:46px;border-radius:13px;display:grid;place-items:center;color:#fff;font-weight:800;font-size:16px;flex:none}
.org-stats{display:flex;gap:18px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border-2)}
.org-stats>div{flex:1}.org-stats .v{font-size:17px;font-weight:800}.org-stats .l{font-size:11px;color:var(--muted)}

.role-card{border:1px solid var(--border);border-radius:14px;padding:16px}
.role-card h4{font-size:14px}.role-card .rd{font-size:12px;color:var(--muted);margin-top:4px;line-height:1.6}
.role-card .rc{display:inline-flex;align-items:center;gap:6px;margin-top:10px;font-size:11.5px;color:var(--text-2)}
</style>
@endpush

@push('scripts')
<script>
window.__USERS = {!! json_encode($usersJs, JSON_UNESCAPED_UNICODE) !!};
(function(){
  var DB=window.__USERS||{};
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  function init(s){return s.split(' ').map(function(w){return w[0];}).join('.');}

  var users=(DB.users&&DB.users.length)?DB.users:[];
  document.getElementById('userBody').innerHTML=users.map(function(u){
    return '<tr data-role="'+u.roleKey+'" data-search="'+u.n+' '+u.m+'">'+
      '<td><div class="cell-user"><span class="uavatar" style="background:'+u.c+'">'+init(u.n)+'</span><span class="cell-strong">'+u.n+'</span></div></td>'+
      '<td class="mono cell-muted">'+u.m+'</td>'+
      '<td>'+(u.pl?window.plate(Object.assign({size:'sm'},u.pl)):'<span class="muted fs12">—</span>')+'</td>'+
      '<td><span class="'+(u.r[1]==='tag'?'tag':'pill '+u.r[1])+'">'+u.r[0]+'</span></td>'+
      '<td>'+(u.auto?'<span class="pill pill-green"><span class="pdot"></span>فعال</span>':'<span class="pill">غیرفعال</span>')+'</td>'+
      '<td class="tnum cell-strong">'+u.w+'</td>'+
      '<td class="tnum">'+u.s+'</td>'+
      '<td><span class="pill '+u.st[1]+'">'+(u.st[1]==='pill-green'?'<span class="pdot"></span>':'')+u.st[0]+'</span></td>'+
      '<td><button class="btn btn-sm">پروفایل</button></td></tr>';
  }).join('');

  var orgs=(DB.orgs&&DB.orgs.length)?DB.orgs:[];
  document.getElementById('orgCards').innerHTML=orgs.map(function(o){
    return '<div class="org-card">'+
      '<div class="org-top"><div class="org-logo" style="background:'+o.c+'">'+o.n[0]+'</div>'+
      '<div><div class="fw7">'+o.n+'</div><div class="muted fs12">'+o.t+'</div></div>'+
      '<span class="pill pill-green" style="margin-inline-start:auto"><span class="pdot"></span>فعال</span></div>'+
      '<div class="org-stats"><div><div class="v tnum">'+o.cars+'</div><div class="l">ناوگان</div></div>'+
      '<div><div class="v tnum">'+o.cards+'</div><div class="l">کارت RFID</div></div></div>'+
      '<div class="org-stats" style="border-top:none;padding-top:0;margin-top:10px"><div><div class="v tnum">'+o.cap+'</div><div class="l">سقف مصرف</div></div>'+
      '<div><div class="v tnum">'+o.spent+'</div><div class="l">مصرف این ماه</div></div></div>'+
      '</div>';
  }).join('');

  var roles=(DB.roles&&DB.roles.length)?DB.roles:[];
  document.getElementById('roleCards').innerHTML=roles.map(function(r){
    return '<div class="role-card"><h4>'+r.t+'</h4><div class="rd">'+r.d+'</div><div class="rc">'+window.icon('users')+' '+r.n+'</div></div>';
  }).join('');
  document.querySelectorAll('.rc svg').forEach(function(s){s.style.width='15px';s.style.height='15px';});

  if(window.AdminUI){
    window.AdminUI.bindSegRows('roleSeg','#userBody',function(tr,idx){
      var role=tr.getAttribute('data-role');
      return idx===0||(idx===1&&role==='normal')||(idx===2&&role==='org');
    });
    window.AdminUI.bindTableSearch('#userSearch','#userBody',function(tr){
      var idx=[].indexOf.call(document.querySelectorAll('#roleSeg button'),document.querySelector('#roleSeg button.active'));
      var role=tr.getAttribute('data-role');
      return idx===0||(idx===1&&role==='normal')||(idx===2&&role==='org');
    });
    if(document.getElementById('userSearch').value) document.getElementById('userSearch').dispatchEvent(new Event('input'));
  }
  document.querySelectorAll('#tabs button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#tabs button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      var t=b.getAttribute('data-tab');
      document.querySelectorAll('[data-pane]').forEach(function(p){p.style.display=p.getAttribute('data-pane')===t?'':'none';});
    });
  });
})();
</script>
@endpush
