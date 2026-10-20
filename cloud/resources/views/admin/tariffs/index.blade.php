@extends('admin.layouts.app', ['page' => 'tariffs', 'title' => 'تعرفه‌ها و کارت‌های RFID', 'crumb' => 'مالی و پرداخت'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="tabs mt-8" id="tabs" style="margin-bottom:18px">
      <button class="active" data-tab="tariff">موتور تعرفه</button>
      <button data-tab="rfid">کارت‌های RFID</button>
      <button data-tab="qr">کدهای QR</button>
    </div>

    <!-- تعرفه -->
    <section data-pane="tariff">
      <div class="flex between items-center" style="margin-bottom:14px">
        <div><h3 style="font-size:16px">موتور تعرفه</h3><div class="muted fs12">مدیریت مدل‌های قیمت‌گذاری شبکه</div></div>
        <a href="/admin/tariffs/create" class="btn btn-sm btn-primary"><span class="bic" data-ic="plus"></span> ایجاد تعرفهٔ جدید</a>
      </div>
      <div class="grid g-3">
        @forelse($tariffs as $tariff)
          @php
            $modelLabel = match($tariff->pricing_model) {
              'per_minute' => ['زمان‌محور', 'background:var(--orange-soft);color:var(--orange)'],
              'mixed' => ['ترکیبی', 'background:color-mix(in srgb,var(--violet) 12%,white);color:var(--violet)'],
              default => ['انرژی‌محور', ''],
            };
            $priceToman = $tariff->pricing_model === 'per_minute'
              ? Persian::num((int) round($tariff->price_per_minute / 10))
              : Persian::num((int) round($tariff->price_per_kwh / 10));
            $unit = $tariff->pricing_model === 'per_minute' ? 'تومان / دقیقه' : ($tariff->pricing_model === 'mixed' ? '+ اشغال' : 'تومان / kWh');
            $statusPill = $tariff->status === 'active' ? 'pill-green' : 'pill-amber';
          @endphp
          <div class="tariff-card">
            <div class="flex between items-center">
              <span class="pill {{ $modelLabel[1] ? '' : 'pill-accent' }}" @if($modelLabel[1]) style="{{ $modelLabel[1] }}" @endif>{{ $modelLabel[0] }}</span>
              @if($tariff->is_default)<span class="pill pill-green" style="font-size:11px">پیش‌فرض</span>@endif
            </div>
            <h3 class="mt-12">{{ $tariff->name }}</h3>
            <div class="muted fs12">{{ $tariff->code }}</div>
            <div class="tprice tnum">{{ $priceToman }} <small>{{ $unit }}</small></div>
            <div class="tmeta"><span>{{ Persian::num($tariff->charging_sessions_count) }} جلسه</span><span class="pill {{ $statusPill }}">{{ $tariff->status === 'active' ? 'فعال' : ($tariff->status === 'inactive' ? 'غیرفعال' : 'پیش‌نویس') }}</span></div>
            <div class="flex gap-8 mt-12">
              <a href="{{ route('admin.tariffs.edit', $tariff) }}" class="btn btn-sm btn-primary" style="flex:1"><span class="bic" data-ic="edit"></span> ویرایش</a>
              @if(!$tariff->is_default)
              <form method="POST" action="{{ route('admin.tariffs.destroy', $tariff) }}" style="flex:1" onsubmit="return confirm('تعرفه «{{ $tariff->name }}» حذف شود؟');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm" style="width:100%;color:var(--red)">حذف</button>
              </form>
              @endif
            </div>
          </div>
        @empty
          <div class="tariff-card"><div class="muted">تعرفه‌ای ثبت نشده است.</div></div>
        @endforelse
        <a href="/admin/tariffs/create" class="tariff-add">
          <span class="ta-plus" data-ic="plus"></span>
          <span class="fw7">ایجاد تعرفهٔ جدید</span>
          <span class="fs12 muted">انرژی‌محور، زمان‌محور یا ترکیبی</span>
        </a>
      </div>

      <div class="grid col-2-1 mt-16">
        <div class="card">
          <div class="card-head"><div><h3>ساختار تعرفهٔ ساعتی</h3><div class="ch-sub">قیمت‌گذاری اوج / غیراوج مصرف</div></div></div>
          <div class="card-pad">
            <svg id="peakChart" width="100%" height="190" viewBox="0 0 680 190" preserveAspectRatio="none"></svg>
            <div class="chart-axis" id="peakAxis"></div>
            <div class="flex gap-16 mt-16">
              <span class="legend-inline"><i style="background:var(--red)"></i>اوج مصرف (۱۷–۲۲) · ۴٬۵۰۰ تومان</span>
              <span class="legend-inline"><i style="background:var(--accent)"></i>عادی · ۳٬۲۰۰ تومان</span>
              <span class="legend-inline"><i style="background:var(--green)"></i>کم‌باری (۰۰–۰۶) · ۲٬۱۰۰ تومان</span>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-head"><h3>ویرایش سریع</h3></div>
          <div class="card-pad">
            @php $quickTariff = $tariffs->firstWhere('is_default', true) ?? $tariffs->first(); @endphp
            @if($quickTariff)
              <div class="muted fs12" style="margin-bottom:12px">تعرفهٔ انتخاب‌شده: <strong>{{ $quickTariff->name }}</strong></div>
              <div class="tprice tnum" style="font-size:22px;margin:0 0 12px">{{ Persian::num((int) round(($quickTariff->pricing_model === 'per_minute' ? $quickTariff->price_per_minute : $quickTariff->price_per_kwh) / 10)) }} <small>{{ $quickTariff->pricing_model === 'per_minute' ? 'تومان/دقیقه' : 'تومان/kWh' }}</small></div>
              <a href="{{ route('admin.tariffs.edit', $quickTariff) }}" class="btn btn-primary mt-12" style="width:100%">ویرایش این تعرفه</a>
            @else
              <div class="muted">تعرفه‌ای برای ویرایش وجود ندارد.</div>
              <a href="{{ route('admin.tariffs.create') }}" class="btn btn-primary mt-12" style="width:100%">ایجاد تعرفه</a>
            @endif
          </div>
        </div>
      </div>
    </section>

    <!-- RFID -->
    <section data-pane="rfid" style="display:none">
      <div class="kpi-grid" style="margin-bottom:16px">
        <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="chip"></div><div><div class="ms-v tnum">{{ Persian::num(\App\Models\RfidCard::count()) }}</div><div class="ms-l">کارت صادرشده</div></div></div>
        <div class="mini-stat"><div class="ms-ic ic-green" data-ic="chip"></div><div><div class="ms-v tnum">{{ Persian::num($stats['active_rfid'] ?? 0) }}</div><div class="ms-l">کارت فعال</div></div></div>
        <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="users"></div><div><div class="ms-v tnum">{{ Persian::num(\App\Models\RfidCard::whereNotNull('organization_id')->count()) }}</div><div class="ms-l">کارت سازمانی</div></div></div>
        <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="alert"></div><div><div class="ms-v tnum">{{ Persian::num(\App\Models\RfidCard::whereNot('status', 'active')->count()) }}</div><div class="ms-l">کارت غیرفعال/مفقود</div></div></div>
      </div>
      <div class="card">
        <div class="card-head">
          <div><h3>مدیریت کارت‌های RFID</h3><div class="ch-sub">صدور، ویرایش و مسدودسازی کارت‌ها</div></div>
          <div class="head-actions" style="display:flex;gap:8px;align-items:center">
            <input class="finput" id="rfidSearch" placeholder="جستجو شماره کارت، دارنده…" style="width:220px;height:36px">
            <a href="{{ route('admin.rfid.create') }}" class="btn btn-sm btn-primary"><span class="bic" data-ic="plus"></span> صدور کارت جدید</a>
          </div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>شماره کارت</th><th>دارنده</th><th>نوع</th><th>کیف پول متصل</th><th>آخرین به‌روزرسانی</th><th>حالت آفلاین</th><th>وضعیت</th><th></th></tr></thead>
            <tbody id="rfidBody">
              @forelse($rfidCards as $card)
                @php
                  $holder = match($card->meta['card_type'] ?? null) {
                    'anon' => $card->card_label ?: 'مهمان',
                    'org' => $card->organization?->name ?? ($card->user?->name ?? '—'),
                    default => $card->user?->name ?? ($card->organization?->name ?? '—'),
                  };
                  $typeLabel = $card->cardTypeLabel();
                  $statusPill = match($card->status) {
                    'active' => ['pill-green', 'فعال'],
                    'blocked' => ['pill-red', 'مسدود'],
                    default => ['pill-amber', 'منقضی'],
                  };
                @endphp
                <tr>
                  <td class="mono">{{ $card->id_tag }}</td>
                  <td class="cell-strong">{{ $holder }}</td>
                  <td><span class="{{ $typeLabel === 'سازمانی' ? 'pill pill-accent' : 'tag' }}">{{ $typeLabel }}</span></td>
                  <td class="tnum">{{ $card->user?->wallet ? Persian::num((int) round($card->user->wallet->balance / 10)).' ت' : '—' }}</td>
                  <td class="cell-muted">{{ $card->updated_at ? Persian::num($card->updated_at->diffInDays(now())).' روز پیش' : '—' }}</td>
                  <td><span class="pill {{ $card->isOfflineAllowed() ? 'pill-amber' : 'pill-green' }}">{{ $card->isOfflineAllowed() ? 'آفلاین' : 'آنلاین' }}</span></td>
                  <td><span class="pill {{ $statusPill[0] }}">{{ $statusPill[1] }}</span></td>
                  <td>
                    <div class="flex gap-8" style="justify-content:flex-end">
                      <a href="{{ route('admin.rfid.edit', $card) }}" class="btn btn-sm"><span class="bic" data-ic="edit"></span></a>
                      @if($card->status === 'active')
                      <form method="POST" action="{{ route('admin.rfid.status', $card) }}" onsubmit="return confirm('این کارت مسدود شود؟');">
                        @csrf
                        <input type="hidden" name="status" value="blocked">
                        <button type="submit" class="btn btn-sm" title="مسدود کردن">مسدود</button>
                      </form>
                      @else
                      <form method="POST" action="{{ route('admin.rfid.status', $card) }}">
                        @csrf
                        <input type="hidden" name="status" value="active">
                        <button type="submit" class="btn btn-sm btn-primary" title="فعال‌سازی">فعال</button>
                      </form>
                      @endif
                      <form method="POST" action="{{ route('admin.rfid.destroy', $card) }}" onsubmit="return confirm('کارت «{{ $card->id_tag }}» حذف شود؟');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm" style="color:var(--red)" title="حذف"><span class="bic" data-ic="x"></span></button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="8" class="cell-muted center">کارتی ثبت نشده است. <a href="{{ route('admin.rfid.create') }}">صدور اولین کارت</a></td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- QR -->
    <section data-pane="qr" style="display:none">
      <div class="grid col-1-2">
        <div class="card" style="align-self:start">
          <div class="card-head"><h3>پیش‌نمایش QR</h3></div>
          <div class="card-pad" style="text-align:center">
            <div class="qr-box" id="qrBox"></div>
            <div class="mono mt-16" id="qrPreviewLink" style="font-size:12px;color:var(--text-2)">—</div>
            <div class="muted fs12 mt-8" id="qrPreviewMeta">یک ردیف از جدول را انتخاب کنید</div>
            <button type="button" class="btn btn-primary mt-16" id="qrDownloadBtn" style="width:100%" disabled><span class="bic" data-ic="download"></span> دانلود برچسب QR</button>
            <a href="{{ route('admin.qr.create') }}" class="btn mt-8" style="width:100%"><span class="bic" data-ic="plus"></span> ایجاد QR جدید</a>
          </div>
        </div>
        <div class="card">
          <div class="card-head">
            <div><h3>کدهای QR فعال</h3><div class="ch-sub">هر کانکتور یک کد یکتا · پشتیبانی از حالت آفلاین</div></div>
            <div class="head-actions" style="display:flex;gap:8px;align-items:center">
              <input class="finput" id="qrSearch" placeholder="جستجو ایستگاه، کانکتور…" style="width:220px;height:36px">
              <a href="{{ route('admin.qr.create') }}" class="btn btn-sm btn-primary"><span class="bic" data-ic="plus"></span> ایجاد QR</a>
            </div>
          </div>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr><th>کد QR</th><th>ایستگاه</th><th>کانکتور</th><th>توکن آفلاین</th><th>اسکن (۳۰ روز)</th><th>وضعیت</th><th></th></tr></thead>
              <tbody id="qrBody">
                @forelse($qrTokens as $token)
                  @php
                    $scans = (int) ($qrScanCounts[$token->connector_id] ?? 0);
                    $statusPill = $token->status === 'active' ? ['pill-green', 'فعال'] : ['pill-amber', 'غیرفعال'];
                  @endphp
                  <tr class="qr-row" data-link="{{ $token->deep_link }}" data-meta="{{ $token->station?->name }} · کانکتور {{ $token->connectorLabel() }}" style="cursor:pointer">
                    <td class="mono">{{ $token->displayCode() }}</td>
                    <td class="cell-strong">{{ $token->station?->name ?? '—' }}</td>
                    <td><span class="tag">{{ $token->connectorLabel() }}</span></td>
                    <td class="mono cell-muted">{{ $token->offlineTokenLabel() }}</td>
                    <td class="tnum">{{ Persian::num($scans) }}</td>
                    <td><span class="pill {{ $statusPill[0] }}">{{ $statusPill[1] }}</span></td>
                    <td>
                      <form method="POST" action="{{ route('admin.qr.destroy', $token) }}" onsubmit="return confirm('کد QR «{{ $token->displayCode() }}» حذف شود؟');" onclick="event.stopPropagation();">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm" style="color:var(--red)" title="حذف"><span class="bic" data-ic="x"></span></button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="cell-muted center">کد QR ثبت نشده است. <a href="{{ route('admin.qr.create') }}">ایجاد اولین QR</a></td></tr>
                @endforelse
              </tbody>
            </table>
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
.ms-v{font-size:22px;font-weight:800}.ms-l{font-size:12px;color:var(--muted)}
.legend-inline{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;color:var(--text-2);font-weight:600}.legend-inline i{width:10px;height:10px;border-radius:3px}
.chart-axis{display:flex;justify-content:space-between;margin-top:8px;color:var(--muted);font-size:11px}

.tariff-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:18px;box-shadow:var(--shadow-sm)}
.tariff-add{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;text-align:center;background:var(--surface-2);border:1.5px dashed var(--border);border-radius:var(--r-lg);padding:18px;color:var(--text-2);transition:all .15s}
.tariff-add:hover{border-color:var(--accent);background:var(--accent-softer)}
.ta-plus{width:42px;height:42px;border-radius:13px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;margin-bottom:4px}.ta-plus svg{width:21px;height:21px}
.tariff-card h3{font-size:16px}
.dotbtn{width:30px;height:30px;border-radius:8px;border:none;background:transparent;color:var(--muted);display:grid;place-items:center}.dotbtn:hover{background:var(--surface-3)}.dotbtn svg{width:18px;height:18px}
.tprice{font-size:26px;font-weight:800;margin:14px 0 4px}.tprice small{font-size:13px;font-weight:600;color:var(--text-2)}
.tmeta{display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-2);font-size:12px;color:var(--muted)}

.fld{display:block;margin-bottom:14px}
.fld>span{display:block;font-size:12px;color:var(--text-2);font-weight:600;margin-bottom:6px}
.inp{width:100%;height:40px;border:1px solid var(--border);background:var(--surface-2);border-radius:10px;padding:0 13px;font-family:inherit;font-size:13px;color:var(--text)}
.inp:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft);background:var(--surface)}
.switch-row{display:flex;align-items:center;justify-content:space-between}
.switch-row>span:first-child{margin-bottom:0}
.switch{width:42px;height:24px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;cursor:pointer;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .15s}
.switch.on{background:var(--green)}.switch.on i{transform:translateX(-18px)}
html[dir=rtl] .switch.on i{transform:translateX(-18px)}

.qr-box{width:200px;height:200px;margin:0 auto;border-radius:16px;padding:14px;background:#fff;box-shadow:var(--shadow-md);display:grid;place-items:center}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  // نمودار اوج مصرف
  (function(){
    var hrs=[2.1,2.1,2.1,2.1,2.1,2.1,3.2,3.2,3.2,3.2,3.2,3.2,3.2,3.2,3.2,3.2,3.2,4.5,4.5,4.5,4.5,4.5,3.2,2.1];
    var W=680,H=190,pad=10,max=5,bw=(W-pad*2)/24-3;
    var bars=hrs.map(function(v,i){
      var x=pad+i*((W-pad*2)/24), h=(H-pad*2)*(v/max), y=H-pad-h;
      var c=v>4?'var(--red)':(v<2.5?'var(--green)':'var(--accent)');
      return '<rect x="'+x+'" y="'+y+'" width="'+bw+'" height="'+h+'" rx="3" fill="'+c+'"/>';
    }).join('');
    document.getElementById('peakChart').innerHTML=bars;
    var ax=document.getElementById('peakAxis');['۰۰','۰۶','۱۲','۱۸','۲۴'].forEach(function(t){var s=document.createElement('span');s.textContent=t;ax.appendChild(s);});
  })();

  function renderQrSvg(seed,box){
    if(!box)return;
    var n=21,cell=172/n,svg='<svg width="172" height="172" viewBox="0 0 172 172" xmlns="http://www.w3.org/2000/svg">';
    function blk(x,y,s){return '<rect x="'+(x*cell)+'" y="'+(y*cell)+'" width="'+(s*cell)+'" height="'+(s*cell)+'" fill="#0F1E2E"/>';}
    var h=0;for(var i=0;i<seed.length;i++)h=((h<<5)-h)+seed.charCodeAt(i)|0;
    for(var y=0;y<n;y++)for(var x=0;x<n;x++){
      var corner=(x<7&&y<7)||(x>=n-7&&y<7)||(x<7&&y>=n-7);
      if(corner)continue;
      h=(h*1103515245+12345)|0;
      if((h>>8)%100<46)svg+=blk(x,y,1);
    }
    function finder(ox,oy){
      return blk(ox,oy,7)+
        '<rect x="'+((ox+1)*cell)+'" y="'+((oy+1)*cell)+'" width="'+(5*cell)+'" height="'+(5*cell)+'" fill="#fff"/>'+
        '<rect x="'+((ox+2)*cell)+'" y="'+((oy+2)*cell)+'" width="'+(3*cell)+'" height="'+(3*cell)+'" fill="#2563eb"/>';
    }
    svg+=finder(0,0)+finder(n-7,0)+finder(0,n-7)+'</svg>';
    box.innerHTML=svg;
    return box.querySelector('svg');
  }

  var qrBox=document.getElementById('qrBox');
  var qrPreviewLink=document.getElementById('qrPreviewLink');
  var qrPreviewMeta=document.getElementById('qrPreviewMeta');
  var qrDownloadBtn=document.getElementById('qrDownloadBtn');
  var selectedQrLink='';

  function selectQrRow(row){
    document.querySelectorAll('.qr-row').forEach(function(r){r.style.background='';});
    if(!row)return;
    row.style.background='var(--accent-softer)';
    selectedQrLink=row.getAttribute('data-link')||'';
    if(qrPreviewLink)qrPreviewLink.textContent=selectedQrLink||'—';
    if(qrPreviewMeta)qrPreviewMeta.textContent=row.getAttribute('data-meta')||'';
    renderQrSvg(selectedQrLink,qrBox);
    if(qrDownloadBtn)qrDownloadBtn.disabled=!selectedQrLink;
  }

  document.querySelectorAll('.qr-row').forEach(function(row){
    row.addEventListener('click',function(){selectQrRow(row);});
  });
  var firstQr=document.querySelector('.qr-row');
  if(firstQr)selectQrRow(firstQr);

  if(qrDownloadBtn){
    qrDownloadBtn.addEventListener('click',function(){
      if(!selectedQrLink)return;
      var svg=qrBox.querySelector('svg');
      if(!svg)return;
      var blob=new Blob(['<?xml version="1.0" encoding="UTF-8"?>\n'+svg.outerHTML],{type:'image/svg+xml'});
      var a=document.createElement('a');
      a.href=URL.createObjectURL(blob);
      a.download='qr-'+selectedQrLink.replace(/[^a-zA-Z0-9]/g,'-')+'.svg';
      a.click();
      URL.revokeObjectURL(a.href);
    });
  }

  function showTab(t,pushUrl){
    document.querySelectorAll('#tabs button').forEach(function(x){
      x.classList.toggle('active',x.getAttribute('data-tab')===t);
    });
    document.querySelectorAll('[data-pane]').forEach(function(p){
      p.style.display=p.getAttribute('data-pane')===t?'':'none';
    });
    if(pushUrl!==false){
      var url=new URL(window.location.href);
      if(t==='tariff') url.searchParams.delete('tab');
      else url.searchParams.set('tab',t);
      history.replaceState(null,'',url.pathname+(url.search||''));
    }
  }

  document.querySelectorAll('#tabs button').forEach(function(b){
    b.addEventListener('click',function(){showTab(b.getAttribute('data-tab'));});
  });

  var initialTab=new URLSearchParams(window.location.search).get('tab');
  if(initialTab&&document.querySelector('#tabs [data-tab="'+initialTab+'"]')) showTab(initialTab,false);

  if(window.AdminUI){
    window.AdminUI.bindTableSearch('#rfidSearch','#rfidBody');
    window.AdminUI.bindTableSearch('#qrSearch','#qrBody');
  }

  // switch toggle
  document.querySelectorAll('.switch').forEach(function(s){s.addEventListener('click',function(){s.classList.toggle('on');});});
})();
</script>
@endpush
