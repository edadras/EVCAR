@php $station = $station ?? null; @endphp
@extends('admin.layouts.app', ['page' => 'stations', 'title' => $station ? 'ویرایش ایستگاه' : 'ایجاد ایستگاه جدید', 'crumb' => $station ? $station->name : 'ایستگاه‌ها و نقشه'])

@section('content')
<form method="POST" action="{{ $station ? route('admin.stations.update', $station) : route('admin.stations.store') }}">@csrf
@if($station)@method('PUT')@endif
@php use App\Support\Persian; @endphp

<div class="flex items-center gap-12 mt-8" style="margin-bottom:18px">
      <a href="/admin/stations" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به ایستگاه‌ها</a>
    </div>

    <div class="grid col-2-1">
      <div style="display:flex;flex-direction:column;gap:16px">
        <!-- مشخصات -->
        <div class="card">
          <div class="card-head"><h3>مشخصات ایستگاه</h3></div>
          <div class="card-pad">
            <div class="form-grid">
              <div class="ffield col-span"><label>نام ایستگاه <span class="req">*</span></label><input class="finput" id="stName" name="name" value="{{ old('name', $station?->name) }}" placeholder="مثلاً ایستگاه میدان آزادی" required></div>
              @if($station)
              <div class="ffield"><label>کد ایستگاه</label><input class="finput tnum" name="code" dir="ltr" style="text-align:right" value="{{ old('code', $station->code) }}" required></div>
              @else
              <div class="ffield"><label>کد ایستگاه</label><input class="finput tnum" dir="ltr" style="text-align:right" value="خودکار پس از ثبت" disabled><span class="fhint">کد یکتا هنگام ذخیره تولید می‌شود</span></div>
              @endif
              <div class="ffield"><label>اپراتور / مالک</label><select class="fselect" name="partner_id"><option value="">SafirCharge (مستقیم)</option>@foreach($partners ?? [] as $p)<option value="{{ $p->id }}" @selected(old('partner_id', $station?->partner_id)==$p->id)>{{ $p->name }}</option>@endforeach</select></div>
              <div class="ffield col-span"><label>آدرس <span class="req">*</span></label><input class="finput" name="address" value="{{ old('address', $station?->address) }}" placeholder="استان، شهر، خیابان، پلاک"></div>
              <div class="ffield"><label>مختصات جغرافیایی</label><input class="finput tnum" id="stCoords" dir="ltr" style="text-align:right" placeholder="۳۵٫۶۹۹۲, ۵۱٫۳۳۸۹" value="{{ old('coords', $station?->latitude ? $station->latitude.','.$station->longitude : '') }}"></div>
              <input type="hidden" name="latitude" id="stLat" value="{{ old('latitude', $station?->latitude) }}">
              <input type="hidden" name="longitude" id="stLng" value="{{ old('longitude', $station?->longitude) }}">
              <input type="hidden" name="type" value="{{ old('type', $station?->type ?? 'public') }}">
              @if($station)
              <div class="ffield"><label>وضعیت</label><select class="fselect" name="status">@foreach(['active'=>'فعال','inactive'=>'غیرفعال','maintenance'=>'تعمیرات'] as $v=>$l)<option value="{{ $v }}" @selected(old('status', $station->status)===$v)>{{ $l }}</option>@endforeach</select></div>
              @endif
              <div class="ffield"><label>ساعات کاری</label><select class="fselect"><option>۲۴ ساعته</option><option>۶ صبح تا ۱۲ شب</option><option>اداری</option></select></div>
            </div>
          </div>
        </div>

        <!-- کانکتورها -->
        <div class="card">
          <div class="card-head"><div><h3>کانکتورها و شارژرها</h3><div class="ch-sub">تعداد و نوع کانکتورهای این ایستگاه</div></div>
            <div class="head-actions"><button class="btn btn-sm" id="addConn"><span class="bic" data-ic="plus"></span> افزودن کانکتور</button></div>
          </div>
          <div class="card-pad" style="padding-top:8px">
            <div id="connList" style="display:flex;flex-direction:column;gap:10px"></div>
          </div>
        </div>

        <!-- امکانات -->
        <div class="card">
          <div class="card-head"><h3>تعرفه و امکانات</h3></div>
          <div class="card-pad">
            <div class="form-grid">
              <div class="ffield"><label>تعرفهٔ پیش‌فرض</label><select class="fselect"><option>تعرفهٔ استاندارد DC — ۳٬۲۰۰ ت/kWh</option><option>تعرفهٔ AC اقتصادی — ۹۸۰ ت/دقیقه</option><option>تعرفهٔ اعضای ویژه</option></select></div>
            </div>
            @if($station)
            <div class="ffield col-span mt-12" id="anprConfig">
              <label class="flex items-center gap-8" style="cursor:pointer">
                <input type="checkbox" name="anpr_enabled" value="1" @checked(old('anpr_enabled', $anprEnabled ?? false)) style="width:18px;height:18px;accent-color:var(--accent)">
                <span>فعال‌سازی پلاک‌خوان PLPR Lite (شارژ خودکار با تشخیص پلاک)</span>
              </label>
              <div class="fs12 muted mt-8" style="line-height:1.7">موتور روی Edge اجرا می‌شود؛ رویدادها از طریق سینک به ابر می‌رسند. حداقل اطمینان: {{ config('safircharge.anpr.min_confidence', 0.92) * 100 }}٪</div>
              <div id="anprCameras" class="mt-12" style="display:flex;flex-direction:column;gap:10px">
                @foreach(old('anpr_cameras', $anprCameras ?? []) as $i => $cam)
                <div class="conn-edit anpr-cam-row">
                  <div class="ce-ic ic-violet" data-ic="camera"></div>
                  <input class="finput" style="max-width:90px" name="anpr_cameras[{{ $i }}][id]" value="{{ $cam['id'] ?? 'CAM-01' }}" placeholder="CAM-01" dir="ltr">
                  <input class="finput" style="flex:1" name="anpr_cameras[{{ $i }}][name]" value="{{ $cam['name'] ?? '' }}" placeholder="نام دوربین">
                  <input class="finput tnum" style="flex:1" name="anpr_cameras[{{ $i }}][rtsp_url]" value="{{ $cam['rtsp_url'] ?? '' }}" placeholder="rtsp://..." dir="ltr">
                  <input type="hidden" name="anpr_cameras[{{ $i }}][enabled]" value="1">
                </div>
                @endforeach
              </div>
              <button type="button" class="btn btn-sm mt-8" id="addAnprCam"><span class="bic" data-ic="plus"></span> افزودن دوربین</button>
            </div>
            @else
            <div class="fs12 muted mt-8">پس از ثبت ایستگاه و اتصال Edge، پیکربندی پلاک‌خوان از همین فرم در دسترس است.</div>
            @endif
            <div class="ffield col-span mt-8"><label>امکانات رفاهی</label>
              <div class="chips" id="amenChips">
                <button type="button" class="chip active">پارکینگ</button>
                <button type="button" class="chip active">۲۴ ساعته</button>
                <button type="button" class="chip">کافه</button>
                <button type="button" class="chip">سرویس بهداشتی</button>
                <button type="button" class="chip">WiFi رایگان</button>
                <button type="button" class="chip">نگهبانی</button>
                <button type="button" class="chip">سرپوشیده</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- پیش‌نمایش -->
      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        <div class="card">
          <div class="card-head"><h3>پیش‌نمایش</h3></div>
          <div class="card-pad">
            <div class="map-panel" style="height:150px;margin-bottom:14px" id="miniMap"></div>
            <div class="fw8" id="pvName" style="font-size:16px">ایستگاه جدید</div>
            <div class="muted fs12 mt-4" id="pvCode">{{ $station?->code ?? 'کد خودکار' }}</div>
            <hr class="sep">
            <div class="flex between" style="font-size:13px"><span class="muted">کانکتورها</span><b class="tnum" id="pvConn">۲</b></div>
            <div class="flex between mt-12" style="font-size:13px"><span class="muted">حداکثر توان</span><b class="tnum" id="pvPower">۱۸۰ kW</b></div>
            <div class="flex between mt-12" style="font-size:13px"><span class="muted">شارژ خودکار پلاک</span><span class="pill {{ ($anprEnabled ?? false) ? 'pill-green' : 'pill-amber' }}" id="pvAnpr"><span class="pdot"></span>{{ ($anprEnabled ?? false) ? 'فعال' : 'غیرفعال' }}</span></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="height:48px"><span class="bic" data-ic="plus"></span> {{ $station ? 'به‌روزرسانی ایستگاه' : 'ثبت ایستگاه' }}</button>
        <button type="button" class="btn">ذخیره پیش‌نویس</button>
      </div>
    </div>
</form>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chip{border:1px solid var(--border);background:var(--surface-2);border-radius:30px;padding:8px 14px;font-size:12.5px;font-weight:600;color:var(--text-2)}
.chip.active{background:var(--accent-soft);color:var(--accent-strong);border-color:color-mix(in srgb,var(--accent) 30%,transparent)}
.conn-edit{display:flex;align-items:center;gap:11px;border:1px solid var(--border);border-radius:13px;padding:11px 13px;background:var(--surface-2)}
.conn-edit .ce-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none}.conn-edit .ce-ic svg{width:19px;height:19px}
.conn-edit select{height:38px;border:1px solid var(--border);background:var(--surface);border-radius:9px;font-family:inherit;font-size:12.5px;color:var(--text);padding:0 10px}
.conn-edit .ce-del{width:34px;height:34px;border-radius:9px;border:1px solid var(--border);background:var(--surface);color:var(--red);display:grid;place-items:center;margin-inline-start:auto}.conn-edit .ce-del svg{width:16px;height:16px}
.map-panel{display:grid;place-items:center}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  // مینی‌مپ
  document.getElementById('miniMap').innerHTML=
    '<svg width="100%" height="100%" viewBox="0 0 100 100" preserveAspectRatio="none" style="position:absolute;inset:0">'+
    '<defs><pattern id="gg" width="7" height="7" patternUnits="userSpaceOnUse"><path d="M7 0H0V7" fill="none" stroke="var(--border)" stroke-width=".5"/></pattern></defs>'+
    '<rect width="100" height="100" fill="url(#gg)"/><path d="M0 50 L100 44" stroke="color-mix(in srgb,var(--accent) 30%,transparent)" stroke-width="1"/></svg>'+
    '<div class="pin" style="left:50%;top:52%"><div class="pin-body" style="background:var(--accent);width:32px;height:32px"><span>⚡</span></div><div class="pin-pulse" style="background:var(--accent);opacity:.3"></div></div>';

  var conns=[{t:'DC CCS2',p:'۱۸۰ kW',c:'ic-green'},{t:'AC Type2',p:'۲۲ kW',c:'ic-blue'}];
  function powerMax(){
    var max=0;conns.forEach(function(c){var n=parseInt(c.p.replace(/[^۰-۹]/g,'').replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);}));if(n>max)max=n;});
    return max;
  }
  function render(){
    document.getElementById('connList').innerHTML=conns.map(function(c,i){
      return '<div class="conn-edit"><div class="ce-ic '+c.c+'">'+window.icon('plug')+'</div>'+
        '<select><option'+(c.t==='DC CCS2'?' selected':'')+'>DC CCS2</option><option'+(c.t==='DC CHAdeMO'?' selected':'')+'>DC CHAdeMO</option><option'+(c.t==='AC Type2'?' selected':'')+'>AC Type2</option></select>'+
        '<select><option'+(c.p==='۱۸۰ kW'?' selected':'')+'>۱۸۰ kW</option><option'+(c.p==='۵۰ kW'?' selected':'')+'>۵۰ kW</option><option'+(c.p==='۲۲ kW'?' selected':'')+'>۲۲ kW</option></select>'+
        '<button class="ce-del" data-i="'+i+'">'+window.icon('x')+'</button></div>';
    }).join('');
    document.getElementById('pvConn').textContent=conns.length.toLocaleString('fa-IR');
    document.getElementById('pvPower').textContent=conns.some(function(c){return c.t.indexOf('DC')>=0;})?'۱۸۰ kW':'۲۲ kW';
    document.querySelectorAll('.ce-del').forEach(function(b){b.addEventListener('click',function(){if(conns.length>1){conns.splice(+b.getAttribute('data-i'),1);render();}});});
  }
  render();
  document.getElementById('addConn').addEventListener('click',function(){conns.push({t:'AC Type2',p:'۲۲ kW',c:'ic-blue'});render();});

  document.getElementById('stName').addEventListener('input',function(){
    document.getElementById('pvName').textContent=this.value||'ایستگاه جدید';
  });
  function asciiDigits(s){return (s||'').replace(/[۰-۹٠-٩]/g,function(d){var p='۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'.indexOf(d);return p>=0?(p%10):d;});}
  var coords=document.getElementById('stCoords');
  if(coords)coords.addEventListener('change',function(){
    var p=asciiDigits(this.value).trim().split(/[,\s]+/);
    if(p.length>=2){document.getElementById('stLat').value=p[0];document.getElementById('stLng').value=p[1];}
  });
  document.querySelectorAll('#amenChips .chip').forEach(function(c){c.addEventListener('click',function(){c.classList.toggle('active');});});

  var anprCb=document.querySelector('input[name="anpr_enabled"]');
  if(anprCb){
    anprCb.addEventListener('change',function(){
      var pv=document.getElementById('pvAnpr');
      if(pv){pv.className='pill '+(anprCb.checked?'pill-green':'pill-amber');pv.innerHTML=(anprCb.checked?'<span class="pdot"></span>':'')+(anprCb.checked?'فعال':'غیرفعال');}
    });
  }
  var camIdx=document.querySelectorAll('.anpr-cam-row').length;
  var addCam=document.getElementById('addAnprCam');
  if(addCam){
    addCam.addEventListener('click',function(){
      var id='CAM-'+String(camIdx+1).padStart(2,'0');
      var row=document.createElement('div');
      row.className='conn-edit anpr-cam-row';
      row.innerHTML='<div class="ce-ic ic-violet">'+window.icon('camera')+'</div>'+
        '<input class="finput" style="max-width:90px" name="anpr_cameras['+camIdx+'][id]" value="'+id+'" dir="ltr">'+
        '<input class="finput" style="flex:1" name="anpr_cameras['+camIdx+'][name]" placeholder="نام دوربین">'+
        '<input class="finput tnum" style="flex:1" name="anpr_cameras['+camIdx+'][rtsp_url]" placeholder="rtsp://..." dir="ltr">'+
        '<input type="hidden" name="anpr_cameras['+camIdx+'][enabled]" value="1">';
      document.getElementById('anprCameras').appendChild(row);
      camIdx++;
    });
  }
})();
</script>
@endpush
