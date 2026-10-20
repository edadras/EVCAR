@extends('admin.layouts.app', ['page' => 'partners', 'title' => 'شرکا و مالکان شارژر', 'crumb' => 'عملیات'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="tabs" id="tabs">
        <button class="active" data-tab="partners">مالکان شارژر</button>
        <button data-tab="invoices">فاکتورها و تسویه</button>
        <button data-tab="stats">آمار و عملکرد</button>
      </div>
      <div class="flex gap-8">
        <button class="btn btn-sm"><span class="bic" data-ic="download"></span> خروجی</button>
        <a href="#" class="btn btn-sm"><span class="bic" data-ic="building"></span> پنل شرکا</a>
        <a href="/admin/partners/create" class="btn btn-sm btn-primary"><span class="bic" data-ic="plus"></span> افزودن مالک / شریک</a>
      </div>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="building"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['active']) }}</div><div class="ms-l">مالک فعال (CPO میزبان)</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="station"></div><div><div class="ms-v tnum">{{ Persian::digits($partners->sum('stations_count')) }}</div><div class="ms-l">ایستگاه شرکا</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="coins"></div><div><div class="ms-v tnum">{{ Persian::digits(round($partnersJs['gross'][5] ?? 0)) }} <small>میلیارد</small></div><div class="ms-l">سهم شرکا (این ماه)</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="receipt"></div><div><div class="ms-v tnum">{{ Persian::digits(count($partnersJs['invoices'] ?? [])) }}</div><div class="ms-l">فاکتور اخیر</div></div></div>
    </div>

    <!-- مالکان -->
    <section data-pane="partners">
      <div class="grid col-2-1 mt-16">
        <div class="card">
          <div class="card-head"><div><h3>درآمد شرکا و سهم تفکیکی</h3><div class="ch-sub">۶ ماه گذشته · کل شبکهٔ میزبان</div></div>
            <div class="head-actions"><span class="legend-inline"><i style="background:var(--accent)"></i>درآمد ناخالص</span><span class="legend-inline"><i style="background:var(--orange)"></i>سهم شریک</span></div>
          </div>
          <div class="card-pad">
            <svg id="revChart" width="100%" height="220" viewBox="0 0 700 220" preserveAspectRatio="none"></svg>
            <div class="chart-axis" id="revAxis"></div>
          </div>
        </div>
        <div class="card">
          <div class="card-head"><h3>مدل همکاری</h3></div>
          <div class="card-pad">
            @forelse($partnersJs['cooperationModels'] ?? [] as $m)
              <div class="model-row">
                <div class="mr-pct tnum">{{ $m['pct'] > 0 ? Persian::digits($m['pct']) : '—' }}@if($m['pct'] > 0)<small>٪</small>@endif</div>
                <div class="l-main"><div class="l-title">{{ $m['label'] }}</div><div class="l-sub">سهم مالک {{ Persian::digits($m['pct']) }}٪</div></div>
                <b class="tnum">{{ Persian::digits($m['count']) }} مالک</b>
              </div>
            @empty
              <div class="muted">شریکی ثبت نشده است.</div>
            @endforelse
          </div>
        </div>
      </div>

      <div class="card mt-16">
        <div class="card-head">
          <div><h3>فهرست مالکان و شرکا</h3><div class="ch-sub">کسانی که پایانهٔ شارژر خود را وارد سامانه کرده‌اند</div></div>
          <div class="head-actions"><div class="search" style="max-width:240px"><input id="partnerSearch" placeholder="جستجوی مالک…" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div></div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>مالک / شریک</th><th>نوع</th><th>ایستگاه‌ها</th><th>مدل همکاری</th><th>انرژی (ماه)</th><th>درآمد ناخالص</th><th>سهم مالک</th><th>وضعیت تسویه</th><th></th></tr></thead>
            <tbody id="partnerBody"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- فاکتورها -->
    <section data-pane="invoices" style="display:none">
      <div class="grid col-2-1 mt-16">
        <div class="card">
          <div class="card-head"><div><h3>فاکتورهای صادرشده برای شرکا</h3><div class="ch-sub">صورت‌حساب سهم درآمد و کارمزد سامانه</div></div>
            <div class="head-actions"><button class="btn btn-sm btn-primary"><span class="bic" data-ic="plus"></span> صدور فاکتور</button></div>
          </div>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr><th>شماره فاکتور</th><th>مالک</th><th>دوره</th><th>مبلغ سهم</th><th>کارمزد سامانه</th><th>مبلغ خالص</th><th>وضعیت</th><th></th></tr></thead>
              <tbody id="invBody"></tbody>
            </table>
          </div>
        </div>

        <!-- پیش‌نمایش فاکتور -->
        <div class="card" style="align-self:start">
          <div class="card-head"><h3>نمونهٔ فاکتور</h3></div>
          <div class="card-pad">
            @php $inv = $partnersJs['sampleInvoice'] ?? null; @endphp
            <div class="invoice">
              @if($inv)
              <div class="inv-top">
                <div><div class="inv-logo"><span data-ic="bolt"></span>SafirCharge</div><div class="fs12 muted mt-4">فاکتور سهم درآمد</div></div>
                <div style="text-align:end"><div class="fw8 mono">{{ $inv->invoice_number }}</div><div class="fs12 muted">{{ $inv->issued_at?->format('Y/m') }}</div></div>
              </div>
              <hr class="sep">
              <div class="inv-row"><span class="muted">مالک</span><b>{{ $inv->partner?->name ?? '—' }}</b></div>
              <div class="inv-row"><span class="muted">وضعیت</span><b>{{ $inv->status }}</b></div>
              <hr class="sep">
              <div class="inv-row"><span class="muted">جمع جزء</span><b class="tnum">{{ Persian::digits((int) round($inv->subtotal / 10)) }}</b></div>
              <div class="inv-row"><span class="muted">مالیات</span><b class="tnum">{{ Persian::digits((int) round($inv->tax_amount / 10)) }}</b></div>
              <hr class="sep">
              <div class="inv-row total"><span>مبلغ قابل پرداخت</span><b class="tnum">{{ Persian::digits((int) round($inv->total_amount / 10)) }}</b></div>
              @else
              <div class="muted">فاکتوری برای نمایش موجود نیست.</div>
              @endif
              <div class="flex gap-8 mt-16"><button type="button" class="btn btn-sm btn-primary" style="flex:1"><span class="bic" data-ic="download"></span> دانلود PDF</button><button type="button" class="btn btn-sm" style="flex:1">ارسال به مالک</button></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- آمار -->
    <section data-pane="stats" style="display:none">
      <div class="grid g-3 mt-16">
        <div class="card card-pad"><div class="muted fs12">میانگین درآمد ماهانهٔ هر مالک</div><div class="fs22 fw8 tnum mt-8">{{ Persian::digits($partnersJs['avgPartnerRevenue'] ?? 0) }} <small style="font-size:13px;color:var(--text-2)">میلیون</small></div><div class="bar bar-accent mt-12"><i style="width:{{ min(100, $partnersJs['avgPartnerRevenue'] ?? 0) }}%"></i></div></div>
        <div class="card card-pad"><div class="muted fs12">میانگین بهره‌برداری ایستگاه‌های شرکا</div><div class="fs22 fw8 tnum mt-8">{{ Persian::digits($partnersJs['partnerUtil'] ?? 0) }}٪</div><div class="bar mt-12"><i style="width:{{ $partnersJs['partnerUtil'] ?? 0 }}%"></i></div></div>
        <div class="card card-pad"><div class="muted fs12">نرخ تسویهٔ به‌موقع</div><div class="fs22 fw8 tnum mt-8">{{ Persian::digits($partnersJs['onTimePct'] ?? 0) }}٪</div><div class="bar bar-orange mt-12"><i style="width:{{ $partnersJs['onTimePct'] ?? 0 }}%"></i></div></div>
      </div>

      <div class="grid col-2-1 mt-16">
        <div class="card">
          <div class="card-head"><div><h3>برترین مالکان بر اساس درآمد</h3><div class="ch-sub">این ماه</div></div></div>
          <div class="card-pad" style="padding-top:6px" id="topPartners"></div>
        </div>
        <div class="card">
          <div class="card-head"><h3>رشد جذب شرکا</h3></div>
          <div class="card-pad">
            <svg id="growthChart" width="100%" height="200" viewBox="0 0 360 200" preserveAspectRatio="none"></svg>
            <div class="chart-axis"><span>تیر</span><span>مرداد</span><span>شهریور</span><span>مهر</span><span>آبان</span><span>آذر</span></div>
          </div>
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
.legend-inline{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text-2);font-weight:600}.legend-inline i{width:10px;height:10px;border-radius:3px}
.chart-axis{display:flex;justify-content:space-between;margin-top:8px;color:var(--muted);font-size:11px}

.model-row{display:flex;align-items:center;gap:13px;padding:13px 0;border-bottom:1px solid var(--border-2)}.model-row:last-child{border-bottom:none}
.mr-pct{width:54px;height:54px;border-radius:14px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;font-weight:800;font-size:18px;flex:none}.mr-pct small{font-size:11px}

.invoice{border:1px solid var(--border-2);border-radius:16px;padding:18px;background:var(--surface-2)}
.inv-top{display:flex;justify-content:space-between;align-items:flex-start}
.inv-logo{display:inline-flex;align-items:center;gap:6px;font-weight:800;font-size:15px}.inv-logo [data-ic]{display:inline-grid;width:18px;height:18px;color:var(--accent)}.inv-logo svg{width:18px;height:18px}
.inv-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:5px 0}
.inv-row.total{font-size:15px;font-weight:800;background:var(--accent-soft);margin:0 -8px;padding:11px 12px;border-radius:10px;color:var(--accent-strong)}
.top-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-2)}.top-row:last-child{border-bottom:none}
.top-rank{width:28px;height:28px;border-radius:8px;background:var(--surface-3);display:grid;place-items:center;font-weight:800;font-size:13px;flex:none}
.top-rank.gold{background:#FEF3C7;color:#D97706}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  var PD=@json($partnersJs);

  // نمودار درآمد
  (function(){
    var gross=PD.gross||[],share=PD.share||[];
    var W=700,H=220,pad=14,max=700,bw=40,gap=(W-pad*2)/gross.length;
    var bars='';gross.forEach(function(v,i){
      var x=pad+i*gap+gap/2;
      var h1=(H-pad*2)*v/max,y1=H-pad-h1;
      var h2=(H-pad*2)*share[i]/max,y2=H-pad-h2;
      bars+='<rect x="'+(x-bw/2-3)+'" y="'+y1+'" width="'+bw+'" height="'+h1+'" rx="7" fill="var(--accent)" opacity=".85"/>';
      bars+='<rect x="'+(x-bw/2-3)+'" y="'+y2+'" width="'+bw+'" height="'+h2+'" rx="7" fill="var(--orange)"/>';
    });
    document.getElementById('revChart').innerHTML=bars;
    var ax=document.getElementById('revAxis');['تیر','مرداد','شهریور','مهر','آبان','آذر'].forEach(function(t){var s=document.createElement('span');s.textContent=t;ax.appendChild(s);});
  })();

  document.getElementById('partnerBody').innerHTML=(PD.partners||[]).map(function(p){
    return '<tr data-search="'+p.search+'">'+
      '<td><div class="cell-user"><span class="uavatar" style="background:'+p.c+';border-radius:9px">'+p.n[0]+'</span><span class="cell-strong">'+p.n+'</span></div></td>'+
      '<td><span class="'+(p.t[1]==='tag'?'tag':'pill '+p.t[1])+'">'+p.t[0]+'</span></td>'+
      '<td class="tnum">'+p.st+'</td><td class="cell-muted">'+p.model+'</td>'+
      '<td class="tnum">'+p.en+'</td><td class="tnum">'+p.gross+'</td>'+
      '<td class="tnum cell-strong" style="color:var(--green)">'+p.share+'</td>'+
      '<td><span class="pill '+p.set[1]+'">'+(p.set[1]==='pill-green'?'<span class="pdot"></span>':'')+p.set[0]+'</span></td>'+
      '<td><button class="btn btn-sm">جزئیات</button></td></tr>';
  }).join('');

  document.getElementById('invBody').innerHTML=(PD.invoices||[]).map(function(r){
    return '<tr data-search="'+r.search+'"><td class="mono">'+r.code+'</td><td class="cell-strong">'+r.partner+'</td><td class="cell-muted">'+r.period+'</td>'+
      '<td class="tnum" style="color:var(--green)">'+r.share+'</td><td class="tnum cell-muted">'+r.fee+'</td><td class="tnum cell-strong">'+r.net+'</td>'+
      '<td><span class="pill '+r.status[1]+'">'+r.status[0]+'</span></td><td><button class="btn btn-sm">مشاهده</button></td></tr>';
  }).join('');

  document.getElementById('topPartners').innerHTML=(PD.topPartners||[]).map(function(t,i){
    return '<div class="top-row"><span class="top-rank'+(i===0?' gold':'')+'">'+(i+1).toLocaleString('fa-IR')+'</span>'+
      '<div class="l-main"><div class="l-title">'+t.n+'</div><div class="bar bar-accent" style="margin-top:6px"><i style="width:'+t.p+'%"></i></div></div>'+
      '<b class="tnum" style="margin-inline-start:auto">'+t.v+'</b></div>';
  }).join('');

  (function(){
    var vals=PD.growth||[0];var W=360,H=200,pad=12,max=Math.max(1,Math.max.apply(null,vals.length?vals:[1]));
    var step=(W-pad*2)/(vals.length-1);
    function p(area){var d=vals.map(function(v,i){var x=pad+i*step;var y=pad+(H-pad*2)*(1-v/max);return (i?'L':'M')+x.toFixed(1)+' '+y.toFixed(1);}).join(' ');if(area)d+=' L'+(W-pad)+' '+(H-pad)+' L'+pad+' '+(H-pad)+' Z';return d;}
    document.getElementById('growthChart').innerHTML=
      '<defs><linearGradient id="gp" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--green)" stop-opacity=".25"/><stop offset="1" stop-color="var(--green)" stop-opacity="0"/></linearGradient></defs>'+
      '<path d="'+p(true)+'" fill="url(#gp)"/><path d="'+p()+'" fill="none" stroke="var(--green)" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>';
  })();

  document.querySelectorAll('#tabs button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#tabs button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      var t=b.getAttribute('data-tab');
      document.querySelectorAll('[data-pane]').forEach(function(s){s.style.display=s.getAttribute('data-pane')===t?'':'none';});
    });
  });
  if(window.AdminUI) window.AdminUI.bindTableSearch('#partnerSearch','#partnerBody');
})();
</script>
@endpush
