@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — درخواست جدید')

@section('phone')
@php use App\Support\Persian; @endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.support') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">ثبت درخواست جدید</div></div>
</div>

<form method="POST" action="{{ route('app.ticket.store') }}" id="ticketForm">
@csrf
<input type="hidden" name="category" id="categoryInput" value="payment">
<div class="screen">
    @if($errors->any())
    <div class="card-lite" style="color:var(--red);margin-bottom:12px">{{ $errors->first() }}</div>
    @endif

    <label class="fld-label">موضوع درخواست</label>
    <div class="cat-grid" id="cats">
        <button type="button" class="cat active" data-c="payment"><span class="cat-ic ic-orange" data-ic="wallet"></span>پرداخت و کیف پول</button>
        <button type="button" class="cat" data-c="charger"><span class="cat-ic ic-blue" data-ic="bolt"></span>مشکل در شارژ</button>
        <button type="button" class="cat" data-c="rfid"><span class="cat-ic ic-green" data-ic="chip"></span>کارت RFID</button>
        <button type="button" class="cat" data-c="station"><span class="cat-ic ic-violet" data-ic="pin"></span>ایستگاه و دستگاه</button>
        <button type="button" class="cat" data-c="account"><span class="cat-ic ic-blue" data-ic="user"></span>حساب کاربری</button>
        <button type="button" class="cat" data-c="other"><span class="cat-ic ic-orange" data-ic="receipt"></span>سایر موارد</button>
    </div>

    <label class="fld-label mt20">جلسهٔ مرتبط (اختیاری)</label>
    <select class="input" name="session_id">
        <option value="">— انتخاب جلسهٔ شارژ —</option>
        @foreach($sessions as $s)
            <option value="{{ $s->id }}" @selected(old('session_id') == $s->id)>
                {{ $s->station?->name }} · {{ $s->started_at ? Persian::digits($s->started_at->format('Y/m/d H:i')) : '—' }}
            </option>
        @endforeach
    </select>

    <label class="fld-label mt16">عنوان</label>
    <input class="input" name="subject" id="tTitle" value="{{ old('subject') }}" placeholder="موضوع را کوتاه بنویسید" required maxlength="255">

    <label class="fld-label mt16">شرح درخواست</label>
    <textarea class="input ta" name="body" id="tBody" placeholder="جزئیات مشکل یا درخواست خود را بنویسید…" required maxlength="2000">{{ old('body') }}</textarea>

    <label class="fld-label mt16">پیوست (اختیاری)</label>
    <button type="button" class="attach"><span data-ic="plus"></span> افزودن عکس یا اسکرین‌شات</button>
</div>

<div class="savebar">
    <button type="submit" class="btn btn-orange" style="height:52px;width:100%;font-size:15px"><span data-ic="check"></span> ثبت درخواست</button>
</div>
</form>
@endsection

@push('styles')
<style>
.fld-label{display:block;font-size:12.5px;color:var(--muted);font-weight:600;margin-bottom:8px}
.cat-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.cat{display:flex;flex-direction:column;align-items:flex-start;gap:9px;padding:14px;border:1.5px solid var(--border);border-radius:15px;background:var(--surface);font-family:inherit;font-size:13px;font-weight:700;color:var(--text);text-align:start}
.cat.active{border-color:var(--accent);background:var(--accent-soft)}
.cat-ic{width:40px;height:40px;border-radius:12px;display:grid;place-items:center}.cat-ic svg{width:20px;height:20px}
.input{width:100%;border:1.5px solid var(--border);background:var(--surface-2);border-radius:13px;padding:0 14px;height:50px;font-family:inherit;font-size:14px;color:var(--text);outline:none}
.input:focus{border-color:var(--accent);background:var(--surface)}
select.input{appearance:none}
.ta{height:110px;padding:13px 14px;line-height:1.8;resize:none}
.attach{width:100%;display:flex;align-items:center;justify-content:center;gap:8px;height:52px;border:1.5px dashed var(--border);border-radius:14px;background:var(--surface-2);color:var(--accent);font-family:inherit;font-weight:700;font-size:13.5px}
.attach svg{width:18px;height:18px}
.savebar{position:absolute;bottom:0;left:0;right:0;background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);padding:14px 20px calc(20px + env(safe-area-inset-bottom,0));z-index:20}
.savebar .btn svg{width:18px;height:18px}
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('#cats .cat').forEach(function(b){
  b.addEventListener('click',function(){
    document.querySelectorAll('#cats .cat').forEach(function(x){x.classList.remove('active');});
    b.classList.add('active');
    document.getElementById('categoryInput').value=b.getAttribute('data-c');
  });
});
</script>
@endpush
