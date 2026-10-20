@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — کارت RFID')

@section('phone')
@php
$presenter = app(\App\Services\App\MobileAppPresenter::class);
$activeCard = $cards->firstWhere('status', 'active') ?? $cards->first();
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.account') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">کارت RFID</div></div>
</div>

<div class="screen">
    @if(session('success'))
    <div class="flash ok">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="flash err">{{ $errors->first() }}</div>
    @endif

    @if($activeCard)
    <div class="rfid-card rise">
        <div class="row between" style="position:relative;z-index:1">
            <span style="font-size:12px;opacity:.9">کارت متصل</span>
            <span class="rc-chip" data-ic="chip"></span>
        </div>
        <div class="rc-num tnum">{{ $presenter->formatRfidTag($activeCard->id_tag) }}</div>
        <div class="row between" style="position:relative;z-index:1">
            <div>
                <div class="rc-l">دارنده</div>
                <div class="rc-v">{{ $activeCard->card_label ?: $user->name }}</div>
            </div>
            <span class="pill" style="background:rgba(255,255,255,.2);color:#fff">
                <span class="pdot" style="background:{{ $activeCard->status === 'active' ? '#7ee2a6' : '#fca5a5' }}"></span>
                {{ $presenter->rfidStatusLabel($activeCard->status) }}
            </span>
        </div>
    </div>
    @else
    <div class="card card-p rise">
        <p class="fs13 muted" style="line-height:1.8;margin:0">کارتی ثبت نشده است. شماره کارت را به‌صورت دستی وارد کنید.</p>
    </div>
    @endif

    <div class="sec-title mt16">کارت‌های من</div>
    <div style="display:flex;flex-direction:column;gap:12px">
        @forelse($cards as $card)
        <div class="rcard {{ $card->status === 'active' ? 'active' : '' }}">
            <div class="rc-top">
                <div class="rc-ic" data-ic="chip"></div>
                <div style="flex:1;min-width:0">
                    <div class="fw7 fs14 tnum" dir="ltr" style="text-align:right">{{ $presenter->formatRfidTag($card->id_tag) }}</div>
                    <div class="fs12 muted mt4">{{ $card->card_label ?: 'بدون نام' }}</div>
                </div>
                <span class="pill {{ $card->status === 'active' ? 'pill-green' : ($card->status === 'blocked' ? 'pill-red' : 'pill-muted') }}">
                    {{ $presenter->rfidStatusLabel($card->status) }}
                </span>
            </div>
            <div class="rc-actions">
                <button type="button" class="rc-edit" data-edit
                    data-id="{{ $card->id }}"
                    data-label="{{ $card->card_label }}"
                    data-tag="{{ $card->id_tag }}">ویرایش</button>
                @if($card->status === 'blocked')
                <form method="POST" action="{{ route('app.rfid.activate', $card) }}" class="inline-form">
                    @csrf
                    <button type="submit" class="rc-act">فعال‌سازی</button>
                </form>
                @elseif($card->status === 'active')
                <form method="POST" action="{{ route('app.rfid.block', $card) }}" class="inline-form" onsubmit="return confirm('این کارت مسدود شود؟')">
                    @csrf
                    <button type="submit" class="rc-block">مسدود</button>
                </form>
                @endif
                <form method="POST" action="{{ route('app.rfid.destroy', $card) }}" class="inline-form" onsubmit="return confirm('این کارت حذف شود؟ سوابق شارژ حفظ می‌ماند.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rc-del" title="حذف">✕</button>
                </form>
            </div>
        </div>
        @empty
        <p class="muted fs13">هنوز کارتی ثبت نکرده‌اید.</p>
        @endforelse
    </div>

    <div class="sec-title mt16">ثبت کارت جدید</div>
    <form method="POST" action="{{ route('app.rfid.store') }}" id="rfidForm">
        @csrf
        <div class="card card-p">
            <div class="field">
                <label>شمارهٔ کارت RFID</label>
                <input class="input tnum" name="id_tag" dir="ltr" style="text-align:center;letter-spacing:.1em" placeholder="CN00001234" value="{{ old('id_tag') }}" required>
            </div>
            <div class="field" style="margin-bottom:0">
                <label>نام کارت (اختیاری)</label>
                <input class="input" name="card_label" placeholder="مثلاً کارت اصلی" value="{{ old('card_label') }}">
            </div>
        </div>
    </form>

    <div class="note-box mt16">
        <span data-ic="shield"></span>
        <span>کارت‌های دارای سوابق شارژ فقط به‌صورت حذف منطقی حذف می‌شوند تا داده‌های مالی حفظ شود.</span>
    </div>

    @include('app.partials.change-history', ['auditRows' => $auditRows ?? []])
</div>

<div class="savebar">
    <button type="submit" form="rfidForm" class="btn"><span data-ic="check"></span> ثبت کارت جدید</button>
</div>
@endsection

@push('overlays')
<div class="sheet-overlay rfid-sheet" id="rfidSheet">
    <form method="POST" action="" id="rfidEditForm" class="rfid-sheet-form">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <div class="sheet" id="rfidSheetPanel">
            <div class="sheet-grab"></div>
            <div class="sheet-head">
                <h3>ویرایش کارت</h3>
                <button type="button" class="ab-btn" id="closeRfidSheet"><span data-ic="back"></span></button>
            </div>
            <label class="fld-l">شماره کارت</label>
            <input class="input tnum" id="editTag" dir="ltr" style="text-align:center;letter-spacing:.1em" readonly>

            <label class="fld-l mt16" for="editLabel">نام کارت</label>
            <input class="input" name="card_label" id="editLabel" placeholder="مثلاً کارت اصلی">

            <button type="submit" class="btn btn-orange sheet-cta mt20"><span data-ic="check"></span> ذخیره تغییرات</button>
        </div>
    </form>
</div>
@endpush

@push('styles')
<style>
.flash{padding:12px 14px;border-radius:12px;font-size:13px;margin-bottom:12px}
.flash.ok{background:var(--green-soft);color:var(--green);border:1px solid color-mix(in srgb,var(--green) 25%,transparent)}
.flash.err{background:var(--red-soft);color:var(--red);border:1px solid color-mix(in srgb,var(--red) 25%,transparent)}
.rfid-card{position:relative;overflow:hidden;border-radius:20px;padding:20px;color:#fff;background:linear-gradient(135deg,#16A34A,#0e7a38);box-shadow:0 14px 30px rgba(22,163,74,.32);display:flex;flex-direction:column;gap:18px}
.rfid-card::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 88% 0%,rgba(255,255,255,.2),transparent 45%)}
.rc-chip{display:inline-grid;width:30px;height:24px;color:#ffe7a8}.rc-chip svg{width:30px;height:24px}
.rc-num{font-size:18px;font-weight:700;letter-spacing:.12em;position:relative;z-index:1;direction:ltr;text-align:left}
.rc-l{font-size:11px;opacity:.85}.rc-v{font-size:14px;font-weight:700;margin-top:2px}
.rcard{padding:14px 16px;border:1.5px solid var(--border);border-radius:16px;background:var(--surface);box-shadow:var(--shadow-sm)}
.rcard.active{border-color:var(--green)}
.rc-top{display:flex;align-items:center;gap:12px}
.rc-ic{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;background:var(--accent-soft);color:var(--accent-strong);flex:none}.rc-ic svg{width:20px;height:20px}
.rc-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-2)}
.inline-form{display:inline;margin:0}
.rc-edit,.rc-act,.rc-block{border:1px solid var(--border);background:var(--surface-2);color:var(--text);font-family:inherit;font-weight:700;font-size:12px;padding:7px 12px;border-radius:9px;cursor:pointer}
.rc-act{border-color:var(--green);background:var(--green-soft);color:var(--green)}
.rc-block{border-color:var(--red);background:var(--red-soft);color:var(--red)}
.rc-del{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:var(--surface);color:var(--red);display:grid;place-items:center;cursor:pointer;font-family:inherit;font-size:14px;line-height:1}
.pill-red{background:var(--red-soft);color:var(--red)}.pill-muted{background:var(--surface-3);color:var(--muted)}
.note-box{display:flex;align-items:flex-start;gap:10px;padding:13px;border-radius:14px;background:var(--surface-2);border:1px solid var(--border-2);font-size:12.5px;color:var(--text-2);line-height:1.7}
.note-box svg{width:18px;height:18px;color:var(--accent);flex:none;margin-top:2px}
.field{margin-bottom:14px}.field label{display:block;font-size:12.5px;color:var(--muted);font-weight:600;margin-bottom:8px}
.input{width:100%;border:1.5px solid var(--border);background:var(--surface-2);border-radius:13px;padding:0 14px;height:50px;font-family:inherit;font-size:14px;color:var(--text);outline:none}
.hist-card{border:1px solid var(--border);border-radius:16px;background:var(--surface);padding:4px 14px;margin-bottom:24px}
.hist-row{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-2)}
.hist-row:last-child{border-bottom:none}
.hist-ic{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:none}.hist-ic svg{width:17px;height:17px}
.hist-ok{background:var(--green-soft);color:var(--green)}
.hist-warn{background:var(--red-soft);color:var(--red)}
.hist-info{background:var(--accent-soft);color:var(--accent-strong)}
.hist-main{flex:1;min-width:0}
.hist-time{flex:none;white-space:nowrap}
.savebar{position:absolute;bottom:0;left:0;right:0;padding:12px 18px calc(16px + env(safe-area-inset-bottom,0));background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);z-index:20}
.savebar .btn{width:100%;height:52px}
.rfid-sheet{position:fixed;inset:0;background:rgba(8,16,24,.5);backdrop-filter:blur(2px);display:flex;align-items:flex-end;justify-content:center;opacity:0;visibility:hidden;transition:opacity .22s;z-index:200}
.rfid-sheet.open{opacity:1;visibility:visible}
.rfid-sheet-form{width:100%;max-width:var(--phone-w);transform:translateY(100%);transition:transform .28s cubic-bezier(.2,.7,.3,1)}
.rfid-sheet.open .rfid-sheet-form{transform:none}
.rfid-sheet .sheet{width:100%;background:var(--surface);border-radius:26px 26px 0 0;padding:10px 20px calc(20px + env(safe-area-inset-bottom,0))}
.sheet-grab{width:42px;height:5px;border-radius:5px;background:var(--border);margin:4px auto 12px}
.sheet-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}.sheet-head h3{font-size:17px}
.fld-l{display:block;font-size:12.5px;color:var(--muted);font-weight:600;margin-bottom:8px}
.sheet-cta{width:100%;height:52px;font-size:15px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var sheet=document.getElementById('rfidSheet');
  var panel=document.getElementById('rfidSheetPanel');
  var form=document.getElementById('rfidEditForm');
  var updateTpl=@json(route('app.rfid.update', ['card' => '__ID__']));

  function openEdit(btn){
    form.action=updateTpl.replace('__ID__',btn.getAttribute('data-id'));
    document.getElementById('editTag').value=btn.getAttribute('data-tag');
    document.getElementById('editLabel').value=btn.getAttribute('data-label')||'';
    sheet.classList.add('open');
    document.body.style.overflow='hidden';
    document.querySelectorAll('#rfidSheet [data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  }

  function closeSheet(){
    sheet.classList.remove('open');
    document.body.style.overflow='';
  }

  document.querySelectorAll('[data-edit]').forEach(function(btn){
    btn.addEventListener('click',function(){openEdit(btn);});
  });
  document.getElementById('closeRfidSheet').addEventListener('click',closeSheet);
  sheet.addEventListener('click',function(e){if(e.target===sheet)closeSheet();});
  panel.addEventListener('click',function(e){e.stopPropagation();});
  document.querySelectorAll('.hist-ic[data-ic],.rc-ic[data-ic],.rc-chip[data-ic]').forEach(function(el){
    el.innerHTML=window.icon(el.getAttribute('data-ic'));
  });
})();
</script>
@endpush
