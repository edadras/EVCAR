@extends('admin.layouts.app', ['page' => 'support', 'title' => 'پشتیبانی و خطاها', 'crumb' => 'عملیات'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="kpi-grid mt-8">
      <div class="mini-stat"><div class="ms-ic ic-red" data-ic="alert"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['high']) }}</div><div class="ms-l">اولویت بالا</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="headset"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['open']) }}</div><div class="ms-l">تیکت باز</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="clock"></div><div><div class="ms-v tnum">۱۴ <small>دقیقه</small></div><div class="ms-l">میانگین زمان پاسخ</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['resolved']) }}</div><div class="ms-l">تیکت حل‌شده</div></div></div>
    </div>

    <div class="grid col-2-1 mt-16">
      <!-- خطاهای فعال -->
      <div class="card">
        <div class="card-head">
          <div><h3>خطاهای فعال شارژرها</h3><div class="ch-sub">دریافت‌شده از طریق OCPP StatusNotification</div></div>
          <div class="head-actions"><div class="seg" id="sevSeg"><button class="active">همه</button><button>بحرانی</button><button>هشدار</button></div></div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>شارژر</th><th>ایستگاه</th><th>کد خطا</th><th>شرح</th><th>زمان</th><th>شدت</th><th></th></tr></thead>
            <tbody id="faultBody"></tbody>
          </table>
        </div>
      </div>

      <!-- کنترل از راه دور -->
      <div class="card">
        <div class="card-head"><h3>کنترل از راه دور</h3></div>
        <div class="card-pad">
          <div class="ctl-sel">
            <div class="muted fs12">شارژر انتخاب‌شده</div>
            <div class="flex between items-center mt-8">
              <div><div class="fw7 fs18">CP‑۲۲۰۴</div><div class="muted fs12">برج میلاد · کانکتور ۱</div></div>
              <span class="pill pill-red"><span class="pdot"></span>قطع ارتباط</span>
            </div>
          </div>
          <div class="grid g-2 mt-16">
            <button class="ctl-btn"><span class="cb-ic ic-blue" data-ic="refresh"></span>راه‌اندازی مجدد</button>
            <button class="ctl-btn"><span class="cb-ic ic-green" data-ic="plug"></span>باز کردن قفل</button>
            <button class="ctl-btn"><span class="cb-ic ic-orange" data-ic="bolt"></span>توقف اضطراری</button>
            <button class="ctl-btn"><span class="cb-ic ic-violet" data-ic="download"></span>دریافت لاگ</button>
          </div>
          <hr class="sep">
          <div class="section-title" style="margin-bottom:10px">گزارش تشخیصی زنده</div>
          <div class="diag">
            <div class="dline"><span class="muted">۱۴:۳۲:۰۸</span> اتصال OCPP برقرار شد</div>
            <div class="dline warn"><span class="muted">۱۴:۳۲:۱۱</span> هشدار: افت ولتاژ ورودی (۱۹۸V)</div>
            <div class="dline err"><span class="muted">۱۴:۳۲:۴۵</span> خطا: قطع ارتباط کانکتور ۱</div>
            <div class="dline"><span class="muted">۱۴:۳۳:۰۲</span> تلاش برای اتصال مجدد…</div>
            <div class="dline ok"><span class="muted">۱۴:۳۳:۲۰</span> Heartbeat دریافت شد</div>
          </div>
        </div>
      </div>
    </div>

    <!-- تیکت‌ها -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3>تیکت‌های پشتیبانی کاربران</h3></div>
        <div class="head-actions"><div class="search" style="max-width:220px"><input id="ticketSearch" placeholder="جستجوی تیکت…" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div></div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>شناسه</th><th>کاربر</th><th>موضوع</th><th>دسته</th><th>کارشناس</th><th>آخرین به‌روزرسانی</th><th>اولویت</th><th>وضعیت</th></tr></thead>
          <tbody id="ticketBody"></tbody>
        </table>
      </div>
    </div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-v small{font-size:12px;font-weight:600;color:var(--text-2)}.ms-l{font-size:12px;color:var(--muted)}
.ic-red{background:var(--red-soft);color:var(--red)}

.ctl-sel{background:var(--surface-2);border:1px solid var(--border-2);border-radius:var(--r);padding:14px 16px}
.ctl-btn{display:flex;flex-direction:column;align-items:center;gap:9px;padding:16px 10px;border:1px solid var(--border);border-radius:14px;background:var(--surface);font-size:12.5px;font-weight:600;color:var(--text);transition:all .15s}
.ctl-btn:hover{background:var(--surface-2);border-color:var(--accent)}
.cb-ic{width:40px;height:40px;border-radius:11px;display:grid;place-items:center}.cb-ic svg{width:20px;height:20px}

.diag{background:#0C2236;border-radius:12px;padding:14px;font-family:ui-monospace,monospace;font-size:12px;color:#cfe0f0;line-height:1.9}
.dline .muted{color:#6e8499;margin-inline-end:8px}
.dline.warn{color:#fbbf24}.dline.err{color:#fb7185}.dline.ok{color:#4ade80}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  var SD=@json($supportJs);

  document.getElementById('faultBody').innerHTML=(SD.faults||[]).map(function(r){
    return '<tr data-sev="'+r.sev+'" data-search="'+r.search+'"><td class="mono cell-strong">'+r.charger+'</td><td>'+r.station+'</td>'+
      '<td><span class="tag">'+r.code+'</span></td><td class="cell-muted">'+r.desc+'</td><td class="cell-muted">'+r.when+'</td>'+
      '<td><span class="pill '+r.label[1]+'">'+r.label[0]+'</span></td>'+
      '<td><a href="{{ route('admin.remote') }}" class="btn btn-sm">رسیدگی</a></td></tr>';
  }).join('');

  document.getElementById('ticketBody').innerHTML=(SD.tickets||[]).map(function(r){
    return '<tr data-search="'+r.search+'"><td class="mono">'+r.id+'</td><td class="cell-strong">'+r.user+'</td><td>'+r.subject+'</td>'+
      '<td><span class="tag">'+r.category+'</span></td><td class="cell-muted">'+r.assignee+'</td><td class="cell-muted">'+r.when+'</td>'+
      '<td><span class="pill '+r.priority[1]+'">'+r.priority[0]+'</span></td><td><span class="pill '+r.status[1]+'">'+r.status[0]+'</span></td></tr>';
  }).join('');
  if(window.AdminUI) window.AdminUI.bindTableSearch('#ticketSearch','#ticketBody');

  document.querySelectorAll('#sevSeg button').forEach(function(b,idx){
    b.addEventListener('click',function(){
      document.querySelectorAll('#sevSeg button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      document.querySelectorAll('#faultBody tr').forEach(function(tr){
        var sev=tr.getAttribute('data-sev');
        var show=idx===0||(idx===1&&sev==='crit')||(idx===2&&sev==='warn');
        tr.style.display=show?'':'none';
      });
    });
  });
})();
</script>
@endpush
