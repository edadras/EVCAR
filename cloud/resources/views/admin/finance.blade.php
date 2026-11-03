@extends('admin.layouts.app', ['page' => 'finance', 'title' => 'مدیریت مالی', 'crumb' => 'مالی و پرداخت'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <span class="muted fs12">مبالغ به تومان · فقط جلسات پایان‌یافته</span>
      <div class="flex gap-8">
        <a class="btn btn-sm" href="{{ route('admin.exports', 'tax') }}"><span class="bic" data-ic="download"></span> گزارش مالیاتی</a>
        <a href="/admin/settlement/create" class="btn btn-sm btn-primary">تسویهٔ جدید</a>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi">
        <div class="kpi-ic ic-green" data-ic="coins"></div>
        <div class="kpi-label">درآمد ناخالص (ماه)</div>
        <div class="kpi-val tnum">{{ Persian::money((int) round($monthRevenue / 10)) }} <small>تومان</small></div>
        <div class="kpi-foot"><span class="muted">ماه جاری (میلادی)</span></div>
      </div>
      <div class="kpi">
        <div class="kpi-ic ic-blue" data-ic="chart"></div>
        <div class="kpi-label">سهم پرداخت‌شدهٔ اپراتورها</div>
        <div class="kpi-val tnum">{{ Persian::money((int) round($paidSettlements / 10)) }} <small>تومان</small></div>
        <div class="kpi-foot"><span class="muted">تسویه‌شده</span></div>
      </div>
      <div class="kpi">
        <div class="kpi-ic ic-orange" data-ic="receipt"></div>
        <div class="kpi-label">تسویه‌های در انتظار</div>
        <div class="kpi-val tnum">{{ Persian::money((int) round($pendingSettlements / 10)) }} <small>تومان</small></div>
        <div class="kpi-foot"><span class="muted">در صف تسویه</span></div>
      </div>
      <div class="kpi">
        <div class="kpi-ic ic-violet" data-ic="leaf"></div>
        <div class="kpi-label">مالیات بر ارزش افزوده (ماه)</div>
        <div class="kpi-val tnum">{{ Persian::money((int) round($monthRevenue * $taxRate / (100 + $taxRate) / 10)) }} <small>تومان</small></div>
        <div class="kpi-foot"><span class="muted">{{ Persian::digits($taxRate) }}٪ · مبالغ شامل مالیات</span></div>
      </div>
    </div>

    <div class="grid col-2-1 mt-16">
      <!-- نمودار درآمد -->
      <div class="card">
        <div class="card-head">
          <div><h3>درآمد و سهم تفکیکی</h3><div class="ch-sub">۳۰ روز گذشته</div></div>
          <div class="head-actions">
            <span class="legend-inline"><i style="background:var(--accent)"></i>درآمد ناخالص روزانه · بیشینه <b class="tnum" id="revMax">—</b></span>
            <span class="legend-inline"><i style="background:var(--orange)"></i>سهم اپراتورها</span>
          </div>
        </div>
        <div class="card-pad">
          <svg id="revChart" width="100%" height="250" viewBox="0 0 720 250" preserveAspectRatio="none"></svg>
          <div class="chart-axis" id="revAxis"></div>
        </div>
      </div>

      <!-- منابع درآمد -->
      <div class="card">
        <div class="card-head"><h3>منابع درآمد</h3></div>
        <div class="card-pad">
          @forelse($revenueSources ?? [] as $src)
            <div class="src">
              <div class="flex between"><span class="fw7">{{ $src['label'] }}</span><span class="tnum fw7">{{ Persian::digits($src['pct']) }}٪</span></div>
              <div class="bar {{ $loop->first ? 'bar-accent' : ($loop->iteration === 3 ? 'bar-orange' : '') }} mt-8"><i style="width:{{ $src['pct'] }}%@if($loop->last);background:var(--violet)@endif"></i></div>
              <div class="muted fs12 mt-8">{{ $src['amount'] }} تومان</div>
            </div>
          @empty
            <div class="muted">دادهٔ درآمد در ۳۰ روز گذشته موجود نیست.</div>
          @endforelse
        </div>
      </div>
    </div>

    <!-- تسویه‌ها -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3>تسویه با اپراتورها</h3><div class="ch-sub">پرداخت سهم درآمد ایستگاه‌ها</div></div>
        <div class="head-actions">
          <div class="seg" id="setSeg"><button class="active">همه</button><button>در انتظار</button><button>پرداخت‌شده</button></div>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>شناسهٔ تسویه</th><th>اپراتور / مالک</th><th>دوره</th><th>درآمد ایستگاه</th><th>سهم اپراتور</th><th>کارمزد سامانه</th><th>مبلغ خالص</th><th>وضعیت</th><th></th></tr></thead>
          <tbody id="setBody" data-search-tbody></tbody>
        </table>
      </div>
    </div>

    <!-- دفتر کل -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3>دفتر کل مالی</h3><div class="ch-sub">آخرین رویدادهای حسابداری</div></div>
        <div class="head-actions"><a class="btn btn-sm" href="{{ route('admin.exports', 'revenue') }}"><span class="bic" data-ic="download"></span> خروجی درآمد</a></div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>سند</th><th>شرح</th><th>طرف حساب</th><th>بدهکار</th><th>بستانکار</th><th>مانده</th><th>تاریخ</th></tr></thead>
          <tbody id="ledgerBody"></tbody>
        </table>
      </div>
    </div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.kpi-ic[data-ic] svg{width:21px;height:21px}
.legend-inline{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text-2);font-weight:600}
.legend-inline i{width:10px;height:10px;border-radius:3px;display:inline-block}
.chart-axis{display:flex;justify-content:space-between;margin-top:8px;color:var(--muted);font-size:11px}
.src{padding:13px 0;border-bottom:1px solid var(--border-2)}.src:last-child{border-bottom:none}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  var FD=@json($financeJs);

  (function(){
    var net=FD.net||[];
    var op=FD.op||[];
    var max=Math.max.apply(null,net.concat(op).concat([1]));
    document.getElementById('revMax').textContent=Math.max.apply(null,net.concat([0])).toLocaleString('fa-IR')+' تومان';
    var W=720,H=250,pad=12;
    function path(vals,area){
      var step=(W-pad*2)/Math.max(1,vals.length-1);
      var d=vals.map(function(v,i){var x=pad+i*step;var y=pad+(H-pad*2)*(1-v/max);return (i?'L':'M')+x.toFixed(1)+' '+y.toFixed(1);}).join(' ');
      if(area)d+=' L'+(W-pad)+' '+(H-pad)+' L'+pad+' '+(H-pad)+' Z';
      return d;
    }
    var grid='';for(var g=0;g<=4;g++){var y=pad+(H-pad*2)*g/4;grid+='<line x1="'+pad+'" y1="'+y+'" x2="'+(W-pad)+'" y2="'+y+'" stroke="var(--border)" stroke-width="1" stroke-dasharray="3 5"/>';}
    document.getElementById('revChart').innerHTML=
      '<defs><linearGradient id="gR" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--accent)" stop-opacity=".26"/><stop offset="1" stop-color="var(--accent)" stop-opacity="0"/></linearGradient></defs>'+
      grid+
      '<path d="'+path(net,true)+'" fill="url(#gR)"/>'+
      '<path d="'+path(net)+'" fill="none" stroke="var(--accent)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'+
      '<path d="'+path(op)+'" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-dasharray="2 4" stroke-linecap="round" stroke-linejoin="round"/>';
    var ax=document.getElementById('revAxis');(FD.days||[]).forEach(function(t,i,a){if(i%5===0||i===a.length-1){var s=document.createElement('span');s.textContent=t;ax.appendChild(s);}});
  })();

  var setFilter='all';
  function renderSettlements(){
    document.getElementById('setBody').innerHTML=(FD.settlements||[]).filter(function(r){
      if(setFilter==='pending') return r.status[0]==='در انتظار'||r.status[0]==='در حال بررسی';
      if(setFilter==='paid') return r.status[0]==='پرداخت‌شده';
      return true;
    }).map(function(r){
      return '<tr data-search="'+r.search+'"><td class="mono">'+r.code+'</td><td class="cell-strong">'+r.partner+'</td><td class="cell-muted">'+r.period+'</td>'+
        '<td class="tnum">'+r.gross+'</td><td class="tnum">'+r.share+'</td><td class="tnum cell-muted">'+r.fee+'</td>'+
        '<td class="tnum cell-strong">'+r.net+'</td><td><span class="pill '+r.status[1]+'">'+r.status[0]+'</span></td>'+
        '<td><a href="{{ route('admin.settlement') }}" class="btn btn-sm">جزئیات</a></td></tr>';
    }).join('');
  }
  renderSettlements();

  document.getElementById('ledgerBody').innerHTML=(FD.ledger||[]).map(function(r){
    return '<tr><td class="mono">'+r.code+'</td><td class="cell-strong">'+r.desc+'</td><td class="cell-muted">'+r.party+'</td>'+
      '<td class="tnum" style="color:var(--green)">'+(r.debit!=='—'?r.debit:'—')+'</td><td class="tnum" style="color:var(--red)">'+(r.credit!=='—'?r.credit:'—')+'</td>'+
      '<td class="tnum cell-strong">'+r.balance+'</td><td class="cell-muted">'+r.date+'</td></tr>';
  }).join('');

  document.querySelectorAll('#setSeg button').forEach(function(b,i){
    b.addEventListener('click',function(){
      document.querySelectorAll('#setSeg button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      setFilter=['all','pending','paid'][i];renderSettlements();
    });
  });
})();
</script>
@endpush
