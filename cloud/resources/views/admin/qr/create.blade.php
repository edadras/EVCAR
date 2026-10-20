@php use App\Services\Charging\ConnectorAvailabilityService; $availability = app(ConnectorAvailabilityService::class); @endphp
@extends('admin.layouts.app', ['page' => 'tariffs', 'title' => 'ایجاد QR کانکتور', 'crumb' => 'تعرفه‌ها و QR'])

@section('content')
<div class="flex items-center gap-12 mt-8" style="margin-bottom:18px">
  <a href="{{ route('admin.tariffs.index', ['tab' => 'qr']) }}" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به کدهای QR</a>
</div>

<div class="grid col-2-1">
  <div class="card">
    <div class="card-head"><div><h3>صدور QR کانکتور</h3><div class="ch-sub">یک کد یکتا برای هر کانکتور · قابل چاپ روی ایستگاه</div></div></div>
    <div class="card-pad">
      <form method="POST" action="{{ route('admin.qr.store') }}">@csrf
        <div class="form-grid">
          <div class="ffield col-span"><label>ایستگاه <span class="req">*</span></label>
            <select class="fselect" name="station_id" required id="station_id">
              <option value="">— انتخاب ایستگاه —</option>
              @foreach($stations as $s)
                <option value="{{ $s->id }}" @selected(old('station_id') == $s->id)>{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="ffield col-span"><label>شارژر (دستگاه) <span class="req">*</span></label>
            <select class="fselect" name="charger_id" required id="charger_id">
              <option value="">— انتخاب شارژر —</option>
              @foreach($stations as $s)
                @foreach($s->chargers as $c)
                  <option value="{{ $c->id }}" data-station="{{ $s->id }}" @selected(old('charger_id') == $c->id)>{{ $c->charge_point_id }}</option>
                @endforeach
              @endforeach
            </select>
          </div>
          <div class="ffield col-span"><label>کانکتور <span class="req">*</span></label>
            <select class="fselect" name="connector_id" required id="connector_id">
              <option value="">— انتخاب کانکتور —</option>
              @foreach($stations as $s)
                @foreach($s->chargers as $c)
                  @foreach($c->connectors as $cn)
                    @php $qrBlock = $availability->qrIssueBlockReason($cn); @endphp
                    <option value="{{ $cn->id }}" data-station="{{ $s->id }}" data-charger="{{ $c->id }}" @selected(old('connector_id') == $cn->id) @disabled($qrBlock)>
                      {{ $c->charge_point_id }} · کانکتور {{ $cn->connector_number }}@if($qrBlock) — {{ $qrBlock }}@endif
                    </option>
                  @endforeach
                @endforeach
              @endforeach
            </select>
          </div>
        </div>
        <label class="toggle-row mt-16">
          <div><div class="fw7 fs13">قابل استفاده در حالت آفلاین</div><div class="fhint">امکان شروع شارژ بدون اتصال به سرور مرکزی</div></div>
          <span class="switch {{ old('offline_capable', '1') ? 'on' : '' }}" data-sw><i></i></span>
          <input type="hidden" name="offline_capable" value="{{ old('offline_capable', '1') }}" id="offlineCapable">
        </label>
        <div class="flex gap-8 mt-16">
          <button type="submit" class="btn btn-primary" style="flex:1;height:48px"><span class="bic" data-ic="plus"></span> ایجاد QR</button>
        </div>
      </form>
    </div>
  </div>
  <div class="card" style="align-self:start">
    <div class="card-head"><h3>پیش‌نمایش</h3></div>
    <div class="card-pad" style="text-align:center">
      <div class="qr-box" id="qrPreview" style="margin:0 auto"></div>
      <div class="mono mt-16" id="qrPreviewLink" style="font-size:12px;color:var(--text-2)">—</div>
      <div class="muted fs12 mt-8" id="qrPreviewMeta">ایستگاه و کانکتور را انتخاب کنید</div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer}
.switch{width:44px;height:25px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .16s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}
.qr-box{width:200px;height:200px;border-radius:16px;padding:14px;background:#fff;box-shadow:var(--shadow-md);display:grid;place-items:center}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  function renderQrSvg(seed,box){
    if(!box)return;
    var n=21,cell=172/n,svg='<svg width="172" height="172" viewBox="0 0 172 172">';
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
        '<rect x="'+((ox+2)*cell)+'" y="'+((oy+2)*cell)+'" width="'+(3*cell)+'" height="'+(3*cell)+'" fill="var(--accent)"/>';
    }
    svg+=finder(0,0)+finder(n-7,0)+finder(0,n-7)+'</svg>';
    box.innerHTML=svg;
  }

  var stationSel=document.getElementById('station_id');
  var chargerSel=document.getElementById('charger_id');
  var connectorSel=document.getElementById('connector_id');
  var previewLink=document.getElementById('qrPreviewLink');
  var previewMeta=document.getElementById('qrPreviewMeta');

  function filterOptions(select,matchFn){
    var first='';
    select.querySelectorAll('option').forEach(function(o){
      if(!o.value){o.style.display='';return;}
      var ok=matchFn(o);
      o.style.display=ok?'':'none';
      if(ok&&!first)first=o.value;
    });
    if(select.value&&!matchFn(select.querySelector('option[value="'+select.value+'"]')))select.value=first||'';
  }

  function updatePreview(){
    var station=stationSel.selectedOptions[0];
    var charger=chargerSel.selectedOptions[0];
    var connector=connectorSel.selectedOptions[0];
    if(!charger||!charger.value||!connector||!connector.value){
      previewLink.textContent='—';
      previewMeta.textContent='ایستگاه و کانکتور را انتخاب کنید';
      document.getElementById('qrPreview').innerHTML='';
      return;
    }
    var cpId=charger.textContent.trim();
    var num=connector.textContent.split('کانکتور').pop().trim();
    var link='charger://'+cpId+'/connector/'+num;
    previewLink.textContent=link;
    previewMeta.textContent=(station?station.textContent.trim():'')+' · کانکتور '+num;
    renderQrSvg(link,document.getElementById('qrPreview'));
  }

  if(stationSel&&chargerSel&&connectorSel){
    stationSel.addEventListener('change',function(){
      filterOptions(chargerSel,function(o){return o.getAttribute('data-station')===stationSel.value;});
      filterOptions(connectorSel,function(o){return o.getAttribute('data-station')===stationSel.value;});
      updatePreview();
    });
    chargerSel.addEventListener('change',function(){
      filterOptions(connectorSel,function(o){return o.getAttribute('data-charger')===chargerSel.value;});
      updatePreview();
    });
    connectorSel.addEventListener('change',updatePreview);
    stationSel.dispatchEvent(new Event('change'));
    if(chargerSel.value)chargerSel.dispatchEvent(new Event('change'));
    updatePreview();
  }

  document.querySelectorAll('[data-sw]').forEach(function(s){
    var hidden=document.getElementById('offlineCapable');
    s.closest('.toggle-row').addEventListener('click',function(){
      s.classList.toggle('on');
      if(hidden) hidden.value=s.classList.contains('on')?'1':'0';
    });
  });
})();
</script>
@endpush
