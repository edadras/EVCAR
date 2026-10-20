@extends('admin.layouts.app', ['page' => 'reports', 'title' => 'گزارش‌ها و تحلیل', 'crumb' => 'مالی و پرداخت'])

@section('content')
@php
    use App\Support\Persian;
    $f = $filters ?? ($reportsJs['filters'] ?? []);
@endphp

<div class="flex between items-center mt-8" style="margin-bottom:16px">
      <div class="rep-tabs" id="repTabs">
        <button class="active" data-r="energy" type="button"><span data-ic="bolt"></span>انرژی و مصرف</button>
        <button data-r="revenue" type="button"><span data-ic="coins"></span>درآمد</button>
        <button data-r="sessions" type="button"><span data-ic="activity"></span>جلسات</button>
        <button data-r="users" type="button"><span data-ic="users"></span>کاربران</button>
        <button data-r="stations" type="button"><span data-ic="station"></span>ایستگاه‌ها</button>
      </div>
      <div class="flex gap-8">
        <button class="btn btn-sm" type="button"><span class="bic" data-ic="download"></span> Excel</button>
        <button class="btn btn-sm" type="button"><span class="bic" data-ic="download"></span> PDF</button>
      </div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="card-pad" style="padding:14px 16px">
        <div class="filter-bar">
          <div class="search" style="max-width:none;flex:1;min-width:200px"><input id="reportSearch" data-search-for="tblBody" placeholder="جستجو در جدول (کاربر، ایستگاه، تاریخ…)" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
          <div class="seg" id="rangeSeg">
            <button type="button" data-range="week" @class(['active' => ($f['range'] ?? 'month') === 'week'])>هفته</button>
            <button type="button" data-range="month" @class(['active' => ($f['range'] ?? 'month') === 'month'])>ماه</button>
            <button type="button" data-range="quarter" @class(['active' => ($f['range'] ?? '') === 'quarter'])>فصل</button>
            <button type="button" data-range="year" @class(['active' => ($f['range'] ?? '') === 'year'])>سال</button>
            <button type="button" data-range="custom" @class(['active' => ($f['range'] ?? '') === 'custom'])>دلخواه</button>
          </div>
          <select class="fselect filt" id="cityFilter">
            <option value="">همهٔ شهرها</option>
            @foreach($reportsJs['cities'] ?? [] as $city)
              <option value="{{ $city }}" @selected(($f['city'] ?? '') === $city)>{{ $city }}</option>
            @endforeach
          </select>
          <select class="fselect filt" id="connectorFilter">
            <option value="">همهٔ کانکتورها</option>
            <option value="dc" @selected(($f['connector'] ?? '') === 'dc')>DC سریع</option>
            <option value="ac" @selected(($f['connector'] ?? '') === 'ac')>AC</option>
          </select>
        </div>
        <div id="customRange" class="flex gap-8 mt-12" style="display:{{ ($f['range'] ?? '') === 'custom' ? 'flex' : 'none' }}">
          <input type="date" class="finput" id="dateFrom" value="{{ $f['date_from'] ?? '' }}" style="max-width:180px">
          <input type="date" class="finput" id="dateTo" value="{{ $f['date_to'] ?? '' }}" style="max-width:180px">
          <button type="button" class="btn btn-sm btn-primary" id="applyCustom">اعمال بازه</button>
        </div>
        <div class="chips mt-12" id="chips">
          <span class="fchip" data-chip="range">{{ $f['range_label'] ?? 'ماه اخیر' }} <button type="button">✕</button></span>
          <span class="fchip" data-chip="connector">{{ $f['connector_label'] ?? 'همه کانکتورها' }} <button type="button">✕</button></span>
          @if(!empty($f['city']))
          <span class="fchip" data-chip="city">{{ $f['city'] }} <button type="button">✕</button></span>
          @endif
          <span class="muted fs12" style="margin-inline-start:auto" id="recordCount">{{ $reportsJs['recordCount'] ?? '—' }} رکورد · {{ $reportsJs['chartSubtitle'] ?? '' }}</span>
        </div>
      </div>
    </div>

    <div class="kpi-grid" id="kpis"></div>

    <div class="grid col-2-1 mt-16">
      <div class="card">
        <div class="card-head"><div><h3 id="chartTitle">روند انرژی تحویلی</h3><div class="ch-sub" id="chartSub">{{ $reportsJs['chartSubtitle'] ?? '۳۰ روز گذشته' }}</div></div>
          <div class="head-actions"><div class="seg" id="chartType"><button type="button" class="active">میله‌ای</button><button type="button">خطی</button></div></div>
        </div>
        <div class="card-pad">
          <svg id="mainChart" width="100%" height="250" viewBox="0 0 720 250" preserveAspectRatio="none"></svg>
          <div class="chart-axis" id="axis"></div>
        </div>
      </div>
      <div class="card">
        <div class="card-head"><h3>تفکیک بر اساس نوع</h3></div>
        <div class="card-pad">
          <div class="flex items-center gap-16">
            <svg width="128" height="128" viewBox="0 0 128 128" id="donut"></svg>
            <div class="legend" id="donutLegend"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mt-16">
      <div class="card-head">
        <div><h3>جزئیات گزارش</h3><div class="ch-sub" id="tblSub">به تفکیک دوره</div></div>
        <div class="head-actions"><button class="btn btn-sm" type="button"><span class="bic" data-ic="download"></span> خروجی این جدول</button></div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead id="tblHead"></thead>
          <tbody id="tblBody"></tbody>
        </table>
      </div>
    </div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.rep-tabs{display:flex;gap:4px;background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:5px;box-shadow:var(--shadow-sm);flex-wrap:wrap}
.rep-tabs button{display:flex;align-items:center;gap:7px;border:none;background:none;font-family:inherit;font-size:13px;font-weight:600;color:var(--text-2);padding:8px 14px;border-radius:9px;transition:all .12s;cursor:pointer}
.rep-tabs button span[data-ic]{display:inline-grid;width:16px;height:16px}.rep-tabs button svg{width:16px;height:16px}
.rep-tabs button.active{background:var(--accent);color:#fff}
.filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.fselect.filt{width:auto;min-width:140px;height:40px}
.chips{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.fchip{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;background:var(--accent-soft);color:var(--accent-strong);padding:4px 6px 4px 11px;border-radius:20px}
.fchip button{border:none;background:none;color:inherit;font-size:11px;cursor:pointer;opacity:.7}
.kpi-ic[data-ic] svg{width:21px;height:21px}
.chart-axis{display:flex;justify-content:space-between;margin-top:8px;color:var(--muted);font-size:11px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  var RJ=@json($reportsJs);
  var state={
    range:(RJ.filters&&RJ.filters.range)||'month',
    city:(RJ.filters&&RJ.filters.city)||'',
    connector:(RJ.filters&&RJ.filters.connector)||'',
    date_from:(RJ.filters&&RJ.filters.date_from)||'',
    date_to:(RJ.filters&&RJ.filters.date_to)||'',
  };

  var heads={
    energy:['دوره','جلسات','انرژی (kWh)','میانگین هر جلسه','اوج توان'],
    revenue:['دوره','تراکنش','درآمد ناخالص','کارمزد','درآمد خالص'],
    sessions:['دوره','جلسات','موفق','ناموفق','میانگین مدت'],
    users:['دوره','کاربر جدید','فعال','جلسات','میانگین موجودی'],
    stations:['ایستگاه','جلسات','انرژی','بهره‌برداری','درآمد']
  };
  var titles={energy:'روند انرژی تحویلی',revenue:'روند درآمد',sessions:'روند جلسات شارژ',users:'رشد و فعالیت کاربران',stations:'عملکرد ایستگاه‌ها'};
  var colors={energy:'var(--accent)',revenue:'var(--green)',sessions:'var(--violet)',users:'var(--accent)',stations:'var(--orange)'};
  var tblSubs={energy:'به تفکیک دوره · انرژی',revenue:'به تفکیک دوره · درآمد',sessions:'به تفکیک دوره · جلسات',users:'به تفکیک دوره · کاربران',stations:'به تفکیک ایستگاه'};

  var cur='energy', chartMode='bar', searchInput=document.getElementById('reportSearch');

  function pack(key,data){
    var s=(data&&data[key])||{};
    return {title:titles[key],color:colors[key],kpis:s.kpis||[],donut:s.donut||[],vals:s.vals||[],head:heads[key],rows:s.rows||[]};
  }

  function buildData(root){
    return {
      energy:pack('energy',root),revenue:pack('revenue',root),sessions:pack('sessions',root),
      users:pack('users',root),stations:pack('stations',root),
      chartPoints:root.chartPoints||30,chartSubtitle:root.chartSubtitle||''
    };
  }

  var DATA=buildData(RJ);

  function axisLabels(n){
    if(n<=7)return ['۱','۲','۳','۴','۵','۶','۷'];
    if(n<=13)return ['۱','۳','۵','۷','۹','۱۱','۱۳'];
    if(n<=12)return ['۱','۲','۳','۴','۵','۶','۷','۸','۹','۱۰','۱۱','۱۲'];
    return ['۱','۵','۱۰','۱۵','۲۰','۲۵','۳۰'];
  }

  function renderKPIs(d){
    document.getElementById('kpis').innerHTML=d.kpis.map(function(k){
      var foot=k[4]?'<span class="muted">'+k[4]+'</span>':'';
      return '<div class="kpi"><div class="kpi-ic '+k[2]+'" data-ic="'+k[3]+'"></div><div class="kpi-label">'+k[0]+'</div><div class="kpi-val tnum" style="font-size:21px">'+k[1]+'</div><div class="kpi-foot">'+foot+'</div></div>';
    }).join('');
    document.querySelectorAll('#kpis [data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  }

  function renderChart(d){
    var vals=d.vals&&d.vals.length?d.vals:[0];
    var n=vals.length;
    var W=720,H=250,pad=12,max=Math.max.apply(null,vals.concat([1]));
    var svg=document.getElementById('mainChart');
    var grid='';for(var g=0;g<=4;g++){var y=pad+(H-pad*2)*g/4;grid+='<line x1="'+pad+'" y1="'+y+'" x2="'+(W-pad)+'" y2="'+y+'" stroke="var(--border)" stroke-width="1" stroke-dasharray="3 5"/>';}
    if(chartMode==='bar'){
      var slot=(W-pad*2)/n;
      var bw=Math.max(2,slot-4);
      var bars=vals.map(function(v,i){var x=pad+i*slot+(slot-bw)/2;var h=(H-pad*2)*v/max;var y=H-pad-h;return '<rect x="'+x+'" y="'+y+'" width="'+bw+'" height="'+h+'" rx="3" fill="'+d.color+'" opacity="'+(i===n-1?1:.82)+'"/>';}).join('');
      svg.innerHTML=grid+bars;
    }else{
      var step=n>1?(W-pad*2)/(n-1):0;
      function p(area){var dd=vals.map(function(v,i){var x=pad+i*step;var y=pad+(H-pad*2)*(1-v/max);return (i?'L':'M')+x.toFixed(1)+' '+y.toFixed(1);}).join(' ');if(area&&n>1)dd+=' L'+(W-pad)+' '+(H-pad)+' L'+pad+' '+(H-pad)+' Z';return dd;}
      svg.innerHTML=grid+'<path d="'+p(true)+'" fill="'+d.color+'" opacity=".12"/><path d="'+p()+'" fill="none" stroke="'+d.color+'" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>';
    }
    var ax=document.getElementById('axis');ax.innerHTML='';
    axisLabels(n).forEach(function(t){var s=document.createElement('span');s.textContent=t;ax.appendChild(s);});
    document.getElementById('chartTitle').textContent=d.title;
    document.getElementById('chartSub').textContent=DATA.chartSubtitle||'';
  }

  function renderDonut(d){
    var R=52,off=0,circ=2*Math.PI*R;
    var rings=(d.donut||[]).map(function(x){var len=(x[1]||0)/100*circ;var el='<circle cx="64" cy="64" r="52" fill="none" stroke="'+x[2]+'" stroke-width="16" stroke-dasharray="'+len+' '+(circ-len)+'" stroke-dashoffset="'+(-off)+'" transform="rotate(-90 64 64)"/>';off+=len;return el;}).join('');
    document.getElementById('donut').innerHTML=rings||'<circle cx="64" cy="64" r="52" fill="none" stroke="var(--border)" stroke-width="16"/>';
    document.getElementById('donutLegend').innerHTML=(d.donut||[]).map(function(x){return '<div class="li"><span class="sw" style="background:'+x[2]+'"></span><span>'+x[0]+'</span><b class="tnum" style="margin-inline-start:auto">'+Number(x[1]).toLocaleString('fa-IR')+'٪</b></div>';}).join('');
  }

  function applyTableSearch(){
    if(!window.AdminUI||!searchInput)return;
    var q=window.AdminUI.normalize(searchInput.value);
    document.querySelectorAll('#tblBody tr').forEach(function(tr){
      var text=window.AdminUI.normalize(tr.getAttribute('data-search')||tr.textContent);
      tr.style.display=!q||text.indexOf(q)!==-1?'':'none';
    });
  }

  function renderTable(d){
    document.getElementById('tblHead').innerHTML='<tr>'+d.head.map(function(h){return '<th>'+h+'</th>';}).join('')+'</tr>';
    document.getElementById('tblBody').innerHTML=d.rows.map(function(r){
      return '<tr data-search="'+r.join(' ')+'">'+r.map(function(c,i){
        var cls=i===0?'cell-strong':(cur==='stations'&&i===0?'cell-strong':'tnum');
        return '<td class="'+cls+'">'+c+'</td>';
      }).join('')+'</tr>';
    }).join('');
    document.getElementById('tblSub').textContent=tblSubs[cur]||'به تفکیک دوره';
    applyTableSearch();
  }

  function renderAll(){
    var d=DATA[cur];
    renderKPIs(d);renderChart(d);renderDonut(d);renderTable(d);
  }

  function updateChips(root){
    var chips=document.getElementById('chips');
    var f=root.filters||{};
    var html='<span class="fchip" data-chip="range">'+(f.range_label||'ماه اخیر')+' <button type="button">✕</button></span>';
    html+='<span class="fchip" data-chip="connector">'+(f.connector_label||'همه کانکتورها')+' <button type="button">✕</button></span>';
    if(f.city)html+='<span class="fchip" data-chip="city">'+f.city+' <button type="button">✕</button></span>';
    html+='<span class="muted fs12" style="margin-inline-start:auto" id="recordCount">'+(root.recordCount||'—')+' رکورد · '+(root.chartSubtitle||'')+'</span>';
    chips.innerHTML=html;
    bindChipRemovers();
  }

  function bindChipRemovers(){
    document.querySelectorAll('.fchip button').forEach(function(b){
      b.addEventListener('click',function(){
        var chip=b.closest('.fchip');
        var key=chip.getAttribute('data-chip');
        if(key==='range'){state.range='month';state.date_from='';state.date_to='';}
        else if(key==='city')state.city='';
        else if(key==='connector')state.connector='';
        reloadData();
      });
    });
  }

  function queryString(){
    var p=new URLSearchParams();
    if(state.range)p.set('range',state.range);
    if(state.city)p.set('city',state.city);
    if(state.connector)p.set('connector',state.connector);
    if(state.range==='custom'&&state.date_from)p.set('date_from',state.date_from);
    if(state.range==='custom'&&state.date_to)p.set('date_to',state.date_to);
    return p.toString();
  }

  function reloadData(){
    if(!window.AdminUI)return;
    var qs=queryString();
    window.AdminUI.fetchJson=window.AdminUI.fetchJson||function(url){
      return fetch(url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();});
    };
    fetch((window.AdminUI.apiBase()+'/api/reports/data'+(qs?'?'+qs:'')),{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json();})
      .then(function(data){
        RJ=data;DATA=buildData(data);
        document.getElementById('recordCount')&&(document.getElementById('recordCount').textContent=(data.recordCount||'—')+' رکورد · '+(data.chartSubtitle||''));
        updateChips(data);
        renderAll();
      })
      .catch(function(){window.location.search=qs?'?'+qs:'';});
  }

  renderAll();
  bindChipRemovers();

  document.querySelectorAll('#repTabs button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#repTabs button').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
      cur=b.getAttribute('data-r');
      renderAll();
    });
  });

  document.querySelectorAll('#chartType button').forEach(function(b,i){
    b.addEventListener('click',function(){
      document.querySelectorAll('#chartType button').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
      chartMode=i===0?'bar':'line';
      renderChart(DATA[cur]);
    });
  });

  document.querySelectorAll('#rangeSeg button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#rangeSeg button').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
      state.range=b.getAttribute('data-range')||'month';
      document.getElementById('customRange').style.display=state.range==='custom'?'flex':'none';
      if(state.range!=='custom')reloadData();
    });
  });

  document.getElementById('cityFilter').addEventListener('change',function(){state.city=this.value;reloadData();});
  document.getElementById('connectorFilter').addEventListener('change',function(){state.connector=this.value;reloadData();});
  document.getElementById('applyCustom').addEventListener('click',function(){
    state.date_from=document.getElementById('dateFrom').value;
    state.date_to=document.getElementById('dateTo').value;
    if(!state.date_from||!state.date_to){alert('تاریخ شروع و پایان را انتخاب کنید.');return;}
    reloadData();
  });

  if(searchInput){
    var onSearch=window.AdminUI?window.AdminUI.debounce(applyTableSearch,200):applyTableSearch;
    searchInput.addEventListener('input',onSearch);
  }
})();
</script>
@endpush
