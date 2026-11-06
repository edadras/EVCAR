@php $station = $station ?? null; @endphp
@extends('admin.layouts.app', ['page' => 'stations', 'title' => $station ? 'ویرایش ایستگاه' : 'ایجاد ایستگاه جدید', 'crumb' => $station ? $station->name : 'ایستگاه‌ها و نقشه'])

@section('content')
<form method="POST" action="{{ $station ? route('admin.stations.update', $station) : route('admin.stations.store') }}" enctype="multipart/form-data">@csrf
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
              <div class="ffield"><label>اپراتور / مالک</label><select class="fselect" name="partner_id"><option value="">QUAD EV (مستقیم)</option>@foreach($partners ?? [] as $p)<option value="{{ $p->id }}" @selected(old('partner_id', $station?->partner_id)==$p->id)>{{ $p->name }}</option>@endforeach</select></div>
              <div class="ffield col-span"><label>آدرس <span class="req">*</span></label><input class="finput" id="stAddr" name="address" value="{{ old('address', $station?->address) }}" placeholder="استان، شهر، خیابان، پلاک"></div>
              <div class="ffield"><label>مختصات جغرافیایی</label><input class="finput tnum" id="stCoords" dir="ltr" style="text-align:right" placeholder="۳۵٫۶۹۹۲, ۵۱٫۳۳۸۹" value="{{ old('coords', $station?->latitude ? $station->latitude.','.$station->longitude : '') }}"></div>
              <input type="hidden" name="latitude" id="stLat" value="{{ old('latitude', $station?->latitude) }}">
              <input type="hidden" name="longitude" id="stLng" value="{{ old('longitude', $station?->longitude) }}">
              <input type="hidden" name="type" value="{{ old('type', $station?->type ?? 'public') }}">
              @if($station)
              <div class="ffield"><label>وضعیت</label><select class="fselect" id="stStatus" name="status">@foreach(['active'=>'فعال','inactive'=>'غیرفعال','maintenance'=>'تعمیرات'] as $v=>$l)<option value="{{ $v }}" @selected(old('status', $station->status)===$v)>{{ $l }}</option>@endforeach</select></div>
              @endif
              @php
                  $hrs = is_array($station?->operating_hours) ? $station->operating_hours : [];
                  $hrsPreset = old('hours_preset', $station?->hoursPreset() ?? '24h');
              @endphp
              <div class="ffield"><label>ساعات کاری</label>
                <select class="fselect" id="stHours" name="hours_preset">
                  @foreach(\App\Models\Station::HOURS_PRESETS as $k => $p)<option value="{{ $k }}" @selected($hrsPreset === $k)>{{ $p['label'] }}</option>@endforeach
                  <option value="custom" @selected($hrsPreset === 'custom')>ساعت دلخواه…</option>
                </select>
                <div class="flex items-center gap-8 mt-8" id="stHoursCustom" @if($hrsPreset !== 'custom') hidden @endif>
                  <span class="fs12 muted">از</span><input class="finput tnum" type="time" name="hours_open" id="stHoursOpen" value="{{ old('hours_open', $hrs['open'] ?? '08:00') }}" dir="ltr">
                  <span class="fs12 muted">تا</span><input class="finput tnum" type="time" name="hours_close" id="stHoursClose" value="{{ old('hours_close', $hrs['close'] ?? '22:00') }}" dir="ltr">
                </div>
                @error('hours_open')<div class="fs12 mt-4" style="color:var(--red)">{{ $message }}</div>@enderror
              </div>
            </div>
          </div>
        </div>

        <!-- تصویر -->
        @php $stImage = $station?->imageUrl(); @endphp
        <div class="card">
          <div class="card-head"><div><h3>تصویر ایستگاه</h3><div class="ch-sub">در اپ مشتری بالای صفحهٔ ایستگاه و در فهرست نقشه نمایش داده می‌شود</div></div></div>
          <div class="card-pad">
            <div class="st-img-row">
              <label class="st-img-drop" for="stImage">
                <img id="stImgPv" src="{{ $stImage ?? '' }}" alt="" @if(! $stImage) hidden @endif>
                <span class="st-img-empty" id="stImgEmpty" @if($stImage) hidden @endif><span data-ic="camera"></span>انتخاب تصویر</span>
              </label>
              <div style="flex:1;min-width:0">
                <input type="file" id="stImage" name="image" accept="image/jpeg,image/png,image/webp" class="finput" style="padding:8px">
                <span class="fhint">JPG، PNG یا WebP — حداکثر ۴ مگابایت. تصویر افقی (مثلاً ۱۲۰۰×۶۰۰) بهترین نتیجه را دارد.</span>
                @error('image')<div class="fs12 mt-4" style="color:var(--red)">{{ $message }}</div>@enderror
                @if($stImage)
                <label class="flex items-center gap-8 mt-12" style="cursor:pointer;font-size:13px">
                  <input type="checkbox" name="remove_image" value="1" id="stImgRemove" style="width:16px;height:16px;accent-color:var(--red)"> حذف تصویر فعلی
                </label>
                @endif
              </div>
            </div>
          </div>
        </div>

        <!-- کانکتورها -->
        <div class="card">
          <div class="card-head"><div><h3>کانکتورها و شارژرها</h3><div class="ch-sub">نوع کانکتور و توان هر شارژر این ایستگاه</div></div>
            @if($station)
            <div class="head-actions"><a class="btn btn-sm" href="{{ route('admin.stations.chargers.create', $station) }}"><span class="bic" data-ic="plus"></span> افزودن شارژر و کانکتور</a></div>
            @endif
          </div>
          <div class="card-pad" style="padding-top:8px">
            <div style="display:flex;flex-direction:column;gap:10px">
              @forelse($station?->chargers ?? [] as $ch)
              <div class="conn-edit">
                <div class="ce-ic {{ in_array($ch->connector_type, ['Type2','Type1','GBT-AC'], true) ? 'ic-blue' : 'ic-green' }}" data-ic="plug"></div>
                <div style="min-width:110px">
                  <a href="{{ route('admin.chargers.show', $ch) }}" class="fw8 tnum" dir="ltr" style="color:var(--text);text-decoration:none">{{ $ch->charge_point_id }}</a>
                  <div class="fs12 muted">{{ ($ch->meta['name'] ?? null) ? $ch->meta['name'].' · ' : '' }}{{ \App\Support\Persian::digits($ch->connectors->count()) }} کانکتور · <a href="{{ route('admin.chargers.edit', $ch) }}" style="color:var(--accent)">ویرایش کامل</a> · <a href="{{ route('admin.chargers.config', $ch) }}" style="color:var(--accent)">تنظیمات دستگاه</a></div>
                </div>
                <select name="charger_types[{{ $ch->id }}]" aria-label="نوع کانکتور">
                  @if($ch->connector_type && ! array_key_exists($ch->connector_type, \App\Models\Charger::CONNECTOR_TYPES))
                  <option value="{{ $ch->connector_type }}" selected>{{ $ch->connector_type }}</option>
                  @endif
                  @foreach(\App\Models\Charger::CONNECTOR_TYPES as $ct => $label)
                  <option value="{{ $ct }}" @selected(old('charger_types.'.$ch->id, $ch->connector_type) === $ct)>{{ $label }}</option>
                  @endforeach
                </select>
                <input class="finput tnum" type="number" min="1" max="500" name="charger_power[{{ $ch->id }}]" value="{{ old('charger_power.'.$ch->id, $ch->max_power_kw) }}" style="max-width:90px;height:38px" dir="ltr" aria-label="توان (kW)">
                <span class="fs12 muted">kW</span>
              </div>
              <div class="conn-chips">
                @foreach($ch->connectors->sortBy('connector_number') as $cn)
                <span class="conn-chip">کانکتور {{ $cn->meta['name'] ?? (['A','B','C','D'][$cn->connector_number - 1] ?? $cn->connector_number) }}
                  <button type="submit" form="delConn{{ $cn->id }}" title="حذف کانکتور" aria-label="حذف کانکتور"
                    onclick="return confirm('کانکتور {{ $cn->connector_number }} شارژر {{ $ch->charge_point_id }} و QR آن حذف شود؟');" data-ic="x"></button></span>
                @endforeach
              </div>
              @empty
              <div class="fs12 muted">{{ $station ? 'هنوز شارژری برای این ایستگاه ثبت نشده است.' : 'پس از ثبت ایستگاه، شارژرها و کانکتورها را از صفحهٔ ایستگاه اضافه کنید.' }}</div>
              @endforelse
            </div>
          </div>
        </div>

        <!-- امکانات -->
        <div class="card">
          <div class="card-head"><h3>تعرفه و امکانات</h3></div>
          <div class="card-pad">
            <div class="form-grid">
              @php
                  $tariffList = $tariffs ?? collect();
                  $assignedIds = $station ? $station->tariffs->pluck('id')->all() : [];
                  $netDefault = $tariffList->firstWhere('is_default', true);
                  $tariffSel = (string) old('tariff_id', count($assignedIds) > 1 ? 'keep' : ($assignedIds[0] ?? ''));
                  $tariffPrice = function ($t) {
                      $perMin = $t->pricing_model === 'per_minute';
                      $v = (float) ($perMin ? $t->price_per_minute : $t->price_per_kwh);
                      return $v > 0 ? Persian::money((int) round($v / 10)).($perMin ? ' ت/دقیقه' : ' ت/kWh') : null;
                  };
              @endphp
              <div class="ffield col-span"><label>تعرفهٔ پیش‌فرض</label>
                <select class="fselect" name="tariff_id" id="stTariff">
                  @if(count($assignedIds) > 1)
                  <option value="keep" @selected($tariffSel === 'keep')>حفظ تخصیص فعلی ({{ Persian::digits(count($assignedIds)) }} تعرفه — {{ $station->tariffs->pluck('name')->implode('، ') }})</option>
                  @endif
                  <option value="" data-price="{{ $netDefault ? $tariffPrice($netDefault) : '' }}" @selected($tariffSel === '')>پیش‌فرض شبکه{{ $netDefault ? ' — '.$netDefault->name.($tariffPrice($netDefault) ? ' · '.$tariffPrice($netDefault) : '') : '' }}</option>
                  @foreach($tariffList as $t)
                  <option value="{{ $t->id }}" data-price="{{ $tariffPrice($t) }}" @selected($tariffSel === (string) $t->id)>{{ $t->name }}{{ $tariffPrice($t) ? ' — '.$tariffPrice($t) : '' }}{{ ($t->meta['connector_scope'] ?? 'all') !== 'all' ? ' ('.strtoupper($t->meta['connector_scope']).')' : '' }}</option>
                  @endforeach
                </select>
                <span class="fhint">مبنای محاسبهٔ هزینهٔ شارژ در این ایستگاه و قیمتی که مشتری در اپ می‌بیند. تعرفه‌ها از <a href="{{ route('admin.tariffs.index') }}" style="color:var(--accent)">صفحهٔ تعرفه‌ها</a> تعریف می‌شوند.</span>
              </div>
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
              @php $amenSel = old('amenities', $station?->amenities() ?? []); @endphp
              <div class="chips" id="amenChips">
                @foreach(array_keys(\App\Models\Station::AMENITIES) as $am)
                <label class="chip"><input type="checkbox" name="amenities[]" value="{{ $am }}" @checked(in_array($am, $amenSel, true))>{{ $am }}</label>
                @endforeach
              </div>
              <span class="fhint">موارد انتخاب‌شده در صفحهٔ ایستگاه در اپ مشتری نمایش داده می‌شوند.</span>
            </div>
          </div>
        </div>
      </div>

      <!-- پیش‌نمایش -->
      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        @php
            $pvConnCount = $station ? $station->chargers->sum(fn ($c) => $c->connectors->count()) : 0;
            $pvMaxKw = $station ? (int) $station->chargers->max('max_power_kw') : 0;
            $pvTypes = $station ? $station->chargers->pluck('connector_type')->filter()->unique()->map(fn ($t) => \App\Models\Charger::CONNECTOR_TYPES[$t] ?? $t)->implode('، ') : '';
            $pvStatus = ['active' => ['فعال', 'pill-green'], 'inactive' => ['غیرفعال', 'pill-red'], 'maintenance' => ['تعمیرات', 'pill-amber']][$station?->status ?? 'active'];
        @endphp
        <div class="card">
          <div class="card-head"><div><h3>نمایش در اپ مشتری</h3><div class="ch-sub">مشتری ایستگاه را این‌طور می‌بیند</div></div></div>
          <div class="card-pad">
            <div class="pv-app">
              <div class="pv-hero" id="pvHero" @if($stImage) style="background-image:url('{{ $stImage }}')" @endif>
                <span class="pv-hero-ic" id="pvHeroIc" @if($stImage) hidden @endif data-ic="plug"></span>
                <span class="pill {{ $pvStatus[1] }} pv-status" id="pvStatus"><span class="pdot"></span>{{ $pvStatus[0] }}</span>
              </div>
              <div class="pv-body">
                <div class="fw8" id="pvName" style="font-size:15px">{{ $station?->name ?: 'نام ایستگاه' }}</div>
                <div class="muted fs12 mt-4" id="pvAddr" style="line-height:1.6">{{ $station?->address ?: 'آدرس ایستگاه' }}</div>
                <div class="pv-stats">
                  <div><b class="tnum">{{ Persian::digits($pvConnCount) }}</b><span>کانکتور</span></div>
                  <div><b class="tnum">{{ $pvMaxKw ? Persian::digits($pvMaxKw) : '—' }}</b><span>kW حداکثر</span></div>
                  <div><b>{{ $pvMaxKw >= 50 ? 'DC' : ($pvMaxKw ? 'AC' : '—') }}</b><span>{{ $pvMaxKw >= 50 ? 'شارژ سریع' : 'نوع شارژ' }}</span></div>
                </div>
                @if($pvTypes)<div class="fs12 muted mt-8">سوکت: {{ $pvTypes }}</div>@endif
                <div class="pv-meta"><span class="pv-h" id="pvHours">{{ $station?->hoursLabel() ?? '۲۴ ساعته' }}</span><span class="pv-p" id="pvPrice" hidden></span></div>
                <div class="pv-meta" id="pvAmen"></div>
              </div>
            </div>
            <div class="flex between items-center mt-16" style="margin-bottom:8px">
              <b style="font-size:13px">موقعیت روی نقشه</b>
              <span class="fs11 muted">برای جابه‌جایی، پین را بکشید یا روی نقشه کلیک کنید</span>
            </div>
            <div id="miniMap" style="height:200px;border-radius:12px;border:1px solid var(--border);overflow:hidden"></div>
            <div class="flex between mt-12" style="font-size:13px"><span class="muted">شارژ خودکار پلاک</span><span class="pill {{ ($anprEnabled ?? false) ? 'pill-green' : 'pill-amber' }}" id="pvAnpr"><span class="pdot"></span>{{ ($anprEnabled ?? false) ? 'فعال' : 'غیرفعال' }}</span></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="height:48px"><span class="bic" data-ic="plus"></span> {{ $station ? 'به‌روزرسانی ایستگاه' : 'ثبت ایستگاه' }}</button>
      </div>
    </div>
</form>
@foreach($station?->chargers ?? [] as $ch)
@foreach($ch->connectors as $cn)
<form id="delConn{{ $cn->id }}" method="POST" action="{{ route('admin.connectors.destroy', $cn) }}" hidden>@csrf @method('DELETE')</form>
@endforeach
@endforeach
@include('admin.external-stations.map-assets')
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.chips{display:flex;flex-wrap:wrap;gap:8px}
.chip{border:1px solid var(--border);background:var(--surface-2);border-radius:30px;padding:8px 14px;font-size:12.5px;font-weight:600;color:var(--text-2)}
.chip.active,.chip:has(input:checked){background:var(--accent-soft);color:var(--accent-strong);border-color:color-mix(in srgb,var(--accent) 30%,transparent)}
label.chip{cursor:pointer;user-select:none;display:inline-flex;align-items:center;gap:6px}
label.chip input{position:absolute;opacity:0;pointer-events:none}
label.chip:has(input:checked)::before{content:"✓";font-weight:800}
label.chip:has(input:focus-visible){outline:2px solid var(--accent);outline-offset:2px}
#stHoursCustom[hidden]{display:none!important}
#stHoursCustom .finput{max-width:130px}
.pv-meta{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.pv-meta span{font-size:11.5px;font-weight:700;border-radius:20px;padding:3px 9px;background:var(--surface);border:1px solid var(--border-2);color:var(--text-2)}
.pv-meta .pv-h{background:var(--green-soft,#e7f7ee);color:var(--green);border-color:transparent}
.pv-meta .pv-p{color:var(--accent-strong)}
.conn-edit{display:flex;align-items:center;gap:11px;border:1px solid var(--border);border-radius:13px;padding:11px 13px;background:var(--surface-2)}
.conn-edit .ce-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none}.conn-edit .ce-ic svg{width:19px;height:19px}
.conn-edit select{height:38px;border:1px solid var(--border);background:var(--surface);border-radius:9px;font-family:inherit;font-size:12.5px;color:var(--text);padding:0 10px}
.conn-edit .ce-del{width:34px;height:34px;border-radius:9px;border:1px solid var(--border);background:var(--surface);color:var(--red);display:grid;place-items:center;margin-inline-start:auto}.conn-edit .ce-del svg{width:16px;height:16px}
.pv-app{border:1px solid var(--border);border-radius:16px;overflow:hidden;background:var(--surface-2)}
.pv-hero{position:relative;height:120px;background:linear-gradient(140deg,var(--accent),color-mix(in srgb,var(--accent) 50%,var(--orange)));background-size:cover;background-position:center;display:grid;place-items:center;color:rgba(255,255,255,.55)}
.pv-hero-ic svg{width:44px;height:44px}
.pv-hero [hidden]{display:none!important}
.pv-status{position:absolute;bottom:10px;right:10px}
.pv-body{padding:12px 14px 14px}
.pv-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}
.pv-stats div{background:var(--surface);border:1px solid var(--border-2);border-radius:10px;padding:8px 4px;text-align:center;display:flex;flex-direction:column;gap:2px}
.pv-stats b{font-size:15px}.pv-stats span{font-size:11px;color:var(--muted)}
.st-img-row{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap}
.st-img-drop{width:220px;height:120px;border:1.5px dashed var(--border);border-radius:14px;background:var(--surface-2);display:grid;place-items:center;overflow:hidden;cursor:pointer;flex:none}
.st-img-drop img{width:100%;height:100%;object-fit:cover}
.st-img-drop [hidden]{display:none!important}
.st-img-empty{display:flex;flex-direction:column;align-items:center;gap:6px;font-size:12.5px;color:var(--text-2)}.st-img-empty svg{width:26px;height:26px}
.conn-chips{display:flex;flex-wrap:wrap;gap:6px;margin:-4px 4px 4px}
.conn-chip{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--border);border-radius:20px;padding:4px 6px 4px 10px;font-size:12px;background:var(--surface)}
.conn-chip button{width:20px;height:20px;border-radius:50%;border:none;background:var(--red-soft,#fde8ea);color:var(--red);display:grid;place-items:center;cursor:pointer;padding:0}.conn-chip button svg{width:12px;height:12px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  function bindText(inputId,targetId,fallback){
    var el=document.getElementById(inputId);
    if(el)el.addEventListener('input',function(){document.getElementById(targetId).textContent=this.value||fallback;});
  }
  bindText('stName','pvName','نام ایستگاه');
  bindText('stAddr','pvAddr','آدرس ایستگاه');
  var stStatus=document.getElementById('stStatus');
  if(stStatus)stStatus.addEventListener('change',function(){
    var map={active:['فعال','pill-green'],inactive:['غیرفعال','pill-red'],maintenance:['تعمیرات','pill-amber']}[this.value];
    var pv=document.getElementById('pvStatus');pv.className='pill '+map[1]+' pv-status';pv.innerHTML='<span class="pdot"></span>'+map[0];
  });

  function asciiDigits(s){return (s||'').replace(/[۰-۹٠-٩]/g,function(d){var p='۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩'.indexOf(d);return p>=0?(p%10):d;});}
  var coords=document.getElementById('stCoords'), latIn=document.getElementById('stLat'), lngIn=document.getElementById('stLng');
  var lat0=parseFloat(latIn.value), lng0=parseFloat(lngIn.value), hasPos=!isNaN(lat0)&&!isNaN(lng0);
  var mini=L.map('miniMap',{zoomControl:true,attributionControl:false}).setView(hasPos?[lat0,lng0]:[35.6997,51.3380],hasPos?15:11);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(mini);
  var pin=null;
  function setPos(lat,lng,fromInput){
    lat=+(+lat).toFixed(7);lng=+(+lng).toFixed(7);
    latIn.value=lat;lngIn.value=lng;
    if(!fromInput&&coords)coords.value=lat+', '+lng;
    if(!pin){
      pin=L.marker([lat,lng],{draggable:true,icon:window.scLogoPin('/assets/brand/quad-icon-256.png','quad')}).addTo(mini);
      pin.on('dragend',function(){var p=pin.getLatLng();setPos(p.lat,p.lng);});
    }else pin.setLatLng([lat,lng]);
  }
  if(hasPos)setPos(lat0,lng0,true);
  mini.on('click',function(e){setPos(e.latlng.lat,e.latlng.lng);});
  if(coords)coords.addEventListener('change',function(){
    var p=asciiDigits(this.value).trim().split(/[,\s]+/).map(parseFloat);
    if(p.length>=2&&!isNaN(p[0])&&!isNaN(p[1])){setPos(p[0],p[1],true);mini.setView([p[0],p[1]],15);}
  });
  setTimeout(function(){mini.invalidateSize();},200);
  var imgIn=document.getElementById('stImage');
  if(imgIn)imgIn.addEventListener('change',function(){
    var f=this.files&&this.files[0];if(!f)return;
    var pv=document.getElementById('stImgPv');
    var url=URL.createObjectURL(f);
    pv.src=url;pv.hidden=false;
    document.getElementById('stImgEmpty').hidden=true;
    document.getElementById('pvHero').style.backgroundImage='url("'+url+'")';
    document.getElementById('pvHeroIc').hidden=true;
    var rm=document.getElementById('stImgRemove');if(rm)rm.checked=false;
  });
  function faDigits(s){return String(s).replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];});}
  var hrsSel=document.getElementById('stHours'), hrsBox=document.getElementById('stHoursCustom'),
      hOpen=document.getElementById('stHoursOpen'), hClose=document.getElementById('stHoursClose');
  function syncHours(){
    var custom=hrsSel.value==='custom';
    hrsBox.hidden=!custom;
    document.getElementById('pvHours').textContent=custom
      ? faDigits((hOpen.value||'--:--')+' تا '+(hClose.value||'--:--'))
      : hrsSel.options[hrsSel.selectedIndex].text;
  }
  [hrsSel,hOpen,hClose].forEach(function(el){el.addEventListener('change',syncHours);el.addEventListener('input',syncHours);});
  syncHours();

  var trSel=document.getElementById('stTariff');
  function syncPrice(){
    var o=trSel.options[trSel.selectedIndex], p=o&&o.getAttribute('data-price'), el=document.getElementById('pvPrice');
    el.hidden=!p;el.textContent=p||'';
  }
  trSel.addEventListener('change',syncPrice);syncPrice();

  function syncAmen(){
    var box=document.getElementById('pvAmen');box.innerHTML='';
    document.querySelectorAll('#amenChips input:checked').forEach(function(c){var s=document.createElement('span');s.textContent=c.value;box.appendChild(s);});
  }
  document.querySelectorAll('#amenChips input').forEach(function(c){c.addEventListener('change',syncAmen);});
  syncAmen();

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
