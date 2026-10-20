@php
    use App\Http\Controllers\Admin\RfidCardController;
    use App\Support\Persian;
    $card = $card ?? null;
    $cardType = old('card_type', $card?->meta['card_type'] ?? ($card?->organization_id ? 'org' : ($card && !$card->user_id ? 'anon' : 'personal')));
    $defaultTag = $card?->id_tag ?? RfidCardController::generateIdTag();
@endphp
@extends('admin.layouts.app', ['page' => 'tariffs', 'title' => $card ? 'ویرایش کارت RFID' : 'صدور کارت RFID جدید', 'crumb' => 'تعرفه‌ها و RFID'])

@section('content')
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <a href="{{ route('admin.tariffs.index', ['tab' => 'rfid']) }}" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به کارت‌ها</a>
      @if($card)
      <form method="POST" action="{{ route('admin.rfid.destroy', $card) }}" onsubmit="return confirm('این کارت RFID حذف شود؟');">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-sm" style="color:var(--red)"><span class="bic" data-ic="x"></span> حذف کارت</button>
      </form>
      @endif
    </div>

<form method="POST" action="{{ $card ? route('admin.rfid.update', $card) : route('admin.rfid.store') }}">@csrf
@if($card)@method('PUT')@endif
<input type="hidden" name="card_type" id="cardType" value="{{ $cardType }}">
<input type="hidden" name="offline_allowed" id="offlineAllowed" value="{{ old('offline_allowed', $card?->isOfflineAllowed() ? '1' : '0') }}">

    <div class="grid col-2-1">
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
          <div class="card-head"><h3>نوع و دارندهٔ کارت</h3></div>
          <div class="card-pad">
            <div class="ffield col-span" style="margin-bottom:16px">
              <label>نوع کارت</label>
              <div class="card-types" id="cardTypes">
                <button type="button" class="ct {{ $cardType === 'personal' ? 'active' : '' }}" data-t="personal"><span class="ct-ic ic-blue" data-ic="chip"></span><span class="fw7">شخصی</span><span class="fs12 muted">متصل به یک کاربر</span></button>
                <button type="button" class="ct {{ $cardType === 'org' ? 'active' : '' }}" data-t="org"><span class="ct-ic ic-violet" data-ic="users"></span><span class="fw7">سازمانی</span><span class="fs12 muted">ناوگان سازمان</span></button>
                <button type="button" class="ct {{ $cardType === 'anon' ? 'active' : '' }}" data-t="anon"><span class="ct-ic ic-orange" data-ic="card"></span><span class="fw7">بی‌نام</span><span class="fs12 muted">شارژ مهمان</span></button>
              </div>
            </div>
            <div class="form-grid">
              <div class="ffield" id="userField"><label>کاربر / دارنده <span class="req" id="userReq">*</span></label>
                <input type="hidden" name="user_id" id="rfidUserId" value="{{ old('user_id', $card?->user_id) }}">
                <input class="finput" id="rfidUserSearch" value="{{ old('user_search', $card?->user?->name) }}" placeholder="جستجوی نام یا موبایل…" autocomplete="off">
              </div>
              <div class="ffield" id="orgField"><label>سازمان <span class="req" id="orgReq" style="display:none">*</span></label>
                <select class="fselect" name="organization_id" id="rfidOrg">
                  <option value="">—</option>
                  @foreach(\App\Models\Organization::orderBy('name')->get() as $org)
                    <option value="{{ $org->id }}" @selected(old('organization_id', $card?->organization_id) == $org->id)>{{ $org->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><div><h3>شمارهٔ کارت</h3><div class="ch-sub">از طریق خواندن کارت با ریدر یا ورود دستی</div></div></div>
          <div class="card-pad">
            @if(!$card)
            <div class="ffield col-span" style="margin-bottom:14px">
              <div class="seg" style="width:100%"><button type="button" class="active" id="mAuto" style="flex:1">صدور خودکار شماره</button><button type="button" id="mManual" style="flex:1">خواندن با ریدر</button></div>
            </div>
            @endif
            <div class="form-grid">
              <div class="ffield"><label>شمارهٔ کارت (UID)</label><input class="finput tnum" dir="ltr" style="text-align:right" id="uid" name="id_tag" value="{{ old('id_tag', $defaultTag) }}" required {{ $card ? '' : 'readonly' }}></div>
              <div class="ffield"><label>تاریخ انقضا</label><input type="date" class="finput" name="expires_at" value="{{ old('expires_at', $card?->expires_at?->format('Y-m-d')) }}"></div>
            </div>
            <div class="reader-box mt-12" id="readerBox" style="display:none">
              <div class="reader-pulse"><span></span><span></span><span data-ic="chip"></span></div>
              <div class="fw7 mt-12">کارت را روی ریدر USB قرار دهید…</div>
              <div class="fs12 muted">UID را در فیلد بالا وارد کنید</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><h3>تنظیمات شارژ</h3></div>
          <div class="card-pad">
            <div class="form-grid">
              <div class="ffield"><label>برچسب کارت</label><input class="finput" name="card_label" value="{{ old('card_label', $card?->card_label) }}" placeholder="مثلاً کارت VIP"></div>
              <div class="ffield"><label>وضعیت</label>
                <select class="fselect" name="status">
                  <option value="active" @selected(old('status', $card?->status ?? 'active') === 'active')>فعال</option>
                  <option value="blocked" @selected(old('status', $card?->status) === 'blocked')>مسدود</option>
                  <option value="expired" @selected(old('status', $card?->status) === 'expired')>منقضی</option>
                </select>
              </div>
            </div>
            <label class="toggle-row mt-8" id="offlineToggle">
              <div><div class="fw7 fs13">مجاز در حالت آفلاین</div><div class="fhint">امکان شارژ حتی هنگام قطع ارتباط ایستگاه با سرور</div></div>
              <span class="switch {{ old('offline_allowed', $card?->isOfflineAllowed()) ? 'on' : '' }}" data-sw><i></i></span>
            </label>
          </div>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        <div class="rfid-preview">
          <div class="flex between items-center" style="position:relative;z-index:1">
            <span style="font-size:13px;opacity:.9">SafirCharge RFID</span>
            <span class="rp-chip" data-ic="chip"></span>
          </div>
          <div class="rp-uid tnum" id="pvUid">{{ old('id_tag', $defaultTag) }}</div>
          <div class="flex between" style="position:relative;z-index:1">
            <div><div class="rp-l">دارنده</div><div class="rp-v" id="pvHolder">{{ $card?->user?->name ?? '—' }}</div></div>
            <div><div class="rp-l">نوع</div><div class="rp-v" id="pvType">{{ ['personal'=>'شخصی','org'=>'سازمانی','anon'=>'بی‌نام'][$cardType] ?? 'شخصی' }}</div></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="height:48px"><span class="bic" data-ic="{{ $card ? 'check' : 'plus' }}"></span> {{ $card ? 'ذخیرهٔ تغییرات' : 'صدور کارت' }}</button>
      </div>
    </div>
</form>

@if($card && !empty($auditRows))
<div class="card mt16">
    <div class="card-head"><h3>سوابق تغییرات کارت</h3></div>
    <div class="card-pad" style="padding-top:6px">
        @foreach($auditRows as $row)
        <div class="audit-hist-row">
            <div class="audit-hist-ic {{ $row['color'] === 'hist-warn' ? 'ic-orange' : ($row['color'] === 'hist-ok' ? 'ic-green' : 'ic-blue') }}" data-ic="{{ $row['icon'] }}"></div>
            <div class="l-main">
                <div class="l-title">{{ $row['title'] }}</div>
                <div class="l-sub">{{ $row['detail'] }}</div>
            </div>
            <span class="muted fs12">{{ $row['time'] }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.card-types{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.ct{display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;padding:15px 8px;border:1.5px solid var(--border);border-radius:14px;background:var(--surface);font-family:inherit;cursor:pointer}
.ct.active{border-color:var(--accent);background:var(--accent-soft)}
.ct-ic{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;margin-bottom:3px}.ct-ic svg{width:21px;height:21px}
.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer}
.switch{width:44px;height:25px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .16s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}
.reader-box{text-align:center;padding:22px;border-radius:14px;background:var(--surface-2);border:1.5px dashed var(--border)}
.reader-pulse{position:relative;display:grid;place-items:center;height:70px}
.reader-pulse span:nth-child(1),.reader-pulse span:nth-child(2){position:absolute;width:56px;height:56px;border-radius:18px;border:2px solid var(--accent);opacity:0;animation:rpulse 2s ease-out infinite}
.reader-pulse span:nth-child(2){animation-delay:1s}
.reader-pulse [data-ic]{width:48px;height:48px;border-radius:15px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;z-index:1}
.reader-pulse [data-ic] svg{width:24px;height:24px}
@keyframes rpulse{0%{opacity:.5;transform:scale(1)}100%{opacity:0;transform:scale(2)}}
.rfid-preview{position:relative;overflow:hidden;border-radius:20px;padding:22px;color:#fff;background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 45%,var(--violet)));box-shadow:var(--shadow-lg);display:flex;flex-direction:column;gap:22px;min-height:180px}
.rfid-preview::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 85% 0%,rgba(255,255,255,.2),transparent 45%)}
.rp-chip{display:inline-grid;width:32px;height:26px;color:#ffe7a8}.rp-chip svg{width:32px;height:26px}
.rp-uid{font-size:21px;font-weight:700;letter-spacing:.12em;position:relative;z-index:1;direction:ltr;text-align:left}
.rp-l{font-size:11px;opacity:.85}.rp-v{font-size:14px;font-weight:700;margin-top:2px}
.audit-hist-row{display:flex;align-items:flex-start;gap:13px;padding:12px 0;border-bottom:1px solid var(--border-2)}
.audit-hist-row:last-child{border-bottom:none}
.audit-hist-ic{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:none}.audit-hist-ic svg{width:17px;height:17px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  document.querySelectorAll('.audit-hist-ic[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  var typeNames={personal:'شخصی',org:'سازمانی',anon:'بی‌نام'};
  var cardTypeInput=document.getElementById('cardType');
  var offlineInput=document.getElementById('offlineAllowed');

  function applyCardType(t){
    cardTypeInput.value=t;
    document.getElementById('pvType').textContent=typeNames[t]||'شخصی';
    document.getElementById('userReq').style.display=t==='personal'?'':'none';
    document.getElementById('orgReq').style.display=t==='org'?'':'none';
    document.getElementById('userField').style.opacity=t==='anon'?'0.5':'1';
    if(t==='anon'){
      document.getElementById('rfidUserId').value='';
      document.getElementById('rfidUserSearch').value='';
      document.getElementById('pvHolder').textContent='مهمان';
    }
  }

  document.querySelectorAll('#cardTypes .ct').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#cardTypes .ct').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
      applyCardType(b.getAttribute('data-t'));
    });
  });
  applyCardType(cardTypeInput.value);

  if(window.AdminUI) window.AdminUI.bindUserAutocomplete(document.getElementById('rfidUserSearch'),{
    hiddenInput:'#rfidUserId',
    onSelect:function(u){document.getElementById('pvHolder').textContent=u.name;}
  });

  document.getElementById('uid').addEventListener('input',function(){document.getElementById('pvUid').textContent=this.value;});

  var mAuto=document.getElementById('mAuto');
  var mManual=document.getElementById('mManual');
  if(mAuto&&mManual){
    mAuto.addEventListener('click',function(){mAuto.classList.add('active');mManual.classList.remove('active');document.getElementById('readerBox').style.display='none';document.getElementById('uid').readOnly=true;});
    mManual.addEventListener('click',function(){mManual.classList.add('active');mAuto.classList.remove('active');document.getElementById('readerBox').style.display='block';document.getElementById('uid').readOnly=false;document.getElementById('uid').focus();});
  }

  var sw=document.querySelector('[data-sw]');
  if(sw){
    sw.closest('.toggle-row').addEventListener('click',function(){
      sw.classList.toggle('on');
      offlineInput.value=sw.classList.contains('on')?'1':'0';
    });
  }
})();
</script>
@endpush
