@extends('admin.layouts.app', ['page' => 'wallet', 'title' => 'کیف پول و پرداخت کاربران', 'crumb' => 'مالی و پرداخت'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="flex items-center gap-12">
        <div class="seg" id="rangeSeg"><button class="active" data-range="today">امروز</button><button data-range="week">هفته</button><button data-range="month">ماه</button></div>
        <span class="muted fs12">مدیریت کیف پول و تراکنش‌های همهٔ کاربران سامانه</span>
      </div>
      <div class="flex gap-8">
        <button class="btn btn-sm" type="button"><span class="bic" data-ic="download"></span> خروجی</button>
        <button class="btn btn-sm btn-primary" type="button" id="topupBtn"><span class="bic" data-ic="plus"></span> شارژ دستی کیف پول</button>
      </div>
    </div>

    <!-- KPI سراسری -->
    <div class="kpi-grid">
      <div class="kpi">
        <div class="kpi-ic ic-blue" data-ic="wallet"></div>
        <div class="kpi-label">مجموع موجودی کیف پول کاربران</div>
        <div class="kpi-val tnum" id="kpiTotalBal">{{ Persian::digits($kpis['total_balance_billion']) }} <small>میلیارد تومان</small></div>
        <div class="kpi-foot"><span class="muted">بدهی سامانه به <span id="kpiWalletCount">{{ Persian::digits(number_format($kpis['wallet_count'])) }}</span> کاربر</span></div>
      </div>
      <div class="kpi">
        <div class="kpi-ic ic-green" data-ic="plus"></div>
        <div class="kpi-label">شارژ کیف پول <span id="kpiRangeLabel">(امروز)</span></div>
        <div class="kpi-val tnum"><span id="kpiTopup">{{ Persian::digits($kpis['today_topup_million']) }}</span> <small>میلیون</small></div>
        <div class="kpi-foot"><span id="kpiTrend" class="{{ ($kpis['topup_trend'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}" @if(($kpis['topup_trend'] ?? 0) == 0) style="display:none" @endif>{{ ($kpis['topup_trend'] ?? 0) >= 0 ? '▲' : '▼' }} <span id="kpiTrendVal">{{ Persian::digits(abs($kpis['topup_trend'] ?? 0)) }}</span>٪</span><span class="muted"><span id="kpiTopupCount">{{ Persian::digits(number_format($kpis['today_topup_count'])) }}</span> تراکنش</span></div>
      </div>
      <div class="kpi">
        <div class="kpi-ic ic-orange" data-ic="bolt"></div>
        <div class="kpi-label">مصرف از کیف پول <span id="kpiSpendLabel">(امروز)</span></div>
        <div class="kpi-val tnum"><span id="kpiSpend">{{ Persian::digits($kpis['today_spend_million']) }}</span> <small>میلیون</small></div>
        <div class="kpi-foot"><span class="muted">هزینهٔ شارژ خودرو</span></div>
      </div>
      <div class="kpi">
        <div class="kpi-ic ic-violet" data-ic="refresh"></div>
        <div class="kpi-label">استرداد در انتظار</div>
        <div class="kpi-val tnum" id="kpiPendingRefunds">{{ Persian::digits($kpis['pending_refunds']) }} <small>درخواست</small></div>
        <div class="kpi-foot"><span class="muted"><span id="kpiPendingAmount">{{ Persian::digits($kpis['pending_refund_million']) }}</span> میلیون تومان</span></div>
      </div>
    </div>

    <div class="grid col-2-1 mt-16">
      <div class="card">
        <div class="card-head">
          <div><h3>جریان کیف پول</h3><div class="ch-sub">شارژ در برابر مصرف · ۱۴ روز</div></div>
          <div class="head-actions">
            <span class="legend-inline"><i style="background:var(--green)"></i>شارژ کیف پول</span>
            <span class="legend-inline"><i style="background:var(--orange)"></i>مصرف</span>
          </div>
        </div>
        <div class="card-pad">
          <svg id="flowChart" width="100%" height="230" viewBox="0 0 720 230" preserveAspectRatio="none"></svg>
          <div class="chart-axis" id="flowAxis"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h3>درگاه‌های پرداخت</h3></div>
        <div class="card-pad" style="padding-top:8px">
          @forelse($paymentGateways ?? [] as $gw)
            <div class="lrow" @if($loop->last) style="padding-bottom:0" @endif>
              <div class="l-ic {{ $gw['color'] }}" data-ic="{{ $gw['icon'] }}"></div>
              <div class="l-main"><div class="l-title">{{ $gw['label'] }}</div><div class="l-sub">{{ $gw['sub'] }}</div></div>
              <span class="pill {{ $gw['status'] }}">@if($gw['status'] === 'pill-green')<span class="pdot"></span>@endif{{ $gw['statusLabel'] }}</span>
            </div>
          @empty
            <div class="muted">تراکنش پرداختی در ۳۰ روز اخیر ثبت نشده است.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div class="card mt-16">
      <div class="card-head">
        <div><h3>کیف پول کاربران</h3><div class="ch-sub">موجودی و وضعیت کیف پول هر کاربر</div></div>
        <div class="head-actions">
          <div class="search" style="max-width:240px"><input id="walletSearch" data-search-for="walletBody" placeholder="جستجوی کاربر یا موبایل…" autocomplete="off">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          </div>
          <div class="seg" id="typeSeg"><button class="active">همه</button><button>عادی</button><button>سازمانی</button></div>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>کاربر</th><th>موبایل</th><th>نوع</th><th>موجودی</th><th>اعتبار پاداش</th><th>آخرین تراکنش</th><th>وضعیت</th><th></th></tr></thead>
          <tbody id="walletBody"></tbody>
        </table>
      </div>
    </div>

    <div class="card mt-16">
      <div class="card-head"><div><h3>درخواست‌های استرداد وجه</h3></div><div class="head-actions"><span class="pill pill-amber" id="refundPendingPill"><span class="pdot"></span>{{ Persian::digits($kpis['pending_refunds']) }} در انتظار</span></div></div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>شناسه</th><th>کاربر</th><th>مبلغ</th><th>علت</th><th>تاریخ</th><th>وضعیت</th><th></th></tr></thead>
          <tbody id="refundBody"></tbody>
        </table>
      </div>
    </div>
@endsection

@push('modals')
<div class="modal-overlay" id="topupModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-head">
      <h3>شارژ دستی کیف پول</h3>
      <button type="button" class="icon-btn" id="closeTopup" style="width:34px;height:34px"><span data-ic="x"></span></button>
    </div>
    <div class="modal-body">
      <div class="ffield" style="margin-bottom:16px"><label>کاربر مقصد <span class="req">*</span></label><input type="hidden" id="topupUserId"><input class="finput" id="topupUserSearch" placeholder="جستجوی نام یا شماره موبایل…" autocomplete="off"></div>
      <div class="ffield" style="margin-bottom:10px"><label>مبلغ (تومان) <span class="req">*</span></label><input class="finput tnum" dir="ltr" style="text-align:right" id="topupAmt" value="۵۰۰٬۰۰۰"></div>
      <div class="flex gap-8" style="margin-bottom:16px">
        <button type="button" class="chip-amt" data-v="۱۰۰٬۰۰۰">۱۰۰ هزار</button>
        <button type="button" class="chip-amt" data-v="۵۰۰٬۰۰۰">۵۰۰ هزار</button>
        <button type="button" class="chip-amt" data-v="۱٬۰۰۰٬۰۰۰">۱ میلیون</button>
      </div>
      <div class="form-grid">
        <div class="ffield"><label>نوع اعتبار</label><select class="fselect" id="topupCreditType"><option value="normal">موجودی عادی</option><option value="bonus">اعتبار پاداش</option><option value="compensation">اعتبار جبرانی</option></select></div>
        <div class="ffield"><label>بابت</label><select class="fselect" id="topupReason"><option>شارژ دستی پشتیبانی</option><option>جبران خطای تراکنش</option><option>پاداش وفاداری</option></select></div>
        <div class="ffield col-span"><label>توضیح (اختیاری)</label><input class="finput" id="topupNote" placeholder="یادداشت برای سوابق"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" id="cancelTopup">انصراف</button>
      <button type="button" class="btn btn-primary" id="topupSubmit"><span class="bic" data-ic="plus"></span> ثبت و شارژ</button>
    </div>
  </div>
</div>
@endpush

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.kpi-ic[data-ic] svg{width:21px;height:21px}
.legend-inline{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text-2);font-weight:600}.legend-inline i{width:10px;height:10px;border-radius:3px}
.chart-axis{display:flex;justify-content:space-between;margin-top:8px;color:var(--muted);font-size:11px}
.l-ic[data-ic] svg{width:18px;height:18px}
.chip-amt{border:1px solid var(--border);background:var(--surface-2);border-radius:20px;padding:6px 14px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.chip-amt.on{background:var(--accent-soft);border-color:var(--accent);color:var(--accent-strong)}
</style>
@endpush

@push('scripts')
<script>
window.__WALLET = {!! json_encode($walletJs, JSON_UNESCAPED_UNICODE) !!};
(function(){
  var DB=window.__WALLET||{};
  var UI=window.AdminUI;
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  var tm=document.getElementById('topupModal');
  function openTopup(user){
    if(user){
      document.getElementById('topupUserId').value=user.uid||'';
      document.getElementById('topupUserSearch').value=user.n||'';
    }else{
      document.getElementById('topupUserId').value='';
      document.getElementById('topupUserSearch').value='';
    }
    tm.classList.add('open');
  }
  function closeTopup(){tm.classList.remove('open');}

  document.getElementById('topupBtn').addEventListener('click',function(){openTopup(null);});
  document.getElementById('closeTopup').addEventListener('click',closeTopup);
  document.getElementById('cancelTopup').addEventListener('click',closeTopup);
  tm.addEventListener('click',function(e){if(e.target===tm)closeTopup();});
  document.querySelectorAll('.chip-amt').forEach(function(c){
    c.addEventListener('click',function(){
      document.getElementById('topupAmt').value=c.getAttribute('data-v');
      document.querySelectorAll('.chip-amt').forEach(function(x){x.classList.remove('on');});
      c.classList.add('on');
    });
  });

  function drawChart(topup,spend){
    topup=topup&&topup.length?topup:[0,0,0,0,0,0,0,0,0,0,0,0,0,0];
    spend=spend&&spend.length?spend:[0,0,0,0,0,0,0,0,0,0,0,0,0,0];
    var maxVal=Math.max.apply(null,topup.concat(spend).concat([1]));
    var W=720,H=230,pad=12,max=maxVal*1.2;
    function path(vals,area){var step=(W-pad*2)/(vals.length-1);var d=vals.map(function(v,i){var x=pad+i*step;var y=pad+(H-pad*2)*(1-v/max);return (i?'L':'M')+x.toFixed(1)+' '+y.toFixed(1);}).join(' ');if(area)d+=' L'+(W-pad)+' '+(H-pad)+' L'+pad+' '+(H-pad)+' Z';return d;}
    var grid='';for(var g=0;g<=4;g++){var y=pad+(H-pad*2)*g/4;grid+='<line x1="'+pad+'" y1="'+y+'" x2="'+(W-pad)+'" y2="'+y+'" stroke="var(--border)" stroke-width="1" stroke-dasharray="3 5"/>';}
    document.getElementById('flowChart').innerHTML=
      '<defs><linearGradient id="gF" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--green)" stop-opacity=".24"/><stop offset="1" stop-color="var(--green)" stop-opacity="0"/></linearGradient></defs>'+
      grid+'<path d="'+path(topup,true)+'" fill="url(#gF)"/>'+
      '<path d="'+path(topup)+'" fill="none" stroke="var(--green)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'+
      '<path d="'+path(spend)+'" fill="none" stroke="var(--orange)" stroke-width="2.2" stroke-dasharray="2 4" stroke-linecap="round" stroke-linejoin="round"/>';
    var ax=document.getElementById('flowAxis');ax.innerHTML='';
    ['۱','۳','۵','۷','۹','۱۱','۱۴'].forEach(function(t){var s=document.createElement('span');s.textContent=t;ax.appendChild(s);});
  }
  drawChart(DB.topup,DB.spend);

  function initials(name){
    return (name||'').split(' ').filter(Boolean).map(function(w){return w.charAt(0);}).slice(0,2).join('.');
  }

  var users=(DB.wallets&&DB.wallets.length)?DB.wallets:[];
  function renderWallets(list){
    document.getElementById('walletBody').innerHTML=list.map(function(u){
      return '<tr data-type="'+u.ty[2]+'" data-uid="'+(u.uid||'')+'" data-search="'+u.n+' '+u.m+'">'+
        '<td><div class="cell-user"><span class="uavatar" style="background:'+u.c+'">'+initials(u.n)+'</span><span class="cell-strong">'+u.n+'</span></div></td>'+
        '<td class="mono cell-muted">'+u.m+'</td>'+
        '<td><span class="'+(u.ty[1]==='tag'?'tag':'pill '+u.ty[1])+'">'+u.ty[0]+'</span></td>'+
        '<td class="tnum cell-strong">'+u.bal+'</td>'+
        '<td class="tnum cell-muted">'+u.rw+'</td>'+
        '<td class="cell-muted">'+u.last+'</td>'+
        '<td><span class="pill '+u.st[1]+'">'+(u.st[1]==='pill-green'?'<span class="pdot"></span>':'')+u.st[0]+'</span></td>'+
        '<td><button type="button" class="btn btn-sm wallet-topup" data-uid="'+(u.uid||'')+'">شارژ / تراکنش</button></td></tr>';
    }).join('');
  }
  renderWallets(users);

  function renderRefunds(list){
    document.getElementById('refundBody').innerHTML=(list||[]).map(function(r){
      var actions=r.can_action
        ? '<div class="flex gap-8"><button type="button" class="btn btn-sm btn-primary refund-approve" data-tid="'+r.tid+'">تأیید</button><button type="button" class="btn btn-sm refund-reject" data-tid="'+r.tid+'">رد</button></div>'
        : '<span class="muted fs12">—</span>';
      return '<tr data-tid="'+r.tid+'"><td class="mono">'+r.id+'</td><td class="cell-strong">'+r.user+'</td><td class="tnum cell-strong">'+r.amount+'</td>'+
        '<td class="cell-muted">'+r.reason+'</td><td class="cell-muted">'+r.when+'</td><td><span class="pill '+r.st[1]+'">'+r.st[0]+'</span></td>'+
        '<td>'+actions+'</td></tr>';
    }).join('');
  }
  renderRefunds(DB.refunds||[]);

  if(UI){
    UI.bindTableSearch('#walletSearch','#walletBody');
    UI.bindSegRows('typeSeg','#walletBody',function(tr,idx){
      var t=tr.getAttribute('data-type');
      return idx===0||(idx===1&&t==='normal')||(idx===2&&t==='org');
    });
    UI.bindUserAutocomplete(document.getElementById('topupUserSearch'),{hiddenInput:'#topupUserId'});
  }

  document.getElementById('walletBody').addEventListener('click',function(e){
    var btn=e.target.closest('.wallet-topup');if(!btn)return;
    var uid=+btn.getAttribute('data-uid');if(!uid)return;
    var u=users.filter(function(x){return x.uid===uid;})[0];
    if(u)openTopup(u);
  });

  document.getElementById('topupSubmit').addEventListener('click',function(){
    if(!UI){alert('خطا در بارگذاری رابط ادمین');return;}
    var uid=document.getElementById('topupUserId').value;
    var amount=+UI.parseDigits(document.getElementById('topupAmt').value)||0;
    if(!uid){alert('لطفاً کاربر را از لیست انتخاب کنید.');return;}
    if(amount<1000){alert('حداقل مبلغ ۱٬۰۰۰ تومان است.');return;}
    var btn=document.getElementById('topupSubmit');
    btn.disabled=true;
    UI.postJson(UI.apiBase()+'/api/wallet/topup',{
      user_id:+uid,amount:amount,
      credit_type:document.getElementById('topupCreditType').value,
      reason:document.getElementById('topupReason').value,
      note:document.getElementById('topupNote').value
    }).then(function(res){
      alert(res.message||'انجام شد');
      closeTopup();
      location.reload();
    }).catch(function(err){
      alert((err&&err.message)||'خطا در شارژ کیف پول');
    }).finally(function(){btn.disabled=false;});
  });

  document.getElementById('refundBody').addEventListener('click',function(e){
    if(!UI)return;
    var approve=e.target.closest('.refund-approve');
    var reject=e.target.closest('.refund-reject');
    var tid=approve?approve.getAttribute('data-tid'):(reject?reject.getAttribute('data-tid'):null);
    if(!tid)return;
    var path=approve?'/api/wallet/refunds/'+tid+'/approve':'/api/wallet/refunds/'+tid+'/reject';
    var msg=approve?'استرداد این درخواست تأیید و به کیف پول واریز شود؟':'درخواست استرداد رد شود؟';
    if(!confirm(msg))return;
    UI.postJson(UI.apiBase()+path,{}).then(function(res){
      alert(res.message||'انجام شد');
      location.reload();
    }).catch(function(err){alert((err&&err.message)||'خطا');});
  });

  var rangeLabels={today:'(امروز)',week:'(هفته)',month:'(ماه)'};
  var ranges=DB.ranges||{};
  document.querySelectorAll('#rangeSeg button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#rangeSeg button').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
      var key=b.getAttribute('data-range')||'today';
      var r=ranges[key]||{};
      document.getElementById('kpiRangeLabel').textContent=rangeLabels[key]||'';
      document.getElementById('kpiSpendLabel').textContent=rangeLabels[key]||'';
      document.getElementById('kpiTopup').textContent=r.topup_million!=null?r.topup_million:'۰';
      document.getElementById('kpiTopupCount').textContent=r.topup_count!=null?Number(r.topup_count).toLocaleString('fa-IR'):'۰';
      document.getElementById('kpiSpend').textContent=r.spend_million!=null?r.spend_million:'۰';
      var trend=document.getElementById('kpiTrend');
      var tv=document.getElementById('kpiTrendVal');
      if(r.topup_trend!=null&&r.topup_trend!==0){
        trend.style.display='';
        trend.className=r.topup_trend>=0?'trend-up':'trend-down';
        trend.innerHTML=(r.topup_trend>=0?'▲ ':'▼ ')+'<span id="kpiTrendVal">'+Math.abs(r.topup_trend)+'</span>٪';
      }else{
        trend.style.display='none';
      }
    });
  });
})();
</script>
@endpush
